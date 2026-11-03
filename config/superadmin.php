<?php

// Super Admin Credentials

return [
    'first_name' => 'Super',
    'last_name' => 'Admin',
    'phone' => '+1 506-555-0100',
    'username' => 'super.admin',
    'email' => 'super.admin@example.com',
    // Read this installation's password from its environment settings.
    'password' => env('SUPER_ADMIN_PASSWORD'),
];
