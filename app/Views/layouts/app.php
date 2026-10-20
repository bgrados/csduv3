<?php

declare(strict_types=1);

/**
 * Plantilla base del área administrativa.
 *
 * @var string $content
 * @var string|null $titulo
 * @var array<string, mixed>|null $usuarioActual
 */

$tituloPagina = ($titulo ?? 'Inicio') . ' · ' . config('app.short_name');
$nombreClub = (string) config('app.name');
$entorno = (string) config('app.env');
$usuarioActual = auth();
$debeCambiarPassword = $usuarioActual !== null && (int) ($usuarioActual['debe_cambiar_password'] ?? 0) === 1;
$rutaActual = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$rutaActual = is_string($rutaActual) ? $rutaActual : '/';
$enPerfil = str_contains($rutaActual, '/perfil');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#063B22">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($tituloPagina) ?></title>
    <link rel="icon" href="<?= e(asset((string) config('app.favicon'))) ?>" type="image/svg+xml">
    <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('/icons/icon-192.png')) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= e((string) config('app.short_name')) ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido principal</a>

<?php if ($entorno === 'development'): ?>
    <p class="env-banner" role="status">
        Entorno de <strong>desarrollo</strong> · los cambios aquí no son datos reales del club.
    </p>
<?php endif; ?>

<div class="layout" data-sidebar>
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <div class="layout__main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>

        <main id="contenido" class="content" tabindex="-1">
            <?php require __DIR__ . '/../partials/flash.php'; ?>

            <?php if ($debeCambiarPassword && !$enPerfil): ?>
                <div class="alert alert--warning" role="status">
                    <p>
                        Su contraseña fue asignada por un administrador.
                        <a href="<?= e(url('/perfil')) ?>">Cámbiela ahora</a> para proteger su cuenta.
                    </p>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>

        <footer class="footer">
            <p>
                <?= e($nombreClub) ?> ·
                Sistema de gestión institucional ·
                <?= e((string) config('app.short_name')) ?>
            </p>
            <p class="footer__meta">
                PHP <?= e(PHP_VERSION) ?> · zona horaria <?= e(date_default_timezone_get()) ?> ·
                <?= e(date('d/m/Y H:i')) ?>
            </p>
        </footer>
    </div>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
