<?php

declare(strict_types=1);

use App\Core\Icons;
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Deportes</p>
        <h1>Torneos en mantenimiento</h1>
        <p class="page-head__lead">
            El portal de torneos no está disponible en este momento. Intente nuevamente en unos minutos.
        </p>
    </div>
</div>

<div class="empty-state">
    <span class="empty-state__icon"><?= Icons::render('alerta', 'icon', 26) ?></span>
    <h2>Servicio temporalmente no disponible</h2>
    <p>Estamos restableciendo la conexión con la base de datos. Las demás secciones del portal siguen disponibles.</p>
    <p><a class="btn btn--ghost" href="<?= e(url('/')) ?>">Volver al inicio</a></p>
</div>
