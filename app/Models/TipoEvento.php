<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Tipos de uso autorizados para las instalaciones.
 */
final class TipoEvento extends Model
{
    protected static string $table = 'tipos_evento';

    /** @return array<int, array<string, mixed>> */
    public static function activos(): array
    {
        return self::fetchAll(
            sprintf('SELECT * FROM `%s` WHERE `activo` = 1 ORDER BY `nombre` ASC', self::table())
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function todos(): array
    {
        return self::fetchAll(
            sprintf('SELECT * FROM `%s` ORDER BY `activo` DESC, `nombre` ASC', self::table())
        );
    }
}
