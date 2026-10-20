<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Categorías de ingreso y egreso de la caja.
 *
 * El club las define según su estatuto y su realidad; el sistema no las inventa.
 */
final class CategoriaMovimiento extends Model
{
    protected static string $table = 'categorias_movimiento';

    public const TIPOS = [
        'ingreso' => 'Ingreso',
        'egreso'  => 'Egreso',
    ];

    /**
     * Todas las categorías, agrupadas por tipo.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function todas(): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` ORDER BY `tipo` ASC, `activo` DESC, `nombre` ASC',
                self::table()
            )
        );
    }

    /**
     * Categorías activas de un tipo.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function activasPorTipo(string $tipo): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` WHERE `tipo` = :tipo AND `activo` = 1 ORDER BY `nombre` ASC',
                self::table()
            ),
            ['tipo' => $tipo]
        );
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function agrupadas(): array
    {
        $agrupadas = ['ingreso' => [], 'egreso' => []];

        foreach (self::todas() as $categoria) {
            $tipo = (string) $categoria['tipo'];
            $agrupadas[$tipo][] = $categoria;
        }

        return $agrupadas;
    }

    public static function crear(array $datos): int
    {
        return self::insertar([
            'nombre'      => (string) $datos['nombre'],
            'tipo'        => (string) $datos['tipo'],
            'descripcion' => self::vacioANulo($datos['descripcion'] ?? null),
            'activo'      => (int) ($datos['activo'] ?? 1) === 1 ? 1 : 0,
        ]);
    }

    public static function cambiarActivo(int $id, bool $activo): void
    {
        self::run(
            sprintf('UPDATE `%s` SET `activo` = :activo WHERE `id` = :id', self::table()),
            ['activo' => $activo ? 1 : 0, 'id' => $id]
        );
    }

    public static function nombreExiste(string $nombre, string $tipo, ?int $excluirId = null): bool
    {
        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` WHERE `nombre` = :nombre AND `tipo` = :tipo',
            self::table()
        );
        $params = ['nombre' => $nombre, 'tipo' => $tipo];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND `id` <> :id';
            $params['id'] = $excluirId;
        }

        return (int) self::scalar($sql, $params) > 0;
    }

    /**
     * Identificador de una categoría por nombre y tipo (0 si no existe).
     */
    public static function idPorNombre(string $nombre, string $tipo): int
    {
        $sql = sprintf(
            'SELECT `id` FROM `%s` WHERE `nombre` = :nombre AND `tipo` = :tipo LIMIT 1',
            self::table()
        );

        $valor = self::scalar($sql, ['nombre' => $nombre, 'tipo' => $tipo]);

        return $valor === false || $valor === null ? 0 : (int) $valor;
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

    private static function vacioANulo(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
