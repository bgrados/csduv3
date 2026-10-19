<?php

declare(strict_types=1);

/**
 * Marco común de las páginas de error.
 *
 * @var int    $codigo
 * @var string $tituloError
 * @var string $mensajeError
 */

$nombreClub = (string) config('app.name');
$siglas = (string) config('app.short_name');
$logo = url((string) config('app.logo'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Error <?= e((string) $codigo) ?> · <?= e($siglas) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="error-page">
<main class="error-box">
    <img class="error-box__logo" src="<?= e($logo) ?>" alt="" width="56" height="56">
    <p class="error-box__code">Error <?= e((string) $codigo) ?></p>
    <h1><?= e($tituloError) ?></h1>
    <p><?= e($mensajeError) ?></p>
    <p class="error-box__actions">
        <a class="btn btn--primary" href="<?= e(url('/')) ?>">Volver al inicio</a>
        <?php if (auth() !== null): ?>
            <a class="btn btn--ghost" href="<?= e(url('/panel')) ?>">Ir al panel</a>
        <?php endif; ?>
    </p>
    <p class="error-box__foot"><?= e($nombreClub) ?></p>
</main>
</body>
</html>
