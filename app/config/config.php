<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => env('APP_NAME', 'CMMS Industrial'),
        'env' => env('APP_ENV', 'production'),
        'debug' => env('APP_DEBUG', 'false') === 'true',
        'url' => env('APP_URL', ''),
    ],
    'db' => [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', '3306'),
        'name' => env('DB_NAME', ''),
        'user' => env('DB_USER', ''),
        'password' => env('DB_PASSWORD', ''),
    ],
    'session' => [
        'name' => env('SESSION_NAME', 'cmms_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', '3600'),
    ],
    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'api_key' => env('AI_API_KEY', ''),
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY', ''),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        'pro_price_id' => env('STRIPE_PRO_PRICE_ID', 'price_pro_147'),
        'enterprise_price_id' => env('STRIPE_ENTERPRISE_PRICE_ID', 'price_enterprise_997'),
    ],
];