<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | CSDUV3 - Diagnóstico de solicitudes y reservas
 |--------------------------------------------------------------------------
 |
 | Revisa el estado real de las solicitudes de alquiler y de las reservas:
 | si la conversión a reserva funciona, si el estado de la reserva se puede
 | cambiar al editarla y si el alquiler se refleja en la caja.
 |
 | Las pruebas crean datos con la marca «DIAG-» y al terminar los borran: la
 | base de datos queda exactamente como estaba.
 |
 | Uso (solo desde la Terminal / SSH):
 |     php8.3 tools/diagnostico_reservas.php
 */

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(404);
    }

    exit('No disponible.');
}

$appPath = dirname(__DIR__);

require $appPath . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();
\App\Core\Env::load($appPath . '/.env');
\App\Core\Config::load($appPath . '/config');

use App\Core\Config;
use App\Core\Database;
use App\Models\Reserva;
use App\Models\SolicitudAlquiler;

function Informar(string $mensaje = ''): void
{
    fwrite(STDOUT, $mensaje . PHP_EOL);
}

function Titulo(string $texto): void
{
    Informar();
    Informar('== ' . $texto . ' ' . str_repeat('=', max(0, 62 - strlen($texto))));
}

$pdo = Database::connection();
$prefijo = (string) Config::get('database.prefix', 'csduv3_');

Informar('CSDUV3 · diagnóstico de solicitudes y reservas');
Informar('PHP ' . PHP_VERSION . ' · ' . date('d/m/Y H:i:s'));

/* -------------------------------------------------------------------------
 | 1. Solicitudes de alquiler
 | ---------------------------------------------------------------------- */

Titulo('Solicitudes de alquiler');

$solicitudes = $pdo->query(
    'SELECT `id`, `codigo`, `estado`, `fecha_evento`, `hora_inicio`, `hora_fin`, `nota_interna`
     FROM `' . $prefijo . 'solicitudes_alquiler`
     ORDER BY `id` DESC
     LIMIT 20'
)->fetchAll(PDO::FETCH_ASSOC);

if ($solicitudes === []) {
    Informar('No hay solicitudes registradas.');
} else {
    foreach ($solicitudes as $solicitud) {
        Informar(sprintf(
            '  #%s  %s  %-12s  %s %s-%s',
            $solicitud['id'],
            $solicitud['codigo'],
            $solicitud['estado'],
            $solicitud['fecha_evento'],
            substr((string) $solicitud['hora_inicio'], 0, 5),
            substr((string) $solicitud['hora_fin'], 0, 5)
        ));

        if ((string) $solicitud['nota_interna'] !== '') {
            Informar('        nota: ' . $solicitud['nota_interna']);
        }
    }
}

/* -------------------------------------------------------------------------
 | 2. Reservas
 | ---------------------------------------------------------------------- */

Titulo('Reservas');

$reservas = $pdo->query(
    'SELECT `id`, `codigo`, `estado`, `precio_acordado`, `monto_pagado`
     FROM `' . $prefijo . 'reservas`
     ORDER BY `id` DESC
     LIMIT 20'
)->fetchAll(PDO::FETCH_ASSOC);

if ($reservas === []) {
    Informar('No hay reservas registradas.');
} else {
    foreach ($reservas as $reserva) {
        $ingreso = Reserva::tieneIngresoEnCaja((string) $reserva['codigo'])
            ? 'con ingreso en caja'
            : 'sin ingreso en caja';

        Informar(sprintf(
            '  #%s  %s  %-11s  precio %9s  pagado %9s  %s',
            $reserva['id'],
            $reserva['codigo'],
            $reserva['estado'],
            $reserva['precio_acordado'],
            $reserva['monto_pagado'],
            $ingreso
        ));
    }
}

/* -------------------------------------------------------------------------
 | 3. Prueba de la conversión
 | ---------------------------------------------------------------------- */

Titulo('Prueba de conversión (los datos de prueba se borran al final)');

$codigoPrueba = 'DIAG-' . date('Ymd-His');
$reservaPruebaId = 0;
$solicitudOriginal = null;

try {
    $solicitud = $pdo->query(
        'SELECT * FROM `' . $prefijo . 'solicitudes_alquiler`
         WHERE `estado` IN (\'pendiente\', \'en_revision\')
         ORDER BY `id` DESC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);

    if (!is_array($solicitud)) {
        Informar('No hay solicitudes pendientes o en revisión: no se prueba la conversión.');
    } else {
        $solicitudOriginal = $solicitud;

        Informar('Solicitud de prueba: ' . $solicitud['codigo'] . ' (estado ' . $solicitud['estado'] . ')');

        $reservaPruebaId = Reserva::crearReservando([
            'codigo'                => $codigoPrueba,
            'instalacion_id'        => (int) $solicitud['instalacion_id'],
            'tarifa_id'             => 0,
            'tipo_evento_id'        => (int) ($solicitud['tipo_evento_id'] ?? 0),
            'solicitante_nombre'    => (string) $solicitud['solicitante_nombre'],
            'solicitante_documento' => (string) ($solicitud['solicitante_documento'] ?? ''),
            'solicitante_telefono'  => (string) ($solicitud['solicitante_telefono'] ?? ''),
            'solicitante_email'     => (string) ($solicitud['solicitante_email'] ?? ''),
            'fecha_evento'          => (string) $solicitud['fecha_evento'],
            'hora_inicio'           => (string) $solicitud['hora_inicio'],
            'hora_fin'              => (string) $solicitud['hora_fin'],
            'precio_acordado'       => 0,
            'monto_adelanto'        => 0,
            'medio_pago_id'         => 0,
            'estado'                => 'pendiente',
            'condiciones'           => '',
            'observaciones'         => 'Prueba de diagnóstico.',
            'registrado_por'        => null,
        ]);

        Informar('  Reserva de prueba creada (#' . $reservaPruebaId . ')');

        SolicitudAlquiler::actualizarEstado(
            (int) $solicitud['id'],
            'atendida',
            null,
            null,
            'Prueba de diagnóstico.'
        );

        $estado = $pdo->query(
            'SELECT `estado` FROM `' . $prefijo . 'solicitudes_alquiler` WHERE `id` = ' . (int) $solicitud['id']
        )->fetchColumn();

        Informar('  Estado de la solicitud tras convertir: ' . (string) $estado);

        if ((string) $estado !== 'atendida') {
            Informar('  FALLO: la solicitud no quedó en atendida.');
        } else {
            Informar('  OK: la conversión marca la solicitud como atendida.');
        }
    }
} catch (Throwable $exception) {
    Informar('  ERROR al convertir: ' . $exception->getMessage());
}

/* -------------------------------------------------------------------------
 | 4. Prueba del cambio de estado al editar una reserva
 | ---------------------------------------------------------------------- */

Titulo('Prueba de edición de una reserva (los datos se borran al final)');

try {
    $reserva = $pdo->query(
        'SELECT * FROM `' . $prefijo . 'reservas` ORDER BY `id` DESC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);

    if (!is_array($reserva)) {
        Informar('No hay reservas registradas: no se prueba la edición.');
    } else {
        Informar('Reserva de prueba: ' . $reserva['codigo'] . ' (estado ' . $reserva['estado'] . ')');

        $estadoOriginal = (string) $reserva['estado'];
        $estadoNuevo = $estadoOriginal === 'confirmada' ? 'pendiente' : 'confirmada';

        Reserva::actualizarReserva((int) $reserva['id'], [
            'instalacion_id'        => (int) $reserva['instalacion_id'],
            'tarifa_id'             => (int) ($reserva['tarifa_id'] ?? 0),
            'tipo_evento_id'        => (int) ($reserva['tipo_evento_id'] ?? 0),
            'solicitante_nombre'    => (string) $reserva['solicitante_nombre'],
            'solicitante_documento' => (string) ($reserva['solicitante_documento'] ?? ''),
            'solicitante_telefono'  => (string) ($reserva['solicitante_telefono'] ?? ''),
            'solicitante_email'     => (string) ($reserva['solicitante_email'] ?? ''),
            'fecha_evento'          => (string) $reserva['fecha_evento'],
            'hora_inicio'           => (string) $reserva['hora_inicio'],
            'hora_fin'              => (string) $reserva['hora_fin'],
            'precio_acordado'       => (float) $reserva['precio_acordado'],
            'monto_adelanto'        => (float) $reserva['monto_adelanto'],
            'condiciones'           => (string) ($reserva['condiciones'] ?? ''),
            'observaciones'         => (string) ($reserva['observaciones'] ?? ''),
            'estado'                => $estadoNuevo,
            'registrado_por'        => null,
        ]);

        $estado = $pdo->query(
            'SELECT `estado` FROM `' . $prefijo . 'reservas` WHERE `id` = ' . (int) $reserva['id']
        )->fetchColumn();

        Informar('  Estado tras guardar: ' . (string) $estado . ' (se esperaba ' . $estadoNuevo . ')');

        if ((string) $estado !== $estadoNuevo) {
            Informar('  FALLO: el estado no se guardó.');
        } else {
            Informar('  OK: el estado se guarda al editar la reserva.');
        }

        // Se deja la reserva como estaba.
        Reserva::actualizarReserva((int) $reserva['id'], [
            'instalacion_id'        => (int) $reserva['instalacion_id'],
            'tarifa_id'             => (int) ($reserva['tarifa_id'] ?? 0),
            'tipo_evento_id'        => (int) ($reserva['tipo_evento_id'] ?? 0),
            'solicitante_nombre'    => (string) $reserva['solicitante_nombre'],
            'solicitante_documento' => (string) ($reserva['solicitante_documento'] ?? ''),
            'solicitante_telefono'  => (string) ($reserva['solicitante_telefono'] ?? ''),
            'solicitante_email'     => (string) ($reserva['solicitante_email'] ?? ''),
            'fecha_evento'          => (string) $reserva['fecha_evento'],
            'hora_inicio'           => (string) $reserva['hora_inicio'],
            'hora_fin'              => (string) $reserva['hora_fin'],
            'precio_acordado'       => (float) $reserva['precio_acordado'],
            'monto_adelanto'        => (float) $reserva['monto_adelanto'],
            'condiciones'           => (string) ($reserva['condiciones'] ?? ''),
            'observaciones'         => (string) ($reserva['observaciones'] ?? ''),
            'estado'                => $estadoOriginal,
            'registrado_por'        => null,
        ]);

        Informar('  Estado restaurado: ' . (string) $pdo->query(
            'SELECT `estado` FROM `' . $prefijo . 'reservas` WHERE `id` = ' . (int) $reserva['id']
        )->fetchColumn());
    }
} catch (Throwable $exception) {
    Informar('  ERROR al editar: ' . $exception->getMessage());
}

/* -------------------------------------------------------------------------
 | 5. Limpieza de los datos de prueba
 | ---------------------------------------------------------------------- */

Titulo('Limpieza');

try {
    $borrados = $pdo->prepare(
        'DELETE FROM `' . $prefijo . 'movimientos` WHERE `referencia` = :referencia'
    );
    $borrados->execute(['referencia' => $codigoPrueba]);

    Informar('  Movimientos de caja de prueba borrados: ' . $borrados->rowCount());

    if ($reservaPruebaId > 0) {
        $borrar = $pdo->prepare('DELETE FROM `' . $prefijo . 'reservas` WHERE `id` = :id');
        $borrar->execute(['id' => $reservaPruebaId]);

        Informar('  Reserva de prueba borrada: ' . $borrar->rowCount());
    }

    if (is_array($solicitudOriginal)) {
        $restaurar = $pdo->prepare(
            'UPDATE `' . $prefijo . 'solicitudes_alquiler`
             SET `estado` = :estado,
                 `revisado_por` = :revisado_por,
                 `revisado_at` = :revisado_at,
                 `motivo_rechazo` = :motivo,
                 `nota_interna` = :nota
             WHERE `id` = :id'
        );
        $restaurar->execute([
            'estado'        => (string) $solicitudOriginal['estado'],
            'revisado_por'  => $solicitudOriginal['revisado_por'],
            'revisado_at'   => $solicitudOriginal['revisado_at'],
            'motivo'        => $solicitudOriginal['motivo_rechazo'],
            'nota'          => $solicitudOriginal['nota_interna'],
            'id'            => (int) $solicitudOriginal['id'],
        ]);

        Informar('  Solicitud restaurada al estado ' . (string) $solicitudOriginal['estado']);
    }
} catch (Throwable $exception) {
    Informar('  ERROR al limpiar: ' . $exception->getMessage());
}

Informar();
Informar('Fin del diagnóstico.');
