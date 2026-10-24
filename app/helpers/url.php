<?php

declare(strict_types=1);

function base_path(): string
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }
    $path = parse_url($config['app']['url'], PHP_URL_PATH) ?: '';
    return rtrim($path, '/');
}

function base_url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}
