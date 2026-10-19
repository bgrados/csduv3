<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Auditoria;

/**
 * Consulta del registro de auditoría.
 *
 * No permite editar ni borrar registros: la trazabilidad es la razón de ser de
 * esta tabla.
 */
final class AuditoriaController
{
    private const POR_PAGINA = 30;

    public function index(Request $request): void
    {
        if (!Database::isAvailable()) {
            Session::flash('error', 'La base de datos no está disponible en este momento.');
            Response::redirect(url('/panel'));
        }

        $busqueda = $request->string('q');
        $busqueda = $busqueda === '' ? null : $busqueda;
        $accion = $request->string('accion');
        $accion = $accion === '' ? null : $accion;
        $pagina = max(1, $request->integer('pagina', 1));
        $total = Auditoria::total($busqueda, $accion);

        View::display('auditoria/index', [
            'titulo'   => 'Auditoría',
            'registros' => Auditoria::listar($busqueda, $accion, self::POR_PAGINA, ($pagina - 1) * self::POR_PAGINA),
            'acciones' => Auditoria::acciones(),
            'busqueda' => $busqueda ?? '',
            'accion'   => $accion ?? '',
            'total'    => $total,
            'pagina'   => $pagina,
            'paginas'  => max(1, (int) ceil($total / self::POR_PAGINA)),
        ]);
    }
}
