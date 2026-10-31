<?php

use App\Actions\PackageSizes\GetPackageSizeDefinitionKey;
use App\Enums\RoleCode;
use App\Models\Category;
use App\Models\Department;
use App\Models\PackageSize;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function packageSizeEditor(RoleCode $role = RoleCode::MANAGER): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true,
        'must_change_password' => false,
    ]);
}

function packageSizeFixture(string $contents = '1', string $unit = 'kg'): PackageSize
{
    $data = ['package_type' => 'bag', 'contents' => $contents, 'contents_unit' => $unit];
    $packageSize = new PackageSize($data);
    $packageSize->definition_key = (new GetPackageSizeDefinitionKey)->get($data);
    $packageSize->save();

    return $packageSize;
}

test('package managers can list definitions and edit without replacing their identity', function (RoleCode $role) {
    $packageSize = packageSizeFixture();
    $this->actingAs(packageSizeEditor($role));

    $this->get(route('package-sizes.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('package-sizes/Index')
            ->where('canCreate', true)->has('packageSizes.data', 1)
            ->where('packageSizes.data.0.canUpdate', true)
            ->where('auth.navigation.packageSizes', true));

    $this->get(route('package-sizes.edit', $packageSize))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('package-sizes/Edit')
            ->where('packageSize.id', $packageSize->id));

    $this->patch(route('package-sizes.update', $packageSize), [
        'package_type' => 'box', 'contents' => '12', 'contents_unit' => 'item',
        'definition_key' => 'untrusted-key', 'id' => $packageSize->id + 100,
    ])->assertRedirect(route('package-sizes.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('package_sizes', [
        'id' => $packageSize->id, 'package_type' => 'box',
        'contents' => 12, 'contents_unit' => 'item', 'definition_key' => 'box:item:12',
    ]);
    $this->assertDatabaseCount('package_sizes', 1);
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('package lists paginate ten definitions per page', function () {
    foreach (range(1, 11) as $contents) {
        packageSizeFixture((string) $contents);
    }
    $this->actingAs(packageSizeEditor());

    $this->get(route('package-sizes.index'))->assertInertia(fn (Assert $page) => $page
        ->has('packageSizes.data', 10)->where('packageSizes.total', 11));
    $this->get(route('package-sizes.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page
        ->has('packageSizes.data', 1)->where('packageSizes.current_page', 2));
});

test('editing can keep an equivalent definition but cannot duplicate another package', function () {
    $packageSize = packageSizeFixture();
    packageSizeFixture('2');
    $this->actingAs(packageSizeEditor());

    $this->patch(route('package-sizes.update', $packageSize), [
        'package_type' => 'bag', 'contents' => '1000', 'contents_unit' => 'g',
    ])->assertSessionHasNoErrors();

    $this->from(route('package-sizes.edit', $packageSize))
        ->patch(route('package-sizes.update', $packageSize), [
            'package_type' => 'bag', 'contents' => '2000', 'contents_unit' => 'g',
        ])->assertSessionHasErrors('contents');

    expect($packageSize->fresh()->definition_key)->toBe('bag:g:1000000');
});

test('invalid package edits leave the saved definition unchanged', function (array $input, string $field) {
    $packageSize = packageSizeFixture();
    $this->actingAs(packageSizeEditor())->from(route('package-sizes.edit', $packageSize))
        ->patch(route('package-sizes.update', $packageSize), array_replace([
            'package_type' => 'bag', 'contents' => '1', 'contents_unit' => 'kg',
        ], $input))->assertSessionHasErrors($field);

    expect($packageSize->fresh()->definition_key)->toBe('bag:g:1000000');
})->with([
    'fractional items' => [['contents' => '12.5', 'contents_unit' => 'item'], 'contents'],
    'zero' => [['contents' => '0'], 'contents'],
    'too precise' => [['contents' => '1.0001'], 'contents'],
    'unknown unit' => [['contents_unit' => 'lb'], 'contents_unit'],
    'unknown package' => [['package_type' => 'crate'], 'package_type'],
]);

test('volunteers cannot list or edit package definitions or see their sidebar link', function () {
    $packageSize = packageSizeFixture();
    $this->actingAs(packageSizeEditor(RoleCode::VOLUNTEER));
    $this->get(route('package-sizes.index'))->assertForbidden();
    $this->get(route('package-sizes.edit', $packageSize))->assertForbidden();
    $this->patch(route('package-sizes.update', $packageSize), [
        'package_type' => 'box', 'contents' => '12', 'contents_unit' => 'item',
    ])->assertForbidden();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.navigation.packageSizes', false));
});

test('guests must sign in before listing or editing package sizes', function () {
    $packageSize = packageSizeFixture();
    $this->get(route('package-sizes.index'))->assertRedirect(route('login'));
    $this->get(route('package-sizes.edit', $packageSize))->assertRedirect(route('login'));
    $this->patch(route('package-sizes.update', $packageSize))->assertRedirect(route('login'));
});

test('a referenced definition cannot be opened or submitted for editing', function () {
    $packageSize = packageSizeFixture();
    // Exercise the actual stock schema rather than recreating a temporary table.
    $department = Department::create(['name' => 'Food']);
    $category = Category::create(['name' => 'Grains', 'department_id' => $department->id]);
    $product = Product::create(['name' => 'Rice', 'category_id' => $category->id]);
    StockItem::create(['product_id' => $product->id, 'package_size_id' => $packageSize->id, 'brand' => null, 'brand_key' => '']);
    $this->actingAs(packageSizeEditor());
    $this->get(route('package-sizes.index'))->assertInertia(fn (Assert $page) => $page
        ->where('packageSizes.data.0.canUpdate', false));
    $this->get(route('package-sizes.edit', $packageSize))->assertForbidden();
    $this->patch(route('package-sizes.update', $packageSize), [
        'package_type' => 'box', 'contents' => '12', 'contents_unit' => 'item',
    ])->assertForbidden();
    expect($packageSize->fresh()->definition_key)->toBe('bag:g:1000000');
});
