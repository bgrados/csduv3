<?php

declare(strict_types=1);

/**
 * Estado y actualización de la base de datos.
 *
 * @var array<int, string> $aplicadas
 * @var array<int, string> $pendientes
 * @var array<int, string> $tablasFaltantes
 * @var array<int, string> $columnasFaltantes
 */

?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Administración</p>
        <h1>Actualizar la base de datos</h1>
        <p class="page-head__lead">
            Cada vez que el club actualiza el sistema se agregan cambios a la
            base de datos. Si queda alguno pendiente, el portal avisa y se
            aplica desde aquí con un clic.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/panel')) ?>">Volver al panel</a>
    </div>
</div>

<?php if ($pendientes === [] && $tablasFaltantes === [] && $columnasFaltantes === []): ?>
    <div class="alert alert--success" role="status">
        <p>
            <strong>Todo en orden.</strong>
            La base de datos está actualizada y tiene todo lo que el sistema
            necesita. No hay nada que hacer.
        </p>
    </div>
<?php else: ?>
    <?php if ($pendientes !== []): ?>
        <div class="alert alert--warning" role="alert">
            <p>
                <strong>Hay <?= e((string) count($pendientes)) ?> actualización(es) pendiente(s).</strong>
                Mientras no se apliquen, algunas funciones pueden no guardar la
                información (por ejemplo, el video de las noticias).
            </p>
        </div>

        <div class="card">
            <h2>Aplicar las actualizaciones</h2>
            <ul class="facts">
                <?php foreach ($pendientes as $pendiente): ?>
                    <li><span>Actualización</span><strong><code><?= e($pendiente) ?></code></strong></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?= e(url('/migraciones/aplicar')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--primary">Actualizar la base de datos</button>
            </form>
            <p class="field__hint">
                Solo hace falta cuando el portal avisa de una actualización
                pendiente. La operación no borra datos.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($tablasFaltantes !== []): ?>
        <div class="alert alert--danger" role="alert">
            <p>
                <strong>Faltan tablas del sistema:</strong>
                <?= e(implode(', ', $tablasFaltantes)) ?>.
                <?php if ($pendientes === []): ?>
                    Póngase en contacto con el administrador del sistema.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($columnasFaltantes !== []): ?>
        <div class="alert alert--warning" role="status">
            <p>
                <strong>Faltan columnas recientes:</strong>
                <?= e(implode(', ', $columnasFaltantes)) ?>.
                Se añaden con las actualizaciones de arriba.
            </p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="card">
    <h2>Historial</h2>
    <p class="muted">Actualizaciones ya aplicadas a la base de datos:</p>
    <?php if ($aplicadas === []): ?>
        <p class="muted">Todavía no se registró ninguna.</p>
    <?php else: ?>
        <ul class="facts">
            <?php foreach ($aplicadas as $aplicada): ?>
                <li><span>Aplicada</span><strong><code><?= e($aplicada) ?></code></strong></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
