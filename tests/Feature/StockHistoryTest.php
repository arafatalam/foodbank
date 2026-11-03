<?php

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
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // All records live in the isolated in-memory testing database.
    $this->actor = User::factory()->create(['first_name' => 'Test', 'last_name' => 'Recorder', 'is_active' => true, 'must_change_password' => false,
        'role_id' => Role::firstOrCreate(['code' => RoleCode::MANAGER->value])->id]);
    $department = Department::create(['name' => 'Food']);
    $category = Category::create(['name' => 'Grains', 'department_id' => $department->id]);
    $this->product = Product::create(['name' => 'Rice', 'category_id' => $category->id]);
    $data = ['package_type' => 'bag', 'contents' => '5', 'contents_unit' => 'kg'];
    $package = new PackageSize($data);
    $package->definition_key = (new GetPackageSizeDefinitionKey)->get($data);
    $package->save();
    $this->item = app(CreateStockItem::class)->create(['product_id' => $this->product->id, 'package_size_id' => $package->id, 'brand' => null], $this->actor);
    $this->url = route('stock-items.history', $this->item);
});

test('history permissions and list flags agree for all roles', function (RoleCode $role) {
    $this->actor->update(['role_id' => Role::firstOrCreate(['code' => $role->value])->id]);
    $allowed = $role !== RoleCode::VOLUNTEER;
    $this->actingAs($this->actor->fresh())->get($this->url)->assertStatus($allowed ? 200 : 403);
    $this->get(route('stock-items.index'))->assertInertia(fn (Assert $page) => $page->where('stockItems.data.0.canViewHistory', $allowed));
})->with(RoleCode::cases());

test('guests inactive accounts and password setup cannot browse history', function () {
    $this->get($this->url)->assertRedirect(route('login'));
    $this->actor->update(['is_active' => false]);
    $this->actingAs($this->actor->fresh())->get($this->url)->assertRedirect();
    $this->actor->update(['is_active' => true, 'must_change_password' => true]);
    $this->actingAs($this->actor->fresh())->get($this->url)->assertRedirect(route('password.setup.show'));
});

test('empty history provides identity and missing items return 404', function () {
    $this->actingAs($this->actor)->get($this->url)->assertInertia(fn (Assert $page) => $page->component('stock-items/History')
        ->where('stockItem', ['id' => $this->item->id, 'product' => 'Rice', 'path' => 'Food / Grains', 'brand' => 'Unbranded', 'package' => 'Bag of 5 kg', 'available_quantity' => 0])
        ->has('movements.data', 0)->where('movements.total', 0)->where('movements.next_page_url', null));
    $this->get('/stock-items/999999/history')->assertNotFound();
    $this->get('/stock-items/no/history')->assertNotFound();
});

test('real receive distribute and adjustments preserve and expose their own context', function () {
    $household = Household::create(['first_name' => 'Sample', 'last_name' => 'Family']);
    $this->actingAs($this->actor);
    $this->post(route('receive.store'), ['notes' => "Donation\nSecond line", 'items' => [['stock_item_id' => $this->item->id, 'quantity' => 10]]])->assertSessionHasNoErrors();
    $this->post(route('distribution.store'), ['household_id' => $household->id, 'items' => [['stock_item_id' => $this->item->id, 'quantity' => 3]]])->assertSessionHasNoErrors();
    foreach (['add', 'remove'] as $direction) {
        $this->post(route('adjustment.store'), ['stock_item_id' => $this->item->id, 'direction' => $direction, 'quantity' => 1, 'reason' => 'Count correction'])->assertSessionHasNoErrors();
    }
    $before = StockMovement::all()->toArray();
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->has('movements.data', 4)->where('stockItem.available_quantity', 7)
        ->where('movements.data.0.type', 'adjust')->where('movements.data.0.direction', 'remove')->where('movements.data.0.resulting_balance', 7)
        ->where('movements.data.0.reason', 'Count correction')->where('movements.data.0.notes', null)
        ->where('movements.data.1.direction', 'add')->where('movements.data.1.resulting_balance', 8)
        ->where('movements.data.2.type', 'distribute')->where('movements.data.2.household', 'Sample Family')->where('movements.data.2.quantity', 3)
        ->where('movements.data.3.type', 'receive')->where('movements.data.3.notes', "Donation\nSecond line")
        ->where('movements.data.3.recorder', 'Test Recorder')->where('movements.data.3.resulting_balance', 10)
        ->where('movements.data.3.recorded_at', StockEntry::first()->created_at->toIso8601String()));
    expect(StockMovement::all()->toArray())->toBe($before);
    $this->delete(route('stock-items.destroy', $this->item))->assertForbidden();
});

test('history scopes items and paginates deterministically newest first', function () {
    // Equal timestamps exercise the secondary ID ordering across page boundaries.
    $ids = [];
    for ($i = 0; $i < 12; $i++) {
        $entry = StockEntry::create(['type' => 'receive', 'created_by' => $this->actor->id]);
        $movement = $entry->movements()->create(['stock_item_id' => $this->item->id, 'direction' => 'add', 'quantity' => 1, 'resulting_balance' => $i + 1]);
        $movement->created_at = '2026-10-31 12:00:00';
        $movement->save();
        $ids[] = $movement->id;
    }
    $other = app(CreateStockItem::class)->create(['product_id' => $this->product->id, 'package_size_id' => $this->item->package_size_id, 'brand' => 'Other'], $this->actor);
    $entry->movements()->create(['stock_item_id' => $other->id, 'direction' => 'add', 'quantity' => 99, 'resulting_balance' => 99]);
    $this->actingAs($this->actor)->get($this->url)->assertInertia(fn (Assert $page) => $page->has('movements.data', 10)->where('movements.total', 12)
        ->where('movements.data.0.id', $ids[11])->where('movements.data.9.id', $ids[2])->where('movements.last_page', 2));
    $this->get($this->url.'?page=2')->assertInertia(fn (Assert $page) => $page->has('movements.data', 2)
        ->where('movements.data.0.id', $ids[1])->where('movements.data.1.id', $ids[0])->where('movements.next_page_url', null));
});

test('history uses current catalogue path and sorts different dates before IDs', function () {
    $older = StockEntry::create(['type' => 'receive', 'created_by' => $this->actor->id]);
    $newerMovement = $older->movements()->create(['stock_item_id' => $this->item->id, 'direction' => 'add', 'quantity' => 1, 'resulting_balance' => 1]);
    $newerMovement->created_at = '2026-11-02 12:00:00';
    $newerMovement->save();
    $another = StockEntry::create(['type' => 'receive', 'created_by' => $this->actor->id]);
    $olderMovement = $another->movements()->create(['stock_item_id' => $this->item->id, 'direction' => 'add', 'quantity' => 1, 'resulting_balance' => 2]);
    $olderMovement->created_at = '2026-11-01 12:00:00';
    $olderMovement->save();
    $department = Department::create(['name' => 'Pantry']);
    $category = Category::create(['name' => 'Staples', 'department_id' => $department->id]);
    $this->product->update(['category_id' => $category->id]);
    $this->actingAs($this->actor)->get($this->url)->assertInertia(fn (Assert $page) => $page
        ->where('stockItem.path', 'Pantry / Staples')->where('movements.data.0.id', $newerMovement->id)
        ->where('movements.data.1.id', $olderMovement->id));
});
