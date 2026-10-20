<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Configuracion;
use App\Models\Instalacion;
use App\Models\MedioPago;
use App\Models\Reserva;
use App\Models\ReservaPago;
use App\Models\Socio;
use App\Models\Tarifa;
use App\Models\TipoEvento;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Reservas de los ambientes del club.
 *
 * Una reserva tiene tarifa, precio acordado, adelanto y pagos registrados.
 * Los pagos se anotan también en caja, en un solo movimiento por pago.
 *
 * El solapamiento de horarios se comprueba dentro de una transacción con
 * bloqueo de filas, de modo que dos reservas simultáneas no ocupen la misma
 * franja.
 *
 * Los permisos (`alquileres.ver`, `alquileres.gestionar`, `alquileres.cancelar`
 * y `caja.registrar`) se comprueban en app/routes.php.
 */
final class ReservasController
{
    private const POR_PAGINA = 20;

    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $filtros = $this->filtros($request);
        $total = Reserva::total($filtros);
        $pagina = max(1, $request->integer('pagina', 1));

        View::display('reservas/index', [
            'titulo'    => 'Reservas',
            'reservas'  => Reserva::listar(
                $filtros,
                self::POR_PAGINA,
                ($pagina - 1) * self::POR_PAGINA
            ),
            'instalaciones' => Instalacion::activas(),
            'estados'   => Reserva::ESTADOS,
            'filtros'   => $filtros,
            'total'     => $total,
            'pagina'    => $pagina,
            'paginas'   => max(1, (int) ceil($total / self::POR_PAGINA)),
            'pendientes' => Reserva::contarProximas(),
        ]);
    }

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $instalaciones = Instalacion::activas();

        View::display('reservas/form', [
            'titulo'        => 'Nueva reserva',
            'reserva'       => null,
            'modo'          => 'crear',
            'instalaciones' => $instalaciones,
            'tiposEvento'   => TipoEvento::activos(),
            'tarifas'       => $this->tarifasPorInstalacion($instalaciones),
            'estados'       => Reserva::ESTADOS_EDITABLES,
            'porcentajeAdelanto' => $this->porcentajeAdelanto(),
            'medios'        => MedioPago::activos(),
        ]);
    }

    public function crear(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $solicitudId = (int) ($request->integer('solicitud_id'));

        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);

            if ($solicitudId > 0) {
                Response::redirect(url('/solicitudes/' . $solicitudId . '/reservar'));
            }

            Response::redirect(url('/reservas/nuevo'));
        }

        $codigoReserva = Reserva::siguienteCodigo();

        try {
            $id = Reserva::crearReservando([
                'codigo'                => $codigoReserva,
                'instalacion_id'        => (int) $limpio['instalacion_id'],
                'tarifa_id'             => (int) ($limpio['tarifa_id'] ?? 0),
                'tipo_evento_id'        => (int) ($limpio['tipo_evento_id'] ?? 0),
                'socio_id'              => $this->socioId((string) ($limpio['socio_codigo'] ?? '')),
                'solicitante_nombre'    => (string) $limpio['solicitante_nombre'],
                'solicitante_documento' => (string) ($limpio['solicitante_documento'] ?? ''),
                'solicitante_telefono'  => (string) ($limpio['solicitante_telefono'] ?? ''),
                'solicitante_email'     => (string) ($limpio['solicitante_email'] ?? ''),
                'fecha_evento'          => (string) $limpio['fecha_evento'],
                'hora_inicio'           => (string) $limpio['hora_inicio'],
                'hora_fin'              => (string) $limpio['hora_fin'],
                'precio_acordado'       => (float) $limpio['precio_acordado'],
                'monto_adelanto'        => (float) $limpio['monto_adelanto'],
                'medio_pago_id'         => (int) ($limpio['medio_pago_id'] ?? 0),
                'estado'                => (string) $limpio['estado'],
                'condiciones'           => (string) ($limpio['condiciones'] ?? ''),
                'observaciones'         => (string) ($limpio['observaciones'] ?? ''),
                'registrado_por'        => (int) Auth::id(),
            ]);
        } catch (RuntimeException $exception) {
            remember_old_input($datos);
            Session::flash('error', $exception->getMessage());

            if ($solicitudId > 0) {
                Response::redirect(url('/solicitudes/' . $solicitudId . '/reservar'));
            }

            Response::redirect(url('/reservas/nuevo'));
        } catch (Throwable $exception) {
            Logger::error('Error al crear una reserva', ['error' => $exception->getMessage()]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo crear la reserva. Intente nuevamente.');

            if ($solicitudId > 0) {
                Response::redirect(url('/solicitudes/' . $solicitudId . '/reservar'));
            }

            Response::redirect(url('/reservas/nuevo'));
        }

        Audit::log(Auth::id(), 'crear_reserva', 'reserva', $id, 'Reserva creada: ' . (string) $limpio['solicitante_nombre']);

        if ($solicitudId > 0) {
            \App\Models\SolicitudAlquiler::actualizarEstado(
                $solicitudId,
                'atendida',
                (int) Auth::id(),
                null,
                'Convertida en la reserva ' . $codigoReserva . '.'
            );

            Audit::log(
                Auth::id(),
                'convertir_solicitud_reserva',
                'solicitud_alquiler',
                $solicitudId,
                'Solicitud convertida en la reserva ' . $codigoReserva
            );

            Session::flash('exito', 'La reserva ' . $codigoReserva . ' quedó registrada y la solicitud se marcó como atendida.');
            Response::redirect(url('/reservas/' . $id));
        }

        Session::flash('exito', 'La reserva quedó registrada.');
        Response::redirect(url('/reservas/' . $id));
    }

    public function ver(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $reserva = $this->obtener((int) $id);

        View::display('reservas/ver', [
            'titulo'        => 'Reserva ' . (string) $reserva['codigo'],
            'reserva'       => $reserva,
            'pagos'         => ReservaPago::listarPorReserva((int) $reserva['id']),
            'medios'        => MedioPago::activos(),
            'estados'       => Reserva::ESTADOS,
            'ingresoEnCaja' => Reserva::tieneIngresoEnCaja((string) $reserva['codigo']),
        ]);
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $reserva = $this->obtener((int) $id);

        if (in_array((string) $reserva['estado'], ['cancelada', 'finalizada'], true)) {
            Session::flash('error', 'Una reserva cancelada o finalizada no se puede editar.');
            Response::redirect(url('/reservas/' . (int) $reserva['id']));
        }

        $instalaciones = Instalacion::activas();

        View::display('reservas/form', [
            'titulo'        => 'Editar reserva',
            'reserva'       => $reserva,
            'modo'          => 'editar',
            'instalaciones' => $instalaciones,
            'tiposEvento'   => TipoEvento::activos(),
            'tarifas'       => $this->tarifasPorInstalacion($instalaciones),
            'estados'       => Reserva::ESTADOS_EDITABLES,
            'porcentajeAdelanto' => $this->porcentajeAdelanto(),
            'medios'        => MedioPago::activos(),
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $reserva = $this->obtener((int) $id);
        $reservaId = (int) $reserva['id'];

        if (in_array((string) $reserva['estado'], ['cancelada', 'finalizada'], true)) {
            Session::flash('error', 'Una reserva cancelada o finalizada no se puede editar.');
            Response::redirect(url('/reservas/' . $reservaId));
        }

        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/reservas/' . $reservaId . '/editar'));
        }

        try {
            Reserva::actualizarReserva($reservaId, [
                'instalacion_id'        => (int) $limpio['instalacion_id'],
                'tarifa_id'             => (int) ($limpio['tarifa_id'] ?? 0),
                'tipo_evento_id'        => (int) ($limpio['tipo_evento_id'] ?? 0),
                'solicitante_nombre'    => (string) $limpio['solicitante_nombre'],
                'solicitante_documento' => (string) ($limpio['solicitante_documento'] ?? ''),
                'solicitante_telefono'  => (string) ($limpio['solicitante_telefono'] ?? ''),
                'solicitante_email'     => (string) ($limpio['solicitante_email'] ?? ''),
                'fecha_evento'          => (string) $limpio['fecha_evento'],
                'hora_inicio'           => (string) $limpio['hora_inicio'],
                'hora_fin'              => (string) $limpio['hora_fin'],
                'precio_acordado'       => (float) $limpio['precio_acordado'],
                'monto_adelanto'        => (float) $limpio['monto_adelanto'],
                'condiciones'           => (string) ($limpio['condiciones'] ?? ''),
                'observaciones'         => (string) ($limpio['observaciones'] ?? ''),
            ]);
        } catch (RuntimeException $exception) {
            remember_old_input($datos);
            Session::flash('error', $exception->getMessage());
            Response::redirect(url('/reservas/' . $reservaId . '/editar'));
        } catch (Throwable $exception) {
            Logger::error('Error al actualizar una reserva', [
                'reserva_id' => $reservaId,
                'error'      => $exception->getMessage(),
            ]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo guardar la reserva.');
            Response::redirect(url('/reservas/' . $reservaId . '/editar'));
        }

        Audit::log(Auth::id(), 'editar_reserva', 'reserva', $reservaId, 'Reserva actualizada: ' . (string) $reserva['codigo']);

        Session::flash('exito', 'La reserva se actualizó.');
        Response::redirect(url('/reservas/' . $reservaId));
    }

    public function cambiarEstado(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $reserva = $this->obtener((int) $id);
        $estado = $request->string('estado');

        if (!isset(Reserva::ESTADOS_EDITABLES[$estado])) {
            Session::flash('error', 'El estado indicado no es válido.');
            Response::redirect(url('/reservas/' . (int) $reserva['id']));
        }

        if ((string) $reserva['estado'] === 'cancelada') {
            Session::flash('error', 'Una reserva cancelada no cambia de estado.');
            Response::redirect(url('/reservas/' . (int) $reserva['id']));
        }

        Reserva::cambiarEstado((int) $reserva['id'], $estado);

        // Aviso por correo cuando la reserva queda confirmada.
        if ($estado === 'confirmada') {
            $this->notificarReserva((int) $reserva['id']);
        }

        Audit::log(
            Auth::id(),
            'cambiar_estado_reserva',
            'reserva',
            (int) $reserva['id'],
            'Reserva ' . (string) $reserva['codigo'] . ' pasó a ' . Reserva::ESTADOS[$estado]
        );

        Session::flash('exito', 'El estado de la reserva se actualizó.');
        Response::redirect(url('/reservas/' . (int) $reserva['id']));
    }

    public function cancelar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $reserva = $this->obtener((int) $id);

        if ((string) $reserva['estado'] === 'cancelada') {
            Session::flash('error', 'La reserva ya estaba cancelada.');
            Response::redirect(url('/reservas/' . (int) $reserva['id']));
        }

        $motivo = trim($request->string('motivo'));

        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 300) {
            remember_old_input($request->only(['motivo']));
            Session::flash('errores', ['El motivo de la cancelación debe tener entre 5 y 300 caracteres.']);
            Response::redirect(url('/reservas/' . (int) $reserva['id']));
        }

        Reserva::cambiarEstado((int) $reserva['id'], 'cancelada', (int) Auth::id(), $motivo);

        Audit::log(
            Auth::id(),
            'cancelar_reserva',
            'reserva',
            (int) $reserva['id'],
            'Reserva cancelada: ' . (string) $reserva['codigo']
        );

        Session::flash('exito', 'La reserva quedó cancelada. El registro se conserva.');
        Response::redirect(url('/reservas/' . (int) $reserva['id']));
    }

    public function registrarPago(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $reserva = $this->obtener((int) $id);
        $reservaId = (int) $reserva['id'];

        $datos = $request->only(['tipo', 'fecha', 'monto', 'medio_pago_id', 'observaciones']);
        $validador = Validator::make($datos, [
            'tipo'          => 'required|in:adelanto,saldo,devolucion',
            'fecha'         => 'required|date',
            'monto'         => 'required|decimal:2',
            'medio_pago_id' => 'nullable|integer|exists:medios_pago,id',
            'observaciones' => 'nullable|string|max:300',
        ], [
            'tipo'          => 'tipo de pago',
            'fecha'         => 'fecha',
            'monto'         => 'monto',
            'medio_pago_id' => 'medio de pago',
            'observaciones' => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            if ((float) $limpio['monto'] <= 0) {
                $errores[] = 'El monto del pago debe ser mayor que cero.';
            }

            if ((string) $limpio['fecha'] > date('Y-m-d')) {
                $errores[] = 'No se pueden registrar pagos con fecha futura.';
            }
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/reservas/' . $reservaId));
        }

        try {
            Reserva::registrarPago($reservaId, [
                'tipo'          => (string) $limpio['tipo'],
                'fecha'         => (string) $limpio['fecha'],
                'monto'         => (float) $limpio['monto'],
                'medio_pago_id' => (int) ($limpio['medio_pago_id'] ?? 0),
                'observaciones' => (string) ($limpio['observaciones'] ?? ''),
                'socio_id'      => (int) ($reserva['socio_id'] ?? 0),
            ], (int) Auth::id());
        } catch (RuntimeException $exception) {
            remember_old_input($datos);
            Session::flash('error', $exception->getMessage());
            Response::redirect(url('/reservas/' . $reservaId));
        } catch (Throwable $exception) {
            Logger::error('Error al registrar un pago de reserva', [
                'reserva_id' => $reservaId,
                'error'      => $exception->getMessage(),
            ]);
            Session::flash('error', 'No se pudo registrar el pago.');
            Response::redirect(url('/reservas/' . $reservaId));
        }

        Audit::log(
            Auth::id(),
            'registrar_pago_reserva',
            'reserva',
            $reservaId,
            'Pago registrado en la reserva ' . (string) $reserva['codigo'] . ': ' . dinero((float) $limpio['monto'])
        );

        Session::flash('exito', 'El pago quedó registrado y anotado en caja.');
        Response::redirect(url('/reservas/' . $reservaId));
    }

    /**
     * Convierte una solicitud del portal en reserva.
     */
    public function desdeSolicitud(Request $request, string $solicitudId): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $solicitud = \App\Models\SolicitudAlquiler::ver((int) $solicitudId);

        if ($solicitud === null) {
            abort(404, 'La solicitud indicada no existe.');
        }

        // Al abrir la conversión, la solicitud pasa a «en revisión»: el
        // personal ya la está atendiendo.
        if ((string) $solicitud['estado'] === 'pendiente') {
            \App\Models\SolicitudAlquiler::actualizarEstado(
                (int) $solicitud['id'],
                'en_revision',
                (int) Auth::id(),
                null,
                'En proceso de conversión a reserva.'
            );
        }

        $tarifa = Tarifa::vigentePara(
            (int) $solicitud['instalacion_id'],
            $solicitud['tipo_evento_id'] === null ? null : (int) $solicitud['tipo_evento_id']
        );

        $precio = $tarifa === null ? 0.0 : (float) $tarifa['monto'];
        $adelanto = round($precio * ($this->porcentajeAdelanto() / 100), 2);

        View::display('reservas/form', [
            'titulo'        => 'Reserva desde ' . (string) $solicitud['codigo'],
            'reserva'       => [
                'id'                    => null,
                'codigo'                => Reserva::siguienteCodigo(),
                'instalacion_id'        => (int) $solicitud['instalacion_id'],
                'tarifa_id'             => $tarifa === null ? 0 : (int) $tarifa['id'],
                'tipo_evento_id'        => $solicitud['tipo_evento_id'] === null ? 0 : (int) $solicitud['tipo_evento_id'],
                'solicitante_nombre'    => (string) $solicitud['solicitante_nombre'],
                'solicitante_documento' => (string) ($solicitud['solicitante_documento'] ?? ''),
                'solicitante_telefono'  => (string) $solicitud['solicitante_telefono'],
                'solicitante_email'     => (string) ($solicitud['solicitante_email'] ?? ''),
                'fecha_evento'          => (string) $solicitud['fecha_evento'],
                'hora_inicio'           => substr((string) $solicitud['hora_inicio'], 0, 5),
                'hora_fin'              => substr((string) $solicitud['hora_fin'], 0, 5),
                'precio_acordado'       => number_format($precio, 2, '.', ''),
                'monto_adelanto'        => number_format($adelanto, 2, '.', ''),
                'estado'                => 'pendiente',
                'condiciones'           => '',
                'observaciones'         => 'Reserva generada desde la solicitud ' . (string) $solicitud['codigo'] . '.',
            ],
            'modo'          => 'crear',
            'solicitudId'   => (int) $solicitud['id'],
            'instalaciones' => Instalacion::activas(),
            'tiposEvento'   => TipoEvento::activos(),
            'tarifas'       => $this->tarifasPorInstalacion(Instalacion::activas()),
            'estados'       => Reserva::ESTADOS_EDITABLES,
            'porcentajeAdelanto' => $this->porcentajeAdelanto(),
            'medios'        => MedioPago::activos(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Request $request): array
    {
        $estado = $request->string('estado');
        $estado = isset(Reserva::ESTADOS[$estado]) ? $estado : '';

        $instalacionId = $request->integer('instalacion_id', 0);

        $desde = $request->string('desde');
        $desde = $this->fechaValida($desde) ? $desde : '';

        $hasta = $request->string('hasta');
        $hasta = $this->fechaValida($hasta) ? $hasta : '';

        return [
            'q'              => $request->string('q'),
            'estado'         => $estado,
            'instalacion_id' => $instalacionId > 0 ? $instalacionId : 0,
            'desde'          => $desde,
            'hasta'          => $hasta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        return $request->only([
            'instalacion_id',
            'tarifa_id',
            'tipo_evento_id',
            'solicitante_nombre',
            'solicitante_documento',
            'solicitante_telefono',
            'solicitante_email',
            'socio_codigo',
            'fecha_evento',
            'hora_inicio',
            'hora_fin',
            'precio_acordado',
            'medio_pago_id',
            'monto_adelanto',
            'estado',
            'condiciones',
            'observaciones',
            'solicitud_id',
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validar(array $datos): array
    {
        $validador = Validator::make($datos, [
            'instalacion_id'        => 'required|integer|exists:instalaciones,id',
            'tarifa_id'             => 'nullable|integer|exists:tarifas,id',
            'tipo_evento_id'        => 'nullable|integer|exists:tipos_evento,id',
            'solicitante_nombre'    => 'required|string|min:3|max:180',
            'solicitante_documento' => 'nullable|string|max:30',
            'solicitante_telefono'  => 'required|string|max:30',
            'solicitante_email'     => 'nullable|email|max:160',
            'socio_codigo'          => 'nullable|string|max:30',
            'fecha_evento'          => 'required|date',
            'hora_inicio'           => 'required|regex:/^\d{2}:\d{2}$/',
            'hora_fin'              => 'required|regex:/^\d{2}:\d{2}$/',
            'precio_acordado'       => 'required|decimal:2',
            'medio_pago_id'         => 'nullable|integer|exists:medios_pago,id',
            'monto_adelanto'        => 'required|decimal:2',
            'estado'                => 'required|in:pendiente,confirmada,finalizada',
            'condiciones'           => 'nullable|string|max:2000',
            'observaciones'         => 'nullable|string|max:2000',
        ], [
            'instalacion_id'        => 'sede',
            'tarifa_id'             => 'tarifa',
            'tipo_evento_id'        => 'tipo de uso',
            'solicitante_nombre'    => 'nombre del solicitante',
            'solicitante_documento' => 'documento',
            'solicitante_telefono'  => 'teléfono',
            'solicitante_email'     => 'correo electrónico',
            'socio_codigo'          => 'código de socio',
            'fecha_evento'          => 'fecha del evento',
            'hora_inicio'           => 'hora de inicio',
            'hora_fin'              => 'hora de fin',
            'precio_acordado'       => 'precio acordado',
            'monto_adelanto'        => 'adelanto',
            'estado'                => 'estado',
            'condiciones'           => 'condiciones',
            'observaciones'         => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $inicio = (string) $limpio['hora_inicio'];
            $fin = (string) $limpio['hora_fin'];

            if (!$this->horaValida($inicio) || !$this->horaValida($fin)) {
                $errores[] = 'Las horas indicadas no son válidas.';
            }

            $precio = (float) $limpio['precio_acordado'];
            $adelanto = (float) $limpio['monto_adelanto'];

            if ($precio < 0) {
                $errores[] = 'El precio acordado no puede ser negativo.';
            }

            if ($adelanto < 0) {
                $errores[] = 'El adelanto no puede ser negativo.';
            }

            if ($adelanto > $precio) {
                $errores[] = 'El adelanto no puede ser mayor que el precio acordado.';
            }

            $codigo = trim((string) ($limpio['socio_codigo'] ?? ''));

            if ($codigo !== '' && Socio::findPorCodigo($codigo) === null) {
                $errores[] = 'No existe un socio con ese código.';
            }
        }

        return [$limpio, $errores];
    }

    /**
     * Tarifas agrupadas por instalación, para el formulario.
     *
     * @param array<int, array<string, mixed>> $instalaciones
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function tarifasPorInstalacion(array $instalaciones): array
    {
        $tarifas = [];

        foreach ($instalaciones as $instalacion) {
            $tarifas[(int) $instalacion['id']] = Tarifa::vigentesDeInstalacion((int) $instalacion['id']);
        }

        return $tarifas;
    }

    private function porcentajeAdelanto(): float
    {
        $valor = (float) Configuracion::valor('porcentaje_adelanto', '50');

        if ($valor < 0 || $valor > 100) {
            return 50.0;
        }

        return $valor;
    }

    private function socioId(string $codigo): int
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return 0;
        }

        $socio = Socio::findPorCodigo($codigo);

        return $socio === null ? 0 : (int) $socio['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function obtener(int $id): array
    {
        $reserva = Reserva::find($id);

        if ($reserva === null) {
            abort(404, 'La reserva indicada no existe.');
        }

        return $reserva;
    }

    private function fechaValida(string $fecha): bool
    {
        if ($fecha === '') {
            return false;
        }

        $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $valor instanceof DateTimeImmutable && $valor->format('Y-m-d') === $fecha;
    }

    private function horaValida(string $hora): bool
    {
        $valor = DateTimeImmutable::createFromFormat('!H:i', $hora);

        return $valor instanceof DateTimeImmutable && $valor->format('H:i') === $hora;
    }

    /**
     * Envia el aviso por correo de la reserva confirmada.
     */
    private function notificarReserva(int $reservaId): void
    {
        try {
            $reserva = Reserva::find($reservaId);

            if ($reserva !== null) {
                \App\Notifications\ReservaConfirmada::enviar($reserva);
            }
        } catch (\Throwable $exception) {
            Logger::error('No se pudo enviar el aviso de la reserva', [
                'reserva_id' => $reservaId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function baseDisponible(): bool
    {
        if (Database::isAvailable()) {
            return true;
        }

        Session::flash('error', 'La base de datos no está disponible en este momento.');
        Response::redirect(url('/panel'));
    }
}
