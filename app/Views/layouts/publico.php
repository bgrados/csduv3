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

$tituloPagina = ($titulo ?? 'Inicio') . ' · ' . config('app.short_name');
$nombreClub = (string) config('app.name');
$entorno = (string) config('app.env');
$usuarioActual = auth();
$rutaActual = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$rutaActual = is_string($rutaActual) && $rutaActual !== '' ? $rutaActual : '/';

$activa = static function (string $prefijo) use ($rutaActual): string {
    $prefijo = '/' . trim($prefijo, '/');

    if ($prefijo === '/') {
        return $rutaActual === '/' ? ' is-active' : '';
    }

    return str_starts_with($rutaActual, $prefijo) ? ' is-active' : '';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1f3b5c">
    <meta name="description" content="Portal público del <?= e($nombreClub) ?>: consulta de ambientes, tarifas, disponibilidad y solicitudes de alquiler.">
    <title><?= e($tituloPagina) ?></title>
    <link rel="icon" href="<?= e(asset((string) config('app.favicon'))) ?>" type="image/svg+xml">
    <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('/icons/icon-192.png')) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= e((string) config('app.short_name')) ?>">
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

<header class="site-header">
    <div class="site-header__inner">
        <a class="site-brand" href="<?= e(url('/')) ?>">
            <img src="<?= e(asset((string) config('app.logo'))) ?>" alt="" width="40" height="40">
            <span class="site-brand__text">
                <strong><?= e((string) config('app.short_name')) ?></strong>
                <small><?= e($nombreClub) ?></small>
            </span>
        </a>

        <nav class="site-nav" aria-label="Navegación del portal">
            <a class="site-nav__link<?= $activa('/') ?>" href="<?= e(url('/')) ?>">Inicio</a>
            <a class="site-nav__link<?= $activa('/alquiler') ?>" href="<?= e(url('/alquiler')) ?>">Ambientes</a>
            <a class="site-nav__link<?= $activa('/alquiler/disponibilidad') ?>" href="<?= e(url('/alquiler/disponibilidad')) ?>">Disponibilidad</a>
            <a class="site-nav__link<?= $activa('/alquiler/solicitud') ?>" href="<?= e(url('/alquiler/solicitud')) ?>">Solicitar</a>

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
            <p class="muted">Sistema de gestión institucional · <?= e((string) config('app.short_name')) ?></p>
        </div>
        <div class="site-footer__links">
            <a href="<?= e(url('/alquiler')) ?>">Ambientes y tarifas</a>
            <a href="<?= e(url('/alquiler/disponibilidad')) ?>">Disponibilidad</a>
            <a href="<?= e(url('/alquiler/solicitud')) ?>">Solicitar alquiler</a>
        </div>
    </div>
    <p class="site-footer__meta">
        Las solicitudes enviadas desde este portal quedan pendientes de revisión.
        Ninguna reserva se confirma automáticamente.
    </p>
</footer>

<div class="install-banner" data-install-banner hidden>
    <div>
        <strong>Instale el portal en su teléfono</strong>
        <p>Acceda más rápido a ambientes y solicitudes, incluso sin conexión a la página informativa.</p>
    </div>
    <div class="install-banner__actions">
        <button type="button" class="btn btn--primary btn--sm" data-install-app>Instalar</button>
        <button type="button" class="btn btn--ghost btn--sm" data-install-dismiss>Cerrar</button>
    </div>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
