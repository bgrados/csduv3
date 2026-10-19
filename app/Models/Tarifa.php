<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Tarifas configurables por instalación y tipo de evento.
 */
final class Tarifa extends Model
{
    protected static string $table = 'tarifas';

    /**
     * Tarifas activas y vigentes en la fecha actual.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function vigentesPorInstalacion(int $instalacionId): array
    {
        $sql = sprintf(
            'SELECT t.*, e.`nombre` AS tipo_evento_nombre
             FROM `%s` t
             LEFT JOIN `%s` e ON e.`id` = t.`tipo_evento_id`
             WHERE t.`instalacion_id` = :instalacion_id
               AND t.`activo` = 1
               AND (t.`vigente_desde` IS NULL OR t.`vigente_desde` <= CURDATE())
               AND (t.`vigente_hasta` IS NULL OR t.`vigente_hasta` >= CURDATE())
             ORDER BY t.`monto` ASC',
            self::table(),
            self::prefijada('tipos_evento')
        );

        return self::fetchAll($sql, ['instalacion_id' => $instalacionId]);
    }

    /**
     * Todas las tarifas de una instalación (para el panel administrativo).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function todasPorInstalacion(int $instalacionId): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` WHERE `instalacion_id` = :instalacion_id ORDER BY `monto` ASC',
                self::table()
            ),
            ['instalacion_id' => $instalacionId]
        );
    }

    protected static function prefijada(string $tabla): string
    {
        $prefijo = (string) \App\Core\Config::get('database.prefix', 'csduv3_');

        return $prefijo . $tabla;
    }
}
