<?php

use App\Models\User;

test('public registration is unavailable', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
});
