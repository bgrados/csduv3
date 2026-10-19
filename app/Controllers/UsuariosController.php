<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Password;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Rol;
use App\Models\Usuario;

/**
 * Administración de usuarios del sistema.
 *
 * Crear, editar, activar/desactivar y restablecer contraseñas. Todas las rutas
 * exigen el permiso correspondiente en app/routes.php.
 */
final class UsuariosController
{
    private const POR_PAGINA = 20;

    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $busqueda = $request->string('q');
        $busqueda = $busqueda === '' ? null : $busqueda;
        $pagina = max(1, $request->integer('pagina', 1));
        $total = Usuario::total($busqueda);

        View::display('usuarios/index', [
            'titulo'   => 'Usuarios',
            'usuarios' => Usuario::listar($busqueda, self::POR_PAGINA, ($pagina - 1) * self::POR_PAGINA),
            'busqueda' => $busqueda ?? '',
            'total'    => $total,
            'pagina'   => $pagina,
            'paginas'  => max(1, (int) ceil($total / self::POR_PAGINA)),
        ]);
    }

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('usuarios/form', [
            'titulo'  => 'Nuevo usuario',
            'usuario' => null,
            'roles'   => Rol::todos(),
            'modo'    => 'crear',
        ]);
    }

    public function crear(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $request->only([
            'nombres',
            'apellidos',
            'usuario',
            'email',
            'rol_id',
            'telefono',
            'cargo_institucional',
            'password',
            'password_confirmacion',
        ]);

        $validador = Validator::make($datos, [
            'nombres'               => 'required|string|min:2|max:100',
            'apellidos'             => 'required|string|min:2|max:100',
            'usuario'               => 'required|string|min:3|max:60|regex:/^[A-Za-z0-9._-]+$/|unique:usuarios,usuario',
            'email'                 => 'nullable|email|max:160|unique:usuarios,email',
            'rol_id'                => 'required|integer|exists:roles,id',
            'telefono'              => 'nullable|string|max:30',
            'cargo_institucional'   => 'nullable|string|max:120',
            'password'              => 'required|string',
            'password_confirmacion' => 'required|same:password',
        ], [
            'nombres'               => 'los nombres',
            'apellidos'             => 'los apellidos',
            'usuario'               => 'el nombre de usuario',
            'email'                 => 'el correo electrónico',
            'rol_id'                => 'el rol',
            'password'              => 'la contraseña',
            'password_confirmacion' => 'la confirmación de contraseña',
        ]);

        $errores = array_values($validador->errors());

        if (!$validador->fails()) {
            foreach (Password::errores((string) $datos['password']) as $error) {
                $errores[] = $error;
            }
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);

            Response::redirect(url('/usuarios/nuevo'));
        }

        $limpio = $validador->validated();

        $id = Usuario::crear([
            'nombres'               => (string) $limpio['nombres'],
            'apellidos'             => (string) $limpio['apellidos'],
            'usuario'               => (string) $limpio['usuario'],
            'email'                 => (string) ($limpio['email'] ?? ''),
            'password_hash'         => password_hash((string) $datos['password'], PASSWORD_DEFAULT),
            'rol_id'                => (int) $limpio['rol_id'],
            'telefono'              => (string) ($limpio['telefono'] ?? ''),
            'cargo_institucional'   => (string) ($limpio['cargo_institucional'] ?? ''),
            'activo'                => 1,
            'debe_cambiar_password' => 1,
        ]);

        Audit::log(Auth::id(), 'crear_usuario', 'usuario', $id, 'Usuario creado: ' . $limpio['usuario']);

        Session::flash('exito', 'Usuario creado. La persona deberá cambiar la contraseña en su primer ingreso.');
        Response::redirect(url('/usuarios'));
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $usuario = Usuario::findConRol((int) $id);

        if ($usuario === null) {
            abort(404, 'El usuario solicitado no existe.');
        }

        View::display('usuarios/form', [
            'titulo'  => 'Editar usuario',
            'usuario' => $usuario,
            'roles'   => Rol::todos(),
            'modo'    => 'editar',
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $usuarioId = (int) $id;
        $usuario = Usuario::findConRol($usuarioId);

        if ($usuario === null) {
            abort(404, 'El usuario solicitado no existe.');
        }

        $datos = $request->only([
            'nombres',
            'apellidos',
            'usuario',
            'email',
            'rol_id',
            'telefono',
            'cargo_institucional',
        ]);

        $datos['activo'] = $request->boolean('activo') ? '1' : '0';

        $validador = Validator::make($datos, [
            'nombres'             => 'required|string|min:2|max:100',
            'apellidos'           => 'required|string|min:2|max:100',
            'usuario'             => 'required|string|min:3|max:60|regex:/^[A-Za-z0-9._-]+$/|unique:usuarios,usuario,' . $usuarioId,
            'email'               => 'nullable|email|max:160|unique:usuarios,email,' . $usuarioId,
            'rol_id'              => 'required|integer|exists:roles,id',
            'telefono'            => 'nullable|string|max:30',
            'cargo_institucional' => 'nullable|string|max:120',
            'activo'              => 'required|in:0,1',
        ], [
            'nombres'   => 'los nombres',
            'apellidos' => 'los apellidos',
            'usuario'   => 'el nombre de usuario',
            'email'     => 'el correo electrónico',
            'rol_id'    => 'el rol',
        ]);

        $errores = array_values($validador->errors());

        // Protecciones contra el bloqueo accidental del propio acceso.
        $esPropio = $usuarioId === Auth::id();

        if ($esPropio && (int) $datos['rol_id'] !== (int) $usuario['rol_id']) {
            $errores[] = 'No puede cambiar su propio rol. Pida a otro administrador que lo haga.';
        }

        if ($esPropio && $datos['activo'] === '0') {
            $errores[] = 'No puede desactivar su propia cuenta.';
        }

        if ($datos['activo'] === '0' && $this->esUltimoAdministrador($usuarioId)) {
            $errores[] = 'No puede desactivar al último administrador activo del sistema.';
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);

            Response::redirect(url('/usuarios/' . $usuarioId . '/editar'));
        }

        $limpio = $validador->validated();

        Usuario::actualizar($usuarioId, [
            'nombres'             => (string) $limpio['nombres'],
            'apellidos'           => (string) $limpio['apellidos'],
            'usuario'             => (string) $limpio['usuario'],
            'email'               => (string) ($limpio['email'] ?? ''),
            'rol_id'              => (int) $limpio['rol_id'],
            'telefono'            => (string) ($limpio['telefono'] ?? ''),
            'cargo_institucional' => (string) ($limpio['cargo_institucional'] ?? ''),
            'activo'              => (int) $limpio['activo'],
        ]);

        Audit::log(Auth::id(), 'editar_usuario', 'usuario', $usuarioId, 'Usuario actualizado: ' . $limpio['usuario']);

        Session::flash('exito', 'Los datos del usuario se actualizaron.');
        Response::redirect(url('/usuarios'));
    }

    public function cambiarEstado(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $usuarioId = (int) $id;
        $usuario = Usuario::findConRol($usuarioId);

        if ($usuario === null) {
            abort(404, 'El usuario solicitado no existe.');
        }

        $activar = $request->string('accion') === 'activar';

        if (!$activar) {
            if ($usuarioId === Auth::id()) {
                Session::flash('error', 'No puede desactivar su propia cuenta.');
                Response::redirect(url('/usuarios'));
            }

            if ($this->esUltimoAdministrador($usuarioId)) {
                Session::flash('error', 'No puede desactivar al último administrador activo del sistema.');
                Response::redirect(url('/usuarios'));
            }
        }

        Usuario::cambiarEstado($usuarioId, $activar);

        Audit::log(
            Auth::id(),
            $activar ? 'activar_usuario' : 'desactivar_usuario',
            'usuario',
            $usuarioId,
            ($activar ? 'Usuario activado: ' : 'Usuario desactivado: ') . $usuario['usuario']
        );

        Session::flash('exito', $activar ? 'La cuenta quedó activa.' : 'La cuenta quedó desactivada.');
        Response::redirect(url('/usuarios'));
    }

    public function restablecerPassword(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $usuarioId = (int) $id;
        $usuario = Usuario::findConRol($usuarioId);

        if ($usuario === null) {
            abort(404, 'El usuario solicitado no existe.');
        }

        $password = $request->input('password', '');
        $confirmacion = $request->input('password_confirmacion', '');
        $password = is_string($password) ? $password : '';
        $confirmacion = is_string($confirmacion) ? $confirmacion : '';

        $errores = [];

        if ($password !== $confirmacion) {
            $errores[] = 'La nueva contraseña y su confirmación no coinciden.';
        }

        foreach (Password::errores($password) as $error) {
            $errores[] = $error;
        }

        if ($errores !== []) {
            Session::flash('errores', $errores);
            Response::redirect(url('/usuarios/' . $usuarioId . '/editar'));
        }

        Usuario::cambiarPassword($usuarioId, $password, true);

        Audit::log(
            Auth::id(),
            'restablecer_password',
            'usuario',
            $usuarioId,
            'Contraseña restablecida para: ' . $usuario['usuario']
        );

        Session::flash('exito', 'Contraseña restablecida. La persona deberá cambiarla en su siguiente ingreso.');
        Response::redirect(url('/usuarios/' . $usuarioId . '/editar'));
    }

    private function esUltimoAdministrador(int $usuarioId): bool
    {
        $usuario = Usuario::findConRol($usuarioId);

        if ($usuario === null || (string) ($usuario['rol_clave'] ?? '') !== 'administrador') {
            return false;
        }

        return Usuario::contarAdministradoresActivos($usuarioId) === 0;
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
