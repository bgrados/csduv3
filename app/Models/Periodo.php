<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Periodos administrativos o contables.
 *
 * Sirven para agrupar los movimientos de caja. El club los define; si no hay
 * ninguno, los movimientos se registran sin periodo.
 */
final class Periodo extends Model
{
    protected static string $table = 'periodos';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function todos(): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` ORDER BY `cerrado` ASC, `fecha_inicio` DESC, `nombre` ASC',
                self::table()
            )
        );
    }

    /**
     * Periodos abiertos, para asignar movimientos nuevos.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function abiertos(): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` WHERE `cerrado` = 0 ORDER BY `fecha_inicio` DESC, `nombre` ASC',
                self::table()
            )
        );
    }

    /**
     * Periodo que corresponde a una fecha.
     *
     * @return array<string, mixed>|null
     */
    public static function porFecha(string $fecha): ?array
    {
        return self::fetchOne(
            sprintf(
                'SELECT * FROM `%s` WHERE `fecha_inicio` <= :desde AND `fecha_fin` >= :hasta
                 ORDER BY `fecha_inicio` DESC LIMIT 1',
                self::table()
            ),
            ['desde' => $fecha, 'hasta' => $fecha]
        );
    }

    public static function crear(array $datos): int
    {
        return self::insertar([
            'nombre'         => (string) $datos['nombre'],
            'fecha_inicio'   => (string) $datos['fecha_inicio'],
            'fecha_fin'      => (string) $datos['fecha_fin'],
            'cerrado'        => (int) ($datos['cerrado'] ?? 0) === 1 ? 1 : 0,
            'observaciones'  => self::vacioANulo($datos['observaciones'] ?? null),
        ]);
    }

    public static function cambiarCerrado(int $id, bool $cerrado): void
    {
        self::run(
            sprintf('UPDATE `%s` SET `cerrado` = :cerrado WHERE `id` = :id', self::table()),
            ['cerrado' => $cerrado ? 1 : 0, 'id' => $id]
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
