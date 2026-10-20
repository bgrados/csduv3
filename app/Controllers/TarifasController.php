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
use App\Models\Instalacion;
use App\Models\Tarifa;
use App\Models\TipoEvento;
use DateTimeImmutable;
use Throwable;

/**
 * Administración de tarifas por ambiente.
 */
final class TarifasController
{
    public function index(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $instalacion = $this->obtenerInstalacion((int) $id);

        View::display('tarifas/index', [
            'titulo'       => 'Tarifas de ' . (string) $instalacion['nombre'],
            'instalacion'  => $instalacion,
            'tarifas'      => Tarifa::todasPorInstalacion((int) $instalacion['id']),
            'tiposEvento'  => TipoEvento::todos(),
        ]);
    }

    public function nuevo(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $instalacion = $this->obtenerInstalacion((int) $id);

        View::display('tarifas/form', [
            'titulo'      => 'Nueva tarifa',
            'instalacion' => $instalacion,
            'tarifa'      => null,
            'modo'        => 'crear',
            'tiposEvento' => TipoEvento::todos(),
        ]);
    }

    public function crear(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $instalacion = $this->obtenerInstalacion((int) $id);
        $instalacionId = (int) $instalacion['id'];
        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/ambientes/' . $instalacionId . '/tarifas/nueva'));
        }

        try {
            $tarifaId = Tarifa::crear($this->registro($limpio, $instalacionId));
        } catch (Throwable $exception) {
            Logger::error('Error al crear una tarifa', [
                'instalacion_id' => $instalacionId,
                'error'          => $exception->getMessage(),
            ]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo crear la tarifa. Intente nuevamente.');
            Response::redirect(url('/ambientes/' . $instalacionId . '/tarifas/nueva'));
        }

        Audit::log(
            Auth::id(),
            'crear_tarifa',
            'tarifa',
            $tarifaId,
            'Tarifa creada para ' . (string) $instalacion['nombre'] . ': ' . (string) $limpio['nombre']
        );

        Session::flash('exito', 'La tarifa quedó registrada.');
        Response::redirect(url('/ambientes/' . $instalacionId . '/tarifas'));
    }

    public function editar(Request $request, string $id, string $tarifaId): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $instalacion = $this->obtenerInstalacion((int) $id);
        $tarifa = $this->obtenerTarifa((int) $tarifaId, (int) $instalacion['id']);

        View::display('tarifas/form', [
            'titulo'      => 'Editar tarifa',
            'instalacion' => $instalacion,
            'tarifa'      => $tarifa,
            'modo'        => 'editar',
            'tiposEvento' => TipoEvento::todos(),
        ]);
    }

    public function actualizar(Request $request, string $id, string $tarifaId): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $instalacion = $this->obtenerInstalacion((int) $id);
        $tarifa = $this->obtenerTarifa((int) $tarifaId, (int) $instalacion['id']);
        $instalacionId = (int) $instalacion['id'];
        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/ambientes/' . $instalacionId . '/tarifas/' . (int) $tarifa['id'] . '/editar'));
        }

        try {
            Tarifa::actualizar((int) $tarifa['id'], $this->registro($limpio, $instalacionId));
        } catch (Throwable $exception) {
            Logger::error('Error al actualizar una tarifa', [
                'tarifa_id'      => (int) $tarifa['id'],
                'instalacion_id' => $instalacionId,
                'error'          => $exception->getMessage(),
            ]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo guardar la tarifa. Intente nuevamente.');
            Response::redirect(url('/ambientes/' . $instalacionId . '/tarifas/' . (int) $tarifa['id'] . '/editar'));
        }

        Audit::log(
            Auth::id(),
            'editar_tarifa',
            'tarifa',
            (int) $tarifa['id'],
            'Tarifa actualizada: ' . (string) $limpio['nombre']
        );

        Session::flash('exito', 'La tarifa se actualizó.');
        Response::redirect(url('/ambientes/' . $instalacionId . '/tarifas'));
    }

    public function cambiarEstado(Request $request, string $id, string $tarifaId): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $instalacion = $this->obtenerInstalacion((int) $id);
        $tarifa = $this->obtenerTarifa((int) $tarifaId, (int) $instalacion['id']);
        $accion = $request->string('accion');

        if (!in_array($accion, ['activar', 'desactivar'], true)) {
            Session::flash('error', 'La acción solicitada no es válida.');
            Response::redirect(url('/ambientes/' . (int) $instalacion['id'] . '/tarifas'));
        }

        $activar = $accion === 'activar';

        try {
            Tarifa::cambiarActivo((int) $tarifa['id'], (int) $instalacion['id'], $activar);
        } catch (Throwable $exception) {
            Logger::error('Error al cambiar el estado de una tarifa', [
                'tarifa_id' => (int) $tarifa['id'],
                'error'     => $exception->getMessage(),
            ]);
            Session::flash('error', 'No se pudo cambiar el estado de la tarifa.');
            Response::redirect(url('/ambientes/' . (int) $instalacion['id'] . '/tarifas'));
        }

        Audit::log(
            Auth::id(),
            $activar ? 'activar_tarifa' : 'desactivar_tarifa',
            'tarifa',
            (int) $tarifa['id'],
            ($activar ? 'Tarifa activada: ' : 'Tarifa desactivada: ') . (string) $tarifa['nombre']
        );

        Session::flash('exito', $activar ? 'La tarifa quedó activa.' : 'La tarifa quedó desactivada.');
        Response::redirect(url('/ambientes/' . (int) $instalacion['id'] . '/tarifas'));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        $datos = $request->only([
            'nombre',
            'tipo_evento_id',
            'monto',
            'hora_inicio',
            'hora_fin',
            'vigente_desde',
            'vigente_hasta',
            'observaciones',
        ]);

        $datos['activo'] = $request->boolean('activo') ? '1' : '0';

        return $datos;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validar(array $datos): array
    {
        $validador = Validator::make($datos, [
            'nombre'         => 'required|string|min:2|max:120',
            'tipo_evento_id' => 'nullable|integer|exists:tipos_evento,id',
            'monto'          => 'required|decimal:2',
            'hora_inicio'    => 'nullable|regex:/^\d{2}:\d{2}$/',
            'hora_fin'       => 'nullable|regex:/^\d{2}:\d{2}$/',
            'vigente_desde'  => 'nullable|date',
            'vigente_hasta'  => 'nullable|date',
            'activo'         => 'required|in:0,1',
            'observaciones'  => 'nullable|string|max:300',
        ], [
            'nombre'         => 'nombre de la tarifa',
            'tipo_evento_id' => 'tipo de uso',
            'monto'          => 'monto',
            'hora_inicio'    => 'hora de inicio',
            'hora_fin'       => 'hora de fin',
            'vigente_desde'  => 'vigencia desde',
            'vigente_hasta'  => 'vigencia hasta',
            'observaciones'  => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $monto = (float) $limpio['monto'];

            if ($monto < 0 || $monto > 9999999999.99) {
                $errores[] = 'El monto debe ser un importe válido mayor o igual a cero.';
            }

            $inicio = (string) ($limpio['hora_inicio'] ?? '');
            $fin = (string) ($limpio['hora_fin'] ?? '');

            if ($inicio !== '' && !$this->horaValida($inicio)) {
                $errores[] = 'La hora de inicio no es válida.';
            }

            if ($fin !== '' && !$this->horaValida($fin)) {
                $errores[] = 'La hora de fin no es válida.';
            }

            if (($inicio === '') !== ($fin === '')) {
                $errores[] = 'Indique ambas horas o deje ambas vacías.';
            } elseif ($inicio !== '' && $fin !== '' && $fin <= $inicio) {
                $errores[] = 'La hora de fin debe ser posterior a la hora de inicio.';
            }

            $desde = (string) ($limpio['vigente_desde'] ?? '');
            $hasta = (string) ($limpio['vigente_hasta'] ?? '');

            if ($desde !== '' && $hasta !== '' && $hasta < $desde) {
                $errores[] = 'La fecha de vigencia final no puede ser anterior a la inicial.';
            }
        }

        return [$limpio, $errores];
    }

    /**
     * @param array<string, mixed> $limpio
     * @return array<string, mixed>
     */
    private function registro(array $limpio, int $instalacionId): array
    {
        return [
            'instalacion_id' => $instalacionId,
            'tipo_evento_id' => ($limpio['tipo_evento_id'] ?? null) === null ? null : (int) $limpio['tipo_evento_id'],
            'nombre'         => (string) $limpio['nombre'],
            'monto'          => (string) $limpio['monto'],
            'hora_inicio'    => (string) ($limpio['hora_inicio'] ?? ''),
            'hora_fin'       => (string) ($limpio['hora_fin'] ?? ''),
            'vigente_desde'  => (string) ($limpio['vigente_desde'] ?? ''),
            'vigente_hasta'  => (string) ($limpio['vigente_hasta'] ?? ''),
            'activo'         => (int) ($limpio['activo'] ?? 1),
            'observaciones'  => (string) ($limpio['observaciones'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerInstalacion(int $id): array
    {
        $instalacion = Instalacion::find($id);

        if ($instalacion === null) {
            abort(404, 'El ambiente indicado no existe.');
        }

        return $instalacion;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerTarifa(int $tarifaId, int $instalacionId): array
    {
        $tarifa = Tarifa::findDeInstalacion($tarifaId, $instalacionId);

        if ($tarifa === null) {
            abort(404, 'La tarifa indicada no existe o no pertenece a este ambiente.');
        }

        return $tarifa;
    }

    private function horaValida(string $hora): bool
    {
        $valor = DateTimeImmutable::createFromFormat('!H:i', $hora);

        return $valor instanceof DateTimeImmutable && $valor->format('H:i') === $hora;
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
