<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Consultas de solo lectura sobre el padrón de socios.
 *
 * El módulo completo se implementa en la Fase 3; aquí solo se exponen los
 * indicadores que necesita el panel principal.
 */
final class Socio extends Model
{
    protected static string $table = 'socios';

    public static function contarActivos(): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `estado` = \'activo\'', self::table())
        );
    }

    public static function contarPendientes(): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `estado` = \'pendiente\'', self::table())
        );
    }

    public static function contar(): int
    {
        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::table()));
    }
}
