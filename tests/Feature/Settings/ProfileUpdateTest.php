<?php

use App\Models\User;

test('settings opens security instead of profile editing', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings')
        ->assertRedirect('/settings/security');
});

test('self service profile editing and deletion are unavailable', function () {
    $user = User::factory()->create();
    $original = $user->fresh()->getAttributes();

    $this->actingAs($user)->get('/settings/profile')->assertNotFound();
    $this->patch('/settings/profile', [
        'first_name' => 'Changed',
        'last_name' => 'Name',
        'email' => 'changed@example.com',
    ])->assertNotFound();
    $this->delete('/settings/profile', [
        'password' => 'password',
    ])->assertNotFound();

    expect($user->fresh()->getAttributes())->toBe($original);
    $this->assertAuthenticatedAs($user);
});
