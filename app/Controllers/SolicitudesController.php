<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\SolicitudAlquiler;

/**
 * Revisión de las solicitudes recibidas desde el portal público.
 *
 * En esta fase una solicitud puede marcarse en revisión, rechazarse con motivo
 * o darse por atendida. La conversión en reserva con importes y pagos pertenece
 * al módulo de alquileres (Fase 4) y no se simula aquí.
 */
final class SolicitudesController
{
    private const POR_PAGINA = 20;

    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $estado = $request->string('estado');
        $estado = isset(SolicitudAlquiler::ESTADOS[$estado]) ? $estado : null;
        $busqueda = $request->string('q');
        $busqueda = $busqueda === '' ? null : $busqueda;
        $pagina = max(1, $request->integer('pagina', 1));
        $total = SolicitudAlquiler::total($estado, $busqueda);

        View::display('solicitudes/index', [
            'titulo'      => 'Solicitudes de alquiler',
            'solicitudes' => SolicitudAlquiler::listar($estado, $busqueda, self::POR_PAGINA, ($pagina - 1) * self::POR_PAGINA),
            'estados'     => SolicitudAlquiler::ESTADOS,
            'estado'      => $estado ?? '',
            'busqueda'    => $busqueda ?? '',
            'total'       => $total,
            'pagina'      => $pagina,
            'paginas'     => max(1, (int) ceil($total / self::POR_PAGINA)),
            'pendientes'  => SolicitudAlquiler::contarPendientes(),
        ]);
    }

    public function ver(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $solicitud = SolicitudAlquiler::ver((int) $id);

        if ($solicitud === null) {
            abort(404, 'La solicitud indicada no existe.');
        }

        View::display('solicitudes/ver', [
            'titulo'    => 'Solicitud ' . $solicitud['codigo'],
            'solicitud' => $solicitud,
            'estados'   => SolicitudAlquiler::ESTADOS,
        ]);
    }

    public function enRevision(Request $request, string $id): void
    {
        $solicitud = $this->obtener((int) $id);

        SolicitudAlquiler::actualizarEstado((int) $solicitud['id'], 'en_revision', Auth::id());

        Audit::log(
            Auth::id(),
            'solicitud_en_revision',
            'solicitud_alquiler',
            (int) $solicitud['id'],
            'Solicitud ' . $solicitud['codigo'] . ' marcada en revisión'
        );

        Session::flash('exito', 'La solicitud quedó marcada como «en revisión».');
        Response::redirect(url('/solicitudes/' . $solicitud['id']));
    }

    public function atender(Request $request, string $id): void
    {
        $solicitud = $this->obtener((int) $id);

        $nota = $request->string('nota');
        $nota = $nota === '' ? null : mb_substr($nota, 0, 500);

        SolicitudAlquiler::actualizarEstado((int) $solicitud['id'], 'atendida', Auth::id(), null, $nota);

        Audit::log(
            Auth::id(),
            'solicitud_atendida',
            'solicitud_alquiler',
            (int) $solicitud['id'],
            'Solicitud ' . $solicitud['codigo'] . ' marcada como atendida'
        );

        Session::flash('exito', 'La solicitud quedó marcada como atendida. Recuerde que esto no crea una reserva: el registro de la reserva se realiza en el módulo de alquileres.');
        Response::redirect(url('/solicitudes/' . $solicitud['id']));
    }

    public function rechazar(Request $request, string $id): void
    {
        $solicitud = $this->obtener((int) $id);

        $motivo = $request->string('motivo');

        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 300) {
            Session::flash('error', 'Indique un motivo de rechazo de entre 5 y 300 caracteres.');
            Response::redirect(url('/solicitudes/' . $solicitud['id']));
        }

        SolicitudAlquiler::actualizarEstado((int) $solicitud['id'], 'rechazada', Auth::id(), $motivo);

        Audit::log(
            Auth::id(),
            'solicitud_rechazada',
            'solicitud_alquiler',
            (int) $solicitud['id'],
            'Solicitud ' . $solicitud['codigo'] . ' rechazada. Motivo: ' . $motivo
        );

        Session::flash('exito', 'La solicitud quedó rechazada y el motivo se guardó en el registro.');
        Response::redirect(url('/solicitudes/' . $solicitud['id']));
    }

    /**
     * @return array<string, mixed>
     */
    private function obtener(int $id): array
    {
        if (!$this->baseDisponible()) {
            exit;
        }

        $solicitud = SolicitudAlquiler::ver($id);

        if ($solicitud === null) {
            abort(404, 'La solicitud indicada no existe.');
        }

        return $solicitud;
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
