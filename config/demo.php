<?php

return [
    // Online demo seeding must be deliberately enabled.
    'seeding_enabled' => env('DEMO_SEEDING_ENABLED', false),

    // Read the online demo account passwords from private settings.
    'manager_password' => env('DEMO_MANAGER_PASSWORD'),
    'volunteer_password' => env('DEMO_VOLUNTEER_PASSWORD'),
];
