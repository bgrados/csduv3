<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Configuración de caja: categorías, medios de pago y periodos.
 *
 * @var array<string, array<int, array<string, mixed>>> $categorias
 * @var array<int, array<string, mixed>> $medios
 * @var array<int, array<string, mixed>> $periodos
 * @var array<string, string> $tipos
 */

$puedeConfigurar = can('caja.registrar');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/caja')) ?>">Caja</a> · configuración
        </p>
        <h1>Configuración de caja</h1>
        <p class="page-head__lead">
            Catálogos que usa el registro de caja. Los define el club: el sistema no
            inventa categorías ni precios.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/caja')) ?>">Volver a caja</a>
    </div>
</div>

<h2 class="section-title">Categorías</h2>

<?php foreach ($tipos as $tipoClave => $tipoEtiqueta): ?>
    <div class="card" style="margin-bottom:1rem;">
        <h3 style="margin:0 0 .75rem;font-size:1rem;"><?= e($tipoEtiqueta) ?></h3>

        <?php if (($categorias[$tipoClave] ?? []) === []): ?>
            <p class="muted">Todavía no hay categorías de <?= e(mb_strtolower($tipoEtiqueta)) ?>.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Descripción</th>
                        <th scope="col">Estado</th>
                        <?php if ($puedeConfigurar): ?>
                            <th scope="col"><span class="visually-hidden">Acciones</span></th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categorias[$tipoClave] as $categoria): ?>
                        <tr>
                            <td><strong><?= e((string) $categoria['nombre']) ?></strong></td>
                            <td><?= e((string) ($categoria['descripcion'] ?? '—')) ?></td>
                            <td>
                                <span class="badge <?= (int) $categoria['activo'] === 1 ? 'badge--ok' : 'badge--soft' ?>">
                                    <?= (int) $categoria['activo'] === 1 ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <?php if ($puedeConfigurar): ?>
                                <td class="table__actions">
                                    <form method="post" action="<?= e(url('/caja/categorias/' . (string) $categoria['id'] . '/estado')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="accion" value="<?= (int) $categoria['activo'] === 1 ? 'desactivar' : 'activar' ?>">
                                        <button type="submit" class="btn btn--ghost btn--sm">
                                            <?= (int) $categoria['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
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

        <?php if ($puedeConfigurar): ?>
            <form class="form form--panel" method="post" action="<?= e(url('/caja/categorias')) ?>" style="margin-top:1rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="tipo" value="<?= e((string) $tipoClave) ?>">
                <div class="field">
                    <label for="nombre-<?= e((string) $tipoClave) ?>">Nombre de la categoría</label>
                    <input type="text" id="nombre-<?= e((string) $tipoClave) ?>" name="nombre" required maxlength="80" value="<?= e((string) old('nombre')) ?>" placeholder="<?= e((string) $tipoEtiqueta) ?>">
                </div>
                <div class="field">
                    <label for="descripcion-<?= e((string) $tipoClave) ?>">Descripción</label>
                    <input type="text" id="descripcion-<?= e((string) $tipoClave) ?>" name="descripcion" maxlength="200" value="<?= e((string) old('descripcion')) ?>">
                </div>
                <div class="field field--actions">
                    <button type="submit" class="btn btn--ghost">Añadir categoría</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<h2 class="section-title">Medios de pago</h2>

<div class="card">
    <?php if ($medios === []): ?>
        <p class="muted">Todavía no hay medios de pago registrados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Descripción</th>
                    <th scope="col">Estado</th>
                    <?php if ($puedeConfigurar): ?>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($medios as $medio): ?>
                    <tr>
                        <td><strong><?= e((string) $medio['nombre']) ?></strong></td>
                        <td><?= e((string) ($medio['descripcion'] ?? '—')) ?></td>
                        <td>
                            <span class="badge <?= (int) $medio['activo'] === 1 ? 'badge--ok' : 'badge--soft' ?>">
                                <?= (int) $medio['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <?php if ($puedeConfigurar): ?>
                            <td class="table__actions">
                                <form method="post" action="<?= e(url('/caja/medios-pago/' . (string) $medio['id'] . '/estado')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="<?= (int) $medio['activo'] === 1 ? 'desactivar' : 'activar' ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm">
                                        <?= (int) $medio['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
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

    <?php if ($puedeConfigurar): ?>
        <form class="form form--panel" method="post" action="<?= e(url('/caja/medios-pago')) ?>" style="margin-top:1rem;">
            <?= csrf_field() ?>
            <div class="field">
                <label for="medio-nombre">Nombre</label>
                <input type="text" id="medio-nombre" name="nombre" required maxlength="60" value="<?= e((string) old('nombre')) ?>" placeholder="Efectivo, transferencia…">
            </div>
            <div class="field">
                <label for="medio-descripcion">Descripción</label>
                <input type="text" id="medio-descripcion" name="descripcion" maxlength="200" value="<?= e((string) old('descripcion')) ?>">
            </div>
            <div class="field field--actions">
                <button type="submit" class="btn btn--ghost">Añadir medio de pago</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<h2 class="section-title">Periodos</h2>

<div class="card">
    <p class="card__hint" style="margin-bottom:.75rem;">
        Un periodo agrupa los movimientos de caja. Un periodo cerrado no admite nuevos movimientos.
    </p>

    <?php if ($periodos === []): ?>
        <p class="muted">Todavía no hay periodos registrados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Desde</th>
                    <th scope="col">Hasta</th>
                    <th scope="col">Estado</th>
                    <?php if ($puedeConfigurar): ?>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($periodos as $periodo): ?>
                    <tr>
                        <td><strong><?= e((string) $periodo['nombre']) ?></strong></td>
                        <td><?= e(fecha_legible((string) $periodo['fecha_inicio'])) ?></td>
                        <td><?= e(fecha_legible((string) $periodo['fecha_fin'])) ?></td>
                        <td>
                            <span class="badge <?= (int) $periodo['cerrado'] === 1 ? 'badge--soft' : 'badge--ok' ?>">
                                <?= (int) $periodo['cerrado'] === 1 ? 'Cerrado' : 'Abierto' ?>
                            </span>
                        </td>
                        <?php if ($puedeConfigurar): ?>
                            <td class="table__actions">
                                <form method="post" action="<?= e(url('/caja/periodos/' . (string) $periodo['id'] . '/estado')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="<?= (int) $periodo['cerrado'] === 1 ? 'abrir' : 'cerrar' ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm">
                                        <?= (int) $periodo['cerrado'] === 1 ? 'Reabrir' : 'Cerrar' ?>
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

    <?php if ($puedeConfigurar): ?>
        <form class="form form--panel" method="post" action="<?= e(url('/caja/periodos')) ?>" style="margin-top:1rem;">
            <?= csrf_field() ?>
            <div class="field">
                <label for="periodo-nombre">Nombre</label>
                <input type="text" id="periodo-nombre" name="nombre" required maxlength="60" value="<?= e((string) old('nombre')) ?>" placeholder="2026 · Enero 2026">
            </div>
            <div class="field">
                <label for="periodo-inicio">Desde</label>
                <input type="date" id="periodo-inicio" name="fecha_inicio" required value="<?= e((string) old('fecha_inicio')) ?>">
            </div>
            <div class="field">
                <label for="periodo-fin">Hasta</label>
                <input type="date" id="periodo-fin" name="fecha_fin" required value="<?= e((string) old('fecha_fin')) ?>">
            </div>
            <div class="field field--actions">
                <button type="submit" class="btn btn--ghost">Añadir periodo</button>
            </div>
        </form>
    <?php endif; ?>
</div>
