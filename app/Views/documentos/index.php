<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Documentos institucionales.
 *
 * @var array<int, array<string, mixed>> $documentos
 * @var array<string, string> $categorias
 * @var array<string, mixed> $filtros
 * @var int $total
 * @var int $pagina
 * @var int $paginas
 */

$puedeGestionar = can('documentos.gestionar');
$hayFiltros = $filtros['q'] !== '' || $filtros['categoria'] !== '' || $filtros['privacidad'] !== '';

$tamano = static function (?int $bytes): string {
    if ($bytes === null || $bytes <= 0) {
        return '—';
    }

    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 0, '.', ',') . ' KB';
    }

    return number_format($bytes / 1048576, 1, '.', ',') . ' MB';
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Institucional</p>
        <h1>Documentos</h1>
        <p class="page-head__lead">
            Actas, resoluciones, reglamentos, contratos e informes. Los archivos se
            guardan fuera de la carpeta pública y solo se descargan con permiso.
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="<?= e(url('/documentos/nuevo')) ?>">
                <?= Icons::render('documentos', 'icon', 18) ?> Nuevo documento
            </a>
        <?php endif; ?>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/documentos')) ?>">
    <div class="field">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= e((string) $filtros['q']) ?>" placeholder="Título, descripción o archivo">
    </div>

    <div class="field">
        <label for="categoria">Categoría</label>
        <select id="categoria" name="categoria">
            <option value="">Todas</option>
            <?php foreach ($categorias as $clave => $etiqueta): ?>
                <option value="<?= e((string) $clave) ?>" <?= $filtros['categoria'] === (string) $clave ? 'selected' : '' ?>>
                    <?= e((string) $etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="privacidad">Privacidad</label>
        <select id="privacidad" name="privacidad">
            <option value="">Todas</option>
            <option value="privado" <?= $filtros['privacidad'] === 'privado' ? 'selected' : '' ?>>Solo internos</option>
            <option value="publico" <?= $filtros['privacidad'] === 'publico' ? 'selected' : '' ?>>Compartibles</option>
        </select>
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Filtrar</button>
        <?php if ($hayFiltros): ?>
            <a class="btn btn--ghost" href="<?= e(url('/documentos')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($documentos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('documentos', 'icon', 26) ?></span>
        <?php if ($hayFiltros): ?>
            <h2>Sin coincidencias</h2>
            <p>No se encontraron documentos con los filtros seleccionados.</p>
        <?php else: ?>
            <h2>Todavía no hay documentos</h2>
            <p>
                Registre las actas, resoluciones y documentos del club.
                <?= $puedeGestionar ? 'Empiece por el primero.' : 'Cuando se carguen aparecerán aquí.' ?>
            </p>
            <?php if ($puedeGestionar): ?>
                <p><a class="btn btn--primary" href="<?= e(url('/documentos/nuevo')) ?>">Registrar el primer documento</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Documento</th>
                    <th scope="col">Categoría</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Archivo</th>
                    <th scope="col">Privacidad</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($documentos as $documento): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $documento['titulo']) ?></strong>
                            <?php if (!empty($documento['descripcion'])): ?>
                                <br><span class="muted"><?= e((string) $documento['descripcion']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($documento['socio_codigo'])): ?>
                                <br><span class="muted">Socio <code><?= e((string) $documento['socio_codigo']) ?></code></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($categorias[(string) $documento['categoria']] ?? (string) $documento['categoria']) ?></td>
                        <td><?= e(fecha_legible($documento['fecha_documento'] ?? null)) ?></td>
                        <td>
                            <?php if (!empty($documento['archivo'])): ?>
                                <?= e($tamano((int) $documento['tamano_bytes'])) ?>
                            <?php else: ?>
                                <span class="muted">Sin archivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= (int) $documento['es_privado'] === 1 ? 'badge--soft' : 'badge--info' ?>">
                                <?= (int) $documento['es_privado'] === 1 ? 'Solo internos' : 'Compartible' ?>
                            </span>
                        </td>
                        <td class="table__actions">
                            <?php if (!empty($documento['archivo'])): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/documentos/' . (string) $documento['id'] . '/descargar')) ?>">Descargar</a>
                            <?php endif; ?>
                            <?php if ($puedeGestionar): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/documentos/' . (string) $documento['id'] . '/editar')) ?>">Editar</a>
                                <form method="post" action="<?= e(url('/documentos/' . (string) $documento['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar este documento y su archivo?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php $etiqueta = 'documentos'; require __DIR__ . '/../partials/paginacion.php'; ?>
<?php endif; ?>
