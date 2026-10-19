<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Catálogo de permisos, agrupados por módulo.
 */
final class Permiso extends Model
{
    protected static string $table = 'permisos';

    /** @return array<int, array<string, mixed>> */
    public static function todos(): array
    {
        return self::fetchAll(
            sprintf('SELECT * FROM `%s` ORDER BY `modulo` ASC, `clave` ASC', self::table())
        );
    }

    /**
     * Agrupa los permisos por módulo para mostrarlos en la matriz de roles.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function agrupados(): array
    {
        $grupos = [];

        foreach (self::todos() as $permiso) {
            $grupos[(string) $permiso['modulo']][] = $permiso;
        }

        return $grupos;
    }

    public static function contar(): int
    {
        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::table()));
    }
}
