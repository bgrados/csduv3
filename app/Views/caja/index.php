<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Caja: listado de movimientos con filtros y resumen.
 *
 * @var array<int, array<string, mixed>> $movimientos
 * @var array<int, array<string, mixed>> $categorias
 * @var array{ingresos: float, egresos: float, saldo: float, cantidad: int} $resumen
 * @var string $resumenPeriodo
 * @var array<string, mixed> $filtros
 * @var int $total
 * @var int $pagina
 * @var int $paginas
 */

$puedeRegistrar = can('caja.registrar');
$puedeAnular = can('caja.anular');
$puedeVerReportes = can('caja.reportes');

$hayFiltros = $filtros['q'] !== ''
    || $filtros['tipo'] !== ''
    || $filtros['estado'] !== ''
    || $filtros['categoria_id'] > 0
    || $filtros['desde'] !== ''
    || $filtros['hasta'] !== '';
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Operación</p>
        <h1>Caja</h1>
        <p class="page-head__lead">
            Registro de ingresos y egresos del club. Los movimientos no se eliminan:
            cuando corresponde se anulan dejando el motivo y el historial.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/caja/configuracion')) ?>">Configuración</a>
        <?php if ($puedeVerReportes): ?>
            <a class="btn btn--ghost" href="<?= e(url('/caja/reportes')) ?>">Reportes</a>
        <?php endif; ?>
        <?php if ($puedeRegistrar): ?>
            <a class="btn btn--primary" href="<?= e(url('/caja/nuevo')) ?>">
                <?= Icons::render('caja', 'icon', 18) ?> Nuevo movimiento
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="cards cards--3">
    <div class="card card--stat">
        <p class="card__label">Ingresos</p>
        <p class="card__value"><?= e(dinero($resumen['ingresos'])) ?></p>
        <p class="card__hint"><?= e($resumenPeriodo) ?></p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Egresos</p>
        <p class="card__value"><?= e(dinero($resumen['egresos'])) ?></p>
        <p class="card__hint"><?= e($resumenPeriodo) ?></p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Saldo</p>
        <p class="card__value"><?= e(dinero($resumen['saldo'])) ?></p>
        <p class="card__hint"><?= e((string) $resumen['cantidad']) ?> movimientos registrados</p>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/caja')) ?>">
    <div class="field">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= e((string) $filtros['q']) ?>" placeholder="Concepto o referencia">
    </div>

    <div class="field">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
            <option value="ingreso" <?= $filtros['tipo'] === 'ingreso' ? 'selected' : '' ?>>Ingreso</option>
            <option value="egreso" <?= $filtros['tipo'] === 'egreso' ? 'selected' : '' ?>>Egreso</option>
        </select>
    </div>

    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <option value="registrado" <?= $filtros['estado'] === 'registrado' ? 'selected' : '' ?>>Registrado</option>
            <option value="anulado" <?= $filtros['estado'] === 'anulado' ? 'selected' : '' ?>>Anulado</option>
        </select>
    </div>

    <div class="field">
        <label for="categoria_id">Categoría</label>
        <select id="categoria_id" name="categoria_id">
            <option value="">Todas</option>
            <?php foreach ($categorias as $categoria): ?>
                <option
                    value="<?= e((string) $categoria['id']) ?>"
                    <?= (int) $filtros['categoria_id'] === (int) $categoria['id'] ? 'selected' : '' ?>
                ><?= e((string) $categoria['nombre']) ?> (<?= e((string) $categoria['tipo']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" value="<?= e((string) $filtros['desde']) ?>">
    </div>

    <div class="field">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="<?= e((string) $filtros['hasta']) ?>">
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Filtrar</button>
        <?php if ($hayFiltros): ?>
            <a class="btn btn--ghost" href="<?= e(url('/caja')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($movimientos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('caja', 'icon', 26) ?></span>
        <?php if ($hayFiltros): ?>
            <h2>Sin coincidencias</h2>
            <p>No se encontraron movimientos con los filtros seleccionados. Pruebe con otros criterios.</p>
        <?php else: ?>
            <h2>Todavía no hay movimientos registrados</h2>
            <p>
                La caja está vacía.
                <?= $puedeRegistrar ? 'Registre el primer ingreso o egreso para comenzar.' : 'Cuando se registren movimientos aparecerán aquí.' ?>
            </p>
            <?php if ($puedeRegistrar): ?>
                <p><a class="btn btn--primary" href="<?= e(url('/caja/nuevo')) ?>">Registrar el primer movimiento</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Concepto</th>
                    <th scope="col">Categoría</th>
                    <th scope="col">Medio</th>
                    <th scope="col">Socio</th>
                    <th scope="col">Monto</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($movimientos as $movimiento): ?>
                    <?php
                    $estado = (string) $movimiento['estado'];
                    $anulado = $estado === 'anulado';
                    $esIngreso = (string) $movimiento['tipo'] === 'ingreso';
                    ?>
                    <tr<?= $anulado ? ' class="is-anulado"' : '' ?>>
                        <td><?= e(fecha_legible((string) $movimiento['fecha'])) ?></td>
                        <td>
                            <span class="badge <?= $esIngreso ? 'badge--ok' : 'badge--warn' ?>">
                                <?= $esIngreso ? 'Ingreso' : 'Egreso' ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= e((string) $movimiento['concepto']) ?></strong>
                            <?php if (!empty($movimiento['referencia'])): ?>
                                <br><span class="muted">Ref. <?= e((string) $movimiento['referencia']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($movimiento['categoria_nombre'] ?? '—')) ?></td>
                        <td><?= e((string) ($movimiento['medio_pago_nombre'] ?? '—')) ?></td>
                        <td>
                            <?php if (!empty($movimiento['socio_codigo'])): ?>
                                <code><?= e((string) $movimiento['socio_codigo']) ?></code>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= e(dinero((string) $movimiento['monto'])) ?></strong></td>
                        <td>
                            <span class="badge <?= $anulado ? 'badge--danger' : 'badge--soft' ?>">
                                <?= $anulado ? 'Anulado' : 'Registrado' ?>
                            </span>
                        </td>
                        <td class="table__actions">
                            <?php if (!$anulado && $puedeRegistrar): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/caja/' . (string) $movimiento['id'] . '/editar')) ?>">Editar</a>
                            <?php endif; ?>
                            <?php if (!$anulado && $puedeAnular): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/caja/' . (string) $movimiento['id'] . '/editar#anular')) ?>">Anular</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php $etiqueta = 'movimientos'; require __DIR__ . '/../partials/paginacion.php'; ?>
<?php endif; ?>
