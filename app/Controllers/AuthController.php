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
use App\Core\View;
use App\Models\Usuario;

/**
 * Inicio y cierre de sesión, y cambio de la contraseña propia.
 */
final class AuthController
{
    public function formulario(Request $request): void
    {
        View::display('auth/login', [
            'titulo' => 'Ingresar',
        ], 'layouts/publico');
    }

    public function ingresar(Request $request): void
    {
        if (!Database::isAvailable()) {
            Session::flash('error', 'La base de datos no está disponible en este momento. Contacte al administrador del sistema.');
            Response::redirect(url('/ingresar'));
        }

        $login = $request->string('usuario');
        $password = $this->texto($request, 'password');

        $resultado = Auth::attempt($login, $password, $request->ip(), $request->userAgent());

        if (!$resultado['ok']) {
            remember_old_input(['usuario' => $login]);
            Session::flash('error', $resultado['mensaje']);
            Response::redirect(url('/ingresar'));
        }

        $usuario = Auth::user();
        $nombre = $usuario === null ? '' : Usuario::nombreCompleto($usuario);
        $intended = Session::get('_intended_url');
        Session::forget('_intended_url');

        Session::flash('exito', $nombre === '' ? 'Sesión iniciada correctamente.' : 'Bienvenido, ' . $nombre . '.');

        if (is_string($intended) && $intended !== '' && $intended !== '/' && $intended !== '/ingresar') {
            Response::redirect(url($intended));
        }

        Response::redirect(url('/panel'));
    }

    public function salir(Request $request): void
    {
        Auth::logout();
        Response::redirect(url('/'));
    }

    public function perfil(Request $request): void
    {
        $usuario = Auth::user();

        if ($usuario === null) {
            Response::redirect(url('/ingresar'));
        }

        View::display('auth/perfil', [
            'titulo'  => 'Mi perfil',
            'usuario' => $usuario,
        ]);
    }

    public function actualizarPerfil(Request $request): void
    {
        $usuario = Auth::user();

        if ($usuario === null) {
            Response::redirect(url('/ingresar'));
        }

        $actual = $this->texto($request, 'password_actual');
        $nueva = $this->texto($request, 'password_nueva');
        $confirmacion = $this->texto($request, 'password_confirmacion');

        $errores = [];

        if ($actual === '' || !password_verify($actual, (string) $usuario['password_hash'])) {
            $errores[] = 'La contraseña actual no es correcta.';
        }

        if ($nueva !== $confirmacion) {
            $errores[] = 'La nueva contraseña y su confirmación no coinciden.';
        }

        foreach (Password::errores($nueva) as $error) {
            $errores[] = $error;
        }

        if ($nueva !== '' && $actual !== '' && $nueva === $actual) {
            $errores[] = 'La nueva contraseña debe ser distinta de la actual.';
        }

        if ($errores !== []) {
            Session::flash('errores', $errores);
            Response::redirect(url('/perfil'));
        }

        Usuario::cambiarPassword((int) $usuario['id'], $nueva, false);
        Audit::log((int) $usuario['id'], 'cambio_password', 'usuario', (int) $usuario['id'], 'Cambio de contraseña propio');

        Auth::refresh();
        Session::flash('exito', 'Su contraseña se actualizó correctamente.');
        Response::redirect(url('/perfil'));
    }

    private function texto(Request $request, string $campo): string
    {
        $valor = $request->input($campo, '');

        return is_string($valor) ? $valor : '';
    }
}
