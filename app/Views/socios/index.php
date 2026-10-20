<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Padrón de socios.
 *
 * @var array<int, array<string, mixed>> $socios
 * @var array<int, array<string, mixed>> $categorias
 * @var array<string, string> $estados
 * @var string $busqueda
 * @var string $estado
 * @var int $categoria
 * @var int $total
 * @var int $pagina
 * @var int $paginas
 */

$puedeCrear = can('socios.crear');
$puedeEditar = can('socios.editar');
$hayFiltros = $busqueda !== '' || $estado !== '' || $categoria > 0;

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'activo'    => 'badge--ok',
        'pendiente' => 'badge--warn',
        'baja'      => 'badge--danger',
        default     => 'badge--info',
    };
};

$documento = static function (array $socio): string {
    $tipo = (string) ($socio['tipo_documento'] ?? '');
    $numero = (string) ($socio['numero_documento'] ?? '');

    if ($numero === '') {
        return '';
    }

    return trim($tipo . ' ' . $numero);
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Institucional</p>
        <h1>Socios</h1>
        <p class="page-head__lead">
            Padrón de socios del club. Un socio nunca se elimina: cuando corresponde,
            se registra su baja y se conserva todo su historial.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/socios/categorias')) ?>">Categorías</a>
        <?php if ($puedeCrear): ?>
            <a class="btn btn--primary" href="<?= e(url('/socios/nuevo')) ?>">
                <?= Icons::render('socios', 'icon', 18) ?> Nuevo socio
            </a>
        <?php endif; ?>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/socios')) ?>">
    <div class="field">
        <label for="q">Buscar</label>
        <input
            type="search"
            id="q"
            name="q"
            value="<?= e($busqueda) ?>"
            placeholder="Código, documento, nombres o apellidos"
        >
    </div>

    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach ($estados as $clave => $etiqueta): ?>
                <option value="<?= e((string) $clave) ?>" <?= $estado === (string) $clave ? 'selected' : '' ?>>
                    <?= e((string) $etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="categoria">Categoría</label>
        <select id="categoria" name="categoria">
            <option value="">Todas</option>
            <?php foreach ($categorias as $item): ?>
                <option
                    value="<?= e((string) $item['id']) ?>"
                    <?= $categoria === (int) $item['id'] ? 'selected' : '' ?>
                ><?= e((string) $item['nombre']) ?><?= (int) $item['activo'] === 1 ? '' : ' (inactiva)' ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Filtrar</button>
        <?php if ($hayFiltros): ?>
            <a class="btn btn--ghost" href="<?= e(url('/socios')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($socios === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('socios', 'icon', 26) ?></span>
        <?php if ($hayFiltros): ?>
            <h2>Sin coincidencias</h2>
            <p>No se encontraron socios con los filtros seleccionados. Pruebe con otros criterios.</p>
        <?php else: ?>
            <h2>Todavía no hay socios registrados</h2>
            <p>
                El padrón está vacío. <?= $puedeCrear ? 'Registre al primer socio para comenzar.' : 'Cuando se registren socios aparecerán aquí.' ?>
            </p>
            <?php if ($puedeCrear): ?>
                <p><a class="btn btn--primary" href="<?= e(url('/socios/nuevo')) ?>">Registrar el primer socio</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Código</th>
                    <th scope="col">Socio</th>
                    <th scope="col">Categoría</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Ingreso</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($socios as $socio): ?>
                    <?php $estadoSocio = (string) $socio['estado']; ?>
                    <tr>
                        <td><code><?= e((string) $socio['codigo']) ?></code></td>
                        <td>
                            <strong><?= e(trim((string) $socio['apellidos'] . ', ' . (string) $socio['nombres'], ', ')) ?></strong>
                            <?php if ($documento($socio) !== ''): ?>
                                <br><span class="muted"><?= e($documento($socio)) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($socio['telefono'])): ?>
                                <br><span class="muted">Tel. <?= e((string) $socio['telefono']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e((string) ($socio['categoria_nombre'] ?? '—')) ?>
                        </td>
                        <td>
                            <span class="badge <?= e($claseEstado($estadoSocio)) ?>">
                                <?= e($estados[$estadoSocio] ?? $estadoSocio) ?>
                            </span>
                        </td>
                        <td><?= e(fecha_legible($socio['fecha_ingreso'] ?? null)) ?></td>
                        <td class="table__actions">
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/socios/' . (string) $socio['id'])) ?>">Ver ficha</a>
                            <?php if ($puedeEditar): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/socios/' . (string) $socio['id'] . '/editar')) ?>">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php $etiqueta = 'socios'; require __DIR__ . '/../partials/paginacion.php'; ?>
<?php endif; ?>
