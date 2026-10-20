<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Directorio público de negocios de la unidad.
 *
 * @var array<int, array<string, mixed>> $negocios
 * @var array<string, string> $categorias
 * @var string $categoria
 */
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Club Social Deportivo Unidad Vecinal N.º 3</p>
        <h1>Negocios de la unidad</h1>
        <p class="page-head__lead">
            Comercios y servicios de nuestra unidad vecinal. El club los publica
            para que los vecinos los conozcan.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Volver a la portada</a>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/negocios')) ?>">
    <div class="field">
        <label for="categoria">Ver</label>
        <select id="categoria" name="categoria">
            <option value="">Todos</option>
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
            <a class="btn btn--ghost" href="<?= e(url('/negocios')) ?>">Todos</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($negocios === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('documentos', 'icon', 26) ?></span>
        <h2>Todavía no hay negocios publicados</h2>
        <p>El club está preparando el directorio de la unidad. Vuelve pronto.</p>
    </div>
<?php else: ?>
    <div class="cards cards--3">
        <?php foreach ($negocios as $negocio): ?>
            <article class="card negocio">
                <?php if (!empty($negocio['archivo'])): ?>
                    <img class="negocio__logo" src="<?= e(url('/negocios/imagen/' . (string) $negocio['id'])) ?>" alt="<?= e((string) $negocio['nombre']) ?>" loading="lazy">
                <?php endif; ?>

                <p class="card__label"><?= e($categorias[(string) $negocio['categoria']] ?? (string) $negocio['categoria']) ?></p>
                <h2 class="negocio__nombre"><?= e((string) $negocio['nombre']) ?></h2>

                <?php if (!empty($negocio['descripcion'])): ?>
                    <p class="negocio__descripcion"><?= e((string) $negocio['descripcion']) ?></p>
                <?php endif; ?>

                <ul class="facts">
                    <?php if (!empty($negocio['direccion'])): ?>
                        <li><span>Dirección</span><strong><?= e((string) $negocio['direccion']) ?></strong></li>
                    <?php endif; ?>
                    <?php if (!empty($negocio['horario'])): ?>
                        <li><span>Horario</span><strong><?= e((string) $negocio['horario']) ?></strong></li>
                    <?php endif; ?>
                    <?php if (!empty($negocio['telefono'])): ?>
                        <li>
                            <span>Teléfono</span>
                            <strong><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $negocio['telefono'])) ?>"><?= e((string) $negocio['telefono']) ?></a></strong>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($negocio['email'])): ?>
                        <li>
                            <span>Correo</span>
                            <strong><a href="mailto:<?= e((string) $negocio['email']) ?>"><?= e((string) $negocio['email']) ?></a></strong>
                        </li>
                    <?php endif; ?>
                </ul>

                <?php if (!empty($negocio['web'])): ?>
                    <div class="card__actions">
                        <a class="btn btn--primary btn--sm" href="<?= e((string) $negocio['web']) ?>" target="_blank" rel="noopener nofollow">
                            Visitar el negocio
                        </a>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
