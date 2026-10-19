<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Instalacion;
use App\Models\Reserva;
use App\Models\SolicitudAlquiler;
use App\Models\Tarifa;
use App\Models\TipoEvento;
use DateTimeImmutable;

/**
 * Portal público de alquileres.
 *
 * No requiere sesión. La disponibilidad y el registro de solicitudes se validan
 * siempre en el servidor: el navegador nunca decide si una solicitud es válida.
 *
 * Una solicitud NO es una reserva. Queda en estado «pendiente» hasta que el
 * personal autorizado la revise desde el panel administrativo.
 */
final class PortalAlquilerController
{
    /** Máximo de solicitudes por IP en una hora. */
    private const MAX_SOLICITUDES_POR_HORA = 5;

    public function index(Request $request): void
    {
        if (!$this->portalListo()) {
            return;
        }

        View::display('alquiler/index', [
            'titulo'        => 'Alquiler de ambientes',
            'instalaciones' => Instalacion::activasConTarifas(),
            'tiposEvento'   => TipoEvento::activos(),
        ], 'layouts/publico');
    }

    public function ambiente(Request $request, string $id): void
    {
        if (!$this->portalListo()) {
            return;
        }

        $instalacionId = (int) $id;
        $instalacion = $instalacionId > 0 ? Instalacion::activaPorId($instalacionId) : null;

        if ($instalacion === null) {
            abort(404, 'El ambiente solicitado no existe o no está publicado.');
        }

        $fecha = $this->fechaValida($request->string('fecha')) ?? date('Y-m-d');

        View::display('alquiler/ambiente', [
            'titulo'       => (string) $instalacion['nombre'],
            'instalacion'  => $instalacion,
            'tarifas'      => Tarifa::vigentesPorInstalacion($instalacionId),
            'fecha'        => $fecha,
            'franjas'      => Reserva::franjasOcupadas($instalacionId, $fecha),
            'tiposEvento'  => TipoEvento::activos(),
        ], 'layouts/publico');
    }

    public function disponibilidad(Request $request): void
    {
        if (!$this->portalListo()) {
            return;
        }

        $instalacionId = $request->integer('instalacion_id');
        $fecha = $this->fechaValida($request->string('fecha'));

        $instalacion = null;
        $franjas = [];

        if ($instalacionId > 0 && $fecha !== null) {
            $instalacion = Instalacion::activaPorId($instalacionId);

            if ($instalacion !== null) {
                $franjas = Reserva::franjasOcupadas($instalacionId, $fecha);
            }
        }

        View::display('alquiler/disponibilidad', [
            'titulo'        => 'Disponibilidad',
            'instalaciones' => Instalacion::activas(),
            'instalacion'   => $instalacion,
            'fecha'         => $fecha ?? date('Y-m-d'),
            'franjas'       => $franjas,
            'consultado'    => $instalacion !== null && $fecha !== null,
        ], 'layouts/publico');
    }

    public function formulario(Request $request): void
    {
        if (!$this->portalListo()) {
            return;
        }

        View::display('alquiler/solicitud', [
            'titulo'        => 'Solicitar alquiler',
            'instalaciones' => Instalacion::activasConTarifas(),
            'tiposEvento'   => TipoEvento::activos(),
            'prefill'       => [
                'instalacion_id' => $request->integer('instalacion_id'),
                'fecha_evento'   => $this->fechaValida($request->string('fecha')) ?? '',
                'hora_inicio'    => $this->horaValida($request->string('hora_inicio')) ?? '',
                'hora_fin'       => $this->horaValida($request->string('hora_fin')) ?? '',
            ],
            'marca'         => time(),
        ], 'layouts/publico');
    }

    public function registrar(Request $request): void
    {
        if (!$this->portalListo()) {
            return;
        }

        $destino = url('/alquiler/solicitud');

        // 1. Trampa para robots: es un campo oculto que ninguna persona ve.
        if ($request->string('sitio_web') !== '') {
            Logger::warning('Solicitud pública descartada: campo trampa completado', ['ip' => $request->ip()]);
            Session::flash('error', 'No se pudo procesar la solicitud. Inténtelo nuevamente.');

            Response::redirect($destino);
        }

        // 2. Tiempo mínimo de llenado: un envío instantáneo es un robot.
        $marca = $request->integer('_ts');

        if ($marca <= 0 || (time() - $marca) < 3) {
            Session::flash('error', 'El formulario se envió demasiado rápido. Revise los datos e inténtelo otra vez.');
            Response::redirect($destino);
        }

        // 3. Límite por conexión.
        if (RateLimiter::demasiadasSolicitudes($request->ip(), self::MAX_SOLICITUDES_POR_HORA)) {
            Session::flash('error', 'Ya se registraron varias solicitudes desde esta conexión. Espere unos minutos o comuníquese directamente con el club.');
            Response::redirect($destino);
        }

        $datos = $request->only([
            'instalacion_id',
            'tipo_evento_id',
            'fecha_evento',
            'hora_inicio',
            'hora_fin',
            'solicitante_nombre',
            'solicitante_documento',
            'solicitante_telefono',
            'solicitante_email',
            'cantidad_personas',
            'descripcion',
            'acepta_condiciones',
        ]);

        $validador = Validator::make($datos, [
            'instalacion_id'        => 'required|integer|exists:instalaciones,id',
            'tipo_evento_id'        => 'nullable|integer|exists:tipos_evento,id',
            'fecha_evento'          => 'required|date',
            'hora_inicio'           => 'required|regex:/^\d{2}:\d{2}$/',
            'hora_fin'              => 'required|regex:/^\d{2}:\d{2}$/',
            'solicitante_nombre'    => 'required|string|min:3|max:150',
            'solicitante_documento' => 'nullable|string|max:30',
            'solicitante_telefono'  => 'required|string|min:6|max:30',
            'solicitante_email'     => 'nullable|email|max:160',
            'cantidad_personas'     => 'nullable|integer',
            'descripcion'           => 'nullable|string|max:1000',
            'acepta_condiciones'    => 'required|in:1',
        ], [
            'instalacion_id'        => 'el ambiente',
            'fecha_evento'          => 'la fecha del evento',
            'hora_inicio'           => 'la hora de inicio',
            'hora_fin'              => 'la hora de fin',
            'solicitante_nombre'    => 'el nombre del solicitante',
            'solicitante_telefono'  => 'el teléfono de contacto',
            'solicitante_email'     => 'el correo electrónico',
            'acepta_condiciones'    => 'la aceptación de las condiciones',
        ]);

        if ($validador->fails()) {
            remember_old_input($datos);
            Session::flash('errores', array_values($validador->errors()));

            Response::redirect($destino);
        }

        $limpio = $validador->validated();

        // 4. Comprobaciones de negocio que no dependen del formato.
        $errores = [];

        $instalacion = Instalacion::activaPorId((int) $limpio['instalacion_id']);

        if ($instalacion === null) {
            $errores[] = 'El ambiente seleccionado ya no está disponible.';
        }

        $fecha = (string) $limpio['fecha_evento'];
        $horaInicio = (string) $limpio['hora_inicio'];
        $horaFin = (string) $limpio['hora_fin'];

        if ($fecha < date('Y-m-d')) {
            $errores[] = 'La fecha del evento no puede ser anterior a hoy.';
        }

        if ($horaFin <= $horaInicio) {
            $errores[] = 'La hora de fin debe ser posterior a la hora de inicio.';
        }

        $telefono = (string) $limpio['solicitante_telefono'];

        if (preg_match('/^[0-9+()\s.-]{6,30}$/', $telefono) !== 1) {
            $errores[] = 'El teléfono solo debe contener números, espacios y los signos + - ( ).';
        }

        $personas = $limpio['cantidad_personas'];

        if ($personas !== null && ((int) $personas < 1 || (int) $personas > 1000)) {
            $errores[] = 'La cantidad de personas debe estar entre 1 y 1000.';
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);

            Response::redirect($destino);
        }

        // 5. La disponibilidad se vuelve a comprobar en el servidor.
        if (Reserva::haySolapamiento((int) $limpio['instalacion_id'], $fecha, $horaInicio, $horaFin)) {
            remember_old_input($datos);
            Session::flash('error', 'Ese horario ya está reservado en el ambiente elegido. Seleccione otra hora o fecha.');

            Response::redirect($destino);
        }

        // Si hay otra solicitud pendiente en el mismo horario, no se rechaza:
        // se marca para que el personal la revise con prioridad.
        $conflicto = SolicitudAlquiler::haySolicitudSolapada(
            (int) $limpio['instalacion_id'],
            $fecha,
            $horaInicio,
            $horaFin
        ) ? 1 : 0;

        $codigo = $this->generarCodigo();

        $id = SolicitudAlquiler::crear([
            'codigo'                => $codigo,
            'instalacion_id'        => (int) $limpio['instalacion_id'],
            'tipo_evento_id'        => $limpio['tipo_evento_id'] === null ? null : (int) $limpio['tipo_evento_id'],
            'solicitante_nombre'    => (string) $limpio['solicitante_nombre'],
            'solicitante_documento' => (string) ($limpio['solicitante_documento'] ?? ''),
            'solicitante_telefono'  => $telefono,
            'solicitante_email'     => (string) ($limpio['solicitante_email'] ?? ''),
            'fecha_evento'          => $fecha,
            'hora_inicio'           => $horaInicio,
            'hora_fin'              => $horaFin,
            'cantidad_personas'     => $personas === null ? null : (int) $personas,
            'descripcion'           => (string) ($limpio['descripcion'] ?? ''),
            'conflicto_detectado'   => $conflicto,
            'ip'                    => $request->ip(),
            'user_agent'            => $request->userAgent(),
        ]);

        Audit::log(
            null,
            'solicitud_alquiler_publica',
            'solicitud_alquiler',
            $id,
            'Solicitud ' . $codigo . ' recibida desde el portal público'
        );

        Response::redirect(url('/alquiler/solicitud/recibida/' . $codigo));
    }

    public function recibida(Request $request, string $codigo): void
    {
        if (!$this->portalListo()) {
            return;
        }

        $resumen = SolicitudAlquiler::resumenPorCodigo($codigo);

        if ($resumen === null) {
            abort(404, 'No encontramos ninguna solicitud con ese código.');
        }

        View::display('alquiler/recibida', [
            'titulo'  => 'Solicitud recibida',
            'resumen' => $resumen,
            'codigo'  => $codigo,
        ], 'layouts/publico');
    }

    /**
     * Comprueba que la base de datos esté accesible antes de mostrar el portal.
     */
    private function portalListo(): bool
    {
        if (Database::isAvailable()) {
            return true;
        }

        if (!headers_sent()) {
            http_response_code(503);
        }

        View::display('alquiler/no_disponible', [
            'titulo' => 'Portal en mantenimiento',
        ], 'layouts/publico');

        return false;
    }

    private function fechaValida(string $fecha): ?string
    {
        if ($fecha === '') {
            return null;
        }

        $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return ($objeto instanceof DateTimeImmutable && $objeto->format('Y-m-d') === $fecha) ? $fecha : null;
    }

    private function horaValida(string $hora): ?string
    {
        return preg_match('/^\d{2}:\d{2}$/', $hora) === 1 ? $hora : null;
    }

    private function generarCodigo(): string
    {
        for ($intento = 0; $intento < 5; $intento++) {
            $codigo = 'SOL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

            if (SolicitudAlquiler::resumenPorCodigo($codigo) === null) {
                return $codigo;
            }
        }

        return 'SOL-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }
}
