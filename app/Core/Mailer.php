<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Envío de correos del portal.
 *
 * Usa la función `mail()` del servidor (el hosting comparte el remitente del
 * dominio). Si el envío falla, el sistema lo registra en el log pero **nunca**
 * interrumpe la operación que lo_originó: una reserva confirmada se guarda
 * aunque el correo no salga.
 */
final class Mailer
{
    /**
     * Envía un correo y devuelve si el servidor lo aceptó.
     */
    public static function enviar(string $destinatario, string $asunto, string $cuerpo): bool
    {
        $destinatario = trim($destinatario);

        if ($destinatario === '' || filter_var($destinatario, FILTER_VALIDATE_EMAIL) === false) {
            Logger::warning('No se envio un correo: destinatario invalido', ['destinatario' => $destinatario]);

            return false;
        }

        if (!function_exists('mail')) {
            Logger::warning('La funcion mail() no esta disponible en el servidor');

            return false;
        }

        $nombre = (string) Config::get('app.short_name', 'CSDUV3');
        $remitente = self::remitente();
        $cabeceras = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: ' . $nombre,
        ];

        if ($remitente !== '') {
            $cabeceras[] = 'From: ' . $nombre . ' <' . $remitente . '>';
            $cabeceras[] = 'Reply-To: ' . $remitente;
        }

        $enviado = @mail($destinatario, $asunto, $cuerpo, implode("\r\n", $cabeceras));

        if (!$enviado) {
            Logger::error('El servidor no acepto el correo', [
                'destinatario' => $destinatario,
                'asunto' => $asunto,
            ]);
        }

        return (bool) $enviado;
    }

    /**
     * Dirección desde la que salen los correos del club.
     */
    public static function remitente(): string
    {
        $configurado = trim((string) \App\Models\Configuracion::cached('correo_remitente', ''));

        if ($configurado !== '' && filter_var($configurado, FILTER_VALIDATE_EMAIL) !== false) {
            return $configurado;
        }

        $institucional = trim((string) \App\Models\Configuracion::cached('email_institucional', ''));

        if ($institucional !== '' && filter_var($institucional, FILTER_VALIDATE_EMAIL) !== false) {
            return $institucional;
        }

        return '';
    }

    /**
     * Dirección del club que recibe los avisos de las reservas.
     */
    public static function destinoNotificaciones(): string
    {
        $configurado = trim((string) \App\Models\Configuracion::cached('correo_notificaciones', ''));

        if ($configurado !== '' && filter_var($configurado, FILTER_VALIDATE_EMAIL) !== false) {
            return $configurado;
        }

        $institucional = trim((string) \App\Models\Configuracion::cached('email_institucional', ''));

        if ($institucional !== '' && filter_var($institucional, FILTER_VALIDATE_EMAIL) !== false) {
            return $institucional;
        }

        return '';
    }
}
