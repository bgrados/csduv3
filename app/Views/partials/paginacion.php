<?php

declare(strict_types=1);

/**
 * Paginación reutilizable.
 *
 * @var int $pagina
 * @var int $paginas
 * @var int $total
 * @var string|null $etiqueta
 */

$pagina = max(1, (int) ($pagina ?? 1));
$paginas = max(1, (int) ($paginas ?? 1));
$total = max(0, (int) ($total ?? 0));
$etiqueta = $etiqueta ?? 'registros';
?>
<?php if ($paginas > 1): ?>
    <nav class="pagination" aria-label="Paginación">
        <?php if ($pagina > 1): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e(query_url(['pagina' => $pagina - 1])) ?>" rel="prev">Anterior</a>
        <?php else: ?>
            <span class="btn btn--ghost btn--sm is-disabled" aria-disabled="true">Anterior</span>
        <?php endif; ?>

        <span class="pagination__info">
            Página <?= e((string) $pagina) ?> de <?= e((string) $paginas) ?>
            · <?= e((string) $total) ?> <?= e($etiqueta) ?>
        </span>

        <?php if ($pagina < $paginas): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e(query_url(['pagina' => $pagina + 1])) ?>" rel="next">Siguiente</a>
        <?php else: ?>
            <span class="btn btn--ghost btn--sm is-disabled" aria-disabled="true">Siguiente</span>
        <?php endif; ?>
    </nav>
<?php endif; ?>
