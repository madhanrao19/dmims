<?php

return [
    'path' => env('FILAMENT_PATH', 'admin'),
    'auth' => [
        'guard' => 'web',
        'pages' => [
            'login' => [
                'username' => 'email',
            ],
        ],
    ],
    'features' => [
        'notifications' => true,
        'widgets' => true,
        'forms' => true,
        'tables' => true,
    ],
    'branding' => [
        'logo' => null,
        'favicon' => null,
        'colors' => [],
    ],
];
