<?php

declare(strict_types=1);

/**
 * Mi perfil: datos de la cuenta y cambio de contraseña propia.
 *
 * @var array<string, mixed> $usuario
 */
?>
<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Mi cuenta</p>
        <h1>Mi perfil</h1>
        <p class="page-head__lead">Datos de su cuenta y cambio de contraseña personal.</p>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <h2>Datos de la cuenta</h2>
        <ul class="facts">
            <li><span>Nombre</span><strong><?= e(usuario_nombre($usuario)) ?></strong></li>
            <li><span>Usuario</span><strong><?= e((string) ($usuario['usuario'] ?? '')) ?></strong></li>
            <li><span>Correo</span><strong><?= e((string) ($usuario['email'] ?? '—')) ?></strong></li>
            <li><span>Rol</span><strong><?= e((string) ($usuario['rol_nombre'] ?? 'Sin rol asignado')) ?></strong></li>
            <li><span>Último acceso</span><strong><?= e(fecha_legible($usuario['ultimo_acceso'] ?? null, true)) ?></strong></li>
        </ul>
        <p class="muted">
            Para modificar su nombre, usuario, correo o rol, solicítelo a un administrador del sistema.
        </p>
    </section>

    <section class="card">
        <h2>Cambiar contraseña</h2>
        <form class="form" method="post" action="<?= e(url('/perfil')) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="password_actual">Contraseña actual</label>
                <input type="password" id="password_actual" name="password_actual" required autocomplete="current-password">
            </div>

            <div class="field">
                <label for="password_nueva">Nueva contraseña</label>
                <input type="password" id="password_nueva" name="password_nueva" required autocomplete="new-password">
                <p class="field__hint">Mínimo 10 caracteres, con al menos una letra y un número.</p>
            </div>

            <div class="field">
                <label for="password_confirmacion">Repita la nueva contraseña</label>
                <input type="password" id="password_confirmacion" name="password_confirmacion" required autocomplete="new-password">
            </div>

            <div class="form__actions">
                <button type="submit" class="btn btn--primary">Actualizar contraseña</button>
            </div>
        </form>
    </section>
</div>
