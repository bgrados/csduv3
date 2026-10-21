<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Uploads;
use App\Core\Validator;
use App\Core\View;
use App\Models\Configuracion;
use RuntimeException;
use Throwable;

/**
 * Configuración institucional del club.
 *
 * Permite cambiar la identidad (nombre, siglas, lema), los datos de contacto,
 * las redes sociales, la marca (logo, favicon y portada) y los parámetros de
 * alquileres. Las imágenes se convierten a WebP para que pesen menos.
 *
 * El permiso `configuracion.gestionar` se comprueba en app/routes.php.
 */
final class ConfiguracionController
{
    /** Tipos de imagen de marca que se pueden subir. */
    private const MARCAS = [
        'logo'    => 'Logotipo',
        'favicon' => 'Icono del navegador (favicon)',
        'portada' => 'Foto de portada',
        'cabecera' => 'Fondo de la cabecera de la portada',
    ];

    /** Parámetros de texto que se guardan, con su grupo y su tipo. */
    private const CAMPOS = [
        'nombre_club'        => ['identidad', 'texto'],
        'siglas'             => ['identidad', 'texto'],
        'lema'               => ['identidad', 'texto'],
        'descripcion_club'   => ['identidad', 'texto'],
        'anios_mandato'      => ['identidad', 'numero'],
        'telefono'           => ['contacto', 'texto'],
        'whatsapp'           => ['contacto', 'texto'],
        'email_institucional' => ['contacto', 'texto'],
        'direccion'          => ['contacto', 'texto'],
        'facebook'           => ['redes', 'texto'],
        'instagram'          => ['redes', 'texto'],
        'youtube'            => ['redes', 'texto'],
        'tiktok'             => ['redes', 'texto'],
        'x'                  => ['redes', 'texto'],
        'correo_notificaciones' => ['notificaciones', 'texto'],
        'correo_remitente'   => ['notificaciones', 'texto'],
        'portada_titulo'     => ['portada', 'texto'],
        'portada_resumen'    => ['portada', 'texto'],
        'telefonos_emergencia' => ['emergencia', 'texto'],
        'porcentaje_adelanto' => ['alquileres', 'numero'],
        'dias_anticipacion'  => ['alquileres', 'numero'],
        'fotos_ejemplo'      => ['portada', 'booleano'],
        'mantenimiento'      => ['general', 'booleano'],
    ];

    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        try {
            $configuracion = Configuracion::mapaCacheado();

            View::display('configuracion/index', [
                'titulo'        => 'Configuración',
                'configuracion' => $configuracion,
                'marcas'        => self::MARCAS,
                'campos'        => self::CAMPOS,
            ]);
        } catch (Throwable $exception) {
            Logger::error('Error al mostrar la configuracion', [
                'error' => $exception->getMessage(),
                'archivo' => $exception->getFile(),
                'linea' => $exception->getLine(),
            ]);

            Session::flash(
                'error',
                'No se pudo mostrar la configuración: ' . $exception->getMessage()
                . ' (' . basename($exception->getFile()) . ':' . $exception->getLine() . ')'
            );

            Response::redirect(url('/panel'));
        }
    }

    /**
     * Guarda los parámetros de texto.
     */
    public function guardar(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $request->only(array_keys(self::CAMPOS));

        $validador = Validator::make($datos, [
            'nombre_club'         => 'required|string|min:3|max:150',
            'siglas'              => 'required|string|min:2|max:20',
            'lema'                => 'nullable|string|max:150',
            'descripcion_club'    => 'nullable|string|max:500',
            'anios_mandato'       => 'nullable|integer|min:1|max:10',
            'telefono'            => 'nullable|string|max:30',
            'whatsapp'            => 'nullable|string|max:30',
            'email_institucional' => 'nullable|email|max:160',
            'direccion'           => 'nullable|string|max:200',
            'facebook'            => 'nullable|url|max:255',
            'instagram'           => 'nullable|url|max:255',
            'youtube'             => 'nullable|url|max:255',
            'tiktok'              => 'nullable|url|max:255',
            'x'                   => 'nullable|url|max:255',
            'portada_titulo'      => 'nullable|string|max:180',
            'portada_resumen'     => 'nullable|string|max:300',
            'porcentaje_adelanto' => 'nullable|integer|min:0|max:100',
            'dias_anticipacion'   => 'nullable|integer|min:0|max:365',
            'correo_notificaciones' => 'nullable|email|max:160',
            'correo_remitente'    => 'nullable|email|max:160',
            'fotos_ejemplo'       => 'required|in:0,1',
            'mantenimiento'       => 'required|in:0,1',
        ], [
            'nombre_club'         => 'el nombre del club',
            'siglas'              => 'las siglas',
            'lema'                => 'el lema',
            'descripcion_club'    => 'la descripción',
            'anios_mandato'       => 'los años de mandato',
            'telefono'            => 'el teléfono',
            'whatsapp'            => 'el WhatsApp',
            'email_institucional' => 'el correo electrónico',
            'direccion'           => 'la dirección',
            'facebook'            => 'el enlace de Facebook',
            'instagram'           => 'el enlace de Instagram',
            'youtube'             => 'el enlace de YouTube',
            'tiktok'              => 'el enlace de TikTok',
            'x'                   => 'el enlace de X',
            'portada_titulo'      => 'el título de la portada',
            'portada_resumen'     => 'la bajada de la portada',
            'porcentaje_adelanto' => 'el porcentaje de adelanto',
            'dias_anticipacion'   => 'los días de anticipación',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        // Las redes sociales solo admiten enlaces http o https.
        foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'x'] as $claveRed) {
            $enlace = trim((string) ($limpio[$claveRed] ?? ''));

            if ($enlace !== '' && preg_match('#^https?://#i', $enlace) !== 1) {
                $errores[] = 'El enlace de ' . \App\Core\Redes::etiqueta($claveRed)
                    . ' debe empezar por http:// o https://.';
            }
        }

        // Teléfonos de emergencia: llegan como filas editables.
        $emergencias = $this->leerEmergencias($request);

        foreach ($emergencias as $emergencia) {
            if ($emergencia['etiqueta'] === '') {
                $errores[] = 'Indique el nombre del teléfono de emergencia.';
                break;
            }

            if ($emergencia['telefono'] === '') {
                $errores[] = 'Indique el número del teléfono «' . $emergencia['etiqueta'] . '».';
                break;
            }
        }

        $limpio['telefonos_emergencia'] = (string) json_encode(
            $emergencias,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($errores !== []) {
            remember_old_input(array_merge($datos, [
                'emergencia_telefonos' => $request->input('emergencia_telefonos', []),
            ]));
            Session::flash('errores', $errores);
            Response::redirect(url('/configuracion'));
        }

        foreach (self::CAMPOS as $clave => [$grupo, $tipo]) {
            $valor = (string) ($limpio[$clave] ?? '');

            if ($tipo === 'booleano') {
                $valor = $valor === '1' ? '1' : '0';
            }

            try {
                Configuracion::guardar($clave, $valor, $tipo, $grupo);
            } catch (Throwable $exception) {
                Logger::error('No se pudo guardar un parametro', [
                    'clave' => $clave,
                    'error' => $exception->getMessage(),
                ]);
                Session::flash('error', 'No se pudo guardar «' . $clave . '»: ' . $exception->getMessage());

                Response::redirect(url('/configuracion'));
            }
        }

        Configuracion::limpiarCache();

        Audit::log(Auth::id(), 'actualizar_configuracion', 'configuracion', null, 'Parámetros del club actualizados');

        Session::flash('exito', 'La configuración quedó guardada.');

        if ($request->string('accion') === 'probar') {
            $this->probarCorreo($request);
        }

        Response::redirect(url('/configuracion'));
    }

    /**
     * Lee las filas de teléfonos de emergencia del formulario.
     *
     * Cada fila trae la etiqueta, el teléfono y una nota opcional. Las filas
     * completamente vacías se descartan.
     *
     * @return array<int, array{etiqueta: string, telefono: string, nota: string}>
     */
    private function leerEmergencias(Request $request): array
    {
        $filas = $request->input('emergencia_telefonos', []);

        if (!is_array($filas)) {
            return [];
        }

        $lista = [];

        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }

            $etiqueta = trim((string) ($fila['etiqueta'] ?? ''));
            $telefono = trim((string) ($fila['telefono'] ?? ''));
            $nota = trim((string) ($fila['nota'] ?? ''));

            if ($etiqueta === '' && $telefono === '' && $nota === '') {
                continue;
            }

            $lista[] = [
                'etiqueta' => mb_substr($etiqueta, 0, 60),
                'telefono' => mb_substr($telefono, 0, 40),
                'nota'     => mb_substr($nota, 0, 80),
            ];
        }

        return $lista;
    }

    /**
     * Envia un correo de prueba a la direccion configurada.
     */
    private function probarCorreo(Request $request): void
    {
        $destino = Mailer::destinoNotificaciones();

        if ($destino === '') {
            Session::flash(
                'error',
                'Aun no hay una direccion de correo configurada. Escriba el correo de avisos '
                . 'en «Notificaciones» y guarde los cambios.'
            );
            Response::redirect(url('/configuracion'));
        }

        $cuerpo = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"></head><body '
            . 'style="margin:0;background:#f5f7f2;font-family:system-ui,Arial,sans-serif;color:#18251b;">'
            . '<div style="max-width:560px;margin:0 auto;padding:24px;">'
            . '<div style="background:#063b22;border-radius:16px 16px 0 0;padding:20px 24px;color:#fff;">'
            . '<p style="margin:0;font-size:13px;letter-spacing:.16em;text-transform:uppercase;color:#fefe08;">'
            . e((string) config('app.short_name', 'CSDUV3')) . '</p>'
            . '<h1 style="margin:6px 0 0;font-size:21px;">Correo de prueba</h1></div>'
            . '<div style="background:#fff;border-radius:0 0 16px 16px;padding:22px 24px;">'
            . '<p style="margin:0;font-size:15px;line-height:1.6;">'
            . 'Este es un mensaje de prueba. Si le llega, el club ya recibira aquí el aviso '
            . 'de cada reserva que se confirme en el portal.</p>'
            . '<p style="margin:14px 0 0;font-size:13px;color:#6b7a6e;">'
            . 'Si no llega, pida al administrador del hosting que revise el correo saliente '
            . 'y la carpeta de spam.</p>'
            . '</div></div></body></html>';

        $enviado = Mailer::enviar($destino, 'Prueba de correo · ' . (string) config('app.short_name'), $cuerpo);

        if ($enviado) {
            Session::flash('exito', 'Se envio el correo de prueba a ' . $destino . '.');
        } else {
            Session::flash(
                'error',
                'El servidor no acepto el correo. Revise la direccion y pida al administrador '
                . 'del hosting que revise el correo saliente.'
            );
        }
    }

    /**
     * Sube una imagen de marca (logo, favicon o portada) en WebP.
     */
    public function subirMarca(Request $request, string $tipo): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        if (!isset(self::MARCAS[$tipo])) {
            Session::flash('error', 'La imagen indicada no es válida.');
            Response::redirect(url('/configuracion'));
        }

        $archivo = $request->file('archivo');

        if ($archivo === null || (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Session::flash('error', 'Seleccione una imagen.');
            Response::redirect(url('/configuracion'));
        }

        try {
            $guardado = Uploads::guardar($archivo, 'marca');
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
            Response::redirect(url('/configuracion'));
        } catch (Throwable $exception) {
            Logger::error('Error al subir una imagen de marca', ['error' => $exception->getMessage()]);
            Session::flash('error', 'No se pudo guardar la imagen.');
            Response::redirect(url('/configuracion'));
        }

        $anterior = Configuracion::cached('marca_' . $tipo, '');

        Configuracion::guardar('marca_' . $tipo, (string) $guardado['ruta'], 'archivo', 'marca');
        Configuracion::limpiarCache();

        if ($anterior !== '' && $anterior !== (string) $guardado['ruta']) {
            Uploads::borrar($anterior, 'marca/');
        }

        Audit::log(
            Auth::id(),
            'subir_marca',
            'configuracion',
            null,
            self::MARCAS[$tipo] . ' actualizada'
        );

        Session::flash('exito', self::MARCAS[$tipo] . ' actualizada. Se guardó en WebP para pesar menos.');
        Response::redirect(url('/configuracion'));
    }

    /**
     * Restaura la imagen de marca que trae la aplicación.
     */
    public function restaurarMarca(Request $request, string $tipo): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        if (!isset(self::MARCAS[$tipo])) {
            Session::flash('error', 'La imagen indicada no es válida.');
            Response::redirect(url('/configuracion'));
        }

        $anterior = Configuracion::cached('marca_' . $tipo, '');

        Configuracion::guardar('marca_' . $tipo, '', 'archivo', 'marca');
        Configuracion::limpiarCache();

        if ($anterior !== '') {
            Uploads::borrar($anterior, 'marca/');
        }

        Session::flash('exito', self::MARCAS[$tipo] . ' restaurada a la de la aplicación.');
        Response::redirect(url('/configuracion'));
    }

    /**
     * Comprueba que la base de datos esté disponible antes de mostrar la página.
     */
    private function baseDisponible(): bool
    {
        if (Database::isAvailable()) {
            return true;
        }

        Session::flash('error', 'La base de datos no está disponible en este momento.');
        Response::redirect(url('/panel'));
    }
}
