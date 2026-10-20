<?php

declare(strict_types=1);

/**
 * Alta y edición de un proceso electoral.
 *
 * @var array<string, mixed>|null $proceso
 * @var string $modo
 * @var array<string, string> $estados
 * @var int $anios
 */

$esEdicion = $modo === 'editar' && $proceso !== null;

$valor = static function (string $campo, mixed $porDefecto = '') use ($proceso): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($proceso !== null && array_key_exists($campo, $proceso)) {
        return (string) ($proceso[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$accion = $esEdicion ? url('/elecciones/' . (string) $proceso['id']) : url('/elecciones');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/elecciones')) ?>">Elecciones</a> · proceso
        </p>
        <h1><?= $esEdicion ? 'Editar proceso electoral' : 'Nuevo proceso electoral' ?></h1>
        <p class="page-head__lead">
            Las reglas aplicables se toman del estatuto. Este módulo registra el
            proceso y sus resultados; no realiza la votación.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/elecciones')) ?>">Volver</a>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Proceso</legend>

        <div class="form-grid">
            <div class="field">
                <label for="nombre">Nombre <span class="req">*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="150" value="<?= e($valor('nombre')) ?>" placeholder="Elección de junta directiva 2026">
            </div>

            <div class="field">
                <label for="fecha_convocatoria">Fecha de convocatoria</label>
                <input type="date" id="fecha_convocatoria" name="fecha_convocatoria" value="<?= e($valor('fecha_convocatoria')) ?>">
            </div>

            <div class="field">
                <label for="fecha_eleccion">Fecha de elección</label>
                <input type="date" id="fecha_eleccion" name="fecha_eleccion" value="<?= e($valor('fecha_eleccion')) ?>">
            </div>

            <div class="field">
                <label for="estado">Estado <span class="req">*</span></label>
                <select id="estado" name="estado" required>
                    <?php foreach ($estados as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $valor('estado', 'planificado') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="3" maxlength="1000" placeholder="Alcance del proceso"><?= e($valor('descripcion')) ?></textarea>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Mandato que se elige</legend>

        <div class="form-grid">
            <div class="field">
                <label for="periodo_inicio">Inicio del mandato</label>
                <input type="date" id="periodo_inicio" name="periodo_inicio" value="<?= e($valor('periodo_inicio')) ?>">
            </div>

            <div class="field">
                <label for="periodo_fin">Fin del mandato</label>
                <input type="date" id="periodo_fin" name="periodo_fin" value="<?= e($valor('periodo_fin')) ?>">
            </div>

            <div class="field">
                <label for="anios_mandato">Años de mandato</label>
                <input type="number" id="anios_mandato" name="anios_mandato" min="1" max="10" value="<?= e($valor('anios_mandato', (string) $anios)) ?>">
                <p class="field__hint">Valor por defecto tomado de la configuración del club.</p>
            </div>
        </div>

        <div class="field">
            <label for="reglamento">Reglamento aplicable</label>
            <textarea id="reglamento" name="reglamento" rows="5" maxlength="5000" placeholder="Reglas tomadas del estatuto"><?= e($valor('reglamento')) ?></textarea>
        </div>

        <div class="field">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="3" maxlength="2000"><?= e($valor('observaciones')) ?></textarea>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear proceso' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/elecciones')) ?>">Cancelar</a>
    </div>
</form>
