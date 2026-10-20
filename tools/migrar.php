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

/**
 * Divide un archivo SQL en sentencias ejecutables de forma individual.
 *
 * Respeta las comillas simples, dobles y las de identificadores, así como
 * los comentarios de línea (`--`) y de bloque.
 *
 * @return array<int, string>
 */
function dividirEnSentencias(string $sql): array
{
    $sentencias = [];
    $actual = '';
    $longitud = strlen($sql);
    $i = 0;

    while ($i < $longitud) {
        $caracter = $sql[$i];
        $siguiente = $i + 1 < $longitud ? $sql[$i + 1] : '';

        // Comentario de línea: se descarta hasta el fin de línea.
        if ($caracter === '-' && $siguiente === '-') {
            while ($i < $longitud && $sql[$i] !== "\n") {
                $i++;
            }

            $actual .= "\n";
            continue;
        }

        // Comentario de bloque: se descarta por completo.
        if ($caracter === '/' && $siguiente === '*') {
            $fin = strpos($sql, '*/', $i + 2);
            $i = $fin === false ? $longitud : $fin + 2;
            $actual .= ' ';
            continue;
        }

        // Cadenas: se copian tal cual hasta el cierre.
        if ($caracter === "'" || $caracter === '"' || $caracter === '`') {
            $comilla = $caracter;
            $actual .= $caracter;
            $i++;

            while ($i < $longitud) {
                $actual .= $sql[$i];

                if ($sql[$i] === '\\' && $i + 1 < $longitud) {
                    $actual .= $sql[$i + 1];
                    $i += 2;
                    continue;
                }

                if ($sql[$i] === $comilla) {
                    $i++;
                    break;
                }

                $i++;
            }

            continue;
        }

        if ($caracter === ';') {
            $sentencia = trim($actual);

            if ($sentencia !== '') {
                $sentencias[] = $sentencia;
            }

            $actual = '';
            $i++;
            continue;
        }

        $actual .= $caracter;
        $i++;
    }

    $sentencia = trim($actual);

    if ($sentencia !== '') {
        $sentencias[] = $sentencia;
    }

    return $sentencias;
}

/**
 * Indica si una sentencia modifica destructivamente la base de datos.
 */
function esDestructiva(string $sentencia): bool
{
    return preg_match('/\b(drop\s+(table|database)|truncate\s+table|delete\s+from)\b/i', $sentencia) === 1;
}

$opciones = array_slice($argv, 1);
$mostrarEstado = in_array('--estado', $opciones, true);
$permitirDestructivo = in_array('--permitir-destructivo', $opciones, true);

if (!\App\Core\Database::isAvailable()) {
    abortar(
        'no se pudo conectar a la base de datos. Revise el archivo .env y que el servidor de '
        . 'base de datos esté disponible.'
    );
}

$pdo = \App\Core\Database::connection();
$prefijo = (string) \App\Core\Config::get('database.prefix', 'csduv3_');
$tablaMigraciones = $prefijo . 'migraciones';

$pdo->exec(sprintf(
    'CREATE TABLE IF NOT EXISTS `%s` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `nombre_archivo` VARCHAR(255) NOT NULL,
        `fecha_aplicacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_migraciones_nombre` (`nombre_archivo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    $tablaMigraciones
));

$directorio = $appPath . '/database/migrations';

if (!is_dir($directorio)) {
    abortar('no se encontró el directorio database/migrations.');
}

$archivos = glob($directorio . '/*.sql') ?: [];
sort($archivos);

$consulta = $pdo->prepare(sprintf('SELECT `id` FROM `%s` WHERE `nombre_archivo` = :archivo', $tablaMigraciones));
$registro = $pdo->prepare(sprintf('INSERT INTO `%s` (`nombre_archivo`) VALUES (:archivo)', $tablaMigraciones));

$aplicadas = 0;
$pendientes = 0;

informar();
informar('CSDUV3 · Migraciones');
informar('====================');
informar();

foreach ($archivos as $archivo) {
    $nombre = basename($archivo);
    $consulta->execute(['archivo' => $nombre]);
    $yaAplicada = $consulta->fetchColumn() !== false;

    if ($yaAplicada) {
        if ($mostrarEstado) {
            informar(sprintf('  [aplicada]  %s', $nombre));
        }

        continue;
    }

    $pendientes++;

    if ($mostrarEstado) {
        informar(sprintf('  [pendiente] %s', $nombre));
        continue;
    }

    $sentencias = dividirEnSentencias((string) file_get_contents($archivo));

    if ($sentencias === []) {
        informar(sprintf('  -> %s: el archivo no contiene sentencias. Se omite.', $nombre));
        continue;
    }

    $destructiva = false;

    foreach ($sentencias as $sentencia) {
        if (esDestructiva($sentencia)) {
            $destructiva = true;
            break;
        }
    }

    if ($destructiva && !$permitirDestructivo) {
        abortar(sprintf(
            'la migración %s contiene sentencias destructivas (DROP, TRUNCATE o DELETE). '
            . 'Revísela y, si el cambio es intencional, ejecútela con --permitir-destructivo.',
            $nombre
        ));
    }

    informar(sprintf('Aplicando %s ...', $nombre));

    foreach ($sentencias as $sentencia) {
        try {
            $pdo->exec($sentencia);
        } catch (PDOException $exception) {
            abortar(sprintf('error al aplicar %s: %s', $nombre, $exception->getMessage()));
        }
    }

    $registro->execute(['archivo' => $nombre]);
    informar(sprintf('  -> Éxito (%d sentencias).', count($sentencias)));
    $aplicadas++;
}

informar();

if ($mostrarEstado) {
    informar(sprintf('Archivos: %d · pendientes: %d', count($archivos), $pendientes));
    exit(0);
}

if ($aplicadas === 0) {
    informar('No hay migraciones pendientes. La base de datos está actualizada.');
    exit(0);
}

informar(sprintf('Se aplicaron %d migración(es).', $aplicadas));
