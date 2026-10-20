<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Procesos electorales del club.
 *
 * El módulo es **administrativo**: registra el proceso, las listas, los
 * candidatos y los resultados que declara la organización. No es un sistema
 * de votación electrónica ni certifica identidades.
 */
final class ProcesoElectoral extends Model
{
    protected static string $table = 'procesos_electorales';

    public const ESTADOS = [
        'planificado' => 'Planificado',
        'convocado'   => 'Convocado',
        'en_proceso'  => 'En proceso',
        'finalizado'  => 'Finalizado',
        'anulado'     => 'Anulado',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function todos(): array
    {
        $sql = sprintf(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM `%s` l WHERE l.`proceso_id` = p.`id`) AS total_listas,
                    (SELECT COUNT(*) FROM `%s` c WHERE c.`proceso_id` = p.`id`) AS total_candidatos
             FROM `%s` p
             ORDER BY CASE p.`estado`
                          WHEN \'en_proceso\' THEN 0
                          WHEN \'convocado\' THEN 1
                          WHEN \'planificado\' THEN 2
                          ELSE 3
                      END ASC,
                      p.`fecha_eleccion` DESC',
            self::prefijada('listas_electorales'),
            self::prefijada('candidatos'),
            self::table()
        );

        return self::fetchAll($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $sql = sprintf(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM `%s` l WHERE l.`proceso_id` = p.`id`) AS total_listas,
                    (SELECT COUNT(*) FROM `%s` c WHERE c.`proceso_id` = p.`id`) AS total_candidatos
             FROM `%s` p
             WHERE p.`id` = :id
             LIMIT 1',
            self::prefijada('listas_electorales'),
            self::prefijada('candidatos'),
            self::table()
        );

        return self::fetchOne($sql, ['id' => $id]);
    }

    public static function crear(array $datos): int
    {
        return self::insertar([
            'nombre'             => (string) $datos['nombre'],
            'descripcion'        => self::vacioANulo($datos['descripcion'] ?? null),
            'fecha_convocatoria' => self::vacioANulo($datos['fecha_convocatoria'] ?? null),
            'fecha_eleccion'     => self::vacioANulo($datos['fecha_eleccion'] ?? null),
            'periodo_inicio'     => self::vacioANulo($datos['periodo_inicio'] ?? null),
            'periodo_fin'        => self::vacioANulo($datos['periodo_fin'] ?? null),
            'anios_mandato'      => (int) ($datos['anios_mandato'] ?? 4),
            'estado'             => (string) $datos['estado'],
            'reglamento'         => self::vacioANulo($datos['reglamento'] ?? null),
            'observaciones'      => self::vacioANulo($datos['observaciones'] ?? null),
        ]);
    }

    public static function actualizar(int $id, array $datos): void
    {
        self::run(
            sprintf(
                'UPDATE `%s`
                 SET `nombre` = :nombre,
                     `descripcion` = :descripcion,
                     `fecha_convocatoria` = :fecha_convocatoria,
                     `fecha_eleccion` = :fecha_eleccion,
                     `periodo_inicio` = :periodo_inicio,
                     `periodo_fin` = :periodo_fin,
                     `anios_mandato` = :anios_mandato,
                     `estado` = :estado,
                     `reglamento` = :reglamento,
                     `observaciones` = :observaciones
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'nombre'             => (string) $datos['nombre'],
                'descripcion'        => self::vacioANulo($datos['descripcion'] ?? null),
                'fecha_convocatoria' => self::vacioANulo($datos['fecha_convocatoria'] ?? null),
                'fecha_eleccion'     => self::vacioANulo($datos['fecha_eleccion'] ?? null),
                'periodo_inicio'     => self::vacioANulo($datos['periodo_inicio'] ?? null),
                'periodo_fin'        => self::vacioANulo($datos['periodo_fin'] ?? null),
                'anios_mandato'      => (int) ($datos['anios_mandato'] ?? 4),
                'estado'             => (string) $datos['estado'],
                'reglamento'         => self::vacioANulo($datos['reglamento'] ?? null),
                'observaciones'      => self::vacioANulo($datos['observaciones'] ?? null),
                'id'                 => $id,
            ]
        );
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        self::run(
            sprintf('UPDATE `%s` SET `estado` = :estado WHERE `id` = :id', self::table()),
            ['estado' => $estado, 'id' => $id]
        );
    }

    public static function contar(): int
    {
        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::table()));
    }

    protected static function prefijada(string $tabla): string
    {
        $prefijo = (string) \App\Core\Config::get('database.prefix', 'csduv3_');

        return $prefijo . $tabla;
    }

    private static function vacioANulo(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
