<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Noticias y fotos del club (panel).
 *
 * @var array<int, array<string, mixed>> $fotos
 * @var array<string, string> $categorias
 * @var string $categoria
 * @var array<string, mixed>|null $portada
 */

$puedeGestionar = can('documentos.gestionar');

$tamano = static function (?int $bytes): string {
    if ($bytes === null || $bytes <= 0) {
        return '—';
    }

    return number_format($bytes / 1024, 0, '.', ',') . ' KB';
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Institucional</p>
        <h1>Noticias y fotos</h1>
        <p class="page-head__lead">
            Cada noticia lleva una foto, un titular y una entradilla. Una sola
            noticia puede ocupar la portada del portal; las demás quedan en la
            galería pública.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/galeria')) ?>">Ver la galería pública</a>
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="<?= e(url('/noticias/nuevo')) ?>">
                <?= Icons::render('documentos', 'icon', 18) ?> Nueva noticia
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($portada !== null): ?>
    <div class="card card--highlight">
        <p class="card__label">Noticia en portada</p>
        <div class="galeria__portada">
            <img src="<?= e(url('/galeria/imagen/' . (string) $portada['id'])) ?>" alt="<?= e((string) $portada['titulo']) ?>">
            <div>
                <h3 style="margin:.2rem 0;"><?= e((string) $portada['titulo']) ?></h3>
                <p class="muted"><?= e((string) ($portada['resumen'] ?? '')) ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<form class="form form--filters" method="get" action="<?= e(url('/noticias')) ?>">
    <div class="field">
        <label for="categoria">Categoría</label>
        <select id="categoria" name="categoria">
            <option value="">Todas</option>
            <?php foreach ($categorias as $clave => $etiqueta): ?>
                <option value="<?= e((string) $clave) ?>" <?= $categoria === (string) $clave ? 'selected' : '' ?>>
                    <?= e((string) $etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Filtrar</button>
        <?php if ($categoria !== ''): ?>
            <a class="btn btn--ghost" href="<?= e(url('/noticias')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($fotos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('documentos', 'icon', 26) ?></span>
        <h2>Todavía no hay noticias</h2>
        <p>
            Publique la primera noticia con su foto: las reuniones, los campeonatos,
            la junta directiva o la actividad de los socios.
        </p>
        <?php if ($puedeGestionar): ?>
            <p><a class="btn btn--primary" href="<?= e(url('/noticias/nuevo')) ?>">Publicar la primera noticia</a></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="galeria">
        <?php foreach ($fotos as $foto): ?>
            <article class="galeria__item">
                <a class="galeria__imagen" href="<?= e(url('/noticias/' . (string) $foto['id'] . '/editar')) ?>">
                    <?php if (!empty($foto['archivo'])): ?>
                        <img src="<?= e(url('/galeria/imagen/' . (string) $foto['id'])) ?>" alt="<?= e((string) $foto['titulo']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="galeria__sin-imagen"><?= Icons::render('documentos', 'icon', 26) ?></span>
                    <?php endif; ?>
                </a>
                <h3 class="galeria__titulo"><?= e((string) $foto['titulo']) ?></h3>
                <p class="galeria__pie">
                    <?= e($categorias[(string) $foto['categoria']] ?? (string) $foto['categoria']) ?>
                    <?php if (!empty($foto['fecha_foto'])): ?>
                        · <?= e(fecha_legible((string) $foto['fecha_foto'])) ?>
                    <?php endif; ?>
                    · <?= e($tamano((int) $foto['tamano_bytes'])) ?>
                </p>
                <?php if (!empty($foto['resumen'])): ?>
                    <p class="galeria__descripcion"><?= e((string) $foto['resumen']) ?></p>
                <?php endif; ?>

                <div class="galeria__acciones">
                    <span class="badge <?= (int) $foto['visible'] === 1 ? 'badge--ok' : 'badge--soft' ?>">
                        <?= (int) $foto['visible'] === 1 ? 'Publicada' : 'Borrador' ?>
                    </span>
                    <?php if ((int) ($foto['destacado'] ?? 0) === 1): ?>
                        <span class="badge badge--info">En portada</span>
                    <?php endif; ?>

                    <?php if ($puedeGestionar): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/noticias/' . (string) $foto['id'] . '/editar')) ?>">Editar</a>
                        <form method="post" action="<?= e(url('/noticias/' . (string) $foto['id'] . '/portada')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost btn--sm">
                                <?= (int) ($foto['destacado'] ?? 0) === 1 ? 'Quitar de portada' : 'Poner en portada' ?>
                            </button>
                        </form>
                        <form method="post" action="<?= e(url('/noticias/' . (string) $foto['id'] . '/estado')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="<?= (int) $foto['visible'] === 1 ? 'ocultar' : 'publicar' ?>">
                            <button type="submit" class="btn btn--ghost btn--sm">
                                <?= (int) $foto['visible'] === 1 ? 'Despublicar' : 'Publicar' ?>
                            </button>
                        </form>
                        <form method="post" action="<?= e(url('/noticias/' . (string) $foto['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar esta noticia y su foto?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
