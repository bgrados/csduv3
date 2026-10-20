<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Core\Logger;
use App\Core\Mailer;

/**
 * Aviso por correo cuando una reserva queda confirmada.
 *
 * Se avisa al club (para que coordine la entrega del ambiente) y, si el
 * solicitante dejó su correo, también se le confirma al cliente.
 *
 * Si no hay correo configurado o el envío falla, la reserva sigue
 * confirmada: el aviso nunca bloquea la operación.
 */
final class ReservaConfirmada
{
    /**
     * @param array<string, mixed> $reserva
     */
    public static function enviar(array $reserva): void
    {
        $club = Mailer::destinoNotificaciones();

        if ($club === '') {
            Logger::info('Reserva confirmada sin correo de avisos configurado', [
                'reserva_id' => (int) ($reserva['id'] ?? 0),
                'codigo' => (string) ($reserva['codigo'] ?? ''),
            ]);

            return;
        }

        $asunto = 'Reserva confirmada · ' . (string) ($reserva['codigo'] ?? '');
        $cuerpo = self::cuerpo($reserva, true);

        $enviado = Mailer::enviar($club, $asunto, $cuerpo);

        Logger::info('Aviso de reserva confirmada enviado al club', [
            'reserva_id' => (int) ($reserva['id'] ?? 0),
            'destinatario' => $club,
            'enviado' => $enviado,
        ]);

        $cliente = trim((string) ($reserva['solicitante_email'] ?? ''));

        if ($cliente !== '' && filter_var($cliente, FILTER_VALIDATE_EMAIL) !== false) {
            Mailer::enviar(
                $cliente,
                'Su reserva en ' . (string) ($reserva['instalacion_nombre'] ?? 'el club') . ' está confirmada',
                self::cuerpo($reserva, false)
            );
        }
    }

    /**
     * @param array<string, mixed> $reserva
     */
    public static function cuerpo(array $reserva, bool $paraElClub): string
    {
        $filas = [
            'Código' => (string) ($reserva['codigo'] ?? ''),
            'Ambiente' => (string) ($reserva['instalacion_nombre'] ?? '—'),
            'Tipo de uso' => (string) ($reserva['tipo_evento_nombre'] ?? '—'),
            'Fecha' => (string) ($reserva['fecha_evento'] ?? '—'),
            'Horario' => substr((string) ($reserva['hora_inicio'] ?? ''), 0, 5) . ' – ' . substr((string) ($reserva['hora_fin'] ?? ''), 0, 5),
            'Solicitante' => (string) ($reserva['solicitante_nombre'] ?? '—'),
            'Teléfono' => (string) ($reserva['solicitante_telefono'] ?? '—'),
            'Correo' => (string) ($reserva['solicitante_email'] ?? '—'),
            'Precio acordado' => 'S/ ' . number_format((float) ($reserva['precio_acordado'] ?? 0), 2),
            'Adelanto pactado' => 'S/ ' . number_format((float) ($reserva['monto_adelanto'] ?? 0), 2),
            'Pagado' => 'S/ ' . number_format((float) ($reserva['monto_pagado'] ?? 0), 2),
        ];

        $tabla = '';

        foreach ($filas as $etiqueta => $valor) {
            if ((string) $valor === '' || (string) $valor === '—') {
                continue;
            }

            $tabla .= '<tr>'
                . '<td style="padding:6px 12px 6px 0;color:#56665a;font-size:14px;">' . htmlspecialchars((string) $etiqueta, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:6px 0;color:#18251b;font-size:14px;font-weight:600;">' . htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }

        $intro = $paraElClub
            ? 'Se ha confirmado una reserva en el portal. Se deben coordinar la entrega del ambiente y el cobro del saldo.'
            : 'Su reserva quedó confirmada. Le contactaremos para coordinar la entrega del ambiente.';

        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"></head>'
            . '<body style="margin:0;background:#f5f7f2;font-family:system-ui,Arial,sans-serif;color:#18251b;">'
            . '<div style="max-width:620px;margin:0 auto;padding:24px;">'
            . '<div style="background:#063b22;border-radius:16px 16px 0 0;padding:20px 24px;color:#fff;">'
            . '<p style="margin:0;font-size:13px;letter-spacing:.16em;text-transform:uppercase;color:#fefe08;">'
            . htmlspecialchars((string) config('app.short_name', 'CSDUV3'), ENT_QUOTES, 'UTF-8')
            . '</p>'
            . '<h1 style="margin:6px 0 0;font-size:22px;">Reserva confirmada</h1>'
            . '</div>'
            . '<div style="background:#fff;border-radius:0 0 16px 16px;padding:22px 24px;">'
            . '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;">' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<table style="border-collapse:collapse;width:100%;">' . $tabla . '</table>'
            . '<p style="margin:18px 0 0;font-size:13px;color:#6b7a6e;">'
            . 'Este mensaje se envía automáticamente al confirmarse una reserva en el portal del club.'
            . '</p>'
            . '</div></div></body></html>';
    }
}
