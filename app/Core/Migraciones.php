<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Migraciones de la base de datos.
 *
 * Los archivos de `database/migrations/` se aplican en orden y se registran
 * en la tabla `csduv3_migraciones`. La misma lógica la usan el despliegue por
 * consola (`tools/migrar.php`) y el botón del panel, para que el club pueda
 * actualizar la base sin depender del Terminal del hosting.
 */
final class Migraciones
{
    /** Tablas que el sistema necesita para funcionar. */
    private const TABLAS_CRITICAS = [
        'usuarios', 'roles', 'permisos', 'socios', 'categorias_socio',
        'instalaciones', 'tarifas', 'tipos_evento', 'reservas', 'solicitudes_alquiler',
        'reserva_pagos', 'categorias_movimiento', 'medios_pago', 'periodos', 'movimientos',
        'movimientos_auditoria', 'directivas', 'directiva_cargos', 'directiva_miembros',
        'procesos_electorales', 'listas_electorales', 'candidatos', 'resultados_electorales',
        'deportes', 'deporte_puntos', 'torneos', 'torneo_equipos', 'jornadas', 'encuentros',
        'documentos', 'actividades', 'fotos', 'negocios', 'auditoria',
    ];

    /** Columnas agregadas por las migraciones recientes. */
    private const COLUMNAS_RECIENTES = [
        'fotos' => ['video_url', 'video_tipo'],
    ];

    private static ?array $aplicadas = null;

    /**
     * Ruta de la carpeta con los archivos de migración.
     */
    public static function directorio(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migraciones';
    }

    /**
     * Nombres de los archivos de migración ya aplicados.
     *
     * @return array<int, string>
     */
    public static function aplicadas(): array
    {
        if (self::$aplicadas !== null) {
            return self::$aplicadas;
        }

        self::$aplicadas = [];

        try {
            $pdo = Database::connection();
            $tabla = self::tablaMigraciones();

            $existe = (int) $pdo->query(sprintf(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '%s'",
                $tabla
            ))->fetchColumn();

            if ($existe === 0) {
                return self::$aplicadas;
            }

            $consulta = $pdo->prepare(sprintf(
                'SELECT `nombre_archivo` FROM `%s` ORDER BY `nombre_archivo`',
                $tabla
            ));
            $consulta->execute();

            foreach ($consulta->fetchAll(PDO::FETCH_COLUMN) as $nombre) {
                self::$aplicadas[] = (string) $nombre;
            }
        } catch (\Throwable $exception) {
            Logger::warning('No se pudo leer el registro de migraciones', [
                'error' => $exception->getMessage(),
            ]);
        }

        return self::$aplicadas;
    }

    /**
     * Nombres de los archivos de migración pendientes, en orden.
     *
     * @return array<int, string>
     */
    public static function pendientes(): array
    {
        $aplicadas = self::aplicadas();
        $pendientes = [];

        foreach (self::archivos() as $archivo) {
            $nombre = basename($archivo);

            if (!in_array($nombre, $aplicadas, true)) {
                $pendientes[] = $nombre;
            }
        }

        return $pendientes;
    }

    /**
     * ¿Hay migraciones pendientes?
     */
    public static function hayPendientes(): bool
    {
        return self::pendientes() !== [];
    }

    /**
     * Aplica las migraciones pendientes.
     *
     * @return array{aplicadas: array<int, string>, agregadas: array<int, string>, errores: array<int, string>}
     */
    public static function aplicar(bool $permitirDestructivo = false): array
    {
        $resultado = ['aplicadas' => [], 'agregadas' => [], 'errores' => []];

        if (!Database::isAvailable()) {
            $resultado['errores'][] = 'No se pudo conectar a la base de datos.';

            return $resultado;
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            $resultado['errores'][] = $exception->getMessage();

            return $resultado;
        }

        $tabla = self::tablaMigraciones();

        try {
            $pdo->exec(sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `nombre_archivo` VARCHAR(255) NOT NULL,
                    `fecha_aplicacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_migraciones_nombre` (`nombre_archivo`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                $tabla
            ));
        } catch (\Throwable $exception) {
            $resultado['errores'][] = 'No se pudo preparar el registro de migraciones: ' . $exception->getMessage();

            return $resultado;
        }

        $consulta = $pdo->prepare(sprintf(
            'SELECT `id` FROM `%s` WHERE `nombre_archivo` = :archivo',
            $tabla
        ));
        $registro = $pdo->prepare(sprintf(
            'INSERT INTO `%s` (`nombre_archivo`) VALUES (:archivo)',
            $tabla
        ));

        foreach (self::archivos() as $archivo) {
            $nombre = basename($archivo);

            $consulta->execute(['archivo' => $nombre]);

            if ($consulta->fetchColumn() !== false) {
                continue;
            }

            $sentencias = self::dividirEnSentencias((string) file_get_contents($archivo));

            if ($sentencias === []) {
                continue;
            }

            $destructiva = false;

            foreach ($sentencias as $sentencia) {
                if (self::esDestructiva($sentencia)) {
                    $destructiva = true;
                    break;
                }
            }

            if ($destructiva && !$permitirDestructivo) {
                $resultado['errores'][] = sprintf(
                    'La migración %s contiene sentencias destructivas (DROP, TRUNCATE o DELETE). '
                    . 'Revísela antes de aplicarla.',
                    $nombre
                );

                continue;
            }

            $fallo = null;

            foreach ($sentencias as $sentencia) {
                try {
                    $pdo->exec($sentencia);
                } catch (\Throwable $exception) {
                    $fallo = $exception->getMessage();
                    break;
                }
            }

            if ($fallo !== null) {
                $resultado['errores'][] = sprintf('Error al aplicar %s: %s', $nombre, $fallo);
                continue;
            }

            $registro->execute(['archivo' => $nombre]);
            $resultado['aplicadas'][] = $nombre;
        }

        self::$aplicadas = null;

        // Al final se comprueban las columnas recientes: si alguna quedara
        // fuera, se agrega (por si una migración se registró sin aplicarse).
        $reparacion = self::repararColumnas();
        $resultado['agregadas'] = $reparacion['agregadas'];
        $resultado['errores'] = array_merge($resultado['errores'], $reparacion['errores']);

        return $resultado;
    }

    /**
     * Tablas del sistema que todavía no existen.
     *
     * @return array<int, string>
     */
    public static function tablasFaltantes(): array
    {
        $faltantes = [];

        try {
            $pdo = Database::connection();
            $prefijo = self::prefijo();

            foreach (self::TABLAS_CRITICAS as $tabla) {
                $existe = (int) $pdo->query(sprintf(
                    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '%s%s'",
                    $prefijo,
                    $tabla
                ))->fetchColumn();

                if ($existe === 0) {
                    $faltantes[] = $prefijo . $tabla;
                }
            }
        } catch (\Throwable) {
            return $faltantes;
        }

        return $faltantes;
    }

    /**
     * Columnas recientes que todavía no existen.
     *
     * @return array<int, string>
     */
    public static function columnasFaltantes(): array
    {
        $faltantes = [];

        try {
            $pdo = Database::connection();
            $prefijo = self::prefijo();

            foreach (self::COLUMNAS_RECIENTES as $tabla => $columnas) {
                foreach ($columnas as $columna) {
                    $existe = (int) $pdo->query(sprintf(
                        "SELECT COUNT(*) FROM information_schema.columns
                         WHERE table_schema = DATABASE() AND table_name = '%s%s' AND column_name = '%s'",
                        $prefijo,
                        $tabla,
                        $columna
                    ))->fetchColumn();

                    if ($existe === 0) {
                        $faltantes[] = $prefijo . $tabla . '.' . $columna;
                    }
                }
            }
        } catch (\Throwable) {
            return $faltantes;
        }

        return $faltantes;
    }

    /**
     * Se asegura de que existan las columnas recientes.
     *
     * Alguna vez una migración queda registrada como aplicada aunque las
     * columnas no llegaran a crearse (por ejemplo, si la sentencia se
     * ignoró). Este método las vuelve a agregar: usa el mismo SQL de las
     * migraciones y es idempotente.
     *
     * @return array{agregadas: array<int, string>, errores: array<int, string>}
     */
    public static function repararColumnas(): array
    {
        $resultado = ['agregadas' => [], 'errores' => []];

        $faltantes = self::columnasFaltantes();

        if ($faltantes === []) {
            return $resultado;
        }

        if (!Database::isAvailable()) {
            $resultado['errores'][] = 'No se pudo conectar a la base de datos.';

            return $resultado;
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            $resultado['errores'][] = $exception->getMessage();

            return $resultado;
        }

        $prefijo = self::prefijo();

        foreach (self::COLUMNAS_RECIENTES as $tabla => $columnas) {
            foreach ($columnas as $columna) {
                if (!in_array($prefijo . $tabla . '.' . $columna, $faltantes, true)) {
                    continue;
                }

                $sql = sprintf(
                    'ALTER TABLE `%s%s` ADD COLUMN IF NOT EXISTS `%s` %s',
                    $prefijo,
                    $tabla,
                    $columna,
                    self::definicionColumna($columna)
                );

                try {
                    $pdo->exec($sql);
                    $resultado['agregadas'][] = $prefijo . $tabla . '.' . $columna;
                } catch (\Throwable $exception) {
                    $resultado['errores'][] = sprintf(
                        'No se pudo agregar %s%s.%s: %s',
                        $prefijo,
                        $tabla,
                        $columna,
                        $exception->getMessage()
                    );
                }
            }
        }

        return $resultado;
    }

    /**
     * Definición SQL de cada columna reciente.
     */
    private static function definicionColumna(string $columna): string
    {
        return match ($columna) {
            'video_url' => "VARCHAR(255) NULL COMMENT 'Enlace al video (YouTube o Facebook)'",
            'video_tipo' => "VARCHAR(20) NULL COMMENT 'youtube o facebook'",
            default     => 'VARCHAR(255) NULL',
        };
    }

    /**
     * Divide un archivo SQL en sentencias ejecutables de forma individual.
     *
     * Respeta las comillas simples, dobles y las de identificadores, así como
     * los comentarios de línea (`--`) y de bloque.
     *
     * @return array<int, string>
     */
    public static function dividirEnSentencias(string $sql): array
    {
        $sentencias = [];
        $actual = '';
        $longitud = strlen($sql);
        $i = 0;

        while ($i < $longitud) {
            $caracter = $sql[$i];
            $siguiente = $i + 1 < $longitud ? $sql[$i + 1] : '';

            if ($caracter === '-' && $siguiente === '-') {
                while ($i < $longitud && $sql[$i] !== "\n") {
                    $i++;
                }

                $actual .= "\n";
                continue;
            }

            if ($caracter === '/' && $siguiente === '*') {
                $fin = strpos($sql, '*/', $i + 2);
                $i = $fin === false ? $longitud : $fin + 2;
                $actual .= ' ';
                continue;
            }

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
    public static function esDestructiva(string $sentencia): bool
    {
        return preg_match('/\b(drop\s+(table|database)|truncate\s+table|delete\s+from)\b/i', $sentencia) === 1;
    }

    /**
     * @return array<int, string>
     */
    private static function archivos(): array
    {
        $directorio = self::directorio();

        if (!is_dir($directorio)) {
            return [];
        }

        $archivos = glob($directorio . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($archivos);

        return $archivos;
    }

    private static function tablaMigraciones(): string
    {
        return self::prefijo() . 'migraciones';
    }

    private static function prefijo(): string
    {
        return (string) Config::get('database.prefix', 'csduv3_');
    }
}
