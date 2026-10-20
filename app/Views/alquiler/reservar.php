<?php

declare(strict_types=1);

/**
 * Reserva directa desde el portal público.
 *
 * El ambiente, la tarifa y el horario se comprueban siempre en el servidor.
 * La reserva queda **confirmada** y bloquea el horario; el pago del adelanto
 * se anota en caja cuando el solicitante indica que ya lo realizó.
 *
 * @var array<string, mixed>|null $instalacion
 * @var array<int, array<string, mixed>> $instalaciones
 * @var array<int, array<string, mixed>> $tiposEvento
 * @var array<int, array<string, mixed>> $tarifas
 * @var array<int, array<string, mixed>> $medios
 * @var array<int, array<string, mixed>> $franjas
 * @var array<string, mixed> $prefill
 * @var float $porcentajeAdelanto
 * @var int $marca
 */

$valor = static function (string $campo, mixed $porDefecto = '') use ($prefill): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if (array_key_exists($campo, $prefill)) {
        return (string) ($prefill[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$hora = static fn (?string $valor): string => $valor === null || $valor === '' ? '—' : substr($valor, 0, 5);
$instalacionId = (int) $valor('instalacion_id', $instalacion === null ? 0 : (int) $instalacion['id']);
$tarifaSeleccionada = (int) $valor('tarifa_id', 0);

if ($tarifaSeleccionada === 0 && $tarifas !== []) {
    $tarifaSeleccionada = (int) $tarifas[0]['id'];
}

$precio = 0.0;
$tarifaElegida = null;

foreach ($tarifas as $tarifa) {
    if ((int) $tarifa['id'] === $tarifaSeleccionada) {
        $tarifaElegida = $tarifa;
        $precio = (float) $tarifa['monto'];
        break;
    }
}

$requiereAdelanto = $instalacion !== null && (int) ($instalacion['requiere_adelanto'] ?? 0) === 1;
$adelanto = $requiereAdelanto ? round($precio * ($porcentajeAdelanto / 100), 2) : 0.0;
$saldo = max(0.0, $precio - $adelanto);
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/alquiler')) ?>">Ambientes</a> · reserva
        </p>
        <h1>Reservar un ambiente</h1>
        <p class="page-head__lead">
            Complete los datos y la reserva queda confirmada al instante. El importe
            y el adelanto se calculan con la tarifa publicada por el club.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/alquiler/disponibilidad')) ?>">Ver disponibilidad</a>
    </div>
</div>

<?php if ($instalaciones === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= \App\Core\Icons::render('alquileres', 'icon', 26) ?></span>
        <h2>No hay ambientes disponibles</h2>
        <p>El club todavía no publicó ambientes con tarifas. Comuníquese directamente con la institución.</p>
    </div>
<?php else: ?>

<?php if ($tarifas === []): ?>
    <div class="notice">
        El ambiente seleccionado todavía no tiene tarifas publicadas. Puede enviar una
        <a href="<?= e(url('/alquiler/solicitud')) ?>">solicitud</a> y el club le confirmará el precio.
    </div>
<?php else: ?>

<form class="form" method="post" action="<?= e(url('/alquiler/reservar')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_ts" value="<?= e((string) $marca) ?>">
    <input type="hidden" name="sitio_web" value="" tabindex="-1" autocomplete="off" aria-hidden="true">

    <fieldset class="form__group">
        <legend>Ambiente y tarifa</legend>

        <div class="form-grid">
            <div class="field">
                <label for="instalacion_id">Ambiente <span class="req">*</span></label>
                <select id="instalacion_id" name="instalacion_id" required>
                    <?php foreach ($instalaciones as $opcion): ?>
                        <option value="<?= e((string) $opcion['id']) ?>" <?= $instalacionId === (int) $opcion['id'] ? 'selected' : '' ?>>
                            <?= e((string) $opcion['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="tarifa_id">Tarifa <span class="req">*</span></label>
                <select id="tarifa_id" name="tarifa_id" required>
                    <?php foreach ($tarifas as $tarifa): ?>
                        <option value="<?= e((string) $tarifa['id']) ?>" <?= $tarifaSeleccionada === (int) $tarifa['id'] ? 'selected' : '' ?>>
                            <?= e((string) ($tarifa['tipo_evento_nombre'] ?? $tarifa['nombre'])) ?>
                            — <?= e(dinero((string) $tarifa['monto'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="tipo_evento_id">Tipo de evento</label>
                <select id="tipo_evento_id" name="tipo_evento_id">
                    <option value="">Sin especificar</option>
                    <?php foreach ($tiposEvento as $tipo): ?>
                        <option value="<?= e((string) $tipo['id']) ?>" <?= (string) $valor('tipo_evento_id') === (string) $tipo['id'] ? 'selected' : '' ?>>
                            <?= e((string) $tipo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Fecha y hora</legend>

        <div class="form-grid">
            <div class="field">
                <label for="fecha_evento">Fecha <span class="req">*</span></label>
                <input type="date" id="fecha_evento" name="fecha_evento" required min="<?= e(date('Y-m-d')) ?>" value="<?= e($valor('fecha_evento', date('Y-m-d'))) ?>">
            </div>

            <div class="field">
                <label for="hora_inicio">Hora de inicio <span class="req">*</span></label>
                <input type="time" id="hora_inicio" name="hora_inicio" required value="<?= e($valor('hora_inicio')) ?>">
            </div>

            <div class="field">
                <label for="hora_fin">Hora de fin <span class="req">*</span></label>
                <input type="time" id="hora_fin" name="hora_fin" required value="<?= e($valor('hora_fin')) ?>">
                <p class="field__hint">
                    Si la hora de fin es anterior a la de inicio, el alquiler termina
                    de madrugada (por ejemplo, de 19:00 a 03:00 del día siguiente).
                </p>
            </div>

            <div class="field">
                <label for="cantidad_personas">Cantidad de personas</label>
                <input type="number" id="cantidad_personas" name="cantidad_personas" min="1" max="1000" value="<?= e($valor('cantidad_personas')) ?>">
            </div>
        </div>

        <?php if ($franjas !== []): ?>
            <p class="muted">
                Horarios ya reservados en esa fecha:
                <?php foreach ($franjas as $franja): ?>
                    <strong><?= e($hora($franja['hora_inicio'] ?? null)) ?>–<?= e($hora($franja['hora_fin'] ?? null)) ?></strong>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </fieldset>

    <fieldset class="form__group">
        <legend>Sus datos</legend>

        <div class="form-grid">
            <div class="field">
                <label for="solicitante_nombre">Nombre o razón social <span class="req">*</span></label>
                <input type="text" id="solicitante_nombre" name="solicitante_nombre" required maxlength="150" value="<?= e($valor('solicitante_nombre')) ?>">
            </div>

            <div class="field">
                <label for="solicitante_telefono">Teléfono <span class="req">*</span></label>
                <input type="text" id="solicitante_telefono" name="solicitante_telefono" required maxlength="30" value="<?= e($valor('solicitante_telefono')) ?>">
            </div>

            <div class="field">
                <label for="solicitante_documento">Documento</label>
                <input type="text" id="solicitante_documento" name="solicitante_documento" maxlength="30" value="<?= e($valor('solicitante_documento')) ?>">
            </div>

            <div class="field">
                <label for="solicitante_email">Correo electrónico</label>
                <input type="email" id="solicitante_email" name="solicitante_email" maxlength="160" value="<?= e($valor('solicitante_email')) ?>">
            </div>
        </div>

        <div class="field">
            <label for="descripcion">Detalle del evento</label>
            <textarea id="descripcion" name="descripcion" rows="3" maxlength="1000" placeholder="Motivo, número de asistentes, equipos, actividades"><?= e($valor('descripcion')) ?></textarea>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Forma de pago</legend>

        <div class="form-grid">
            <div class="field">
                <label for="medio_pago_id">Medio de pago</label>
                <select id="medio_pago_id" name="medio_pago_id">
                    <option value="">Sin especificar</option>
                    <?php foreach ($medios as $medio): ?>
                        <option value="<?= e((string) $medio['id']) ?>"><?= e((string) $medio['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="pagado_ahora">¿Ya realizó el adelanto?</label>
                <select id="pagado_ahora" name="pagado_ahora" required>
                    <option value="0">No, pagaré en el club</option>
                    <option value="1">Sí, ya transferí o pagué</option>
                </select>
                <p class="field__hint">
                    Si marca que ya pagó, el abono queda registrado en la caja del club.
                </p>
            </div>
        </div>
    </fieldset>

    <div class="card card--highlight">
        <h2 class="section-title" style="margin-top:0;">Resumen de la reserva</h2>
        <ul class="facts">
            <li><span>Tarifa</span><strong><?= e(dinero((string) $precio)) ?></strong></li>
            <li><span>Adelanto</span><strong><?= e(dinero((string) $adelanto)) ?></strong></li>
            <li><span>Saldo a pagar en el club</span><strong><?= e(dinero((string) $saldo)) ?></strong></li>
        </ul>
    </div>

    <div class="check">
        <input type="checkbox" id="acepta_condiciones" name="acepta_condiciones" value="1" required>
        <label for="acepta_condiciones">
            Confirmo que los datos son correctos, acepto las condiciones del alquiler y
            entiendo que <strong>la reserva queda confirmada</strong> y el horario bloqueado.
        </label>
    </div>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary">Confirmar reserva</button>
        <a class="btn btn--ghost" href="<?= e(url('/alquiler')) ?>">Cancelar</a>
    </div>
</form>

<?php endif; ?>
<?php endif; ?>
