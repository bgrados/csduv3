<?php

declare(strict_types=1);

use App\Core\Redes;

/**
 * Recuadros de las redes sociales del club.
 *
 * Cada red se muestra con su propio logo y color de marca dentro de un
 * recuadro. Si el club no configuró ninguna red, no se imprime nada.
 *
 * @var string|null $tituloRedes  Título opcional del bloque
 */

$redesClub = redes_club();
$tituloBloque = trim((string) ($tituloRedes ?? ''));

if ($redesClub === []) {
    return;
}
?>

<div class="redes">
    <?php if ($tituloBloque !== ''): ?>
        <p class="redes__titulo"><?= e($tituloBloque) ?></p>
    <?php endif; ?>

    <div class="redes__lista">
        <?php foreach ($redesClub as $clave => $enlace): ?>
            <a
                class="redes__icono <?= e(Redes::clase($clave)) ?>"
                href="<?= e($enlace) ?>"
                target="_blank"
                rel="noopener"
                title="<?= e(Redes::etiqueta($clave)) ?>"
                aria-label="<?= e(Redes::etiqueta($clave) . ' del club') ?>"
            >
                <?= Redes::render($clave, 18) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
