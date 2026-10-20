<?php

declare(strict_types=1);

/**
 * Formulario de alta y edición de tarifas.
 *
 * @var array<string, mixed> $instalacion
 * @var array<string, mixed>|null $tarifa
 * @var string $modo
 * @var array<int, array<string, mixed>> $tiposEvento
 */

$esEdicion = $modo === 'editar' && $tarifa !== null;

$valor = static function (string $campo, mixed $porDefecto = '') use ($tarifa): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($tarifa !== null && array_key_exists($campo, $tarifa)) {
        return (string) ($tarifa[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$activoPrevio = old('activo', null);
$activa = $activoPrevio !== null
    ? (string) $activoPrevio === '1'
    : ($esEdicion ? (int) $tarifa['activo'] === 1 : true);

$accion = $esEdicion
    ? url('/ambientes/' . (string) $instalacion['id'] . '/tarifas/' . (string) $tarifa['id'])
    : url('/ambientes/' . (string) $instalacion['id'] . '/tarifas');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/ambientes')) ?>">Ambientes y tarifas</a> ·
            <a href="<?= e(url('/ambientes/' . (string) $instalacion['id'] . '/tarifas')) ?>"><?= e((string) $instalacion['nombre']) ?></a> ·
            <?= $esEdicion ? 'edición' : 'alta' ?>
        </p>
        <h1><?= $esEdicion ? 'Editar tarifa' : 'Nueva tarifa' ?></h1>
        <p class="page-head__lead">
            Defina el importe y, si corresponde, la franja horaria y el periodo de vigencia.
            Los importes se expresan en soles peruanos (PEN).
        </p>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Datos de la tarifa</legend>

        <div class="form-grid">
            <div class="field">
                <label for="nombre">Nombre <span class="req">*</span></label>
                <input type="text" id="nombre" name="nombre" maxlength="120" required value="<?= e($valor('nombre')) ?>" placeholder="Por ejemplo: Tarifa general diurna">
            </div>

            <div class="field">
                <label for="tipo_evento_id">Tipo de uso</label>
                <select id="tipo_evento_id" name="tipo_evento_id">
                    <option value="">Uso general</option>
                    <?php foreach ($tiposEvento as $tipo): ?>
                        <?php
                        $actual = $valor('tipo_evento_id') === (string) $tipo['id'];
                        $inactivo = (int) $tipo['activo'] !== 1;
                        ?>
                        <option value="<?= e((string) $tipo['id']) ?>" <?= $actual ? 'selected' : '' ?> <?= $inactivo && !$actual ? 'disabled' : '' ?>>
                            <?= e((string) $tipo['nombre']) ?><?= $inactivo ? ' (inactivo)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="monto">Monto <span class="req">*</span></label>
                <input type="number" id="monto" name="monto" min="0" max="9999999999.99" step="0.01" required value="<?= e($valor('monto')) ?>" placeholder="0.00">
                <p class="field__hint">Moneda: <?= e((string) config('app.currency', 'PEN')) ?>.</p>
            </div>

            <div class="field">
                <label for="hora_inicio">Hora de inicio</label>
                <input type="time" id="hora_inicio" name="hora_inicio" value="<?= e(substr($valor('hora_inicio'), 0, 5)) ?>">
            </div>

            <div class="field">
                <label for="hora_fin">Hora de fin</label>
                <input type="time" id="hora_fin" name="hora_fin" value="<?= e(substr($valor('hora_fin'), 0, 5)) ?>">
                <p class="field__hint">Deje ambas horas vacías si la tarifa aplica a todo el día.</p>
            </div>

            <div class="field">
                <label for="vigente_desde">Vigente desde</label>
                <input type="date" id="vigente_desde" name="vigente_desde" value="<?= e($valor('vigente_desde')) ?>">
            </div>

            <div class="field">
                <label for="vigente_hasta">Vigente hasta</label>
                <input type="date" id="vigente_hasta" name="vigente_hasta" value="<?= e($valor('vigente_hasta')) ?>">
                <p class="field__hint">Opcional. Sin fechas, la tarifa se considera permanente.</p>
            </div>
        </div>

        <div class="field">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="3" maxlength="300" placeholder="Condiciones, restricciones o notas internas"><?= e($valor('observaciones')) ?></textarea>
        </div>

        <div class="check">
            <input type="checkbox" id="activo" name="activo" value="1" <?= $activa ? 'checked' : '' ?>>
            <label for="activo">Tarifa activa. Solo las tarifas activas y vigentes se publican en el portal.</label>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary">
            <?= $esEdicion ? 'Guardar cambios' : 'Crear tarifa' ?>
        </button>
        <a class="btn btn--ghost" href="<?= e(url('/ambientes/' . (string) $instalacion['id'] . '/tarifas')) ?>">Cancelar</a>
    </div>
</form>
