<?php

declare(strict_types=1);

/**
 * Alta y edición de una actividad del calendario.
 *
 * @var array<string, mixed>|null $actividad
 * @var string $modo
 * @var array<string, string> $tipos
 * @var array<string, string> $estados
 * @var array<int, array<string, mixed>> $instalaciones
 */

$esEdicion = $modo === 'editar' && $actividad !== null;

$valor = static function (string $campo, mixed $porDefecto = '') use ($actividad): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($actividad !== null && array_key_exists($campo, $actividad)) {
        return (string) ($actividad[$campo] ?? '');
    }

    return (string) $porDefecto;
};

// La fecha y la hora se guardan juntas en `fecha_inicio`.
$fecha = $valor('fecha', date('Y-m-d'));
$hora = '';

if ($esEdicion) {
    $crudo = (string) $actividad['fecha_inicio'];
    $fecha = substr($crudo, 0, 10);
    $hora = (int) $actividad['todo_el_dia'] === 1 ? '' : substr($crudo, 11, 5);
} else {
    $hora = substr($valor('hora'), 0, 5);
}

$todoElDiaPrevio = old('todo_el_dia', null);
$todoElDia = $todoElDiaPrevio !== null
    ? (string) $todoElDiaPrevio === '1'
    : ($esEdicion ? (int) $actividad['todo_el_dia'] === 1 : false);

$visiblePrevio = old('visible', null);
$visible = $visiblePrevio !== null
    ? (string) $visiblePrevio === '1'
    : true;

$accion = $esEdicion ? url('/calendario/' . (string) $actividad['id']) : url('/calendario');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/calendario')) ?>">Calendario</a> · actividad
        </p>
        <h1><?= $esEdicion ? 'Editar actividad' : 'Nueva actividad' ?></h1>
        <p class="page-head__lead">
            Programe una reunión, elección, evento o actividad del club.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/calendario')) ?>">Volver</a>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Actividad</legend>

        <div class="form-grid">
            <div class="field">
                <label for="titulo">Título <span class="req">*</span></label>
                <input type="text" id="titulo" name="titulo" required maxlength="180" value="<?= e($valor('titulo')) ?>" placeholder="Asamblea general de socios">
            </div>

            <div class="field">
                <label for="tipo">Tipo <span class="req">*</span></label>
                <select id="tipo" name="tipo" required>
                    <?php foreach ($tipos as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $valor('tipo', 'institucional') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="fecha">Fecha <span class="req">*</span></label>
                <input type="date" id="fecha" name="fecha" required value="<?= e($fecha) ?>">
            </div>

            <div class="field">
                <label for="hora">Hora</label>
                <input type="time" id="hora" name="hora" value="<?= e($hora) ?>">
                <p class="field__hint">Se ignora si la actividad es de todo el día.</p>
            </div>

            <div class="field">
                <label for="fecha_fin">Fecha de fin</label>
                <input type="date" id="fecha_fin" name="fecha_fin" value="<?= e($valor('fecha_fin')) ?>">
                <p class="field__hint">Déjelo vacío si es de un solo día.</p>
            </div>

            <div class="field">
                <label for="lugar">Lugar</label>
                <input type="text" id="lugar" name="lugar" maxlength="150" value="<?= e($valor('lugar')) ?>" placeholder="Salón principal">
            </div>

            <div class="field">
                <label for="instalacion_id">Sede del club</label>
                <select id="instalacion_id" name="instalacion_id">
                    <option value="">Sin sede asignada</option>
                    <?php foreach ($instalaciones as $instalacion): ?>
                        <option value="<?= e((string) $instalacion['id']) ?>" <?= $valor('instalacion_id') === (string) $instalacion['id'] ? 'selected' : '' ?>>
                            <?= e((string) $instalacion['nombre']) ?><?= (int) $instalacion['activo'] === 1 ? '' : ' (inactivo)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="estado">Estado <span class="req">*</span></label>
                <select id="estado" name="estado" required>
                    <?php foreach ($estados as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $valor('estado', 'programada') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="4" maxlength="2000"><?= e($valor('descripcion')) ?></textarea>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="todo_el_dia">Todo el día</label>
                <select id="todo_el_dia" name="todo_el_dia">
                    <option value="0" <?= $todoElDia ? '' : 'selected' ?>>No</option>
                    <option value="1" <?= $todoElDia ? 'selected' : '' ?>>Sí</option>
                </select>
            </div>

            <div class="field">
                <label for="visible">Visible en el portal público</label>
                <select id="visible" name="visible">
                    <option value="0" <?= $visible ? '' : 'selected' ?>>No</option>
                    <option value="1" <?= $visible ? 'selected' : '' ?>>Sí</option>
                </select>
            </div>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary"><?= $esEdicion ? 'Guardar cambios' : 'Programar actividad' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/calendario')) ?>">Cancelar</a>
    </div>
</form>
