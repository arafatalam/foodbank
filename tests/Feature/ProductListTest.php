<?php

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function productListViewer(RoleCode $role = RoleCode::MANAGER): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true,
        'must_change_password' => false,
    ]);
}

function productListRecords(): array
{
    $rice = Department::create(['name' => 'Pantry'])->categories()->create(['name' => 'Grains'])
        ->subcategories()->create(['name' => 'Rice']);
    $soap = Department::create(['name' => 'Household'])->categories()->create(['name' => 'Care'])
        ->subcategories()->create(['name' => 'Soap']);
    $tag = Tag::create(['name' => 'Halal']);
    $product = Product::create(['name' => 'Basmati', 'brand' => 'Example', 'subcategory_id' => $rice->id, 'is_active' => true]);
    $product->tags()->attach([$tag->id, Tag::create(['name' => 'Halal certified'])->id]);
    Product::create(['name' => 'Soap bar', 'brand' => null, 'subcategory_id' => $soap->id, 'is_active' => false]);

    return [$product, $rice, $soap, $tag];
}

test('every role can browse catalogue fields with the correct create permission', function (RoleCode $role) {
    [$product] = productListRecords();
    $this->actingAs(productListViewer($role))->get(route('products.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('products/Index')
            ->has('products.data', 2)->where('products.per_page', 10)
            ->where('products.data.0.id', $product->id)->missing('products.data.0.department')
            ->missing('products.data.0.category')->missing('products.data.0.subcategory')
            ->where('products.data.0.brand', 'Example')->has('products.data.0.tags', 2)
            ->where('products.data.1.is_active', false)->where('products.data.1.brand', null)
            ->missing('products.data.0.code')->missing('products.data.0.quantity')
            ->where('canCreate', $role !== RoleCode::VOLUNTEER));
})->with(RoleCode::cases());

test('search matches partial product or tag names without duplicate rows', function (string $search) {
    [$product] = productListRecords();
    $this->actingAs(productListViewer())->get(route('products.index', ['search' => $search]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 1)
        ->where('products.data.0.id', $product->id)->where('products.total', 1));
})->with(['asm', 'halal', '  HALAL  ']);

test('each filter works on its own and all filters combine with tag search', function () {
    [$product, $rice, , $tag] = productListRecords();
    $filters = ['department_id' => $rice->category->department_id, 'category_id' => $rice->category_id,
        'subcategory_id' => $rice->id, 'tag_ids' => [$tag->id], 'status' => 'active'];
    $this->actingAs(productListViewer());
    foreach ($filters as $key => $value) {
        $this->get(route('products.index', [$key => $value]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $product->id));
    }
    $this->get(route('products.index', [...$filters, 'search' => 'Halal']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $product->id));
    $this->get(route('products.index', [...$filters, 'status' => 'inactive']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    $this->get(route('products.index', ['status' => 'inactive']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Soap bar'));
    $this->get(route('products.index', ['status' => 'all', 'search' => '']))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 2));
});

test('filters run before ten row pagination and remain in page links', function () {
    [$product, $rice, , $tag] = productListRecords();
    for ($i = 1; $i <= 12; $i++) {
        $row = Product::create(['name' => sprintf('Matching %02d', $i), 'subcategory_id' => $rice->id, 'is_active' => true]);
        $row->tags()->attach($tag->id);
    }
    $filters = ['search' => 'Matching', 'tag_ids' => [$tag->id], 'department_id' => $rice->category->department_id, 'status' => 'active'];
    $this->actingAs(productListViewer())->get(route('products.index', $filters))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 10)->where('products.total', 12)
            ->where('products.data.0.name', 'Matching 01')->where('products.data.9.name', 'Matching 10')
            ->where('products.next_page_url', function ($url) use ($filters) {
                parse_str(parse_url($url, PHP_URL_QUERY), $query);

                return $query == [...$filters, 'page' => 2];
            }));
    $this->get(route('products.index', [...$filters, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 2)->where('products.data.0.name', 'Matching 11'));
    $this->get(route('products.index', [...$filters, 'page' => 3]))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('empty and unmatched lists render correctly', function () {
    $this->actingAs(productListViewer())->get(route('products.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0)->where('products.last_page', 1));
    productListRecords();
    $this->get(route('products.index', ['search' => 'no-match']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('contradictory hierarchy selections are rejected', function () {
    [, $rice, $soap] = productListRecords();
    $this->actingAs(productListViewer());
    foreach ([
        ['department_id' => $rice->category->department_id, 'category_id' => $soap->category_id],
        ['department_id' => $rice->category->department_id, 'subcategory_id' => $soap->id],
        ['category_id' => $rice->category_id, 'subcategory_id' => $soap->id],
    ] as $filters) {
        $this->getJson(route('products.index', $filters))->assertUnprocessable();
    }
});

test('invalid catalogue filters are rejected', function (array $filters, string $field) {
    $this->actingAs(productListViewer())->getJson(route('products.index', $filters))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['search' => ['bad']], 'search'], [['search' => str_repeat('a', 101)], 'search'],
    [['page' => 0], 'page'], [['page' => 'bad'], 'page'],
    [['department_id' => 99999], 'department_id'], [['department_id' => ['bad']], 'department_id'],
    [['category_id' => 99999], 'category_id'], [['subcategory_id' => 99999], 'subcategory_id'],
    [['tag_ids' => [99999]], 'tag_ids.0'], [['status' => 'unknown'], 'status'],
]);

test('guests cannot browse products', function () {
    $this->get(route('products.index'))->assertRedirect(route('login'));
});

test('multiple tags match any selected tag once and combine with other filters', function () {
    [$product, , , $halal] = productListRecords();
    $vegan = Tag::create(['name' => 'Vegan']);
    $soap = Product::where('name', 'Soap bar')->firstOrFail();
    $soap->tags()->attach($vegan->id);
    $product->tags()->attach($vegan->id);
    $this->actingAs(productListViewer(RoleCode::VOLUNTEER));
    $filters = ['tag_ids' => [$halal->id, $vegan->id]];
    $this->get(route('products.index', $filters))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 2)->where('products.total', 2));
    $this->get(route('products.index', [...$filters, 'status' => 'inactive']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $soap->id));
    $this->get(route('products.index', ['tag_ids' => []]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 2));
});

test('malformed and duplicate selected tags are rejected', function (mixed $ids, string $field) {
    $this->actingAs(productListViewer())->getJson(route('products.index', ['tag_ids' => $ids]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['bad', 'tag_ids'],
    [['bad'], 'tag_ids.0'],
    [[''], 'tag_ids.0'],
    [[1, 1], 'tag_ids.0'],
]);
