<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Migraciones;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * Actualización de la base de datos desde el panel.
 *
 * Muestra qué migraciones están pendientes y permite aplicarlas sin depender
 * del Terminal del hosting. Usa el permiso «configuracion.gestionar».
 */
final class MigracionesController
{
    public function index(Request $request): void
    {
        View::display('migraciones/index', [
            'titulo'      => 'Actualizar la base de datos',
            'aplicadas'   => Migraciones::aplicadas(),
            'pendientes'  => Migraciones::pendientes(),
            'tablasFaltantes' => Migraciones::tablasFaltantes(),
            'columnasFaltantes' => Migraciones::columnasFaltantes(),
            'agregadas'   => [],
            'errores'     => [],
        ]);
    }

    public function aplicar(Request $request): void
    {
        $pendientes = Migraciones::pendientes();

        if ($pendientes === []) {
            // Aun sin migraciones pendientes puede faltar alguna columna.
            $reparacion = Migraciones::repararColumnas();

            if ($reparacion['agregadas'] !== []) {
                Audit::log(
                    Auth::id(),
                    'reparar_base_datos',
                    'migracion',
                    null,
                    'Columnas agregadas: ' . implode(', ', $reparacion['agregadas'])
                );

                Session::flash(
                    'exito',
                    'Listo: se agregaron a la base de datos ' . implode(', ', $reparacion['agregadas']) . '.'
                );
                Response::redirect(url('/migraciones'));
            }

            if ($reparacion['errores'] !== []) {
                foreach ($reparacion['errores'] as $error) {
                    Logger::error('Error al reparar la base de datos desde el panel', ['error' => $error]);
                }

                Session::flash('error', 'No se pudo actualizar la base de datos: ' . implode(' ', $reparacion['errores']));
                Response::redirect(url('/migraciones'));
            }

            Session::flash('exito', 'La base de datos ya está actualizada.');
            Response::redirect(url('/migraciones'));
        }

        $resultado = Migraciones::aplicar();

        foreach ($resultado['errores'] as $error) {
            Logger::error('Error al aplicar migraciones desde el panel', ['error' => $error]);
        }

        Audit::log(
            Auth::id(),
            'aplicar_migraciones',
            'migracion',
            null,
            'Migraciones aplicadas: ' . (implode(', ', $resultado['aplicadas']) ?: 'ninguna')
        );

        $mensajes = [];

        if ($resultado['aplicadas'] !== []) {
            $mensajes[] = 'Se aplicaron ' . count($resultado['aplicadas']) . ' actualización(es): '
                . implode(', ', $resultado['aplicadas']) . '.';
        }

        if ($resultado['agregadas'] !== []) {
            $mensajes[] = 'Se agregaron las columnas que faltaban: '
                . implode(', ', $resultado['agregadas']) . '.';
        }

        if ($mensajes !== []) {
            Session::flash('exito', implode(' ', $mensajes));
        }

        if ($resultado['errores'] !== []) {
            Session::flash(
                'error',
                'No se pudieron aplicar todas las actualizaciones: ' . implode(' ', $resultado['errores'])
            );
        }

        Response::redirect(url('/migraciones'));
    }
}
