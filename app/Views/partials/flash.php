<?php

declare(strict_types=1);

use App\Core\Icons;
use App\Core\Session;

/**
 * Alertas de éxito y de error (mensajes de un solo uso).
 */

$mensajes = Session::pullFlashes();

$exito = $mensajes['exito'] ?? null;
$error = $mensajes['error'] ?? null;
$aviso = $mensajes['aviso'] ?? null;
$errores = $mensajes['errores'] ?? null;
?>
<?php if (is_string($exito) && $exito !== ''): ?>
    <div class="alert alert--success" role="status">
        <?= Icons::render('exito') ?>
        <p><?= e($exito) ?></p>
    </div>
<?php endif; ?>

<?php if (is_string($error) && $error !== ''): ?>
    <div class="alert alert--danger" role="alert">
        <?= Icons::render('alerta') ?>
        <p><?= e($error) ?></p>
    </div>
<?php endif; ?>

<?php if (is_string($aviso) && $aviso !== ''): ?>
    <div class="alert alert--warning" role="status">
        <?= Icons::render('alerta') ?>
        <p><?= e($aviso) ?></p>
    </div>
<?php endif; ?>

<?php if (is_array($errores) && $errores !== []): ?>
    <div class="alert alert--danger" role="alert">
        <?= Icons::render('alerta') ?>
        <div>
            <p><strong>Revise los siguientes datos:</strong></p>
            <ul>
                <?php foreach ($errores as $mensaje): ?>
                    <li><?= e((string) $mensaje) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
