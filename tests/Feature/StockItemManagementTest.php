<?php

use App\Actions\PackageSizes\GetPackageSizeDefinitionKey;
use App\Actions\StockItems\ListStockItems;
use App\Enums\RoleCode;
use App\Models\Category;
use App\Models\Department;
use App\Models\PackageSize;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\StockProductSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

function stockEditor(RoleCode $role = RoleCode::MANAGER): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true, 'must_change_password' => false,
    ]);
}

function stockProduct(string $name = 'Rice'): Product
{
    $department = Department::firstOrCreate(['name' => 'Food']);
    $category = Category::firstOrCreate(['name' => 'Grains', 'department_id' => $department->id]);

    return Product::create(['name' => $name, 'category_id' => $category->id]);
}

function stockPackage(string $contents = '5', string $unit = 'kg'): PackageSize
{
    $data = ['package_type' => 'bag', 'contents' => $contents, 'contents_unit' => $unit];
    $package = new PackageSize($data);
    $package->definition_key = (new GetPackageSizeDefinitionKey)->get($data);
    $package->save();

    return $package;
}

function stockSummaryUnit(Product $product): ?string
{
    return StockProductSetting::query()->where('product_id', $product->id)->first()?->summary_unit;
}

function stockInput(Product $product, PackageSize $package, ?string $brand = 'Tilda'): array
{
    return ['product_id' => $product->id, 'package_size_id' => $package->id, 'brand' => $brand];
}

test('stock measurement settings belong to their own table rather than the catalogue', function () {
    expect(Schema::hasColumn('products', 'summary_unit'))->toBeFalse();
    $product = stockProduct();
    $package = stockPackage();
    // Load database defaults before comparing the saved Product before and after setup.
    $originalProduct = $product->fresh()->getAttributes();
    $this->actingAs(stockEditor())->post(route('stock-items.store'), stockInput($product, $package))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stock_product_settings', ['product_id' => $product->id, 'summary_unit' => 'kg']);
    expect($product->fresh()->getAttributes())->toBe($originalProduct);
    expect(StockProductSetting::firstOrFail()->product->id)->toBe($product->id);
});

test('management can create edit list and delete unused stock with server owned balance and key', function (RoleCode $role) {
    $product = stockProduct();
    $package = stockPackage();
    $this->actingAs(stockEditor($role));
    $this->get(route('stock-items.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('stock-items/Create')->has('products', 1)->has('packageSizes', 1));
    $this->from(route('stock-items.create'))->post(route('stock-items.store'), [
        ...stockInput($product, $package, ' Royal   Umbrella '), 'brand_key' => 'forged', 'available_quantity' => 100,
    ])->assertRedirect(route('stock-items.create'))->assertSessionHasNoErrors();
    $item = StockItem::firstOrFail();
    expect($item->brand)->toBe('Royal Umbrella')->and($item->brand_key)->toBe('royal umbrella')
        ->and($item->available_quantity)->toBe(0)->and(stockSummaryUnit($product))->toBe('kg');
    $this->get(route('stock-items.index'))->assertInertia(fn (Assert $page) => $page
        ->has('stockItems.data', 1)->where('stockItems.data.0.total_quantity', '0 kg')
        ->where('stockItems.data.0.canDelete', true)->where('auth.navigation.stockItems', true));
    $this->get(route('stock-items.edit', $item))->assertOk();
    $this->patch(route('stock-items.update', $item), stockInput($product, $package, 'ROYAL UMBRELLA'))
        ->assertRedirect(route('stock-items.index'))->assertSessionHasNoErrors();
    expect($item->fresh()->brand)->toBe('ROYAL UMBRELLA');
    $this->delete(route('stock-items.destroy', $item))->assertRedirect(route('stock-items.index'));
    expect(stockSummaryUnit($product))->toBeNull();
    $this->assertDatabaseCount('stock_items', 0);
    $this->assertDatabaseHas('package_sizes', ['id' => $package->id]);
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('normalized brands and unbranded combinations cannot be duplicated', function (?string $first, ?string $duplicate) {
    $product = stockProduct();
    $package = stockPackage();
    $this->actingAs(stockEditor());
    $this->post(route('stock-items.store'), stockInput($product, $package, $first))->assertSessionHasNoErrors();
    $this->post(route('stock-items.store'), stockInput($product, $package, $duplicate))->assertSessionHasErrors('brand');
    $this->assertDatabaseCount('stock_items', 1);
})->with([['Tilda', ' TILDA '], ['Royal Umbrella', ' royal   UMBRELLA '], [null, '   ']]);

test('invalid input is rejected without creating stock', function (array $changes, string $field) {
    $product = stockProduct();
    $package = stockPackage();
    $this->actingAs(stockEditor())->post(route('stock-items.store'), array_replace(stockInput($product, $package), $changes))
        ->assertSessionHasErrors($field);
    $this->assertDatabaseCount('stock_items', 0);
})->with([
    [['product_id' => 999999], 'product_id'],
    [['package_size_id' => 999999], 'package_size_id'],
    [['brand' => str_repeat('a', 256)], 'brand'],
    [['brand' => ['wrong']], 'brand'],
]);

test('compatible packages coexist while incompatible families are rejected', function () {
    $product = stockProduct();
    $kg = stockPackage();
    $g = stockPackage('500', 'g');
    $litres = stockPackage('1', 'litre');
    $this->actingAs(stockEditor());
    $this->post(route('stock-items.store'), stockInput($product, $kg))->assertSessionHasNoErrors();
    $this->post(route('stock-items.store'), stockInput($product, $g))->assertSessionHasNoErrors();
    $this->post(route('stock-items.store'), stockInput($product, $litres))->assertSessionHasErrors('package_size_id');
    $this->patch(route('stock-items.update', StockItem::firstOrFail()), stockInput($product, $litres))
        ->assertSessionHasErrors('package_size_id');
    expect(stockSummaryUnit($product))->toBe('kg');
    $this->assertDatabaseCount('stock_items', 2);
});

test('sole unused edits recalculate the unit and moving products clears the old unit', function () {
    $product = stockProduct();
    $other = stockProduct('Oil');
    $kg = stockPackage();
    $litres = stockPackage('1', 'litre');
    $this->actingAs(stockEditor())->post(route('stock-items.store'), stockInput($product, $kg));
    $item = StockItem::firstOrFail();
    $this->patch(route('stock-items.update', $item), stockInput($product, $litres))->assertSessionHasNoErrors();
    expect(stockSummaryUnit($product))->toBe('litre');
    $this->patch(route('stock-items.update', $item), stockInput($other, $litres))->assertSessionHasNoErrors();
    expect(stockSummaryUnit($product))->toBeNull()->and(stockSummaryUnit($other))->toBe('litre');
});

test('search covers product and brand before ten row pagination and includes zero balances', function () {
    $product = stockProduct();
    $package = stockPackage();
    $this->actingAs(stockEditor());
    foreach (range(1, 12) as $number) {
        $this->post(route('stock-items.store'), stockInput($product, $package, 'Brand '.$number))->assertSessionHasNoErrors();
    }
    $this->get(route('stock-items.index', ['search' => 'rICE']))->assertInertia(fn (Assert $page) => $page
        ->has('stockItems.data', 10)->where('stockItems.total', 12)->where('stockItems.data.0.available_quantity', 0));
    $this->get(route('stock-items.index', ['search' => 'rICE', 'page' => 2]))->assertInertia(fn (Assert $page) => $page
        ->has('stockItems.data', 2)->where('stockItems.current_page', 2));
    $this->get(route('stock-items.index', ['search' => 'BRAND 12']))->assertInertia(fn (Assert $page) => $page->has('stockItems.data', 1));
    $this->get(route('stock-items.index', ['search' => 'missing']))->assertInertia(fn (Assert $page) => $page->has('stockItems.data', 0));
});

test('volunteers have read access but cannot create edit delete or manage packages', function () {
    $product = stockProduct();
    $package = stockPackage();
    $this->actingAs(stockEditor())->post(route('stock-items.store'), stockInput($product, $package));
    $item = StockItem::firstOrFail();
    $this->actingAs(stockEditor(RoleCode::VOLUNTEER));
    $this->get(route('stock-items.index'))->assertInertia(fn (Assert $page) => $page
        ->where('canCreate', false)->where('stockItems.data.0.canUpdate', false)->where('stockItems.data.0.canDelete', false));
    $this->get(route('stock-items.create'))->assertForbidden();
    $this->post(route('stock-items.store'), stockInput($product, $package))->assertForbidden();
    $this->get(route('stock-items.edit', $item))->assertForbidden();
    $this->patch(route('stock-items.update', $item), stockInput($product, $package))->assertForbidden();
    $this->delete(route('stock-items.destroy', $item))->assertForbidden();
    $this->delete(route('package-sizes.destroy', $package))->assertForbidden();
});

test('guests are redirected from every stock setup endpoint', function () {
    foreach ([['get', 'stock-items.index'], ['get', 'stock-items.create'], ['post', 'stock-items.store']] as [$verb, $name]) {
        $this->{$verb}(route($name))->assertRedirect(route('login'));
    }
});

test('referenced packages cannot change or be deleted and unused definitions can be deleted', function () {
    $product = stockProduct();
    $package = stockPackage();
    $unused = stockPackage('2');
    $this->actingAs(stockEditor())->post(route('stock-items.store'), stockInput($product, $package));
    $this->patch(route('package-sizes.update', $package), [
        'package_type' => 'bag', 'contents' => '6', 'contents_unit' => 'kg',
    ])->assertForbidden();
    $this->delete(route('package-sizes.destroy', $package))->assertForbidden();
    $this->delete(route('package-sizes.destroy', $unused))->assertRedirect(route('package-sizes.index'));
    expect(PackageSize::find($package->id))->not->toBeNull();
});

test('database foreign keys protect Product and Package references', function () {
    $product = stockProduct();
    $package = stockPackage();
    $this->actingAs(stockEditor())->post(route('stock-items.store'), stockInput($product, $package));
    expect(fn () => $package->delete())->toThrow(QueryException::class);
    expect(fn () => $product->delete())->toThrow(QueryException::class);
});

test('any movement locks identity and deletion even at zero while spelling corrections remain allowed', function () {
    $product = stockProduct();
    $package = stockPackage();
    $other = stockPackage('1');
    $this->actingAs(stockEditor())->post(route('stock-items.store'), stockInput($product, $package));
    $item = StockItem::firstOrFail();
    // A restrictive reference fixture verifies setup guards before the later movement module exists.
    Schema::create('stock_movements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
    });
    try {
        $item->getConnection()->table('stock_movements')->insert(['stock_item_id' => $item->id]);
        $this->patch(route('stock-items.update', $item), stockInput($product, $other))->assertSessionHasErrors('package_size_id');
        $this->patch(route('stock-items.update', $item), stockInput($product, $package, 'TILDA'))->assertSessionHasNoErrors();
        $this->delete(route('stock-items.destroy', $item))->assertForbidden();
        expect(fn () => $item->delete())->toThrow(QueryException::class);
    } finally {
        Schema::dropIfExists('stock_movements');
    }
});

test('measurement display calculates exactly before rounding to three decimals', function (string $contents, string $unit, int $count, string $expected) {
    $product = stockProduct();
    $package = stockPackage($contents, $unit);
    $item = new StockItem([
        'product_id' => $product->id, 'package_size_id' => $package->id,
        'brand' => null, 'brand_key' => '',
    ]);
    $item->available_quantity = $count;
    $item->save();
    $rows = (new ListStockItems)->get(['search' => ''], stockEditor())->items();
    expect($rows[0]['total_quantity'])->toBe($expected);
})->with([
    ['500', 'g', 3, '1.5 kg'], ['0.333', 'g', 3, '0.001 kg'],
    ['750', 'ml', 3, '2.25 L'], ['12', 'item', 3, '36 items'],
    ['999999999.999', 'kg', 4294967295, '4294967294995705032.705 kg'],
]);
