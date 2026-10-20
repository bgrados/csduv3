<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Administración de ambientes.
 *
 * @var array<int, array<string, mixed>> $instalaciones
 * @var array<string, string> $tipos
 * @var string $busqueda
 * @var string $estado
 */

$puedeGestionar = can('alquileres.tarifas');
$hayFiltros = $busqueda !== '' || $estado !== '';
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Operación</p>
        <h1>Ambientes y tarifas</h1>
        <p class="page-head__lead">
            Administre los espacios que el club alquila y sus tarifas. Un ambiente activo
            con al menos una tarifa vigente se publica automáticamente en el portal.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/alquiler')) ?>">Ver portal público</a>
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="<?= e(url('/ambientes/nuevo')) ?>">
                <?= Icons::render('alquileres', 'icon', 18) ?> Nuevo ambiente
            </a>
        <?php endif; ?>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/ambientes')) ?>">
    <div class="field">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= e($busqueda) ?>" placeholder="Nombre, tipo, ubicación o descripción">
    </div>

    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <option value="activo" <?= $estado === 'activo' ? 'selected' : '' ?>>Activos</option>
            <option value="inactivo" <?= $estado === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
        </select>
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Filtrar</button>
        <?php if ($hayFiltros): ?>
            <a class="btn btn--ghost" href="<?= e(url('/ambientes')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($instalaciones === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('alquileres', 'icon', 26) ?></span>
        <h2><?= $hayFiltros ? 'Sin coincidencias' : 'Todavía no hay ambientes' ?></h2>
        <p>
            <?php if ($hayFiltros): ?>
                No se encontraron ambientes con los filtros seleccionados. Pruebe con otros criterios.
            <?php else: ?>
                Cree el primer ambiente, asígnele una tarifa vigente y aparecerá en el portal público.
            <?php endif; ?>
        </p>
        <?php if ($puedeGestionar && !$hayFiltros): ?>
            <p><a class="btn btn--primary" href="<?= e(url('/ambientes/nuevo')) ?>">Crear el primer ambiente</a></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Ambiente</th>
                    <th scope="col">Capacidad</th>
                    <th scope="col">Tarifas</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($instalaciones as $instalacion): ?>
                    <?php
                    $activo = (int) $instalacion['activo'] === 1;
                    $totalTarifas = (int) ($instalacion['total_tarifas'] ?? 0);
                    $tarifasActivas = (int) ($instalacion['tarifas_activas'] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $instalacion['nombre']) ?></strong>
                            <br><span class="muted"><?= e($tipos[(string) $instalacion['tipo']] ?? (string) $instalacion['tipo']) ?></span>
                            <?php if (!empty($instalacion['ubicacion'])): ?>
                                <br><span class="muted"><?= e((string) $instalacion['ubicacion']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($instalacion['aforo'])): ?>
                                <?= e((string) $instalacion['aforo']) ?> personas
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e((string) $tarifasActivas) ?></strong> vigentes
                            <br><span class="muted"><?= e((string) $totalTarifas) ?> registradas</span>
                        </td>
                        <td>
                            <span class="badge <?= $activo ? 'badge--ok' : 'badge--soft' ?>">
                                <?= $activo ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="table__actions">
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/ambientes/' . (string) $instalacion['id'] . '/tarifas')) ?>">Tarifas</a>
                            <?php if ($puedeGestionar): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/ambientes/' . (string) $instalacion['id'] . '/editar')) ?>">Editar</a>
                                <form class="inline-form" method="post" action="<?= e(url('/ambientes/' . (string) $instalacion['id'] . '/estado')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="<?= $activo ? 'desactivar' : 'activar' ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm">
                                        <?= $activo ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
