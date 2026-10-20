<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;
use App\Models\Foto;

/**
 * Portal público de la galería institucional.
 *
 * No requiere sesión. Solo muestra las fotos visibles.
 */
final class PortalGaleriaController
{
    public function index(Request $request): void
    {
        if (!$this->portalListo()) {
            return;
        }

        $categoria = $request->string('categoria');
        $categoria = isset(Foto::CATEGORIAS[$categoria]) ? $categoria : '';

        try {
            $fotos = Foto::listarVisibles(36, $categoria);
        } catch (\Throwable $exception) {
            \App\Core\Logger::warning('No se pudo cargar la galería pública', ['error' => $exception->getMessage()]);
            $fotos = [];
        }

        View::display('galeria/publico', [
            'titulo'     => 'El club en imágenes',
            'fotos'      => $fotos,
            'categorias' => Foto::CATEGORIAS,
            'categoria'  => $categoria,
        ], 'layouts/publico');
    }

    /**
     * Entrega la imagen de una foto visible.
     */
    public function imagen(Request $request, string $id): void
    {
        if (!$this->portalListo()) {
            return;
        }

        $foto = Foto::find((int) $id);

        if ($foto === null || (int) $foto['visible'] !== 1) {
            abort(404, 'La imagen solicitada no está disponible.');
        }

        $absoluta = Uploads::rutaAbsoluta((string) ($foto['archivo'] ?? ''), 'fotos/');

        if ($absoluta === null || !is_file($absoluta)) {
            abort(404, 'La imagen solicitada no está disponible.');
        }

        if (!headers_sent()) {
            header('Content-Type: ' . (string) ($foto['tipo_mime'] ?? 'image/jpeg'));
            header('Content-Length: ' . (string) filesize($absoluta));
            header('Cache-Control: public, max-age=86400');
            header('X-Content-Type-Options: nosniff');
        }

        readfile($absoluta);
        exit;
    }

    private function portalListo(): bool
    {
        if (Database::isAvailable()) {
            return true;
        }

        http_response_code(503);
        View::display('torneos/no_disponible', [
            'titulo' => 'Galería no disponible',
        ], 'layouts/publico');
    }
}
