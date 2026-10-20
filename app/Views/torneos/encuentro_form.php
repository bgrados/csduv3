<?php

declare(strict_types=1);

/**
 * Edición de un encuentro y su resultado.
 *
 * @var array<string, mixed> $encuentro
 * @var array<string, mixed> $torneo
 * @var array<string, string> $estados
 * @var array<int, array<string, mixed>> $instalaciones
 */

$valor = static function (string $campo, mixed $porDefecto = '') use ($encuentro): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if (array_key_exists($campo, $encuentro)) {
        return (string) ($encuentro[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$torneoId = (int) $torneo['id'];
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/deportes')) ?>">Deportes y torneos</a> ·
            <a href="<?= e(url('/deportes/torneos/' . $torneoId)) ?>"><?= e((string) $torneo['nombre']) ?></a> ·
            encuentro
        </p>
        <h1>Editar encuentro</h1>
        <p class="page-head__lead">
            <strong><?= e((string) ($encuentro['local_nombre'] ?? 'Equipo local')) ?></strong>
            vs
            <strong><?= e((string) ($encuentro['visitante_nombre'] ?? 'Equipo visitante')) ?></strong>
            <?php if (!empty($encuentro['jornada_nombre'])): ?>
                · <?= e((string) $encuentro['jornada_nombre']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/deportes/torneos/' . $torneoId)) ?>">Volver al torneo</a>
    </div>
</div>

<form class="form" method="post" action="<?= e(url('/deportes/encuentros/' . (int) $encuentro['id'])) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Programación</legend>

        <div class="form-grid">
            <div class="field">
                <label for="fecha">Fecha <span class="req">*</span></label>
                <input type="date" id="fecha" name="fecha" required value="<?= e($valor('fecha')) ?>">
            </div>

            <div class="field">
                <label for="hora">Hora</label>
                <input type="time" id="hora" name="hora" value="<?= e(substr($valor('hora'), 0, 5)) ?>">
                <p class="field__hint">Opcional. Se muestra en el fixture público.</p>
            </div>

            <div class="field">
                <label for="instalacion_id">Sede</label>
                <select id="instalacion_id" name="instalacion_id">
                    <option value="">Por definir</option>
                    <?php foreach ($instalaciones as $instalacion): ?>
                        <option value="<?= e((string) $instalacion['id']) ?>" <?= $valor('instalacion_id') === (string) $instalacion['id'] ? 'selected' : '' ?>>
                            <?= e((string) $instalacion['nombre']) ?><?= (int) $instalacion['activo'] === 1 ? '' : ' (inactivo)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Resultado</legend>

        <div class="form-grid">
            <div class="field">
                <label for="estado">Estado del encuentro <span class="req">*</span></label>
                <select id="estado" name="estado" required>
                    <?php foreach ($estados as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $valor('estado', 'programado') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field__hint">Los estados «Jugado» y «Walkover» se incluyen en la tabla de posiciones.</p>
            </div>

            <div class="field">
                <label for="marcador_local">Goles de <?= e((string) ($encuentro['local_nombre'] ?? 'local')) ?></label>
                <input type="number" id="marcador_local" name="marcador_local" min="0" max="999" step="1" value="<?= e($valor('marcador_local')) ?>" placeholder="—">
            </div>

            <div class="field">
                <label for="marcador_visitante">Goles de <?= e((string) ($encuentro['visitante_nombre'] ?? 'visitante')) ?></label>
                <input type="number" id="marcador_visitante" name="marcador_visitante" min="0" max="999" step="1" value="<?= e($valor('marcador_visitante')) ?>" placeholder="—">
            </div>
        </div>

        <div class="field">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="3" maxlength="300" placeholder="Incidencias, programación pendiente u observaciones"><?= e($valor('observaciones')) ?></textarea>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary">Guardar encuentro</button>
        <a class="btn btn--ghost" href="<?= e(url('/deportes/torneos/' . $torneoId)) ?>">Cancelar</a>
    </div>
</form>
