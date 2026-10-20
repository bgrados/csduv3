<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Disciplinas deportivas configuradas en el sistema.
 */
final class Deporte extends Model
{
    protected static string $table = 'deportes';

    /** @return array<int, array<string, mixed>> */
    public static function todos(): array
    {
        return self::fetchAll(
            sprintf('SELECT * FROM `%s` ORDER BY `activo` DESC, `nombre` ASC', self::table())
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function activos(): array
    {
        return self::fetchAll(
            sprintf('SELECT * FROM `%s` WHERE `activo` = 1 ORDER BY `nombre` ASC', self::table())
        );
    }

    /** @return array<string, mixed>|null */
    public static function activoPorId(int $id): ?array
    {
        return self::fetchOne(
            sprintf('SELECT * FROM `%s` WHERE `id` = :id AND `activo` = 1 LIMIT 1', self::table()),
            ['id' => $id]
        );
    }

    public static function contarActivos(): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `activo` = 1', self::table())
        );
    }
}
