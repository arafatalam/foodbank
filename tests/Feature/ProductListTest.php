<?php

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('all roles see generic products and policy driven edit permissions', function (RoleCode $role) {
    $actor = User::factory()->create(['role_id' => Role::firstOrCreate(['code' => $role->value])->id, 'is_active' => true, 'must_change_password' => false]);
    $category = Department::create(['name' => 'Food'])->categories()->create(['name' => 'Grains']);
    $product = $category->products()->create(['name' => 'Rice']);
    $canEdit = $role !== RoleCode::VOLUNTEER;
    $this->actingAs($actor)->get('/products')->assertOk()->assertInertia(fn (Assert $page) => $page->component('products/Index')
        ->where('canCreate', $canEdit)->where('products.data', [['id' => $product->id, 'name' => 'Rice', 'department' => 'Food', 'category' => 'Grains', 'canUpdate' => $canEdit]])
        ->missing('tags')->missing('subcategories')->missing('auth.navigation.tags')->missing('auth.navigation.subcategories'));
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN, RoleCode::VOLUNTEER]);

test('search and hierarchy filter all stored statuses before pagination and preserve query links', function () {
    $actor = User::factory()->create(['role_id' => Role::firstOrCreate(['code' => RoleCode::MANAGER->value])->id, 'is_active' => true, 'must_change_password' => false]);
    $category = Department::create(['name' => 'Food'])->categories()->create(['name' => 'Grains']);
    for ($i = 1; $i <= 12; $i++) {
        $category->products()->create(['name' => sprintf('Rice %02d', $i)]);
    }
    $category->products()->create(['name' => 'Rice inactive', 'is_active' => false]);
    $other = $category->department->categories()->create(['name' => 'Other']);
    $other->products()->create(['name' => 'Rice other']);
    $query = ['search' => 'Rice', 'department_id' => $category->department_id, 'category_id' => $category->id];
    $this->actingAs($actor)->get('/products?'.http_build_query($query))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 10)->where('products.total', 13)->where('products.next_page_url', fn ($url) => str_contains($url, 'category_id='.$category->id) && ! str_contains($url, 'status=')));
    $this->get('/products?'.http_build_query([...$query, 'page' => 2]))->assertInertia(fn (Assert $page) => $page->has('products.data', 3)->where('products.data.0.name', 'Rice 11'));
    $this->get('/products?search=not-found')->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    $this->get('/products?status=inactive')->assertInertia(fn (Assert $page) => $page->where('products.total', 14)->missing('filters.status')->missing('products.data.0.is_active'));
});

test('malformed and conflicting list filters are rejected', function () {
    $actor = User::factory()->create(['role_id' => Role::firstOrCreate(['code' => RoleCode::MANAGER->value])->id, 'is_active' => true, 'must_change_password' => false]);
    $category = Department::create(['name' => 'Food'])->categories()->create(['name' => 'Grains']);
    $other = Department::create(['name' => 'Other']);
    $this->actingAs($actor)->get('/products?'.http_build_query(['department_id' => $other->id, 'category_id' => $category->id]))->assertSessionHasErrors('category_id');
    foreach (['page' => '-1', 'search' => ['bad']] as $field => $value) {
        $this->get('/products?'.http_build_query([$field => $value]))->assertSessionHasErrors($field);
    }
});
