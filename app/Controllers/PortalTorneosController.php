<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use App\Models\Encuentro;
use App\Models\Torneo;
use App\Models\TorneoEquipo;

/**
 * Portal público de torneos.
 *
 * No requiere sesión. Solo muestra torneos que no estén anulados.
 */
final class PortalTorneosController
{
    public function index(Request $request): void
    {
        if (!$this->portalListo()) {
            return;
        }

        View::display('torneos/index', [
            'titulo'      => 'Torneos',
            'torneos'     => Torneo::publicos(),
            'estados'     => Torneo::ESTADOS,
            'modalidades' => Torneo::MODALIDADES,
        ], 'layouts/publico');
    }

    public function ver(Request $request, string $id): void
    {
        if (!$this->portalListo()) {
            return;
        }

        $torneo = Torneo::findPublico((int) $id);

        if ($torneo === null) {
            abort(404, 'El torneo solicitado no existe o no está publicado.');
        }

        $torneoId = (int) $torneo['id'];
        $equipos = TorneoEquipo::listarPorTorneo($torneoId);
        $encuentros = Encuentro::listarPorTorneo($torneoId);
        $posiciones = (string) $torneo['modalidad'] === 'todos_contra_todos'
            ? Torneo::posiciones($torneoId, (int) $torneo['deporte_id'])
            : [];

        View::display('torneos/ver_publico', [
            'titulo'      => (string) $torneo['nombre'],
            'torneo'      => $torneo,
            'equipos'     => $equipos,
            'encuentros'  => $encuentros,
            'posiciones'  => $posiciones,
            'estados'     => Torneo::ESTADOS,
            'estadosPartido' => Encuentro::ESTADOS,
            'modalidades' => Torneo::MODALIDADES,
        ], 'layouts/publico');
    }

    private function portalListo(): bool
    {
        if (Database::isAvailable()) {
            return true;
        }

        if (!headers_sent()) {
            http_response_code(503);
        }

        View::display('torneos/no_disponible', [
            'titulo' => 'Torneos en mantenimiento',
        ], 'layouts/publico');

        return false;
    }
}
