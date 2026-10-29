<?php

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Role;
use App\Models\Subcategory;
use App\Models\User;
use App\Policies\SubcategoryPolicy;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

// Model policies take precedence over a separately named Gate ability.
class ReadOnlySubcategoryTestPolicy extends SubcategoryPolicy
{
    public function update(User $user, Subcategory $subcategory): bool
    {
        return false;
    }
}

function subcategoryEditor(RoleCode $role): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true,
        'must_change_password' => false,
    ]);
}

function editableSubcategory(): Subcategory
{
    return Department::create(['name' => 'Pantry'])->categories()
        ->create(['name' => 'Grains'])->subcategories()->create(['name' => 'Rice']);
}

test('catalogue editors see edit permission and can rename and move the subcategory while preserving its identity', function (RoleCode $role) {
    $subcategory = editableSubcategory();
    $other = Department::create(['name' => 'Bakery'])->categories()->create(['name' => 'Bread']);
    $this->actingAs(subcategoryEditor($role));

    $this->get(route('subcategories.index'))->assertInertia(fn (Assert $page) => $page
        ->where('subcategories.data.0.canEdit', true));
    $this->get(route('subcategories.edit', $subcategory))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('subcategories/Edit')
            ->where('subcategory', [
                'id' => $subcategory->id, 'name' => 'Rice',
                'category' => ['id' => $subcategory->category_id, 'name' => 'Grains',
                    'department' => ['id' => $subcategory->category->department_id, 'name' => 'Pantry']],
            ]));

    // Name and the selected category are editable; identity and stock fields are not.
    $this->patch(route('subcategories.update', $subcategory), [
        'name' => '  Brown Rice  ', 'category_id' => $other->id,
        'department_id' => $other->department_id, 'id' => 999, 'quantity' => 42,
    ])->assertRedirect(route('subcategories.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('subcategories', [
        'id' => $subcategory->id, 'name' => 'Brown Rice', 'category_id' => $other->id,
    ]);
    $this->assertDatabaseCount('subcategories', 1);
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('subcategory can keep its name or use the same name as another category', function (string $name) {
    $subcategory = editableSubcategory();
    $subcategory->category->department->categories()->create(['name' => 'Other Grains'])
        ->subcategories()->create(['name' => 'Oats']);
    $this->actingAs(subcategoryEditor(RoleCode::MANAGER))
        ->patch(route('subcategories.update', $subcategory), ['name' => $name, 'category_id' => $subcategory->category_id, 'department_id' => $subcategory->category->department_id])
        ->assertRedirect(route('subcategories.index'))->assertSessionHasNoErrors();
    expect($subcategory->fresh()->name)->toBe($name);
})->with(['Rice', 'Oats', str_repeat('a', 255)]);

test('invalid rename leaves the subcategory unchanged', function (mixed $name) {
    $subcategory = editableSubcategory();
    $subcategory->category->subcategories()->create(['name' => 'Oats']);
    $this->actingAs(subcategoryEditor(RoleCode::MANAGER))
        ->from(route('subcategories.edit', $subcategory))
        ->patch(route('subcategories.update', $subcategory), ['name' => $name, 'category_id' => $subcategory->category_id, 'department_id' => $subcategory->category->department_id])
        ->assertRedirect(route('subcategories.edit', $subcategory))->assertSessionHasErrors('name');
    expect($subcategory->fresh()->name)->toBe('Rice');
})->with(['missing' => null, 'blank' => '  ', 'duplicate' => ' Oats ', 'too long' => str_repeat('a', 256), 'array' => [['Rice']]]);

test('a move cannot duplicate a name already used in the destination category', function () {
    $subcategory = editableSubcategory();
    $other = $subcategory->category->department->categories()->create(['name' => 'Other Grains']);
    $other->subcategories()->create(['name' => 'Oats']);
    $this->actingAs(subcategoryEditor(RoleCode::MANAGER))
        ->patch(route('subcategories.update', $subcategory), ['name' => 'Oats', 'category_id' => $other->id, 'department_id' => $other->department_id])
        ->assertSessionHasErrors('name');
    expect($subcategory->fresh()->name)->toBe('Rice');
});

test('volunteers cannot open or submit subcategory edits', function () {
    $subcategory = editableSubcategory();
    $this->actingAs(subcategoryEditor(RoleCode::VOLUNTEER));
    $this->get(route('subcategories.edit', $subcategory))->assertForbidden();
    $this->patch(route('subcategories.update', $subcategory), ['name' => 'Changed'])->assertForbidden();
    expect($subcategory->fresh()->name)->toBe('Rice');
});

test('guests must sign in before opening or submitting subcategory edits', function () {
    $subcategory = editableSubcategory();
    $this->get(route('subcategories.edit', $subcategory))->assertRedirect(route('login'));
    $this->patch(route('subcategories.update', $subcategory), ['name' => 'Changed'])->assertRedirect(route('login'));
    expect($subcategory->fresh()->name)->toBe('Rice');
});

test('missing subcategory edit and update return not found', function () {
    $this->actingAs(subcategoryEditor(RoleCode::MANAGER));
    $this->get(route('subcategories.edit', 999999))->assertNotFound();
    $this->patch(route('subcategories.update', 999999), ['name' => 'Changed'])->assertNotFound();
});

test('list row permission and edit routes respect an update denial independently of list access', function () {
    $subcategory = editableSubcategory();
    $this->actingAs(subcategoryEditor(RoleCode::MANAGER));
    // Simulate a policy change without granting editing merely because listing is allowed.
    Gate::policy(Subcategory::class, ReadOnlySubcategoryTestPolicy::class);
    $this->get(route('subcategories.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('subcategories.data.0.canEdit', false));
    $this->get(route('subcategories.edit', $subcategory))->assertForbidden();
    $this->patch(route('subcategories.update', $subcategory), ['name' => 'Changed'])->assertForbidden();
    expect($subcategory->fresh()->name)->toBe('Rice');
});

test('a subcategory moves between categories in the same department without changing its ID', function () {
    $subcategory = editableSubcategory();
    $other = $subcategory->category->department->categories()->create(['name' => 'Other Grains']);
    $this->actingAs(subcategoryEditor(RoleCode::MANAGER))
        ->patch(route('subcategories.update', $subcategory), [
            'name' => 'Rice', 'category_id' => $other->id, 'department_id' => $other->department_id,
        ])->assertRedirect(route('subcategories.index'))->assertSessionHasNoErrors();
    expect($subcategory->fresh()->category_id)->toBe($other->id);
    expect($subcategory->fresh()->id)->toBe($subcategory->id);
    $this->get(route('subcategories.edit', $subcategory))->assertInertia(fn (Assert $page) => $page
        ->where('subcategory.category.id', $other->id));
});

test('a move checks destination uniqueness rather than the original category', function () {
    $subcategory = editableSubcategory();
    $subcategory->category->subcategories()->create(['name' => 'Oats']);
    $other = Department::create(['name' => 'Bakery'])->categories()->create(['name' => 'Bread']);
    $this->actingAs(subcategoryEditor(RoleCode::MANAGER))
        ->patch(route('subcategories.update', $subcategory), [
            'name' => 'Oats', 'category_id' => $other->id, 'department_id' => $other->department_id,
        ])->assertSessionHasNoErrors();
    expect($subcategory->fresh()->name)->toBe('Oats');
    expect($subcategory->fresh()->category_id)->toBe($other->id);
    $this->get(route('subcategories.index', ['search' => 'Bakery']))->assertInertia(fn (Assert $page) => $page
        ->has('subcategories.data', 1)->where('subcategories.data.0.id', $subcategory->id)
        ->where('subcategories.data.0.category.department.name', 'Bakery'));
});

test('invalid destination selections cannot partially rename or move a subcategory', function (array $override, string $error) {
    $subcategory = editableSubcategory();
    $this->actingAs(subcategoryEditor(RoleCode::MANAGER))
        ->patch(route('subcategories.update', $subcategory), array_replace([
            'name' => 'Changed', 'category_id' => $subcategory->category_id,
            'department_id' => $subcategory->category->department_id,
        ], $override))->assertSessionHasErrors($error);
    expect($subcategory->fresh()->name)->toBe('Rice');
    expect($subcategory->fresh()->category_id)->toBe($subcategory->category_id);
})->with([
    'missing department' => [['department_id' => null], 'department_id'],
    'unknown department' => [['department_id' => 999999], 'department_id'],
    'array department' => [['department_id' => [1]], 'department_id'],
    'fraction department' => [['department_id' => 1.5], 'department_id'],
    'missing category' => [['category_id' => null], 'category_id'],
    'unknown category' => [['category_id' => 999999], 'category_id'],
    'array category' => [['category_id' => [1]], 'category_id'],
    'fraction category' => [['category_id' => 1.5], 'category_id'],
]);

test('category must belong to the selected department when moving', function () {
    $subcategory = editableSubcategory();
    $other = Department::create(['name' => 'Bakery'])->categories()->create(['name' => 'Bread']);
    $this->actingAs(subcategoryEditor(RoleCode::MANAGER))
        ->patch(route('subcategories.update', $subcategory), [
            'name' => 'Changed', 'category_id' => $other->id,
            'department_id' => $subcategory->category->department_id,
        ])->assertSessionHasErrors('category_id');
    expect($subcategory->fresh()->name)->toBe('Rice');
    expect($subcategory->fresh()->category_id)->toBe($subcategory->category_id);
});
