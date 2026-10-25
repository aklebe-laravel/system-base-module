<?php

return [
    'name'     => 'SystemBase',
    'schedule' => [
        // This flag 'enabled' have to be true to make schedules working!
        'enabled' => env('SCHEDULE_ENABLED', true),
    ],
    'cache'    => [
        'enabled'             => env('MODULE_SYSTEM_BASE_CACHE_ENABLED', true),
        'default_ttl'         => env('MODULE_SYSTEM_BASE_CACHE_DEFAULT_TTL', 1),
        'default_ttl_low'     => env('MODULE_SYSTEM_BASE_CACHE_DEFAULT_TTL_LOW', 1),
        'default_ttl_big'     => env('MODULE_SYSTEM_BASE_CACHE_DEFAULT_TTL_BIG', 1),
        'object'              => [
            'signature' => [
                'ttl' => env('MODULE_SYSTEM_BASE_CACHE_OBJECT_SIGNATURE_TTL', 1),
            ],
            'instance'  => [
                'ttl' => env('MODULE_SYSTEM_BASE_CACHE_OBJECT_INSTANCE_TTL', 1),
            ],
        ],
        'db'                  => [
            'signature' => [
                'ttl' => env('MODULE_SYSTEM_BASE_CACHE_DB_SIGNATURE_TTL', 1),
            ],
        ],
        'translations'        => [
            'ttl' => env('MODULE_SYSTEM_BASE_CACHE_TRANSLATIONS_TTL', 1),
        ],
        'frontend'            => [
            'ttl' => env('MODULE_SYSTEM_BASE_CACHE_FRONTEND_TTL', 1),
        ],
        'user_based_frontend' => [
            'ttl' => env('MODULE_SYSTEM_BASE_CACHE_DEFAULT_TTL_LOW', 1),
        ],
    ],
];
