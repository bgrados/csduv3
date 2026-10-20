<?php

declare(strict_types=1);

/**
 * Plantilla del portal público.
 *
 * No incluye el menú administrativo ni muestra información de socios. Es la
 * interfaz que se instala como PWA en el teléfono del visitante.
 *
 * @var string $content
 * @var string|null $titulo
 */

$tituloPagina = ($titulo ?? 'Inicio') . ' · ' . club('siglas', (string) config('app.short_name'));
$nombreClub = club('nombre_club', (string) config('app.name'));
$siglasClub = club('siglas', (string) config('app.short_name'));
$logoClub = marca_url('logo', 'assets/img/logo.jpg');
$faviconClub = marca_url('favicon', 'assets/img/favicon.svg');
$enMantenimiento = club('mantenimiento', '0') === '1';
$resumenClub = \App\Models\Resumen::datos();
$entorno = (string) config('app.env');
$usuarioActual = auth();
$rutaActual = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$rutaActual = is_string($rutaActual) && $rutaActual !== '' ? $rutaActual : '/';

$prefijosNav = ['/alquiler/disponibilidad', '/alquiler/solicitud', '/alquiler', '/torneos'];
$rutaActiva = $rutaActual === '/' ? '/' : null;

foreach ($prefijosNav as $prefijo) {
    if ($rutaActual === $prefijo || str_starts_with($rutaActual, $prefijo . '/')) {
        $rutaActiva = $prefijo;
        break;
    }
}

$esActiva = static function (string $prefijo) use ($rutaActiva): bool {
    $prefijo = '/' . trim($prefijo, '/');

    return $prefijo === $rutaActiva;
};

$activa = static function (string $prefijo) use ($esActiva): string {
    return $esActiva($prefijo) ? ' is-active' : '';
};

$aria = static function (string $prefijo) use ($esActiva): string {
    return $esActiva($prefijo) ? ' aria-current="page"' : '';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#063B22">
    <meta name="description" content="Portal público del <?= e($nombreClub) ?>: consulta de ambientes, tarifas, disponibilidad, solicitudes de alquiler y torneos.">
    <title><?= e($tituloPagina) ?></title>
    <link rel="icon" href="<?= e($faviconClub) ?>" type="image/svg+xml">
    <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('/icons/icon-192.png')) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= e($siglasClub) ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="public-body">
<a class="skip-link" href="#contenido">Saltar al contenido principal</a>

<?php if ($entorno === 'development'): ?>
    <p class="env-banner" role="status">
        Entorno de <strong>desarrollo</strong> · las solicitudes registradas aquí no son reservas reales.
    </p>
<?php endif; ?>

<?php if ($enMantenimiento): ?>
    <p class="env-banner env-banner--alerta" role="status">
        El portal está temporalmente en mantenimiento. Vuelva a intentarlo en unos minutos.
    </p>
<?php endif; ?>

<header class="site-header">
    <div class="site-header__inner">
        <a class="site-brand" href="<?= e(url('/')) ?>">
            <img src="<?= e($logoClub) ?>" alt="" width="44" height="44">
            <span class="site-brand__text">
                <strong><?= e($siglasClub) ?></strong>
                <small><?= e($nombreClub) ?></small>
            </span>
        </a>

        <nav class="site-nav" aria-label="Navegación del portal">
            <a class="site-nav__link<?= $activa('/') ?>" href="<?= e(url('/')) ?>"<?= $aria('/') ?>>Inicio</a>
            <a class="site-nav__link<?= $activa('/alquiler') ?>" href="<?= e(url('/alquiler')) ?>"<?= $aria('/alquiler') ?>>Ambientes</a>
            <a class="site-nav__link<?= $activa('/alquiler/disponibilidad') ?>" href="<?= e(url('/alquiler/disponibilidad')) ?>"<?= $aria('/alquiler/disponibilidad') ?>>Disponibilidad</a>
            <a class="site-nav__link<?= $activa('/alquiler/solicitud') ?>" href="<?= e(url('/alquiler/solicitud')) ?>"<?= $aria('/alquiler/solicitud') ?>>Solicitar</a>
            <a class="site-nav__link<?= $activa('/torneos') ?>" href="<?= e(url('/torneos')) ?>"<?= $aria('/torneos') ?>>Torneos</a>
            <a class="site-nav__link<?= $activa('/galeria') ?>" href="<?= e(url('/galeria')) ?>"<?= $aria('/galeria') ?>>Galería</a>
            <a class="site-nav__link<?= $activa('/negocios') ?>" href="<?= e(url('/negocios')) ?>"<?= $aria('/negocios') ?>>Negocios</a>
            <a class="site-nav__link site-nav__link--externo" href="https://uv3-platform.vercel.app/" target="_blank" rel="noopener">Portal UV3</a>

            <?php if ($usuarioActual === null): ?>
                <a class="btn btn--ghost btn--sm site-nav__cta" href="<?= e(url('/ingresar')) ?>">Ingresar</a>
            <?php else: ?>
                <a class="btn btn--primary btn--sm site-nav__cta" href="<?= e(url('/panel')) ?>">Panel</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="contenido" class="site-main" tabindex="-1">
    <?php require __DIR__ . '/../partials/flash.php'; ?>
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="site-footer__inner">
        <div>
            <strong><?= e($nombreClub) ?></strong>
            <p class="muted">Sistema de gestión institucional · <?= e($siglasClub) ?></p>
            <?php
            $telefonoClub = club('telefono', '');
            $correoClub = club('email_institucional', '');
            $direccionClub = club('direccion', '');
            $whatsappClub = club('whatsapp', '');
            ?>
            <?php if ($telefonoClub !== '' || $correoClub !== '' || $direccionClub !== ''): ?>
                <p class="site-footer__contacto">
                    <?php if ($direccionClub !== ''): ?><?= e($direccionClub) ?><br><?php endif; ?>
                    <?php if ($telefonoClub !== ''): ?>Tel. <?= e($telefonoClub) ?><?php endif; ?>
                    <?php if ($telefonoClub !== '' && $correoClub !== ''): ?> · <?php endif; ?>
                    <?php if ($correoClub !== ''): ?><?= e($correoClub) ?><?php endif; ?>
                    <?php if ($whatsappClub !== ''): ?><br>WhatsApp: <?= e($whatsappClub) ?><?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="site-footer__links">
            <a href="<?= e(url('/alquiler')) ?>">Ambientes y tarifas</a>
            <a href="<?= e(url('/alquiler/disponibilidad')) ?>">Disponibilidad</a>
            <a href="<?= e(url('/alquiler/reservar')) ?>">Reservar ahora</a>
            <a href="<?= e(url('/alquiler/solicitud')) ?>">Solicitar alquiler</a>
            <a href="<?= e(url('/torneos')) ?>">Torneos</a>
            <a href="<?= e(url('/galeria')) ?>">Galería del club</a>
            <a href="<?= e(url('/negocios')) ?>">Negocios de la unidad</a>
            <a href="https://uv3-platform.vercel.app/" target="_blank" rel="noopener">Portal de la Unidad Vecinal</a>
            <?php $facebookClub = club('facebook', ''); $instagramClub = club('instagram', ''); ?>
            <?php if ($facebookClub !== ''): ?>
                <a href="<?= e($facebookClub) ?>" target="_blank" rel="noopener">Facebook</a>
            <?php endif; ?>
            <?php if ($instagramClub !== ''): ?>
                <a href="<?= e($instagramClub) ?>" target="_blank" rel="noopener">Instagram</a>
            <?php endif; ?>
        </div>

        <div class="site-footer__club">
            <p class="site-footer__titulo">En el club</p>
            <ul class="site-footer__cifras">
                <li><strong><?= e((string) $resumenClub['ambientes']) ?></strong> ambientes para alquiler</li>
                <li><strong><?= e((string) $resumenClub['noticias']) ?></strong> noticias publicadas</li>
                <li><strong><?= e((string) $resumenClub['negocios']) ?></strong> negocios de la unidad</li>
            </ul>

            <?php if ($resumenClub['evento'] !== null): ?>
                <p class="site-footer__proximo">
                    <span class="site-footer__titulo">Próximo evento</span>
                    <strong><?= e((string) $resumenClub['evento']['titulo']) ?></strong>
                    <span class="muted">
                        <?= e(fecha_legible(substr((string) $resumenClub['evento']['fecha_inicio'], 0, 10))) ?>
                        <?php if ((int) $resumenClub['evento']['todo_el_dia'] === 0): ?>
                            · <?= e(substr((string) $resumenClub['evento']['fecha_inicio'], 11, 5)) ?>
                        <?php endif; ?>
                        <?php if (!empty($resumenClub['evento']['lugar'])): ?>
                            · <?= e((string) $resumenClub['evento']['lugar']) ?>
                        <?php endif; ?>
                    </span>
                </p>
            <?php endif; ?>
        </div>
    </div>
</footer>

<div class="install-banner" data-install-banner hidden>
    <div>
        <strong>Instale el portal en su teléfono</strong>
        <p>Acceda más rápido a ambientes, solicitudes y torneos, incluso sin conexión a la página informativa.</p>
    </div>
    <div class="install-banner__actions">
        <button type="button" class="btn btn--primary btn--sm" data-install-app>Instalar</button>
        <button type="button" class="btn btn--ghost btn--sm" data-install-dismiss>Cerrar</button>
    </div>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
