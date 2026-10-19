<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Movimientos de caja (solo lectura para el panel principal).
 *
 * El módulo completo se implementa en la Fase 5.
 */
final class Movimiento extends Model
{
    protected static string $table = 'movimientos';

    /**
     * @return array{ingresos: float, egresos: float, saldo: float, cantidad: int}
     */
    public static function resumenPeriodo(string $inicio, string $fin): array
    {
        $sql = sprintf(
            'SELECT
                COALESCE(SUM(CASE WHEN `tipo` = \'ingreso\' THEN `monto` ELSE 0 END), 0) AS ingresos,
                COALESCE(SUM(CASE WHEN `tipo` = \'egreso\' THEN `monto` ELSE 0 END), 0) AS egresos,
                COUNT(*) AS cantidad
             FROM `%s`
             WHERE `estado` = \'registrado\'
               AND `fecha` BETWEEN :inicio AND :fin',
            self::table()
        );

        $fila = self::fetchOne($sql, ['inicio' => $inicio, 'fin' => $fin]);

        if ($fila === null) {
            return ['ingresos' => 0.0, 'egresos' => 0.0, 'saldo' => 0.0, 'cantidad' => 0];
        }

        $ingresos = (float) $fila['ingresos'];
        $egresos = (float) $fila['egresos'];

        return [
            'ingresos' => $ingresos,
            'egresos'  => $egresos,
            'saldo'    => $ingresos - $egresos,
            'cantidad' => (int) $fila['cantidad'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function ultimos(int $limite = 5): array
    {
        $limite = max(1, min(20, $limite));

        $sql = sprintf(
            'SELECT m.`id`, m.`tipo`, m.`fecha`, m.`concepto`, m.`monto`,
                    c.`nombre` AS categoria_nombre
             FROM `%s` m
             LEFT JOIN `%s` c ON c.`id` = m.`categoria_id`
             WHERE m.`estado` = \'registrado\'
             ORDER BY m.`fecha` DESC, m.`id` DESC
             LIMIT %d',
            self::table(),
            self::prefijada('categorias_movimiento'),
            $limite
        );

        return self::fetchAll($sql);
    }

    protected static function prefijada(string $tabla): string
    {
        $prefijo = (string) \App\Core\Config::get('database.prefix', 'csduv3_');

        return $prefijo . $tabla;
    }
}
