<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Galería institucional: reuniones, campeonatos, junta directiva y socios.
 *
 * Las fotos se guardan en `storage/uploads` y se sirven por el controlador.
 * Solo las fotos visibles se publican en el portal.
 */
final class Foto extends Model
{
    protected static string $table = 'fotos';

    public const CATEGORIAS = [
        'reunion'      => 'Reunión',
        'campeonato'  => 'Campeonato',
        'directiva'   => 'Junta directiva',
        'socios'      => 'Socios',
        'evento'      => 'Evento',
        'instalacion' => 'Instalaciones',
    ];

    /**
     * Fotos visibles para el portal público.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarVisibles(int $limite = 24, string $categoria = ''): array
    {
        $limite = max(1, min(120, $limite));

        $condiciones = ['`visible` = 1'];
        $params = [];

        if (isset(self::CATEGORIAS[$categoria])) {
            $condiciones[] = '`categoria` = :categoria';
            $params['categoria'] = $categoria;
        }

        $sql = sprintf(
            'SELECT * FROM `%s`
             WHERE %s
             ORDER BY `destacado` DESC, `fecha_foto` DESC, `id` DESC
             LIMIT %d',
            self::table(),
            implode(' AND ', $condiciones),
            $limite
        );

        return self::fetchAll($sql, $params);
    }

    /**
     * Noticias destacadas: las que aparecen en la portada.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarPortada(int $limite = 5): array
    {
        $limite = max(1, min(24, $limite));

        $sql = sprintf(
            'SELECT * FROM `%s`
             WHERE `visible` = 1 AND `destacado` = 1
             ORDER BY `fecha_foto` DESC, `id` DESC
             LIMIT %d',
            self::table(),
            $limite
        );

        return self::fetchAll($sql);
    }

    /**
     * La noticia que ocupa la portada del portal.
     *
     * @return array<string, mixed>|null
     */
    public static function portada(): ?array
    {
        $sql = sprintf(
            'SELECT * FROM `%s`
             WHERE `visible` = 1 AND `destacado` = 1
             ORDER BY `fecha_foto` DESC, `id` DESC
             LIMIT 1',
            self::table()
        );

        return self::fetchOne($sql);
    }

    /**
     * Publica una noticia en portada: solo puede haber una.
     *
     * Las demás noticias publicadas en portada se devuelven a la galería.
     */
    public static function establecerPortada(int $id): void
    {
        self::run(
            sprintf('UPDATE `%s` SET `destacado` = 0 WHERE `destacado` = 1 AND `id` <> :id', self::table()),
            ['id' => $id]
        );

        self::run(
            sprintf('UPDATE `%s` SET `destacado` = 1 WHERE `id` = :id', self::table()),
            ['id' => $id]
        );
    }

    /**
     * Quita una noticia de la portada.
     */
    public static function quitarPortada(int $id): void
    {
        self::run(
            sprintf('UPDATE `%s` SET `destacado` = 0 WHERE `id` = :id', self::table()),
            ['id' => $id]
        );
    }

    /**
     * Todas las fotos (panel administrativo).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function todas(string $categoria = ''): array
    {
        $condiciones = [];
        $params = [];

        if (isset(self::CATEGORIAS[$categoria])) {
            $condiciones[] = '`categoria` = :categoria';
            $params['categoria'] = $categoria;
        }

        $sql = sprintf(
            'SELECT * FROM `%s` %s ORDER BY `fecha_foto` DESC, `id` DESC',
            self::table(),
            $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones)
        );

        return self::fetchAll($sql, $params);
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
            'resumen'         => self::vacioANulo($datos['resumen'] ?? null),
            'descripcion'     => self::vacioANulo($datos['descripcion'] ?? null),
            'categoria'       => (string) $datos['categoria'],
            'archivo'         => self::vacioANulo($datos['archivo'] ?? null),
            'nombre_original' => self::vacioANulo($datos['nombre_original'] ?? null),
            'tipo_mime'       => self::vacioANulo($datos['tipo_mime'] ?? null),
            'tamano_bytes'    => self::enteroANulo($datos['tamano_bytes'] ?? null),
            'fecha_foto'      => self::vacioANulo($datos['fecha_foto'] ?? null),
            'visible'         => (int) ($datos['visible'] ?? 1) === 1 ? 1 : 0,
            'destacado'       => (int) ($datos['destacado'] ?? 0) === 1 ? 1 : 0,
            'orden'           => (int) ($datos['orden'] ?? 1),
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
                     `resumen` = :resumen,
                     `descripcion` = :descripcion,
                     `categoria` = :categoria,
                     `archivo` = :archivo,
                     `nombre_original` = :nombre_original,
                     `tipo_mime` = :tipo_mime,
                     `tamano_bytes` = :tamano_bytes,
                     `fecha_foto` = :fecha_foto,
                     `visible` = :visible,
                     `destacado` = :destacado,
                     `orden` = :orden
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'titulo'          => (string) $datos['titulo'],
                'resumen'         => self::vacioANulo($datos['resumen'] ?? null),
                'descripcion'     => self::vacioANulo($datos['descripcion'] ?? null),
                'categoria'       => (string) $datos['categoria'],
                'archivo'         => self::vacioANulo($datos['archivo'] ?? null),
                'nombre_original' => self::vacioANulo($datos['nombre_original'] ?? null),
                'tipo_mime'       => self::vacioANulo($datos['tipo_mime'] ?? null),
                'tamano_bytes'    => self::enteroANulo($datos['tamano_bytes'] ?? null),
                'fecha_foto'      => self::vacioANulo($datos['fecha_foto'] ?? null),
                'visible'         => (int) ($datos['visible'] ?? 1) === 1 ? 1 : 0,
                'destacado'       => (int) ($datos['destacado'] ?? 0) === 1 ? 1 : 0,
                'orden'           => (int) ($datos['orden'] ?? 1),
                'id'              => $id,
            ]
        );
    }

    public static function cambiarVisible(int $id, bool $visible): void
    {
        self::run(
            sprintf('UPDATE `%s` SET `visible` = :visible WHERE `id` = :id', self::table()),
            ['visible' => $visible ? 1 : 0, 'id' => $id]
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
