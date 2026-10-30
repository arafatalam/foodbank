<?php

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use App\Policies\TagPolicy;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

function tagManager(RoleCode $role = RoleCode::MANAGER): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true,
        'must_change_password' => false,
    ]);
}

test('catalogue managers create and rename a shared tag without replacing its identity', function (RoleCode $role) {
    $this->actingAs(tagManager($role));
    $this->get(route('tags.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tags/Create'));

    $this->from(route('tags.create'))->post(route('tags.store'), [
        'name' => '  Gluten-free  ', 'id' => 999, 'quantity' => 50,
    ])->assertRedirect(route('tags.create'))->assertSessionHasNoErrors();
    $tag = Tag::sole();
    expect($tag->name)->toBe('Gluten-free');
    expect($tag->id)->not->toBe(999);
    expect($tag->getAttributes())->not->toHaveKey('quantity');

    $this->get(route('tags.edit', $tag))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('tags/Edit')->where('tag', ['id' => $tag->id, 'name' => 'Gluten-free']));
    $this->patch(route('tags.update', $tag), ['name' => '  Low sodium  ', 'id' => 999])
        ->assertRedirect(route('tags.index'))->assertSessionHasNoErrors();
    expect($tag->fresh()->name)->toBe('Low sodium');
    $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Low sodium']);
    $this->assertDatabaseCount('tags', 1);
    $this->get(route('tags.index'))->assertInertia(fn (Assert $page) => $page
        ->where('tags.data.0.name', 'Low sodium')->where('tags.data.0.canEdit', true)->where('canCreate', true));
})->with([RoleCode::MANAGER, RoleCode::SUPER_ADMIN]);

test('invalid tag names cannot create or rename records', function (mixed $name) {
    $tag = Tag::create(['name' => 'Original']);
    Tag::create(['name' => 'Existing']);
    $this->actingAs(tagManager());
    $this->from(route('tags.create'))->post(route('tags.store'), ['name' => $name])
        ->assertRedirect(route('tags.create'))->assertSessionHasErrors('name');
    $this->from(route('tags.edit', $tag))->patch(route('tags.update', $tag), ['name' => $name])
        ->assertRedirect(route('tags.edit', $tag))->assertSessionHasErrors('name');
    expect($tag->fresh()->name)->toBe('Original');
    $this->assertDatabaseCount('tags', 2);
})->with(['null' => null, 'blank' => '   ', 'array' => [['bad']], 'long' => str_repeat('a', 256), 'duplicate' => ' Existing ']);

test('tag names accept the maximum length and can keep their current name', function () {
    $this->actingAs(tagManager())->post(route('tags.store'), ['name' => str_repeat('a', 255)])
        ->assertSessionHasNoErrors();
    $tag = Tag::sole();
    $this->patch(route('tags.update', $tag), ['name' => $tag->name])->assertSessionHasNoErrors();
    $this->patch(route('tags.update', $tag), ['name' => str_repeat('b', 255)])->assertSessionHasNoErrors();
    expect($tag->fresh()->name)->toBe(str_repeat('b', 255));
});

test('tag search filters before pagination and retains the search on page links', function () {
    foreach (range(1, 12) as $i) {
        Tag::create(['name' => sprintf('Matching %02d', $i)]);
    }
    Tag::create(['name' => 'A different label']);
    $this->actingAs(tagManager());
    $this->get(route('tags.index', ['search' => '  Matching  ']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tags/Index')->where('search', 'Matching')
            ->has('tags.data', 10)->where('tags.total', 12)->where('tags.data.0.name', 'Matching 01')
            ->where('tags.next_page_url', fn ($url) => str_contains($url, 'search=Matching') && str_contains($url, 'page=2')));
    $this->get(route('tags.index', ['search' => 'Matching', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('tags.data', 2)->where('tags.data.0.name', 'Matching 11'));
    $this->get(route('tags.index', ['search' => 'no-such-tag']))
        ->assertInertia(fn (Assert $page) => $page->has('tags.data', 0));
    $this->get(route('tags.index', ['search' => '']))
        ->assertInertia(fn (Assert $page) => $page->where('tags.total', 13));
});

test('the empty tag list has valid pagination', function () {
    $this->actingAs(tagManager())->get(route('tags.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('tags.data', 0)
            ->where('tags.current_page', 1)->where('tags.last_page', 1));
});

test('invalid tag list filters are rejected', function (array $filters, string $field) {
    $this->actingAs(tagManager())->getJson(route('tags.index', $filters))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['search' => ['bad']], 'search'], [['search' => str_repeat('a', 101)], 'search'],
    [['page' => 0], 'page'], [['page' => 1.5], 'page'], [['page' => 2147483648], 'page'],
]);

test('volunteers and guests cannot access tag pages or write tags', function (bool $signedIn) {
    $tag = Tag::create(['name' => 'Original']);
    if ($signedIn) {
        $this->actingAs(tagManager(RoleCode::VOLUNTEER));
    }
    foreach (['tags.index', 'tags.create', 'tags.edit'] as $route) {
        $response = $this->get(route($route, $route === 'tags.edit' ? $tag : []));
        $signedIn ? $response->assertForbidden() : $response->assertRedirect(route('login'));
    }
    foreach ([$this->post(route('tags.store'), ['name' => 'New']), $this->patch(route('tags.update', $tag), ['name' => 'Changed'])] as $response) {
        $signedIn ? $response->assertForbidden() : $response->assertRedirect(route('login'));
    }
    expect($tag->fresh()->name)->toBe('Original');
    $this->assertDatabaseCount('tags', 1);
})->with([true, false]);

test('missing tags return not found and deletion is not exposed', function () {
    $tag = Tag::create(['name' => 'Original']);
    $this->actingAs(tagManager());
    $this->get(route('tags.edit', 999999))->assertNotFound();
    $this->patch(route('tags.update', 999999), ['name' => 'Changed'])->assertNotFound();
    $this->delete('/tags/'.$tag->id)->assertMethodNotAllowed();
    $this->assertDatabaseCount('tags', 1);
});

test('edit buttons and both edit routes respect policy denial independently of list access', function () {
    $tag = Tag::create(['name' => 'Original']);
    Gate::policy(Tag::class, ReadOnlyTagTestPolicy::class);
    $this->actingAs(tagManager())->get(route('tags.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tags.data.0.canEdit', false));
    $this->get(route('tags.edit', $tag))->assertForbidden();
    $this->patch(route('tags.update', $tag), ['name' => 'Changed'])->assertForbidden();
    expect($tag->fresh()->name)->toBe('Original');
});

class ReadOnlyTagTestPolicy extends TagPolicy
{
    public function update(User $user, Tag $tag): bool
    {
        return false;
    }
}
