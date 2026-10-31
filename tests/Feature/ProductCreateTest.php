<?php

use App\Actions\Products\CreateProduct;
use App\Actions\Products\UpdateProduct;
use App\Enums\RoleCode;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

function productCreator(RoleCode $role = RoleCode::MANAGER): User
{
    return User::factory()->create(['role_id' => Role::firstOrCreate(['code' => $role->value])->id, 'is_active' => true, 'must_change_password' => false]);
}
function productPath(): Category
{
    return Department::create(['name' => 'Food'])->categories()->create(['name' => 'Grains']);
}
function productInput(Category $category): array
{
    return ['department_id' => $category->department_id, 'category_id' => $category->id, 'name' => '  Rice  '];
}

test('catalogue editors create generic products directly under categories', function (RoleCode $role) {
    $category = productPath();
    $this->actingAs(productCreator($role))->get(route('products.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('products/Create')->has('departments', 1)->has('categories', 1)->missing('subcategories')->missing('tags'));
    $this->from(route('products.create'))->post(route('products.store'), [...productInput($category), 'is_active' => false, 'brand' => 'Ignored', 'quantity' => 100, 'tag_ids' => [999], 'id' => 999])
        ->assertRedirect(route('products.create'))->assertSessionHasNoErrors();
    $product = Product::sole();
    expect($product->name)->toBe('Rice')->and($product->category_id)->toBe($category->id)->and($product->is_active)->toBeTrue();
    expect($product->getAttributes())->not->toHaveKeys(['brand', 'subcategory_id', 'quantity', 'tag_ids', 'department_id']);
    expect($category->products->modelKeys())->toBe([$product->id]);
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('same category duplicates are blocked even with confirmation and inactive matches', function () {
    $category = productPath();
    $category->products()->create(['name' => 'Rice', 'is_active' => false]);
    $this->actingAs(productCreator())->post(route('products.store'), [...productInput($category), 'name' => '  rICE  ', 'confirm_duplicate_name' => true])->assertSessionHasErrors('name');
    $this->assertDatabaseCount('products', 1);
});

test('database also prevents case and space variants in one category', function () {
    $category = productPath();
    $category->products()->create(['name' => 'Rice']);
    expect(fn () => $category->products()->create(['name' => ' rice ']))->toThrow(QueryException::class);
});

test('cross category names require a warning and explicit confirmation', function () {
    $category = productPath();
    $other = $category->department->categories()->create(['name' => 'Pantry Staples']);
    $other->products()->create(['name' => 'Rice', 'is_active' => false]);
    $this->actingAs(productCreator())->from(route('products.create'))->post(route('products.store'), productInput($category))
        ->assertSessionHasErrors('confirm_duplicate_name')
        ->assertSessionHas('errors', fn ($errors) => str_contains($errors->first('confirm_duplicate_name'), 'Food → Pantry Staples → Rice'));
    $this->assertDatabaseCount('products', 1);
    $this->post(route('products.store'), [...productInput($category), 'confirm_duplicate_name' => true])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('products', 2);
});

test('invalid fields and mismatched hierarchy never create a product', function (array $override, string $field) {
    $category = productPath();
    $this->actingAs(productCreator())->post(route('products.store'), array_replace(productInput($category), $override))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('products', 0);
})->with([
    [['name' => '   '], 'name'], [['name' => ['bad']], 'name'], [['name' => str_repeat('x', 256)], 'name'],
    [['department_id' => 9999], 'department_id'], [['category_id' => 9999], 'category_id'],
    [['category_id' => [1]], 'category_id'], [['confirm_duplicate_name' => 'yes'], 'confirm_duplicate_name'],
]);

test('a category cannot be submitted under another department', function () {
    $category = productPath();
    $other = Department::create(['name' => 'Personal Care']);
    $this->actingAs(productCreator())->post(route('products.store'), [...productInput($category), 'department_id' => $other->id])->assertSessionHasErrors('category_id');
    $this->assertDatabaseCount('products', 0);
});

test('edit choices contain the saved direct category and no stock fields', function () {
    $category = productPath();
    $product = $category->products()->create(['name' => 'Rice']);
    $this->actingAs(productCreator())->get(route('products.edit', $product))->assertOk()->assertInertia(fn (Assert $page) => $page->component('products/Edit')
        ->where('product', ['id' => $product->id, 'name' => 'Rice', 'category_id' => $category->id, 'department_id' => $category->department_id])
        ->missing('subcategories')->missing('tags'));
});

test('renaming or moving requires confirmation for another category match', function (string $change) {
    $category = productPath();
    $other = $category->department->categories()->create(['name' => 'Pantry Staples']);
    $third = $category->department->categories()->create(['name' => 'Other']);
    $product = $category->products()->create(['name' => $change === 'rename' ? 'Oats' : 'Rice']);
    $other->products()->create(['name' => 'Rice']);
    $input = [...productInput($category), 'name' => 'Rice'];
    if ($change === 'move') {
        $input['category_id'] = $third->id;
    }
    $this->actingAs(productCreator())->patch(route('products.update', $product), $input)->assertSessionHasErrors('confirm_duplicate_name');
    expect($product->fresh()->name)->toBe($change === 'rename' ? 'Oats' : 'Rice');
    expect($product->fresh()->category_id)->toBe($category->id);
    $this->patch(route('products.update', $product), [...$input, 'confirm_duplicate_name' => true])->assertRedirect(route('products.index'))->assertSessionHasNoErrors();
    expect($product->fresh()->category_id)->toBe($input['category_id'])->and($product->fresh()->name)->toBe('Rice');
})->with(['rename', 'move']);

test('moving into an existing same category name is blocked even when confirmed', function () {
    $category = productPath();
    $other = $category->department->categories()->create(['name' => 'Pantry Staples']);
    $product = $category->products()->create(['name' => 'Rice']);
    $other->products()->create(['name' => 'Rice']);
    $this->actingAs(productCreator())->patch(route('products.update', $product), [...productInput($other), 'confirm_duplicate_name' => true])->assertSessionHasErrors('name');
    expect($product->fresh()->category_id)->toBe($category->id);
});

test('submitted status is ignored without repeating an unchanged name warning', function () {
    $category = productPath();
    $other = $category->department->categories()->create(['name' => 'Pantry Staples']);
    $product = $category->products()->create(['name' => 'Rice']);
    $other->products()->create(['name' => 'Rice']);
    $this->actingAs(productCreator())->patch(route('products.update', $product), [...productInput($category), 'is_active' => false])->assertSessionHasNoErrors();
    expect($product->fresh()->is_active)->toBeTrue();
});

test('volunteers cannot open or submit catalogue management forms', function () {
    $category = productPath();
    $product = $category->products()->create(['name' => 'Rice']);
    $this->actingAs(productCreator(RoleCode::VOLUNTEER));
    $this->get(route('products.create'))->assertForbidden();
    $this->get(route('products.edit', $product))->assertForbidden();
    $this->post(route('products.store'), productInput($category))->assertForbidden();
    $this->patch(route('products.update', $product), productInput($category))->assertForbidden();
    expect(fn () => (new CreateProduct)->create(productInput($category), auth()->user()))->toThrow(AuthorizationException::class);
    expect(fn () => (new UpdateProduct)->update($product, productInput($category), auth()->user()))->toThrow(AuthorizationException::class);
});

test('guests must sign in and retired catalogue URLs are unavailable', function () {
    $this->get('/products')->assertRedirect('/login');
    $this->get('/products/create')->assertRedirect('/login');
    $this->actingAs(productCreator());
    foreach (['/tags', '/tags/create', '/subcategories', '/subcategories/create'] as $url) {
        $this->get($url)->assertNotFound();
    }
});

// The visible form sends no status; Laravel still creates an active Product.
test('products default active without a status input', function () {
    $category = productPath();
    $this->actingAs(productCreator())->post(route('products.store'), productInput($category))->assertSessionHasNoErrors();
    expect(Product::sole()->is_active)->toBeTrue();
});

// Even callers of the actions cannot change status through editable data.
test('product actions ignore supplied status and edits preserve a legacy inactive value', function () {
    $category = productPath();
    $actor = productCreator();
    $product = (new CreateProduct)->create([...productInput($category), 'is_active' => false], $actor);
    expect($product->is_active)->toBeTrue();
    $product->update(['is_active' => false]);
    (new UpdateProduct)->update($product, [...productInput($category), 'name' => 'Brown Rice', 'is_active' => true], $actor);
    expect($product->fresh()->is_active)->toBeFalse()->and($product->fresh()->name)->toBe('Brown Rice');
    $this->actingAs($actor)->patch(route('products.update', $product), [...productInput($category), 'name' => 'White Rice', 'is_active' => true])->assertSessionHasNoErrors();
    expect($product->fresh()->is_active)->toBeFalse();
});
