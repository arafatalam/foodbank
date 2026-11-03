<?php

use App\Actions\Users\GenerateUsername;
use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;

test('ordinary usernames keep the existing normalization', function () {
    expect(app(GenerateUsername::class)->generate('Mary Jane', "Émilie-O'Connor"))
        ->toBe('maryjane.emilieoconnor');
});

test('expanded names fit the database and retain both name parts', function () {
    $username = app(GenerateUsername::class)->generate(str_repeat('Æ', 100), str_repeat('ß', 100));
    expect(strlen($username))->toBe(255)
        ->and($username)->toMatch('/^[a-z]+\.[a-z]+$/');
});

test('long duplicate usernames leave room for growing suffixes and ignore the edited account', function () {
    $generator = app(GenerateUsername::class);
    $first = str_repeat('Æ', 100);
    $last = str_repeat('ß', 100);

    // Persist enough duplicates to cross from two-digit to three-digit endings.
    for ($index = 0; $index <= 100; $index++) {
        $username = $generator->generate($first, $last);
        expect(strlen($username))->toBeLessThanOrEqual(255);
        if ($index > 0) {
            expect($username)->toEndWith('.'.str_pad((string) $index, 2, '0', STR_PAD_LEFT));
        }
        $user = User::factory()->create(['username' => $username]);
    }

    expect($generator->generate($first, $last, $user))->toBe($user->username);
});

test('long names can be created and edited without changing the stored names', function () {
    $role = Role::firstOrCreate(['code' => RoleCode::SUPER_ADMIN->value]);
    $actor = User::factory()->create(['role_id' => $role->id]);
    $volunteerRole = Role::firstOrCreate(['code' => RoleCode::VOLUNTEER->value]);
    $data = [
        'first_name' => str_repeat('Æ', 100),
        'last_name' => str_repeat('ß', 100),
        'email' => 'long-name@example.test',
        'phone' => '+1 506-555-0123',
        'role_id' => $volunteerRole->id,
        'is_active' => true,
    ];
    config(['auth.initial_password' => 'InitialCheck123!']);
    $this->actingAs($actor)->post(route('users.store'), $data)->assertSessionHasNoErrors();
    $user = User::where('email', $data['email'])->sole();
    expect($user->first_name)->toBe($data['first_name'])
        ->and($user->last_name)->toBe($data['last_name'])
        ->and(strlen($user->username))->toBeLessThanOrEqual(255);

    $data['first_name'] = str_repeat('ß', 100);
    $this->patch(route('users.update', $user), $data)->assertSessionHasNoErrors()->assertRedirect(route('users.index'));
    expect($user->fresh()->first_name)->toBe($data['first_name'])
        ->and(strlen($user->fresh()->username))->toBeLessThanOrEqual(255);
});
