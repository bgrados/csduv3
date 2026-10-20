<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Alta y edición de un movimiento de caja.
 *
 * @var array<string, mixed>|null $movimiento
 * @var string $modo
 * @var array<int, array<string, mixed>> $categorias
 * @var array<int, array<string, mixed>> $medios
 * @var array<int, array<string, mixed>> $periodos
 * @var array<int, array<string, mixed>> $socios
 */

$esEdicion = $modo === 'editar' && $movimiento !== null;

$valor = static function (string $campo, mixed $porDefecto = '') use ($movimiento): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($movimiento !== null && array_key_exists($campo, $movimiento)) {
        return (string) ($movimiento[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$tipo = $valor('tipo', 'ingreso');
$accion = $esEdicion ? url('/caja/' . (string) $movimiento['id']) : url('/caja');

$categoriasIngreso = array_values(array_filter(
    $categorias,
    static fn (array $categoria): bool => (string) $categoria['tipo'] === 'ingreso'
));
$categoriasEgreso = array_values(array_filter(
    $categorias,
    static fn (array $categoria): bool => (string) $categoria['tipo'] === 'egreso'
));
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/caja')) ?>">Caja</a> · <?= $esEdicion ? 'editar movimiento' : 'nuevo movimiento' ?>
        </p>
        <h1><?= $esEdicion ? 'Editar movimiento' : 'Nuevo movimiento' ?></h1>
        <p class="page-head__lead">
            Registre un ingreso o un egreso del club. Los importes se guardan en soles
            y no admiten fechas futuras.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/caja')) ?>">Volver</a>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Operación</legend>

        <div class="form-grid">
            <div class="field">
                <label for="tipo">Tipo <span class="req">*</span></label>
                <select id="tipo" name="tipo" required>
                    <option value="ingreso" <?= $tipo === 'ingreso' ? 'selected' : '' ?>>Ingreso</option>
                    <option value="egreso" <?= $tipo === 'egreso' ? 'selected' : '' ?>>Egreso</option>
                </select>
            </div>

            <div class="field">
                <label for="fecha">Fecha <span class="req">*</span></label>
                <input type="date" id="fecha" name="fecha" required max="<?= e(date('Y-m-d')) ?>" value="<?= e($valor('fecha', date('Y-m-d'))) ?>">
            </div>

            <div class="field">
                <label for="monto">Monto (S/) <span class="req">*</span></label>
                <input type="number" id="monto" name="monto" required min="0.01" max="9999999999.99" step="0.01" value="<?= e($valor('monto')) ?>" placeholder="0.00">
            </div>

            <div class="field">
                <label for="categoria_id">Categoría</label>
                <select id="categoria_id" name="categoria_id">
                    <option value="">Sin categoría</option>
                    <?php foreach ($categoriasIngreso as $categoria): ?>
                        <option value="<?= e((string) $categoria['id']) ?>" <?= $valor('categoria_id') === (string) $categoria['id'] ? 'selected' : '' ?>>
                            <?= e((string) $categoria['nombre']) ?> (ingreso)<?= (int) $categoria['activo'] === 1 ? '' : ' · inactiva' ?>
                        </option>
                    <?php endforeach; ?>
                    <?php foreach ($categoriasEgreso as $categoria): ?>
                        <option value="<?= e((string) $categoria['id']) ?>" <?= $valor('categoria_id') === (string) $categoria['id'] ? 'selected' : '' ?>>
                            <?= e((string) $categoria['nombre']) ?> (egreso)<?= (int) $categoria['activo'] === 1 ? '' : ' · inactiva' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="medio_pago_id">Medio de pago</label>
                <select id="medio_pago_id" name="medio_pago_id">
                    <option value="">Sin especificar</option>
                    <?php foreach ($medios as $medio): ?>
                        <option value="<?= e((string) $medio['id']) ?>" <?= $valor('medio_pago_id') === (string) $medio['id'] ? 'selected' : '' ?>>
                            <?= e((string) $medio['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="periodo_id">Periodo</label>
                <select id="periodo_id" name="periodo_id">
                    <option value="">Sin periodo</option>
                    <?php foreach ($periodos as $periodo): ?>
                        <option value="<?= e((string) $periodo['id']) ?>" <?= $valor('periodo_id') === (string) $periodo['id'] ? 'selected' : '' ?>>
                            <?= e((string) $periodo['nombre']) ?><?= (int) $periodo['cerrado'] === 1 ? ' (cerrado)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field__hint">Los periodos se crean en la configuración de caja.</p>
            </div>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Detalle</legend>

        <div class="form-grid">
            <div class="field">
                <label for="concepto">Concepto <span class="req">*</span></label>
                <input type="text" id="concepto" name="concepto" required maxlength="200" value="<?= e($valor('concepto')) ?>" placeholder="Cuota de socios, pago de luz…">
            </div>

            <div class="field">
                <label for="socio_codigo">Socio</label>
                <input
                    type="text"
                    id="socio_codigo"
                    name="socio_codigo"
                    list="lista-socios"
                    maxlength="30"
                    value="<?= e($valor('socio_codigo')) ?>"
                    placeholder="Código de socio (opcional)"
                >
                <datalist id="lista-socios">
                    <?php foreach ($socios as $socio): ?>
                        <option value="<?= e((string) $socio['codigo']) ?>">
                            <?= e(trim((string) $socio['apellidos'] . ', ' . (string) $socio['nombres'], ', ')) ?>
                        </option>
                    <?php endforeach; ?>
                </datalist>
                <p class="field__hint">Déjelo vacío si el movimiento no corresponde a un socio.</p>
            </div>

            <div class="field">
                <label for="referencia">Referencia</label>
                <input type="text" id="referencia" name="referencia" maxlength="60" value="<?= e($valor('referencia')) ?>" placeholder="N.º de operación o recibo">
            </div>
        </div>

        <div class="field">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="3" maxlength="1000" placeholder="Detalle del movimiento"><?= e($valor('observaciones')) ?></textarea>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary"><?= $esEdicion ? 'Guardar cambios' : 'Registrar movimiento' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/caja')) ?>">Cancelar</a>
    </div>
</form>

<?php if ($esEdicion && can('caja.anular') && (string) $movimiento['estado'] === 'registrado'): ?>
    <h2 class="section-title" id="anular">Anular movimiento</h2>

    <div class="alert alert--danger" role="alert">
        <?= Icons::render('alerta') ?>
        <p>
            Anular no elimina el registro: lo marca como anulado, deja de computar en los
            saldos y guarda el motivo en el historial del movimiento.
        </p>
    </div>

    <form class="form form--panel" method="post" action="<?= e(url('/caja/' . (string) $movimiento['id'] . '/anular')) ?>" data-confirmar="¿Anular este movimiento?">
        <?= csrf_field() ?>
        <div class="field">
            <label for="motivo">Motivo de la anulación <span class="req">*</span></label>
            <input type="text" id="motivo" name="motivo" required minlength="5" maxlength="300" value="<?= e((string) old('motivo')) ?>" placeholder="Motivo de la anulación">
        </div>
        <div class="field field--actions">
            <button type="submit" class="btn btn--danger">Anular movimiento</button>
        </div>
    </form>
<?php endif; ?>
