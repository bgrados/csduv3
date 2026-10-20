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
use App\Models\Actividad;
use App\Models\Instalacion;
use DateTimeImmutable;
use Throwable;

/**
 * Calendario institucional.
 *
 * Registra reuniones, elecciones, actividades y eventos del club, con su
 * fecha, lugar y estado.
 *
 * Los permisos (`calendario.ver`, `calendario.gestionar`) se comprueban en
 * app/routes.php.
 */
final class CalendarioController
{
    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $anio = $request->integer('anio', (int) date('Y'));
        $mes = $request->integer('mes', (int) date('n'));
        $mes = max(1, min(12, $mes));
        $anio = max(2000, min(2100, $anio));

        $tipo = $request->string('tipo');
        $tipo = isset(Actividad::TIPOS[$tipo]) ? $tipo : '';

        $inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fin = date('Y-m-t', strtotime($inicio));

        View::display('calendario/index', [
            'titulo'     => 'Calendario',
            'actividades' => Actividad::listarPorRango($inicio, $fin, $tipo),
            'anio'       => $anio,
            'mes'        => $mes,
            'tipo'       => $tipo,
            'tipos'      => Actividad::TIPOS,
            'estados'    => Actividad::ESTADOS,
            'proximas'   => Actividad::listarProximas(5),
        ]);
    }

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('calendario/form', [
            'titulo'        => 'Nueva actividad',
            'actividad'     => null,
            'modo'          => 'crear',
            'tipos'         => Actividad::TIPOS,
            'estados'       => Actividad::ESTADOS,
            'instalaciones' => Instalacion::todas(),
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
            Response::redirect(url('/calendario/nuevo'));
        }

        try {
            $id = Actividad::crear($this->registro($limpio));
        } catch (Throwable $exception) {
            Logger::error('Error al crear una actividad', ['error' => $exception->getMessage()]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo crear la actividad.');
            Response::redirect(url('/calendario/nuevo'));
        }

        Audit::log(Auth::id(), 'crear_actividad', 'actividad', $id, 'Actividad programada: ' . (string) $limpio['titulo']);

        Session::flash('exito', 'La actividad quedó programada.');
        Response::redirect(url('/calendario'));
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $actividad = $this->obtener((int) $id);

        View::display('calendario/form', [
            'titulo'        => 'Editar actividad',
            'actividad'     => $actividad,
            'modo'          => 'editar',
            'tipos'         => Actividad::TIPOS,
            'estados'       => Actividad::ESTADOS,
            'instalaciones' => Instalacion::todas(),
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $actividad = $this->obtener((int) $id);
        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/calendario/' . (int) $actividad['id'] . '/editar'));
        }

        Actividad::actualizar((int) $actividad['id'], $this->registro($limpio));

        Audit::log(Auth::id(), 'editar_actividad', 'actividad', (int) $actividad['id'], 'Actividad actualizada: ' . (string) $limpio['titulo']);

        Session::flash('exito', 'La actividad se actualizó.');
        Response::redirect(url('/calendario'));
    }

    public function cambiarEstado(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $actividad = $this->obtener((int) $id);
        $estado = $request->string('estado');

        if (!isset(Actividad::ESTADOS[$estado])) {
            Session::flash('error', 'El estado indicado no es válido.');
            Response::redirect(url('/calendario'));
        }

        Actividad::cambiarEstado((int) $actividad['id'], $estado);

        Audit::log(
            Auth::id(),
            'cambiar_estado_actividad',
            'actividad',
            (int) $actividad['id'],
            'Actividad pasó a ' . Actividad::ESTADOS[$estado]
        );

        Session::flash('exito', 'El estado de la actividad se actualizó.');
        Response::redirect(url('/calendario'));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        $datos = $request->only([
            'titulo',
            'tipo',
            'descripcion',
            'fecha',
            'hora',
            'fecha_fin',
            'lugar',
            'instalacion_id',
            'estado',
        ]);

        $datos['todo_el_dia'] = $request->boolean('todo_el_dia') ? '1' : '0';
        $datos['visible'] = $request->boolean('visible') ? '1' : '0';

        return $datos;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validar(array $datos): array
    {
        $validador = Validator::make($datos, [
            'titulo'         => 'required|string|min:3|max:180',
            'tipo'           => 'required|in:institucional,directiva,eleccion,deportiva,social,mantenimiento',
            'descripcion'    => 'nullable|string|max:2000',
            'fecha'          => 'required|date',
            'hora'           => 'nullable|regex:/^\d{2}:\d{2}$/',
            'fecha_fin'      => 'nullable|date',
            'lugar'          => 'nullable|string|max:150',
            'instalacion_id' => 'nullable|integer|exists:instalaciones,id',
            'estado'         => 'required|in:programada,realizada,cancelada,reprogramada',
            'todo_el_dia'    => 'required|in:0,1',
            'visible'        => 'required|in:0,1',
        ], [
            'titulo'         => 'título',
            'tipo'           => 'tipo',
            'descripcion'    => 'descripción',
            'fecha'          => 'fecha',
            'hora'           => 'hora',
            'fecha_fin'      => 'fecha de fin',
            'lugar'          => 'lugar',
            'instalacion_id' => 'sede',
            'estado'         => 'estado',
            'todo_el_dia'    => 'todo el día',
            'visible'        => 'visibilidad',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $hora = (string) ($limpio['hora'] ?? '');

            if ($hora !== '' && !$this->horaValida($hora)) {
                $errores[] = 'La hora no es válida.';
            }

            $fecha = (string) $limpio['fecha'];
            $fin = (string) ($limpio['fecha_fin'] ?? '');

            if ($fin !== '' && $fin < $fecha) {
                $errores[] = 'La fecha de fin no puede ser anterior a la fecha de inicio.';
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
        $todoElDia = (int) ($limpio['todo_el_dia'] ?? 0) === 1;
        $hora = trim((string) ($limpio['hora'] ?? ''));

        if ($todoElDia || $hora === '') {
            $fechaInicio = (string) $limpio['fecha'] . ' 00:00:00';
        } else {
            $fechaInicio = (string) $limpio['fecha'] . ' ' . $hora . ':00';
        }

        $fechaFin = trim((string) ($limpio['fecha_fin'] ?? ''));

        if ($fechaFin === '') {
            $fechaFin = $todoElDia ? (string) $limpio['fecha'] . ' 23:59:59' : $fechaInicio;
        } elseif ($todoElDia) {
            $fechaFin .= ' 23:59:59';
        } else {
            $fechaFin .= ' ' . ($hora !== '' ? $hora . ':00' : '00:00:00');
        }

        return [
            'titulo'         => (string) $limpio['titulo'],
            'tipo'           => (string) $limpio['tipo'],
            'descripcion'    => (string) ($limpio['descripcion'] ?? ''),
            'fecha_inicio'   => $fechaInicio,
            'fecha_fin'      => $fechaFin,
            'todo_el_dia'    => (int) ($limpio['todo_el_dia'] ?? 0),
            'lugar'          => (string) ($limpio['lugar'] ?? ''),
            'instalacion_id' => (int) ($limpio['instalacion_id'] ?? 0),
            'estado'         => (string) $limpio['estado'],
            'visible'        => (int) ($limpio['visible'] ?? 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function obtener(int $id): array
    {
        $actividad = Actividad::find($id);

        if ($actividad === null) {
            abort(404, 'La actividad indicada no existe.');
        }

        return $actividad;
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
