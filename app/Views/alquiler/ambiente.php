<?php

declare(strict_types=1);

/**
 * Detalle público de un ambiente y su disponibilidad en una fecha.
 *
 * @var array<string, mixed> $instalacion
 * @var array<int, array<string, mixed>> $tarifas
 * @var array<int, array<string, mixed>> $franjas
 * @var string $fecha
 */

$hora = static fn (?string $valor): string => $valor === null || $valor === '' ? '—' : substr($valor, 0, 5);
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/alquiler')) ?>">Ambientes</a> · detalle
        </p>
        <h1><?= e((string) $instalacion['nombre']) ?></h1>
        <p class="page-head__lead"><?= e((string) ($instalacion['descripcion'] ?? 'Ambiente del club')) ?></p>
    </div>    <div class="page-head__actions">
        <a class="btn btn--primary" href="<?= e(url('/alquiler/reservar?instalacion_id=' . (string) $instalacion['id'] . '&fecha=' . urlencode($fecha))) ?>">
            Reservar ahora
        </a>
        <a class="btn btn--ghost" href="<?= e(url('/alquiler/solicitud?instalacion_id=' . (string) $instalacion['id'] . '&fecha=' . urlencode($fecha))) ?>">
            Solicitar este ambiente
        </a>
    </div>
</div>

<?php if (!empty($instalacion['archivo'])): ?>
    <figure class="ambiente__foto">
        <img
            src="<?= e(url('/ambiente/imagen/' . (string) $instalacion['id'])) ?>"
            alt="<?= e((string) $instalacion['nombre']) ?>"
        >
    </figure>
<?php endif; ?>

<div class="grid-2">
    <section class="card">
        <h2>Características</h2>
        <ul class="facts">
            <li><span>Tipo</span><strong><?= e((string) ($instalacion['tipo'] ?? '—')) ?></strong></li>
            <li><span>Ubicación</span><strong><?= e((string) ($instalacion['ubicacion'] ?? '—')) ?></strong></li>
            <li><span>Aforo</span><strong><?= !empty($instalacion['aforo']) ? e((string) $instalacion['aforo']) . ' personas' : '—' ?></strong></li>
            <li><span>Adelanto</span><strong><?= ((int) ($instalacion['requiere_adelanto'] ?? 0) === 1) ? 'Sí, según condiciones' : 'No especificado' ?></strong></li>
        </ul>
    </section>

    <section class="card">
        <h2>Tarifas publicadas</h2>
        <?php if ($tarifas === []): ?>
            <p class="muted">El club todavía no publicó tarifas para este ambiente.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead>
                    <tr>
                        <th scope="col">Tipo de uso</th>
                        <th scope="col">Franja</th>
                        <th scope="col">Tarifa</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tarifas as $tarifa): ?>
                        <tr>
                            <td><?= e((string) ($tarifa['tipo_evento_nombre'] ?? $tarifa['nombre'] ?? 'Uso general')) ?></td>
                            <td>
                                <?php if (!empty($tarifa['hora_inicio']) || !empty($tarifa['hora_fin'])): ?>
                                    <?= e($hora($tarifa['hora_inicio'] ?? null)) ?> – <?= e($hora($tarifa['hora_fin'] ?? null)) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><strong><?= e(dinero((string) $tarifa['monto'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <h2>Disponibilidad por fecha</h2>
    <form class="form form--inline" method="get" action="<?= e(url('/alquiler/ambiente/' . (string) $instalacion['id'])) ?>">
        <div class="field">
            <label for="fecha">Fecha del evento</label>
            <input type="date" id="fecha" name="fecha" value="<?= e($fecha) ?>" required>
        </div>
        <div class="field field--actions">
            <button type="submit" class="btn btn--primary">Consultar</button>
        </div>
    </form>

    <p class="muted">
        Se muestran únicamente los horarios ya reservados o en revisión para la fecha elegida.
    </p>

    <?php if ($franjas === []): ?>
        <p class="notice notice--ok">No hay horarios ocupados registrados para el <?= e(fecha_legible($fecha)) ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead>
                <tr>
                    <th scope="col">Desde</th>
                    <th scope="col">Hasta</th>
                    <th scope="col">Situación</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($franjas as $franja): ?>
                    <tr>
                        <td>
                            <?= e($hora($franja['hora_inicio'] ?? null)) ?>
                            <?php if ((int) ($franja['cruza_medianoche'] ?? 0) === 1): ?>
                                <br><span class="muted"><?= e(fecha_legible((string) ($franja['fecha_evento'] ?? ''))) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($hora($franja['hora_fin'] ?? null)) ?>
                            <?php if ((int) ($franja['cruza_medianoche'] ?? 0) === 1): ?>
                                <br><span class="muted">+1 día</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= ((string) ($franja['estado'] ?? '') === 'confirmada') ? 'badge--danger' : 'badge--warn' ?>">
                                <?= e((string) ($franja['estado'] ?? '')) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="card__actions">
        <a class="btn btn--primary" href="<?= e(url('/alquiler/reservar?instalacion_id=' . (string) $instalacion['id'] . '&fecha=' . urlencode($fecha))) ?>">
            Reservar para el <?= e(fecha_legible($fecha)) ?>
        </a>
        <a class="btn btn--ghost" href="<?= e(url('/alquiler/solicitud?instalacion_id=' . (string) $instalacion['id'] . '&fecha=' . urlencode($fecha))) ?>">
            Enviar una consulta
        </a>
    </p>
</section>
