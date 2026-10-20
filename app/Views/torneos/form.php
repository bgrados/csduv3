<?php

declare(strict_types=1);

/**
 * Formulario de alta y edición de torneos.
 *
 * @var array<string, mixed>|null $torneo
 * @var string $modo
 * @var array<int, array<string, mixed>> $deportes
 * @var array<string, string> $modalidades
 * @var array<string, string> $estados
 */

$esEdicion = $modo === 'editar' && $torneo !== null;

$valor = static function (string $campo, mixed $porDefecto = '') use ($torneo): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($torneo !== null && array_key_exists($campo, $torneo)) {
        return (string) ($torneo[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$accion = $esEdicion ? url('/deportes/torneos/' . (string) $torneo['id']) : url('/deportes/torneos');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/deportes')) ?>">Deportes y torneos</a> · <?= $esEdicion ? 'edición' : 'alta' ?>
        </p>
        <h1><?= $esEdicion ? 'Editar torneo' : 'Nuevo torneo' ?></h1>
        <p class="page-head__lead">
            Defina la disciplina, la categoría y el periodo. Después podrá inscribir equipos,
            generar el fixture y registrar los resultados.
        </p>
    </div>
</div>

<?php if ($deportes === []): ?>
    <div class="alert alert--warning" role="status">
        <?= \App\Core\Icons::render('alerta') ?>
        <p>No hay disciplinas activas. Importe la información inicial de la base de datos antes de crear un torneo.</p>
    </div>
<?php endif; ?>

<form class="form" method="post" action="<?= e($accion) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Datos del torneo</legend>

        <div class="form-grid">
            <div class="field">
                <label for="nombre">Nombre <span class="req">*</span></label>
                <input type="text" id="nombre" name="nombre" maxlength="150" required value="<?= e($valor('nombre')) ?>" placeholder="Por ejemplo: Campeonato de Fulbito 2026">
            </div>

            <div class="field">
                <label for="deporte_id">Disciplina <span class="req">*</span></label>
                <select id="deporte_id" name="deporte_id" required>
                    <option value="">Seleccione una disciplina</option>
                    <?php foreach ($deportes as $deporte): ?>
                        <option value="<?= e((string) $deporte['id']) ?>" <?= $valor('deporte_id') === (string) $deporte['id'] ? 'selected' : '' ?>>
                            <?= e((string) $deporte['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="categoria">Categoría</label>
                <input type="text" id="categoria" name="categoria" maxlength="80" list="categorias-sugeridas" value="<?= e($valor('categoria')) ?>" placeholder="Fulbito, Libre, Sub-20...">
                <datalist id="categorias-sugeridas">
                    <option value="Fulbito"></option>
                    <option value="Libre"></option>
                    <option value="Sub-20"></option>
                    <option value="Femenino"></option>
                    <option value="Máster"></option>
                </datalist>
            </div>

            <div class="field">
                <label for="modalidad">Modalidad <span class="req">*</span></label>
                <select id="modalidad" name="modalidad" required>
                    <?php foreach ($modalidades as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $valor('modalidad', 'todos_contra_todos') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field__hint">La generación automática de fixture está disponible para todos contra todos.</p>
            </div>

            <div class="field">
                <label for="fecha_inicio">Fecha de inicio</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= e($valor('fecha_inicio')) ?>">
            </div>

            <div class="field">
                <label for="fecha_fin">Fecha de fin</label>
                <input type="date" id="fecha_fin" name="fecha_fin" value="<?= e($valor('fecha_fin')) ?>">
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
            <label for="reglamento">Reglamento</label>
            <textarea id="reglamento" name="reglamento" rows="5" maxlength="5000" placeholder="Bases, inscripción, reglas de juego y criterios de desempate"><?= e($valor('reglamento')) ?></textarea>
        </div>

        <div class="field">
            <label for="observaciones">Observaciones internas</label>
            <textarea id="observaciones" name="observaciones" rows="3" maxlength="5000" placeholder="Notas de organización"><?= e($valor('observaciones')) ?></textarea>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary">
            <?= $esEdicion ? 'Guardar cambios' : 'Crear torneo' ?>
        </button>
        <a class="btn btn--ghost" href="<?= e($esEdicion ? url('/deportes/torneos/' . (string) $torneo['id']) : url('/deportes')) ?>">Cancelar</a>
    </div>
</form>
