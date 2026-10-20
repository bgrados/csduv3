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
use App\Core\Uploads;
use App\Core\Validator;
use App\Core\View;
use App\Models\Instalacion;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Administración de ambientes e instalaciones.
 *
 * La publicación en el portal público se controla con el campo `activo` y con
 * las tarifas vigentes de cada ambiente. Las operaciones exigen el permiso
 * `alquileres.tarifas`, comprobado en app/routes.php.
 */
final class AmbientesController
{
    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $busqueda = $request->string('q');
        $estado = $request->string('estado');
        $estado = in_array($estado, ['activo', 'inactivo'], true) ? $estado : '';

        View::display('ambientes/index', [
            'titulo'       => 'Ambientes y tarifas',
            'instalaciones' => Instalacion::listarConResumen($busqueda === '' ? null : $busqueda, $estado),
            'busqueda'     => $busqueda,
            'estado'       => $estado,
            'tipos'        => Instalacion::TIPOS,
        ]);
    }

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('ambientes/form', [
            'titulo'      => 'Nuevo ambiente',
            'instalacion' => null,
            'modo'        => 'crear',
            'tipos'       => Instalacion::TIPOS,
        ]);
    }

    public function crear(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos, null);

        $foto = $this->recibirFoto($request, $errores);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/ambientes/nuevo'));
        }

        try {
            $id = Instalacion::crear($this->registro($limpio, $foto));
        } catch (PDOException $exception) {
            if ($this->esDuplicado($exception)) {
                remember_old_input($datos);
                Session::flash('error', 'Ya existe un ambiente con ese nombre.');
                Response::redirect(url('/ambientes/nuevo'));
            }

            Logger::error('Error al crear un ambiente', ['error' => $exception->getMessage()]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo crear el ambiente. Intente nuevamente.');
            Response::redirect(url('/ambientes/nuevo'));
        }

        Audit::log(Auth::id(), 'crear_ambiente', 'instalacion', $id, 'Ambiente creado: ' . (string) $limpio['nombre']);

        Session::flash('exito', 'El ambiente quedó creado. Publíquelo con una tarifa vigente para que aparezca en el portal.');
        Response::redirect(url('/ambientes'));
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $instalacion = $this->obtener((int) $id);

        View::display('ambientes/form', [
            'titulo'      => 'Editar ambiente',
            'instalacion' => $instalacion,
            'modo'        => 'editar',
            'tipos'       => Instalacion::TIPOS,
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $instalacion = $this->obtener((int) $id);
        $instalacionId = (int) $instalacion['id'];
        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos, $instalacionId);

        $foto = $this->recibirFoto($request, $errores);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/ambientes/' . $instalacionId . '/editar'));
        }

        $rutaAnterior = (string) ($instalacion['archivo'] ?? '');

        try {
            Instalacion::actualizar($instalacionId, $this->registro($limpio, $foto));
        } catch (PDOException $exception) {
            if ($this->esDuplicado($exception)) {
                remember_old_input($datos);
                Session::flash('error', 'Ya existe otro ambiente con ese nombre.');
                Response::redirect(url('/ambientes/' . $instalacionId . '/editar'));
            }

            Logger::error('Error al actualizar un ambiente', [
                'instalacion_id' => $instalacionId,
                'error'          => $exception->getMessage(),
            ]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo guardar el ambiente. Intente nuevamente.');
            Response::redirect(url('/ambientes/' . $instalacionId . '/editar'));
        }

        Audit::log(
            Auth::id(),
            'editar_ambiente',
            'instalacion',
            $instalacionId,
            'Ambiente actualizado: ' . (string) $limpio['nombre']
        );

        if ($foto !== null && $rutaAnterior !== '') {
            Uploads::borrar($rutaAnterior, 'ambientes/');
        }

        Session::flash('exito', 'El ambiente se actualizó.');
        Response::redirect(url('/ambientes'));
    }

    public function cambiarEstado(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $instalacion = $this->obtener((int) $id);
        $accion = $request->string('accion');

        if (!in_array($accion, ['activar', 'desactivar'], true)) {
            Session::flash('error', 'La acción solicitada no es válida.');
            Response::redirect(url('/ambientes'));
        }

        $activar = $accion === 'activar';

        try {
            Instalacion::cambiarActivo((int) $instalacion['id'], $activar);
        } catch (Throwable $exception) {
            Logger::error('Error al cambiar el estado de un ambiente', [
                'instalacion_id' => (int) $instalacion['id'],
                'error'          => $exception->getMessage(),
            ]);
            Session::flash('error', 'No se pudo cambiar el estado del ambiente.');
            Response::redirect(url('/ambientes'));
        }

        Audit::log(
            Auth::id(),
            $activar ? 'activar_ambiente' : 'desactivar_ambiente',
            'instalacion',
            (int) $instalacion['id'],
            ($activar ? 'Ambiente activado: ' : 'Ambiente desactivado: ') . (string) $instalacion['nombre']
        );

        Session::flash('exito', $activar ? 'El ambiente quedó activo.' : 'El ambiente quedó desactivado y ya no se publica.');
        Response::redirect(url('/ambientes'));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        $datos = $request->only([
            'nombre',
            'tipo',
            'descripcion',
            'ubicacion',
            'aforo',
        ]);

        $datos['requiere_adelanto'] = $request->boolean('requiere_adelanto') ? '1' : '0';
        $datos['activo'] = $request->boolean('activo') ? '1' : '0';

        return $datos;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validar(array $datos, ?int $instalacionId): array
    {
        $validador = Validator::make($datos, [
            'nombre'            => 'required|string|min:3|max:120',
            'tipo'              => 'required|in:ambiente,losa,cancha,salon,otro',
            'descripcion'       => 'nullable|string|max:300',
            'ubicacion'         => 'nullable|string|max:150',
            'aforo'             => 'nullable|integer',
            'requiere_adelanto' => 'required|in:0,1',
            'activo'            => 'required|in:0,1',
        ], [
            'nombre'      => 'nombre del ambiente',
            'tipo'        => 'tipo de ambiente',
            'descripcion' => 'descripción',
            'ubicacion'   => 'ubicación',
            'aforo'       => 'aforo',
            'requiere_adelanto' => 'requiere adelanto',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes() && Instalacion::nombreExiste((string) $limpio['nombre'], $instalacionId)) {
            $errores[] = 'Ya existe un ambiente con ese nombre.';
        }

        $aforo = $limpio['aforo'] ?? null;

        if ($aforo !== null && ((int) $aforo < 1 || (int) $aforo > 5000)) {
            $errores[] = 'El aforo debe estar entre 1 y 5000 personas.';
        }

        return [$limpio, $errores];
    }

    /**
     * @param array<string, mixed> $limpio
     * @param array<string, mixed>|null $foto
     * @return array<string, mixed>
     */
    private function registro(array $limpio, ?array $foto = null): array
    {
        $aforo = $limpio['aforo'] ?? null;

        return [
            'nombre'            => (string) $limpio['nombre'],
            'tipo'              => (string) $limpio['tipo'],
            'descripcion'       => (string) ($limpio['descripcion'] ?? ''),
            'ubicacion'         => (string) ($limpio['ubicacion'] ?? ''),
            'aforo'             => $aforo === null ? null : (int) $aforo,
            'requiere_adelanto' => (int) ($limpio['requiere_adelanto'] ?? 0),
            'activo'            => (int) ($limpio['activo'] ?? 1),
            'archivo'           => $foto === null ? '' : (string) $foto['ruta'],
            'nombre_original'   => $foto === null ? '' : (string) $foto['nombre_original'],
            'tipo_mime'         => $foto === null ? '' : (string) $foto['tipo_mime'],
            'tamano_bytes'      => $foto === null ? 0 : (int) $foto['tamano'],
        ];
    }

    /**
     * Recibe la foto del ambiente y la guarda en WebP.
     *
     * @param array<int, string> $errores
     * @return array{ruta: string, nombre_original: string, tipo_mime: string, tamano: int}|null
     */
    private function recibirFoto(Request $request, array &$errores): ?array
    {
        $archivo = $request->file('foto');

        if ($archivo === null || (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        try {
            $guardado = Uploads::guardar($archivo, 'ambientes');
        } catch (RuntimeException $exception) {
            $errores[] = $exception->getMessage();
            return null;
        } catch (Throwable $exception) {
            Logger::error('Error al subir la foto de un ambiente', ['error' => $exception->getMessage()]);
            $errores[] = 'No se pudo guardar la foto del ambiente.';
            return null;
        }

        if (!Uploads::esImagen((string) $guardado['tipo_mime'])) {
            Uploads::borrar((string) $guardado['ruta'], 'ambientes/');
            $errores[] = 'La foto debe ser una imagen (JPG, PNG o WEBP).';

            return null;
        }

        return $guardado;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtener(int $id): array
    {
        $instalacion = Instalacion::find($id);

        if ($instalacion === null) {
            abort(404, 'El ambiente indicado no existe.');
        }

        return $instalacion;
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
