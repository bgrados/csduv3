<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Instalaciones y ambientes que el club pone a disposición.
 */
final class Instalacion extends Model
{
    protected static string $table = 'instalaciones';

    /** Tipos de ambiente admitidos por el esquema y el formulario. */
    public const TIPOS = [
        'ambiente' => 'Ambiente',
        'losa'     => 'Losa deportiva',
        'cancha'   => 'Cancha',
        'salon'    => 'Salón',
        'otro'     => 'Otro',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function activas(): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` WHERE `activo` = 1 ORDER BY `tipo` ASC, `nombre` ASC',
                self::table()
            )
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function todas(): array
    {
        return self::fetchAll(
            sprintf('SELECT * FROM `%s` ORDER BY `activo` DESC, `tipo` ASC, `nombre` ASC', self::table())
        );
    }

    /** @return array<string, mixed>|null */
    public static function activaPorId(int $id): ?array
    {
        return self::fetchOne(
            sprintf('SELECT * FROM `%s` WHERE `id` = :id AND `activo` = 1 LIMIT 1', self::table()),
            ['id' => $id]
        );
    }

    /**
     * Instalaciones activas con sus tarifas vigentes.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function activasConTarifas(): array
    {
        $instalaciones = self::activas();

        foreach ($instalaciones as $indice => $instalacion) {
            $instalaciones[$indice]['tarifas'] = Tarifa::vigentesPorInstalacion((int) $instalacion['id']);
        }

        return $instalaciones;
    }

    /**
     * Listado administrativo con el número de tarifas registradas y vigentes.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarConResumen(?string $busqueda = null, string $estado = ''): array
    {
        $condiciones = [];
        $params = [];

        $busqueda = $busqueda === null ? '' : trim($busqueda);

        if ($busqueda !== '') {
            $condiciones[] = '(`nombre` LIKE :q1 OR `tipo` LIKE :q2 OR `ubicacion` LIKE :q3 OR `descripcion` LIKE :q4)';
            $like = '%' . $busqueda . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        if ($estado === 'activo' || $estado === 'inactivo') {
            $condiciones[] = '`activo` = :activo';
            $params['activo'] = $estado === 'activo' ? 1 : 0;
        }

        $sql = sprintf(
            'SELECT i.*,
                    (SELECT COUNT(*) FROM `%s` t WHERE t.`instalacion_id` = i.`id`) AS total_tarifas,
                    (SELECT COUNT(*) FROM `%s` t
                     WHERE t.`instalacion_id` = i.`id`
                       AND t.`activo` = 1
                       AND (t.`vigente_desde` IS NULL OR t.`vigente_desde` <= CURDATE())
                       AND (t.`vigente_hasta` IS NULL OR t.`vigente_hasta` >= CURDATE())) AS tarifas_activas
             FROM `%s` i
             %s
             ORDER BY i.`activo` DESC, i.`tipo` ASC, i.`nombre` ASC',
            Tarifa::table(),
            Tarifa::table(),
            self::table(),
            $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones)
        );

        return self::fetchAll($sql, $params);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'nombre'           => (string) $datos['nombre'],
            'tipo'             => (string) $datos['tipo'],
            'descripcion'      => self::vacioANulo($datos['descripcion'] ?? null),
            'ubicacion'        => self::vacioANulo($datos['ubicacion'] ?? null),
            'aforo'            => self::enteroANulo($datos['aforo'] ?? null),
            'requiere_adelanto' => (int) ($datos['requiere_adelanto'] ?? 0) === 1 ? 1 : 0,
            'activo'           => (int) ($datos['activo'] ?? 1) === 1 ? 1 : 0,
            'archivo'          => self::vacioANulo($datos['archivo'] ?? null),
            'nombre_original'  => self::vacioANulo($datos['nombre_original'] ?? null),
            'tipo_mime'        => self::vacioANulo($datos['tipo_mime'] ?? null),
            'tamano_bytes'     => self::enteroANulo($datos['tamano_bytes'] ?? null),
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
                 SET `nombre` = :nombre,
                     `tipo` = :tipo,
                     `descripcion` = :descripcion,
                     `ubicacion` = :ubicacion,
                     `aforo` = :aforo,
                     `requiere_adelanto` = :requiere_adelanto,
                     `activo` = :activo,
                     `archivo` = :archivo,
                     `nombre_original` = :nombre_original,
                     `tipo_mime` = :tipo_mime,
                     `tamano_bytes` = :tamano_bytes
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'nombre'            => (string) $datos['nombre'],
                'tipo'              => (string) $datos['tipo'],
                'descripcion'       => self::vacioANulo($datos['descripcion'] ?? null),
                'ubicacion'         => self::vacioANulo($datos['ubicacion'] ?? null),
                'aforo'             => self::enteroANulo($datos['aforo'] ?? null),
                'requiere_adelanto' => (int) ($datos['requiere_adelanto'] ?? 0) === 1 ? 1 : 0,
                'activo'            => (int) ($datos['activo'] ?? 1) === 1 ? 1 : 0,
                'archivo'           => self::vacioANulo($datos['archivo'] ?? null),
                'nombre_original'   => self::vacioANulo($datos['nombre_original'] ?? null),
                'tipo_mime'         => self::vacioANulo($datos['tipo_mime'] ?? null),
                'tamano_bytes'      => self::enteroANulo($datos['tamano_bytes'] ?? null),
                'id'                => $id,
            ]
        );
    }

    public static function cambiarActivo(int $id, bool $activo): void
    {
        self::run(
            sprintf('UPDATE `%s` SET `activo` = :activo WHERE `id` = :id', self::table()),
            ['activo' => $activo ? 1 : 0, 'id' => $id]
        );
    }

    public static function nombreExiste(string $nombre, ?int $excluirId = null): bool
    {
        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `nombre` = :nombre', self::table());
        $params = ['nombre' => $nombre];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND `id` <> :id';
            $params['id'] = $excluirId;
        }

        return (int) self::scalar($sql, $params) > 0;
    }

    public static function contar(): int
    {
        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::table()));
    }

    public static function contarActivas(): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `activo` = 1', self::table())
        );
    }

    /**
     * Ambientes que el club publica en el portal: activos y con tarifa vigente.
     */
    public static function contarPublicadas(): int
    {
        return (int) self::scalar(
            sprintf(
                'SELECT COUNT(*) FROM `%s` i
                 WHERE i.`activo` = 1
                   AND EXISTS (
                       SELECT 1 FROM `%s` t
                       WHERE t.`instalacion_id` = i.`id`
                         AND t.`activo` = 1
                         AND (t.`vigente_desde` IS NULL OR t.`vigente_desde` <= CURDATE())
                         AND (t.`vigente_hasta` IS NULL OR t.`vigente_hasta` >= CURDATE())
                   )',
                self::table(),
                Tarifa::table()
            )
        );
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
