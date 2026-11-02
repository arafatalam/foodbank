<?php

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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Use disposable database records; never change the developer's stock.
    $this->actor = User::factory()->create(['is_active' => true, 'must_change_password' => false,
        'role_id' => Role::firstOrCreate(['code' => RoleCode::MANAGER->value])->id]);
    $this->household = Household::create(['first_name' => 'Test', 'last_name' => 'Household']);
    $department = Department::create(['name' => 'Food']);
    $category = Category::create(['name' => 'Grains', 'department_id' => $department->id]);
    $product = Product::create(['name' => 'Rice', 'category_id' => $category->id]);
    $data = ['package_type' => 'bag', 'contents' => '5', 'contents_unit' => 'kg'];
    $package = new PackageSize($data);
    $package->definition_key = (new GetPackageSizeDefinitionKey)->get($data);
    $package->save();
    $this->items = collect(['Brand A', 'Brand B'])->map(function ($brand) use ($product, $package) {
        $item = app(CreateStockItem::class)->create(['product_id' => $product->id,
            'package_size_id' => $package->id, 'brand' => $brand], $this->actor);
        $item->available_quantity = 5;
        $item->save();

        return $item;
    });
    $this->payload = ['household_id' => $this->household->id, 'items' => $this->items->map(fn ($item) => ['stock_item_id' => $item->id, 'quantity' => 2])->all()];
});

test('all agreed roles can open and save a multi-item distribution', function (RoleCode $role) {
    $this->actor->update(['role_id' => Role::firstOrCreate(['code' => $role->value])->id]);
    $this->actingAs($this->actor->fresh())->get(route('distribution.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('distribution/Create')
            ->where('auth.navigation.distribution', true)->has('households', 1)->has('stockItems', 2));
    $this->post(route('distribution.store'), $this->payload)->assertRedirect(route('distribution.create'))->assertSessionHasNoErrors();
    $entry = StockEntry::sole();
    expect($entry->type)->toBe('distribute')->and($entry->created_by)->toBe($this->actor->id)
        ->and($entry->household->id)->toBe($this->household->id)->and($entry->created_at)->not->toBeNull();
    $this->assertDatabaseCount('stock_movements', 2);
    foreach ($this->items as $item) {
        expect($item->fresh()->available_quantity)->toBe(3);
        $this->assertDatabaseHas('stock_movements', ['stock_entry_id' => $entry->id,
            'stock_item_id' => $item->id, 'direction' => 'remove', 'quantity' => 2, 'resulting_balance' => 3]);
    }
})->with(RoleCode::cases());

test('invalid distribution data leaves every balance and history unchanged', function (string $case, string $field) {
    $data = $this->payload;
    match ($case) {
        'household' => $data['household_id'] = 999999,
        'empty' => $data['items'] = [],
        'duplicate' => $data['items'][1]['stock_item_id'] = $data['items'][0]['stock_item_id'],
        'missing' => $data['items'][1]['stock_item_id'] = 999999,
        'zero' => $data['items'][1]['quantity'] = 0,
        'negative' => $data['items'][1]['quantity'] = -1,
        'fraction' => $data['items'][1]['quantity'] = 1.5,
        'overflow' => $data['items'][1]['quantity'] = 4294967296,
        'excess' => $data['items'][1]['quantity'] = 6,
        'extra' => $data['items'][1]['direction'] = 'add',
    };
    $this->actingAs($this->actor)->from(route('distribution.create'))
        ->post(route('distribution.store'), $data)->assertRedirect(route('distribution.create'))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('stock_entries', 0);
    $this->assertDatabaseCount('stock_movements', 0);
    foreach ($this->items as $item) {
        expect($item->fresh()->available_quantity)->toBe(5);
    }
})->with([
    ['household', 'household_id'], ['empty', 'items'], ['duplicate', 'items.0.stock_item_id'],
    ['missing', 'items.1.stock_item_id'], ['zero', 'items.1.quantity'], ['negative', 'items.1.quantity'],
    ['fraction', 'items.1.quantity'], ['overflow', 'items.1.quantity'], ['excess', 'items'], ['extra', 'items.1'],
]);

test('numeric strings can exhaust stock and exhausted choices disappear', function () {
    $data = ['household_id' => (string) $this->household->id,
        'items' => [['stock_item_id' => (string) $this->items[0]->id, 'quantity' => '5']]];
    $this->actingAs($this->actor)->post(route('distribution.store'), $data)->assertSessionHasNoErrors();
    expect($this->items[0]->fresh()->available_quantity)->toBe(0);
    $this->get(route('distribution.create'))->assertInertia(fn (Assert $page) => $page
        ->has('stockItems', 1)->where('stockItems.0.id', $this->items[1]->id));
    // A second stale request must not spend the same stock again.
    $this->post(route('distribution.store'), $data)->assertSessionHasErrors('items');
    $this->assertDatabaseCount('stock_entries', 1);
});

test('a failure during the second movement rolls back the first movement and balance', function () {
    $writes = 0;
    StockMovement::creating(function () use (&$writes) {
        if (++$writes === 2) {
            throw new RuntimeException('Simulated storage failure');
        }
    });
    try {
        expect(fn () => app(CreateDistribution::class)->create($this->payload, $this->actor))
            ->toThrow(RuntimeException::class, 'Simulated storage failure');
    } finally {
        StockMovement::flushEventListeners();
    }
    $this->assertDatabaseCount('stock_entries', 0);
    $this->assertDatabaseCount('stock_movements', 0);
    foreach ($this->items as $item) {
        expect($item->fresh()->available_quantity)->toBe(5);
    }
});

test('guests cannot display or submit distribution', function () {
    $this->get(route('distribution.create'))->assertRedirect(route('login'));
    $this->post(route('distribution.store'), $this->payload)->assertRedirect(route('login'));
    $this->assertDatabaseCount('stock_entries', 0);
});

test('policy denial protects both routes and the action', function () {
    Gate::before(fn () => false);
    $this->actingAs($this->actor)->get(route('distribution.create'))->assertForbidden();
    $this->post(route('distribution.store'), $this->payload)->assertForbidden();
    expect(fn () => app(CreateDistribution::class)->create($this->payload, $this->actor))
        ->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('stock_entries', 0);
});

test('inactive accounts cannot use existing sessions to distribute', function () {
    $this->actor->update(['is_active' => false]);
    $this->actingAs($this->actor)->get(route('distribution.create'))->assertRedirect(route('login'));
    $this->actingAs($this->actor)->post(route('distribution.store'), $this->payload)->assertRedirect(route('login'));
    $this->assertDatabaseCount('stock_entries', 0);
});

test('unfinished accounts must complete setup before distribution', function () {
    $this->actor->update(['must_change_password' => true]);
    $this->actingAs($this->actor)->get(route('distribution.create'))->assertRedirect(route('password.setup.show'));
    $this->post(route('distribution.store'), $this->payload)->assertRedirect(route('password.setup.show'));
    $this->assertDatabaseCount('stock_entries', 0);
});

test('submitted metadata cannot forge the recorder type or saved time', function () {
    $this->travelTo(now()->startOfSecond());
    $data = $this->payload + ['created_by' => 999999, 'type' => 'receive', 'created_at' => '2000-01-01 00:00:00'];
    $this->actingAs($this->actor)->post(route('distribution.store'), $data)->assertSessionHasNoErrors();
    $entry = StockEntry::sole();
    expect($entry->type)->toBe('distribute')->and($entry->created_by)->toBe($this->actor->id)
        ->and($entry->created_at->equalTo(now()))->toBeTrue();
});
