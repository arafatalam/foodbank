<?php

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('every role goes to dashboard instead of a remembered destination', function (RoleCode $role) {
    // Give each role an active account that has already completed setup.
    $user = User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'is_active' => true,
        'must_change_password' => false,
    ]);

    // A protected page visited before login must not override Dashboard.
    $this->withSession(['url.intended' => url('/stock-items')])
        ->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionMissing('url.intended');
})->with(RoleCode::cases());

test('dashboard landing still requires initial password setup', function () {
    $user = User::factory()->create(['is_active' => true, 'must_change_password' => true]);

    // The login destination is fixed, but setup still guards that destination.
    $this->withSession(['url.intended' => url('/products')])
        ->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('password.setup.show'));
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
