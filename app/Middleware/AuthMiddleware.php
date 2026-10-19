<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Exige una sesión iniciada para acceder al panel administrativo.
 */
final class AuthMiddleware
{
    public static function handle(Request $request): bool
    {
        if (Auth::check()) {
            return true;
        }

        Session::put('_intended_url', $request->path());
        Session::flash('aviso', 'Inicie sesión para acceder al panel administrativo.');

        Response::redirect(url('/ingresar'));
    }
}
