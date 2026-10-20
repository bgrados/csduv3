<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Pagos de una reserva, vinculados al movimiento de caja correspondiente.
 */
final class ReservaPago extends Model
{
    protected static string $table = 'reserva_pagos';

    public const TIPOS = [
        'adelanto'   => 'Adelanto',
        'saldo'      => 'Saldo',
        'devolucion' => 'Devolución',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listarPorReserva(int $reservaId): array
    {
        $sql = sprintf(
            'SELECT p.*,
                    mp.`nombre` AS medio_pago_nombre,
                    u.`nombres` AS usuario_nombres,
                    u.`apellidos` AS usuario_apellidos
             FROM `%s` p
             LEFT JOIN `%s` mp ON mp.`id` = (SELECT `medio_pago_id` FROM `%s` m WHERE m.`id` = p.`movimiento_id`)
             LEFT JOIN `%s` u ON u.`id` = p.`registrado_por`
             WHERE p.`reserva_id` = :reserva_id
             ORDER BY p.`fecha` DESC, p.`id` DESC',
            self::table(),
            self::prefijada('medios_pago'),
            self::prefijada('movimientos'),
            self::prefijada('usuarios')
        );

        return self::fetchAll($sql, ['reserva_id' => $reservaId]);
    }

    public static function totalReservado(int $reservaId): float
    {
        return (float) self::scalar(
            sprintf(
                'SELECT COALESCE(SUM(CASE WHEN `tipo` <> \'devolucion\' THEN `monto` ELSE -`monto` END), 0)
                 FROM `%s` WHERE `reserva_id` = :reserva_id',
                self::table()
            ),
            ['reserva_id' => $reservaId]
        );
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'reserva_id'     => (int) $datos['reserva_id'],
            'movimiento_id'  => self::enteroANulo($datos['movimiento_id'] ?? null),
            'tipo'           => (string) $datos['tipo'],
            'fecha'          => (string) $datos['fecha'],
            'monto'          => (string) $datos['monto'],
            'registrado_por' => self::enteroANulo($datos['registrado_por'] ?? null),
            'observaciones'  => self::vacioANulo($datos['observaciones'] ?? null),
        ]);
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
