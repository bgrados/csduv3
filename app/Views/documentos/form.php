<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Alta y edición de un documento.
 *
 * @var array<string, mixed>|null $documento
 * @var string $modo
 * @var array<string, string> $categorias
 * @var array<int, array<string, mixed>> $socios
 */

$esEdicion = $modo === 'editar' && $documento !== null;

$valor = static function (string $campo, mixed $porDefecto = '') use ($documento): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($documento !== null && array_key_exists($campo, $documento)) {
        return (string) ($documento[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$privadoPrevio = old('es_privado', null);
$privado = $privadoPrevio !== null
    ? (string) $privadoPrevio === '1'
    : ($esEdicion ? (int) $documento['es_privado'] === 1 : true);

$accion = $esEdicion ? url('/documentos/' . (string) $documento['id']) : url('/documentos');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/documentos')) ?>">Documentos</a> · registro
        </p>
        <h1><?= $esEdicion ? 'Editar documento' : 'Nuevo documento' ?></h1>
        <p class="page-head__lead">
            Los archivos se guardan fuera de la carpeta pública y solo se descargan
            desde el panel, con el permiso correspondiente.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/documentos')) ?>">Volver</a>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Datos del documento</legend>

        <div class="form-grid">
            <div class="field">
                <label for="titulo">Título <span class="req">*</span></label>
                <input type="text" id="titulo" name="titulo" required maxlength="180" value="<?= e($valor('titulo')) ?>" placeholder="Acta de asamblea general">
            </div>

            <div class="field">
                <label for="categoria">Categoría <span class="req">*</span></label>
                <select id="categoria" name="categoria" required>
                    <?php foreach ($categorias as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $valor('categoria', 'general') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="fecha_documento">Fecha del documento</label>
                <input type="date" id="fecha_documento" name="fecha_documento" value="<?= e($valor('fecha_documento')) ?>">
            </div>

            <div class="field">
                <label for="socio_codigo">Socio relacionado</label>
                <input
                    type="text"
                    id="socio_codigo"
                    name="socio_codigo"
                    list="lista-socios-documentos"
                    maxlength="30"
                    value="<?= e($valor('socio_codigo')) ?>"
                    placeholder="Código de socio (opcional)"
                >
                <datalist id="lista-socios-documentos">
                    <?php foreach ($socios as $socio): ?>
                        <option value="<?= e((string) $socio['codigo']) ?>">
                            <?= e(trim((string) $socio['apellidos'] . ', ' . (string) $socio['nombres'], ', ')) ?>
                        </option>
                    <?php endforeach; ?>
                </datalist>
            </div>
        </div>

        <div class="field">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="3" maxlength="2000"><?= e($valor('descripcion')) ?></textarea>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Archivo</legend>

        <div class="field">
            <label for="archivo"><?= $esEdicion ? 'Reemplazar archivo' : 'Adjuntar archivo' ?></label>
            <input type="file" id="archivo" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.odt,.txt">
            <p class="field__hint">
                Formatos permitidos: PDF, imágenes y documentos de Office. Máximo 5 MB.
                <?php if ($esEdicion && !empty($documento['archivo'])): ?>
                    <br>Archivo actual: <?= e((string) $documento['nombre_original']) ?>. Si no sube uno nuevo, se conserva.
                <?php endif; ?>
            </p>
        </div>

        <div class="field">
            <label for="es_privado">Privacidad</label>
            <select id="es_privado" name="es_privado">
                <option value="1" <?= $privado ? 'selected' : '' ?>>Solo internos</option>
                <option value="0" <?= $privado ? '' : 'selected' ?>>Compartible</option>
            </select>
            <p class="field__hint">La descarga siempre exige sesión y permiso; esta marca ordena el archivo.</p>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary"><?= $esEdicion ? 'Guardar cambios' : 'Registrar documento' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/documentos')) ?>">Cancelar</a>
    </div>
</form>
