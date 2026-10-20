<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Integrantes de cada junta directiva.
 *
 * El integrante puede ser un socio registrado (se enlaza con `socio_id`) o
 * una persona externa, en cuyo caso se registra su nombre completo.
 */
final class DirectivaMiembro extends Model
{
    protected static string $table = 'directiva_miembros';

    public const ESTADOS = [
        'vigente'  => 'Vigente',
        'cesado'   => 'Cesar en el cargo',
        'renuncia' => 'Renuncia',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listarPorDirectiva(int $directivaId): array
    {
        $sql = sprintf(
            'SELECT m.*,
                    c.`nombre` AS cargo_nombre,
                    s.`codigo` AS socio_codigo,
                    CONCAT(s.`nombres`, \' \', s.`apellidos`) AS socio_nombre
             FROM `%s` m
             LEFT JOIN `%s` c ON c.`id` = m.`cargo_id`
             LEFT JOIN `%s` s ON s.`id` = m.`socio_id`
             WHERE m.`directiva_id` = :directiva_id
             ORDER BY c.`orden` ASC, m.`nombre_completo` ASC, m.`id` ASC',
            self::table(),
            self::prefijada('directiva_cargos'),
            self::prefijada('socios')
        );

        return self::fetchAll($sql, ['directiva_id' => $directivaId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $sql = sprintf(
            'SELECT m.*,
                    c.`nombre` AS cargo_nombre,
                    s.`codigo` AS socio_codigo,
                    CONCAT(s.`nombres`, \' \', s.`apellidos`) AS socio_nombre
             FROM `%s` m
             LEFT JOIN `%s` c ON c.`id` = m.`cargo_id`
             LEFT JOIN `%s` s ON s.`id` = m.`socio_id`
             WHERE m.`id` = :id
             LIMIT 1',
            self::table(),
            self::prefijada('directiva_cargos'),
            self::prefijada('socios')
        );

        return self::fetchOne($sql, ['id' => $id]);
    }

    public static function crear(array $datos): int
    {
        return self::insertar([
            'directiva_id'    => (int) $datos['directiva_id'],
            'cargo_id'        => (int) $datos['cargo_id'],
            'socio_id'        => self::enteroANulo($datos['socio_id'] ?? null),
            'nombre_completo' => self::vacioANulo($datos['nombre_completo'] ?? null),
            'documento'       => self::vacioANulo($datos['documento'] ?? null),
            'telefono'        => self::vacioANulo($datos['telefono'] ?? null),
            'email'           => self::vacioANulo($datos['email'] ?? null),
            'fecha_inicio'    => self::vacioANulo($datos['fecha_inicio'] ?? null),
            'fecha_fin'       => self::vacioANulo($datos['fecha_fin'] ?? null),
            'estado'          => (string) $datos['estado'],
            'observaciones'   => self::vacioANulo($datos['observaciones'] ?? null),
        ]);
    }

    public static function actualizar(int $id, array $datos): void
    {
        self::run(
            sprintf(
                'UPDATE `%s`
                 SET `cargo_id` = :cargo_id,
                     `socio_id` = :socio_id,
                     `nombre_completo` = :nombre_completo,
                     `documento` = :documento,
                     `telefono` = :telefono,
                     `email` = :email,
                     `fecha_inicio` = :fecha_inicio,
                     `fecha_fin` = :fecha_fin,
                     `estado` = :estado,
                     `observaciones` = :observaciones
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'cargo_id'        => (int) $datos['cargo_id'],
                'socio_id'        => self::enteroANulo($datos['socio_id'] ?? null),
                'nombre_completo' => self::vacioANulo($datos['nombre_completo'] ?? null),
                'documento'       => self::vacioANulo($datos['documento'] ?? null),
                'telefono'        => self::vacioANulo($datos['telefono'] ?? null),
                'email'           => self::vacioANulo($datos['email'] ?? null),
                'fecha_inicio'    => self::vacioANulo($datos['fecha_inicio'] ?? null),
                'fecha_fin'       => self::vacioANulo($datos['fecha_fin'] ?? null),
                'estado'          => (string) $datos['estado'],
                'observaciones'   => self::vacioANulo($datos['observaciones'] ?? null),
                'id'              => $id,
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

    public static function contarPorDirectiva(int $directivaId): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `directiva_id` = :directiva_id', self::table()),
            ['directiva_id' => $directivaId]
        );
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
