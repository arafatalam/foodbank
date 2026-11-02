<?php

// Run explicitly with: php tests/Integration/DistributionConcurrency.php
// A uniquely named temporary MySQL database keeps practice stock untouched.
use App\Actions\Distribution\CreateDistribution;
use App\Actions\PackageSizes\GetPackageSizeDefinitionKey;
use App\Actions\StockItems\CreateStockItem;
use App\Enums\RoleCode;
use App\Models\Category;
use App\Models\Department;
use App\Models\Household;
use App\Models\PackageSize;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockEntry;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$worker = ($argv[1] ?? '') === 'worker';
$name = $worker ? $argv[2] : 'foodbank_distribution_test_'.bin2hex(random_bytes(6));
if (! preg_match('/^foodbank_distribution_test_[a-f0-9]{12}$/', $name)) {
    throw new RuntimeException('Refusing an unexpected test database name.');
}
if (config('database.default') !== 'mysql') {
    throw new RuntimeException('This explicit integration check requires MySQL.');
}

// Retain a separate administrative connection solely for this disposable database.
$admin = $worker ? null : DB::connection()->getPdo();
if ($admin) {
    $admin->exec("CREATE DATABASE `$name`");
}
config(['database.connections.mysql.database' => $name, 'database.connections.mysql.url' => null]);
DB::purge('mysql');

if ($worker) {
    $data = json_decode(file_get_contents($argv[3].'/payload.json'), true, flags: JSON_THROW_ON_ERROR);
    $actor = User::findOrFail($data['actor']);
    file_put_contents($argv[3].'/ready-'.$argv[4], 'ready');
    try {
        app(CreateDistribution::class)->create($data['payload'], $actor);
        echo 'saved';
    } catch (ValidationException $exception) {
        if (! isset($exception->errors()['items'])) {
            throw $exception;
        }
        echo 'rejected';
    }
    exit;
}

$directory = sys_get_temp_dir().'/'.$name;
mkdir($directory, 0700);
$processes = [];
try {
    Artisan::call('migrate', ['--force' => true]);
    $role = Role::create(['code' => RoleCode::MANAGER->value]);
    $actor = User::factory()->create(['role_id' => $role->id]);
    $household = Household::create(['first_name' => 'Concurrency', 'last_name' => 'Sample']);
    $department = Department::create(['name' => 'Food']);
    $category = Category::create(['name' => 'Grains', 'department_id' => $department->id]);
    $product = Product::create(['name' => 'Rice', 'category_id' => $category->id]);
    $definition = ['package_type' => 'bag', 'contents' => '5', 'contents_unit' => 'kg'];
    $package = new PackageSize($definition);
    $package->definition_key = (new GetPackageSizeDefinitionKey)->get($definition);
    $package->save();
    $item = app(CreateStockItem::class)->create(['product_id' => $product->id,
        'package_size_id' => $package->id, 'brand' => null], $actor);
    // Test fixture: two people each request four of the same five packages.
    $item->available_quantity = 5;
    $item->save();
    file_put_contents($directory.'/payload.json', json_encode(['actor' => $actor->id,
        'payload' => ['household_id' => $household->id,
            'items' => [['stock_item_id' => $item->id, 'quantity' => 4]]]], JSON_THROW_ON_ERROR));

    // Hold the first lock until both independent processes are ready to save.
    DB::beginTransaction();
    Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
    for ($index = 0; $index < 2; $index++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $name, $directory, (string) $index],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start a test worker.');
        }
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    $deadline = microtime(true) + 15;
    while (count(glob($directory.'/ready-*')) !== 2) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Workers did not become ready in time.');
        }
        usleep(20000);
    }
    usleep(200000);
    foreach ($processes as [$process]) {
        if (! proc_get_status($process)['running']) {
            throw new RuntimeException('Worker finished while the lock was held.');
        }
    }
    DB::commit();
    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $results[] = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Worker failed: '.$error);
        }
    }
    $processes = [];
    sort($results);
    if ($results !== ['rejected', 'saved'] || $item->fresh()->available_quantity !== 1
        || StockEntry::count() !== 1 || StockMovement::count() !== 1
        || StockMovement::sole()->resulting_balance !== 1) {
        throw new RuntimeException('Concurrent saves did not preserve stock and history.');
    }
    echo "PASS: two overlapping MySQL saves; one saved, one rejected; balance 1; one entry/movement.\n";
} finally {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    foreach ($processes as [$process]) {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
    DB::disconnect('mysql');
    $admin->exec("DROP DATABASE `$name`");
    foreach (glob($directory.'/*') as $path) {
        unlink($path);
    }
    rmdir($directory);
}
