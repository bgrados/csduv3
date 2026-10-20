<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Negocios de la unidad vecinal que el club publica en su portal.
 *
 * Todo es editable desde el panel: nombre, categoría, descripción, contactos,
 * enlace, dirección, horario y logotipo.
 */
final class Negocio extends Model
{
    protected static string $table = 'negocios';

    public const CATEGORIAS = [
        'comercio'    => 'Comercio',
        'servicio'    => 'Servicio',
        'profesional' => 'Profesional',
        'salud'       => 'Salud',
        'educacion'   => 'Educación',
        'otro'        => 'Otro',
    ];

    /**
     * Negocios visibles para el portal público.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarVisibles(): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` WHERE `visible` = 1 ORDER BY `destacado` DESC, `orden` ASC, `nombre` ASC',
                self::table()
            )
        );
    }

    /**
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
            'SELECT * FROM `%s` %s ORDER BY `visible` DESC, `destacado` DESC, `orden` ASC, `nombre` ASC',
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
            'nombre'          => (string) $datos['nombre'],
            'categoria'       => (string) $datos['categoria'],
            'descripcion'     => self::vacioANulo($datos['descripcion'] ?? null),
            'telefono'        => self::vacioANulo($datos['telefono'] ?? null),
            'whatsapp'        => self::vacioANulo($datos['whatsapp'] ?? null),
            'email'           => self::vacioANulo($datos['email'] ?? null),
            'web'             => self::vacioANulo($datos['web'] ?? null),
            'direccion'       => self::vacioANulo($datos['direccion'] ?? null),
            'horario'         => self::vacioANulo($datos['horario'] ?? null),
            'archivo'         => self::vacioANulo($datos['archivo'] ?? null),
            'nombre_original' => self::vacioANulo($datos['nombre_original'] ?? null),
            'tipo_mime'       => self::vacioANulo($datos['tipo_mime'] ?? null),
            'tamano_bytes'    => self::enteroANulo($datos['tamano_bytes'] ?? null),
            'visible'         => (int) ($datos['visible'] ?? 1) === 1 ? 1 : 0,
            'destacado'       => (int) ($datos['destacado'] ?? 0) === 1 ? 1 : 0,
            'orden'           => (int) ($datos['orden'] ?? 1),
            'creado_por'      => self::enteroANulo($datos['creado_por'] ?? null),
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
                     `categoria` = :categoria,
                     `descripcion` = :descripcion,
                     `telefono` = :telefono,
                     `whatsapp` = :whatsapp,
                     `email` = :email,
                     `web` = :web,
                     `direccion` = :direccion,
                     `horario` = :horario,
                     `archivo` = :archivo,
                     `nombre_original` = :nombre_original,
                     `tipo_mime` = :tipo_mime,
                     `tamano_bytes` = :tamano_bytes,
                     `visible` = :visible,
                     `destacado` = :destacado,
                     `orden` = :orden
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'nombre'          => (string) $datos['nombre'],
                'categoria'       => (string) $datos['categoria'],
                'descripcion'     => self::vacioANulo($datos['descripcion'] ?? null),
                'telefono'        => self::vacioANulo($datos['telefono'] ?? null),
                'whatsapp'        => self::vacioANulo($datos['whatsapp'] ?? null),
                'email'           => self::vacioANulo($datos['email'] ?? null),
                'web'             => self::vacioANulo($datos['web'] ?? null),
                'direccion'       => self::vacioANulo($datos['direccion'] ?? null),
                'horario'         => self::vacioANulo($datos['horario'] ?? null),
                'archivo'         => self::vacioANulo($datos['archivo'] ?? null),
                'nombre_original' => self::vacioANulo($datos['nombre_original'] ?? null),
                'tipo_mime'       => self::vacioANulo($datos['tipo_mime'] ?? null),
                'tamano_bytes'    => self::enteroANulo($datos['tamano_bytes'] ?? null),
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

    /**
     * Negocios publicados en el portal.
     */
    public static function contarPublicados(): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `visible` = 1', self::table())
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
