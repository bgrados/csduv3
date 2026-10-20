<?php

declare(strict_types=1);

/**
 * Alta y edición de un negocio de la unidad.
 *
 * @var array<string, mixed>|null $negocio
 * @var string $modo
 * @var array<string, string> $categorias
 */

$esEdicion = $modo === 'editar' && $negocio !== null;

$valor = static function (string $campo, mixed $porDefecto = '') use ($negocio): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($negocio !== null && array_key_exists($campo, $negocio)) {
        return (string) ($negocio[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$visiblePrevio = old('visible', null);
$visible = $visiblePrevio !== null
    ? (string) $visiblePrevio === '1'
    : ($esEdicion ? (int) $negocio['visible'] === 1 : true);

$destacadoPrevio = old('destacado', null);
$destacado = $destacadoPrevio !== null
    ? (string) $destacadoPrevio === '1'
    : ($esEdicion ? (int) $negocio['destacado'] === 1 : false);

$accion = $esEdicion ? url('/negocios/' . (string) $negocio['id']) : url('/negocios');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/negocios')) ?>">Negocios de la unidad</a> · registro
        </p>
        <h1><?= $esEdicion ? 'Editar negocio' : 'Nuevo negocio' ?></h1>
        <p class="page-head__lead">
            Los negocios que se publican en el portal del club. El enlace puede ser
            una web, una red social o el contacto del club.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/negocios')) ?>">Volver</a>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Ficha del negocio</legend>

        <div class="form-grid">
            <div class="field">
                <label for="nombre">Nombre <span class="req">*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="140" value="<?= e($valor('nombre')) ?>" placeholder="Panadería San Martín">
            </div>

            <div class="field">
                <label for="categoria">Categoría <span class="req">*</span></label>
                <select id="categoria" name="categoria" required>
                    <?php foreach ($categorias as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $valor('categoria', 'comercio') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="web">Enlace</label>
                <input type="url" id="web" name="web" maxlength="255" value="<?= e($valor('web')) ?>" placeholder="https://…">
            </div>

            <div class="field">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono" maxlength="30" value="<?= e($valor('telefono')) ?>">
            </div>

            <div class="field">
                <label for="whatsapp">WhatsApp</label>
                <input type="text" id="whatsapp" name="whatsapp" maxlength="30" value="<?= e($valor('whatsapp')) ?>">
            </div>

            <div class="field">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" maxlength="160" value="<?= e($valor('email')) ?>">
            </div>

            <div class="field">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion" maxlength="200" value="<?= e($valor('direccion')) ?>">
            </div>

            <div class="field">
                <label for="horario">Horario</label>
                <input type="text" id="horario" name="horario" maxlength="200" value="<?= e($valor('horario')) ?>" placeholder="Lunes a sábado de 8:00 a 20:00">
            </div>

            <div class="field">
                <label for="orden">Orden</label>
                <input type="number" id="orden" name="orden" min="1" max="999" value="<?= e($valor('orden', '1')) ?>">
            </div>
        </div>

        <div class="field">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="3" maxlength="600"><?= e($valor('descripcion')) ?></textarea>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Imagen y publicación</legend>

        <div class="field">
            <label for="archivo">Logotipo o foto</label>
            <input type="file" id="archivo" name="archivo" accept="image/jpeg,image/png,image/webp,image/gif">
            <p class="field__hint">JPG, PNG, WEBP o GIF. Es opcional.</p>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="visible">Publicado en el portal</label>
                <select id="visible" name="visible">
                    <option value="1" <?= $visible ? 'selected' : '' ?>>Sí</option>
                    <option value="0" <?= $visible ? '' : 'selected' ?>>No</option>
                </select>
            </div>

            <div class="field">
                <label for="destacado">Aparece en la portada</label>
                <select id="destacado" name="destacado">
                    <option value="0" <?= $destacado ? '' : 'selected' ?>>No (solo en el directorio)</option>
                    <option value="1" <?= $destacado ? 'selected' : '' ?>>Sí (tarjeta en la portada)</option>
                </select>
                <p class="field__hint">
                    Los negocios de la portada se muestran en la página principal; todos
                    los visibles están en el directorio <code>/negocios</code>.
                </p>
            </div>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary"><?= $esEdicion ? 'Guardar cambios' : 'Registrar negocio' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/negocios')) ?>">Cancelar</a>
    </div>
</form>
