<?php

use App\Enums\RoleCode;
use App\Models\Category;
use App\Models\Department;
use App\Models\Role;
use App\Models\Subcategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function subcategoryCreator(RoleCode $role): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true,
        'must_change_password' => false,
    ]);
}

function subcategoryParent(): Category
{
    return Department::create(['name' => 'Pantry'])->categories()->create(['name' => 'Grains']);
}

test('catalogue creators can load departments and their category choices', function (RoleCode $role) {
    $category = subcategoryParent();
    $this->actingAs(subcategoryCreator($role))
        ->get(route('subcategories.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('subcategories/Create')
            ->where('departments', [['id' => $category->department_id, 'name' => 'Pantry']])
            ->where('categories', [['id' => $category->id, 'name' => 'Grains', 'department_id' => $category->department_id]])
            ->missing('summaryUnits'));
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('catalogue creators save the name and category without duplicating the department', function (RoleCode $role) {
    $category = subcategoryParent();
    $this->actingAs(subcategoryCreator($role))
        ->from(route('subcategories.create'))
        ->post(route('subcategories.store'), [
            'department_id' => $category->department_id,
            'category_id' => $category->id,
            'name' => '  Rice  ',
            'id' => 999,
            'summary_unit' => 'kg',
            'quantity' => 999,
        ])->assertRedirect(route('subcategories.create'))->assertSessionHasNoErrors();

    $subcategory = Subcategory::sole();
    expect($subcategory->name)->toBe('Rice');
    expect($subcategory->category_id)->toBe($category->id);
    expect($subcategory->id)->not->toBe(999);
    expect($category->subcategories()->sole()->id)->toBe($subcategory->id);
    expect($subcategory->category->department->name)->toBe('Pantry');
    expect($subcategory->getAttributes())->not->toHaveKeys(['department_id', 'summary_unit', 'quantity']);
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('invalid subcategory fields do not save a record', function (array $override, string $error) {
    $category = subcategoryParent();
    $this->actingAs(subcategoryCreator(RoleCode::MANAGER))
        ->from(route('subcategories.create'))
        ->post(route('subcategories.store'), array_replace([
            'department_id' => $category->department_id,
            'category_id' => $category->id,
            'name' => 'Rice',
        ], $override))->assertRedirect(route('subcategories.create'))->assertSessionHasErrors($error);
    $this->assertDatabaseCount('subcategories', 0);
})->with([
    'missing name' => [['name' => null], 'name'],
    'blank name' => [['name' => '   '], 'name'],
    'long name' => [['name' => str_repeat('a', 256)], 'name'],
    'array name' => [['name' => ['Rice']], 'name'],
    'missing department' => [['department_id' => null], 'department_id'],
    'nonexistent department' => [['department_id' => 99999], 'department_id'],
    'text department' => [['department_id' => 'bad'], 'department_id'],
    'array department' => [['department_id' => [1]], 'department_id'],
    'fraction department' => [['department_id' => 1.5], 'department_id'],
    'missing category' => [['category_id' => null], 'category_id'],
    'nonexistent category' => [['category_id' => 99999], 'category_id'],
    'text category' => [['category_id' => 'bad'], 'category_id'],
    'array category' => [['category_id' => [1]], 'category_id'],
    'fraction category' => [['category_id' => 1.5], 'category_id'],
]);

test('a category from another department is rejected even in a direct request', function () {
    $category = subcategoryParent();
    $other = Department::create(['name' => 'Frozen']);
    $this->actingAs(subcategoryCreator(RoleCode::MANAGER))
        ->post(route('subcategories.store'), [
            'department_id' => $other->id, 'category_id' => $category->id, 'name' => 'Rice',
        ])->assertSessionHasErrors('category_id');
    $this->assertDatabaseCount('subcategories', 0);
});

test('a category moved after the form loads cannot be saved under its previous department', function () {
    $category = subcategoryParent();
    $oldDepartment = $category->department_id;
    $new = Department::create(['name' => 'Dry Goods']);
    $category->update(['department_id' => $new->id]);
    $this->actingAs(subcategoryCreator(RoleCode::MANAGER))
        ->post(route('subcategories.store'), [
            'department_id' => $oldDepartment, 'category_id' => $category->id, 'name' => 'Rice',
        ])->assertSessionHasErrors('category_id');
    $this->assertDatabaseCount('subcategories', 0);
});

test('names are unique within a category and reusable in other categories', function () {
    $category = subcategoryParent();
    $category->subcategories()->create(['name' => 'Rice']);
    $this->actingAs(subcategoryCreator(RoleCode::MANAGER));
    $this->post(route('subcategories.store'), [
        'department_id' => $category->department_id, 'category_id' => $category->id, 'name' => ' Rice ',
    ])->assertSessionHasErrors('name');
    $this->assertDatabaseCount('subcategories', 1);

    $other = $category->department->categories()->create(['name' => 'Other Grains']);
    $this->post(route('subcategories.store'), [
        'department_id' => $other->department_id, 'category_id' => $other->id, 'name' => 'Rice',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('subcategories', 2);
});

test('volunteers cannot open or submit subcategory creation', function () {
    $category = subcategoryParent();
    $this->actingAs(subcategoryCreator(RoleCode::VOLUNTEER));
    $this->get(route('subcategories.create'))->assertForbidden();
    $this->post(route('subcategories.store'), [
        'department_id' => $category->department_id, 'category_id' => $category->id, 'name' => 'Rice',
    ])->assertForbidden();
    $this->assertDatabaseCount('subcategories', 0);
});

test('guests must sign in before creating subcategories', function () {
    $this->get(route('subcategories.create'))->assertRedirect(route('login'));
    $this->post(route('subcategories.store'), [])->assertRedirect(route('login'));
    $this->assertDatabaseCount('subcategories', 0);
});

test('category rename and department move preserve existing subcategory links', function () {
    $category = subcategoryParent();
    $subcategory = $category->subcategories()->create(['name' => 'Rice']);
    $other = Department::create(['name' => 'Dry Goods']);
    $this->actingAs(subcategoryCreator(RoleCode::MANAGER))
        ->patch(route('categories.update', $category), ['name' => 'Cereals', 'department_id' => $other->id])
        ->assertSessionHasNoErrors();
    expect($subcategory->fresh()->category_id)->toBe($category->id);
    expect($subcategory->fresh()->category->name)->toBe('Cereals');
    expect($subcategory->fresh()->category->department->name)->toBe('Dry Goods');
});
