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
use App\Models\Permiso;
use App\Models\Rol;

/**
 * Roles y permisos.
 *
 * El rol «Administrador del sistema» es de solo lectura: siempre conserva todos
 * los permisos. Así se evita que un error de configuración deje el sistema sin
 * ninguna cuenta capaz de administrarlo.
 */
final class RolesController
{
    public function index(Request $request): void
    {
        if (!Database::isAvailable()) {
            Session::flash('error', 'La base de datos no está disponible en este momento.');
            Response::redirect(url('/panel'));
        }

        $roles = Rol::todos();
        $seleccionado = $this->seleccionar($roles, $request->integer('rol'));

        $permisosDelRol = $seleccionado === null ? [] : Rol::permisosDe((int) $seleccionado['id']);

        View::display('roles/index', [
            'titulo'            => 'Roles y permisos',
            'roles'             => $roles,
            'rolSeleccionado'   => $seleccionado,
            'permisosAgrupados' => Permiso::agrupados(),
            'permisosDelRol'    => array_fill_keys($permisosDelRol, true),
            'esAdministrador'   => $seleccionado !== null && (string) $seleccionado['clave'] === 'administrador',
            'totalPermisos'     => Permiso::contar(),
        ]);
    }

    public function actualizarPermisos(Request $request): void
    {
        if (!Database::isAvailable()) {
            Session::flash('error', 'La base de datos no está disponible en este momento.');
            Response::redirect(url('/panel'));
        }

        $rolId = $request->integer('rol_id');
        $rol = Rol::porId($rolId);

        if ($rol === null) {
            abort(404, 'El rol solicitado no existe.');
        }

        if ((string) $rol['clave'] === 'administrador') {
            Session::flash('error', 'El rol Administrador del sistema conserva todos los permisos y no puede modificarse.');
            Response::redirect(url('/roles?rol=' . $rolId));
        }

        $recibidos = $request->input('permisos', []);
        $recibidos = is_array($recibidos) ? $recibidos : [];

        $validos = [];

        foreach (Permiso::todos() as $permiso) {
            $validos[(int) $permiso['id']] = true;
        }

        $seleccionados = [];

        foreach ($recibidos as $valor) {
            if (!is_scalar($valor) || !ctype_digit((string) $valor)) {
                continue;
            }

            $permisoId = (int) $valor;

            if (isset($validos[$permisoId])) {
                $seleccionados[] = $permisoId;
            }
        }

        Rol::reemplazarPermisos($rolId, $seleccionados);

        Audit::log(
            Auth::id(),
            'actualizar_permisos',
            'rol',
            $rolId,
            'Permisos actualizados para el rol ' . $rol['nombre'] . ' (' . count($seleccionados) . ' permisos)'
        );

        Session::flash('exito', 'Los permisos del rol «' . $rol['nombre'] . '» se actualizaron. El cambio se aplica de inmediato.');
        Response::redirect(url('/roles?rol=' . $rolId));
    }

    /**
     * @param array<int, array<string, mixed>> $roles
     * @return array<string, mixed>|null
     */
    private function seleccionar(array $roles, int $rolId): ?array
    {
        foreach ($roles as $rol) {
            if ((int) $rol['id'] === $rolId) {
                return $rol;
            }
        }

        // Por defecto se muestra el primer rol editable (no administrador).
        foreach ($roles as $rol) {
            if ((string) $rol['clave'] !== 'administrador') {
                return $rol;
            }
        }

        return $roles[0] ?? null;
    }
}
