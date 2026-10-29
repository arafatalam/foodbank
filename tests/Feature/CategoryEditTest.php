<?php

use App\Enums\RoleCode;
use App\Models\Category;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// Active accounts let these tests focus on catalogue permissions.
function categoryEditor(RoleCode $role): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true,
        'must_change_password' => false,
    ]);
}

function editableCategory(): Category
{
    $department = Department::create(['name' => 'Pantry']);

    return $department->categories()->create(['name' => 'Rice']);
}

test('catalogue editors can open and rename a category and move it while preserving its identity', function (RoleCode $role) {
    $category = editableCategory();
    $otherDepartment = Department::create(['name' => 'Frozen']);
    $this->actingAs(categoryEditor($role));

    $this->get(route('categories.edit', $category))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/Edit')
            ->where('category', [
                'id' => $category->id,
                'name' => 'Rice',
                'department' => ['id' => $category->department_id, 'name' => 'Pantry'],
            ]));

    // The department may change, but a submitted ID must not replace the category.
    $this->patch(route('categories.update', $category), [
        'name' => '  Grains  ',
        'department_id' => $otherDepartment->id,
        'id' => $category->id + 100,
    ])->assertRedirect(route('categories.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Grains',
        'department_id' => $otherDepartment->id,
    ]);
    $this->assertDatabaseCount('categories', 1);
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('a category can keep its current name or use a name from another department', function (string $name) {
    $category = editableCategory();
    Department::create(['name' => 'Frozen'])->categories()->create(['name' => 'Vegetables']);

    $this->actingAs(categoryEditor(RoleCode::MANAGER))
        ->patch(route('categories.update', $category), ['name' => $name, 'department_id' => $category->department_id])
        ->assertRedirect(route('categories.index'))
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe($name);
})->with(['Rice', 'Vegetables']);

test('invalid category names leave the saved category unchanged', function (mixed $name) {
    $category = editableCategory();
    $category->department->categories()->create(['name' => 'Pasta']);

    $this->actingAs(categoryEditor(RoleCode::MANAGER))
        ->from(route('categories.edit', $category))
        ->patch(route('categories.update', $category), ['name' => $name, 'department_id' => $category->department_id])
        ->assertRedirect(route('categories.edit', $category))
        ->assertSessionHasErrors('name');

    expect($category->fresh()->name)->toBe('Rice');
})->with(['blank' => '   ', 'missing' => null, 'duplicate' => ' Pasta ', 'too long' => str_repeat('a', 256), 'array' => [['Rice']]]);

test('a move rejects duplicate names in the destination department', function () {
    $category = editableCategory();
    $other = Department::create(['name' => 'Frozen']);
    $other->categories()->create(['name' => 'Pasta']);

    $this->actingAs(categoryEditor(RoleCode::MANAGER))
        ->patch(route('categories.update', $category), ['name' => 'Pasta', 'department_id' => $other->id])
        ->assertSessionHasErrors('name');

    expect($category->fresh()->name)->toBe('Rice');
});

test('volunteers cannot open or submit category edits', function () {
    $category = editableCategory();
    $this->actingAs(categoryEditor(RoleCode::VOLUNTEER));

    $this->get(route('categories.edit', $category))->assertForbidden();
    $this->patch(route('categories.update', $category), ['name' => 'Changed'])->assertForbidden();
    expect($category->fresh()->name)->toBe('Rice');
});

test('guests must sign in before opening or submitting category edits', function () {
    $category = editableCategory();

    $this->get(route('categories.edit', $category))->assertRedirect(route('login'));
    $this->patch(route('categories.update', $category), ['name' => 'Changed'])->assertRedirect(route('login'));
    expect($category->fresh()->name)->toBe('Rice');
});

test('editing a missing category returns not found', function () {
    $this->actingAs(categoryEditor(RoleCode::MANAGER));

    $this->get(route('categories.edit', 999999))->assertNotFound();
    $this->patch(route('categories.update', 999999), ['name' => 'Changed'])->assertNotFound();
});

test('renaming a department preserves its categories and displays its current name', function () {
    $category = editableCategory();
    $this->actingAs(categoryEditor(RoleCode::MANAGER));

    $this->patch(route('departments.update', $category->department_id), ['name' => 'Dry Goods'])
        ->assertRedirect(route('departments.index'))->assertSessionHasNoErrors();

    expect($category->fresh()->department->name)->toBe('Dry Goods');
    $this->get(route('categories.edit', $category))->assertInertia(fn (Assert $page) => $page
        ->where('category.department.name', 'Dry Goods'));
    $this->get(route('categories.index', ['search' => 'Dry Goods']))->assertInertia(fn (Assert $page) => $page
        ->has('categories.data', 1)
        ->where('categories.data.0.id', $category->id)
        ->where('categories.data.0.department.name', 'Dry Goods'));
});

test('moving uses destination uniqueness rather than the original department', function () {
    $category = editableCategory();
    $category->department->categories()->create(['name' => 'Pasta']);
    $other = Department::create(['name' => 'Frozen']);

    $this->actingAs(categoryEditor(RoleCode::MANAGER))
        ->patch(route('categories.update', $category), ['name' => 'Pasta', 'department_id' => $other->id])
        ->assertRedirect(route('categories.index'))->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('Pasta');
    expect($category->fresh()->department_id)->toBe($other->id);
});

test('invalid department choices cannot change a category', function (mixed $departmentId) {
    $category = editableCategory();
    $this->actingAs(categoryEditor(RoleCode::MANAGER))
        ->patch(route('categories.update', $category), ['name' => 'Changed', 'department_id' => $departmentId])
        ->assertSessionHasErrors('department_id');

    expect($category->fresh()->name)->toBe('Rice');
    expect($category->fresh()->department_id)->toBe($category->department_id);
})->with(['missing' => null, 'nonexistent' => 999999, 'text' => 'invalid', 'array' => [[1]], 'fraction' => 1.5]);
