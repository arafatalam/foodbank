<?php

use App\Actions\Users\GenerateUsername;
use App\Actions\Users\SaveUser;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

function concurrentUserData(): array
{
    $user = User::factory()->make();

    return $user->only(['first_name', 'last_name', 'email', 'phone', 'role_id', 'is_active']);
}

test('a username taken between checking and saving is regenerated', function () {
    $taken = User::factory()->create(['username' => 'shared.name']);
    $generator = Mockery::mock(GenerateUsername::class);
    $generator->shouldReceive('generate')->twice()->andReturn('shared.name', 'shared.name.01');
    config(['auth.initial_password' => 'InitialCheck123!']);

    $saved = (new SaveUser($generator))->save(concurrentUserData());

    expect($saved->username)->toBe('shared.name.01')
        ->and($saved->must_change_password)->toBeTrue()
        ->and($saved->email_verified_at)->not->toBeNull()
        ->and(User::count())->toBe(2);
});

test('an edit retries without losing deactivation session cleanup', function () {
    config(['session.driver' => 'database']);
    User::factory()->create(['username' => 'shared.name']);
    $user = User::factory()->create(['is_active' => true, 'remember_token' => 'old-token']);
    DB::table('sessions')->insert(['id' => 'old-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $generator = Mockery::mock(GenerateUsername::class);
    $generator->shouldReceive('generate')->twice()->andReturn('shared.name', 'shared.name.01');

    $saved = (new SaveUser($generator))->save([
        ...$user->only(['last_name', 'email', 'phone', 'role_id']),
        'first_name' => 'Changed', 'is_active' => false,
    ], $user);

    expect($saved->username)->toBe('shared.name.01')
        ->and($saved->is_active)->toBeFalse()
        ->and($saved->remember_token)->not->toBe('old-token')
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});

test('unrelated unique errors are not retried', function () {
    $existing = User::factory()->create();
    $generator = Mockery::mock(GenerateUsername::class);
    $generator->shouldReceive('generate')->once()->andReturn('available.name');

    expect(fn () => (new SaveUser($generator))->save([
        ...concurrentUserData(), 'email' => $existing->email,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('repeated collisions stop with a validation message and no partial account', function () {
    User::factory()->create(['username' => 'taken.name']);
    $generator = Mockery::mock(GenerateUsername::class);
    $generator->shouldReceive('generate')->times(5)->andReturn('taken.name');

    expect(fn () => (new SaveUser($generator))->save(concurrentUserData()))
        ->toThrow(ValidationException::class);
    expect(User::count())->toBe(1);
});
