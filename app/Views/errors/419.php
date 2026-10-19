<?php

declare(strict_types=1);

/**
 * @var int    $status
 * @var string $message
 */

$codigo = 419;
$tituloError = 'Sesión del formulario expirada';
$mensajeError = $message !== ''
    ? $message
    : 'Por seguridad, el formulario dejó de ser válido. Vuelva a cargarlo e inténtelo otra vez.';

require __DIR__ . '/_marco.php';
