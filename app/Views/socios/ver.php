<?php

declare(strict_types=1);

/**
 * Ficha individual de un socio.
 *
 * @var array<string, mixed> $socio
 * @var array<string, mixed>|null $baja
 * @var array<string, string> $estados
 * @var array<string, string> $tiposDocumento
 * @var array<string, string> $sexos
 */

$estadoSocio = (string) $socio['estado'];
$puedeEditar = can('socios.editar');
$puedeBaja = can('socios.baja');
$estaDeBaja = $estadoSocio === 'baja';

$claseEstado = match ($estadoSocio) {
    'activo'    => 'badge--ok',
    'pendiente' => 'badge--warn',
    'baja'      => 'badge--danger',
    default     => 'badge--info',
};

$dato = static function (mixed $valor, string $vacio = '—'): string {
    $texto = trim((string) ($valor ?? ''));

    return $texto === '' ? $vacio : $texto;
};

$nombreCompleto = trim((string) $socio['nombres'] . ' ' . (string) $socio['apellidos']);
$tipoDocumento = $tiposDocumento[(string) $socio['tipo_documento']] ?? $dato($socio['tipo_documento']);
$sexo = $sexos[(string) $socio['sexo']] ?? $dato($socio['sexo'], '');

$responsableBaja = trim(
    (string) ($baja['registrado_por_nombres'] ?? '') . ' ' . (string) ($baja['registrado_por_apellidos'] ?? '')
);
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow"><a href="<?= e(url('/socios')) ?>">Socios</a> · ficha</p>
        <h1><?= e($nombreCompleto !== '' ? $nombreCompleto : (string) $socio['codigo']) ?></h1>
        <p class="page-head__lead">
            <code><?= e((string) $socio['codigo']) ?></code>
            <span class="badge <?= e($claseEstado) ?>"><?= e($estados[$estadoSocio] ?? $estadoSocio) ?></span>
            <?php if (!empty($socio['categoria_nombre'])): ?>
                · <?= e((string) $socio['categoria_nombre']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($puedeEditar): ?>
            <a class="btn btn--primary" href="<?= e(url('/socios/' . (string) $socio['id'] . '/editar')) ?>">Editar ficha</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <h2>Identificación</h2>
        <ul class="facts">
            <li><span>Código</span><strong><code><?= e((string) $socio['codigo']) ?></code></strong></li>
            <li><span>Tipo de documento</span><strong><?= e($tipoDocumento) ?></strong></li>
            <li><span>Número de documento</span><strong><?= e($dato($socio['numero_documento'])) ?></strong></li>
            <li><span>Fecha de nacimiento</span><strong><?= e(fecha_legible($socio['fecha_nacimiento'] ?? null)) ?></strong></li>
            <li><span>Sexo</span><strong><?= e($dato($sexo)) ?></strong></li>
            <li><span>Estado civil</span><strong><?= e($dato($socio['estado_civil'])) ?></strong></li>
        </ul>
    </section>

    <section class="card">
        <h2>Contacto</h2>
        <ul class="facts">
            <li><span>Teléfono</span><strong><?= e($dato($socio['telefono'])) ?></strong></li>
            <li><span>Teléfono alterno</span><strong><?= e($dato($socio['telefono_alterno'])) ?></strong></li>
            <li><span>Correo electrónico</span><strong><?= e($dato($socio['email'])) ?></strong></li>
            <li><span>Dirección</span><strong><?= e($dato($socio['direccion'])) ?></strong></li>
            <li><span>Distrito</span><strong><?= e($dato($socio['distrito'])) ?></strong></li>
        </ul>
    </section>

    <section class="card">
        <h2>Membresía</h2>
        <ul class="facts">
            <li><span>Categoría</span><strong><?= e($dato($socio['categoria_nombre'], 'Sin categoría')) ?></strong></li>
            <li><span>Fecha de ingreso</span><strong><?= e(fecha_legible($socio['fecha_ingreso'] ?? null)) ?></strong></li>
            <li><span>Estado</span><strong><?= e($estados[$estadoSocio] ?? $estadoSocio) ?></strong></li>
            <li><span>Registrado</span><strong><?= e(fecha_legible($socio['created_at'] ?? null, true)) ?></strong></li>
            <li><span>Última actualización</span><strong><?= e(fecha_legible($socio['updated_at'] ?? null, true)) ?></strong></li>
        </ul>
    </section>

    <section class="card">
        <h2>Observaciones</h2>
        <?php if (!empty($socio['observaciones'])): ?>
            <p><?= nl2br(e((string) $socio['observaciones'])) ?></p>
        <?php else: ?>
            <p class="muted">Sin observaciones registradas.</p>
        <?php endif; ?>
    </section>
</div>

<?php if ($baja !== null): ?>
    <section class="card">
        <h2><?= $estaDeBaja ? 'Baja registrada' : 'Última baja registrada' ?></h2>
        <ul class="facts">
            <li><span>Fecha de baja</span><strong><?= e(fecha_legible($baja['fecha_baja'] ?? null)) ?></strong></li>
            <li><span>Tipo</span><strong><?= ((int) ($baja['es_renuncia'] ?? 0) === 1) ? 'Renuncia' : 'Baja' ?></strong></li>
            <li><span>Motivo</span><strong><?= e($dato($baja['motivo'], 'Sin motivo registrado')) ?></strong></li>
            <li><span>Registrada por</span><strong><?= e($dato($responsableBaja)) ?></strong></li>
            <li><span>Registrada el</span><strong><?= e(fecha_legible($baja['created_at'] ?? null, true)) ?></strong></li>
        </ul>
        <p class="muted">
            <?= $estaDeBaja
                ? 'El registro del socio se conserva: la baja no elimina su historial.'
                : 'El socio fue reactivado posteriormente; esta baja se conserva como historial.' ?>
        </p>
    </section>
<?php endif; ?>

<?php if ($puedeBaja && !$estaDeBaja): ?>
    <section class="card">
        <h2>Registrar baja</h2>
        <p class="muted">
            El socio dejará de figurar como activo, pero su ficha y su historial se conservan.
            Esta acción queda registrada en la auditoría.
        </p>

        <form class="form" method="post" action="<?= e(url('/socios/' . (string) $socio['id'] . '/baja')) ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="field">
                    <label for="fecha_baja">Fecha de baja</label>
                    <input type="date" id="fecha_baja" name="fecha_baja" value="<?= e(date('Y-m-d')) ?>" required>
                </div>

                <div class="field">
                    <label for="es_renuncia">Tipo de baja</label>
                    <div class="check">
                        <input type="checkbox" id="es_renuncia" name="es_renuncia" value="1">
                        <label for="es_renuncia">Es una renuncia voluntaria</label>
                    </div>
                </div>
            </div>

            <div class="field">
                <label for="motivo">Motivo</label>
                <textarea id="motivo" name="motivo" rows="3" maxlength="300" placeholder="Motivo de la baja (opcional, máximo 300 caracteres)"></textarea>
            </div>

            <div class="form__actions">
                <button type="submit" class="btn btn--danger">Registrar baja</button>
            </div>
        </form>
    </section>
<?php elseif ($estaDeBaja && $puedeEditar): ?>
    <section class="card">
        <h2>Reactivar socio</h2>
        <p class="muted">
            Este socio figura de baja. Para reactivarlo, edite su ficha y cambie el estado a «Activo».
        </p>
        <p><a class="btn btn--ghost" href="<?= e(url('/socios/' . (string) $socio['id'] . '/editar')) ?>">Editar ficha</a></p>
    </section>
<?php endif; ?>
