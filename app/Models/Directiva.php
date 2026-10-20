<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Periodos de gestión de la junta directiva.
 *
 * Se conserva el histórico: un periodo concluido no se borra, solo se cambia
 * de estado.
 */
final class Directiva extends Model
{
    protected static string $table = 'directivas';

    public const ESTADOS = [
        'vigente'   => 'Vigente',
        'concluida' => 'Concluida',
        'anulada'   => 'Anulada',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function todas(): array
    {
        $sql = sprintf(
            'SELECT d.*,
                    (SELECT COUNT(*) FROM `%s` m WHERE m.`directiva_id` = d.`id`) AS total_miembros,
                    (SELECT COUNT(*) FROM `%s` m
                     WHERE m.`directiva_id` = d.`id` AND m.`estado` = \'vigente\') AS miembros_vigentes
             FROM `%s` d
             ORDER BY CASE d.`estado` WHEN \'vigente\' THEN 0 ELSE 1 END ASC,
                      d.`fecha_inicio` DESC',
            self::prefijada('directiva_miembros'),
            self::prefijada('directiva_miembros'),
            self::table()
        );

        return self::fetchAll($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function vigente(): ?array
    {
        return self::fetchOne(
            sprintf(
                'SELECT * FROM `%s` WHERE `estado` = \'vigente\' ORDER BY `fecha_inicio` DESC LIMIT 1',
                self::table()
            )
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $sql = sprintf(
            'SELECT d.*,
                    (SELECT COUNT(*) FROM `%s` m WHERE m.`directiva_id` = d.`id`) AS total_miembros,
                    (SELECT COUNT(*) FROM `%s` m
                     WHERE m.`directiva_id` = d.`id` AND m.`estado` = \'vigente\') AS miembros_vigentes
             FROM `%s` d
             WHERE d.`id` = :id
             LIMIT 1',
            self::prefijada('directiva_miembros'),
            self::prefijada('directiva_miembros'),
            self::table()
        );

        return self::fetchOne($sql, ['id' => $id]);
    }

    public static function crear(array $datos): int
    {
        return self::insertar([
            'nombre'        => (string) $datos['nombre'],
            'fecha_inicio'  => (string) $datos['fecha_inicio'],
            'fecha_fin'     => self::vacioANulo($datos['fecha_fin'] ?? null),
            'estado'        => (string) $datos['estado'],
            'observaciones' => self::vacioANulo($datos['observaciones'] ?? null),
        ]);
    }

    public static function actualizar(int $id, array $datos): void
    {
        self::run(
            sprintf(
                'UPDATE `%s`
                 SET `nombre` = :nombre,
                     `fecha_inicio` = :fecha_inicio,
                     `fecha_fin` = :fecha_fin,
                     `estado` = :estado,
                     `observaciones` = :observaciones
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'nombre'        => (string) $datos['nombre'],
                'fecha_inicio'  => (string) $datos['fecha_inicio'],
                'fecha_fin'     => self::vacioANulo($datos['fecha_fin'] ?? null),
                'estado'        => (string) $datos['estado'],
                'observaciones' => self::vacioANulo($datos['observaciones'] ?? null),
                'id'            => $id,
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
