<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Galería pública: «El club en imágenes».
 *
 * @var array<int, array<string, mixed>> $fotos
 * @var array<string, string> $categorias
 * @var string $categoria
 */

$destacada = null;
$resto = [];

foreach ($fotos as $foto) {
    if ($destacada === null) {
        $destacada = $foto;
        continue;
    }

    $resto[] = $foto;
}
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Club Social Deportivo Unidad Vecinal N.º 3</p>
        <h1>El club en imágenes</h1>
        <p class="page-head__lead">
            Reuniones, asambleas, junta directiva, campeonatos y socios: la vida
            del club en fotos.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Volver a la portada</a>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/galeria')) ?>">
    <div class="field">
        <label for="categoria">Ver</label>
        <select id="categoria" name="categoria">
            <option value="">Todas las fotos</option>
            <?php foreach ($categorias as $clave => $etiqueta): ?>
                <option value="<?= e((string) $clave) ?>" <?= $categoria === (string) $clave ? 'selected' : '' ?>>
                    <?= e((string) $etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Ver</button>
        <?php if ($categoria !== ''): ?>
            <a class="btn btn--ghost" href="<?= e(url('/galeria')) ?>">Todas</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($fotos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('documentos', 'icon', 26) ?></span>
        <h2>Todavía no hay fotos publicadas</h2>
        <p>El club está preparando su galería. Vuelve pronto para ver las imágenes de reuniones y campeonato.</p>
    </div>
<?php else: ?>
    <?php if ($destacada !== null): ?>
        <article class="periodico__portada">
            <a class="periodico__portada-imagen" href="<?= e(url('/galeria/imagen/' . (string) $destacada['id'])) ?>">
                <img src="<?= e(url('/galeria/imagen/' . (string) $destacada['id'])) ?>" alt="<?= e((string) $destacada['titulo']) ?>">
            </a>
            <div class="periodico__portada-texto">
                <p class="periodico__kicker">
                    <?= e($categorias[(string) $destacada['categoria']] ?? (string) $destacada['categoria']) ?>
                    <?php if (!empty($destacada['fecha_foto'])): ?>
                        · <?= e(fecha_legible((string) $destacada['fecha_foto'])) ?>
                    <?php endif; ?>
                </p>
                <h2><?= e((string) $destacada['titulo']) ?></h2>
                <?php if (!empty($destacada['descripcion'])): ?>
                    <p><?= e((string) $destacada['descripcion']) ?></p>
                <?php endif; ?>
            </div>
        </article>
    <?php endif; ?>

    <?php if ($resto !== []): ?>
        <div class="galeria galeria--publica">
            <?php foreach ($resto as $foto): ?>
                <article class="galeria__item">
                    <a class="galeria__imagen" href="<?= e(url('/galeria/imagen/' . (string) $foto['id'])) ?>">
                        <img src="<?= e(url('/galeria/imagen/' . (string) $foto['id'])) ?>" alt="<?= e((string) $foto['titulo']) ?>" loading="lazy">
                    </a>
                    <h3 class="galeria__titulo"><?= e((string) $foto['titulo']) ?></h3>
                    <p class="galeria__pie">
                        <?= e($categorias[(string) $foto['categoria']] ?? (string) $foto['categoria']) ?>
                        <?php if (!empty($foto['fecha_foto'])): ?>
                            · <?= e(fecha_legible((string) $foto['fecha_foto'])) ?>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($foto['descripcion'])): ?>
                        <p class="galeria__descripcion"><?= e((string) $foto['descripcion']) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
