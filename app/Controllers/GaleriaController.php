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
use App\Models\Foto;
use RuntimeException;
use Throwable;

/**
 * Galería institucional: reuniones, campeonatos, junta directiva y socios.
 *
 * Solo las fotos visibles se publican en el portal público.
 */
final class GaleriaController
{
    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $categoria = $request->string('categoria');
        $categoria = isset(Foto::CATEGORIAS[$categoria]) ? $categoria : '';

        try {
            $fotos = Foto::todas($categoria);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo cargar la galería', ['error' => $exception->getMessage()]);
            $fotos = [];
            Session::flash('error', 'La galería todavía no está disponible: falta aplicar la migración de la base de datos.');
        }

        View::display('galeria/index', [
            'titulo'     => 'Fotos del club',
            'fotos'      => $fotos,
            'categorias' => Foto::CATEGORIAS,
            'categoria'  => $categoria,
        ]);
    }

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('galeria/form', [
            'titulo'     => 'Subir foto',
            'foto'       => null,
            'modo'       => 'crear',
            'categorias' => Foto::CATEGORIAS,
        ]);
    }

    public function crear(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        $archivo = $this->recibirImagen($request, $errores);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/fotos/nuevo'));
        }

        try {
            $id = Foto::crear([
                'titulo'          => (string) $limpio['titulo'],
                'descripcion'     => (string) ($limpio['descripcion'] ?? ''),
                'categoria'       => (string) $limpio['categoria'],
                'archivo'         => (string) $archivo['ruta'],
                'nombre_original' => (string) $archivo['nombre_original'],
                'tipo_mime'       => (string) $archivo['tipo_mime'],
                'tamano_bytes'    => (int) $archivo['tamano'],
                'fecha_foto'      => (string) ($limpio['fecha_foto'] ?? ''),
                'visible'         => (int) ($limpio['visible'] ?? 1),
                'orden'           => (int) ($limpio['orden'] ?? 1),
                'subido_por'      => (int) Auth::id(),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Error al registrar una foto', ['error' => $exception->getMessage()]);
            Uploads::borrar((string) $archivo['ruta'], 'fotos/');
            remember_old_input($datos);
            Session::flash('error', 'No se pudo registrar la foto.');
            Response::redirect(url('/fotos/nuevo'));
        }

        Audit::log(Auth::id(), 'crear_foto', 'foto', $id, 'Foto publicada: ' . (string) $limpio['titulo']);

        Session::flash('exito', 'La foto quedó registrada.');
        Response::redirect(url('/fotos'));
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $foto = $this->obtener((int) $id);

        View::display('galeria/form', [
            'titulo'     => 'Editar foto',
            'foto'       => $foto,
            'modo'       => 'editar',
            'categorias' => Foto::CATEGORIAS,
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $foto = $this->obtener((int) $id);
        $fotoId = (int) $foto['id'];

        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        $archivo = $this->recibirImagen($request, $errores);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/fotos/' . $fotoId . '/editar'));
        }

        $rutaAnterior = (string) ($foto['archivo'] ?? '');

        Foto::actualizar($fotoId, [
            'titulo'          => (string) $limpio['titulo'],
            'descripcion'     => (string) ($limpio['descripcion'] ?? ''),
            'categoria'       => (string) $limpio['categoria'],
            'archivo'         => $archivo === null ? $rutaAnterior : (string) $archivo['ruta'],
            'nombre_original' => $archivo === null
                ? (string) ($foto['nombre_original'] ?? '')
                : (string) $archivo['nombre_original'],
            'tipo_mime'       => $archivo === null
                ? (string) ($foto['tipo_mime'] ?? '')
                : (string) $archivo['tipo_mime'],
            'tamano_bytes'    => $archivo === null
                ? (int) ($foto['tamano_bytes'] ?? 0)
                : (int) $archivo['tamano'],
            'fecha_foto'      => (string) ($limpio['fecha_foto'] ?? ''),
            'visible'         => (int) ($limpio['visible'] ?? 1),
            'orden'           => (int) ($limpio['orden'] ?? 1),
        ]);

        if ($archivo !== null && $rutaAnterior !== '') {
            Uploads::borrar($rutaAnterior, 'fotos/');
        }

        Audit::log(Auth::id(), 'editar_foto', 'foto', $fotoId, 'Foto actualizada: ' . (string) $limpio['titulo']);

        Session::flash('exito', 'La foto se actualizó.');
        Response::redirect(url('/fotos'));
    }

    public function estado(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $foto = $this->obtener((int) $id);
        $visible = $request->string('accion') === 'publicar';

        Foto::cambiarVisible((int) $foto['id'], $visible);

        Session::flash(
            'exito',
            $visible ? 'La foto ya se publica en el portal.' : 'La foto dejó de publicarse en el portal.'
        );
        Response::redirect(url('/fotos'));
    }

    public function eliminar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $foto = $this->obtener((int) $id);

        Foto::eliminar((int) $foto['id']);

        $ruta = (string) ($foto['archivo'] ?? '');

        if ($ruta !== '') {
            Uploads::borrar($ruta, 'fotos/');
        }

        Audit::log(Auth::id(), 'eliminar_foto', 'foto', (int) $foto['id'], 'Foto eliminada: ' . (string) $foto['titulo']);

        Session::flash('exito', 'La foto se eliminó.');
        Response::redirect(url('/fotos'));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        $datos = $request->only(['titulo', 'descripcion', 'categoria', 'fecha_foto', 'orden']);

        $datos['visible'] = $request->boolean('visible') ? '1' : '0';

        return $datos;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validar(array $datos): array
    {
        $validador = Validator::make($datos, [
            'titulo'      => 'required|string|min:3|max:180',
            'descripcion' => 'nullable|string|max:500',
            'categoria'   => 'required|in:reunion,campeonato,directiva,socios,evento,instalacion',
            'fecha_foto'  => 'nullable|date',
            'orden'       => 'nullable|integer|min:1|max:999',
            'visible'     => 'required|in:0,1',
        ], [
            'titulo'      => 'título',
            'descripcion' => 'pie de foto',
            'categoria'   => 'categoría',
            'fecha_foto'  => 'fecha de la foto',
            'orden'       => 'orden',
            'visible'     => 'visibilidad',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        return [$limpio, $errores];
    }

    /**
     * @param array<int, string> $errores
     * @return array{ruta: string, nombre_original: string, tipo_mime: string, tamano: int}|null
     */
    private function recibirImagen(Request $request, array &$errores): ?array
    {
        $archivo = $request->file('archivo');

        if ($archivo === null || (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errores[] = 'Seleccione una imagen.';
            return null;
        }

        try {
            $guardado = Uploads::guardar($archivo, 'fotos');
        } catch (RuntimeException $exception) {
            $errores[] = $exception->getMessage();
            return null;
        }

        if (!Uploads::esImagen((string) $guardado['tipo_mime'])) {
            Uploads::borrar((string) $guardado['ruta'], 'fotos/');
            $errores[] = 'El archivo debe ser una imagen (JPG, PNG o WEBP).';

            return null;
        }

        return $guardado;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtener(int $id): array
    {
        $foto = Foto::find($id);

        if ($foto === null) {
            abort(404, 'La foto indicada no existe.');
        }

        return $foto;
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
