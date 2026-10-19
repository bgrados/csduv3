<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Parámetros institucionales guardados en la base de datos.
 */
final class Configuracion extends Model
{
    protected static string $table = 'configuracion';

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

    public static function valor(string $clave, string $porDefecto = ''): string
    {
        $valor = self::scalar(
            sprintf('SELECT `valor` FROM `%s` WHERE `clave` = :clave LIMIT 1', self::table()),
            ['clave' => $clave]
        );

        return $valor === false || $valor === null ? $porDefecto : (string) $valor;
    }
}
