<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Impide que un usuario ya autenticado vuelva al formulario de acceso.
 */
final class GuestMiddleware
{
    public static function handle(Request $request): bool
    {
        if (Auth::check()) {
            Response::redirect(url('/panel'));
        }

        return true;
    }
}
