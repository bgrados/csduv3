<?php

declare(strict_types=1);

/**
 * @var int    $status
 * @var string $message
 */

$codigo = 500;
$tituloError = 'Error interno';
$mensajeError = $message !== ''
    ? $message
    : 'Ocurrió un problema al procesar la solicitud. El incidente quedó registrado.';

require __DIR__ . '/_marco.php';
