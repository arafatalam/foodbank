<?php

use App\Actions\Products\CreateProduct;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Product;
use App\Models\Role;
use App\Models\Subcategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function productCreator(RoleCode $role = RoleCode::MANAGER): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true,
        'must_change_password' => false,
    ]);
}

function productPath(): Subcategory
{
    return Department::create(['name' => 'Pantry'])->categories()
        ->create(['name' => 'Grains'])->subcategories()->create(['name' => 'Rice']);
}

function productInput(Subcategory $subcategory): array
{
    return [
        'department_id' => $subcategory->category->department_id,
        'category_id' => $subcategory->category_id,
        'subcategory_id' => $subcategory->id,
        'name' => '  Basmati Rice  ',
        'brand' => '  Example Brand  ',
        'is_active' => true,
    ];
}

test('both catalogue creators receive the connected form choices', function (RoleCode $role) {
    $subcategory = productPath();
    $tag = Tag::create(['name' => 'Halal']);
    $this->actingAs(productCreator($role))->get(route('products.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('products/Create')
            ->where('departments', [['id' => $subcategory->category->department_id, 'name' => 'Pantry']])
            ->where('categories', [['id' => $subcategory->category_id, 'name' => 'Grains', 'department_id' => $subcategory->category->department_id]])
            ->where('subcategories', [['id' => $subcategory->id, 'name' => 'Rice', 'category_id' => $subcategory->category_id]])
            ->where('tags', [['id' => $tag->id, 'name' => 'Halal']]));
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('creators save only catalogue fields and both relationship directions work', function (RoleCode $role) {
    $subcategory = productPath();
    $halal = Tag::create(['name' => 'Halal']);
    $vegan = Tag::create(['name' => 'Vegan']);
    $this->actingAs(productCreator($role))->from(route('products.create'))
        ->post(route('products.store'), [...productInput($subcategory), 'is_active' => false,
            'tag_ids' => [$halal->id, $vegan->id], 'id' => 999, 'code' => 'BAD', 'quantity' => 100])
        ->assertRedirect(route('products.create'))->assertSessionHasNoErrors();

    $product = Product::sole();
    expect($product->name)->toBe('Basmati Rice')->and($product->brand)->toBe('Example Brand')
        ->and($product->is_active)->toBeFalse()->and($product->subcategory->id)->toBe($subcategory->id);
    expect($product->id)->not->toBe(999);
    expect($product->getAttributes())->not->toHaveKeys(['department_id', 'category_id', 'code', 'quantity']);
    expect($product->tags->modelKeys())->toEqualCanonicalizing([$halal->id, $vegan->id]);
    expect($halal->products->modelKeys())->toBe([$product->id]);
    $this->assertDatabaseCount('product_tag', 2);

    // Renaming a shared tag is visible from the product without replacing links.
    $halal->update(['name' => 'Halal Certified']);
    expect($product->tags()->whereKey($halal->id)->sole()->name)->toBe('Halal Certified');
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('products can be saved unbranded and untagged', function (mixed $brand, mixed $tags) {
    $subcategory = productPath();
    $input = [...productInput($subcategory), 'brand' => $brand];
    if ($tags !== 'omitted') {
        $input['tag_ids'] = $tags;
    }
    $this->actingAs(productCreator())->post(route('products.store'), $input)->assertSessionHasNoErrors();
    expect(Product::sole()->brand)->toBeNull()->and(Product::sole()->tags)->toHaveCount(0);
})->with([[null, 'omitted'], ['', []], ['   ', null]]);

test('invalid product input never saves partial records', function (array $override, string $field) {
    $subcategory = productPath();
    $this->actingAs(productCreator())->from(route('products.create'))
        ->post(route('products.store'), array_replace(productInput($subcategory), $override))
        ->assertRedirect(route('products.create'))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('product_tag', 0);
})->with([
    [['name' => null], 'name'], [['name' => '   '], 'name'], [['name' => ['bad']], 'name'], [['name' => str_repeat('a', 256)], 'name'],
    [['brand' => ['bad']], 'brand'], [['brand' => str_repeat('a', 256)], 'brand'],
    [['department_id' => null], 'department_id'], [['department_id' => 99999], 'department_id'],
    [['category_id' => null], 'category_id'], [['category_id' => [1]], 'category_id'],
    [['subcategory_id' => null], 'subcategory_id'], [['subcategory_id' => 99999], 'subcategory_id'], [['subcategory_id' => 1.5], 'subcategory_id'],
    [['is_active' => null], 'is_active'], [['is_active' => 'yes'], 'is_active'],
    [['tag_ids' => 'bad'], 'tag_ids'], [['tag_ids' => [99999]], 'tag_ids.0'], [['tag_ids' => [null]], 'tag_ids.0'],
    [['tag_ids' => ['x']], 'tag_ids.0'], [['tag_ids' => ['bad' => 1]], 'tag_ids'],
]);

test('mismatched parent choices are rejected on the server', function (string $field) {
    $subcategory = productPath();
    $otherCategory = Department::create(['name' => 'Bakery'])->categories()->create(['name' => 'Bread']);
    $otherSubcategory = $otherCategory->subcategories()->create(['name' => 'Loaves']);
    $input = productInput($subcategory);
    $input[$field] = $field === 'category_id' ? $otherCategory->id : $otherSubcategory->id;
    $this->actingAs(productCreator())->post(route('products.store'), $input)->assertSessionHasErrors($field);
    $this->assertDatabaseCount('products', 0);
})->with(['category_id', 'subcategory_id']);

test('duplicate products include inactive entries and ignore name brand case and spaces', function (mixed $brand) {
    $subcategory = productPath();
    Product::create(['name' => 'Basmati Rice', 'brand' => $brand, 'subcategory_id' => $subcategory->id, 'is_active' => false]);
    $input = [...productInput($subcategory), 'name' => '  BASMATI RICE  ', 'brand' => $brand === null ? '  ' : ' example BRAND '];
    $this->actingAs(productCreator())->post(route('products.store'), $input)->assertSessionHasErrors('name');
    $this->assertDatabaseCount('products', 1);
})->with(['Example Brand', null]);

test('different brands and subcategories are separate catalogue identities', function () {
    $subcategory = productPath();
    $other = $subcategory->category->subcategories()->create(['name' => 'Other Rice']);
    $this->actingAs(productCreator());
    foreach ([productInput($subcategory), [...productInput($subcategory), 'brand' => 'Different'], productInput($other)] as $input) {
        $this->post(route('products.store'), $input)->assertSessionHasNoErrors();
    }
    $this->assertDatabaseCount('products', 3);
});

test('maximum length product fields save successfully', function () {
    $subcategory = productPath();
    $this->actingAs(productCreator())->post(route('products.store'), [
        ...productInput($subcategory), 'name' => str_repeat('a', 255), 'brand' => str_repeat('b', 255),
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('products', 1);
});

test('duplicate selected tag IDs are rejected', function () {
    $subcategory = productPath();
    $tag = Tag::create(['name' => 'Halal']);
    $this->actingAs(productCreator())->post(route('products.store'), [
        ...productInput($subcategory), 'tag_ids' => [$tag->id, (string) $tag->id],
    ])->assertSessionHasErrors('tag_ids.0');
    $this->assertDatabaseCount('products', 0);
});

test('volunteers and guests cannot open or submit creation', function (bool $signedIn) {
    $subcategory = productPath();
    if ($signedIn) {
        $this->actingAs(productCreator(RoleCode::VOLUNTEER));
    }
    foreach ([$this->get(route('products.create')), $this->post(route('products.store'), productInput($subcategory))] as $response) {
        $signedIn ? $response->assertForbidden() : $response->assertRedirect(route('login'));
    }
    $this->assertDatabaseCount('products', 0);
})->with([true, false]);

test('the action independently checks creation permission', function () {
    $subcategory = productPath();
    expect(fn () => app(CreateProduct::class)->create(productInput($subcategory), productCreator(RoleCode::VOLUNTEER)))
        ->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('products', 0);
});

test('the action rechecks the current hierarchy and selected tags', function (string $change) {
    $subcategory = productPath();
    $tag = Tag::create(['name' => 'Halal']);
    $input = [...productInput($subcategory), 'tag_ids' => [$tag->id]];
    if ($change === 'category') {
        $subcategory->category->update(['department_id' => Department::create(['name' => 'Other'])->id]);
    } elseif ($change === 'subcategory') {
        $subcategory->update(['category_id' => $subcategory->category->department->categories()->create(['name' => 'Other'])->id]);
    } else {
        $tag->delete();
    }
    expect(fn () => app(CreateProduct::class)->create($input, productCreator()))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('products', 0);
})->with(['category', 'subcategory', 'tag']);

test('tag-link failure rolls back the product insert', function () {
    $subcategory = productPath();
    $tag = Tag::create(['name' => 'Halal']);
    // Force a real database failure only during this isolated test transaction.
    DB::statement("CREATE TRIGGER fail_product_tag BEFORE INSERT ON product_tag BEGIN SELECT RAISE(ABORT, 'simulated tag-link failure'); END");
    expect(fn () => app(CreateProduct::class)->create([...productInput($subcategory), 'tag_ids' => [$tag->id]], productCreator()))
        ->toThrow(QueryException::class);
    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('product_tag', 0);
});

test('pivot constraints prevent duplicate links and deleting a used tag', function () {
    $subcategory = productPath();
    $tag = Tag::create(['name' => 'Halal']);
    $product = app(CreateProduct::class)->create([...productInput($subcategory), 'tag_ids' => [$tag->id]], productCreator());
    expect(fn () => $product->tags()->attach($tag->id))->toThrow(QueryException::class);
    expect(fn () => $tag->delete())->toThrow(QueryException::class);
    $this->assertDatabaseCount('product_tag', 1);
});
