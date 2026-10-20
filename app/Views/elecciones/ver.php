<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Detalle de un proceso electoral: listas, candidatos y resultados.
 *
 * @var array<string, mixed> $proceso
 * @var array<int, array<string, mixed>> $listas
 * @var array<int, array<string, mixed>> $candidatos
 * @var array<int, array<string, mixed>> $resultados
 * @var array<string, string> $estados
 * @var array<string, string> $estadosLista
 * @var array<int, array<string, mixed>> $cargos
 */

$puedeGestionar = can('elecciones.gestionar');
$procesoId = (int) $proceso['id'];
$estado = (string) $proceso['estado'];

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'en_proceso'  => 'badge--ok',
        'convocado'   => 'badge--info',
        'planificado' => 'badge--soft',
        'finalizado'  => 'badge--soft',
        'anulado'     => 'badge--danger',
        default       => 'badge--soft',
    };
};

$claseLista = static function (string $valor): string {
    return match ($valor) {
        'ganadora' => 'badge--ok',
        'retirada' => 'badge--warn',
        'tachada'  => 'badge--danger',
        'inscrita' => 'badge--info',
        default    => 'badge--soft',
    };
};

$totalVotos = 0;

foreach ($resultados as $resultado) {
    $totalVotos += (int) $resultado['votos'];
}
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/elecciones')) ?>">Elecciones</a> · proceso
        </p>
        <h1><?= e((string) $proceso['nombre']) ?></h1>
        <p class="page-head__lead">
            <?php if (!empty($proceso['descripcion'])): ?>
                <?= e((string) $proceso['descripcion']) ?>
            <?php else: ?>
                Proceso electoral del club.
            <?php endif; ?>
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--ghost" href="<?= e(url('/elecciones/' . $procesoId . '/editar')) ?>">Editar proceso</a>
        <?php endif; ?>
        <a class="btn btn--ghost" href="<?= e(url('/elecciones')) ?>">Volver</a>
    </div>
</div>

<div class="cards cards--4">
    <div class="card card--stat">
        <p class="card__label">Estado</p>
        <p class="card__value">
            <span class="badge <?= e($claseEstado($estado)) ?>"><?= e($estados[$estado] ?? $estado) ?></span>
        </p>
        <p class="card__hint"><?= e(fecha_legible($proceso['fecha_eleccion'] ?? null)) ?></p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Listas</p>
        <p class="card__value"><?= e((string) $proceso['total_listas']) ?></p>
        <p class="card__hint">Agrupaciones inscritas</p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Candidatos</p>
        <p class="card__value"><?= e((string) $proceso['total_candidatos']) ?></p>
        <p class="card__hint">Inscritos en el proceso</p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Votos registrados</p>
        <p class="card__value"><?= e((string) $totalVotos) ?></p>
        <p class="card__hint">Suma de las listas</p>
    </div>
</div>

<?php if ($puedeGestionar): ?>
    <div class="card">
        <form class="form form--panel" method="post" action="<?= e(url('/elecciones/' . $procesoId . '/estado')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="estado-proceso">Estado del proceso</label>
                <select id="estado-proceso" name="estado">
                    <?php foreach ($estados as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $estado === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field--actions">
                <button type="submit" class="btn btn--ghost">Actualizar estado</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if (!empty($proceso['reglamento'])): ?>
    <details class="details">
        <summary>Reglamento aplicable</summary>
        <p><?= nl2br(e((string) $proceso['reglamento'])) ?></p>
    </details>
<?php endif; ?>

<h2 class="section-title">Listas</h2>

<?php if ($listas === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('elecciones', 'icon', 26) ?></span>
        <h2>Todavía no hay listas inscritas</h2>
        <p>Inscriba las listas o agrupaciones que participan en el proceso.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Lista</th>
                    <th scope="col">Lema</th>
                    <th scope="col">Candidatos</th>
                    <th scope="col">Votos</th>
                    <th scope="col">Estado</th>
                    <?php if ($puedeGestionar): ?>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($listas as $lista): ?>
                    <?php $estadoLista = (string) $lista['estado']; ?>
                    <tr>
                        <td><strong><?= e((string) $lista['nombre']) ?></strong></td>
                        <td><?= e((string) ($lista['lema'] ?? '—')) ?></td>
                        <td><?= e((string) $lista['total_candidatos']) ?></td>
                        <td>
                            <?php if ($lista['votos'] === null): ?>
                                <span class="muted">Sin registrar</span>
                            <?php else: ?>
                                <strong><?= e((string) $lista['votos']) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= e($claseLista($estadoLista)) ?>">
                                <?= e($estadosLista[$estadoLista] ?? $estadoLista) ?>
                            </span>
                        </td>
                        <?php if ($puedeGestionar): ?>
                            <td class="table__actions">
                                <?php if ($estadoLista === 'inscrita'): ?>
                                    <form method="post" action="<?= e(url('/elecciones/' . $procesoId . '/listas/' . (string) $lista['id'] . '/estado')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="estado" value="retirada">
                                        <button type="submit" class="btn btn--ghost btn--sm" data-confirmar="¿Marcar esta lista como retirada?">Retirar</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('/elecciones/' . $procesoId . '/listas/' . (string) $lista['id'] . '/estado')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="estado" value="inscrita">
                                        <button type="submit" class="btn btn--ghost btn--sm">Reinscribir</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($puedeGestionar): ?>
    <h2 class="section-title">Inscribir lista</h2>

    <form class="form form--panel" method="post" action="<?= e(url('/elecciones/' . $procesoId . '/listas')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="lista-nombre">Nombre <span class="req">*</span></label>
            <input type="text" id="lista-nombre" name="nombre" required maxlength="120" value="<?= e((string) old('nombre')) ?>">
        </div>
        <div class="field">
            <label for="lista-lema">Lema</label>
            <input type="text" id="lista-lema" name="lema" maxlength="200" value="<?= e((string) old('lema')) ?>">
        </div>
        <div class="field">
            <label for="lista-orden">Orden en la cédula</label>
            <input type="number" id="lista-orden" name="orden" min="1" max="999" value="<?= e((string) old('orden', '1')) ?>">
        </div>
        <div class="field field--actions">
            <button type="submit" class="btn btn--primary">Inscribir lista</button>
        </div>
    </form>
<?php endif; ?>

<h2 class="section-title">Candidatos</h2>

<?php if ($candidatos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('elecciones', 'icon', 26) ?></span>
        <h2>Todavía no hay candidatos inscritos</h2>
        <p>Inscriba a los candidatos de cada lista con el cargo al que postulan.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Lista</th>
                    <th scope="col">Candidato</th>
                    <th scope="col">Cargo al que postula</th>
                    <th scope="col">Documento</th>
                    <th scope="col">Cabeza de lista</th>
                    <?php if ($puedeGestionar): ?>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($candidatos as $candidato): ?>
                    <tr>
                        <td><?= e((string) ($candidato['lista_nombre'] ?? 'Sin lista')) ?></td>
                        <td>
                            <strong><?= e(trim((string) $candidato['nombres'] . ' ' . (string) $candidato['apellidos'])) ?></strong>
                            <?php if (!empty($candidato['socio_codigo'])): ?>
                                <br><span class="muted"><code><?= e((string) $candidato['socio_codigo']) ?></code></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($candidato['cargo_nombre'] ?? '—')) ?></td>
                        <td><?= e((string) ($candidato['documento'] ?? '—')) ?></td>
                        <td><?= (int) $candidato['es_cabeza_lista'] === 1 ? 'Sí' : '—' ?></td>
                        <?php if ($puedeGestionar): ?>
                            <td class="table__actions">
                                <form method="post" action="<?= e(url('/elecciones/' . $procesoId . '/candidatos/' . (string) $candidato['id'] . '/eliminar')) ?>" data-confirmar="¿Retirar a este candidato de la lista?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn--ghost btn--sm">Retirar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($puedeGestionar): ?>
    <h2 class="section-title">Inscribir candidato</h2>

    <form class="form form--panel" method="post" action="<?= e(url('/elecciones/' . $procesoId . '/candidatos')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="candidato-lista">Lista</label>
            <select id="candidato-lista" name="lista_id">
                <option value="">Sin lista</option>
                <?php foreach ($listas as $lista): ?>
                    <option value="<?= e((string) $lista['id']) ?>" <?= (string) old('lista_id') === (string) $lista['id'] ? 'selected' : '' ?>>
                        <?= e((string) $lista['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="candidato-nombres">Nombres <span class="req">*</span></label>
            <input type="text" id="candidato-nombres" name="nombres" required maxlength="120" value="<?= e((string) old('nombres')) ?>">
        </div>
        <div class="field">
            <label for="candidato-apellidos">Apellidos <span class="req">*</span></label>
            <input type="text" id="candidato-apellidos" name="apellidos" required maxlength="120" value="<?= e((string) old('apellidos')) ?>">
        </div>
        <div class="field">
            <label for="candidato-socio">Código de socio</label>
            <input type="text" id="candidato-socio" name="socio_codigo" maxlength="30" value="<?= e((string) old('socio_codigo')) ?>">
        </div>
        <div class="field">
            <label for="candidato-documento">Documento</label>
            <input type="text" id="candidato-documento" name="documento" maxlength="30" value="<?= e((string) old('documento')) ?>">
        </div>
        <div class="field">
            <label for="candidato-cargo">Cargo al que postula</label>
            <select id="candidato-cargo" name="cargo_id">
                <option value="">Sin cargo</option>
                <?php foreach ($cargos as $cargo): ?>
                    <option value="<?= e((string) $cargo['id']) ?>" <?= (string) old('cargo_id') === (string) $cargo['id'] ? 'selected' : '' ?>>
                        <?= e((string) $cargo['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="candidato-orden">Orden en la lista</label>
            <input type="number" id="candidato-orden" name="orden" min="1" max="999" value="<?= e((string) old('orden', '1')) ?>">
        </div>
        <div class="field">
            <label for="candidato-cabeza">Cabeza de lista</label>
            <select id="candidato-cabeza" name="es_cabeza_lista">
                <option value="0">No</option>
                <option value="1" <?= (string) old('es_cabeza_lista', '0') === '1' ? 'selected' : '' ?>>Sí</option>
            </select>
        </div>
        <div class="field field--actions">
            <button type="submit" class="btn btn--primary">Inscribir candidato</button>
        </div>
    </form>
<?php endif; ?>

<h2 class="section-title">Resultados</h2>

<?php if ($listas === []): ?>
    <p class="muted">Inscriba primero las listas para registrar los resultados.</p>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Lista</th>
                    <th scope="col">Votos</th>
                    <th scope="col">Nulos</th>
                    <th scope="col">En blanco</th>
                    <th scope="col">Ganadora</th>
                    <?php if ($puedeGestionar): ?>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($listas as $lista): ?>
                    <?php
                    $resultado = null;

                    foreach ($resultados as $fila) {
                        if ((int) $fila['lista_id'] === (int) $lista['id']) {
                            $resultado = $fila;
                            break;
                        }
                    }
                    ?>
                    <tr>
                        <td><strong><?= e((string) $lista['nombre']) ?></strong></td>
                        <td><?= e((string) ($resultado['votos'] ?? 0)) ?></td>
                        <td><?= e((string) ($resultado['votos_nulos'] ?? 0)) ?></td>
                        <td><?= e((string) ($resultado['votos_blancos'] ?? 0)) ?></td>
                        <td>
                            <?php if ((int) ($resultado['es_ganadora'] ?? 0) === 1): ?>
                                <span class="badge badge--ok">Ganadora</span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($puedeGestionar): ?>
                            <td class="table__actions">
                                <a class="btn btn--ghost btn--sm" href="#resultado-<?= e((string) $lista['id']) ?>">Registrar</a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($puedeGestionar): ?>
        <h2 class="section-title">Registrar resultado por lista</h2>

        <form class="form form--panel" method="post" action="<?= e(url('/elecciones/' . $procesoId . '/resultados')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="resultado-lista">Lista <span class="req">*</span></label>
                <select id="resultado-lista" name="lista_id" required>
                    <?php foreach ($listas as $lista): ?>
                        <option value="<?= e((string) $lista['id']) ?>"><?= e((string) $lista['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="resultado-votos">Votos <span class="req">*</span></label>
                <input type="number" id="resultado-votos" name="votos" required min="0" max="1000000" value="<?= e((string) old('votos', '0')) ?>">
            </div>
            <div class="field">
                <label for="resultado-nulos">Votos nulos</label>
                <input type="number" id="resultado-nulos" name="votos_nulos" min="0" max="1000000" value="<?= e((string) old('votos_nulos', '0')) ?>">
            </div>
            <div class="field">
                <label for="resultado-blancos">Votos en blanco</label>
                <input type="number" id="resultado-blancos" name="votos_blancos" min="0" max="1000000" value="<?= e((string) old('votos_blancos', '0')) ?>">
            </div>
            <div class="field">
                <label for="resultado-ganadora">Lista ganadora</label>
                <select id="resultado-ganadora" name="es_ganadora">
                    <option value="0">No</option>
                    <option value="1" <?= (string) old('es_ganadora', '0') === '1' ? 'selected' : '' ?>>Sí</option>
                </select>
                <p class="field__hint">Al marcar una lista como ganadora, las demás dejan de serlo.</p>
            </div>
            <div class="field">
                <label for="resultado-observaciones">Observaciones</label>
                <textarea id="resultado-observaciones" name="observaciones" rows="3" maxlength="1000"><?= e((string) old('observaciones')) ?></textarea>
            </div>
            <div class="field field--actions">
                <button type="submit" class="btn btn--primary">Registrar resultado</button>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>
