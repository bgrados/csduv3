<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Alta y edición de una noticia con su foto.
 *
 * @var array<string, mixed>|null $foto
 * @var string $modo
 * @var array<string, string> $categorias
 */

$esEdicion = $modo === 'editar' && $foto !== null;

$valor = static function (string $campo, mixed $porDefecto = '') use ($foto): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($foto !== null && array_key_exists($campo, $foto)) {
        return (string) ($foto[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$visiblePrevio = old('visible', null);
$visible = $visiblePrevio !== null
    ? (string) $visiblePrevio === '1'
    : ($esEdicion ? (int) $foto['visible'] === 1 : true);

$destacadoPrevio = old('destacado', null);
$destacado = $destacadoPrevio !== null
    ? (string) $destacadoPrevio === '1'
    : ($esEdicion ? (int) ($foto['destacado'] ?? 0) === 1 : false);

$accion = $esEdicion ? url('/noticias/' . (string) $foto['id']) : url('/noticias');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/noticias')) ?>">Noticias y fotos</a> · <?= $esEdicion ? 'editar' : 'nueva' ?>
        </p>
        <h1><?= $esEdicion ? 'Editar noticia' : 'Nueva noticia' ?></h1>
        <p class="page-head__lead">
            Escriba el titular, la entradilla y suba la foto. Las imágenes se
            convierten a WebP para que pesen menos.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/noticias')) ?>">Volver</a>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>La foto</legend>

        <div class="field">
            <label for="archivo">Imagen <span class="req">*</span></label>
            <input type="file" id="archivo" name="archivo" accept="image/jpeg,image/png,image/webp,image/gif" required>
            <p class="field__hint">
                Se convierte a WebP y se reduce a 2000 px de ancho.
                <?php if ($esEdicion && !empty($foto['archivo'])): ?>
                    <br>Si no sube una imagen nueva, se conserva la actual.
                <?php endif; ?>
            </p>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>La noticia</legend>

        <div class="form-grid">
            <div class="field">
                <label for="titulo">Titular <span class="req">*</span></label>
                <input type="text" id="titulo" name="titulo" required maxlength="180" value="<?= e($valor('titulo')) ?>" placeholder="Asamblea general de socios">
            </div>

            <div class="field">
                <label for="categoria">Categoría <span class="req">*</span></label>
                <select id="categoria" name="categoria" required>
                    <?php foreach ($categorias as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $valor('categoria', 'evento') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="fecha_foto">Fecha de la noticia</label>
                <input type="date" id="fecha_foto" name="fecha_foto" value="<?= e($valor('fecha_foto', date('Y-m-d'))) ?>">
            </div>

            <div class="field">
                <label for="orden">Orden</label>
                <input type="number" id="orden" name="orden" min="1" max="999" value="<?= e($valor('orden', '1')) ?>">
            </div>

            <div class="field">
                <label for="visible">Publicada</label>
                <select id="visible" name="visible">
                    <option value="1" <?= $visible ? 'selected' : '' ?>>Sí, visible en el portal</option>
                    <option value="0" <?= $visible ? '' : 'selected' ?>>No, queda como borrador</option>
                </select>
            </div>

            <div class="field">
                <label for="destacado">Publicar en la portada</label>
                <select id="destacado" name="destacado">
                    <option value="0" <?= $destacado ? '' : 'selected' ?>>No, solo en la galería</option>
                    <option value="1" <?= $destacado ? 'selected' : '' ?>>Sí, como noticia de portada</option>
                </select>
                <p class="field__hint">Solo puede haber una noticia en portada: al elegirla, las demás salen de ella.</p>
            </div>
        </div>

        <div class="field">
            <label for="resumen">Entradilla</label>
            <textarea id="resumen" name="resumen" rows="2" maxlength="300" placeholder="Una o dos frases que acompañen al titular"><?= e($valor('resumen')) ?></textarea>
        </div>

        <div class="field">
            <label for="descripcion">Pie de foto</label>
            <textarea id="descripcion" name="descripcion" rows="3" maxlength="500" placeholder="Detalle de la foto"><?= e($valor('descripcion')) ?></textarea>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary"><?= $esEdicion ? 'Guardar cambios' : 'Publicar noticia' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/noticias')) ?>">Cancelar</a>
    </div>
</form>
