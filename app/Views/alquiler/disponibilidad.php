<?php

declare(strict_types=1);

/**
 * Consulta pública de disponibilidad por ambiente y fecha.
 *
 * @var array<int, array<string, mixed>> $instalaciones
 * @var array<string, mixed>|null $instalacion
 * @var string $fecha
 * @var array<int, array<string, mixed>> $franjas
 * @var bool $consultado
 */

$hora = static fn (?string $valor): string => $valor === null || $valor === '' ? '—' : substr($valor, 0, 5);
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Portal de alquileres</p>
        <h1>Disponibilidad</h1>
        <p class="page-head__lead">
            Elija el ambiente y la fecha del evento. La consulta muestra los horarios ya
            ocupados o en revisión. La confirmación final siempre la realiza el club.
        </p>
    </div>
</div>

<form class="form form--panel" method="get" action="<?= e(url('/alquiler/disponibilidad')) ?>">
    <div class="field">
        <label for="instalacion_id">Ambiente</label>
        <select id="instalacion_id" name="instalacion_id" required>
            <option value="">Seleccione un ambiente</option>
            <?php foreach ($instalaciones as $opcion): ?>
                <option
                    value="<?= e((string) $opcion['id']) ?>"
                    <?= ($instalacion !== null && (int) $instalacion['id'] === (int) $opcion['id']) ? 'selected' : '' ?>
                ><?= e((string) $opcion['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="fecha">Fecha del evento</label>
        <input type="date" id="fecha" name="fecha" value="<?= e($fecha) ?>" required>
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--primary">Comprobar disponibilidad</button>
    </div>
</form>

<?php if ($instalaciones === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= \App\Core\Icons::render('alquileres', 'icon', 26) ?></span>
        <h2>Sin ambientes publicados</h2>
        <p>El club todavía no publicó ambientes en el portal.</p>
    </div>
<?php elseif ($consultado && $instalacion !== null): ?>
    <section class="card">
        <h2><?= e((string) $instalacion['nombre']) ?> · <?= e(fecha_legible($fecha)) ?></h2>

        <?php if ($franjas === []): ?>
            <p class="notice notice--ok">
                No hay horarios ocupados registrados para esa fecha. Puede enviar su solicitud
                indicando el horario que necesita.
            </p>
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
            <p class="muted">
                Los horarios que no aparecen en la lista se consideran libres según los registros
                actuales, sujetos a la revisión del club.
            </p>
        <?php endif; ?>

        <p class="card__actions">
            <a class="btn btn--primary" href="<?= e(url('/alquiler/solicitud?instalacion_id=' . (string) $instalacion['id'] . '&fecha=' . urlencode($fecha))) ?>">
                Solicitar este horario
            </a>
        </p>
    </section>
<?php elseif ($consultado): ?>
    <div class="alert alert--warning" role="status">
        <p>El ambiente seleccionado no está publicado. Elija otro ambiente.</p>
    </div>
<?php endif; ?>
