<?php

declare(strict_types=1);

/**
 * Edición de un integrante de la junta directiva.
 *
 * @var array<string, mixed> $directiva
 * @var array<string, mixed> $miembro
 * @var string $modo
 * @var array<int, array<string, mixed>> $cargos
 * @var array<string, string> $estados
 * @var array<int, array<string, mixed>> $socios
 */

$directivaId = (int) $directiva['id'];

$valor = static function (string $campo, mixed $porDefecto = '') use ($miembro): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if (array_key_exists($campo, $miembro)) {
        return (string) ($miembro[$campo] ?? '');
    }

    return (string) $porDefecto;
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/directiva')) ?>">Junta directiva</a> ·
            <a href="<?= e(url('/directiva/' . $directivaId)) ?>"><?= e((string) $directiva['nombre']) ?></a> ·
            integrante
        </p>
        <h1>Editar integrante</h1>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/directiva/' . $directivaId)) ?>">Volver</a>
    </div>
</div>

<form class="form" method="post" action="<?= e(url('/directiva/' . $directivaId . '/miembros/' . (string) $miembro['id'])) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Datos del integrante</legend>

        <div class="form-grid">
            <div class="field">
                <label for="cargo_id">Cargo <span class="req">*</span></label>
                <select id="cargo_id" name="cargo_id" required>
                    <?php foreach ($cargos as $cargo): ?>
                        <option value="<?= e((string) $cargo['id']) ?>" <?= $valor('cargo_id') === (string) $cargo['id'] ? 'selected' : '' ?>>
                            <?= e((string) $cargo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="socio_codigo">Código de socio</label>
                <input
                    type="text"
                    id="socio_codigo"
                    name="socio_codigo"
                    list="lista-socios-directiva"
                    maxlength="30"
                    value="<?= e($valor('socio_codigo')) ?>"
                >
                <datalist id="lista-socios-directiva">
                    <?php foreach ($socios as $socio): ?>
                        <option value="<?= e((string) $socio['codigo']) ?>">
                            <?= e(trim((string) $socio['apellidos'] . ', ' . (string) $socio['nombres'], ', ')) ?>
                        </option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="field">
                <label for="nombre_completo">Nombre completo</label>
                <input type="text" id="nombre_completo" name="nombre_completo" maxlength="180" value="<?= e($valor('nombre_completo')) ?>">
            </div>

            <div class="field">
                <label for="documento">Documento</label>
                <input type="text" id="documento" name="documento" maxlength="30" value="<?= e($valor('documento')) ?>">
            </div>

            <div class="field">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono" maxlength="30" value="<?= e($valor('telefono')) ?>">
            </div>

            <div class="field">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" maxlength="160" value="<?= e($valor('email')) ?>">
            </div>

            <div class="field">
                <label for="fecha_inicio">Desde</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= e($valor('fecha_inicio')) ?>">
            </div>

            <div class="field">
                <label for="fecha_fin">Hasta</label>
                <input type="date" id="fecha_fin" name="fecha_fin" value="<?= e($valor('fecha_fin')) ?>">
            </div>

            <div class="field">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
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
            <textarea id="observaciones" name="observaciones" rows="3" maxlength="300"><?= e($valor('observaciones')) ?></textarea>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary">Guardar cambios</button>
        <a class="btn btn--ghost" href="<?= e(url('/directiva/' . $directivaId)) ?>">Cancelar</a>
    </div>
</form>
