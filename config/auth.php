<?php

return [

    'defaults' => [
        'guard'     => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'users'],
        // Pelamar: sesi cookie biasa (satu cookie per domain)
        'applicant' => ['driver' => 'session', 'provider' => 'applicants'],
    ],

    'providers' => [
        'users'      => ['driver' => 'eloquent', 'model' => App\Models\User::class],
        'applicants' => ['driver' => 'eloquent', 'model' => App\Models\Applicantdata::class],
    ],

    'passwords' => [
        'users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60],
    ],

    'password_timeout' => 10800,
];
