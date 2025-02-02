<?php

return [
    'name'     => 'SystemBase',
    'schedule' => [
        // This flag 'enabled' have to be true to make schedules working!
        'enabled' => env('SCHEDULE_ENABLED', true),
    ],
    'cache'    => [
        'enabled' => env('CACHE_ENABLED', true),
        'default_ttl'  => env('MODULE_SYSTEM_BASE_CACHE_DEFAULT_TTL', 1),
        'object'       => [
            'signature' => [
                'ttl' => env('MODULE_SYSTEM_BASE_CACHE_OBJECT_SIGNATURE_TTL', 1),
            ],
            'instance'  => [
                'ttl' => env('MODULE_SYSTEM_BASE_CACHE_OBJECT_INSTANCE_TTL', 1),
            ],
        ],
        'db'           => [
            'signature' => [
                'ttl' => env('MODULE_SYSTEM_BASE_CACHE_DB_SIGNATURE_TTL', 1),
            ],
        ],
        'translations' => [
            'ttl' => env('MODULE_SYSTEM_BASE_CACHE_TRANSLATIONS_TTL', 1),
        ],
        'frontend' => [
            'ttl' => env('MODULE_SYSTEM_BASE_CACHE_FRONTEND_TTL', 1),
        ],
    ],
];
