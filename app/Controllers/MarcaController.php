<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploads;
use App\Models\Configuracion;

/**
 * Sirve las imágenes de marca del club (logo, favicon y portada).
 *
 * No requiere sesión: son imágenes públicas. Los archivos se guardan fuera de
 * la carpeta `public` y se entregan con caché para que el navegador no las
 * vuelva a pedir.
 */
final class MarcaController
{
    public function mostrar(Request $request, string $tipo): void
    {
        if (!in_array($tipo, ['logo', 'favicon', 'portada'], true)) {
            abort(404, 'La imagen solicitada no existe.');
        }

        $archivo = '';

        if (Database::isAvailable()) {
            $archivo = Configuracion::cached('marca_' . $tipo, '');
        }

        $absoluta = $archivo === '' ? null : Uploads::rutaAbsoluta($archivo, 'marca/');

        if ($absoluta === null || !is_file($absoluta)) {
            $porDefecto = match ($tipo) {
                'favicon' => 'assets/img/favicon.svg',
                'logo'    => 'assets/img/logo.jpg',
                default   => '',
            };

            if ($porDefecto !== '') {
                Response::archivo(base_path('public/' . $porDefecto), 'public, max-age=86400');
            }

            abort(404, 'La imagen solicitada no existe.');
        }

        Response::archivo($absoluta, 'public, max-age=604800');
    }
}
