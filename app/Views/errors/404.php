<?php

declare(strict_types=1);

/**
 * @var int    $status
 * @var string $message
 */

$codigo = 404;
$tituloError = 'Página no encontrada';
$mensajeError = $message !== ''
    ? $message
    : 'La dirección solicitada no existe o fue movida.';

require __DIR__ . '/_marco.php';
