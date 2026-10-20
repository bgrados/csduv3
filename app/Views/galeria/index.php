<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Galería institucional (panel).
 *
 * @var array<int, array<string, mixed>> $fotos
 * @var array<string, string> $categorias
 * @var string $categoria
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
        <h1>Fotos del club</h1>
        <p class="page-head__lead">
            Suba las fotos de las reuniones, los campeonatos, la junta directiva y
            fotos publicadas aparecen en la portada y en la galería pública.
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="<?= e(url('/fotos/nuevo')) ?>">
                <?= Icons::render('documentos', 'icon', 18) ?> Subir foto
            </a>
        <?php endif; ?>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/fotos')) ?>">
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
            <a class="btn btn--ghost" href="<?= e(url('/fotos')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($fotos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('documentos', 'icon', 26) ?></span>
        <h2>Todavía no hay fotos</h2>
        <p>
            Sube las fotos de las reuniones, los campeonatos, la junta directiva y
            los socios: forman la sección de la portada.
        </p>
        <?php if ($puedeGestionar): ?>
            <p><a class="btn btn--primary" href="<?= e(url('/fotos/nuevo')) ?>">Subir la primera foto</a></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="galeria">
        <?php foreach ($fotos as $foto): ?>
            <article class="galeria__item">
                <a class="galeria__imagen" href="<?= e(url('/fotos/' . (string) $foto['id'] . '/editar')) ?>">
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
                <?php if (!empty($foto['descripcion'])): ?>
                    <p class="galeria__descripcion"><?= e((string) $foto['descripcion']) ?></p>
                <?php endif; ?>

                <div class="galeria__acciones">
                    <span class="badge <?= (int) $foto['visible'] === 1 ? 'badge--ok' : 'badge--soft' ?>">
                        <?= (int) $foto['visible'] === 1 ? 'Publicada' : 'Oculta' ?>
                    </span>
                    <span class="badge <?= (int) ($foto['destacado'] ?? 0) === 1 ? 'badge--info' : 'badge--soft' ?>">
                        <?= (int) ($foto['destacado'] ?? 0) === 1 ? 'En portada' : 'Solo galería' ?>
                    </span>

                    <?php if ($puedeGestionar): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/fotos/' . (string) $foto['id'] . '/editar')) ?>">Editar</a>
                        <form method="post" action="<?= e(url('/fotos/' . (string) $foto['id'] . '/destacar')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost btn--sm">
                                <?= (int) ($foto['destacado'] ?? 0) === 1 ? 'Quitar de portada' : 'Poner en portada' ?>
                            </button>
                        </form>
                        <form method="post" action="<?= e(url('/fotos/' . (string) $foto['id'] . '/estado')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="<?= (int) $foto['visible'] === 1 ? 'ocultar' : 'publicar' ?>">
                            <button type="submit" class="btn btn--ghost btn--sm">
                                <?= (int) $foto['visible'] === 1 ? 'Ocultar' : 'Publicar' ?>
                            </button>
                        </form>
                        <form method="post" action="<?= e(url('/fotos/' . (string) $foto['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar esta foto?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
