<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;
use App\Models\Negocio;

/**
 * Portal público de los negocios de la unidad vecinal.
 *
 * No requiere sesión. Solo muestra los negocios visibles.
 */
final class PortalNegociosController
{
    public function index(Request $request): void
    {
        if (!$this->portalListo()) {
            return;
        }

        $categoria = $request->string('categoria');
        $categoria = isset(Negocio::CATEGORIAS[$categoria]) ? $categoria : '';

        try {
            $negocios = Negocio::listarVisibles();
        } catch (\Throwable $exception) {
            \App\Core\Logger::warning('No se pudo cargar el directorio de negocios', ['error' => $exception->getMessage()]);
            $negocios = [];
        }

        if ($categoria !== '') {
            $negocios = array_values(array_filter(
                $negocios,
                static fn (array $negocio): bool => (string) $negocio['categoria'] === $categoria
            ));
        }

        View::display('negocios/publico', [
            'titulo'     => 'Negocios de la unidad',
            'negocios'   => $negocios,
            'categorias' => Negocio::CATEGORIAS,
            'categoria'  => $categoria,
        ], 'layouts/publico');
    }

    /**
     * Entrega el logotipo de un negocio visible.
     */
    public function imagen(Request $request, string $id): void
    {
        if (!$this->portalListo()) {
            return;
        }

        $negocio = Negocio::find((int) $id);

        if ($negocio === null || (int) $negocio['visible'] !== 1) {
            abort(404, 'La imagen solicitada no está disponible.');
        }

        $absoluta = Uploads::rutaAbsoluta((string) ($negocio['archivo'] ?? ''), 'negocios/');

        if ($absoluta === null || !is_file($absoluta)) {
            abort(404, 'La imagen solicitada no está disponible.');
        }

        if (!headers_sent()) {
            header('Content-Type: ' . (string) ($negocio['tipo_mime'] ?? 'image/jpeg'));
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
            'titulo' => 'Directorio no disponible',
        ], 'layouts/publico');
    }
}
