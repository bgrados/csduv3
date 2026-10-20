<?php

declare(strict_types=1);

/**
 * Formulario de alta y edición de socios.
 *
 * @var array<string, mixed>|null $socio
 * @var string $modo  'crear' | 'editar'
 * @var array<int, array<string, mixed>> $categorias
 * @var array<string, string> $estados
 * @var array<string, string> $tiposDocumento
 * @var array<string, string> $sexos
 * @var string $codigoSugerido
 */

$esEdicion = $modo === 'editar' && $socio !== null;
$estaDeBaja = $esEdicion && (string) $socio['estado'] === 'baja';

$valor = static function (string $campo, mixed $porDefecto = '') use ($socio): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($socio !== null && array_key_exists($campo, $socio)) {
        return (string) ($socio[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$accion = $esEdicion ? url('/socios/' . (string) $socio['id']) : url('/socios');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/socios')) ?>">Socios</a> ·
            <?= $esEdicion ? 'edición' : 'alta' ?>
        </p>
        <h1><?= $esEdicion ? 'Editar socio' : 'Nuevo socio' ?></h1>
        <p class="page-head__lead">
            Los datos personales se usan únicamente para la gestión interna del club.
            El código de socio debe ser único.
        </p>
    </div>
</div>

<?php if ($estaDeBaja): ?>
    <div class="alert alert--warning" role="status">
        <p>
            Este socio figura <strong>de baja</strong>. Si cambia su estado a «Activo» y guarda,
            quedará reactivado; el registro de la baja anterior se conserva en el historial.
        </p>
    </div>
<?php endif; ?>

<form class="form" method="post" action="<?= e($accion) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Identificación</legend>

        <div class="form-grid">
            <div class="field">
                <label for="codigo">Código de socio <span class="req">*</span></label>
                <input
                    type="text"
                    id="codigo"
                    name="codigo"
                    maxlength="30"
                    required
                    value="<?= e($valor('codigo', $codigoSugerido)) ?>"
                    autocomplete="off"
                >
                <p class="field__hint">Se sugiere el siguiente correlativo; puede cambiarlo si el club usa otro esquema.</p>
            </div>

            <div class="field">
                <label for="tipo_documento">Tipo de documento <span class="req">*</span></label>
                <select id="tipo_documento" name="tipo_documento" required>
                    <?php foreach ($tiposDocumento as $clave => $etiqueta): ?>
                        <option
                            value="<?= e((string) $clave) ?>"
                            <?= $valor('tipo_documento', 'DNI') === (string) $clave ? 'selected' : '' ?>
                        ><?= e((string) $etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="numero_documento">Número de documento</label>
                <input
                    type="text"
                    id="numero_documento"
                    name="numero_documento"
                    maxlength="30"
                    value="<?= e($valor('numero_documento')) ?>"
                    autocomplete="off"
                >
                <p class="field__hint">No se admiten dos socios con el mismo tipo y número.</p>
            </div>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Datos personales</legend>

        <div class="form-grid">
            <div class="field">
                <label for="nombres">Nombres <span class="req">*</span></label>
                <input
                    type="text"
                    id="nombres"
                    name="nombres"
                    maxlength="120"
                    required
                    value="<?= e($valor('nombres')) ?>"
                >
            </div>

            <div class="field">
                <label for="apellidos">Apellidos <span class="req">*</span></label>
                <input
                    type="text"
                    id="apellidos"
                    name="apellidos"
                    maxlength="120"
                    required
                    value="<?= e($valor('apellidos')) ?>"
                >
            </div>

            <div class="field">
                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                <input
                    type="date"
                    id="fecha_nacimiento"
                    name="fecha_nacimiento"
                    value="<?= e($valor('fecha_nacimiento')) ?>"
                >
            </div>

            <div class="field">
                <label for="sexo">Sexo</label>
                <select id="sexo" name="sexo">
                    <option value="">Sin especificar</option>
                    <?php foreach ($sexos as $clave => $etiqueta): ?>
                        <option
                            value="<?= e((string) $clave) ?>"
                            <?= $valor('sexo') === (string) $clave ? 'selected' : '' ?>
                        ><?= e((string) $etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="estado_civil">Estado civil</label>
                <input
                    type="text"
                    id="estado_civil"
                    name="estado_civil"
                    maxlength="30"
                    value="<?= e($valor('estado_civil')) ?>"
                    placeholder="Por ejemplo: soltero/a, casado/a"
                >
            </div>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Contacto</legend>

        <div class="form-grid">
            <div class="field">
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" maxlength="30" value="<?= e($valor('telefono')) ?>">
            </div>

            <div class="field">
                <label for="telefono_alterno">Teléfono alterno</label>
                <input
                    type="tel"
                    id="telefono_alterno"
                    name="telefono_alterno"
                    maxlength="30"
                    value="<?= e($valor('telefono_alterno')) ?>"
                >
            </div>

            <div class="field">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" maxlength="160" value="<?= e($valor('email')) ?>">
            </div>

            <div class="field">
                <label for="distrito">Distrito</label>
                <input type="text" id="distrito" name="distrito" maxlength="80" value="<?= e($valor('distrito')) ?>">
            </div>

            <div class="field">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion" maxlength="200" value="<?= e($valor('direccion')) ?>">
            </div>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Membresía</legend>

        <div class="form-grid">
            <div class="field">
                <label for="fecha_ingreso">Fecha de ingreso</label>
                <input type="date" id="fecha_ingreso" name="fecha_ingreso" value="<?= e($valor('fecha_ingreso')) ?>">
            </div>

            <div class="field">
                <label for="categoria_id">Categoría</label>
                <select id="categoria_id" name="categoria_id">
                    <option value="">Sin categoría</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <?php
                        $categoriaActual = $valor('categoria_id') === (string) $categoria['id'];
                        $inactiva = (int) $categoria['activo'] !== 1;
                        ?>
                        <option
                            value="<?= e((string) $categoria['id']) ?>"
                            <?= $categoriaActual ? 'selected' : '' ?>
                            <?= $inactiva && !$categoriaActual ? 'disabled' : '' ?>
                        ><?= e((string) $categoria['nombre']) ?><?= $inactiva ? ' (inactiva)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($categorias === []): ?>
                    <p class="field__hint">
                        Todavía no hay categorías configuradas.
                        <?php if (can('socios.editar')): ?>
                            <a href="<?= e(url('/socios/categorias')) ?>">Cree la primera categoría</a>.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="field__hint">Las categorías inactivas no se pueden asignar de nuevo.</p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="estado">Estado <span class="req">*</span></label>
                <select id="estado" name="estado" required>
                    <?php foreach ($estados as $clave => $etiqueta): ?>
                        <option
                            value="<?= e((string) $clave) ?>"
                            <?= $valor('estado', 'activo') === (string) $clave ? 'selected' : '' ?>
                        ><?= e((string) $etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="4" maxlength="2000"><?= e($valor('observaciones')) ?></textarea>
            <p class="field__hint">Información interna útil para la junta directiva. No incluya datos sensibles innecesarios.</p>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary">
            <?= $esEdicion ? 'Guardar cambios' : 'Registrar socio' ?>
        </button>
        <a class="btn btn--ghost" href="<?= e($esEdicion ? url('/socios/' . (string) $socio['id']) : url('/socios')) ?>">Cancelar</a>
    </div>
</form>
