<?php

declare(strict_types=1);

/**
 * Confirmación de recepción de la solicitud.
 *
 * Muestra solo el resumen de la solicitud (ambiente, fecha y horario). No expone
 * datos personales del solicitante.
 *
 * @var array<string, mixed> $resumen
 * @var string $codigo
 */

$hora = static fn (?string $valor): string => $valor === null || $valor === '' ? '—' : substr($valor, 0, 5);
$etiquetas = [
    'pendiente'   => 'Pendiente de revisión',
    'en_revision' => 'En revisión',
    'atendida'    => 'Atendida',
    'rechazada'   => 'Rechazada',
];
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Portal de alquileres</p>
        <h1>Solicitud recibida</h1>
        <p class="page-head__lead">
            Guarde su código de seguimiento. El club se comunicará con usted usando los
            datos de contacto que registró.
        </p>
    </div>
</div>

<section class="card card--highlight">
    <p class="card__label">Código de seguimiento</p>
    <p class="code-tag"><?= e($codigo) ?></p>

    <ul class="facts">
        <li><span>Ambiente</span><strong><?= e((string) ($resumen['instalacion_nombre'] ?? '')) ?></strong></li>
        <li><span>Fecha</span><strong><?= e(fecha_legible((string) ($resumen['fecha_evento'] ?? ''))) ?></strong></li>
        <li>
            <span>Horario</span>
            <strong><?= e($hora($resumen['hora_inicio'] ?? null)) ?> – <?= e($hora($resumen['hora_fin'] ?? null)) ?></strong>
        </li>
        <li>
            <span>Situación</span>
            <strong><?= e($etiquetas[(string) ($resumen['estado'] ?? 'pendiente')] ?? 'Pendiente de revisión') ?></strong>
        </li>
    </ul>
</section>

<div class="alert alert--warning" role="status">
    <p>
        <strong>Esto no es una reserva confirmada.</strong>
        Su horario no queda bloqueado hasta que el club revise la solicitud y confirme
        las condiciones del alquiler.
    </p>
</div>

<h2 class="section-title">¿Qué ocurre ahora?</h2>
<ol class="steps">
    <li>
        <strong>Revisión de la disponibilidad</strong>
        <span>El personal autorizado comprueba que el horario siga libre.</span>
    </li>
    <li>
        <strong>Contacto con usted</strong>
        <span>Le confirmarán o le propondrán un horario alternativo según las condiciones del club.</span>
    </li>
    <li>
        <strong>Confirmación y adelanto</strong>
        <span>Si acepta las condiciones, el club registra la reserva y el adelanto correspondiente.</span>
    </li>
</ol>

<p class="card__actions">
    <a class="btn btn--ghost" href="<?= e(url('/alquiler')) ?>">Volver a los ambientes</a>
    <a class="btn btn--primary" href="<?= e(url('/alquiler/solicitud')) ?>">Enviar otra solicitud</a>
</p>
