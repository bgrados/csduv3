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
use App\Models\CategoriaMovimiento;
use App\Models\MedioPago;
use App\Models\Movimiento;
use App\Models\Periodo;
use App\Models\Socio;
use DateTimeImmutable;
use Throwable;

/**
 * Caja del club: ingresos, egresos, catálogos y reportes administrativos.
 *
 * Los movimientos **no se eliminan**: se anulan dejando el motivo y la traza
 * en `csduv3_movimientos_auditoria`. Es un registro financiero administrativo,
 * no contabilidad tributaria.
 *
 * Los permisos (`caja.ver`, `caja.registrar`, `caja.anular`, `caja.reportes`)
 * se comprueban en app/routes.php.
 */
final class CajaController
{
    private const POR_PAGINA = 25;

    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $filtros = $this->filtros($request);
        $total = Movimiento::total($filtros);
        $pagina = max(1, $request->integer('pagina', 1));

        [$desde, $hasta] = $this->rangoResumen($filtros);

        View::display('caja/index', [
            'titulo'      => 'Caja',
            'movimientos' => Movimiento::listar(
                $filtros,
                self::POR_PAGINA,
                ($pagina - 1) * self::POR_PAGINA
            ),
            'categorias'  => CategoriaMovimiento::todas(),
            'resumen'     => Movimiento::resumenPeriodo($desde, $hasta),
            'resumenPeriodo' => ($filtros['desde'] === '' && $filtros['hasta'] === '')
                ? 'del mes en curso'
                : 'del período filtrado',
            'filtros'     => $filtros,
            'total'       => $total,
            'pagina'      => $pagina,
            'paginas'     => max(1, (int) ceil($total / self::POR_PAGINA)),
        ]);
    }

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('caja/form', [
            'titulo'    => 'Nuevo movimiento',
            'movimiento' => null,
            'modo'      => 'crear',
            'categorias' => CategoriaMovimiento::todas(),
            'medios'    => MedioPago::activos(),
            'periodos'  => Periodo::abiertos(),
            'socios'    => Socio::listar(null, 'activo', 0, 100, 0),
        ]);
    }

    public function crear(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/caja/nuevo'));
        }

        try {
            $id = Movimiento::crear($this->registro($limpio));
        } catch (Throwable $exception) {
            Logger::error('Error al registrar un movimiento', ['error' => $exception->getMessage()]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo registrar el movimiento. Intente nuevamente.');
            Response::redirect(url('/caja/nuevo'));
        }

        Movimiento::registrarAuditoria($id, 'creacion', null, $limpio, Auth::id());

        Audit::log(
            Auth::id(),
            'crear_movimiento',
            'movimiento',
            $id,
            sprintf(
                '%s registrado por %s: %s',
                (string) $limpio['tipo'] === 'ingreso' ? 'Ingreso' : 'Egreso',
                dinero((float) $limpio['monto']),
                (string) $limpio['concepto']
            )
        );

        Session::flash('exito', 'El movimiento quedó registrado.');
        Response::redirect(url('/caja'));
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $movimiento = $this->obtener((int) $id);

        if ((string) $movimiento['estado'] !== 'registrado') {
            Session::flash('error', 'Un movimiento anulado no se puede editar.');
            Response::redirect(url('/caja'));
        }

        View::display('caja/form', [
            'titulo'    => 'Editar movimiento',
            'movimiento' => $movimiento,
            'modo'      => 'editar',
            'categorias' => CategoriaMovimiento::todas(),
            'medios'    => MedioPago::activos(),
            'periodos'  => Periodo::todos(),
            'socios'    => Socio::listar(null, 'activo', 0, 100, 0),
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $movimiento = $this->obtener((int) $id);
        $movimientoId = (int) $movimiento['id'];

        if ((string) $movimiento['estado'] !== 'registrado') {
            Session::flash('error', 'Un movimiento anulado no se puede editar.');
            Response::redirect(url('/caja'));
        }

        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/caja/' . $movimientoId . '/editar'));
        }

        try {
            Movimiento::actualizar($movimientoId, $this->registro($limpio));
        } catch (Throwable $exception) {
            Logger::error('Error al actualizar un movimiento', [
                'movimiento_id' => $movimientoId,
                'error'         => $exception->getMessage(),
            ]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo guardar el movimiento. Intente nuevamente.');
            Response::redirect(url('/caja/' . $movimientoId . '/editar'));
        }

        Movimiento::registrarAuditoria(
            $movimientoId,
            'edicion',
            $this->datosAuditable($movimiento),
            $limpio,
            Auth::id()
        );

        Audit::log(
            Auth::id(),
            'editar_movimiento',
            'movimiento',
            $movimientoId,
            'Movimiento actualizado: ' . (string) $limpio['concepto']
        );

        Session::flash('exito', 'El movimiento se actualizó.');
        Response::redirect(url('/caja'));
    }

    public function anular(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $movimiento = $this->obtener((int) $id);
        $movimientoId = (int) $movimiento['id'];

        if ((string) $movimiento['estado'] !== 'registrado') {
            Session::flash('error', 'El movimiento ya estaba anulado.');
            Response::redirect(url('/caja'));
        }

        $motivo = trim($request->string('motivo'));

        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 300) {
            remember_old_input($request->only(['motivo']));
            Session::flash('errores', ['El motivo de la anulación debe tener entre 5 y 300 caracteres.']);
            Response::redirect(url('/caja'));
        }

        try {
            Movimiento::anular($movimientoId, (int) Auth::id(), $motivo);
        } catch (Throwable $exception) {
            Logger::error('Error al anular un movimiento', [
                'movimiento_id' => $movimientoId,
                'error'         => $exception->getMessage(),
            ]);
            Session::flash('error', 'No se pudo anular el movimiento.');
            Response::redirect(url('/caja'));
        }

        Movimiento::registrarAuditoria(
            $movimientoId,
            'anulacion',
            $this->datosAuditable($movimiento),
            null,
            Auth::id(),
            $motivo
        );

        Audit::log(
            Auth::id(),
            'anular_movimiento',
            'movimiento',
            $movimientoId,
            'Movimiento anulado: ' . (string) $movimiento['concepto']
        );

        Session::flash('exito', 'El movimiento quedó anulado. El registro se conserva.');
        Response::redirect(url('/caja'));
    }

    public function configuracion(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('caja/configuracion', [
            'titulo'     => 'Configuración de caja',
            'categorias' => CategoriaMovimiento::agrupadas(),
            'medios'     => MedioPago::todos(),
            'periodos'   => Periodo::todos(),
            'tipos'      => Movimiento::TIPOS,
        ]);
    }

    public function guardarCategoria(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $request->only(['nombre', 'tipo', 'descripcion']);
        $validador = Validator::make($datos, [
            'nombre'      => 'required|string|min:2|max:80',
            'tipo'        => 'required|in:ingreso,egreso',
            'descripcion' => 'nullable|string|max:200',
        ], [
            'nombre'      => 'nombre de la categoría',
            'tipo'        => 'tipo',
            'descripcion' => 'descripción',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes() && CategoriaMovimiento::nombreExiste((string) $limpio['nombre'], (string) $limpio['tipo'])) {
            $errores[] = 'Ya existe una categoría con ese nombre para ese tipo.';
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/caja/configuracion'));
        }

        CategoriaMovimiento::crear([
            'nombre'      => (string) $limpio['nombre'],
            'tipo'        => (string) $limpio['tipo'],
            'descripcion' => (string) ($limpio['descripcion'] ?? ''),
            'activo'      => 1,
        ]);

        Audit::log(
            Auth::id(),
            'crear_categoria_caja',
            'categoria_movimiento',
            null,
            'Categoría de caja creada: ' . (string) $limpio['nombre']
        );

        Session::flash('exito', 'La categoría quedó creada.');
        Response::redirect(url('/caja/configuracion'));
    }

    public function estadoCategoria(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $categoria = CategoriaMovimiento::find((int) $id);

        if ($categoria === null) {
            Session::flash('error', 'La categoría indicada no existe.');
            Response::redirect(url('/caja/configuracion'));
        }

        $accion = $request->string('accion');
        $activar = $accion === 'activar';

        CategoriaMovimiento::cambiarActivo((int) $categoria['id'], $activar);

        Audit::log(
            Auth::id(),
            $activar ? 'activar_categoria_caja' : 'desactivar_categoria_caja',
            'categoria_movimiento',
            (int) $categoria['id'],
            ($activar ? 'Categoría activada: ' : 'Categoría desactivada: ') . (string) $categoria['nombre']
        );

        Session::flash('exito', $activar ? 'La categoría quedó activa.' : 'La categoría quedó desactivada.');
        Response::redirect(url('/caja/configuracion'));
    }

    public function guardarMedio(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $request->only(['nombre', 'descripcion', 'orden']);
        $validador = Validator::make($datos, [
            'nombre'      => 'required|string|min:2|max:60',
            'descripcion' => 'nullable|string|max:200',
            'orden'       => 'nullable|integer|min:1|max:999',
        ], [
            'nombre'      => 'nombre del medio de pago',
            'descripcion' => 'descripción',
            'orden'       => 'orden',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes() && MedioPago::nombreExiste((string) $limpio['nombre'])) {
            $errores[] = 'Ya existe un medio de pago con ese nombre.';
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/caja/configuracion'));
        }

        MedioPago::crear([
            'nombre'      => (string) $limpio['nombre'],
            'descripcion' => (string) ($limpio['descripcion'] ?? ''),
            'orden'       => (int) ($limpio['orden'] ?? 1),
            'activo'      => 1,
        ]);

        Audit::log(
            Auth::id(),
            'crear_medio_pago',
            'medio_pago',
            null,
            'Medio de pago creado: ' . (string) $limpio['nombre']
        );

        Session::flash('exito', 'El medio de pago quedó creado.');
        Response::redirect(url('/caja/configuracion'));
    }

    public function estadoMedio(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $medio = MedioPago::find((int) $id);

        if ($medio === null) {
            Session::flash('error', 'El medio de pago indicado no existe.');
            Response::redirect(url('/caja/configuracion'));
        }

        $activar = $request->string('accion') === 'activar';

        MedioPago::cambiarActivo((int) $medio['id'], $activar);

        Session::flash('exito', $activar ? 'El medio de pago quedó activo.' : 'El medio de pago quedó desactivado.');
        Response::redirect(url('/caja/configuracion'));
    }

    public function guardarPeriodo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $request->only(['nombre', 'fecha_inicio', 'fecha_fin', 'observaciones']);
        $validador = Validator::make($datos, [
            'nombre'        => 'required|string|min:2|max:60',
            'fecha_inicio'  => 'required|date',
            'fecha_fin'     => 'required|date',
            'observaciones' => 'nullable|string|max:200',
        ], [
            'nombre'        => 'nombre del periodo',
            'fecha_inicio'  => 'fecha de inicio',
            'fecha_fin'     => 'fecha de fin',
            'observaciones' => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            if ((string) $limpio['fecha_fin'] < (string) $limpio['fecha_inicio']) {
                $errores[] = 'La fecha de fin no puede ser anterior a la de inicio.';
            }

            if (Periodo::nombreExiste((string) $limpio['nombre'])) {
                $errores[] = 'Ya existe un periodo con ese nombre.';
            }
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/caja/configuracion'));
        }

        Periodo::crear([
            'nombre'        => (string) $limpio['nombre'],
            'fecha_inicio'  => (string) $limpio['fecha_inicio'],
            'fecha_fin'     => (string) $limpio['fecha_fin'],
            'observaciones' => (string) ($limpio['observaciones'] ?? ''),
            'cerrado'       => 0,
        ]);

        Session::flash('exito', 'El periodo quedó creado.');
        Response::redirect(url('/caja/configuracion'));
    }

    public function estadoPeriodo(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $periodo = Periodo::find((int) $id);

        if ($periodo === null) {
            Session::flash('error', 'El periodo indicado no existe.');
            Response::redirect(url('/caja/configuracion'));
        }

        $cerrar = $request->string('accion') === 'cerrar';

        Periodo::cambiarCerrado((int) $periodo['id'], $cerrar);

        Session::flash(
            'exito',
            $cerrar
                ? 'El periodo quedó cerrado: no admite nuevos movimientos.'
                : 'El periodo volvió a estar abierto.'
        );
        Response::redirect(url('/caja/configuracion'));
    }

    public function reportes(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $desde = $request->string('desde');
        $desde = $this->fechaValida($desde) ? $desde : date('Y-01-01');
        $hasta = $request->string('hasta');
        $hasta = $this->fechaValida($hasta) ? $hasta : date('Y-m-d');

        if ($hasta < $desde) {
            $hasta = $desde;
        }

        View::display('caja/reportes', [
            'titulo'      => 'Reportes de caja',
            'desde'       => $desde,
            'hasta'       => $hasta,
            'resumen'     => Movimiento::resumenPeriodo($desde, $hasta),
            'categorias'  => Movimiento::resumenPorCategoria($desde, $hasta),
            'mensual'     => Movimiento::resumenMensual($desde, $hasta),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Request $request): array
    {
        $tipo = $request->string('tipo');
        $tipo = isset(Movimiento::TIPOS[$tipo]) ? $tipo : '';

        $estado = $request->string('estado');
        $estado = isset(Movimiento::ESTADOS[$estado]) ? $estado : '';

        $desde = $request->string('desde');
        $desde = $this->fechaValida($desde) ? $desde : '';

        $hasta = $request->string('hasta');
        $hasta = $this->fechaValida($hasta) ? $hasta : '';

        $categoriaId = $request->integer('categoria_id', 0);

        return [
            'q'            => $request->string('q'),
            'tipo'         => $tipo,
            'estado'       => $estado,
            'categoria_id' => $categoriaId > 0 ? $categoriaId : 0,
            'desde'        => $desde,
            'hasta'        => $hasta,
        ];
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: string}
     */
    private function rangoResumen(array $filtros): array
    {
        $desde = (string) $filtros['desde'];
        $hasta = (string) $filtros['hasta'];

        if ($desde === '') {
            $desde = date('Y-m-01');
        }

        if ($hasta === '') {
            $hasta = date('Y-m-d');
        }

        return [$desde, $hasta];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        $datos = $request->only([
            'tipo',
            'fecha',
            'concepto',
            'monto',
            'categoria_id',
            'medio_pago_id',
            'periodo_id',
            'socio_codigo',
            'referencia',
            'observaciones',
        ]);

        return $datos;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validar(array $datos): array
    {
        $validador = Validator::make($datos, [
            'tipo'          => 'required|in:ingreso,egreso',
            'fecha'         => 'required|date',
            'concepto'      => 'required|string|min:3|max:200',
            'monto'         => 'required|decimal:2',
            'categoria_id'  => 'nullable|integer|exists:categorias_movimiento,id',
            'medio_pago_id' => 'nullable|integer|exists:medios_pago,id',
            'periodo_id'    => 'nullable|integer|exists:periodos,id',
            'socio_codigo'  => 'nullable|string|max:30',
            'referencia'    => 'nullable|string|max:60',
            'observaciones' => 'nullable|string|max:1000',
        ], [
            'tipo'          => 'tipo de movimiento',
            'fecha'         => 'fecha',
            'concepto'      => 'concepto',
            'monto'         => 'monto',
            'categoria_id'  => 'categoría',
            'medio_pago_id' => 'medio de pago',
            'periodo_id'    => 'periodo',
            'socio_codigo'  => 'código de socio',
            'referencia'    => 'referencia',
            'observaciones' => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $monto = (float) $limpio['monto'];

            if ($monto <= 0) {
                $errores[] = 'El monto debe ser mayor que cero.';
            }

            if ($monto > 9999999999.99) {
                $errores[] = 'El monto es demasiado grande.';
            }

            $fecha = (string) $limpio['fecha'];

            if ($fecha > date('Y-m-d')) {
                $errores[] = 'No se pueden registrar movimientos con fecha futura.';
            }

            $periodoId = (int) ($limpio['periodo_id'] ?? 0);

            if ($periodoId > 0) {
                $periodo = Periodo::find($periodoId);

                if ($periodo !== null && (int) $periodo['cerrado'] === 1) {
                    $errores[] = 'El periodo seleccionado está cerrado y no admite nuevos movimientos.';
                }
            }

            $codigo = trim((string) ($limpio['socio_codigo'] ?? ''));

            if ($codigo !== '' && Socio::findPorCodigo($codigo) === null) {
                $errores[] = 'No existe un socio con ese código.';
            }
        }

        return [$limpio, $errores];
    }

    /**
     * @param array<string, mixed> $limpio
     * @return array<string, mixed>
     */
    private function registro(array $limpio): array
    {
        $codigo = trim((string) ($limpio['socio_codigo'] ?? ''));
        $socio = $codigo === '' ? null : Socio::findPorCodigo($codigo);

        return [
            'tipo'           => (string) $limpio['tipo'],
            'fecha'          => (string) $limpio['fecha'],
            'concepto'       => (string) $limpio['concepto'],
            'monto'          => (string) $limpio['monto'],
            'categoria_id'   => (int) ($limpio['categoria_id'] ?? 0),
            'medio_pago_id'  => (int) ($limpio['medio_pago_id'] ?? 0),
            'periodo_id'     => (int) ($limpio['periodo_id'] ?? 0),
            'socio_id'       => $socio === null ? 0 : (int) $socio['id'],
            'referencia'     => (string) ($limpio['referencia'] ?? ''),
            'observaciones'  => (string) ($limpio['observaciones'] ?? ''),
            'responsable_id' => (int) Auth::id(),
        ];
    }

    /**
     * Datos del movimiento que se guardan en la traza de auditoría.
     *
     * @param array<string, mixed> $movimiento
     * @return array<string, mixed>
     */
    private function datosAuditable(array $movimiento): array
    {
        return [
            'tipo'      => (string) $movimiento['tipo'],
            'fecha'     => (string) $movimiento['fecha'],
            'concepto'  => (string) $movimiento['concepto'],
            'monto'     => (string) $movimiento['monto'],
            'estado'    => (string) $movimiento['estado'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function obtener(int $id): array
    {
        $movimiento = Movimiento::findConCatalogos($id);

        if ($movimiento === null) {
            abort(404, 'El movimiento indicado no existe.');
        }

        return $movimiento;
    }

    private function fechaValida(string $fecha): bool
    {
        if ($fecha === '') {
            return false;
        }

        $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $valor instanceof DateTimeImmutable && $valor->format('Y-m-d') === $fecha;
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
