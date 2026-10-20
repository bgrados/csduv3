<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Jornadas o fechas de un torneo.
 */
final class Jornada extends Model
{
    protected static string $table = 'jornadas';

    /** @return array<int, array<string, mixed>> */
    public static function listarPorTorneo(int $torneoId): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` WHERE `torneo_id` = :torneo_id ORDER BY `numero` ASC',
                self::table()
            ),
            ['torneo_id' => $torneoId]
        );
    }

    public static function crear(int $torneoId, int $numero, string $nombre, string $fecha): int
    {
        return self::insertar([
            'torneo_id'    => $torneoId,
            'numero'       => $numero,
            'nombre'       => $nombre,
            'fecha_inicio' => $fecha,
            'fecha_fin'    => $fecha,
        ]);
    }

    public static function eliminarPorTorneo(int $torneoId): void
    {
        self::run(
            sprintf('DELETE FROM `%s` WHERE `torneo_id` = :torneo_id', self::table()),
            ['torneo_id' => $torneoId]
        );
    }

    public static function contarPorTorneo(int $torneoId): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `torneo_id` = :torneo_id', self::table()),
            ['torneo_id' => $torneoId]
        );
    }
}
