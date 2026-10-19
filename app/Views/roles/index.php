<?php

declare(strict_types=1);

/**
 * Matriz de roles y permisos.
 *
 * @var array<int, array<string, mixed>> $roles
 * @var array<string, mixed>|null $rolSeleccionado
 * @var array<string, array<int, array<string, mixed>>> $permisosAgrupados
 * @var array<int, bool> $permisosDelRol
 * @var bool $esAdministrador
 * @var int $totalPermisos
 */

$etiquetasModulo = [
    'panel'         => 'Panel',
    'reportes'      => 'Reportes',
    'socios'        => 'Socios',
    'directiva'     => 'Directiva',
    'elecciones'    => 'Elecciones',
    'alquileres'    => 'Alquileres',
    'caja'          => 'Caja',
    'deportes'      => 'Deportes',
    'calendario'    => 'Calendario',
    'documentos'    => 'Documentos',
    'usuarios'      => 'Usuarios',
    'roles'         => 'Roles',
    'auditoria'     => 'Auditoría',
    'configuracion' => 'Configuración',
];
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Administración</p>
        <h1>Roles y permisos</h1>
        <p class="page-head__lead">
            Los permisos se comprueban en el servidor en cada petición. Ocultar un botón no
            protege una operación: lo que protege es la comprobación del permiso.
        </p>
    </div>
</div>

<?php if ($roles === []): ?>
    <div class="empty-state">
        <h2>Sin roles registrados</h2>
        <p>Importe database/02_datos_iniciales.sql para cargar los roles del sistema.</p>
    </div>
<?php else: ?>
    <nav class="tabs" aria-label="Roles">
        <?php foreach ($roles as $rol): ?>
            <a
                class="tabs__item<?= ($rolSeleccionado !== null && (int) $rolSeleccionado['id'] === (int) $rol['id']) ? ' is-active' : '' ?>"
                href="<?= e(url('/roles?rol=' . (string) $rol['id'])) ?>"
            ><?= e((string) $rol['nombre']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($rolSeleccionado === null): ?>
        <div class="empty-state">
            <h2>Seleccione un rol</h2>
            <p>Elija uno de los roles de la lista para ver sus permisos.</p>
        </div>
    <?php else: ?>
        <section class="card">
            <h2><?= e((string) $rolSeleccionado['nombre']) ?></h2>
            <p class="muted"><?= e((string) ($rolSeleccionado['descripcion'] ?? '')) ?></p>
            <p class="muted">
                <?= (int) $rolSeleccionado['es_sistema'] === 1 ? 'Rol del sistema' : 'Rol personalizado' ?>
                · <code><?= e((string) $rolSeleccionado['clave']) ?></code>
            </p>

            <?php if ($esAdministrador): ?>
                <div class="alert alert--warning" role="status">
                    <p>
                        El rol <strong>Administrador del sistema</strong> conserva siempre todos los permisos
                        y no puede modificarse desde aquí. Así el sistema nunca queda sin una cuenta capaz
                        de administrarlo.
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/roles/permisos')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="rol_id" value="<?= e((string) $rolSeleccionado['id']) ?>">

                <?php foreach ($permisosAgrupados as $modulo => $permisos): ?>
                    <fieldset class="form__group permission-group" <?= $esAdministrador ? 'disabled' : '' ?>>
                        <legend><?= e($etiquetasModulo[$modulo] ?? ucfirst((string) $modulo)) ?></legend>

                        <div class="permission-list">
                            <?php foreach ($permisos as $permiso): ?>
                                <?php $marcado = isset($permisosDelRol[(int) $permiso['id']]); ?>
                                <div class="check check--compact">
                                    <input
                                        type="checkbox"
                                        id="permiso-<?= e((string) $permiso['id']) ?>"
                                        name="permisos[]"
                                        value="<?= e((string) $permiso['id']) ?>"
                                        <?= $marcado ? 'checked' : '' ?>
                                        <?= $esAdministrador ? 'disabled' : '' ?>
                                    >
                                    <label for="permiso-<?= e((string) $permiso['id']) ?>">
                                        <?= e((string) $permiso['descripcion']) ?>
                                        <code><?= e((string) $permiso['clave']) ?></code>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>

                <?php if (!$esAdministrador): ?>
                    <div class="form__actions">
                        <button type="submit" class="btn btn--primary">Guardar permisos del rol</button>
                    </div>
                <?php endif; ?>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>
