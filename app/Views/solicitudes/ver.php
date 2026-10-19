<?php

declare(strict_types=1);

/**
 * Detalle de una solicitud de alquiler.
 *
 * @var array<string, mixed> $solicitud
 * @var array<string, string> $estados
 */

$hora = static fn (?string $valor): string => $valor === null || $valor === '' ? '—' : substr($valor, 0, 5);
$estado = (string) $solicitud['estado'];
$puedeGestionar = can('alquileres.gestionar');
$revisor = trim(((string) ($solicitud['revisor_nombres'] ?? '')) . ' ' . ((string) ($solicitud['revisor_apellidos'] ?? '')));
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow"><a href="<?= e(url('/solicitudes')) ?>">Solicitudes</a> · detalle</p>
        <h1><code><?= e((string) $solicitud['codigo']) ?></code></h1>
        <p class="page-head__lead">
            Recibida el <?= e(fecha_legible($solicitud['created_at'] ?? null, true)) ?> desde el portal público.
        </p>
    </div>
    <div class="page-head__actions">
        <span class="badge badge--info"><?= e($estados[$estado] ?? $estado) ?></span>
    </div>
</div>

<?php if ((int) ($solicitud['conflicto_detectado'] ?? 0) === 1): ?>
    <div class="alert alert--danger" role="alert">
        <p>
            <strong>Posible cruce de horario.</strong>
            Al registrarse la solicitud existía otra solicitud pendiente en el mismo ambiente
            y horario. Verifique antes de confirmar cualquier condición.
        </p>
    </div>
<?php endif; ?>

<div class="grid-2">
    <section class="card">
        <h2>Solicitante</h2>
        <ul class="facts">
            <li><span>Nombre</span><strong><?= e((string) $solicitud['solicitante_nombre']) ?></strong></li>
            <li><span>Documento</span><strong><?= e((string) ($solicitud['solicitante_documento'] ?? '—')) ?></strong></li>
            <li>
                <span>Teléfono</span>
                <strong>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $solicitud['solicitante_telefono'])) ?>">
                        <?= e((string) $solicitud['solicitante_telefono']) ?>
                    </a>
                </strong>
            </li>
            <li>
                <span>Correo</span>
                <strong>
                    <?php if (!empty($solicitud['solicitante_email'])): ?>
                        <a href="mailto:<?= e((string) $solicitud['solicitante_email']) ?>"><?= e((string) $solicitud['solicitante_email']) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </strong>
            </li>
        </ul>
        <p class="muted">
            Estos datos se registraron para responder a esta solicitud. No se muestran en el portal público.
        </p>
    </section>

    <section class="card">
        <h2>Evento solicitado</h2>
        <ul class="facts">
            <li><span>Ambiente</span><strong><?= e((string) $solicitud['instalacion_nombre']) ?></strong></li>
            <li><span>Tipo de uso</span><strong><?= e((string) ($solicitud['tipo_evento_nombre'] ?? 'Sin especificar')) ?></strong></li>
            <li><span>Fecha</span><strong><?= e(fecha_legible((string) $solicitud['fecha_evento'])) ?></strong></li>
            <li><span>Horario</span><strong><?= e($hora($solicitud['hora_inicio'] ?? null)) ?> – <?= e($hora($solicitud['hora_fin'] ?? null)) ?></strong></li>
            <li><span>Personas</span><strong><?= e((string) ($solicitud['cantidad_personas'] ?? '—')) ?></strong></li>
        </ul>

        <?php if (!empty($solicitud['descripcion'])): ?>
            <h3>Descripción enviada</h3>
            <p><?= nl2br(e((string) $solicitud['descripcion'])) ?></p>
        <?php endif; ?>
    </section>
</div>

<?php if (!empty($solicitud['motivo_rechazo']) || !empty($solicitud['nota_interna'])): ?>
    <section class="card">
        <h2>Seguimiento interno</h2>
        <ul class="facts">
            <?php if (!empty($solicitud['motivo_rechazo'])): ?>
                <li><span>Motivo de rechazo</span><strong><?= e((string) $solicitud['motivo_rechazo']) ?></strong></li>
            <?php endif; ?>
            <?php if (!empty($solicitud['nota_interna'])): ?>
                <li><span>Nota interna</span><strong><?= e((string) $solicitud['nota_interna']) ?></strong></li>
            <?php endif; ?>
            <li><span>Revisada por</span><strong><?= e($revisor !== '' ? $revisor : '—') ?></strong></li>
            <li><span>Fecha de revisión</span><strong><?= e(fecha_legible($solicitud['revisado_at'] ?? null, true)) ?></strong></li>
        </ul>
    </section>
<?php endif; ?>

<?php if ($puedeGestionar): ?>
    <h2 class="section-title">Acciones de revisión</h2>

    <div class="action-grid">
        <?php if ($estado === 'pendiente'): ?>
            <section class="card">
                <h3>Marcar en revisión</h3>
                <p class="muted">Use esta opción mientras verifica la disponibilidad con el solicitante.</p>
                <form method="post" action="<?= e(url('/solicitudes/' . (string) $solicitud['id'] . '/en-revision')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost">Marcar en revisión</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="card">
            <h3>Marcar atendida</h3>
            <p class="muted">
                Registra que la solicitud fue respondida. <strong>No crea una reserva.</strong>
            </p>
            <form method="post" action="<?= e(url('/solicitudes/' . (string) $solicitud['id'] . '/atender')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="nota">Nota interna (opcional)</label>
                    <textarea id="nota" name="nota" rows="3" maxlength="500" placeholder="Detalle de la gestión realizada"></textarea>
                </div>
                <button type="submit" class="btn btn--primary">Marcar atendida</button>
            </form>
        </section>

        <section class="card">
            <h3>Rechazar solicitud</h3>
            <p class="muted">El motivo queda registrado en la auditoría del sistema.</p>
            <form method="post" action="<?= e(url('/solicitudes/' . (string) $solicitud['id'] . '/rechazar')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="motivo">Motivo del rechazo <span class="req">*</span></label>
                    <input type="text" id="motivo" name="motivo" maxlength="300" required placeholder="Por ejemplo: horario ya reservado">
                </div>
                <button type="submit" class="btn btn--danger">Rechazar</button>
            </form>
        </section>
    </div>

    <div class="alert alert--warning" role="status">
        <p>
            Convertir una solicitud en reserva, con tarifa, adelanto y pago, se implementa en el
            módulo de alquileres (Fase 4). Mientras tanto, la reserva se registra por el canal
            que el club determine.
        </p>
    </div>
<?php endif; ?>
