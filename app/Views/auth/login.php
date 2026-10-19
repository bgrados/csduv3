<?php

declare(strict_types=1);

/**
 * Formulario de acceso del personal.
 */
?>
<div class="auth-wrap">
    <section class="card auth-card">
        <h1>Acceso del personal</h1>
        <p class="muted">
            Este panel está reservado al personal autorizado del club.
            Ingrese con la cuenta que le fue asignada.
        </p>

        <form class="form" method="post" action="<?= e(url('/ingresar')) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="usuario">Usuario o correo electrónico</label>
                <input
                    type="text"
                    id="usuario"
                    name="usuario"
                    value="<?= e((string) old('usuario', '')) ?>"
                    required
                    autocomplete="username"
                    autofocus
                >
            </div>

            <div class="field">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <div class="form__actions">
                <button type="submit" class="btn btn--primary">Ingresar</button>
                <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Volver al portal</a>
            </div>
        </form>

        <p class="muted auth-card__note">
            Si no tiene cuenta o perdió su contraseña, solicite apoyo al administrador del sistema del club.
        </p>
    </section>
</div>
