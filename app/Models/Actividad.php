<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Calendario institucional: reuniones, elecciones, actividades y eventos.
 */
final class Actividad extends Model
{
    protected static string $table = 'actividades';

    public const TIPOS = [
        'institucional' => 'Institucional',
        'directiva'      => 'Junta directiva',
        'eleccion'       => 'Elecciones',
        'deportiva'      => 'Deportiva',
        'social'         => 'Social',
        'mantenimiento'  => 'Mantenimiento',
    ];

    public const ESTADOS = [
        'programada'   => 'Programada',
        'realizada'    => 'Realizada',
        'cancelada'    => 'Cancelada',
        'reprogramada' => 'Reprogramada',
    ];

    /**
     * Actividades de un rango de fechas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarPorRango(string $inicio, string $fin, string $tipo = ''): array
    {
        $condiciones = [
            '(`fecha_inicio` <= :fin AND (`fecha_fin` IS NULL OR `fecha_fin` >= :inicio))',
        ];
        $params = ['inicio' => $inicio, 'fin' => $fin];

        if (isset(self::TIPOS[$tipo])) {
            $condiciones[] = '`tipo` = :tipo';
            $params['tipo'] = $tipo;
        }

        $sql = sprintf(
            'SELECT a.*,
                    i.`nombre` AS instalacion_nombre
             FROM `%s` a
             LEFT JOIN `%s` i ON i.`id` = a.`instalacion_id`
             WHERE %s
             ORDER BY a.`fecha_inicio` ASC, a.`id` ASC',
            self::table(),
            self::prefijada('instalaciones'),
            implode(' AND ', $condiciones)
        );

        return self::fetchAll($sql, $params);
    }

    /**
     * Próximas actividades programadas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarProximas(int $limite = 5): array
    {
        $limite = max(1, min(20, $limite));

        $sql = sprintf(
            'SELECT a.*, i.`nombre` AS instalacion_nombre
             FROM `%s` a
             LEFT JOIN `%s` i ON i.`id` = a.`instalacion_id`
             WHERE a.`estado` = \'programada\'
               AND a.`fecha_inicio` >= CURDATE()
             ORDER BY a.`fecha_inicio` ASC
             LIMIT %d',
            self::table(),
            self::prefijada('instalaciones'),
            $limite
        );

        return self::fetchAll($sql);
    }

    /**
     * Próximas actividades programadas y visibles para el portal público.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function proximasPublicas(int $limite = 5): array
    {
        $limite = max(1, min(20, $limite));

        $sql = sprintf(
            'SELECT a.*, i.`nombre` AS instalacion_nombre
             FROM `%s` a
             LEFT JOIN `%s` i ON i.`id` = a.`instalacion_id`
             WHERE a.`visible` = 1
               AND a.`estado` = \'programada\'
               AND a.`fecha_inicio` >= NOW()
             ORDER BY a.`fecha_inicio` ASC
             LIMIT %d',
            self::table(),
            self::prefijada('instalaciones'),
            $limite
        );

        return self::fetchAll($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $sql = sprintf(
            'SELECT a.*, i.`nombre` AS instalacion_nombre
             FROM `%s` a
             LEFT JOIN `%s` i ON i.`id` = a.`instalacion_id`
             WHERE a.`id` = :id LIMIT 1',
            self::table(),
            self::prefijada('instalaciones')
        );

        return self::fetchOne($sql, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'titulo'         => (string) $datos['titulo'],
            'tipo'           => (string) $datos['tipo'],
            'descripcion'    => self::vacioANulo($datos['descripcion'] ?? null),
            'fecha_inicio'   => (string) $datos['fecha_inicio'],
            'fecha_fin'      => self::vacioANulo($datos['fecha_fin'] ?? null),
            'todo_el_dia'    => (int) ($datos['todo_el_dia'] ?? 0) === 1 ? 1 : 0,
            'lugar'          => self::vacioANulo($datos['lugar'] ?? null),
            'instalacion_id' => self::enteroANulo($datos['instalacion_id'] ?? null),
            'estado'         => (string) $datos['estado'],
            'visible'        => (int) ($datos['visible'] ?? 1) === 1 ? 1 : 0,
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
                 SET `titulo` = :titulo,
                     `tipo` = :tipo,
                     `descripcion` = :descripcion,
                     `fecha_inicio` = :fecha_inicio,
                     `fecha_fin` = :fecha_fin,
                     `todo_el_dia` = :todo_el_dia,
                     `lugar` = :lugar,
                     `instalacion_id` = :instalacion_id,
                     `estado` = :estado,
                     `visible` = :visible
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'titulo'         => (string) $datos['titulo'],
                'tipo'           => (string) $datos['tipo'],
                'descripcion'    => self::vacioANulo($datos['descripcion'] ?? null),
                'fecha_inicio'   => (string) $datos['fecha_inicio'],
                'fecha_fin'      => self::vacioANulo($datos['fecha_fin'] ?? null),
                'todo_el_dia'    => (int) ($datos['todo_el_dia'] ?? 0) === 1 ? 1 : 0,
                'lugar'          => self::vacioANulo($datos['lugar'] ?? null),
                'instalacion_id' => self::enteroANulo($datos['instalacion_id'] ?? null),
                'estado'         => (string) $datos['estado'],
                'visible'        => (int) ($datos['visible'] ?? 1) === 1 ? 1 : 0,
                'id'             => $id,
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

    private static function enteroANulo(mixed $valor): ?int
    {
        $entero = (int) ($valor ?? 0);

        return $entero > 0 ? $entero : null;
    }
}
