<?php

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// Use a fully active account so these checks exercise department permissions.
function departmentEditor(RoleCode $role): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true,
        'must_change_password' => false,
    ]);
}

test('catalogue editors can open and rename a department without replacing it', function (RoleCode $role) {
    $department = Department::create(['name' => 'Pantry']);
    $this->actingAs(departmentEditor($role));

    $this->get(route('departments.edit', $department))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('departments/Edit')
            ->where('department', ['id' => $department->id, 'name' => 'Pantry']));

    // Extra submitted fields must not change the record's identity.
    $this->patch(route('departments.update', $department), [
        'name' => '  Dry Goods  ',
        'id' => $department->id + 100,
    ])->assertRedirect(route('departments.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'Dry Goods']);
    $this->assertDatabaseCount('departments', 1);
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('a department can keep its current name', function () {
    $department = Department::create(['name' => 'Pantry']);

    $this->actingAs(departmentEditor(RoleCode::MANAGER))
        ->patch(route('departments.update', $department), ['name' => 'Pantry'])
        ->assertRedirect(route('departments.index'))
        ->assertSessionHasNoErrors();
});

test('invalid department names are rejected without changing the record', function (mixed $name) {
    $department = Department::create(['name' => 'Pantry']);
    Department::create(['name' => 'Frozen']);

    $this->actingAs(departmentEditor(RoleCode::MANAGER))
        ->from(route('departments.edit', $department))
        ->patch(route('departments.update', $department), ['name' => $name])
        ->assertRedirect(route('departments.edit', $department))
        ->assertSessionHasErrors('name');

    expect($department->fresh()->name)->toBe('Pantry');
})->with(['blank' => '   ', 'duplicate' => ' Frozen ', 'too long' => str_repeat('a', 256)]);

test('volunteers cannot open or submit the edit form', function () {
    $department = Department::create(['name' => 'Pantry']);
    $this->actingAs(departmentEditor(RoleCode::VOLUNTEER));

    $this->get(route('departments.edit', $department))->assertForbidden();
    $this->patch(route('departments.update', $department), ['name' => 'Changed'])->assertForbidden();

    expect($department->fresh()->name)->toBe('Pantry');
});

test('guests must sign in before opening or submitting the edit form', function () {
    $department = Department::create(['name' => 'Pantry']);

    $this->get(route('departments.edit', $department))->assertRedirect(route('login'));
    $this->patch(route('departments.update', $department), ['name' => 'Changed'])
        ->assertRedirect(route('login'));

    expect($department->fresh()->name)->toBe('Pantry');
});

test('editing a missing department returns not found', function () {
    $this->actingAs(departmentEditor(RoleCode::MANAGER));

    $this->get(route('departments.edit', 999999))->assertNotFound();
    $this->patch(route('departments.update', 999999), ['name' => 'Changed'])->assertNotFound();
});
