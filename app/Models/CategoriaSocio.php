<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;

/**
 * Categorías de socio configurables por la institución.
 *
 * El sistema no precarga categorías: el club define las suyas. Una categoría
 * inactiva se conserva para no perder el histórico de los socios asignados.
 */
final class CategoriaSocio extends Model
{
    protected static string $table = 'categorias_socio';

    /**
     * Categorías ordenadas para su presentación.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function todas(bool $soloActivas = false): array
    {
        $filtro = $soloActivas ? ' WHERE `activo` = 1' : '';

        return self::fetchAll(sprintf(
            'SELECT * FROM `%s`%s ORDER BY `orden` ASC, `nombre` ASC',
            self::table(),
            $filtro
        ));
    }

    /**
     * Categorías con el número de socios que las tienen asignadas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function todasConUso(): array
    {
        return self::fetchAll(sprintf(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM `%s` s WHERE s.`categoria_id` = c.`id`) AS total_socios
             FROM `%s` c
             ORDER BY c.`orden` ASC, c.`nombre` ASC',
            self::prefijada('socios'),
            self::table()
        ));
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

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'nombre'      => $datos['nombre'],
            'descripcion' => self::vacioANulo($datos['descripcion'] ?? null),
            'orden'       => max(1, (int) ($datos['orden'] ?? 1)),
            'activo'      => 1,
        ]);
    }

    public static function cambiarActivo(int $id, bool $activo): void
    {
        self::run(
            sprintf('UPDATE `%s` SET `activo` = :activo WHERE `id` = :id', self::table()),
            ['activo' => $activo ? 1 : 0, 'id' => $id]
        );
    }

    public static function contar(): int
    {
        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::table()));
    }

    protected static function prefijada(string $tabla): string
    {
        $prefijo = (string) Config::get('database.prefix', 'csduv3_');

        return $prefijo . $tabla;
    }

    private static function vacioANulo(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
