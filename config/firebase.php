<?php

return [
    'default' => 'app',

    'projects' => [
        'app' => [
            'credentials' => env('FIREBASE_CREDENTIALS'),
            'auth' => [
                'tenant_id' => env('FIREBASE_AUTH_TENANT_ID'),
            ],
            'firestore' => [
                'database' => env('FIREBASE_FIRESTORE_DATABASE'),
            ],
            'database' => [
                'url' => env('FIREBASE_DATABASE_URL'),
            ],
            'storage' => [
                'default_bucket' => env('FIREBASE_STORAGE_DEFAULT_BUCKET'),
            ],
            'cache_store' => 'file', // Força usar arquivo para evitar conflito gRPC + Database/Redis
            'logging' => [
                'http_log_channel' => null,
                'http_debug_log_channel' => null,
            ],
            'http_client_options' => [
                'proxy' => null,
                'timeout' => null,
            ],
        ],
    ],
];
