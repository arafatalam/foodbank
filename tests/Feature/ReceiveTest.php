<?php

use App\Actions\PackageSizes\GetPackageSizeDefinitionKey;
use App\Actions\StockItems\CreateStockItem;
use App\Enums\RoleCode;
use App\Models\Category;
use App\Models\Department;
use App\Models\PackageSize;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockEntry;
use App\Models\StockItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function receiveActor(RoleCode $role): User
{
    return User::factory()->create([
        'first_name' => 'Clara', 'last_name' => 'Roy',
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true, 'must_change_password' => false,
    ]);
}

function receiveRiceItem(): StockItem
{
    $department = Department::create(['name' => 'Food']);
    $category = Category::create(['name' => 'Grains', 'department_id' => $department->id]);
    $product = Product::create(['name' => 'Rice', 'category_id' => $category->id]);
    $data = ['package_type' => 'bag', 'contents' => '5', 'contents_unit' => 'kg'];
    $package = new PackageSize($data);
    $package->definition_key = (new GetPackageSizeDefinitionKey)->get($data);
    $package->save();

    return app(CreateStockItem::class)->create([
        'product_id' => $product->id, 'package_size_id' => $package->id, 'brand' => 'Tilda',
    ], receiveActor(RoleCode::MANAGER));
}

test('each permitted role sees Receive navigation and saves a receipt', function (RoleCode $role) {
    $item = receiveRiceItem();
    $actor = receiveActor($role);
    $this->actingAs($actor)->get(route('receive.create'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('receive/Create')->where('auth.navigation.receive', true)
        ->has('stockItems', 1));

    // Numeric strings from an HTML form must become whole-number movement data.
    $this->post(route('receive.store'), [
        'items' => [['stock_item_id' => (string) $item->id, 'quantity' => '3']],
        'notes' => 'Opening pantry stock: three 5 kg rice bags.',
    ])->assertRedirect(route('receive.create'))->assertSessionHasNoErrors();

    $entry = StockEntry::sole();
    expect($entry->created_by)->toBe($actor->id)
        ->and($entry->type)->toBe('receive')
        ->and($entry->notes)->toBe('Opening pantry stock: three 5 kg rice bags.')
        ->and($item->fresh()->available_quantity)->toBe(3);
    $this->assertDatabaseHas('stock_movements', [
        'stock_entry_id' => $entry->id, 'stock_item_id' => $item->id,
        'direction' => 'add', 'quantity' => 3, 'resulting_balance' => 3,
    ]);
})->with([RoleCode::SUPER_ADMIN, RoleCode::MANAGER, RoleCode::VOLUNTEER]);

test('oversized receipt notes return validation errors without saving stock', function (string $character) {
    $item = receiveRiceItem();
    $this->actingAs(receiveActor(RoleCode::MANAGER))
        ->from(route('receive.create'))->post(route('receive.store'), [
            'items' => [['stock_item_id' => $item->id, 'quantity' => 2]],
            'notes' => str_repeat($character, 10001),
        ])->assertRedirect(route('receive.create'))->assertSessionHasErrors('notes');

    $this->assertDatabaseCount('stock_entries', 0);
    $this->assertDatabaseCount('stock_movements', 0);
    expect($item->fresh()->available_quantity)->toBe(0);
})->with(['a', '🥫']);

test('receipt notes accept the character limit including multibyte text', function (string $character) {
    $item = receiveRiceItem();
    $note = str_repeat($character, 10000);
    $this->actingAs(receiveActor(RoleCode::MANAGER))->post(route('receive.store'), [
        'items' => [['stock_item_id' => $item->id, 'quantity' => 2]],
        'notes' => $note,
    ])->assertRedirect(route('receive.create'))->assertSessionHasNoErrors();

    expect(StockEntry::sole()->notes)->toBe($note)
        ->and($item->fresh()->available_quantity)->toBe(2);
})->with(['a', '🥫']);

test('a receipt may omit its note', function () {
    $item = receiveRiceItem();
    $this->actingAs(receiveActor(RoleCode::MANAGER))->post(route('receive.store'), [
        'items' => [['stock_item_id' => $item->id, 'quantity' => 2]],
    ])->assertRedirect(route('receive.create'))->assertSessionHasNoErrors();

    expect(StockEntry::sole()->notes)->toBeNull();
});
