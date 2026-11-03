<?php

// Run explicitly: php tests/Integration/UsernameConcurrency.php
// All fixtures live in a disposable MySQL database.
use App\Actions\Users\GenerateUsername;
use App\Actions\Users\SaveUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$worker = ($argv[1] ?? '') === 'worker';
$name = $worker ? $argv[2] : 'foodbank_username_test_'.bin2hex(random_bytes(6));
if (! preg_match('/^foodbank_username_test_[a-f0-9]{12}$/', $name) || config('database.default') !== 'mysql') {
    throw new RuntimeException('Expected disposable MySQL test database.');
}
$admin = $worker ? null : DB::connection()->getPdo();
if ($admin) {
    $admin->exec("CREATE DATABASE `$name`");
}
config(['database.connections.mysql.database' => $name, 'database.connections.mysql.url' => null, 'auth.initial_password' => 'IntegrationCheck123!']);
DB::purge('mysql');

if ($worker) {
    $directory = $argv[3];
    $index = $argv[4];
    $payload = json_decode(file_get_contents($directory.'/payload-'.$index.'.json'), true, flags: JSON_THROW_ON_ERROR);
    $generator = new class($directory, $index) extends GenerateUsername
    {
        private bool $firstAttempt = true;

        public function __construct(private string $directory, private string $index) {}

        public function generate(string $firstName, string $lastName, ?User $ignoreUser = null): string
        {
            $candidate = parent::generate($firstName, $lastName, $ignoreUser);
            if ($this->firstAttempt) {
                $this->firstAttempt = false;
                // Both workers must choose their candidate before either saves.
                file_put_contents($this->directory.'/ready-'.$this->index, $candidate);
                $deadline = microtime(true) + 15;
                while (! file_exists($this->directory.'/release')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('Timed out waiting for the second worker.');
                    }
                    usleep(10000);
                }
            }

            return $candidate;
        }
    };
    $saved = (new SaveUser($generator))->save($payload['data'], isset($payload['id']) ? User::findOrFail($payload['id']) : null);
    echo $saved->username;
    exit;
}

$directory = sys_get_temp_dir().'/'.$name;
mkdir($directory, 0700);
$processes = [];
try {
    Artisan::call('migrate', ['--force' => true]);
    $role = Role::create(['code' => 'volunteer']);
    foreach (['create-create', 'create-edit', 'edit-edit'] as $scenario) {
        $base = ['first_name' => str_replace('-', '', $scenario), 'last_name' => 'Example', 'phone' => '+1 506-555-0123', 'role_id' => $role->id, 'is_active' => true];
        $before = User::count();
        foreach ([0, 1] as $index) {
            $data = [...$base, 'email' => $scenario.$index.'@example.test'];
            $payload = ['data' => $data];
            if ($scenario === 'edit-edit' || ($scenario === 'create-edit' && $index === 1)) {
                $user = User::factory()->create(['role_id' => $role->id, 'email' => $data['email']]);
                $payload['id'] = $user->id;
            }
            file_put_contents($directory.'/payload-'.$index.'.json', json_encode($payload, JSON_THROW_ON_ERROR));
            $process = proc_open([PHP_BINARY, __FILE__, 'worker', $name, $directory, (string) $index], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (! is_resource($process)) {
                throw new RuntimeException('Could not start test worker.');
            }
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        $deadline = microtime(true) + 15;
        while (count(glob($directory.'/ready-*')) !== 2) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not reach the generation barrier.');
            }
            usleep(10000);
        }
        $candidate = file_get_contents($directory.'/ready-0');
        if ($candidate !== file_get_contents($directory.'/ready-1')) {
            throw new RuntimeException('The test did not force a username collision.');
        }
        touch($directory.'/release');
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
        if ($results !== [$candidate, $candidate.'.01'] || User::count() !== $before + 2) {
            throw new RuntimeException('Both accounts must save with distinct usernames and no partial records.');
        }
        echo "PASS $scenario: both chose $candidate; saved ".implode(', ', $results).".\n";
        foreach (glob($directory.'/*') as $path) {
            unlink($path);
        }
    }
} finally {
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
