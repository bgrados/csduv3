<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Deporte;
use App\Models\Torneo;

/**
 * Módulo administrativo de deportes y torneos.
 */
final class DeportesController
{
    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $busqueda = $request->string('q');
        $deporteId = $request->integer('deporte_id');
        $deporteId = $deporteId > 0 ? $deporteId : null;
        $estado = $request->string('estado');
        $estado = isset(Torneo::ESTADOS[$estado]) ? $estado : '';

        View::display('deportes/index', [
            'titulo'     => 'Deportes y torneos',
            'torneos'    => Torneo::listar($busqueda === '' ? null : $busqueda, $deporteId, $estado),
            'deportes'   => Deporte::todos(),
            'modalidades' => Torneo::MODALIDADES,
            'estados'    => Torneo::ESTADOS,
            'busqueda'   => $busqueda,
            'deporteId'  => $deporteId ?? 0,
            'estado'     => $estado,
            'total'      => Torneo::contar(),
        ]);
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
