<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Parámetros institucionales guardados en la base de datos.
 *
 * Son los valores que el club puede cambiar desde el panel: identidad,
 * contacto, marca (logo, favicon y portada) y parámetros de alquileres.
 */
final class Configuracion extends Model
{
    protected static string $table = 'configuracion';

    /** Caché de la sesión para no repetir la consulta en cada página. */
    private static ?array $cache = null;

    /**
     * Devuelve todos los parámetros como mapa clave => valor.
     *
     * @return array<string, string>
     */
    public static function mapa(): array
    {
        $filas = self::fetchAll(sprintf('SELECT `clave`, `valor` FROM `%s`', self::table()));
        $mapa = [];

        foreach ($filas as $fila) {
            $mapa[(string) $fila['clave']] = (string) ($fila['valor'] ?? '');
        }

        return $mapa;
    }

    /**
     * Mapa con caché: se usa en las plantillas, que piden varios valores.
     *
     * @return array<string, string>
     */
    public static function mapaCacheado(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            self::$cache = self::mapa();
        } catch (\Throwable) {
            self::$cache = [];
        }

        return self::$cache;
    }

    public static function limpiarCache(): void
    {
        self::$cache = null;
    }

    public static function valor(string $clave, string $porDefecto = ''): string
    {
        $valor = self::scalar(
            sprintf('SELECT `valor` FROM `%s` WHERE `clave` = :clave LIMIT 1', self::table()),
            ['clave' => $clave]
        );

        return $valor === false || $valor === null ? $porDefecto : (string) $valor;
    }

    /**
     * Valor de un parámetro desde la caché (no consulta la base de datos).
     */
    public static function cached(string $clave, string $porDefecto = ''): string
    {
        $mapa = self::mapaCacheado();
        $valor = $mapa[$clave] ?? '';

        return $valor === '' ? $porDefecto : $valor;
    }

    /**
     * Guarda un parámetro (crea la fila si no existe).
     */
    public static function guardar(string $clave, string $valor, string $tipo = 'texto', string $grupo = 'general'): void
    {
        self::run(
            sprintf(
                'INSERT INTO `%s` (`clave`, `valor`, `tipo`, `grupo`)
                 VALUES (:clave, :valor, :tipo, :grupo)
                 ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`), `tipo` = VALUES(`tipo`)',
                self::table()
            ),
            [
                'clave'  => $clave,
                'valor'  => $valor,
                'tipo'   => $tipo,
                'grupo'  => $grupo,
            ]
        );

        self::limpiarCache();
    }

    /**
     * Guarda varios parámetros de una vez.
     *
     * @param array<string, string> $valores
     */
    public static function guardarVarios(array $valores, string $grupo = 'general'): void
    {
        foreach ($valores as $clave => $valor) {
            self::guardar((string) $clave, (string) $valor, 'texto', $grupo);
        }
    }
}
