<?php

declare(strict_types=1);

use App\Core\Redes;

/**
 * Bloque de redes del club.
 *
 * Muestra el contenido de la página de Facebook del club (sus publicaciones)
 * y, debajo, los iconos de las demás redes, que son enlaces porque no se
 * pueden incrustar sin credenciales.
 *
 * @var string|null $tituloRedes  Título opcional del bloque
 */

$redesClub = redes_club();
$tituloBloque = trim((string) ($tituloRedes ?? ''));
$facebook = $redesClub['facebook'] ?? '';

if ($redesClub === []) {
    return;
}
?>

<div class="redes">
    <?php if ($tituloBloque !== ''): ?>
        <p class="redes__titulo"><?= e($tituloBloque) ?></p>
    <?php endif; ?>

    <?php if ($facebook !== ''): ?>
        <?= \App\Core\Video::paginaFacebook($facebook, 240, 'Facebook del club') ?>
    <?php endif; ?>

    <?php $otras = array_diff_key($redesClub, ['facebook' => true]); ?>
    <?php if ($otras !== []): ?>
        <div class="redes__lista">
            <?php foreach ($otras as $clave => $enlace): ?>
                <a
                    class="redes__icono <?= e(Redes::clase($clave)) ?>"
                    href="<?= e($enlace) ?>"
                    target="_blank"
                    rel="noopener"
                    title="<?= e(Redes::etiqueta($clave)) ?>"
                    aria-label="<?= e(Redes::etiqueta($clave) . ' del club') ?>"
                >
                    <?= Redes::render($clave, 16) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
