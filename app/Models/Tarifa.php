<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;

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
                'SELECT t.*, e.`nombre` AS tipo_evento_nombre
                 FROM `%s` t
                 LEFT JOIN `%s` e ON e.`id` = t.`tipo_evento_id`
                 WHERE t.`instalacion_id` = :instalacion_id
                 ORDER BY t.`activo` DESC, t.`monto` ASC, t.`id` ASC',
                self::table(),
                self::prefijada('tipos_evento')
            ),
            ['instalacion_id' => $instalacionId]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findDeInstalacion(int $id, int $instalacionId): ?array
    {
        return self::fetchOne(
            sprintf(
                'SELECT * FROM `%s` WHERE `id` = :id AND `instalacion_id` = :instalacion_id LIMIT 1',
                self::table()
            ),
            ['id' => $id, 'instalacion_id' => $instalacionId]
        );
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'instalacion_id' => (int) $datos['instalacion_id'],
            'tipo_evento_id' => self::enteroANulo($datos['tipo_evento_id'] ?? null),
            'nombre'         => (string) $datos['nombre'],
            'monto'          => (string) $datos['monto'],
            'moneda'         => 'PEN',
            'hora_inicio'    => self::vacioANulo($datos['hora_inicio'] ?? null),
            'hora_fin'       => self::vacioANulo($datos['hora_fin'] ?? null),
            'vigente_desde'  => self::vacioANulo($datos['vigente_desde'] ?? null),
            'vigente_hasta'  => self::vacioANulo($datos['vigente_hasta'] ?? null),
            'activo'         => (int) ($datos['activo'] ?? 1) === 1 ? 1 : 0,
            'observaciones'  => self::vacioANulo($datos['observaciones'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        self::run(
            sprintf(
                'UPDATE `%s`
                 SET `tipo_evento_id` = :tipo_evento_id,
                     `nombre` = :nombre,
                     `monto` = :monto,
                     `hora_inicio` = :hora_inicio,
                     `hora_fin` = :hora_fin,
                     `vigente_desde` = :vigente_desde,
                     `vigente_hasta` = :vigente_hasta,
                     `activo` = :activo,
                     `observaciones` = :observaciones
                 WHERE `id` = :id AND `instalacion_id` = :instalacion_id',
                self::table()
            ),
            [
                'tipo_evento_id' => self::enteroANulo($datos['tipo_evento_id'] ?? null),
                'nombre'         => (string) $datos['nombre'],
                'monto'          => (string) $datos['monto'],
                'hora_inicio'    => self::vacioANulo($datos['hora_inicio'] ?? null),
                'hora_fin'       => self::vacioANulo($datos['hora_fin'] ?? null),
                'vigente_desde'  => self::vacioANulo($datos['vigente_desde'] ?? null),
                'vigente_hasta'  => self::vacioANulo($datos['vigente_hasta'] ?? null),
                'activo'         => (int) ($datos['activo'] ?? 1) === 1 ? 1 : 0,
                'observaciones'  => self::vacioANulo($datos['observaciones'] ?? null),
                'id'             => $id,
                'instalacion_id' => (int) $datos['instalacion_id'],
            ]
        );
    }

    public static function cambiarActivo(int $id, int $instalacionId, bool $activo): void
    {
        self::run(
            sprintf(
                'UPDATE `%s` SET `activo` = :activo WHERE `id` = :id AND `instalacion_id` = :instalacion_id',
                self::table()
            ),
            ['activo' => $activo ? 1 : 0, 'id' => $id, 'instalacion_id' => $instalacionId]
        );
    }

    public static function contarPorInstalacion(int $instalacionId): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `instalacion_id` = :instalacion_id', self::table()),
            ['instalacion_id' => $instalacionId]
        );
    }

    protected static function prefijada(string $tabla): string
    {
        $prefijo = (string) Config::get('database.prefix', 'csduv3_');

        return $prefijo . $tabla;
    }

    private static function vacioANulo(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    private static function enteroANulo(mixed $valor): ?int
    {
        $entero = (int) ($valor ?? 0);

        return $entero > 0 ? $entero : null;
    }
}
