<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Documentos institucionales.
 *
 * Los archivos se guardan en `storage/uploads` (nunca en `public`) y se sirven
 * mediante el controlador, que comprueba el acceso antes de entregarlos.
 */
final class Documento extends Model
{
    protected static string $table = 'documentos';

    public const CATEGORIAS = [
        'acta'        => 'Acta',
        'resolucion'  => 'Resolución',
        'reglamento'  => 'Reglamento',
        'contrato'    => 'Contrato',
        'informe'     => 'Informe',
        'eleccion'    => 'Elecciones',
        'socio'       => 'Socio',
        'general'     => 'General',
    ];

    /**
     * Listado de documentos con sus filtros.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, array<string, mixed>>
     */
    public static function listar(array $filtros, int $limite, int $desplazamiento): array
    {
        $limite = max(1, min(200, $limite));
        $desplazamiento = max(0, $desplazamiento);

        [$filtro, $params] = self::condiciones($filtros, 'd');

        $sql = sprintf(
            'SELECT d.*,
                    s.`codigo` AS socio_codigo,
                    CONCAT(s.`nombres`, \' \', s.`apellidos`) AS socio_nombre
             FROM `%s` d
             LEFT JOIN `%s` s ON s.`id` = d.`socio_id`
             %s
             ORDER BY d.`fecha_documento` DESC, d.`id` DESC
             LIMIT %d OFFSET %d',
            self::table(),
            self::prefijada('socios'),
            $filtro,
            $limite,
            $desplazamiento
        );

        return self::fetchAll($sql, $params);
    }

    /**
     * @param array<string, mixed> $filtros
     */
    public static function total(array $filtros): int
    {
        [$filtro, $params] = self::condiciones($filtros, 'd');

        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` d %s', self::table(), $filtro),
            $params
        );
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

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'titulo'          => (string) $datos['titulo'],
            'categoria'       => (string) $datos['categoria'],
            'descripcion'     => self::vacioANulo($datos['descripcion'] ?? null),
            'archivo'         => self::vacioANulo($datos['archivo'] ?? null),
            'nombre_original' => self::vacioANulo($datos['nombre_original'] ?? null),
            'tipo_mime'       => self::vacioANulo($datos['tipo_mime'] ?? null),
            'tamano_bytes'    => self::enteroANulo($datos['tamano_bytes'] ?? null),
            'fecha_documento' => self::vacioANulo($datos['fecha_documento'] ?? null),
            'es_privado'      => (int) ($datos['es_privado'] ?? 1) === 1 ? 1 : 0,
            'socio_id'        => self::enteroANulo($datos['socio_id'] ?? null),
            'subido_por'      => self::enteroANulo($datos['subido_por'] ?? null),
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
                     `categoria` = :categoria,
                     `descripcion` = :descripcion,
                     `archivo` = :archivo,
                     `nombre_original` = :nombre_original,
                     `tipo_mime` = :tipo_mime,
                     `tamano_bytes` = :tamano_bytes,
                     `fecha_documento` = :fecha_documento,
                     `es_privado` = :es_privado,
                     `socio_id` = :socio_id
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'titulo'          => (string) $datos['titulo'],
                'categoria'       => (string) $datos['categoria'],
                'descripcion'     => self::vacioANulo($datos['descripcion'] ?? null),
                'archivo'         => self::vacioANulo($datos['archivo'] ?? null),
                'nombre_original' => self::vacioANulo($datos['nombre_original'] ?? null),
                'tipo_mime'       => self::vacioANulo($datos['tipo_mime'] ?? null),
                'tamano_bytes'    => self::enteroANulo($datos['tamano_bytes'] ?? null),
                'fecha_documento' => self::vacioANulo($datos['fecha_documento'] ?? null),
                'es_privado'      => (int) ($datos['es_privado'] ?? 1) === 1 ? 1 : 0,
                'socio_id'        => self::enteroANulo($datos['socio_id'] ?? null),
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

    public static function contar(): int
    {
        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::table()));
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function condiciones(array $filtros, string $alias): array
    {
        $condiciones = [];
        $params = [];

        $busqueda = trim((string) ($filtros['q'] ?? ''));

        if ($busqueda !== '') {
            $condiciones[] = sprintf(
                '(%s.`titulo` LIKE :q1 OR %s.`descripcion` LIKE :q2 OR %s.`nombre_original` LIKE :q3)',
                $alias,
                $alias,
                $alias
            );
            $params['q1'] = '%' . $busqueda . '%';
            $params['q2'] = '%' . $busqueda . '%';
            $params['q3'] = '%' . $busqueda . '%';
        }

        $categoria = (string) ($filtros['categoria'] ?? '');

        if (isset(self::CATEGORIAS[$categoria])) {
            $condiciones[] = sprintf('%s.`categoria` = :categoria', $alias);
            $params['categoria'] = $categoria;
        }

        $privacidad = (string) ($filtros['privacidad'] ?? '');

        if ($privacidad === 'privado') {
            $condiciones[] = sprintf('%s.`es_privado` = 1', $alias);
        } elseif ($privacidad === 'publico') {
            $condiciones[] = sprintf('%s.`es_privado` = 0', $alias);
        }

        if ($condiciones === []) {
            return ['', $params];
        }

        return ['WHERE ' . implode(' AND ', $condiciones), $params];
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
