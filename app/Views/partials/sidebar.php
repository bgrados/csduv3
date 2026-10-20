<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Menú lateral del panel.
 *
 * Los módulos de fases posteriores se muestran deshabilitados y con su fase
 * indicada: no se enlazan pantallas que todavía no existen.
 */

$enlaces = [];

$enlaces[] = [
    'etiqueta' => 'Panel',
    'url'      => url('/panel'),
    'icono'    => 'panel',
    'activo'   => is_current('/panel', true) || is_current('/perfil'),
    'visible'  => can('panel.ver'),
];

$enlaces[] = [
    'etiqueta' => 'Solicitudes',
    'url'      => url('/solicitudes'),
    'icono'    => 'alquileres',
    'activo'   => is_current('/solicitudes'),
    'visible'  => can('alquileres.ver') || can('alquileres.gestionar'),
];
?>
<aside id="menu-lateral" class="sidebar" aria-label="Menú administrativo">
    <div class="sidebar__brand">
        <span class="sidebar__logo">
            <img src="<?= e(asset((string) config('app.logo'))) ?>" alt="" width="42" height="42">
        </span>
        <span class="sidebar__identity">
            <strong><?= e((string) config('app.short_name')) ?></strong>
            <small><?= e((string) config('app.name')) ?></small>
        </span>
    </div>

    <nav class="sidebar__nav" aria-label="Secciones">
        <p class="sidebar__section">General</p>
        <ul class="sidebar__list">
            <?php foreach ($enlaces as $enlace): ?>
                <?php if (!$enlace['visible']) { continue; } ?>
                <li>
                    <a class="sidebar__link<?= $enlace['activo'] ? ' is-active' : '' ?>" href="<?= e($enlace['url']) ?>"<?= $enlace['activo'] ? ' aria-current="page"' : '' ?>>
                        <?= Icons::render($enlace['icono'], 'icon', 20) ?>
                        <span><?= e($enlace['etiqueta']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <p class="sidebar__section">Institucional</p>
        <ul class="sidebar__list">
            <?php if (can('socios.ver')): ?>
                <li>
                    <a class="sidebar__link<?= is_current('/socios') ? ' is-active' : '' ?>" href="<?= e(url('/socios')) ?>"<?= is_current('/socios') ? ' aria-current="page"' : '' ?>>
                        <?= Icons::render('socios', 'icon', 20) ?><span>Socios</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (can('directiva.ver')): ?>
                <li><span class="sidebar__link is-disabled" aria-disabled="true">
                    <?= Icons::render('directiva', 'icon', 20) ?><span>Directiva</span><span class="badge badge--soft">Fase 3</span>
                </span></li>
            <?php endif; ?>
            <?php if (can('elecciones.ver')): ?>
                <li><span class="sidebar__link is-disabled" aria-disabled="true">
                    <?= Icons::render('elecciones', 'icon', 20) ?><span>Elecciones</span><span class="badge badge--soft">Fase 3</span>
                </span></li>
            <?php endif; ?>
        </ul>

        <p class="sidebar__section">Operación</p>
        <ul class="sidebar__list">
            <?php if (can('alquileres.ver') || can('alquileres.tarifas')): ?>
                <li>
                    <a class="sidebar__link<?= is_current('/ambientes') ? ' is-active' : '' ?>" href="<?= e(url('/ambientes')) ?>"<?= is_current('/ambientes') ? ' aria-current="page"' : '' ?>>
                        <?= Icons::render('alquileres', 'icon', 20) ?><span>Ambientes y tarifas</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (can('caja.ver') || can('caja.registrar')): ?>
                <li>
                    <a class="sidebar__link<?= is_current('/caja') ? ' is-active' : '' ?>" href="<?= e(url('/caja')) ?>"<?= is_current('/caja') ? ' aria-current="page"' : '' ?>>
                        <?= Icons::render('caja', 'icon', 20) ?><span>Caja</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (can('deportes.ver')): ?>
                <li>
                    <a class="sidebar__link<?= is_current('/deportes') ? ' is-active' : '' ?>" href="<?= e(url('/deportes')) ?>"<?= is_current('/deportes') ? ' aria-current="page"' : '' ?>>
                        <?= Icons::render('deportes', 'icon', 20) ?><span>Deportes y torneos</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (can('calendario.ver') || can('documentos.ver')): ?>
                <li><span class="sidebar__link is-disabled" aria-disabled="true">
                    <?= Icons::render('documentos', 'icon', 20) ?><span>Calendario y documentos</span><span class="badge badge--soft">Fase 7</span>
                </span></li>
            <?php endif; ?>
        </ul>

        <p class="sidebar__section">Administración</p>
        <ul class="sidebar__list">
            <?php if (can('usuarios.ver')): ?>
                <li>
                    <a class="sidebar__link<?= is_current('/usuarios') ? ' is-active' : '' ?>" href="<?= e(url('/usuarios')) ?>"<?= is_current('/usuarios') ? ' aria-current="page"' : '' ?>>
                        <?= Icons::render('administracion', 'icon', 20) ?><span>Usuarios</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (can('roles.gestionar')): ?>
                <li>
                    <a class="sidebar__link<?= is_current('/roles') ? ' is-active' : '' ?>" href="<?= e(url('/roles')) ?>"<?= is_current('/roles') ? ' aria-current="page"' : '' ?>>
                        <?= Icons::render('administracion', 'icon', 20) ?><span>Roles y permisos</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (can('auditoria.ver')): ?>
                <li>
                    <a class="sidebar__link<?= is_current('/auditoria') ? ' is-active' : '' ?>" href="<?= e(url('/auditoria')) ?>"<?= is_current('/auditoria') ? ' aria-current="page"' : '' ?>>
                        <?= Icons::render('auditoria', 'icon', 20) ?><span>Auditoría</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if ((string) config('app.env') === 'development'): ?>
                <li>
                    <a class="sidebar__link<?= is_current('/diagnostico', true) ? ' is-active' : '' ?>" href="<?= e(url('/diagnostico')) ?>"<?= is_current('/diagnostico', true) ? ' aria-current="page"' : '' ?>>
                        <?= Icons::render('diagnostico', 'icon', 20) ?><span>Diagnóstico</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <p class="sidebar__foot">
        <a href="<?= e(url('/')) ?>">Ver portal público</a>
    </p>
</aside>
