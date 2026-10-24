<?php

declare(strict_types=1);

function log_error(string $message): void
{
    $dir = dirname(__DIR__, 2) . '/storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $line = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $message, PHP_EOL);
    file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
}
