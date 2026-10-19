<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | CSDUV3 - Punto de entrada público
 |--------------------------------------------------------------------------
 | Único archivo PHP accesible desde el navegador. Toda solicitud pasa por
 | este front controller.
 |
 | El DocumentRoot del subdominio debe apuntar a esta carpeta (/public).
 | Si el hosting no lo permite, consulte docs/DESPLIEGUE_BANAHOSTING.md.
 */

define('CSDUV3_START', microtime(true));

// ---------------------------------------------------------------------------
// 1. Localización de la aplicación
// ---------------------------------------------------------------------------
$appPath = getenv('CSDUV3_APP_PATH');

if (!is_string($appPath) || $appPath === '') {
    $overrideFile = __DIR__ . '/.app_path.php';

    if (is_file($overrideFile)) {
        $custom = require $overrideFile;

        if (is_string($custom) && $custom !== '') {
            $appPath = $custom;
        }
    }
}

if (!is_string($appPath) || $appPath === '') {
    $appPath = dirname(__DIR__);
}

$appPath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $appPath), DIRECTORY_SEPARATOR);

if (!is_dir($appPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('CSDUV3: la ruta de la aplicación no es válida.');
}

define('APP_PATH', $appPath);
define('PUBLIC_PATH', __DIR__);

// ---------------------------------------------------------------------------
// 2. Carga de clases, configuración y utilidades
// ---------------------------------------------------------------------------
require APP_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();
\App\Core\Env::load(APP_PATH . '/.env');
\App\Core\Config::load(APP_PATH . '/config');

require APP_PATH . '/app/Core/helpers.php';

date_default_timezone_set((string) \App\Core\Config::get('app.timezone', 'America/Lima'));

// Cadenas en español para fechas y números cuando el sistema lo permite.
@setlocale(LC_TIME, 'es_PE.UTF-8', 'es_PE.utf8', 'es_PE', 'Spanish_Peru');

// ---------------------------------------------------------------------------
// 3. Manejo de errores y cabeceras de seguridad
// ---------------------------------------------------------------------------
\App\Core\ErrorHandler::register((bool) \App\Core\Config::get('app.debug', false));

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// ---------------------------------------------------------------------------
// 4. Sesión y enrutamiento
// ---------------------------------------------------------------------------
\App\Core\Session::start();

$router = new \App\Core\Router();

require APP_PATH . '/app/routes.php';

$router->dispatch(\App\Core\Request::capture());
