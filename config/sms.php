<?php

return [
    // Used only by AdminUserSeeder to create the first admin account.
    'admin_name' => env('SMS_ADMIN_NAME', 'Administrator'),
    'admin_email' => env('SMS_ADMIN_EMAIL'),
    'admin_password' => env('SMS_ADMIN_PASSWORD'),
];
