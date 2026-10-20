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
use App\Models\Deporte;
use App\Models\Encuentro;
use App\Models\Instalacion;
use App\Models\Torneo;
use App\Models\TorneoEquipo;
use DateTimeImmutable;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Administración de torneos, equipos, fixture y resultados.
 */
final class TorneosController
{
    /** Máximo de equipos por torneo para mantener un fixture manejable. */
    private const MAX_EQUIPOS = 64;

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('torneos/form', [
            'titulo'      => 'Nuevo torneo',
            'torneo'      => null,
            'modo'        => 'crear',
            'deportes'    => Deporte::activos(),
            'modalidades' => Torneo::MODALIDADES,
            'estados'     => Torneo::ESTADOS,
        ]);
    }

    public function crear(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $this->datosTorneo($request);
        [$limpio, $errores] = $this->validarTorneo($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/deportes/torneos/nuevo'));
        }

        try {
            $id = Torneo::crear($this->registroTorneo($limpio));
        } catch (Throwable $exception) {
            Logger::error('Error al crear un torneo', ['error' => $exception->getMessage()]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo crear el torneo. Intente nuevamente.');
            Response::redirect(url('/deportes/torneos/nuevo'));
        }

        Audit::log(Auth::id(), 'crear_torneo', 'torneo', $id, 'Torneo creado: ' . (string) $limpio['nombre']);

        Session::flash('exito', 'El torneo quedó creado. Inscriba los equipos y genere el fixture.');
        Response::redirect(url('/deportes/torneos/' . $id));
    }

    public function ver(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $torneo = $this->obtenerTorneo((int) $id);
        $torneoId = (int) $torneo['id'];
        $equipos = TorneoEquipo::listarPorTorneo($torneoId);
        $encuentros = Encuentro::listarPorTorneo($torneoId);
        $posiciones = (string) $torneo['modalidad'] === 'todos_contra_todos'
            ? Torneo::posiciones($torneoId, (int) $torneo['deporte_id'])
            : [];

        View::display('torneos/ver', [
            'titulo'       => (string) $torneo['nombre'],
            'torneo'       => $torneo,
            'equipos'      => $equipos,
            'encuentros'   => $encuentros,
            'posiciones'   => $posiciones,
            'estados'      => Torneo::ESTADOS,
            'estadosPartido' => Encuentro::ESTADOS,
            'modalidades'  => Torneo::MODALIDADES,
            'instalaciones' => Instalacion::todas(),
        ]);
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $torneo = $this->obtenerTorneo((int) $id);

        View::display('torneos/form', [
            'titulo'      => 'Editar torneo',
            'torneo'      => $torneo,
            'modo'        => 'editar',
            'deportes'    => Deporte::activos(),
            'modalidades' => Torneo::MODALIDADES,
            'estados'     => Torneo::ESTADOS,
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $torneo = $this->obtenerTorneo((int) $id);
        $torneoId = (int) $torneo['id'];
        $datos = $this->datosTorneo($request);
        [$limpio, $errores] = $this->validarTorneo($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/deportes/torneos/' . $torneoId . '/editar'));
        }

        try {
            Torneo::actualizar($torneoId, $this->registroTorneo($limpio));
        } catch (Throwable $exception) {
            Logger::error('Error al actualizar un torneo', [
                'torneo_id' => $torneoId,
                'error'     => $exception->getMessage(),
            ]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo guardar el torneo. Intente nuevamente.');
            Response::redirect(url('/deportes/torneos/' . $torneoId . '/editar'));
        }

        Audit::log(
            Auth::id(),
            'editar_torneo',
            'torneo',
            $torneoId,
            'Torneo actualizado: ' . (string) $limpio['nombre']
        );

        Session::flash('exito', 'El torneo se actualizó.');
        Response::redirect(url('/deportes/torneos/' . $torneoId));
    }

    public function cambiarEstado(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $torneo = $this->obtenerTorneo((int) $id);
        $estado = $request->string('estado');

        if (!isset(Torneo::ESTADOS[$estado])) {
            Session::flash('error', 'El estado solicitado no es válido.');
            Response::redirect(url('/deportes/torneos/' . (int) $torneo['id']));
        }

        Torneo::cambiarEstado((int) $torneo['id'], $estado);

        Audit::log(
            Auth::id(),
            'cambiar_estado_torneo',
            'torneo',
            (int) $torneo['id'],
            'Torneo ' . (string) $torneo['nombre'] . ' pasó a ' . Torneo::ESTADOS[$estado]
        );

        Session::flash('exito', 'El estado del torneo se actualizó.');
        Response::redirect(url('/deportes/torneos/' . (int) $torneo['id']));
    }

    public function agregarEquipo(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $torneo = $this->obtenerTorneo((int) $id);
        $torneoId = (int) $torneo['id'];

        if ((string) $torneo['estado'] === 'anulado') {
            Session::flash('error', 'Un torneo anulado no admite modificaciones.');
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        if (Encuentro::contarPorTorneo($torneoId) > 0) {
            Session::flash('error', 'El fixture ya fue generado. Regenérelo para incorporar nuevos equipos.');
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        if (TorneoEquipo::contarPorTorneo($torneoId) >= self::MAX_EQUIPOS) {
            Session::flash('error', 'Se alcanzó el máximo de ' . self::MAX_EQUIPOS . ' equipos para este torneo.');
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        $datos = $request->only([
            'nombre',
            'abreviatura',
            'delegado_nombre',
            'delegado_telefono',
            'observaciones',
        ]);

        $validador = Validator::make($datos, [
            'nombre'            => 'required|string|min:2|max:120',
            'abreviatura'       => 'nullable|string|max:10',
            'delegado_nombre'   => 'nullable|string|max:180',
            'delegado_telefono' => 'nullable|string|max:30',
            'observaciones'     => 'nullable|string|max:300',
        ], [
            'nombre'            => 'nombre del equipo',
            'abreviatura'       => 'abreviatura',
            'delegado_nombre'   => 'nombre del delegado',
            'delegado_telefono' => 'teléfono del delegado',
            'observaciones'     => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes() && TorneoEquipo::nombreExiste($torneoId, (string) $limpio['nombre'])) {
            $errores[] = 'Ya existe un equipo con ese nombre en el torneo.';
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        try {
            $equipoId = TorneoEquipo::crear([
                'torneo_id'        => $torneoId,
                'nombre'           => (string) $limpio['nombre'],
                'abreviatura'      => (string) ($limpio['abreviatura'] ?? ''),
                'delegado_nombre'  => (string) ($limpio['delegado_nombre'] ?? ''),
                'delegado_telefono' => (string) ($limpio['delegado_telefono'] ?? ''),
                'observaciones'    => (string) ($limpio['observaciones'] ?? ''),
            ]);
        } catch (PDOException $exception) {
            if ($this->esDuplicado($exception)) {
                Session::flash('error', 'Ya existe un equipo con ese nombre en el torneo.');
                Response::redirect(url('/deportes/torneos/' . $torneoId));
            }

            Logger::error('Error al inscribir un equipo', [
                'torneo_id' => $torneoId,
                'error'     => $exception->getMessage(),
            ]);
            Session::flash('error', 'No se pudo inscribir el equipo. Intente nuevamente.');
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        Audit::log(
            Auth::id(),
            'crear_equipo_torneo',
            'torneo_equipo',
            $equipoId,
            'Equipo inscrito: ' . (string) $limpio['nombre']
        );

        Session::flash('exito', 'El equipo quedó inscrito.');
        Response::redirect(url('/deportes/torneos/' . $torneoId));
    }

    public function eliminarEquipo(Request $request, string $id, string $equipoId): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $torneo = $this->obtenerTorneo((int) $id);
        $torneoId = (int) $torneo['id'];
        $equipo = $this->obtenerEquipo((int) $equipoId, $torneoId);

        if ((string) $torneo['estado'] === 'anulado') {
            Session::flash('error', 'Un torneo anulado no admite modificaciones.');
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        if (Encuentro::contarPorTorneo($torneoId) > 0) {
            Session::flash('error', 'No se pueden eliminar equipos después de generar el fixture. Regenere el fixture primero.');
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        if (TorneoEquipo::tieneEncuentros((int) $equipo['id'])) {
            Session::flash('error', 'El equipo tiene encuentros registrados y no puede eliminarse.');
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        TorneoEquipo::eliminar((int) $equipo['id']);

        Audit::log(
            Auth::id(),
            'eliminar_equipo_torneo',
            'torneo_equipo',
            (int) $equipo['id'],
            'Equipo eliminado: ' . (string) $equipo['nombre']
        );

        Session::flash('exito', 'El equipo se eliminó del torneo.');
        Response::redirect(url('/deportes/torneos/' . $torneoId));
    }

    public function generarFixture(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $torneo = $this->obtenerTorneo((int) $id);
        $torneoId = (int) $torneo['id'];

        if ((string) $torneo['estado'] === 'anulado') {
            Session::flash('error', 'Un torneo anulado no admite modificaciones.');
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        $fechaInicio = $request->string('fecha_inicio');

        if ($fechaInicio === '') {
            $fechaInicio = (string) ($torneo['fecha_inicio'] ?? '');
        }

        if ($fechaInicio === '') {
            $fechaInicio = date('Y-m-d');
        }

        if (!$this->fechaValida($fechaInicio)) {
            Session::flash('error', 'Indique una fecha de inicio válida para el fixture.');
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        $reemplazar = $request->boolean('reemplazar');

        try {
            $partidos = Torneo::generarFixture($torneoId, $fechaInicio, $reemplazar, Auth::id());
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        } catch (Throwable $exception) {
            Logger::error('No se pudo generar el fixture', [
                'torneo_id' => $torneoId,
                'error'     => $exception->getMessage(),
            ]);
            Session::flash('error', 'No se pudo generar el fixture. Intente nuevamente.');
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        Audit::log(
            Auth::id(),
            'generar_fixture',
            'torneo',
            $torneoId,
            'Fixture generado con ' . $partidos . ' partidos'
        );

        Session::flash('exito', 'Se generaron ' . $partidos . ' partidos. Ya puede programar fechas, horas y resultados.');
        Response::redirect(url('/deportes/torneos/' . $torneoId));
    }

    public function editarEncuentro(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $encuentro = $this->obtenerEncuentro((int) $id);
        $torneo = $this->obtenerTorneo((int) $encuentro['torneo_id']);

        if ((string) $torneo['estado'] === 'anulado') {
            Session::flash('error', 'Un torneo anulado no admite modificaciones.');
            Response::redirect(url('/deportes/torneos/' . (int) $torneo['id']));
        }

        View::display('torneos/encuentro_form', [
            'titulo'        => 'Editar encuentro',
            'encuentro'     => $encuentro,
            'torneo'        => $torneo,
            'estados'       => Encuentro::ESTADOS,
            'instalaciones' => Instalacion::todas(),
        ]);
    }

    public function actualizarEncuentro(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $encuentro = $this->obtenerEncuentro((int) $id);
        $torneo = $this->obtenerTorneo((int) $encuentro['torneo_id']);
        $torneoId = (int) $torneo['id'];

        if ((string) $torneo['estado'] === 'anulado') {
            Session::flash('error', 'Un torneo anulado no admite modificaciones.');
            Response::redirect(url('/deportes/torneos/' . $torneoId));
        }

        $datos = $request->only([
            'instalacion_id',
            'fecha',
            'hora',
            'marcador_local',
            'marcador_visitante',
            'estado',
            'observaciones',
        ]);

        $validador = Validator::make($datos, [
            'instalacion_id'     => 'nullable|integer|exists:instalaciones,id',
            'fecha'              => 'required|date',
            'hora'               => 'nullable|regex:/^\d{2}:\d{2}$/',
            'marcador_local'     => 'nullable|integer',
            'marcador_visitante' => 'nullable|integer',
            'estado'             => 'required|in:programado,jugado,suspendido,cancelado,walkover',
            'observaciones'      => 'nullable|string|max:300',
        ], [
            'instalacion_id'     => 'sede',
            'fecha'              => 'fecha',
            'hora'               => 'hora',
            'marcador_local'     => 'marcador del equipo local',
            'marcador_visitante' => 'marcador del equipo visitante',
            'estado'             => 'estado del encuentro',
            'observaciones'      => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $local = $limpio['marcador_local'];
            $visitante = $limpio['marcador_visitante'];
            $estado = (string) $limpio['estado'];
            $hora = (string) ($limpio['hora'] ?? '');

            if ($hora !== '' && !$this->horaValida($hora)) {
                $errores[] = 'La hora del encuentro no es válida.';
            }

            foreach (['local' => $local, 'visitante' => $visitante] as $nombre => $marcador) {
                if ($marcador !== null && ((int) $marcador < 0 || (int) $marcador > 999)) {
                    $errores[] = 'El marcador del equipo ' . $nombre . ' debe estar entre 0 y 999.';
                }
            }

            if (in_array($estado, ['jugado', 'walkover'], true) && ($local === null || $visitante === null)) {
                $errores[] = 'Para marcar el encuentro como jugado o walkover debe registrar ambos marcadores.';
            }
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/deportes/encuentros/' . (int) $encuentro['id'] . '/editar'));
        }

        Encuentro::actualizar((int) $encuentro['id'], [
            'instalacion_id'     => $limpio['instalacion_id'],
            'fecha'              => (string) $limpio['fecha'],
            'hora'               => (string) ($limpio['hora'] ?? ''),
            'marcador_local'     => $limpio['marcador_local'],
            'marcador_visitante' => $limpio['marcador_visitante'],
            'estado'             => (string) $limpio['estado'],
            'observaciones'      => (string) ($limpio['observaciones'] ?? ''),
            'registrado_por'     => Auth::id(),
        ]);

        Audit::log(
            Auth::id(),
            'actualizar_encuentro',
            'encuentro',
            (int) $encuentro['id'],
            'Resultado actualizado: ' . (string) ($encuentro['local_nombre'] ?? '') . ' vs ' . (string) ($encuentro['visitante_nombre'] ?? '')
        );

        Session::flash('exito', 'El encuentro se actualizó.');
        Response::redirect(url('/deportes/torneos/' . $torneoId));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosTorneo(Request $request): array
    {
        return $request->only([
            'deporte_id',
            'nombre',
            'categoria',
            'modalidad',
            'reglamento',
            'fecha_inicio',
            'fecha_fin',
            'estado',
            'observaciones',
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validarTorneo(array $datos): array
    {
        $validador = Validator::make($datos, [
            'deporte_id'   => 'required|integer|exists:deportes,id',
            'nombre'       => 'required|string|min:3|max:150',
            'categoria'    => 'nullable|string|max:80',
            'modalidad'    => 'required|in:todos_contra_todos',
            'reglamento'   => 'nullable|string|max:5000',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date',
            'estado'       => 'required|in:planificado,en_curso,finalizado,anulado',
            'observaciones' => 'nullable|string|max:5000',
        ], [
            'deporte_id'   => 'disciplina',
            'nombre'       => 'nombre del torneo',
            'categoria'    => 'categoría',
            'modalidad'    => 'modalidad',
            'reglamento'   => 'reglamento',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin'    => 'fecha de fin',
            'estado'       => 'estado',
            'observaciones' => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $deporte = Deporte::find((int) $limpio['deporte_id']);

            if ($deporte === null || (int) $deporte['activo'] !== 1) {
                $errores[] = 'La disciplina seleccionada no está activa.';
            }

            $inicio = (string) ($limpio['fecha_inicio'] ?? '');
            $fin = (string) ($limpio['fecha_fin'] ?? '');

            if ($inicio !== '' && $fin !== '' && $fin < $inicio) {
                $errores[] = 'La fecha de fin no puede ser anterior a la fecha de inicio.';
            }
        }

        return [$limpio, $errores];
    }

    /**
     * @param array<string, mixed> $limpio
     * @return array<string, mixed>
     */
    private function registroTorneo(array $limpio): array
    {
        return [
            'deporte_id'   => (int) $limpio['deporte_id'],
            'nombre'       => (string) $limpio['nombre'],
            'categoria'    => (string) ($limpio['categoria'] ?? ''),
            'modalidad'    => (string) $limpio['modalidad'],
            'reglamento'   => (string) ($limpio['reglamento'] ?? ''),
            'fecha_inicio' => (string) ($limpio['fecha_inicio'] ?? ''),
            'fecha_fin'    => (string) ($limpio['fecha_fin'] ?? ''),
            'estado'       => (string) $limpio['estado'],
            'observaciones' => (string) ($limpio['observaciones'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerTorneo(int $id): array
    {
        $torneo = Torneo::findConDeporte($id);

        if ($torneo === null) {
            abort(404, 'El torneo indicado no existe.');
        }

        return $torneo;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerEquipo(int $equipoId, int $torneoId): array
    {
        $equipo = TorneoEquipo::findDeTorneo($equipoId, $torneoId);

        if ($equipo === null) {
            abort(404, 'El equipo indicado no existe en este torneo.');
        }

        return $equipo;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerEncuentro(int $id): array
    {
        $encuentro = Encuentro::findConEquipos($id);

        if ($encuentro === null) {
            abort(404, 'El encuentro indicado no existe.');
        }

        return $encuentro;
    }

    private function fechaValida(string $fecha): bool
    {
        $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $valor instanceof DateTimeImmutable && $valor->format('Y-m-d') === $fecha;
    }

    private function horaValida(string $hora): bool
    {
        $valor = DateTimeImmutable::createFromFormat('!H:i', $hora);

        return $valor instanceof DateTimeImmutable && $valor->format('H:i') === $hora;
    }

    private function esDuplicado(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return is_array($errorInfo) && (int) ($errorInfo[1] ?? 0) === 1062;
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
