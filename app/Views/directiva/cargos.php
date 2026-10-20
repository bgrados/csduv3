<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Cargos de la junta directiva.
 *
 * @var array<int, array<string, mixed>> $cargos
 */

$puedeGestionar = can('directiva.gestionar');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/directiva')) ?>">Junta directiva</a> · cargos
        </p>
        <h1>Cargos de la junta directiva</h1>
        <p class="page-head__lead">
            Los define el club según su estatuto. El orden determina cómo aparecen
            en la lista de integrantes.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/directiva')) ?>">Volver</a>
    </div>
</div>

<div class="card">
    <?php if ($cargos === []): ?>
        <p class="muted">Todavía no hay cargos definidos.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Cargo</th>
                    <th scope="col">Descripción</th>
                    <th scope="col">Orden</th>
                    <th scope="col">Estado</th>
                    <?php if ($puedeGestionar): ?>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($cargos as $cargo): ?>
                    <tr>
                        <td><strong><?= e((string) $cargo['nombre']) ?></strong></td>
                        <td><?= e((string) ($cargo['descripcion'] ?? '—')) ?></td>
                        <td><?= e((string) $cargo['orden']) ?></td>
                        <td>
                            <span class="badge <?= (int) $cargo['activo'] === 1 ? 'badge--ok' : 'badge--soft' ?>">
                                <?= (int) $cargo['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <?php if ($puedeGestionar): ?>
                            <td class="table__actions">
                                <form method="post" action="<?= e(url('/directiva/cargos/' . (string) $cargo['id'] . '/estado')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="<?= (int) $cargo['activo'] === 1 ? 'desactivar' : 'activar' ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm">
                                        <?= (int) $cargo['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($puedeGestionar): ?>
        <h2 class="section-title">Añadir cargo</h2>

        <form class="form form--panel" method="post" action="<?= e(url('/directiva/cargos')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="nombre">Nombre <span class="req">*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="80" value="<?= e((string) old('nombre')) ?>" placeholder="Presidente, Secretary, Tesorero…">
            </div>
            <div class="field">
                <label for="descripcion">Descripción</label>
                <input type="text" id="descripcion" name="descripcion" maxlength="200" value="<?= e((string) old('descripcion')) ?>">
            </div>
            <div class="field">
                <label for="orden">Orden</label>
                <input type="number" id="orden" name="orden" min="1" max="999" value="<?= e((string) old('orden', '1')) ?>">
            </div>
            <div class="field field--actions">
                <button type="submit" class="btn btn--primary">Añadir cargo</button>
            </div>
        </form>
    <?php endif; ?>
</div>
