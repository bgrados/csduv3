<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | CSDUV3 - Diagnóstico de migraciones y tablas
 |--------------------------------------------------------------------------
 |
 | Muestra, en un solo comando, el estado de la base de datos: versión de PHP
 | usada, migraciones aplicadas y pendientes, y si existen las tablas que el
 | sistema necesita.
 |
 | Uso (solo desde la Terminal / SSH):
 |     php tools/verificar_migraciones.php
 |
 | Si alguma migración está pendiente, ejecute:
 |     php tools/migrar.php
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
use App\Core\Database;

/**
 * Escribe una línea en la salida estándar.
 */
function informar(string $mensaje = ''): void
{
    fwrite(STDOUT, $mensaje . PHP_EOL);
}

informar();
informar('CSDUV3 · Diagnóstico de la base de datos');
informar('=======================================');
informar();
informar('PHP en consola : ' . PHP_VERSION . ' (' . PHP_SAPI . ')');
informar('Servidor       : ' . (string) Config::get('app.url', '(sin configurar)'));
informar('Base de datos  : ' . (string) Config::get('database.database', '(sin configurar)'));
informar();

if (!Database::isAvailable()) {
    informar('ERROR: no se pudo conectar a la base de datos.');
    informar('Revise el archivo .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD) y que el');
    informar('servidor de base de datos esté disponible.');
    exit(1);
}

$pdo = Database::connection();
$prefijo = (string) Config::get('database.prefix', 'csduv3_');
$tablaMigraciones = $prefijo . 'migraciones';

// --- Migraciones ------------------------------------------------------------

$aplicadas = [];
$tieneTablaMigraciones = (int) $pdo->query(
    sprintf(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '%s'",
        $tablaMigraciones
    )
)->fetchColumn() > 0;

if ($tieneTablaMigraciones) {
    $consulta = $pdo->prepare(sprintf('SELECT `nombre_archivo` FROM `%s` ORDER BY `nombre_archivo`', $tablaMigraciones));
    $consulta->execute();

    foreach ($consulta->fetchAll(PDO::FETCH_COLUMN) as $nombre) {
        $aplicadas[] = (string) $nombre;
    }
}

$archivos = glob($appPath . '/database/migrations/*.sql') ?: [];
sort($archivos);

$pendientes = [];

foreach ($archivos as $archivo) {
    $nombre = basename($archivo);

    if (!in_array($nombre, $aplicadas, true)) {
        $pendientes[] = $nombre;
    }
}

informar('Migraciones aplicadas : ' . ($aplicadas === [] ? '(ninguna)' : implode(', ', $aplicadas)));
informar('Migraciones pendientes: ' . ($pendientes === [] ? 'ninguna' : implode(', ', $pendientes)));
informar();

if ($pendientes !== []) {
    informar('Aplique las migraciones pendientes con:');
    informar('    php tools/migrar.php');
    informar();
}

// --- Tablas esperadas -------------------------------------------------------

$esperadas = [
    'usuarios', 'roles', 'permisos', 'socios', 'categorias_socio',
    'instalaciones', 'tarifas', 'tipos_evento', 'reservas', 'solicitudes_alquiler',
    'reserva_pagos', 'categorias_movimiento', 'medios_pago', 'periodos', 'movimientos',
    'movimientos_auditoria', 'directivas', 'directiva_cargos', 'directiva_miembros',
    'procesos_electorales', 'listas_electorales', 'candidatos', 'resultados_electorales',
    'deportes', 'deporte_puntos', 'torneos', 'torneo_equipos', 'jornadas', 'encuentros',
    'documentos', 'actividades', 'fotos', 'negocios', 'auditoria',
];

$consulta = $pdo->prepare(
    "SELECT `table_name` FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE :prefijo"
);
$consulta->execute(['prefijo' => $prefijo . '%']);

$existentes = [];

foreach ($consulta->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
    $existentes[] = (string) $tabla;
}

$total = count($existentes);
$informes = [];

foreach ($esperadas as $tabla) {
    if (!in_array($prefijo . $tabla, $existentes, true)) {
        $informes[] = $tabla;
    }
}

informar('Tablas del sistema    : ' . $total . ' encontradas (se esperaban ' . count($esperadas) . ')');

if ($informes === []) {
    informar('Todas las tablas necesarias existen.');
} else {
    informar('FALTAN estas tablas: ' . implode(', ', $informes));
    informar('Aplique las migraciones: php tools/migrar.php');
}

informar();

// --- Columnas que agregan las migraciones recientes ------------------------

$columnasEsperadas = [
    'fotos' => ['video_url', 'video_tipo'],
];

Informar('Columnas recientes   :');

$columnasFaltantes = [];

foreach ($columnasEsperadas as $tabla => $columnas) {
    foreach ($columnas as $columna) {
        $existe = (int) $pdo->query(sprintf(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = '%s%s' AND column_name = '%s'",
            $prefijo,
            $tabla,
            $columna
        ))->fetchColumn();

        if ($existe === 0) {
            $columnasFaltantes[] = $prefijo . $tabla . '.' . $columna;
            Informar(sprintf('  FALTA  %s%s.%s', $prefijo, $tabla, $columna));
            continue;
        }

        Informar(sprintf('  OK     %s%s.%s', $prefijo, $tabla, $columna));
    }
}

if ($columnasFaltantes !== []) {
    Informar('Aplique las migraciones pendientes para que el sistema use esas columnas.');
}

Informar();

if ($pendientes === [] && $informes === [] && $columnasFaltantes === []) {
    Informar('Todo en orden: puede continuar usando el sistema.');
    exit(0);
}

exit(1);
