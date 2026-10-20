<?php

declare(strict_types=1);

/**
 * Formulario de alta y edición de ambientes.
 *
 * @var array<string, mixed>|null $instalacion
 * @var string $modo
 * @var array<string, string> $tipos
 */

$esEdicion = $modo === 'editar' && $instalacion !== null;

$valor = static function (string $campo, mixed $porDefecto = '') use ($instalacion): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($instalacion !== null && array_key_exists($campo, $instalacion)) {
        return (string) ($instalacion[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$activoPrevio = old('activo', null);
$activo = $activoPrevio !== null
    ? (string) $activoPrevio === '1'
    : ($esEdicion ? (int) $instalacion['activo'] === 1 : true);

$adelantoPrevio = old('requiere_adelanto', null);
$requiereAdelanto = $adelantoPrevio !== null
    ? (string) $adelantoPrevio === '1'
    : ($esEdicion && (int) $instalacion['requiere_adelanto'] === 1);

$accion = $esEdicion ? url('/ambientes/' . (string) $instalacion['id']) : url('/ambientes');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/ambientes')) ?>">Ambientes y tarifas</a> · <?= $esEdicion ? 'edición' : 'alta' ?>
        </p>
        <h1><?= $esEdicion ? 'Editar ambiente' : 'Nuevo ambiente' ?></h1>
        <p class="page-head__lead">
            Registre las características del espacio. La publicación en el portal requiere
            que el ambiente esté activo y tenga al menos una tarifa vigente.
        </p>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Identificación</legend>

        <div class="form-grid">
            <div class="field">
                <label for="nombre">Nombre <span class="req">*</span></label>
                <input type="text" id="nombre" name="nombre" maxlength="120" required value="<?= e($valor('nombre')) ?>" placeholder="Por ejemplo: Salón principal">
                <p class="field__hint">Debe ser único para evitar confusiones en el portal.</p>
            </div>

            <div class="field">
                <label for="tipo">Tipo <span class="req">*</span></label>
                <select id="tipo" name="tipo" required>
                    <?php foreach ($tipos as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $valor('tipo', 'ambiente') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="ubicacion">Ubicación</label>
                <input type="text" id="ubicacion" name="ubicacion" maxlength="150" value="<?= e($valor('ubicacion')) ?>" placeholder="Sede, piso, referencia">
            </div>

            <div class="field">
                <label for="aforo">Aforo referencial</label>
                <input type="number" id="aforo" name="aforo" min="1" max="5000" step="1" value="<?= e($valor('aforo')) ?>" placeholder="Número de personas">
                <p class="field__hint">Opcional. Se muestra como referencia en el portal.</p>
            </div>
        </div>

        <div class="field">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="3" maxlength="300" placeholder="Características y condiciones del ambiente"><?= e($valor('descripcion')) ?></textarea>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Publicación</legend>

        <div class="check">
            <input type="checkbox" id="requiere_adelanto" name="requiere_adelanto" value="1" <?= $requiereAdelanto ? 'checked' : '' ?>>
            <label for="requiere_adelanto">El alquiler requiere un adelanto según las condiciones del club.</label>
        </div>

        <div class="check">
            <input type="checkbox" id="activo" name="activo" value="1" <?= $activo ? 'checked' : '' ?>>
            <label for="activo">Ambiente activo. Si además tiene una tarifa vigente, se publica en el portal.</label>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary">
            <?= $esEdicion ? 'Guardar cambios' : 'Crear ambiente' ?>
        </button>
        <a class="btn btn--ghost" href="<?= e(url('/ambientes')) ?>">Cancelar</a>
    </div>
</form>
