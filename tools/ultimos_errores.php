<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | CSDUV3 - Últimos errores del registro
 |--------------------------------------------------------------------------
 |
 | Muestra las últimas líneas del registro de la aplicación y del despliegue,
 | que es donde queda anotado el detalle técnico de los problemas.
 |
 | Uso (solo desde la Terminal / SSH):
 |     php8.3 tools/ultimos_errores.php [lineas]
 |
 | Por defecto muestra las últimas 40 líneas.
 */

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(404);
    }

    exit('No disponible.');
}

$appPath = dirname(__DIR__);

require $appPath . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();
\App\Core\Env::load($appPath . '/.env');
\App\Core\Config::load($appPath . '/config');

use App\Core\Config;

$lineas = (int) ($argv[1] ?? 40);
$lineas = max(5, min(500, $lineas));

function mostrar(string $titulo, string $archivo, int $lineas): void
{
    fwrite(STDOUT, PHP_EOL . '== ' . $titulo . ' ==' . PHP_EOL);

    if (!is_file($archivo)) {
        fwrite(STDOUT, '(no existe todavia: ' . $archivo . ')' . PHP_EOL);

        return;
    }

    $contenido = (string) file_get_contents($archivo);
    $partes = preg_split('/\R/', $contenido) ?: [];
    $ultimas = array_slice($partes, -$lineas);

    foreach ($ultimas as $linea) {
        fwrite(STDOUT, $linea . PHP_EOL);
    }
}

$almacenamiento = (string) Config::get('app.storage_path', $appPath . '/storage');

mostrar(
    'Registro de la aplicacion',
    $almacenamiento . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log',
    $lineas
);

mostrar(
    'Registro del despliegue',
    $almacenamiento . DIRECTORY_SEPARATOR . 'deploy.log',
    $lineas
);

fwrite(STDOUT, PHP_EOL);
