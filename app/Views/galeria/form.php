<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Alta y edición de una foto de la galería.
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

$accion = $esEdicion ? url('/fotos/' . (string) $foto['id']) : url('/fotos');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/fotos')) ?>">Fotos del club</a> · registro
        </p>
        <h1><?= $esEdicion ? 'Editar foto' : 'Subir foto' ?></h1>
        <p class="page-head__lead">
            Las imágenes se guardan fuera de la carpeta pública y se muestran en la
            portada y en la galería del portal.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/fotos')) ?>">Volver</a>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Fotografía</legend>

        <div class="field">
            <label for="archivo">Imagen <span class="req">*</span></label>
            <input type="file" id="archivo" name="archivo" accept="image/jpeg,image/png,image/webp,image/gif" required>
            <p class="field__hint">
                JPG, PNG, WEBP o GIF. Máximo <?= e((string) (int) config('security.uploads.max_size_mb', 5)) ?> MB.
                <?php if ($esEdicion && !empty($foto['archivo'])): ?>
                    <br>Si no sube una imagen nueva, se conserva la actual.
                <?php endif; ?>
            </p>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Ficha</legend>

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
                <label for="fecha_foto">Fecha</label>
                <input type="date" id="fecha_foto" name="fecha_foto" value="<?= e($valor('fecha_foto', date('Y-m-d'))) ?>">
            </div>

            <div class="field">
                <label for="orden">Orden</label>
                <input type="number" id="orden" name="orden" min="1" max="999" value="<?= e($valor('orden', '1')) ?>">
            </div>

            <div class="field">
                <label for="visible">Publicada en el portal</label>
                <select id="visible" name="visible">
                    <option value="1" <?= $visible ? 'selected' : '' ?>>Sí</option>
                    <option value="0" <?= $visible ? '' : 'selected' ?>>No</option>
                </select>
            </div>

            <div class="field">
                <label for="destacado">Noticia de portada</label>
                <select id="destacado" name="destacado">
                    <option value="0" <?= $destacado ? '' : 'selected' ?>>No (solo en la galería)</option>
                    <option value="1" <?= $destacado ? 'selected' : '' ?>>Sí (titular en la portada)</option>
                </select>
                <p class="field__hint">Solo las noticias de portada aparecen en el bloque de noticias del portal.</p>
            </div>
        </div>

        <div class="field">
            <label for="resumen">Entradilla</label>
            <textarea id="resumen" name="resumen" rows="2" maxlength="300" placeholder="Una o dos frases que acompañen al titular"><?= e($valor('resumen')) ?></textarea>
        </div>

        <div class="field">
            <label for="descripcion">Pie de foto</label>
            <textarea id="descripcion" name="descripcion" rows="3" maxlength="500" placeholder="Una frase que acompañe a la foto"><?= e($valor('descripcion')) ?></textarea>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary"><?= $esEdicion ? 'Guardar cambios' : 'Subir foto' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/fotos')) ?>">Cancelar</a>
    </div>
</form>
