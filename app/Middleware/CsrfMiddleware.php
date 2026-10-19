<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;

/**
 * Verifica el token CSRF de toda petición que modifica información.
 *
 * Se aplica globalmente desde app/routes.php para que ningún formulario quede
 * sin protección por olvido.
 */
final class CsrfMiddleware
{
    /** @var array<int, string> */
    private const METODOS_QUE_MODIFICAN = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public static function handle(Request $request): bool
    {
        if (!in_array($request->method(), self::METODOS_QUE_MODIFICAN, true)) {
            return true;
        }

        if (Csrf::check()) {
            return true;
        }

        Logger::warning('Token CSRF ausente o inválido', [
            'ruta' => $request->path(),
            'ip'   => $request->ip(),
        ]);

        abort(419, 'La sesión del formulario expiró. Recargue la página e inténtelo nuevamente.');
    }
}
