<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use RuntimeException;

/**
 * Reservas confirmadas o pendientes de una instalación.
 *
 * El solapamiento se comprueba **dentro de una transacción con bloqueo**
 * (`SELECT ... FOR UPDATE` sobre el índice por instalación y fecha), de modo
 * que dos peticiones simultáneas no reservan el mismo horario.
 *
 * Los importes se calculan en el servidor: el precio acordado, el adelanto y
 * el total pagado.
 */
final class Reserva extends Model
{
    protected static string $table = 'reservas';

    public const ESTADOS = [
        'pendiente'  => 'Pendiente',
        'confirmada' => 'Confirmada',
        'cancelada'  => 'Cancelada',
        'finalizada' => 'Finalizada',
    ];

    public const ESTADOS_EDITABLES = [
        'pendiente'  => 'Pendiente',
        'confirmada' => 'Confirmada',
        'finalizada' => 'Finalizada',
    ];

    /**
     * ¿Existe una reserva activa que se superpone con el rango indicado?
     *
     * Si la hora de fin es menor o igual que la de inicio, la reserva termina
     * al día siguiente (por ejemplo, de 19:00 a 03:00).
     */
    public static function haySolapamiento(
        int $instalacionId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $exceptoId = null
    ): bool {
        $inicio = self::instanteInicio($fecha, $horaInicio);
        $fin = self::instanteFin($fecha, $horaInicio, $horaFin);

        $sql = sprintf(
            'SELECT COUNT(*)
             FROM `%s`
             WHERE `instalacion_id` = :instalacion_id
               AND `estado` IN (\'pendiente\', \'confirmada\')
               AND TIMESTAMP(`fecha_evento`, `hora_inicio`) < :fin
               AND CASE
                   WHEN `hora_fin` <= `hora_inicio`
                       THEN TIMESTAMP(DATE_ADD(`fecha_evento`, INTERVAL 1 DAY), `hora_fin`)
                   ELSE TIMESTAMP(`fecha_evento`, `hora_fin`)
               END > :inicio',
            self::table()
        );

        $params = [
            'instalacion_id' => $instalacionId,
            'inicio' => $inicio,
            'fin'    => $fin,
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
     * Incluye las reservas que empezaron el día anterior y terminan de
     * madrugada en esta fecha.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function franjasOcupadas(int $instalacionId, string $fecha): array
    {
        $sql = sprintf(
            'SELECT `hora_inicio`, `hora_fin`, `estado`, `fecha_evento`,
                    IF(`hora_fin` <= `hora_inicio`, 1, 0) AS cruza_medianoche
             FROM `%s`
             WHERE `instalacion_id` = :instalacion_id
               AND `estado` IN (\'pendiente\', \'confirmada\')
               AND (
                   `fecha_evento` = :fecha
                   OR (`hora_fin` <= `hora_inicio` AND DATE_ADD(`fecha_evento`, INTERVAL 1 DAY) = :fecha)
               )
             ORDER BY `fecha_evento` ASC, `hora_inicio` ASC',
            self::table()
        );

        return self::fetchAll($sql, ['instalacion_id' => $instalacionId, 'fecha' => $fecha]);
    }

    /**
     * Instante de inicio de la reserva (formato Y-m-d H:i:s).
     */
    public static function instanteInicio(string $fecha, string $horaInicio): string
    {
        $marca = strtotime($fecha . ' ' . self::normalizarHora($horaInicio));

        return $marca === false ? $fecha . ' 00:00:00' : date('Y-m-d H:i:s', $marca);
    }

    /**
     * Instante de fin de la reserva.
     *
     * Si la hora de fin es menor o igual que la de inicio, termina al día
     * siguiente (por ejemplo, de 19:00 a 03:00).
     */
    public static function instanteFin(string $fecha, string $horaInicio, string $horaFin): string
    {
        $inicio = self::normalizarHora($horaInicio);
        $fin = self::normalizarHora($horaFin);

        $marcaInicio = strtotime($fecha . ' ' . $inicio);
        $marcaFin = strtotime($fecha . ' ' . $fin);

        if ($marcaInicio === false || $marcaFin === false) {
            return $fecha . ' ' . $fin;
        }

        if ($marcaFin <= $marcaInicio) {
            $marcaFin = strtotime('+1 day', $marcaFin);
        }

        return date('Y-m-d H:i:s', $marcaFin);
    }

    /**
     * ¿La reserva termina al día siguiente?
     */
    public static function cruzaMedianoche(string $horaInicio, string $horaFin): bool
    {
        return self::normalizarHora($horaFin) <= self::normalizarHora($horaInicio);
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

    /**
     * Listado administrativo con filtros.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, array<string, mixed>>
     */
    public static function listar(array $filtros, int $limite, int $desplazamiento): array
    {
        $limite = max(1, min(200, $limite));
        $desplazamiento = max(0, $desplazamiento);

        [$filtro, $params] = self::condiciones($filtros, 'r');

        $sql = sprintf(
            'SELECT r.*,
                    i.`nombre` AS instalacion_nombre,
                    t.`nombre` AS tipo_evento_nombre,
                    ta.`nombre` AS tarifa_nombre,
                    s.`codigo` AS socio_codigo
             FROM `%s` r
             LEFT JOIN `%s` i ON i.`id` = r.`instalacion_id`
             LEFT JOIN `%s` t ON t.`id` = r.`tipo_evento_id`
             LEFT JOIN `%s` ta ON ta.`id` = r.`tarifa_id`
             LEFT JOIN `%s` s ON s.`id` = r.`socio_id`
             %s
             ORDER BY r.`fecha_evento` DESC, r.`hora_inicio` DESC, r.`id` DESC
             LIMIT %d OFFSET %d',
            self::table(),
            Instalacion::table(),
            TipoEvento::table(),
            Tarifa::table(),
            Socio::table(),
            $filtro,
            $limite,
            $desplazamiento
        );

        return self::fetchAll($sql, $params);
    }

    /**
     * @param array<string, mixed> $filtros
     */
    public static function total(array $filtros): int
    {
        [$filtro, $params] = self::condiciones($filtros, 'r');

        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` r %s', self::table(), $filtro),
            $params
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $sql = sprintf(
            'SELECT r.*,
                    i.`nombre` AS instalacion_nombre,
                    t.`nombre` AS tipo_evento_nombre,
                    ta.`nombre` AS tarifa_nombre,
                    s.`codigo` AS socio_codigo
             FROM `%s` r
             LEFT JOIN `%s` i ON i.`id` = r.`instalacion_id`
             LEFT JOIN `%s` t ON t.`id` = r.`tipo_evento_id`
             LEFT JOIN `%s` ta ON ta.`id` = r.`tarifa_id`
             LEFT JOIN `%s` s ON s.`id` = r.`socio_id`
             WHERE r.`id` = :id
             LIMIT 1',
            self::table(),
            Instalacion::table(),
            TipoEvento::table(),
            Tarifa::table(),
            Socio::table()
        );

        return self::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Crea la reserva comprobando el solapamiento con bloqueo de filas.
     *
     * @param array<string, mixed> $datos
     * @throws RuntimeException cuando el horario ya está reservado.
     */
    public static function crearReservando(array $datos): int
    {
        return (int) Database::transaction(static function () use ($datos): int {
            self::bloquearHorario(
                (int) $datos['instalacion_id'],
                (string) $datos['fecha_evento'],
                self::normalizarHora((string) $datos['hora_inicio']),
                self::normalizarHora((string) $datos['hora_fin'])
            );

            $registro = self::insertar([
                'codigo'                => (string) $datos['codigo'],
                'instalacion_id'        => (int) $datos['instalacion_id'],
                'tarifa_id'             => self::enteroANulo($datos['tarifa_id'] ?? null),
                'tipo_evento_id'        => self::enteroANulo($datos['tipo_evento_id'] ?? null),
                'socio_id'              => self::enteroANulo($datos['socio_id'] ?? null),
                'solicitante_nombre'    => (string) $datos['solicitante_nombre'],
                'solicitante_documento' => self::vacioANulo($datos['solicitante_documento'] ?? null),
                'solicitante_telefono'  => self::vacioANulo($datos['solicitante_telefono'] ?? null),
                'solicitante_email'     => self::vacioANulo($datos['solicitante_email'] ?? null),
                'fecha_evento'          => (string) $datos['fecha_evento'],
                'hora_inicio'           => self::normalizarHora((string) $datos['hora_inicio']),
                'hora_fin'              => self::normalizarHora((string) $datos['hora_fin']),
                'precio_acordado'       => number_format((float) $datos['precio_acordado'], 2, '.', ''),
                'monto_adelanto'        => number_format((float) $datos['monto_adelanto'], 2, '.', ''),
                'monto_pagado'          => '0.00',
                'estado'                => (string) ($datos['estado'] ?? 'pendiente'),
                'condiciones'           => self::vacioANulo($datos['condiciones'] ?? null),
                'observaciones'         => self::vacioANulo($datos['observaciones'] ?? null),
                'registrado_por'        => self::enteroANulo($datos['registrado_por'] ?? null),
            ]);

            $id = (int) $registro;

            // El alquiler se refleja en caja desde el momento en que se confirma.
            if ((string) ($datos['estado'] ?? 'pendiente') === 'confirmada') {
                self::registrarIngresoEnCaja(
                    $id,
                    self::enteroANulo($datos['registrado_por'] ?? null),
                    self::enteroANulo($datos['medio_pago_id'] ?? null),
                    (string) $datos['fecha_evento']
                );
            }

            return $id;
        });
    }

    /**
     * Actualiza los datos de una reserva, comprobando el solapamiento.
     *
     * @param array<string, mixed> $datos
     * @throws RuntimeException cuando el horario ya está reservado.
     */
    public static function actualizarReserva(int $id, array $datos): void
    {
        Database::transaction(static function () use ($id, $datos): void {
            self::bloquearHorario(
                (int) $datos['instalacion_id'],
                (string) $datos['fecha_evento'],
                self::normalizarHora((string) $datos['hora_inicio']),
                self::normalizarHora((string) $datos['hora_fin']),
                $id
            );

            self::run(
                sprintf(
                    'UPDATE `%s`
                     SET `instalacion_id` = :instalacion_id,
                         `tarifa_id` = :tarifa_id,
                         `tipo_evento_id` = :tipo_evento_id,
                         `solicitante_nombre` = :solicitante_nombre,
                         `solicitante_documento` = :solicitante_documento,
                         `solicitante_telefono` = :solicitante_telefono,
                         `solicitante_email` = :solicitante_email,
                         `fecha_evento` = :fecha_evento,
                         `hora_inicio` = :hora_inicio,
                         `hora_fin` = :hora_fin,
                         `precio_acordado` = :precio_acordado,
                         `monto_adelanto` = :monto_adelanto,
                         `condiciones` = :condiciones,
                         `observaciones` = :observaciones
                     WHERE `id` = :id',
                    self::table()
                ),
                [
                    'instalacion_id'        => (int) $datos['instalacion_id'],
                    'tarifa_id'             => self::enteroANulo($datos['tarifa_id'] ?? null),
                    'tipo_evento_id'        => self::enteroANulo($datos['tipo_evento_id'] ?? null),
                    'solicitante_nombre'    => (string) $datos['solicitante_nombre'],
                    'solicitante_documento' => self::vacioANulo($datos['solicitante_documento'] ?? null),
                    'solicitante_telefono'  => self::vacioANulo($datos['solicitante_telefono'] ?? null),
                    'solicitante_email'     => self::vacioANulo($datos['solicitante_email'] ?? null),
                    'fecha_evento'          => (string) $datos['fecha_evento'],
                    'hora_inicio'           => self::normalizarHora((string) $datos['hora_inicio']),
                    'hora_fin'              => self::normalizarHora((string) $datos['hora_fin']),
                    'precio_acordado'       => number_format((float) $datos['precio_acordado'], 2, '.', ''),
                    'monto_adelanto'        => number_format((float) $datos['monto_adelanto'], 2, '.', ''),
                    'condiciones'           => self::vacioANulo($datos['condiciones'] ?? null),
                    'observaciones'         => self::vacioANulo($datos['observaciones'] ?? null),
                    'id'                    => $id,
                ]
            );
        });
    }

    public static function cambiarEstado(
        int $id,
        string $estado,
        ?int $usuarioId = null,
        ?string $motivo = null
    ): void {
        if ($estado === 'cancelada') {
            self::run(
                sprintf(
                    'UPDATE `%s`
                     SET `estado` = \'cancelada\',
                         `motivo_cancelacion` = :motivo,
                         `cancelada_por` = :usuario,
                         `cancelada_at` = NOW()
                     WHERE `id` = :id',
                    self::table()
                ),
                [
                    'motivo' => self::vacioANulo($motivo),
                    'usuario' => $usuarioId,
                    'id'     => $id,
                ]
            );

            // La reserva cancelada no puede seguir contando como ingreso.
            self::revertirIngresoEnCaja($id, $usuarioId);

            return;
        }

        self::run(
            sprintf('UPDATE `%s` SET `estado` = :estado WHERE `id` = :id', self::table()),
            ['estado' => $estado, 'id' => $id]
        );

        if ($estado === 'confirmada') {
            self::registrarIngresoEnCaja($id, $usuarioId);
        }
    }

    /**
     * Registra un pago de la reserva y lo anota en caja.
     *
     * @param array<string, mixed> $datos
     * @return int Identificador del pago registrado.
     * @throws RuntimeException cuando el pago no es válido.
     */
    public static function registrarPago(int $reservaId, array $datos, ?int $usuarioId = null): int
    {
        return (int) Database::transaction(static function () use ($reservaId, $datos, $usuarioId): int {
            $sql = sprintf(
                'SELECT r.`id`, r.`codigo`, r.`estado`, r.`precio_acordado`, r.`monto_pagado`,
                        i.`nombre` AS instalacion_nombre
                 FROM `%s` r
                 LEFT JOIN `%s` i ON i.`id` = r.`instalacion_id`
                 WHERE r.`id` = :id
                 FOR UPDATE',
                self::table(),
                Instalacion::table()
            );

            $statement = self::db()->prepare($sql);
            $statement->execute(['id' => $reservaId]);
            $reserva = $statement->fetch();

            if (!is_array($reserva)) {
                throw new RuntimeException('La reserva indicada no existe.');
            }

            if ((string) $reserva['estado'] === 'cancelada') {
                throw new RuntimeException('No se pueden registrar pagos en una reserva cancelada.');
            }

            $tipo = (string) $datos['tipo'];
            $monto = round((float) $datos['monto'], 2);

            if ($monto <= 0) {
                throw new RuntimeException('El monto del pago debe ser mayor que cero.');
            }

            $pagado = (float) $reserva['monto_pagado'];
            $precio = (float) $reserva['precio_acordado'];

            if ($tipo !== 'devolucion' && ($pagado + $monto) > ($precio + 0.001)) {
                throw new RuntimeException(
                    'El pago supera el saldo pendiente de la reserva (S/ '
                    . number_format(max(0.0, $precio - $pagado), 2, '.', ',') . ').'
                );
            }

            $movimientoId = self::movimientoIngresoId((string) $reserva['codigo']);

            if ($movimientoId === 0) {
                // Si la reserva todavía no tiene ingreso en caja, se registra
                // ahora con el importe cobrado. Una devolución es un egreso.
                $movimientoId = (int) Movimiento::crear([
                    'tipo'           => $tipo === 'devolucion' ? 'egreso' : 'ingreso',
                    'fecha'          => (string) $datos['fecha'],
                    'concepto'       => 'Alquiler ' . (string) $reserva['instalacion_nombre']
                        . ($tipo === 'devolucion' ? ' (devolución)' : ' (' . $tipo . ')')
                        . ' · ' . (string) $reserva['codigo'],
                    'monto'          => number_format($monto, 2, '.', ''),
                    'categoria_id'   => CategoriaMovimiento::idPorNombre('Alquileres', 'ingreso'),
                    'medio_pago_id'  => self::enteroANulo($datos['medio_pago_id'] ?? null),
                    'periodo_id'     => self::periodoDe((string) $datos['fecha']),
                    'socio_id'       => self::enteroANulo($datos['socio_id'] ?? null),
                    'referencia'     => (string) $reserva['codigo'],
                    'observaciones'  => (string) ($datos['observaciones'] ?? ''),
                    'responsable_id' => $usuarioId,
                ]);
            }

            $pagoId = ReservaPago::crear([
                'reserva_id'     => $reservaId,
                'movimiento_id'  => $movimientoId,
                'tipo'           => $tipo,
                'fecha'          => (string) $datos['fecha'],
                'monto'          => number_format($monto, 2, '.', ''),
                'registrado_por' => $usuarioId,
                'observaciones'  => (string) ($datos['observaciones'] ?? ''),
            ]);

            self::run(
                sprintf(
                    'UPDATE `%s` SET `monto_pagado` = :monto WHERE `id` = :id',
                    self::table()
                ),
                [
                    'monto' => number_format($tipo === 'devolucion' ? $pagado - $monto : $pagado + $monto, 2, '.', ''),
                    'id'    => $reservaId,
                ]
            );

            return $pagoId;
        });
    }

    /**
     * Registra el ingreso de la reserva en la caja del club.
     *
     * Se hace una sola vez por reserva: si ya existe un movimiento con el
     * código de la reserva, no se vuelve a crear (así el alquiler no se
     * cuenta dos veces en caja).
     */
    public static function registrarIngresoEnCaja(
        int $reservaId,
        ?int $usuarioId = null,
        ?int $medioPagoId = null,
        ?string $fecha = null
    ): bool {
        $sql = sprintf(
            'SELECT r.`id`, r.`codigo`, r.`precio_acordado`, r.`estado`,
                    i.`nombre` AS instalacion_nombre
             FROM `%s` r
             LEFT JOIN `%s` i ON i.`id` = r.`instalacion_id`
             WHERE r.`id` = :id
             LIMIT 1',
            self::table(),
            Instalacion::table()
        );

        $statement = self::db()->prepare($sql);
        $statement->execute(['id' => $reservaId]);
        $reserva = $statement->fetch();

        if (!is_array($reserva)) {
            return false;
        }

        $precio = (float) $reserva['precio_acordado'];

        if ($precio <= 0 || self::tieneIngresoEnCaja((string) $reserva['codigo'])) {
            return false;
        }

        $fecha = $fecha !== null && $fecha !== '' ? $fecha : date('Y-m-d');

        Movimiento::crear([
            'tipo'           => 'ingreso',
            'fecha'          => $fecha,
            'concepto'       => 'Alquiler ' . (string) $reserva['instalacion_nombre']
                . ' · ' . (string) $reserva['codigo'],
            'monto'          => number_format($precio, 2, '.', ''),
            'categoria_id'   => CategoriaMovimiento::idPorNombre('Alquileres', 'ingreso'),
            'medio_pago_id'  => self::enteroANulo($medioPagoId),
            'periodo_id'     => self::periodoDe($fecha),
            'referencia'     => (string) $reserva['codigo'],
            'observaciones'  => 'Ingreso por reserva confirmada. El cobro se anota en la ficha de la reserva.',
            'responsable_id' => $usuarioId,
        ]);

        return true;
    }

    /**
     * Anula el ingreso de caja de una reserva cancelada.
     *
     * No se borra el movimiento original: se registra un egreso de reversa
     * para que la caja no sume un alquiler que ya no existe.
     */
    public static function revertirIngresoEnCaja(int $reservaId, ?int $usuarioId = null): bool
    {
        $sql = sprintf(
            'SELECT r.`codigo`, r.`precio_acordado`, i.`nombre` AS instalacion_nombre
             FROM `%s` r
             LEFT JOIN `%s` i ON i.`id` = r.`instalacion_id`
             WHERE r.`id` = :id
             LIMIT 1',
            self::table(),
            Instalacion::table()
        );

        $statement = self::db()->prepare($sql);
        $statement->execute(['id' => $reservaId]);
        $reserva = $statement->fetch();

        if (!is_array($reserva)) {
            return false;
        }

        $precio = (float) $reserva['precio_acordado'];

        if ($precio <= 0 || !self::tieneIngresoEnCaja((string) $reserva['codigo'])) {
            return false;
        }

        if (self::tieneReversaEnCaja((string) $reserva['codigo'])) {
            return false;
        }

        Movimiento::crear([
            'tipo'           => 'egreso',
            'fecha'          => date('Y-m-d'),
            'concepto'       => 'Anulación de reserva ' . (string) $reserva['instalacion_nombre']
                . ' · ' . (string) $reserva['codigo'],
            'monto'          => number_format($precio, 2, '.', ''),
            'categoria_id'   => CategoriaMovimiento::idPorNombre('Alquileres', 'ingreso'),
            'medio_pago_id'  => null,
            'periodo_id'     => self::periodoDe(date('Y-m-d')),
            'referencia'     => (string) $reserva['codigo'],
            'observaciones'  => 'Reversa del ingreso por la cancelación de la reserva.',
            'responsable_id' => $usuarioId,
        ]);

        return true;
    }

    /**
     * ¿La reserva ya tiene su ingreso anotado en caja?
     */
    public static function tieneIngresoEnCaja(string $codigo): bool
    {
        return (int) self::scalar(
            sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE `referencia` = :referencia AND `tipo` = \'ingreso\'',
                Movimiento::table()
            ),
            ['referencia' => $codigo]
        ) > 0;
    }

    /**
     * ¿La reserva ya tiene la reversa de caja por cancelación?
     */
    public static function tieneReversaEnCaja(string $codigo): bool
    {
        return (int) self::scalar(
            sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE `referencia` = :referencia AND `tipo` = \'egreso\'',
                Movimiento::table()
            ),
            ['referencia' => $codigo]
        ) > 0;
    }

    /**
     * Identificador del movimiento de ingreso de la reserva (0 si no hay).
     */
    public static function movimientoIngresoId(string $codigo): int
    {
        $valor = self::scalar(
            sprintf(
                'SELECT `id` FROM `%s` WHERE `referencia` = :referencia AND `tipo` = \'ingreso\' ORDER BY `id` DESC LIMIT 1',
                Movimiento::table()
            ),
            ['referencia' => $codigo]
        );

        return $valor === false || $valor === null ? 0 : (int) $valor;
    }

    /**
     * Siguiente código interno de reserva.
     */
    public static function siguienteCodigo(): string
    {
        $anio = date('Y');
        $total = (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `codigo` LIKE :prefijo', self::table()),
            ['prefijo' => 'RES-' . $anio . '%']
        );

        return sprintf('RES-%s-%04d', $anio, $total + 1);
    }

    public static function contar(): int
    {
        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::table()));
    }

    /**
     * Reservas futuras activas de un teléfono (protección ante el bloqueo
     * abusivo de horarios desde el portal público).
     */
    public static function contarActivasPorTelefono(string $telefono): int
    {
        $telefono = trim($telefono);

        if ($telefono === '') {
            return 0;
        }

        return (int) self::scalar(
            sprintf(
                'SELECT COUNT(*) FROM `%s`
                 WHERE `solicitante_telefono` = :telefono
                   AND `estado` IN (\'pendiente\', \'confirmada\')
                   AND `fecha_evento` >= CURDATE()',
                self::table()
            ),
            ['telefono' => $telefono]
        );
    }

    /**
     * Bloquea las filas del horario indicado y avisa si está ocupado.
     *
     * @throws RuntimeException cuando hay una reserva que se superpone.
     */
    private static function bloquearHorario(
        int $instalacionId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $exceptoId = null
    ): void {
        $inicio = self::instanteInicio($fecha, $horaInicio);
        $fin = self::instanteFin($fecha, $horaInicio, $horaFin);

        $sql = sprintf(
            'SELECT `id` FROM `%s`
             WHERE `instalacion_id` = :instalacion_id
               AND `estado` IN (\'pendiente\', \'confirmada\')
               AND TIMESTAMP(`fecha_evento`, `hora_inicio`) < :fin
               AND CASE
                   WHEN `hora_fin` <= `hora_inicio`
                       THEN TIMESTAMP(DATE_ADD(`fecha_evento`, INTERVAL 1 DAY), `hora_fin`)
                   ELSE TIMESTAMP(`fecha_evento`, `hora_fin`)
               END > :inicio
             FOR UPDATE',
            self::table()
        );

        $params = [
            'instalacion_id' => $instalacionId,
            'inicio' => $inicio,
            'fin'    => $fin,
        ];

        if ($exceptoId !== null) {
            $sql .= ' AND `id` <> :excepto';
            $params['excepto'] = $exceptoId;
        }

        $statement = self::db()->prepare($sql);
        $statement->execute($params);

        if ($statement->fetchAll() !== []) {
            throw new RuntimeException('Ese horario ya está reservado. Revise la agenda de la instalación.');
        }
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function condiciones(array $filtros, string $alias): array
    {
        $condiciones = [];
        $params = [];

        $busqueda = trim((string) ($filtros['q'] ?? ''));

        if ($busqueda !== '') {
            $condiciones[] = sprintf(
                '(%s.`codigo` LIKE :q1 OR %s.`solicitante_nombre` LIKE :q2)',
                $alias,
                $alias
            );
            $params['q1'] = '%' . $busqueda . '%';
            $params['q2'] = '%' . $busqueda . '%';
        }

        $estado = (string) ($filtros['estado'] ?? '');

        if (isset(self::ESTADOS[$estado])) {
            $condiciones[] = sprintf('%s.`estado` = :estado', $alias);
            $params['estado'] = $estado;
        }

        $instalacionId = (int) ($filtros['instalacion_id'] ?? 0);

        if ($instalacionId > 0) {
            $condiciones[] = sprintf('%s.`instalacion_id` = :instalacion_id', $alias);
            $params['instalacion_id'] = $instalacionId;
        }

        $desde = (string) ($filtros['desde'] ?? '');

        if ($desde !== '') {
            $condiciones[] = sprintf('%s.`fecha_evento` >= :desde', $alias);
            $params['desde'] = $desde;
        }

        $hasta = (string) ($filtros['hasta'] ?? '');

        if ($hasta !== '') {
            $condiciones[] = sprintf('%s.`fecha_evento` <= :hasta', $alias);
            $params['hasta'] = $hasta;
        }

        if ($condiciones === []) {
            return ['', $params];
        }

        return ['WHERE ' . implode(' AND ', $condiciones), $params];
    }

    private static function periodoDe(string $fecha): int
    {
        $periodo = Periodo::porFecha($fecha);

        return $periodo === null ? 0 : (int) $periodo['id'];
    }

    private static function normalizarHora(string $hora): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $hora) === 1) {
            return $hora . ':00';
        }

        return $hora;
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
