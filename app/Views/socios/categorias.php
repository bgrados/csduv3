<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Categorías configurables del padrón de socios.
 *
 * @var array<int, array<string, mixed>> $categorias
 */

$puedeGestionar = can('socios.editar');

$valor = static function (string $campo, mixed $porDefecto = ''): string {
    $previo = old($campo, null);

    return $previo === null ? (string) $porDefecto : (is_scalar($previo) ? (string) $previo : '');
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow"><a href="<?= e(url('/socios')) ?>">Socios</a> · configuración</p>
        <h1>Categorías de socio</h1>
        <p class="page-head__lead">
            Las categorías las define el club según su estatuto. El sistema no precarga ninguna.
            Una categoría inactiva se conserva para no perder el historial de los socios asignados.
        </p>
    </div>
</div>

<?php if ($puedeGestionar): ?>
    <section class="card">
        <h2>Nueva categoría</h2>

        <form class="form" method="post" action="<?= e(url('/socios/categorias')) ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="field">
                    <label for="nombre">Nombre <span class="req">*</span></label>
                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        maxlength="80"
                        required
                        value="<?= e($valor('nombre')) ?>"
                        placeholder="Por ejemplo: socio activo, vitalicio, honorario"
                    >
                </div>

                <div class="field">
                    <label for="orden">Orden</label>
                    <input
                        type="number"
                        id="orden"
                        name="orden"
                        min="1"
                        max="9999"
                        value="<?= e($valor('orden', '1')) ?>"
                    >
                    <p class="field__hint">Define el orden en que se muestran las categorías.</p>
                </div>
            </div>

            <div class="field">
                <label for="descripcion">Descripción</label>
                <input
                    type="text"
                    id="descripcion"
                    name="descripcion"
                    maxlength="200"
                    value="<?= e($valor('descripcion')) ?>"
                    placeholder="Descripción breve (opcional)"
                >
            </div>

            <div class="form__actions">
                <button type="submit" class="btn btn--primary">Crear categoría</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if ($categorias === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('socios', 'icon', 26) ?></span>
        <h2>Todavía no hay categorías</h2>
        <p>
            <?= $puedeGestionar
                ? 'Cree la primera categoría para poder clasificar a los socios del padrón.'
                : 'Un usuario con permiso para editar socios debe crear las categorías.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Orden</th>
                    <th scope="col">Nombre</th>
                    <th scope="col">Descripción</th>
                    <th scope="col">Socios</th>
                    <th scope="col">Estado</th>
                    <?php if ($puedeGestionar): ?>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($categorias as $categoria): ?>
                    <?php $activa = (int) $categoria['activo'] === 1; ?>
                    <tr>
                        <td><?= e((string) $categoria['orden']) ?></td>
                        <td><strong><?= e((string) $categoria['nombre']) ?></strong></td>
                        <td><?= e((string) ($categoria['descripcion'] ?? '')) !== '' ? e((string) $categoria['descripcion']) : '—' ?></td>
                        <td><?= e((string) ($categoria['total_socios'] ?? 0)) ?></td>
                        <td>
                            <span class="badge <?= $activa ? 'badge--ok' : 'badge--danger' ?>">
                                <?= $activa ? 'Activa' : 'Inactiva' ?>
                            </span>
                        </td>
                        <?php if ($puedeGestionar): ?>
                            <td class="table__actions">
                                <form method="post" action="<?= e(url('/socios/categorias/' . (string) $categoria['id'] . '/estado')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="<?= $activa ? 'desactivar' : 'activar' ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm">
                                        <?= $activa ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="muted">
        Desactivar una categoría no cambia a los socios que ya la tienen asignada.
    </p>
<?php endif; ?>
