<?php

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('security page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->has('passwordRules')
            ->missing('canManagePasskeys')
            ->missing('passkeys')
            ->missing('canManageTwoFactor')
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation'),
        );
});

test('security page opens without a separate password confirmation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertOk();
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
    $this->assertGuest();
});

test('changing a password ends all of that users database sessions', function (RoleCode $role) {
    config(['session.driver' => 'database']);
    $user = User::factory()->create([
        'role_id' => Role::firstOrCreate(['code' => $role->value])->id,
        'remember_token' => 'previous-remember-token',
    ]);
    $otherUser = User::factory()->create();

    // Represent two devices for this user and one for a different user.
    foreach (['first-device' => $user->id, 'second-device' => $user->id, 'other-user-device' => $otherUser->id] as $id => $userId) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'payload' => '',
            'last_activity' => time(),
        ]);
    }

    $this->actingAs($user)->withSession(['private_marker' => 'old session'])
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionMissing('private_marker');

    $this->assertGuest();
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'other-user-device')->exists())->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe('previous-remember-token');

    // The old password must fail; the new one must start a fresh login.
    $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('login');
    $this->assertGuest();
    $this->post(route('login.store'), ['login' => $user->email, 'password' => 'new-password'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
})->with(RoleCode::cases());

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});

test('a remembered login cannot be reused after changing the password', function () {
    $user = User::factory()->create();
    $login = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);
    $cookieName = auth()->guard('web')->getRecallerName();
    $cookie = collect($login->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === $cookieName);
    expect($cookie)->not->toBeNull();

    $this->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('login'));

    // Simulate another browser presenting the old encrypted Remember me cookie.
    $this->flushSession();
    app('auth')->forgetGuards();
    $this->withUnencryptedCookie($cookieName, $cookie->getValue())
        ->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('invalid password changes keep the password and existing logins intact', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create(['remember_token' => 'unchanged-token']);
    $originalPassword = $user->password;
    DB::table('sessions')->insert([
        'id' => 'existing-device',
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => time(),
    ]);

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'incorrect-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasErrors('current_password');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->password)->toBe($originalPassword)
        ->and($user->fresh()->remember_token)->toBe('unchanged-token')
        ->and(DB::table('sessions')->where('id', 'existing-device')->exists())->toBeTrue();
});
