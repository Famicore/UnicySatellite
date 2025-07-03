<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UnicyHub Central Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour la communication avec UnicyHub central
    |
    */
    'hub' => [
        'url' => env('UNICYHUB_URL', 'https://hub.unicy.io'),
        'api_key' => env('UNICYHUB_API_KEY'),
        'timeout' => env('UNICYHUB_TIMEOUT', 30),
        'retry_attempts' => env('UNICYHUB_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('UNICYHUB_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Satellite Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration de cette application satellite
    |
    */
    'satellite' => [
        'name' => env('SATELLITE_NAME', config('app.name')),
        'type' => env('SATELLITE_TYPE', 'default'), // logistik, vinci, pixel, etc.
        'version' => env('SATELLITE_VERSION', '1.0.0'),
        'url' => env('SATELLITE_URL', config('app.url')),
        'api_prefix' => env('SATELLITE_API_PREFIX', 'api/satellite'),
        'enabled' => env('SATELLITE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronization Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour la synchronisation avec UnicyHub
    |
    */
    'sync' => [
        'enabled' => env('SATELLITE_SYNC_ENABLED', true),
        'interval' => env('SATELLITE_SYNC_INTERVAL', 300), // seconds
        'batch_size' => env('SATELLITE_SYNC_BATCH_SIZE', 100),
        'auto_register' => env('SATELLITE_AUTO_REGISTER', true),
        'send_metrics' => env('SATELLITE_SEND_METRICS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour les checks de santé
    |
    */
    'health' => [
        'enabled' => env('SATELLITE_HEALTH_ENABLED', true),
        'endpoint' => env('SATELLITE_HEALTH_ENDPOINT', '/health'),
        'checks' => [
            'database' => true,
            'cache' => true,
            'storage' => true,
            'queue' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour l'envoi de métriques vers UnicyHub
    |
    */
    'metrics' => [
        'enabled' => env('SATELLITE_METRICS_ENABLED', true),
        'interval' => env('SATELLITE_METRICS_INTERVAL', 60), // seconds
        'include' => [
            'users_count' => true,
            'tenants_count' => true,
            'active_sessions' => true,
            'memory_usage' => true,
            'cpu_usage' => true,
            'disk_usage' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration de sécurité pour les communications
    |
    */
    'security' => [
        'verify_ssl' => env('SATELLITE_VERIFY_SSL', true),
        'rate_limit' => env('SATELLITE_RATE_LIMIT', 100), // requests per minute
        'ip_whitelist' => env('SATELLITE_IP_WHITELIST', ''),
        'encryption_key' => env('SATELLITE_ENCRYPTION_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration du cache pour les données synchronisées
    |
    */
    'cache' => [
        'enabled' => env('SATELLITE_CACHE_ENABLED', true),
        'ttl' => env('SATELLITE_CACHE_TTL', 3600), // seconds
        'prefix' => env('SATELLITE_CACHE_PREFIX', 'satellite'),
        'tags' => ['satellite', 'sync', 'unicyhub'],
    ],
]; 