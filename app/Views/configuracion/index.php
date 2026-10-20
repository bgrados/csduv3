<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Configuración institucional del club.
 *
 * @var array<string, string> $configuracion
 * @var array<string, string> $marcas
 * @var array<string, array{0: string, 1: string}> $campos
 */

$valor = static function (string $campo, mixed $porDefecto = '') use ($configuracion): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    $guardado = $configuracion[$campo] ?? null;

    if ($guardado !== null && $guardado !== '') {
        return (string) $guardado;
    }

    return (string) $porDefecto;
};

$booleano = static function (string $campo, bool $porDefecto = false) use ($configuracion): bool {
    $previo = old($campo, null);

    if ($previo !== null) {
        return (string) $previo === '1';
    }

    $guardado = $configuracion[$campo] ?? null;

    if ($guardado !== null && $guardado !== '') {
        return (string) $guardado === '1';
    }

    return $porDefecto;
};

$marcaActual = static function (string $tipo) use ($configuracion): string {
    $archivo = $configuracion['marca_' . $tipo] ?? '';

    return $archivo === '' ? '' : marca_url($tipo);
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Administración</p>
        <h1>Configuración</h1>
        <p class="page-head__lead">
            Identidad, contacto, marca y parámetros de alquileres del club. Las
            imágenes se convierten a WebP para que pesen menos.
        </p>
    </div>
</div>

<form class="form" method="post" action="<?= e(url('/configuracion')) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Identidad</legend>

        <div class="form-grid">
            <div class="field">
                <label for="nombre_club">Nombre del club <span class="req">*</span></label>
                <input type="text" id="nombre_club" name="nombre_club" required maxlength="150" value="<?= e($valor('nombre_club', (string) config('app.name'))) ?>">
            </div>

            <div class="field">
                <label for="siglas">Siglas <span class="req">*</span></label>
                <input type="text" id="siglas" name="siglas" required maxlength="20" value="<?= e($valor('siglas', (string) config('app.short_name'))) ?>">
            </div>

            <div class="field">
                <label for="lema">Lema</label>
                <input type="text" id="lema" name="lema" maxlength="150" value="<?= e($valor('lema')) ?>" placeholder="Unidad, tradición y sport">
            </div>

            <div class="field">
                <label for="anios_mandato">Años de mandato</label>
                <input type="number" id="anios_mandato" name="anios_mandato" min="1" max="10" value="<?= e($valor('anios_mandato', '4')) ?>">
            </div>
        </div>

        <div class="field">
            <label for="descripcion_club">Descripción breve</label>
            <textarea id="descripcion_club" name="descripcion_club" rows="2" maxlength="500"><?= e($valor('descripcion_club')) ?></textarea>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Contacto</legend>

        <div class="form-grid">
            <div class="field">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono" maxlength="30" value="<?= e($valor('telefono')) ?>">
            </div>

            <div class="field">
                <label for="whatsapp">WhatsApp</label>
                <input type="text" id="whatsapp" name="whatsapp" maxlength="30" value="<?= e($valor('whatsapp')) ?>">
            </div>

            <div class="field">
                <label for="email_institucional">Correo electrónico</label>
                <input type="email" id="email_institucional" name="email_institucional" maxlength="160" value="<?= e($valor('email_institucional')) ?>">
            </div>

            <div class="field">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion" maxlength="200" value="<?= e($valor('direccion')) ?>">
            </div>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Redes sociales</legend>

        <p class="field__hint">
            Cada red con enlace aparece en el pie de página con su logo y su
            color de marca. Si una red todavía no existe, déjalo vacío: no se
            mostrará hasta que la crees.
        </p>

        <div class="form-grid">
            <?php foreach (\App\Core\Redes::disponibles() as $claveRed => $datosRed): ?>
                <?php if ($claveRed === 'whatsapp') { continue; } ?>
                <div class="field">
                    <label for="<?= e($claveRed) ?>">
                        <span class="redes__marca redes__icono <?= e($datosRed['clase']) ?>">
                            <?= \App\Core\Redes::render($claveRed, 14) ?>
                        </span>
                        <?= e($datosRed['etiqueta']) ?>
                    </label>
                    <input
                        type="url"
                        id="<?= e($claveRed) ?>"
                        name="<?= e($claveRed) ?>"
                        maxlength="255"
                        value="<?= e($valor($claveRed)) ?>"
                        placeholder="https://<?= e($claveRed) ?>.com/…"
                    >
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Portada del portal</legend>

        <div class="form-grid">
            <div class="field">
                <label for="portada_titulo">Titular de la portada</label>
                <input type="text" id="portada_titulo" name="portada_titulo" maxlength="180" value="<?= e($valor('portada_titulo')) ?>" placeholder="La vida del club en imágenes">
            </div>

            <div class="field">
                <label for="fotos_ejemplo">Mostrar contenido de ejemplo</label>
                <select id="fotos_ejemplo" name="fotos_ejemplo">
                    <option value="1" <?= $booleano('fotos_ejemplo', true) ? 'selected' : '' ?>>Sí, mientras no haya contenido real</option>
                    <option value="0" <?= $booleano('fotos_ejemplo', true) ? '' : 'selected' ?>>No, mostrar solo contenido real</option>
                </select>
                <p class="field__hint">Las fotos de ejemplo llevan la insignia «Ejemplo».</p>
            </div>
        </div>

        <div class="field">
            <label for="portada_resumen">Bajada de la portada</label>
            <textarea id="portada_resumen" name="portada_resumen" rows="2" maxlength="300"><?= e($valor('portada_resumen')) ?></textarea>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Alquileres y avisos</legend>

        <div class="form-grid">
            <div class="field">
                <label for="porcentaje_adelanto">Adelanto por defecto (%)</label>
                <input type="number" id="porcentaje_adelanto" name="porcentaje_adelanto" min="0" max="100" value="<?= e($valor('porcentaje_adelanto', '50')) ?>">
            </div>

            <div class="field">
                <label for="dias_anticipacion">Días mínimos de anticipación</label>
                <input type="number" id="dias_anticipacion" name="dias_anticipacion" min="0" max="365" value="<?= e($valor('dias_anticipacion', '0')) ?>">
                <p class="field__hint">0 permite reservar para hoy.</p>
            </div>

            <div class="field">
                <label for="mantenimiento">Portal en mantenimiento</label>
                <select id="mantenimiento" name="mantenimiento">
                    <option value="0" <?= $booleano('mantenimiento') ? '' : 'selected' ?>>No, el portal funciona</option>
                    <option value="1" <?= $booleano('mantenimiento') ? 'selected' : '' ?>>Sí, avisar que está en mantenimiento</option>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Notificaciones por correo</legend>

        <div class="form-grid">
            <div class="field">
                <label for="correo_notificaciones">Correo del club (recibe el aviso de cada reserva)</label>
                <input type="email" id="correo_notificaciones" name="correo_notificaciones" maxlength="160" value="<?= e($valor('correo_notificaciones')) ?>" placeholder="reservas@club.org">
                <p class="field__hint">
                    Es el aviso interno para el club: código, ambiente, fecha, solicitante
                    e importes. Si lo dejas vacío se usará el «correo institucional».
                </p>
            </div>

            <div class="field">
                <label for="correo_remitente">Correo remitente de las confirmaciones</label>
                <input type="email" id="correo_remitente" name="correo_remitente" maxlength="160" value="<?= e($valor('correo_remitente')) ?>" placeholder="club@club.org">
                <p class="field__hint">
                    Desde esta dirección sale la <strong>confirmación que recibe la
                    persona que reservó</strong> (con el correo que dejó en el
                    formulario). Si lo dejas vacío se usa el remitente por defecto
                    del hosting.
                </p>
            </div>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary">Guardar configuración</button>
        <button type="submit" name="accion" value="probar" class="btn btn--ghost">
            Guardar y enviar un correo de prueba
        </button>
    </div>
</form>

<h2 class="section-title">Marca e imágenes</h2>

<div class="cards cards--3">
    <?php foreach ($marcas as $tipo => $etiqueta): ?>
        <?php $actual = $marcaActual($tipo); ?>
        <article class="card">
            <h3 style="margin-top:0;"><?= e($etiqueta) ?></h3>

            <?php if ($actual !== ''): ?>
                <img
                    src="<?= e($actual) ?>"
                    alt="<?= e($etiqueta) ?>"
                    style="max-width:100%;max-height:120px;border-radius:var(--radius-md);background:#eef2f0;"
                >
                <p class="card__hint">Imagen vigente (WebP).</p>
            <?php else: ?>
                <p class="muted">Se usa la imagen que trae la aplicación.</p>
            <?php endif; ?>

            <form class="form form--panel" method="post" action="<?= e(url('/configuracion/marca/' . $tipo)) ?>" enctype="multipart/form-data" style="margin-top:.6rem;">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="archivo-<?= e((string) $tipo) ?>">Subir imagen</label>
                    <input type="file" id="archivo-<?= e((string) $tipo) ?>" name="archivo" accept="image/jpeg,image/png,image/webp,image/gif" required>
                    <p class="field__hint">Se convierte a WebP y se reduce a 2000 px de ancho.</p>
                </div>
                <div class="field field--actions">
                    <button type="submit" class="btn btn--ghost btn--sm">Subir</button>
                </div>
            </form>

            <?php if ($actual !== ''): ?>
                <form method="post" action="<?= e(url('/configuracion/marca/' . $tipo . '/restaurar')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--sm">Restaurar la original</button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<div class="alert alert--warning" role="note" style="margin-top:1rem;">
    <?= Icons::render('alerta') ?>
    <p>
        Las imágenes que suba el club se guardan fuera de la carpeta pública y se
        sirven con control de caché. Para que la portada se vea al día, publique
        después de cambiarlas.
    </p>
</div>
