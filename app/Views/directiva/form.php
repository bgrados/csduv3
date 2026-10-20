<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Alta y edición de un periodo de gestión.
 *
 * @var array<string, mixed>|null $directiva
 * @var string $modo
 * @var array<string, string> $estados
 */

$esEdicion = $modo === 'editar' && $directiva !== null;

$valor = static function (string $campo, mixed $porDefecto = '') use ($directiva): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($directiva !== null && array_key_exists($campo, $directiva)) {
        return (string) ($directiva[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$accion = $esEdicion ? url('/directiva/' . (string) $directiva['id']) : url('/directiva');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/directiva')) ?>">Junta directiva</a> · periodo de gestión
        </p>
        <h1><?= $esEdicion ? 'Editar periodo de gestión' : 'Nuevo periodo de gestión' ?></h1>
        <p class="page-head__lead">
            Un periodo de gestión agrupa a los integrantes de la junta directiva.
            Puede cerrar el anterior y abrir el nuevo sin perder el histórico.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/directiva')) ?>">Volver</a>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Periodo</legend>

        <div class="form-grid">
            <div class="field">
                <label for="nombre">Nombre <span class="req">*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="120" value="<?= e($valor('nombre')) ?>" placeholder="Gestión 2026-2030">
            </div>

            <div class="field">
                <label for="fecha_inicio">Fecha de inicio <span class="req">*</span></label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" required value="<?= e($valor('fecha_inicio', date('Y-m-d'))) ?>">
            </div>

            <div class="field">
                <label for="fecha_fin">Fecha de fin</label>
                <input type="date" id="fecha_fin" name="fecha_fin" value="<?= e($valor('fecha_fin')) ?>">
                <p class="field__hint">Déjelo vacío si el periodo sigue vigente.</p>
            </div>

            <div class="field">
                <label for="estado">Estado <span class="req">*</span></label>
                <select id="estado" name="estado" required>
                    <?php foreach ($estados as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $valor('estado', 'vigente') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="3" maxlength="2000" placeholder="Acuerdos o resoluciones internas del periodo"><?= e($valor('observaciones')) ?></textarea>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear periodo' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/directiva')) ?>">Cancelar</a>
    </div>
</form>
