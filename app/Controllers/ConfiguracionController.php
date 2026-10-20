<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
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
        'facebook'           => ['contacto', 'texto'],
        'instagram'          => ['contacto', 'texto'],
        'portada_titulo'     => ['portada', 'texto'],
        'portada_resumen'    => ['portada', 'texto'],
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

        $configuracion = Configuracion::mapaCacheado();

        View::display('configuracion/index', [
            'titulo'        => 'Configuración',
            'configuracion' => $configuracion,
            'marcas'        => self::MARCAS,
            'campos'        => self::CAMPOS,
        ]);
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
            'portada_titulo'      => 'nullable|string|max:180',
            'portada_resumen'     => 'nullable|string|max:300',
            'porcentaje_adelanto' => 'nullable|integer|min:0|max:100',
            'dias_anticipacion'   => 'nullable|integer|min:0|max:365',
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
            'portada_titulo'      => 'el título de la portada',
            'portada_resumen'     => 'la bajada de la portada',
            'porcentaje_adelanto' => 'el porcentaje de adelanto',
            'dias_anticipacion'   => 'los días de anticipación',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/configuracion'));
        }

        foreach (self::CAMPOS as $clave => [$grupo, $tipo]) {
            $valor = (string) ($limpio[$clave] ?? '');

            if ($tipo === 'booleano') {
                $valor = $valor === '1' ? '1' : '0';
            }

            Configuracion::guardar($clave, $valor, $tipo, $grupo);
        }

        Configuracion::limpiarCache();

        Audit::log(Auth::id(), 'actualizar_configuracion', 'configuracion', null, 'Parámetros del club actualizados');

        Session::flash('exito', 'La configuración quedó guardada.');
        Response::redirect(url('/configuracion'));
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
}
