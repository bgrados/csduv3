<?php

declare(strict_types=1);

/**
 * Registro de auditoría (solo consulta).
 *
 * @var array<int, array<string, mixed>> $registros
 * @var array<int, string> $acciones
 * @var string $busqueda
 * @var string $accion
 * @var int $total
 */
?>
<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Administración</p>
        <h1>Auditoría</h1>
        <p class="page-head__lead">
            Registro de las acciones importantes: accesos, cambios de usuarios, permisos,
            solicitudes y rechazos. Los registros no pueden editarse ni eliminarse desde el sistema.
        </p>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/auditoria')) ?>">
    <div class="field">
        <label for="accion">Acción</label>
        <select id="accion" name="accion">
            <option value="">Todas</option>
            <?php foreach ($acciones as $opcion): ?>
                <option value="<?= e($opcion) ?>" <?= $accion === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= e($busqueda) ?>" placeholder="Descripción o usuario">
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Filtrar</button>
        <?php if ($accion !== '' || $busqueda !== ''): ?>
            <a class="btn btn--ghost" href="<?= e(url('/auditoria')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($registros === []): ?>
    <div class="empty-state">
        <h2>Sin registros</h2>
        <p>No hay acciones registradas que coincidan con el filtro.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Usuario</th>
                    <th scope="col">Acción</th>
                    <th scope="col">Descripción</th>
                    <th scope="col">Origen</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($registros as $registro): ?>
                    <tr>
                        <td><?= e(fecha_legible($registro['created_at'] ?? null, true)) ?></td>
                        <td>
                            <?php if (!empty($registro['usuario'])): ?>
                                <?= e(trim(((string) $registro['nombres']) . ' ' . ((string) $registro['apellidos']))) ?>
                                <br><span class="muted"><code><?= e((string) $registro['usuario']) ?></code></span>
                            <?php else: ?>
                                <span class="muted">Portal público</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e((string) $registro['accion']) ?></code></td>
                        <td><?= e((string) ($registro['descripcion'] ?? '—')) ?></td>
                        <td><span class="muted"><?= e((string) ($registro['ip'] ?? '—')) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php $etiqueta = 'registros'; require __DIR__ . '/../partials/paginacion.php'; ?>
<?php endif; ?>
