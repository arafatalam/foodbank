<?php

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function subcategoryListViewer(RoleCode $role): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true,
        'must_change_password' => false,
    ]);
}

function listedSubcategories(): array
{
    $department = Department::create(['name' => 'Pantry']);
    $category = $department->categories()->create(['name' => 'Grains']);
    $rice = $category->subcategories()->create(['name' => 'Brown Rice']);
    $bread = Department::create(['name' => 'Bakery'])->categories()->create(['name' => 'Bread']);
    $loaf = $bread->subcategories()->create(['name' => 'Whole Wheat']);

    return [$rice, $loaf];
}

test('catalogue managers can list subcategories and their complete parent paths', function (RoleCode $role) {
    [$rice] = listedSubcategories();
    $this->actingAs(subcategoryListViewer($role))->get(route('subcategories.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('subcategories/Index')->where('search', '')
        ->has('subcategories.data', 2)
        ->where('subcategories.data.0.id', $rice->id)
        ->where('subcategories.data.0.category.name', 'Grains')
        ->where('subcategories.data.0.category.department.name', 'Pantry')
        ->where('subcategories.per_page', 10)
        ->missing('subcategories.data.0.quantity')
        ->missing('subcategories.data.0.summary_unit'));
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('partial search matches subcategory category or department names', function (string $search) {
    [$rice] = listedSubcategories();
    $this->actingAs(subcategoryListViewer(RoleCode::MANAGER))
        ->get(route('subcategories.index', ['search' => $search]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('search', trim($search))
        ->has('subcategories.data', 1)
        ->where('subcategories.data.0.id', $rice->id));
})->with(['Rice', 'rai', 'ant', '  Rice  ']);

test('empty search restores the full list and absent matches return no rows', function () {
    listedSubcategories();
    $this->actingAs(subcategoryListViewer(RoleCode::MANAGER));
    $this->get(route('subcategories.index', ['search' => 'no-matching-name']))
        ->assertInertia(fn (Assert $page) => $page->has('subcategories.data', 0)->where('subcategories.total', 0));
    $this->get(route('subcategories.index', ['search' => '']))
        ->assertInertia(fn (Assert $page) => $page->has('subcategories.data', 2)->where('search', ''));
});

test('search runs before pagination and page links retain it', function () {
    $category = Department::create(['name' => 'Pantry'])->categories()->create(['name' => 'Grains']);
    for ($i = 1; $i <= 12; $i++) {
        $category->subcategories()->create(['name' => sprintf('Matching %02d', $i)]);
    }
    $category->subcategories()->create(['name' => 'A nonmatch']);
    $this->actingAs(subcategoryListViewer(RoleCode::MANAGER));

    $this->get(route('subcategories.index', ['search' => 'Matching']))
        ->assertInertia(function (Assert $page) {
            $page->has('subcategories.data', 10)->where('subcategories.total', 12)
                ->where('subcategories.data.0.name', 'Matching 01')
                ->where('subcategories.data.9.name', 'Matching 10')
                ->where('subcategories.next_page_url', fn ($url) => str_contains($url, 'search=Matching') && str_contains($url, 'page=2'));
        });
    $this->get(route('subcategories.index', ['search' => 'Matching', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('subcategories.data', 2)
            ->where('subcategories.data.0.name', 'Matching 11')
            ->where('subcategories.prev_page_url', fn ($url) => str_contains($url, 'search=Matching')));
});

test('matching multiple levels does not duplicate a subcategory', function () {
    $category = Department::create(['name' => 'Rice Pantry'])->categories()->create(['name' => 'Rice Grains']);
    $category->subcategories()->create(['name' => 'Brown Rice']);
    $this->actingAs(subcategoryListViewer(RoleCode::MANAGER))
        ->get(route('subcategories.index', ['search' => 'Rice']))
        ->assertInertia(fn (Assert $page) => $page->has('subcategories.data', 1)->where('subcategories.total', 1));
});

test('an empty catalogue renders a valid empty page', function () {
    $this->actingAs(subcategoryListViewer(RoleCode::MANAGER))->get(route('subcategories.index'))
        ->assertInertia(fn (Assert $page) => $page->has('subcategories.data', 0)
            ->where('subcategories.current_page', 1)->where('subcategories.last_page', 1));
});

test('invalid list filters are rejected', function (array $filters, string $error) {
    $this->actingAs(subcategoryListViewer(RoleCode::MANAGER))
        ->getJson(route('subcategories.index', $filters))->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'long search' => [['search' => str_repeat('x', 101)], 'search'],
    'array search' => [['search' => ['Rice']], 'search'],
    'zero page' => [['page' => 0], 'page'],
    'fraction page' => [['page' => 1.5], 'page'],
    'oversized page' => [['page' => 2147483648], 'page'],
]);

test('volunteers cannot access the list or search results', function () {
    $this->actingAs(subcategoryListViewer(RoleCode::VOLUNTEER));
    $this->get(route('subcategories.index'))->assertForbidden();
    $this->get(route('subcategories.index', ['search' => 'Rice']))->assertForbidden();
});

test('guests must sign in to list subcategories', function () {
    $this->get(route('subcategories.index'))->assertRedirect(route('login'));
});
