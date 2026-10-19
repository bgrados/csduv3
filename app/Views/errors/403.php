<?php

declare(strict_types=1);

/**
 * @var int    $status
 * @var string $message
 */

$codigo = 403;
$tituloError = 'Acceso no autorizado';
$mensajeError = $message !== ''
    ? $message
    : 'Su cuenta no tiene permisos para ver esta sección.';

require __DIR__ . '/_marco.php';
