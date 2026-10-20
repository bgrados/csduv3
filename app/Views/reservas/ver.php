<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Detalle de una reserva con sus pagos.
 *
 * @var array<string, mixed> $reserva
 * @var array<int, array<string, mixed>> $pagos
 * @var array<int, array<string, mixed>> $medios
 * @var array<string, string> $estados
 */

$puedeGestionar = can('alquileres.gestionar');
$puedeAnular = can('alquileres.cancelar');
$puedeCobrar = can('caja.registrar');

$reservaId = (int) $reserva['id'];
$estado = (string) $reserva['estado'];
$precio = (float) $reserva['precio_acordado'];
$adelanto = (float) $reserva['monto_adelanto'];
$pagado = (float) $reserva['monto_pagado'];
$saldo = max(0.0, $precio - $pagado);
$cancelada = $estado === 'cancelada';
$finalizada = $estado === 'finalizada';

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'pendiente'  => 'badge--warn',
        'confirmada' => 'badge--ok',
        'finalizada' => 'badge--soft',
        'cancelada'  => 'badge--danger',
        default      => 'badge--soft',
    };
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/reservas')) ?>">Reservas</a> · <?= e((string) $reserva['codigo']) ?>
        </p>
        <h1>Reserva <?= e((string) $reserva['codigo']) ?></h1>
        <p class="page-head__lead">
            <?= e((string) ($reserva['instalacion_nombre'] ?? '—')) ?>
            <?php if (!empty($reserva['tipo_evento_nombre'])): ?>
                · <?= e((string) $reserva['tipo_evento_nombre']) ?>
            <?php endif; ?>
            · <?= e(fecha_legible((string) $reserva['fecha_evento'])) ?>
            (<?= e(substr((string) $reserva['hora_inicio'], 0, 5)) ?> – <?= e(substr((string) $reserva['hora_fin'], 0, 5)) ?>)
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($puedeGestionar && ! $cancelada && ! $finalizada): ?>
            <a class="btn btn--ghost" href="<?= e(url('/reservas/' . $reservaId . '/editar')) ?>">Editar</a>
        <?php endif; ?>
        <a class="btn btn--ghost" href="<?= e(url('/reservas')) ?>">Volver</a>
    </div>
</div>

<div class="cards cards--4">
    <div class="card card--stat">
        <p class="card__label">Estado</p>
        <p class="card__value">
            <span class="badge <?= e($claseEstado($estado)) ?>"><?= e($estados[$estado] ?? $estado) ?></span>
        </p>
        <p class="card__hint">De la reserva</p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Precio acordado</p>
        <p class="card__value"><?= e(dinero((string) $precio)) ?></p>
        <p class="card__hint">
            <?php if (!empty($reserva['tarifa_nombre'])): ?>
                Tarifa: <?= e((string) $reserva['tarifa_nombre']) ?>
            <?php else: ?>
                Sin tarifa asociada
            <?php endif; ?>
        </p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Adelanto pactado</p>
        <p class="card__value"><?= e(dinero((string) $adelanto)) ?></p>
        <p class="card__hint">Mínimo a cancelar al reservar</p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Saldo pendiente</p>
        <p class="card__value"><?= e(dinero((string) $saldo)) ?></p>
        <p class="card__hint">Pagado: <?= e(dinero((string) $pagado)) ?></p>
    </div>
</div>

<?php if ($cancelada && !empty($reserva['motivo_cancelacion'])): ?>
    <div class="alert alert--danger" role="alert">
        <?= Icons::render('alerta') ?>
        <p>
            Reserva cancelada<?php if (!empty($reserva['cancelada_at'])): ?>
                el <?= e(fecha_legible(substr((string) $reserva['cancelada_at'], 0, 10))) ?>
            <?php endif; ?>.
            Motivo: <?= e((string) $reserva['motivo_cancelacion']) ?>
        </p>
    </div>
<?php endif; ?>

<div class="cards">
    <div class="card">
        <h2 class="section-title" style="margin-top:0;">Solicitante</h2>
        <p><strong><?= e((string) $reserva['solicitante_nombre']) ?></strong></p>
        <ul class="plain-list">
            <?php if (!empty($reserva['solicitante_telefono'])): ?>
                <li>Tel. <?= e((string) $reserva['solicitante_telefono']) ?></li>
            <?php endif; ?>
            <?php if (!empty($reserva['solicitante_email'])): ?>
                <li><?= e((string) $reserva['solicitante_email']) ?></li>
            <?php endif; ?>
            <?php if (!empty($reserva['solicitante_documento'])): ?>
                <li>Documento: <?= e((string) $reserva['solicitante_documento']) ?></li>
            <?php endif; ?>
            <?php if (!empty($reserva['socio_codigo'])): ?>
                <li>Socio: <code><?= e((string) $reserva['socio_codigo']) ?></code></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="card">
        <h2 class="section-title" style="margin-top:0;">Condiciones</h2>
        <?php if (!empty($reserva['condiciones'])): ?>
            <p><?= nl2br(e((string) $reserva['condiciones'])) ?></p>
        <?php else: ?>
            <p class="muted">No se registraron condiciones.</p>
        <?php endif; ?>

        <?php if (!empty($reserva['observaciones'])): ?>
            <h2 class="section-title">Observaciones</h2>
            <p><?= nl2br(e((string) $reserva['observaciones'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if ($puedeGestionar && ! $cancelada): ?>
    <div class="card">
        <form class="form form--panel" method="post" action="<?= e(url('/reservas/' . $reservaId . '/estado')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="estado-reserva">Estado de la reserva</label>
                <select id="estado-reserva" name="estado">
                    <?php foreach (['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'finalizada' => 'Finalizada'] as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $estado === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field--actions">
                <button type="submit" class="btn btn--ghost">Actualizar estado</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<h2 class="section-title">Pagos</h2>

<?php if ($pagos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('caja', 'icon', 26) ?></span>
        <h2>Todavía no hay pagos registrados</h2>
        <p>
            <?php if ($adelanto > 0): ?>
                El adelanto pactado es de <?= e(dinero((string) $adelanto)) ?>.
            <?php else: ?>
                Esta reserva no tiene adelanto pactado.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Monto</th>
                    <th scope="col">Medio de pago</th>
                    <th scope="col">Registrado por</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pagos as $pago): ?>
                    <tr>
                        <td><?= e(fecha_legible((string) $pago['fecha'])) ?></td>
                        <td>
                            <?= e(\App\Models\ReservaPago::TIPOS[(string) $pago['tipo']] ?? (string) $pago['tipo']) ?>
                        </td>
                        <td><strong><?= e(dinero((string) $pago['monto'])) ?></strong></td>
                        <td><?= e((string) ($pago['medio_pago_nombre'] ?? '—')) ?></td>
                        <td>
                            <?= e(trim((string) ($pago['usuario_nombres'] ?? '') . ' ' . (string) ($pago['usuario_apellidos'] ?? ''), ', ')) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($puedeCobrar && ! $cancelada): ?>
    <h2 class="section-title">Registrar pago</h2>

    <form class="form form--panel" method="post" action="<?= e(url('/reservas/' . $reservaId . '/pagos')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="pago-tipo">Tipo de pago <span class="req">*</span></label>
            <select id="pago-tipo" name="tipo" required>
                <option value="adelanto">Adelanto</option>
                <option value="saldo">Saldo</option>
                <option value="devolucion">Devolución</option>
            </select>
        </div>
        <div class="field">
            <label for="pago-fecha">Fecha <span class="req">*</span></label>
            <input type="date" id="pago-fecha" name="fecha" required max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>">
        </div>
        <div class="field">
            <label for="pago-monto">Monto (S/) <span class="req">*</span></label>
            <input type="number" id="pago-monto" name="monto" required min="0.01" max="9999999999.99" step="0.01" value="<?= e((string) old('monto', '')) ?>">
        </div>
        <div class="field">
            <label for="pago-medio">Medio de pago</label>
            <select id="pago-medio" name="medio_pago_id">
                <option value="">Sin especificar</option>
                <?php foreach ($medios as $medio): ?>
                    <option value="<?= e((string) $medio['id']) ?>"><?= e((string) $medio['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="pago-observaciones">Observaciones</label>
            <input type="text" id="pago-observaciones" name="observaciones" maxlength="300" value="<?= e((string) old('observaciones')) ?>">
        </div>
        <div class="field field--actions">
            <button type="submit" class="btn btn--primary">Registrar pago</button>
        </div>
    </form>
<?php endif; ?>

<?php if ($puedeAnular && ! $cancelada): ?>
    <h2 class="section-title">Cancelar reserva</h2>

    <div class="alert alert--danger" role="alert">
        <?= Icons::render('alerta') ?>
        <p>La reserva no se elimina: queda cancelada, con su motivo y su historial.</p>
    </div>

    <form class="form form--panel" method="post" action="<?= e(url('/reservas/' . $reservaId . '/cancelar')) ?>" data-confirmar="¿Cancelar esta reserva?">
        <?= csrf_field() ?>
        <div class="field">
            <label for="motivo">Motivo de la cancelación <span class="req">*</span></label>
            <input type="text" id="motivo" name="motivo" required minlength="5" maxlength="300" value="<?= e((string) old('motivo')) ?>" placeholder="Motivo de la cancelación">
        </div>
        <div class="field field--actions">
            <button type="submit" class="btn btn--danger">Cancelar reserva</button>
        </div>
    </form>
<?php endif; ?>
