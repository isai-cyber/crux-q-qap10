<?php

return [
    'provider' => env('AI_PROVIDER', 'deepseek'),
    'api_key' => env('AI_API_KEY', ''),
    'model' => env('AI_MODEL', 'deepseek-chat'),
    'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
];
