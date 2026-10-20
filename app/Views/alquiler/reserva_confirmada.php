<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Confirmación de la reserva tomada desde el portal público.
 *
 * @var array<string, mixed>|null $reserva
 * @var array<int, array<string, mixed>> $pagos
 */

$hora = static fn (?string $valor): string => $valor === null || $valor === '' ? '—' : substr($valor, 0, 5);
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Portal de alquileres</p>
        <h1><?= $reserva === null ? 'Reserva registrada' : 'Reserva confirmada' ?></h1>
        <?php if ($reserva !== null): ?>
            <p class="page-head__lead">
                Código de reserva <strong><?= e((string) $reserva['codigo']) ?></strong>.
                Guarde este código: el club lo utiliza para identificar la reserva.
            </p>
        <?php endif; ?>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/alquiler')) ?>">Ver ambientes</a>
    </div>
</div>

<?php if ($reserva === null): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('alquileres', 'icon', 26) ?></span>
        <h2>No encontramos la reserva</h2>
        <p>
            La reserva se muestra solo al terminar la reserva. Si ya la confirmó y
            cierra la página, comuníquese con el club indicando el código.
        </p>
    </div>
<?php else: ?>

    <div class="alert alert--danger" role="status">
        <p>
            La reserva quedó <strong>confirmada</strong> y el horario está bloqueado.
            El club puede contactarle para coordinar la entrega del ambiente.
        </p>
    </div>

    <div class="grid-2">
        <section class="card">
            <h2>Detalle de la reserva</h2>
            <ul class="facts">
                <li><span>Código</span><strong><?= e((string) $reserva['codigo']) ?></strong></li>
                <li><span>Ambiente</span><strong><?= e((string) ($reserva['instalacion_nombre'] ?? '—')) ?></strong></li>
                <li><span>Tipo de uso</span><strong><?= e((string) ($reserva['tipo_evento_nombre'] ?? 'Uso general')) ?></strong></li>
                <li><span>Fecha</span><strong><?= e(fecha_legible((string) $reserva['fecha_evento'])) ?></strong></li>
                <li><span>Horario</span><strong><?= e($hora($reserva['hora_inicio'])) ?> – <?= e($hora($reserva['hora_fin'])) ?></strong></li>
                <li><span>Estado</span><strong><?= e((string) $reserva['estado']) ?></strong></li>
            </ul>
        </section>

        <section class="card">
            <h2>Importes</h2>
            <ul class="facts">
                <li><span>Tarifa</span><strong><?= e(dinero((string) $reserva['precio_acordado'])) ?></strong></li>
                <li><span>Adelanto pactado</span><strong><?= e(dinero((string) $reserva['monto_adelanto'])) ?></strong></li>
                <li><span>Pagado</span><strong><?= e(dinero((string) $reserva['monto_pagado'])) ?></strong></li>
                <li>
                    <span>Saldo pendiente</span>
                    <strong><?= e(dinero((string) max(0.0, (float) $reserva['precio_acordado'] - (float) $reserva['monto_pagado']))) ?></strong>
                </li>
            </ul>

            <?php if ($pagos !== []): ?>
                <p class="muted">Pagos registrados:</p>
                <ul class="facts">
                    <?php foreach ($pagos as $pago): ?>
                        <li>
                            <span><?= e(fecha_legible((string) $pago['fecha'])) ?></span>
                            <strong><?= e(dinero((string) $pago['monto'])) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">
                    El saldo pendiente se abona en el club al momento de entregar el
                    ambiente.
                </p>
            <?php endif; ?>
        </section>
    </div>

    <section class="card">
        <h2>Próximos pasos</h2>
        <ol class="steps">
            <li>
                <strong>Guarde su código de reserva</strong>
                <span>Código <strong><?= e((string) $reserva['codigo']) ?></strong>. Basta para que el club localice su reserva.</span>
            </li>
            <li>
                <strong>Confirme el pago</strong>
                <span>
                    <?php if ((float) $reserva['monto_pagado'] > 0): ?>
                        Ya registramos su adelanto. Solo queda el saldo en el club.
                    <?php else: ?>
                        Pague el adelanto o el total al llegar al club, según lo acordado.
                    <?php endif; ?>
                </span>
            </li>
            <li>
                <strong>El club lo contacta</strong>
                <span>Para coordinar la entrega, el CGT y las condiciones del alquiler.</span>
            </li>
        </ol>
    </section>

<?php endif; ?>
