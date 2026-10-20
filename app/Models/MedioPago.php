<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Medios de pago aceptados por la caja.
 */
final class MedioPago extends Model
{
    protected static string $table = 'medios_pago';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function todos(): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` ORDER BY `activo` DESC, `orden` ASC, `nombre` ASC',
                self::table()
            )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function activos(): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` WHERE `activo` = 1 ORDER BY `orden` ASC, `nombre` ASC',
                self::table()
            )
        );
    }

    public static function crear(array $datos): int
    {
        return self::insertar([
            'nombre'      => (string) $datos['nombre'],
            'descripcion' => self::vacioANulo($datos['descripcion'] ?? null),
            'orden'       => (int) ($datos['orden'] ?? 1),
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
