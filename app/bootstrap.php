<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/helpers/*.php') as $helper) {
    require_once $helper;
}

$config = require __DIR__ . '/config/config.php';

error_reporting(E_ALL);
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e): void {
    log_error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Ha ocurrido un error. Intente nuevamente más tarde.';
});

session_name($config['session']['name']);
session_set_cookie_params([
    'lifetime' => $config['session']['lifetime'],
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
]);
session_start();

spl_autoload_register(function (string $class): void {
    $dirs = ['config', 'controllers', 'models', 'services', 'middleware'];
    $variants = [
        $class,
        strtolower($class),
        ucfirst($class),
        lcfirst($class)
    ];

    foreach ($dirs as $dir) {
        foreach ($variants as $variant) {
            $file = __DIR__ . "/{$dir}/{$variant}.php";
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
});
