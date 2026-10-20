<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Negocios de la unidad (panel).
 *
 * @var array<int, array<string, mixed>> $negocios
 * @var array<string, string> $categorias
 * @var string $categoria
 */

$puedeGestionar = can('documentos.gestionar');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Institucional</p>
        <h1>Negocios de la unidad</h1>
        <p class="page-head__lead">
            Los negocios y servicios que el club publica en su portal, con su
            enlace y sus datos de contacto. Todo se edita desde aquí.
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="<?= e(url('/negocios/nuevo')) ?>">
                <?= Icons::render('documentos', 'icon', 18) ?> Nuevo negocio
            </a>
        <?php endif; ?>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/negocios')) ?>">
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
            <a class="btn btn--ghost" href="<?= e(url('/negocios')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($negocios === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('documentos', 'icon', 26) ?></span>
        <h2>Todavía no hay negocios registrados</h2>
        <p>Registre los comercios y servicios de la unidad para publicarlos en el portal del club.</p>
        <?php if ($puedeGestionar): ?>
            <p><a class="btn btn--primary" href="<?= e(url('/negocios/nuevo')) ?>">Registrar el primero</a></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Negocio</th>
                    <th scope="col">Categoría</th>
                    <th scope="col">Contacto</th>
                    <th scope="col">Enlace</th>
                    <th scope="col">Estado</th>
                    <?php if ($puedeGestionar): ?>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($negocios as $negocio): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $negocio['nombre']) ?></strong>
                            <?php if (!empty($negocio['descripcion'])): ?>
                                <br><span class="muted"><?= e((string) $negocio['descripcion']) ?></span>
                            <?php endif; ?>
                            <?php if ((int) $negocio['destacado'] === 1): ?>
                                <br><span class="badge badge--info">Destacado</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($categorias[(string) $negocio['categoria']] ?? (string) $negocio['categoria']) ?></td>
                        <td>
                            <?php if (!empty($negocio['telefono'])): ?>
                                Tel. <?= e((string) $negocio['telefono']) ?><br>
                            <?php endif; ?>
                            <?php if (!empty($negocio['direccion'])): ?>
                                <span class="muted"><?= e((string) $negocio['direccion']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($negocio['web'])): ?>
                                <a href="<?= e((string) $negocio['web']) ?>" target="_blank" rel="noopener nofollow">Abrir enlace</a>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= (int) $negocio['visible'] === 1 ? 'badge--ok' : 'badge--soft' ?>">
                                <?= (int) $negocio['visible'] === 1 ? 'Publicado' : 'Oculto' ?>
                            </span>
                        </td>
                        <?php if ($puedeGestionar): ?>
                            <td class="table__actions">
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/negocios/' . (string) $negocio['id'] . '/editar')) ?>">Editar</a>
                                <form method="post" action="<?= e(url('/negocios/' . (string) $negocio['id'] . '/estado')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="<?= (int) $negocio['visible'] === 1 ? 'ocultar' : 'publicar' ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm">
                                        <?= (int) $negocio['visible'] === 1 ? 'Ocultar' : 'Publicar' ?>
                                    </button>
                                </form>
                                <form method="post" action="<?= e(url('/negocios/' . (string) $negocio['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar este negocio del portal?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
