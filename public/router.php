<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Enrutador para el servidor embebido de PHP (solo desarrollo)
 |--------------------------------------------------------------------------
 | Uso:  php -S localhost:8000 -t public public/router.php
 |
 | En producción (Apache) el enrutamiento lo resuelve public/.htaccess.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);

if ($path !== '/' && is_file($file) && basename($file) !== 'index.php') {
    return false; // PHP sirve el archivo estático directamente.
}

require __DIR__ . '/index.php';
