<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | CSDUV3 - Aplicador de migraciones
 |--------------------------------------------------------------------------
 |
 | Ejecuta, en orden, los archivos SQL de `database/migrations/` que todavía
 | no se han aplicado. Cada migración se registra en la tabla
 | `csduv3_migraciones` para no ejecutarla dos veces.
 |
 | Usa la misma lógica que el botón «Actualizar la base de datos» del panel
 | (App\Core\Migraciones), para que ambas formas de actualizar coincidan.
 |
 | Uso (solo desde la Terminal / SSH, nunca desde el navegador):
 |     php tools/migrar.php
 |     php tools/migrar.php --estado
 |     php tools/migrar.php --permitir-destructivo
 |
 | La conexión se obtiene del archivo `.env` a través de la propia
 | configuración de la aplicación, de modo que no hay credenciales escritas
 | en el código.
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

date_default_timezone_set((string) \App\Core\Config::get('app.timezone', 'America/Lima'));

use App\Core\Database;
use App\Core\Migraciones;

/**
 * Escribe una línea en la salida estándar.
 */
function informar(string $mensaje = ''): void
{
    fwrite(STDOUT, $mensaje . PHP_EOL);
}

function abortar(string $mensaje): never
{
    fwrite(STDERR, 'Error: ' . $mensaje . PHP_EOL);
    exit(1);
}

$opciones = array_slice($argv, 1);
$mostrarEstado = in_array('--estado', $opciones, true);
$permitirDestructivo = in_array('--permitir-destructivo', $opciones, true);

if (!Database::isAvailable()) {
    abortar(
        'no se pudo conectar a la base de datos. Revise el archivo .env y que el servidor de '
        . 'base de datos esté disponible.'
    );
}

Informar();
Informar('CSDUV3 · Migraciones');
Informar('====================');
Informar();

$aplicadas = Migraciones::aplicadas();
$pendientes = Migraciones::pendientes();

Informar('Migraciones aplicadas : ' . ($aplicadas === [] ? '(ninguna)' : implode(', ', $aplicadas)));
Informar('Migraciones pendientes: ' . ($pendientes === [] ? 'ninguna' : implode(', ', $pendientes)));
Informar();

if ($mostrarEstado) {
    exit($pendientes === [] ? 0 : 1);
}

if ($pendientes === []) {
    Informar('No hay migraciones pendientes. La base de datos está actualizada.');
    exit(0);
}

foreach ($pendientes as $pendiente) {
    Informar('Aplicando ' . $pendiente . ' ...');
}

$resultado = Migraciones::aplicar($permitirDestructivo);

foreach ($resultado['aplicadas'] as $aplicada) {
    Informar('  -> ' . $aplicada . ': Éxito.');
}

if ($resultado['errores'] !== []) {
    foreach ($resultado['errores'] as $error) {
        fwrite(STDERR, '  ERROR: ' . $error . PHP_EOL);
    }

    abortar('no se pudieron aplicar todas las migraciones. Revise los mensajes anteriores.');
}

// Comprobacion de tablas: deja constancia de si el sistema tiene todo lo que
// necesita. Es la misma verificacion que usa tools/verificar_migraciones.php.
$faltantes = Migraciones::tablasFaltantes();
$columnasFaltantes = Migraciones::columnasFaltantes();

if ($faltantes !== []) {
    Informar('ADVERTENCIA: siguen sin existir estas tablas: ' . implode(', ', $faltantes));
    Informar('Revise el archivo .env y los permisos del usuario de base de datos.');
} else {
    Informar('Comprobacion de tablas: todo en orden.');
}

if ($columnasFaltantes !== []) {
    Informar('ADVERTENCIA: siguen sin existir estas columnas: ' . implode(', ', $columnasFaltantes));
}

Informar();
Informar(sprintf('Se aplicaron %d migración(es).', count($resultado['aplicadas'])));
