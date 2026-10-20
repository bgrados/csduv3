<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\View;
use App\Models\Configuracion;
use App\Models\Foto;
use App\Models\Instalacion;
use App\Models\Negocio;

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
        $fotos = [];
        $negocios = [];

        if ($dbDisponible) {
            try {
                $instalaciones = Instalacion::activasConTarifas();
                $configuracion = Configuracion::mapa();
            } catch (\Throwable $exception) {
                Logger::warning('No se pudo cargar la portada pública', ['error' => $exception->getMessage()]);
                $dbDisponible = false;
            }
        }

        // Las secciones de fotos y negocios son opcionales: si sus tablas aún
        // no existen (migración pendiente) la portada sigue funcionando.
        if ($dbDisponible) {
            try {
                $fotos = Foto::listarPortada(5);
            } catch (\Throwable $exception) {
                Logger::warning('No se pudo cargar la galería de la portada', ['error' => $exception->getMessage()]);
            }

            try {
                $negocios = Negocio::listarVisibles();
            } catch (\Throwable $exception) {
                Logger::warning('No se pudo cargar el directorio de negocios', ['error' => $exception->getMessage()]);
            }
        }

        View::display('home/index', [
            'titulo'         => 'Inicio',
            'dbDisponible'   => $dbDisponible,
            'instalaciones'  => $instalaciones,
            'configuracion'  => $configuracion,
            'fotos'          => $fotos,
            'negocios'       => $negocios,
        ], 'layouts/publico');
    }
}
