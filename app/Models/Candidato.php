<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Candidatos inscritos en un proceso electoral.
 */
final class Candidato extends Model
{
    protected static string $table = 'candidatos';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listarPorProceso(int $procesoId): array
    {
        $sql = sprintf(
            'SELECT c.*,
                    l.`nombre` AS lista_nombre,
                    g.`nombre` AS cargo_nombre,
                    s.`codigo` AS socio_codigo
             FROM `%s` c
             LEFT JOIN `%s` l ON l.`id` = c.`lista_id`
             LEFT JOIN `%s` g ON g.`id` = c.`cargo_id`
             LEFT JOIN `%s` s ON s.`id` = c.`socio_id`
             WHERE c.`proceso_id` = :proceso_id
             ORDER BY l.`orden` ASC, c.`orden` ASC, c.`id` ASC',
            self::table(),
            self::prefijada('listas_electorales'),
            self::prefijada('directiva_cargos'),
            self::prefijada('socios')
        );

        return self::fetchAll($sql, ['proceso_id' => $procesoId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return self::fetchOne(
            sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', self::table()),
            ['id' => $id]
        );
    }

    public static function crear(array $datos): int
    {
        return self::insertar([
            'proceso_id'      => (int) $datos['proceso_id'],
            'lista_id'        => self::enteroANulo($datos['lista_id'] ?? null),
            'socio_id'        => self::enteroANulo($datos['socio_id'] ?? null),
            'nombres'         => (string) $datos['nombres'],
            'apellidos'       => (string) $datos['apellidos'],
            'documento'       => self::vacioANulo($datos['documento'] ?? null),
            'cargo_id'        => self::enteroANulo($datos['cargo_id'] ?? null),
            'orden'           => (int) ($datos['orden'] ?? 1),
            'es_cabeza_lista' => (int) ($datos['es_cabeza_lista'] ?? 0) === 1 ? 1 : 0,
            'observaciones'   => self::vacioANulo($datos['observaciones'] ?? null),
        ]);
    }

    public static function actualizar(int $id, array $datos): void
    {
        self::run(
            sprintf(
                'UPDATE `%s`
                 SET `lista_id` = :lista_id,
                     `socio_id` = :socio_id,
                     `nombres` = :nombres,
                     `apellidos` = :apellidos,
                     `documento` = :documento,
                     `cargo_id` = :cargo_id,
                     `orden` = :orden,
                     `es_cabeza_lista` = :es_cabeza_lista,
                     `observaciones` = :observaciones
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'lista_id'        => self::enteroANulo($datos['lista_id'] ?? null),
                'socio_id'        => self::enteroANulo($datos['socio_id'] ?? null),
                'nombres'         => (string) $datos['nombres'],
                'apellidos'       => (string) $datos['apellidos'],
                'documento'       => self::vacioANulo($datos['documento'] ?? null),
                'cargo_id'        => self::enteroANulo($datos['cargo_id'] ?? null),
                'orden'           => (int) ($datos['orden'] ?? 1),
                'es_cabeza_lista' => (int) ($datos['es_cabeza_lista'] ?? 0) === 1 ? 1 : 0,
                'observaciones'   => self::vacioANulo($datos['observaciones'] ?? null),
                'id'              => $id,
            ]
        );
    }

    public static function eliminar(int $id): void
    {
        self::run(
            sprintf('DELETE FROM `%s` WHERE `id` = :id', self::table()),
            ['id' => $id]
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
