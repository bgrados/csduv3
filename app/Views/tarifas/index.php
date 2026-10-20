<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Tarifas de un ambiente.
 *
 * @var array<string, mixed> $instalacion
 * @var array<int, array<string, mixed>> $tarifas
 */

$puedeGestionar = can('alquileres.tarifas');
$hora = static fn (?string $valor): string => $valor === null || $valor === '' ? '—' : substr($valor, 0, 5);
$vigencia = static function (array $tarifa): string {
    $desde = $tarifa['vigente_desde'] ?? null;
    $hasta = $tarifa['vigente_hasta'] ?? null;

    if (($desde === null || $desde === '') && ($hasta === null || $hasta === '')) {
        return 'Permanente';
    }

    if ($desde !== null && $desde !== '' && $hasta !== null && $hasta !== '') {
        return 'Desde ' . fecha_legible((string) $desde) . ' hasta ' . fecha_legible((string) $hasta);
    }

    return $desde !== null && $desde !== ''
        ? 'Desde ' . fecha_legible((string) $desde)
        : 'Hasta ' . fecha_legible((string) $hasta);
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/ambientes')) ?>">Ambientes y tarifas</a> · <?= e((string) $instalacion['nombre']) ?>
        </p>
        <h1>Tarifas</h1>
        <p class="page-head__lead">
            Las tarifas activas y vigentes se publican en el portal. Si un ambiente no tiene
            tarifa vigente, se muestra como «tarifa a consultar».
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/alquiler/ambiente/' . (string) $instalacion['id'])) ?>">Ver en el portal</a>
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="<?= e(url('/ambientes/' . (string) $instalacion['id'] . '/tarifas/nueva')) ?>">
                <?= Icons::render('caja', 'icon', 18) ?> Nueva tarifa
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($tarifas === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('caja', 'icon', 26) ?></span>
        <h2>Sin tarifas registradas</h2>
        <p>
            Este ambiente todavía no tiene tarifas. Agregue al menos una tarifa activa y vigente
            para que los visitantes puedan consultar el precio en el portal.
        </p>
        <?php if ($puedeGestionar): ?>
            <p><a class="btn btn--primary" href="<?= e(url('/ambientes/' . (string) $instalacion['id'] . '/tarifas/nueva')) ?>">Crear la primera tarifa</a></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Tarifa</th>
                    <th scope="col">Tipo de uso</th>
                    <th scope="col">Horario</th>
                    <th scope="col">Vigencia</th>
                    <th scope="col">Monto</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tarifas as $tarifa): ?>
                    <?php $activa = (int) $tarifa['activo'] === 1; ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $tarifa['nombre']) ?></strong>
                            <?php if (!empty($tarifa['observaciones'])): ?>
                                <br><span class="muted"><?= e((string) $tarifa['observaciones']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($tarifa['tipo_evento_nombre'] ?? 'Uso general')) ?></td>
                        <td>
                            <?php if (!empty($tarifa['hora_inicio']) || !empty($tarifa['hora_fin'])): ?>
                                <?= e($hora($tarifa['hora_inicio'] ?? null)) ?> – <?= e($hora($tarifa['hora_fin'] ?? null)) ?>
                            <?php else: ?>
                                <span class="muted">Todo el día</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($vigencia($tarifa)) ?></td>
                        <td><strong><?= e(dinero($tarifa['monto'] ?? 0)) ?></strong></td>
                        <td>
                            <span class="badge <?= $activa ? 'badge--ok' : 'badge--soft' ?>">
                                <?= $activa ? 'Activa' : 'Inactiva' ?>
                            </span>
                        </td>
                        <td class="table__actions">
                            <?php if ($puedeGestionar): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/ambientes/' . (string) $instalacion['id'] . '/tarifas/' . (string) $tarifa['id'] . '/editar')) ?>">Editar</a>
                                <form class="inline-form" method="post" action="<?= e(url('/ambientes/' . (string) $instalacion['id'] . '/tarifas/' . (string) $tarifa['id'] . '/estado')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="<?= $activa ? 'desactivar' : 'activar' ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm">
                                        <?= $activa ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
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
<?php endif; ?>

<p class="section-actions">
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/ambientes')) ?>">Volver a ambientes</a>
</p>
