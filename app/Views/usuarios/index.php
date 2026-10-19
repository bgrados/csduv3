<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Listado de usuarios del sistema.
 *
 * @var array<int, array<string, mixed>> $usuarios
 * @var string $busqueda
 * @var int $total
 */

$puedeGestionar = can('usuarios.gestionar');
$propioId = (int) (auth()['id'] ?? 0);
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Administración</p>
        <h1>Usuarios</h1>
        <p class="page-head__lead">
            Cada persona del club debe tener su propia cuenta. Los permisos se asignan por rol,
            no cuenta por cuenta.
        </p>
    </div>
    <?php if ($puedeGestionar): ?>
        <div class="page-head__actions">
            <a class="btn btn--primary" href="<?= e(url('/usuarios/nuevo')) ?>"><?= Icons::render('usuario', 'icon', 18) ?> Nuevo usuario</a>
        </div>
    <?php endif; ?>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/usuarios')) ?>">
    <div class="field">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= e($busqueda) ?>" placeholder="Nombre, usuario o correo">
    </div>
    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Buscar</button>
        <?php if ($busqueda !== ''): ?>
            <a class="btn btn--ghost" href="<?= e(url('/usuarios')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($usuarios === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('usuario', 'icon', 26) ?></span>
        <h2>Sin usuarios</h2>
        <p>
            <?= $busqueda !== '' ? 'No se encontraron coincidencias con la búsqueda.' : 'Todavía no hay cuentas registradas.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Usuario</th>
                    <th scope="col">Rol</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Último acceso</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td>
                            <?= e(usuario_nombre($usuario)) ?>
                            <?php if ((int) $usuario['id'] === $propioId): ?>
                                <span class="badge badge--info">usted</span>
                            <?php endif; ?>
                            <?php if ((int) ($usuario['debe_cambiar_password'] ?? 0) === 1): ?>
                                <span class="badge badge--warn">debe cambiar contraseña</span>
                            <?php endif; ?>
                            <?php if (!empty($usuario['email'])): ?>
                                <br><span class="muted"><?= e((string) $usuario['email']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e((string) $usuario['usuario']) ?></code></td>
                        <td><?= e((string) ($usuario['rol_nombre'] ?? 'Sin rol')) ?></td>
                        <td>
                            <span class="badge <?= ((int) $usuario['activo'] === 1) ? 'badge--ok' : 'badge--danger' ?>">
                                <?= ((int) $usuario['activo'] === 1) ? 'Activa' : 'Desactivada' ?>
                            </span>
                        </td>
                        <td><?= e(fecha_legible($usuario['ultimo_acceso'] ?? null, true)) ?></td>
                        <td class="table__actions">
                            <?php if ($puedeGestionar): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/usuarios/' . (string) $usuario['id'] . '/editar')) ?>">Editar</a>

                                <?php if ((int) $usuario['id'] !== $propioId): ?>
                                    <form method="post" action="<?= e(url('/usuarios/' . (string) $usuario['id'] . '/estado')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="accion" value="<?= ((int) $usuario['activo'] === 1) ? 'desactivar' : 'activar' ?>">
                                        <button type="submit" class="btn btn--ghost btn--sm">
                                            <?= ((int) $usuario['activo'] === 1) ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted">Solo lectura</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php $etiqueta = 'usuarios'; require __DIR__ . '/../partials/paginacion.php'; ?>
<?php endif; ?>
