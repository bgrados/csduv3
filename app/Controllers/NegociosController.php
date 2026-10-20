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
use App\Models\Negocio;
use RuntimeException;
use Throwable;

/**
 * Negocios de la unidad vecinal que el club publica en su portal.
 *
 * Todo es editable: nombre, categoría, descripción, contactos, enlace,
 * dirección, horario y logotipo. Solo los negocios visibles se publican.
 */
final class NegociosController
{
    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $categoria = $request->string('categoria');
        $categoria = isset(Negocio::CATEGORIAS[$categoria]) ? $categoria : '';

        try {
            $negocios = Negocio::todas($categoria);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo cargar el directorio de negocios', ['error' => $exception->getMessage()]);
            $negocios = [];
            Session::flash('error', 'El directorio todavía no está disponible: falta aplicar la migración de la base de datos.');
        }

        View::display('negocios/index', [
            'titulo'     => 'Negocios de la unidad',
            'negocios'   => $negocios,
            'categorias' => Negocio::CATEGORIAS,
            'categoria'  => $categoria,
        ]);
    }

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('negocios/form', [
            'titulo'     => 'Nuevo negocio',
            'negocio'    => null,
            'modo'       => 'crear',
            'categorias' => Negocio::CATEGORIAS,
        ]);
    }

    public function crear(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        $archivo = $this->recibirLogotipo($request, $errores);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/negocios/nuevo'));
        }

        try {
            $id = Negocio::crear([
                'nombre'          => (string) $limpio['nombre'],
                'categoria'       => (string) $limpio['categoria'],
                'descripcion'     => (string) ($limpio['descripcion'] ?? ''),
                'telefono'        => (string) ($limpio['telefono'] ?? ''),
                'whatsapp'        => (string) ($limpio['whatsapp'] ?? ''),
                'email'           => (string) ($limpio['email'] ?? ''),
                'web'             => (string) ($limpio['web'] ?? ''),
                'direccion'       => (string) ($limpio['direccion'] ?? ''),
                'horario'         => (string) ($limpio['horario'] ?? ''),
                'archivo'         => $archivo === null ? '' : (string) $archivo['ruta'],
                'nombre_original' => $archivo === null ? '' : (string) $archivo['nombre_original'],
                'tipo_mime'       => $archivo === null ? '' : (string) $archivo['tipo_mime'],
                'tamano_bytes'    => $archivo === null ? 0 : (int) $archivo['tamano'],
                'visible'         => (int) ($limpio['visible'] ?? 1),
                'destacado'       => (int) ($limpio['destacado'] ?? 0),
                'orden'           => (int) ($limpio['orden'] ?? 1),
                'creado_por'      => (int) Auth::id(),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Error al registrar un negocio', ['error' => $exception->getMessage()]);

            if ($archivo !== null) {
                Uploads::borrar((string) $archivo['ruta'], 'negocios/');
            }

            remember_old_input($datos);
            Session::flash('error', 'No se pudo registrar el negocio.');
            Response::redirect(url('/negocios/nuevo'));
        }

        Audit::log(Auth::id(), 'crear_negocio', 'negocio', $id, 'Negocio registrado: ' . (string) $limpio['nombre']);

        Session::flash('exito', 'El negocio quedó registrado.');
        Response::redirect(url('/negocios'));
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $negocio = $this->obtener((int) $id);

        View::display('negocios/form', [
            'titulo'     => 'Editar negocio',
            'negocio'    => $negocio,
            'modo'       => 'editar',
            'categorias' => Negocio::CATEGORIAS,
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $negocio = $this->obtener((int) $id);
        $negocioId = (int) $negocio['id'];

        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        $archivo = $this->recibirLogotipo($request, $errores);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/negocios/' . $negocioId . '/editar'));
        }

        $rutaAnterior = (string) ($negocio['archivo'] ?? '');

        Negocio::actualizar($negocioId, [
            'nombre'          => (string) $limpio['nombre'],
            'categoria'       => (string) $limpio['categoria'],
            'descripcion'     => (string) ($limpio['descripcion'] ?? ''),
            'telefono'        => (string) ($limpio['telefono'] ?? ''),
            'whatsapp'        => (string) ($limpio['whatsapp'] ?? ''),
            'email'           => (string) ($limpio['email'] ?? ''),
            'web'             => (string) ($limpio['web'] ?? ''),
            'direccion'       => (string) ($limpio['direccion'] ?? ''),
            'horario'         => (string) ($limpio['horario'] ?? ''),
            'archivo'         => $archivo === null ? $rutaAnterior : (string) $archivo['ruta'],
            'nombre_original' => $archivo === null
                ? (string) ($negocio['nombre_original'] ?? '')
                : (string) $archivo['nombre_original'],
            'tipo_mime'       => $archivo === null
                ? (string) ($negocio['tipo_mime'] ?? '')
                : (string) $archivo['tipo_mime'],
            'tamano_bytes'    => $archivo === null
                ? (int) ($negocio['tamano_bytes'] ?? 0)
                : (int) $archivo['tamano'],
            'visible'         => (int) ($limpio['visible'] ?? 1),
            'destacado'       => (int) ($limpio['destacado'] ?? 0),
            'orden'           => (int) ($limpio['orden'] ?? 1),
        ]);

        if ($archivo !== null && $rutaAnterior !== '') {
            Uploads::borrar($rutaAnterior, 'negocios/');
        }

        Audit::log(Auth::id(), 'editar_negocio', 'negocio', $negocioId, 'Negocio actualizado: ' . (string) $limpio['nombre']);

        Session::flash('exito', 'El negocio se actualizó.');
        Response::redirect(url('/negocios'));
    }

    public function estado(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $negocio = $this->obtener((int) $id);
        $visible = $request->string('accion') === 'publicar';

        Negocio::cambiarVisible((int) $negocio['id'], $visible);

        Session::flash(
            'exito',
            $visible ? 'El negocio ya se publica en el portal.' : 'El negocio dejó de publicarse.'
        );
        Response::redirect(url('/negocios'));
    }

    public function eliminar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $negocio = $this->obtener((int) $id);

        Negocio::eliminar((int) $negocio['id']);

        $ruta = (string) ($negocio['archivo'] ?? '');

        if ($ruta !== '') {
            Uploads::borrar($ruta, 'negocios/');
        }

        Audit::log(Auth::id(), 'eliminar_negocio', 'negocio', (int) $negocio['id'], 'Negocio eliminado: ' . (string) $negocio['nombre']);

        Session::flash('exito', 'El negocio se eliminó.');
        Response::redirect(url('/negocios'));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        $datos = $request->only([
            'nombre',
            'categoria',
            'descripcion',
            'telefono',
            'whatsapp',
            'email',
            'web',
            'direccion',
            'horario',
            'orden',
        ]);

        $datos['visible'] = $request->boolean('visible') ? '1' : '0';
        $datos['destacado'] = $request->boolean('destacado') ? '1' : '0';

        return $datos;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validar(array $datos): array
    {
        $validador = Validator::make($datos, [
            'nombre'      => 'required|string|min:2|max:140',
            'categoria'   => 'required|in:comercio,servicio,profesional,salud,educacion,otro',
            'descripcion' => 'nullable|string|max:600',
            'telefono'    => 'nullable|string|max:30',
            'whatsapp'    => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:160',
            'web'         => 'nullable|url|max:255',
            'direccion'   => 'nullable|string|max:200',
            'horario'     => 'nullable|string|max:200',
            'orden'       => 'nullable|integer|min:1|max:999',
            'visible'     => 'required|in:0,1',
            'destacado'   => 'required|in:0,1',
        ], [
            'nombre'      => 'nombre del negocio',
            'categoria'   => 'categoría',
            'descripcion' => 'descripción',
            'telefono'    => 'teléfono',
            'whatsapp'    => 'WhatsApp',
            'email'       => 'correo electrónico',
            'web'         => 'enlace',
            'direccion'   => 'dirección',
            'horario'     => 'horario',
            'orden'       => 'orden',
            'visible'     => 'visibilidad',
            'destacado'   => 'destacado',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $web = trim((string) ($limpio['web'] ?? ''));

            if ($web !== '' && !preg_match('#^https?://#i', $web)) {
                $errores[] = 'El enlace debe empezar por http:// o https://';
            }
        }

        return [$limpio, $errores];
    }

    /**
     * @param array<int, string> $errores
     * @return array{ruta: string, nombre_original: string, tipo_mime: string, tamano: int}|null
     */
    private function recibirLogotipo(Request $request, array &$errores): ?array
    {
        $archivo = $request->file('archivo');

        if ($archivo === null || (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        try {
            $guardado = Uploads::guardar($archivo, 'negocios');
        } catch (RuntimeException $exception) {
            $errores[] = $exception->getMessage();
            return null;
        }

        if (!Uploads::esImagen((string) $guardado['tipo_mime'])) {
            Uploads::borrar((string) $guardado['ruta'], 'negocios/');
            $errores[] = 'El logotipo debe ser una imagen (JPG, PNG o WEBP).';

            return null;
        }

        return $guardado;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtener(int $id): array
    {
        $negocio = Negocio::find($id);

        if ($negocio === null) {
            abort(404, 'El negocio indicado no existe.');
        }

        return $negocio;
    }

    private function baseDisponible(): bool
    {
        if (Database::isAvailable()) {
            return true;
        }

        Session::flash('error', 'La base de datos no está disponible en este momento.');
        Response::redirect(url('/panel'));
    }
}
