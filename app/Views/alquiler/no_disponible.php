<?php

declare(strict_types=1);

/**
 * Portal en mantenimiento: la base de datos no está disponible.
 */
?>
<section class="card card--highlight">
    <h1>Portal en mantenimiento</h1>
    <p>
        El sistema de alquileres no está disponible en este momento porque el servicio
        de datos no responde. Le pedimos disculpas por la molestia.
    </p>
    <p class="muted">
        Vuelva a intentarlo en unos minutos o comuníquese directamente con el club.
    </p>
    <p class="card__actions">
        <a class="btn btn--primary" href="<?= e(url('/')) ?>">Volver al inicio</a>
    </p>
</section>
