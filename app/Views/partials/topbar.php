<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Barra superior del panel administrativo.
 *
 * @var string|null $titulo
 */

$usuarioActual = auth();
$nombre = usuario_nombre($usuarioActual);
$rol = (string) ($usuarioActual['rol_nombre'] ?? 'Sin rol asignado');
?>
<header class="topbar">
    <button type="button" class="topbar__toggle" data-sidebar-toggle aria-controls="menu-lateral" aria-expanded="false">
        <?= Icons::render('menu', 'icon', 22) ?>
        <span class="visually-hidden">Abrir menú</span>
    </button>

    <div class="topbar__title">
        <h1 title="<?= e($titulo ?? 'Panel') ?>"><?= e($titulo ?? 'Panel') ?></h1>
    </div>

    <div class="topbar__user">
        <span class="topbar__user-icon"><?= Icons::render('usuario', 'icon', 20) ?></span>
        <span class="topbar__user-info">
            <strong><?= e($nombre !== '' ? $nombre : 'Usuario') ?></strong>
            <small><?= e($rol) ?></small>
        </span>

        <a class="btn btn--ghost btn--sm topbar__profile" href="<?= e(url('/perfil')) ?>" aria-label="Mi perfil" title="Mi perfil">
            <?= Icons::render('usuario', 'icon', 18) ?>
            <span class="topbar__profile-text">Mi perfil</span>
        </a>

        <form method="post" action="<?= e(url('/salir')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--ghost btn--sm" title="Cerrar sesión">
                <?= Icons::render('salir', 'icon', 18) ?>
                <span class="visually-hidden">Cerrar sesión</span>
            </button>
        </form>
    </div>
</header>
