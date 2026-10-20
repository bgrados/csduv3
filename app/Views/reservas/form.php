<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Alta y edición de una reserva.
 *
 * @var array<string, mixed>|null $reserva
 * @var string $modo
 * @var array<int, array<string, mixed>> $instalaciones
 * @var array<int, array<string, mixed>> $tiposEvento
 * @var array<int, array<int, array<string, mixed>>> $tarifas
 * @var array<string, string> $estados
 * @var float $porcentajeAdelanto
 * @var int|null $solicitudId
 */

$esEdicion = $modo === 'editar' && $reserva !== null;

$valor = static function (string $campo, mixed $porDefecto = '') use ($reserva): string {
    $previo = old($campo, null);

    if ($previo !== null) {
        return is_scalar($previo) ? (string) $previo : '';
    }

    if ($reserva !== null && array_key_exists($campo, $reserva)) {
        return (string) ($reserva[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$accion = $esEdicion
    ? url('/reservas/' . (string) $reserva['id'])
    : url('/reservas');

// Mapa de tarifas por instalación para sugirir el precio en el navegador.
$mapaTarifas = [];

foreach ($tarifas as $instalacionId => $lista) {
    $mapaTarifas[(int) $instalacionId] = array_map(
        static fn (array $tarifa): array => [
            'id' => (int) $tarifa['id'],
            'nombre' => (string) $tarifa['nombre'],
            'monto' => (float) $tarifa['monto'],
            'tipo_evento_id' => (int) ($tarifa['tipo_evento_id'] ?? 0),
        ],
        $lista
    );
}
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/reservas')) ?>">Reservas</a> · <?= $esEdicion ? 'editar' : 'nueva' ?>
        </p>
        <h1><?= $esEdicion ? 'Editar reserva' : 'Nueva reserva' ?></h1>
        <p class="page-head__lead">
            El precio y el adelanto los fija el club. El sistema comprueba que el
            horario no esté ocupado antes de guardar.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/reservas')) ?>">Volver</a>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>">
    <?= csrf_field() ?>

    <?php if (($solicitudId ?? null) !== null): ?>
        <input type="hidden" name="solicitud_id" value="<?= e((string) $solicitudId) ?>">
    <?php endif; ?>

    <fieldset class="form__group">
        <legend>Ambiente y horario</legend>

        <div class="form-grid">
            <div class="field">
                <label for="instalacion_id">Sede <span class="req">*</span></label>
                <select id="instalacion_id" name="instalacion_id" required>
                    <option value="">Seleccione una sede</option>
                    <?php foreach ($instalaciones as $instalacion): ?>
                        <option
                            value="<?= e((string) $instalacion['id']) ?>"
                            <?= $valor('instalacion_id') === (string) $instalacion['id'] ? 'selected' : '' ?>
                        ><?= e((string) $instalacion['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="tipo_evento_id">Tipo de uso</label>
                <select id="tipo_evento_id" name="tipo_evento_id">
                    <option value="">Sin especificar</option>
                    <?php foreach ($tiposEvento as $tipo): ?>
                        <option
                            value="<?= e((string) $tipo['id']) ?>"
                            <?= $valor('tipo_evento_id') === (string) $tipo['id'] ? 'selected' : '' ?>
                        ><?= e((string) $tipo['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="tarifa_id">Tarifa vigente</label>
                <select id="tarifa_id" name="tarifa_id">
                    <option value="">Sin tarifa (precio libre)</option>
                    <?php foreach ($tarifas as $instalacionId => $lista): ?>
                        <?php foreach ($lista as $tarifa): ?>
                            <option
                                value="<?= e((string) $tarifa['id']) ?>"
                                data-instalacion="<?= e((string) $instalacionId) ?>"
                                data-monto="<?= e((string) $tarifa['monto']) ?>"
                                <?= $valor('tarifa_id') === (string) $tarifa['id'] ? 'selected' : '' ?>
                            ><?= e((string) $tarifa['nombre']) ?> — <?= e(dinero((string) $tarifa['monto'])) ?></option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
                <p class="field__hint">Al elegir la sede se sugiere el precio de su tarifa.</p>
            </div>

            <div class="field">
                <label for="fecha_evento">Fecha <span class="req">*</span></label>
                <input type="date" id="fecha_evento" name="fecha_evento" required value="<?= e($valor('fecha_evento', date('Y-m-d'))) ?>">
            </div>

            <div class="field">
                <label for="hora_inicio">Hora de inicio <span class="req">*</span></label>
                <input type="time" id="hora_inicio" name="hora_inicio" required value="<?= e(substr($valor('hora_inicio'), 0, 5)) ?>">
            </div>

            <div class="field">
                <label for="hora_fin">Hora de fin <span class="req">*</span></label>
                <input type="time" id="hora_fin" name="hora_fin" required value="<?= e(substr($valor('hora_fin'), 0, 5)) ?>">
            </div>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Importes</legend>

        <div class="form-grid">
            <div class="field">
                <label for="precio_acordado">Precio acordado (S/) <span class="req">*</span></label>
                <input type="number" id="precio_acordado" name="precio_acordado" required min="0" max="9999999999.99" step="0.01" value="<?= e($valor('precio_acordado', '0.00')) ?>">
            </div>

            <div class="field">
                <label for="monto_adelanto">Adelanto (S/) <span class="req">*</span></label>
                <input type="number" id="monto_adelanto" name="monto_adelanto" required min="0" max="9999999999.99" step="0.01" value="<?= e($valor('monto_adelanto', '0.00')) ?>">
                <p class="field__hint">Sugerencia: <?= e((string) (int) $porcentajeAdelanto) ?> % del precio acordado.</p>
            </div>

            <div class="field">
                <label for="estado">Estado <span class="req">*</span></label>
                <select id="estado" name="estado" required>
                    <?php foreach ($estados as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $valor('estado', 'pendiente') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Solicitante</legend>

        <div class="form-grid">
            <div class="field">
                <label for="solicitante_nombre">Nombre o razón social <span class="req">*</span></label>
                <input type="text" id="solicitante_nombre" name="solicitante_nombre" required maxlength="180" value="<?= e($valor('solicitante_nombre')) ?>">
            </div>

            <div class="field">
                <label for="solicitante_telefono">Teléfono <span class="req">*</span></label>
                <input type="text" id="solicitante_telefono" name="solicitante_telefono" required maxlength="30" value="<?= e($valor('solicitante_telefono')) ?>">
            </div>

            <div class="field">
                <label for="solicitante_documento">Documento</label>
                <input type="text" id="solicitante_documento" name="solicitante_documento" maxlength="30" value="<?= e($valor('solicitante_documento')) ?>">
            </div>

            <div class="field">
                <label for="solicitante_email">Correo electrónico</label>
                <input type="email" id="solicitante_email" name="solicitante_email" maxlength="160" value="<?= e($valor('solicitante_email')) ?>">
            </div>

            <div class="field">
                <label for="socio_codigo">Código de socio</label>
                <input type="text" id="socio_codigo" name="socio_codigo" maxlength="30" value="<?= e($valor('socio_codigo')) ?>" placeholder="Si el solicitante es socio">
            </div>
        </div>

        <div class="field">
            <label for="condiciones">Condiciones del alquiler</label>
            <textarea id="condiciones" name="condiciones" rows="3" maxlength="2000"><?= e($valor('condiciones')) ?></textarea>
        </div>

        <div class="field">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="3" maxlength="2000"><?= e($valor('observaciones')) ?></textarea>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear reserva' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/reservas')) ?>">Cancelar</a>
    </div>
</form>

<script>
    (function () {
        'use strict';

        var mapa = <?= json_encode($mapaTarifas, JSON_UNESCAPED_UNICODE) ?>;
        var porcentaje = <?= e((string) $porcentajeAdelanto) ?>;

        var instalacion = document.getElementById('instalacion_id');
        var tarifa = document.getElementById('tarifa_id');
        var precio = document.getElementById('precio_acordado');
        var adelanto = document.getElementById('monto_adelanto');

        if (!instalacion || !tarifa || !precio || !adelanto) {
            return;
        }

        function filtrarTarifas() {
            var seleccion = instalacion.value;
            var opciones = tarifa.options;

            for (var i = 0; i < opciones.length; i++) {
                var opcion = opciones[i];
                var pertenece = opcion.getAttribute('data-instalacion') === seleccion;
                opcion.hidden = !pertenece && opcion.value !== '';
                opcion.disabled = !pertenece && opcion.value !== '';
            }
        }

        function sugerirPrecio() {
            var opcion = tarifa.options[tarifa.selectedIndex];

            if (!opcion || !opcion.getAttribute('data-monto')) {
                return;
            }

            var monto = parseFloat(opcion.getAttribute('data-monto'));

            if (isNaN(monto)) {
                return;
            }

            precio.value = monto.toFixed(2);
            adelanto.value = (monto * porcentaje / 100).toFixed(2);
        }

        instalacion.addEventListener('change', filtrarTarifas);
        tarifa.addEventListener('change', sugerirPrecio);

        filtrarTarifas();
    })();
</script>
