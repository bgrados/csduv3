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
use App\Models\Directiva;
use App\Models\DirectivaCargo;
use App\Models\DirectivaMiembro;
use App\Models\Socio;
use DateTimeImmutable;
use Throwable;

/**
 * Junta directiva: periodos de gestión, cargos e integrantes.
 *
 * El club define sus cargos según el estatuto. Los integrantes pueden ser
 * socios registrados o personas externas; el histórico se conserva.
 *
 * Los permisos (`directiva.ver`, `directiva.gestionar`) se comprueban en
 * app/routes.php.
 */
final class DirectivaController
{
    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $directivas = Directiva::todas();
        $vigente = null;

        foreach ($directivas as $directiva) {
            if ((string) $directiva['estado'] === 'vigente') {
                $vigente = $directiva;
                break;
            }
        }

        View::display('directiva/index', [
            'titulo'     => 'Junta directiva',
            'directivas' => $directivas,
            'vigente'    => $vigente,
            'estados'    => Directiva::ESTADOS,
        ]);
    }

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('directiva/form', [
            'titulo'    => 'Nuevo periodo de gestión',
            'directiva' => null,
            'modo'      => 'crear',
            'estados'   => Directiva::ESTADOS,
        ]);
    }

    public function crear(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $this->datosPeriodo($request);
        [$limpio, $errores] = $this->validarPeriodo($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/directiva/nuevo'));
        }

        try {
            $id = Directiva::crear([
                'nombre'        => (string) $limpio['nombre'],
                'fecha_inicio'  => (string) $limpio['fecha_inicio'],
                'fecha_fin'     => (string) ($limpio['fecha_fin'] ?? ''),
                'estado'        => (string) $limpio['estado'],
                'observaciones' => (string) ($limpio['observaciones'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Error al crear un periodo de gestión', ['error' => $exception->getMessage()]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo crear el periodo de gestión.');
            Response::redirect(url('/directiva/nuevo'));
        }

        Audit::log(Auth::id(), 'crear_directiva', 'directiva', $id, 'Periodo de gestión creado: ' . (string) $limpio['nombre']);

        Session::flash('exito', 'El periodo de gestión quedó creado.');
        Response::redirect(url('/directiva/' . $id));
    }

    public function ver(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $directiva = $this->obtener((int) $id);

        View::display('directiva/ver', [
            'titulo'    => 'Junta directiva',
            'directiva' => $directiva,
            'miembros'  => DirectivaMiembro::listarPorDirectiva((int) $directiva['id']),
            'cargos'    => DirectivaCargo::activos(),
            'estados'   => Directiva::ESTADOS,
            'estadosMiembro' => DirectivaMiembro::ESTADOS,
        ]);
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $directiva = $this->obtener((int) $id);

        View::display('directiva/form', [
            'titulo'    => 'Editar periodo de gestión',
            'directiva' => $directiva,
            'modo'      => 'editar',
            'estados'   => Directiva::ESTADOS,
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $directiva = $this->obtener((int) $id);
        $datos = $this->datosPeriodo($request);
        [$limpio, $errores] = $this->validarPeriodo($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/directiva/' . (int) $directiva['id'] . '/editar'));
        }

        Directiva::actualizar((int) $directiva['id'], [
            'nombre'        => (string) $limpio['nombre'],
            'fecha_inicio'  => (string) $limpio['fecha_inicio'],
            'fecha_fin'     => (string) ($limpio['fecha_fin'] ?? ''),
            'estado'        => (string) $limpio['estado'],
            'observaciones' => (string) ($limpio['observaciones'] ?? ''),
        ]);

        Audit::log(Auth::id(), 'editar_directiva', 'directiva', (int) $directiva['id'], 'Periodo de gestión actualizado: ' . (string) $limpio['nombre']);

        Session::flash('exito', 'El periodo de gestión se actualizó.');
        Response::redirect(url('/directiva/' . (int) $directiva['id']));
    }

    public function cambiarEstado(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $directiva = $this->obtener((int) $id);
        $estado = $request->string('estado');

        if (!isset(Directiva::ESTADOS[$estado])) {
            Session::flash('error', 'El estado indicado no es válido.');
            Response::redirect(url('/directiva/' . (int) $directiva['id']));
        }

        Directiva::cambiarEstado((int) $directiva['id'], $estado);

        Audit::log(
            Auth::id(),
            'cambiar_estado_directiva',
            'directiva',
            (int) $directiva['id'],
            'Periodo de gestión pasó a ' . Directiva::ESTADOS[$estado]
        );

        Session::flash('exito', 'El estado del periodo se actualizó.');
        Response::redirect(url('/directiva/' . (int) $directiva['id']));
    }

    public function agregarMiembro(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $directiva = $this->obtener((int) $id);
        $directivaId = (int) $directiva['id'];

        $datos = $this->datosMiembro($request);
        [$limpio, $errores] = $this->validarMiembro($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/directiva/' . $directivaId));
        }

        $socio = $this->socioPorCodigo((string) ($limpio['socio_codigo'] ?? ''));

        try {
            $miembroId = DirectivaMiembro::crear([
                'directiva_id'    => $directivaId,
                'cargo_id'        => (int) $limpio['cargo_id'],
                'socio_id'        => $socio === null ? 0 : (int) $socio['id'],
                'nombre_completo' => $socio === null ? (string) ($limpio['nombre_completo'] ?? '') : '',
                'documento'       => (string) ($limpio['documento'] ?? ''),
                'telefono'        => (string) ($limpio['telefono'] ?? ''),
                'email'           => (string) ($limpio['email'] ?? ''),
                'fecha_inicio'    => (string) ($limpio['fecha_inicio'] ?? ''),
                'fecha_fin'       => (string) ($limpio['fecha_fin'] ?? ''),
                'estado'          => (string) $limpio['estado'],
                'observaciones'   => (string) ($limpio['observaciones'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Error al registrar un integrante', [
                'directiva_id' => $directivaId,
                'error'        => $exception->getMessage(),
            ]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo registrar el integrante.');
            Response::redirect(url('/directiva/' . $directivaId));
        }

        Audit::log(
            Auth::id(),
            'agregar_directiva_miembro',
            'directiva_miembro',
            $miembroId,
            'Integrante registrado en la junta directiva'
        );

        Session::flash('exito', 'El integrante quedó registrado.');
        Response::redirect(url('/directiva/' . $directivaId));
    }

    public function editarMiembro(Request $request, string $id, string $miembroId): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $directiva = $this->obtener((int) $id);
        $miembro = $this->obtenerMiembro((int) $miembroId, (int) $directiva['id']);

        View::display('directiva/miembro_form', [
            'titulo'    => 'Editar integrante',
            'directiva' => $directiva,
            'miembro'   => $miembro,
            'modo'      => 'editar',
            'cargos'    => DirectivaCargo::activos(),
            'estados'   => DirectivaMiembro::ESTADOS,
            'socios'    => Socio::listar(null, 'activo', 0, 100, 0),
        ]);
    }

    public function actualizarMiembro(Request $request, string $id, string $miembroId): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $directiva = $this->obtener((int) $id);
        $directivaId = (int) $directiva['id'];
        $miembro = $this->obtenerMiembro((int) $miembroId, $directivaId);

        $datos = $this->datosMiembro($request);
        [$limpio, $errores] = $this->validarMiembro($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/directiva/' . $directivaId . '/miembros/' . (int) $miembro['id'] . '/editar'));
        }

        $socio = $this->socioPorCodigo((string) ($limpio['socio_codigo'] ?? ''));

        DirectivaMiembro::actualizar((int) $miembro['id'], [
            'cargo_id'        => (int) $limpio['cargo_id'],
            'socio_id'        => $socio === null ? 0 : (int) $socio['id'],
            'nombre_completo' => $socio === null ? (string) ($limpio['nombre_completo'] ?? '') : '',
            'documento'       => (string) ($limpio['documento'] ?? ''),
            'telefono'        => (string) ($limpio['telefono'] ?? ''),
            'email'           => (string) ($limpio['email'] ?? ''),
            'fecha_inicio'    => (string) ($limpio['fecha_inicio'] ?? ''),
            'fecha_fin'       => (string) ($limpio['fecha_fin'] ?? ''),
            'estado'          => (string) $limpio['estado'],
            'observaciones'   => (string) ($limpio['observaciones'] ?? ''),
        ]);

        Audit::log(
            Auth::id(),
            'editar_directiva_miembro',
            'directiva_miembro',
            (int) $miembro['id'],
            'Integrante actualizado'
        );

        Session::flash('exito', 'El integrante se actualizó.');
        Response::redirect(url('/directiva/' . $directivaId));
    }

    public function estadoMiembro(Request $request, string $id, string $miembroId): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $directiva = $this->obtener((int) $id);
        $directivaId = (int) $directiva['id'];
        $miembro = $this->obtenerMiembro((int) $miembroId, $directivaId);

        $estado = $request->string('estado');

        if (!isset(DirectivaMiembro::ESTADOS[$estado])) {
            Session::flash('error', 'El estado indicado no es válido.');
            Response::redirect(url('/directiva/' . $directivaId));
        }

        DirectivaMiembro::cambiarEstado((int) $miembro['id'], $estado);

        Audit::log(
            Auth::id(),
            'cambiar_estado_directiva_miembro',
            'directiva_miembro',
            (int) $miembro['id'],
            'Integrante pasó a ' . DirectivaMiembro::ESTADOS[$estado]
        );

        Session::flash('exito', 'El estado del integrante se actualizó.');
        Response::redirect(url('/directiva/' . $directivaId));
    }

    public function cargos(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('directiva/cargos', [
            'titulo' => 'Cargos de la junta directiva',
            'cargos' => DirectivaCargo::todos(),
        ]);
    }

    public function guardarCargo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $request->only(['nombre', 'descripcion', 'orden']);
        $validador = Validator::make($datos, [
            'nombre'      => 'required|string|min:2|max:80',
            'descripcion' => 'nullable|string|max:200',
            'orden'       => 'nullable|integer|min:1|max:999',
        ], [
            'nombre'      => 'nombre del cargo',
            'descripcion' => 'descripción',
            'orden'       => 'orden',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes() && DirectivaCargo::nombreExiste((string) $limpio['nombre'])) {
            $errores[] = 'Ya existe un cargo con ese nombre.';
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/directiva/cargos'));
        }

        DirectivaCargo::crear([
            'nombre'      => (string) $limpio['nombre'],
            'descripcion' => (string) ($limpio['descripcion'] ?? ''),
            'orden'       => (int) ($limpio['orden'] ?? 1),
            'activo'      => 1,
        ]);

        Audit::log(Auth::id(), 'crear_cargo_directiva', 'directiva_cargo', null, 'Cargo creado: ' . (string) $limpio['nombre']);

        Session::flash('exito', 'El cargo quedó creado.');
        Response::redirect(url('/directiva/cargos'));
    }

    public function editarCargo(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $cargo = DirectivaCargo::find((int) $id);

        if ($cargo === null) {
            Session::flash('error', 'El cargo indicado no existe.');
            Response::redirect(url('/directiva/cargos'));
        }

        $datos = $request->only(['nombre', 'descripcion', 'orden']);
        $validador = Validator::make($datos, [
            'nombre'      => 'required|string|min:2|max:80',
            'descripcion' => 'nullable|string|max:200',
            'orden'       => 'nullable|integer|min:1|max:999',
        ], [
            'nombre'      => 'nombre del cargo',
            'descripcion' => 'descripción',
            'orden'       => 'orden',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes() && DirectivaCargo::nombreExiste((string) $limpio['nombre'], (int) $cargo['id'])) {
            $errores[] = 'Ya existe un cargo con ese nombre.';
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/directiva/cargos'));
        }

        DirectivaCargo::actualizar((int) $cargo['id'], [
            'nombre'      => (string) $limpio['nombre'],
            'descripcion' => (string) ($limpio['descripcion'] ?? ''),
            'orden'       => (int) ($limpio['orden'] ?? 1),
            'activo'      => (int) $cargo['activo'],
        ]);

        Session::flash('exito', 'El cargo se actualizó.');
        Response::redirect(url('/directiva/cargos'));
    }

    public function estadoCargo(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $cargo = DirectivaCargo::find((int) $id);

        if ($cargo === null) {
            Session::flash('error', 'El cargo indicado no existe.');
            Response::redirect(url('/directiva/cargos'));
        }

        $activar = $request->string('accion') === 'activar';

        DirectivaCargo::cambiarActivo((int) $cargo['id'], $activar);

        Session::flash('exito', $activar ? 'El cargo quedó activo.' : 'El cargo quedó desactivado.');
        Response::redirect(url('/directiva/cargos'));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosPeriodo(Request $request): array
    {
        return $request->only(['nombre', 'fecha_inicio', 'fecha_fin', 'estado', 'observaciones']);
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validarPeriodo(array $datos): array
    {
        $validador = Validator::make($datos, [
            'nombre'        => 'required|string|min:3|max:120',
            'fecha_inicio'  => 'required|date',
            'fecha_fin'     => 'nullable|date',
            'estado'        => 'required|in:vigente,concluida,anulada',
            'observaciones' => 'nullable|string|max:2000',
        ], [
            'nombre'        => 'nombre del periodo',
            'fecha_inicio'  => 'fecha de inicio',
            'fecha_fin'     => 'fecha de fin',
            'estado'        => 'estado',
            'observaciones' => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $inicio = (string) $limpio['fecha_inicio'];
            $fin = (string) ($limpio['fecha_fin'] ?? '');

            if ($fin !== '' && $fin < $inicio) {
                $errores[] = 'La fecha de fin no puede ser anterior a la de inicio.';
            }
        }

        return [$limpio, $errores];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosMiembro(Request $request): array
    {
        return $request->only([
            'cargo_id',
            'socio_codigo',
            'nombre_completo',
            'documento',
            'telefono',
            'email',
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
    private function validarMiembro(array $datos): array
    {
        $validador = Validator::make($datos, [
            'cargo_id'        => 'required|integer|exists:directiva_cargos,id',
            'socio_codigo'    => 'nullable|string|max:30',
            'nombre_completo' => 'nullable|string|max:180',
            'documento'       => 'nullable|string|max:30',
            'telefono'        => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:160',
            'fecha_inicio'    => 'nullable|date',
            'fecha_fin'       => 'nullable|date',
            'estado'          => 'required|in:vigente,cesado,renuncia',
            'observaciones'   => 'nullable|string|max:300',
        ], [
            'cargo_id'        => 'cargo',
            'socio_codigo'    => 'código de socio',
            'nombre_completo' => 'nombre completo',
            'documento'       => 'documento',
            'telefono'        => 'teléfono',
            'email'           => 'correo electrónico',
            'fecha_inicio'    => 'fecha de inicio',
            'fecha_fin'       => 'fecha de fin',
            'estado'          => 'estado',
            'observaciones'   => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $codigo = trim((string) ($limpio['socio_codigo'] ?? ''));
            $nombre = trim((string) ($limpio['nombre_completo'] ?? ''));

            if ($codigo === '' && mb_strlen($nombre) < 3) {
                $errores[] = 'Indique el código de un socio o el nombre completo de la persona.';
            }

            if ($codigo !== '' && $this->socioPorCodigo($codigo) === null) {
                $errores[] = 'No existe un socio con ese código.';
            }

            $inicio = (string) ($limpio['fecha_inicio'] ?? '');
            $fin = (string) ($limpio['fecha_fin'] ?? '');

            if ($inicio !== '' && $fin !== '' && $fin < $inicio) {
                $errores[] = 'La fecha de fin no puede ser anterior a la de inicio.';
            }
        }

        return [$limpio, $errores];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function socioPorCodigo(string $codigo): ?array
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        return Socio::findPorCodigo($codigo);
    }

    /**
     * @return array<string, mixed>
     */
    private function obtener(int $id): array
    {
        $directiva = Directiva::find($id);

        if ($directiva === null) {
            abort(404, 'El periodo de gestión indicado no existe.');
        }

        return $directiva;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerMiembro(int $miembroId, int $directivaId): array
    {
        $miembro = DirectivaMiembro::find($miembroId);

        if ($miembro === null || (int) $miembro['directiva_id'] !== $directivaId) {
            abort(404, 'El integrante indicado no existe en este periodo de gestión.');
        }

        return $miembro;
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
