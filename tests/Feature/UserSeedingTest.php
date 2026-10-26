<?php

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

test('database seeding creates one verified super admin with separate profile fields', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->sole();

    expect(Role::query()->count())->toBe(3)
        ->and($user->role->code)->toBe(RoleCode::SUPER_ADMIN)
        ->and($user->first_name)->toBe(config('superadmin.first_name'))
        ->and($user->last_name)->toBe(config('superadmin.last_name'))
        ->and($user->phone)->toBe(config('superadmin.phone'))
        ->and($user->is_active)->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check(config('superadmin.password'), $user->password))->toBeTrue();
});

test('seeding preserves an existing super admin and personal password', function () {
    $this->seed(RoleSeeder::class);
    $role = Role::query()->where('code', RoleCode::SUPER_ADMIN->value)->firstOrFail();
    $user = User::factory()->create([
        'role_id' => $role->id,
        'password' => 'Existing-personal-password-42!',
        'must_change_password' => false,
    ]);
    $original = $user->fresh()->getAttributes();

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(1)
        ->and($user->fresh()->getAttributes())->toBe($original);
});

test('user factory reuses the volunteer role and supplies profile fields', function () {
    $users = User::factory()->count(2)->create();

    expect(Role::query()->count())->toBe(1);

    foreach ($users as $user) {
        expect($user->role->code)->toBe(RoleCode::VOLUNTEER)
            ->and($user->first_name)->not->toBeEmpty()
            ->and($user->last_name)->not->toBeEmpty()
            ->and($user->phone)->toMatch('/^\+1 506-555-01[0-9]{2}$/');
    }
});
