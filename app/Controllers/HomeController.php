<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\View;
use App\Models\Configuracion;
use App\Models\Instalacion;

/**
 * Portada pública del sistema.
 *
 * No requiere sesión. No muestra datos de socios, movimientos ni información
 * interna: solo la presentación institucional y el acceso al portal de alquileres.
 */
final class HomeController
{
    public function index(Request $request): void
    {
        $dbDisponible = Database::isAvailable();
        $instalaciones = [];
        $configuracion = [];

        if ($dbDisponible) {
            try {
                $instalaciones = Instalacion::activasConTarifas();
                $configuracion = Configuracion::mapa();
            } catch (\Throwable $exception) {
                Logger::warning('No se pudo cargar la portada pública', ['error' => $exception->getMessage()]);
                $dbDisponible = false;
            }
        }

        View::display('home/index', [
            'titulo'         => 'Inicio',
            'dbDisponible'   => $dbDisponible,
            'instalaciones'  => $instalaciones,
            'configuracion'  => $configuracion,
        ], 'layouts/publico');
    }
}
