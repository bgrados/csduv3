<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Reservas confirmadas o pendientes de una instalación.
 *
 * La comprobación de solapamiento se hace en el servidor. Para que dos
 * peticiones simultáneas no reserven el mismo horario, debe ejecutarse dentro de
 * una transacción con bloqueo (ver docs/BASE_DE_DATOS.md).
 */
final class Reserva extends Model
{
    protected static string $table = 'reservas';

    /**
     * ¿Existe una reserva activa que se superpone con el rango indicado?
     */
    public static function haySolapamiento(
        int $instalacionId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $exceptoId = null
    ): bool {
        $sql = sprintf(
            'SELECT COUNT(*)
             FROM `%s`
             WHERE `instalacion_id` = :instalacion_id
               AND `fecha_evento` = :fecha
               AND `estado` IN (\'pendiente\', \'confirmada\')
               AND `hora_inicio` < :hora_fin
               AND `hora_fin` > :hora_inicio',
            self::table()
        );

        $params = [
            'instalacion_id' => $instalacionId,
            'fecha'          => $fecha,
            'hora_fin'       => self::normalizarHora($horaFin),
            'hora_inicio'    => self::normalizarHora($horaInicio),
        ];

        if ($exceptoId !== null) {
            $sql .= ' AND `id` <> :excepto';
            $params['excepto'] = $exceptoId;
        }

        return (int) self::scalar($sql, $params) > 0;
    }

    /**
     * Franjas ocupadas de una instalación en una fecha (sin datos personales).
     *
     * @return array<int, array{hora_inicio: string, hora_fin: string, estado: string}>
     */
    public static function franjasOcupadas(int $instalacionId, string $fecha): array
    {
        $sql = sprintf(
            'SELECT `hora_inicio`, `hora_fin`, `estado`
             FROM `%s`
             WHERE `instalacion_id` = :instalacion_id
               AND `fecha_evento` = :fecha
               AND `estado` IN (\'pendiente\', \'confirmada\')
             ORDER BY `hora_inicio` ASC',
            self::table()
        );

        return self::fetchAll($sql, ['instalacion_id' => $instalacionId, 'fecha' => $fecha]);
    }

    /**
     * Próximas reservas para el panel administrativo.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function proximas(int $limite = 5): array
    {
        $limite = max(1, min(20, $limite));

        $sql = sprintf(
            'SELECT r.`id`, r.`codigo`, r.`fecha_evento`, r.`hora_inicio`, r.`hora_fin`,
                    r.`estado`, r.`precio_acordado`, i.`nombre` AS instalacion_nombre
             FROM `%s` r
             INNER JOIN `%s` i ON i.`id` = r.`instalacion_id`
             WHERE r.`fecha_evento` >= CURDATE()
               AND r.`estado` IN (\'pendiente\', \'confirmada\')
             ORDER BY r.`fecha_evento` ASC, r.`hora_inicio` ASC
             LIMIT %d',
            self::table(),
            Instalacion::table(),
            $limite
        );

        return self::fetchAll($sql);
    }

    public static function contarProximas(): int
    {
        return (int) self::scalar(
            sprintf(
                'SELECT COUNT(*) FROM `%s`
                 WHERE `fecha_evento` >= CURDATE() AND `estado` IN (\'pendiente\', \'confirmada\')',
                self::table()
            )
        );
    }

    private static function normalizarHora(string $hora): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $hora) === 1) {
            return $hora . ':00';
        }

        return $hora;
    }
}
