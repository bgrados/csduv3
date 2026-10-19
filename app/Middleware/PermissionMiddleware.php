<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;

/**
 * Comprueba permisos en el servidor.
 *
 * Ocultar un botón no es un control de seguridad: la comprobación se hace aquí,
 * antes de ejecutar el controlador, para toda petición que llegue por URL.
 */
final class PermissionMiddleware
{
    public static function requiere(string $permiso): callable
    {
        return static function (Request $request) use ($permiso): bool {
            if (!Auth::check()) {
                \App\Core\Session::put('_intended_url', $request->path());
                \App\Core\Response::redirect(url('/ingresar'));
            }

            if (Auth::can($permiso)) {
                return true;
            }

            Logger::warning('Acceso denegado por permisos', [
                'permiso' => $permiso,
                'ruta'    => $request->path(),
                'usuario' => Auth::id(),
            ]);

            Audit::log(
                Auth::id(),
                'acceso_denegado',
                'ruta',
                null,
                'Intento de acceso sin el permiso ' . $permiso . ' en ' . $request->path()
            );

            abort(403, 'No tiene permisos para acceder a esta sección.');
        };
    }

    /**
     * Exige al menos uno de los permisos indicados.
     *
     * @param array<int, string> $permisos
     */
    public static function requiereAlguno(array $permisos): callable
    {
        return static function (Request $request) use ($permisos): bool {
            if (!Auth::check()) {
                \App\Core\Session::put('_intended_url', $request->path());
                \App\Core\Response::redirect(url('/ingresar'));
            }

            if (Auth::canAny($permisos)) {
                return true;
            }

            Logger::warning('Acceso denegado por permisos', [
                'permisos' => implode(',', $permisos),
                'ruta'     => $request->path(),
                'usuario'  => Auth::id(),
            ]);

            Audit::log(
                Auth::id(),
                'acceso_denegado',
                'ruta',
                null,
                'Intento de acceso sin permisos (' . implode(',', $permisos) . ') en ' . $request->path()
            );

            abort(403, 'No tiene permisos para acceder a esta sección.');
        };
    }
}
