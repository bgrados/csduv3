<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Solicitudes de alquiler enviadas desde el portal público.
 *
 * Una solicitud NO es una reserva: queda pendiente de revisión por el personal
 * autorizado y nunca se confirma automáticamente.
 */
final class SolicitudAlquiler extends Model
{
    protected static string $table = 'solicitudes_alquiler';

    public const ESTADOS = [
        'pendiente'   => 'Pendiente',
        'en_revision' => 'En revisión',
        'atendida'    => 'Atendida',
        'rechazada'   => 'Rechazada',
    ];

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'codigo'               => $datos['codigo'],
            'instalacion_id'       => $datos['instalacion_id'],
            'tipo_evento_id'       => $datos['tipo_evento_id'] ?: null,
            'solicitante_nombre'   => $datos['solicitante_nombre'],
            'solicitante_documento' => $datos['solicitante_documento'] ?: null,
            'solicitante_telefono' => $datos['solicitante_telefono'],
            'solicitante_email'    => $datos['solicitante_email'] ?: null,
            'fecha_evento'         => $datos['fecha_evento'],
            'hora_inicio'          => $datos['hora_inicio'],
            'hora_fin'             => $datos['hora_fin'],
            'cantidad_personas'    => $datos['cantidad_personas'] ?: null,
            'descripcion'          => $datos['descripcion'] ?: null,
            'acepta_condiciones'   => 1,
            'estado'               => 'pendiente',
            'conflicto_detectado'  => $datos['conflicto_detectado'] ?? 0,
            'ip'                   => $datos['ip'] ?? null,
            'user_agent'           => $datos['user_agent'] ?? null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listar(?string $estado, ?string $busqueda, int $limite, int $desplazamiento): array
    {
        $limite = max(1, min(200, $limite));
        $desplazamiento = max(0, $desplazamiento);

        $params = [];
        $condiciones = [];

        if ($estado !== null && isset(self::ESTADOS[$estado])) {
            $condiciones[] = 's.`estado` = :estado';
            $params['estado'] = $estado;
        }

        if ($busqueda !== null && $busqueda !== '') {
            $condiciones[] = '(s.`codigo` LIKE :q OR s.`solicitante_nombre` LIKE :q OR s.`solicitante_telefono` LIKE :q OR s.`solicitante_email` LIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }

        $filtro = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        $sql = sprintf(
            'SELECT s.*, i.`nombre` AS instalacion_nombre, e.`nombre` AS tipo_evento_nombre
             FROM `%s` s
             INNER JOIN `%s` i ON i.`id` = s.`instalacion_id`
             LEFT JOIN `%s` e ON e.`id` = s.`tipo_evento_id`
             %s
             ORDER BY s.`created_at` DESC
             LIMIT %d OFFSET %d',
            self::table(),
            Instalacion::table(),
            self::prefijada('tipos_evento'),
            $filtro,
            $limite,
            $desplazamiento
        );

        return self::fetchAll($sql, $params);
    }

    public static function total(?string $estado, ?string $busqueda): int
    {
        $params = [];
        $condiciones = [];

        if ($estado !== null && isset(self::ESTADOS[$estado])) {
            $condiciones[] = '`estado` = :estado';
            $params['estado'] = $estado;
        }

        if ($busqueda !== null && $busqueda !== '') {
            $condiciones[] = '(`codigo` LIKE :q OR `solicitante_nombre` LIKE :q OR `solicitante_telefono` LIKE :q OR `solicitante_email` LIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }

        $filtro = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s` %s', self::table(), $filtro), $params);
    }

    /** @return array<string, mixed>|null */
    public static function ver(int $id): ?array
    {
        $sql = sprintf(
            'SELECT s.*, i.`nombre` AS instalacion_nombre, i.`tipo` AS instalacion_tipo,
                    e.`nombre` AS tipo_evento_nombre,
                    u.`nombres` AS revisor_nombres, u.`apellidos` AS revisor_apellidos
             FROM `%s` s
             INNER JOIN `%s` i ON i.`id` = s.`instalacion_id`
             LEFT JOIN `%s` e ON e.`id` = s.`tipo_evento_id`
             LEFT JOIN `%s` u ON u.`id` = s.`revisado_por`
             WHERE s.`id` = :id
             LIMIT 1',
            self::table(),
            Instalacion::table(),
            self::prefijada('tipos_evento'),
            self::prefijada('usuarios')
        );

        return self::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Resumen mínimo para la confirmación pública (sin datos personales).
     *
     * @return array<string, mixed>|null
     */
    public static function resumenPorCodigo(string $codigo): ?array
    {
        $sql = sprintf(
            'SELECT s.`codigo`, s.`fecha_evento`, s.`hora_inicio`, s.`hora_fin`, s.`estado`,
                    i.`nombre` AS instalacion_nombre
             FROM `%s` s
             INNER JOIN `%s` i ON i.`id` = s.`instalacion_id`
             WHERE s.`codigo` = :codigo
             LIMIT 1',
            self::table(),
            Instalacion::table()
        );

        return self::fetchOne($sql, ['codigo' => $codigo]);
    }

    public static function contarPorEstado(string $estado): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `estado` = :estado', self::table()),
            ['estado' => $estado]
        );
    }

    public static function contarPendientes(): int
    {
        return (int) self::scalar(
            sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE `estado` IN (\'pendiente\', \'en_revision\')',
                self::table()
            )
        );
    }

    /**
     * ¿Hay otra solicitud pendiente que se superpone con el horario pedido?
     *
     * No impide registrar la solicitud: se marca como conflicto para que el
     * personal la revise con prioridad.
     */
    public static function haySolicitudSolapada(
        int $instalacionId,
        string $fecha,
        string $horaInicio,
        string $horaFin
    ): bool {
        $sql = sprintf(
            'SELECT COUNT(*)
             FROM `%s`
             WHERE `instalacion_id` = :instalacion_id
               AND `fecha_evento` = :fecha
               AND `estado` IN (\'pendiente\', \'en_revision\')
               AND `hora_inicio` < :hora_fin
               AND `hora_fin` > :hora_inicio',
            self::table()
        );

        return (int) self::scalar($sql, [
            'instalacion_id' => $instalacionId,
            'fecha'          => $fecha,
            'hora_fin'       => self::normalizarHora($horaFin),
            'hora_inicio'    => self::normalizarHora($horaInicio),
        ]) > 0;
    }

    private static function normalizarHora(string $hora): string
    {
        return preg_match('/^\d{2}:\d{2}$/', $hora) === 1 ? $hora . ':00' : $hora;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function ultimas(int $limite = 5): array
    {
        $limite = max(1, min(20, $limite));

        $sql = sprintf(
            'SELECT s.`id`, s.`codigo`, s.`solicitante_nombre`, s.`fecha_evento`,
                    s.`hora_inicio`, s.`hora_fin`, s.`estado`, i.`nombre` AS instalacion_nombre
             FROM `%s` s
             INNER JOIN `%s` i ON i.`id` = s.`instalacion_id`
             ORDER BY s.`created_at` DESC
             LIMIT %d',
            self::table(),
            Instalacion::table(),
            $limite
        );

        return self::fetchAll($sql);
    }

    public static function actualizarEstado(int $id, string $estado, ?int $usuarioId, ?string $motivo = null, ?string $nota = null): void
    {
        $sql = sprintf(
            'UPDATE `%s`
             SET `estado` = :estado,
                 `revisado_por` = :usuario,
                 `revisado_at` = NOW(),
                 `motivo_rechazo` = :motivo,
                 `nota_interna` = :nota
             WHERE `id` = :id',
            self::table()
        );

        self::run($sql, [
            'estado'  => $estado,
            'usuario' => $usuarioId,
            'motivo'  => $motivo,
            'nota'    => $nota,
            'id'      => $id,
        ]);
    }

    protected static function prefijada(string $tabla): string
    {
        $prefijo = (string) \App\Core\Config::get('database.prefix', 'csduv3_');

        return $prefijo . $tabla;
    }
}
