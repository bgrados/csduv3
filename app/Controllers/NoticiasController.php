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
use App\Core\Video;
use App\Core\View;
use App\Models\Foto;
use RuntimeException;
use Throwable;

/**
 * Noticias y galería institucional: reuniones, campeonatos, junta directiva y socios.
 *
 * Una sola noticia ocupa la portada del portal; las demás quedan en la galería.
 */
final class NoticiasController
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
            Logger::warning('No se pudieron cargar las noticias', ['error' => $exception->getMessage()]);
            $fotos = [];
            Session::flash('error', 'Las noticias todavía no están disponibles: falta aplicar la migración de la base de datos.');
        }

        View::display('noticias/index', [
            'titulo'     => 'Noticias y fotos',
            'fotos'      => $fotos,
            'categorias' => Foto::CATEGORIAS,
            'categoria'  => $categoria,
            'portada'    => Foto::portada(),
        ]);
    }

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('noticias/form', [
            'titulo'     => 'Nueva noticia',
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

        if ($archivo === null && trim((string) ($limpio['video_url'] ?? '')) === '') {
            $errores[] = 'Seleccione una imagen o indique el enlace de un video.';
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/noticias/nuevo'));
        }

        try {
            $id = Foto::crear([
                'titulo'          => (string) $limpio['titulo'],
                'resumen'         => (string) ($limpio['resumen'] ?? ''),
                'descripcion'     => (string) ($limpio['descripcion'] ?? ''),
                'categoria'       => (string) $limpio['categoria'],
                'archivo'         => (string) ($archivo['ruta'] ?? ''),
                'nombre_original' => (string) ($archivo['nombre_original'] ?? ''),
                'tipo_mime'       => (string) ($archivo['tipo_mime'] ?? ''),
                'tamano_bytes'    => (int) ($archivo['tamano'] ?? 0),
                'fecha_foto'      => (string) ($limpio['fecha_foto'] ?? ''),
                'visible'         => (int) ($limpio['visible'] ?? 1),
                'destacado'       => (int) ($limpio['destacado'] ?? 0),
                'video_url'       => (string) ($limpio['video_url'] ?? ''),
                'video_tipo'      => (string) ($limpio['video_tipo'] ?? ''),
                'orden'           => (int) ($limpio['orden'] ?? 1),
                'subido_por'      => (int) Auth::id(),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Error al registrar una foto', ['error' => $exception->getMessage()]);
            Uploads::borrar((string) ($archivo['ruta'] ?? ''), 'fotos/');
            remember_old_input($datos);
            Session::flash('error', 'No se pudo registrar la noticia.');
            Response::redirect(url('/noticias/nuevo'));
        }

        Audit::log(Auth::id(), 'crear_foto', 'foto', $id, 'Foto publicada: ' . (string) $limpio['titulo']);

        Session::flash('exito', 'La noticia quedó registrada.');
        Response::redirect(url('/noticias'));
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $foto = $this->obtener((int) $id);

        View::display('noticias/form', [
            'titulo'     => 'Editar noticia',
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
            Response::redirect(url('/noticias/' . $fotoId . '/editar'));
        }

        $rutaAnterior = (string) ($foto['archivo'] ?? '');

        Foto::actualizar($fotoId, [
            'titulo'          => (string) $limpio['titulo'],
            'resumen'         => (string) ($limpio['resumen'] ?? ''),
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
            'destacado'       => (int) ($limpio['destacado'] ?? 0),
            'video_url'       => (string) ($limpio['video_url'] ?? ''),
            'video_tipo'      => (string) ($limpio['video_tipo'] ?? ''),
            'orden'           => (int) ($limpio['orden'] ?? 1),
        ]);

        if ($archivo !== null && $rutaAnterior !== '') {
            Uploads::borrar($rutaAnterior, 'fotos/');
        }

        Audit::log(Auth::id(), 'editar_foto', 'foto', $fotoId, 'Foto actualizada: ' . (string) $limpio['titulo']);

        Session::flash('exito', 'La noticia se actualizó.');
        Response::redirect(url('/noticias'));
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
        Response::redirect(url('/noticias'));
    }

    public function destacar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $foto = $this->obtener((int) $id);
        $enPortada = (int) $foto['destacado'] === 1;

        if ($enPortada) {
            Foto::quitarPortada((int) $foto['id']);
        } else {
            Foto::establecerPortada((int) $foto['id']);
        }

        Session::flash(
            'exito',
            $enPortada
                ? 'La noticia dejó de ocupar la portada.'
                : 'La noticia ya ocupa la portada del portal.'
        );
        Response::redirect(url('/noticias'));
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

        Session::flash('exito', 'La noticia se eliminó.');
        Response::redirect(url('/noticias'));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        $datos = $request->only(['titulo', 'resumen', 'descripcion', 'categoria', 'fecha_foto', 'orden', 'video_url']);

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
            'titulo'      => 'required|string|min:3|max:180',
            'resumen'     => 'nullable|string|max:300',
            'descripcion' => 'nullable|string|max:500',
            'categoria'   => 'required|in:reunion,campeonato,directiva,socios,evento,instalacion',
            'fecha_foto'  => 'nullable|date',
            'orden'       => 'nullable|integer|min:1|max:999',
            'visible'     => 'required|in:0,1',
            'destacado'   => 'required|in:0,1',
            'video_url'   => 'nullable|string|max:255',
        ], [
            'titulo'      => 'titular',
            'resumen'     => 'entradilla',
            'descripcion' => 'pie de foto',
            'categoria'   => 'categoría',
            'fecha_foto'  => 'fecha de la foto',
            'orden'       => 'orden',
            'visible'     => 'visibilidad',
            'destacado'   => 'noticia de portada',
            'video_url'   => 'enlace del video',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        // El video tiene que ser un enlace de YouTube o de Facebook.
        $video = trim((string) ($limpio['video_url'] ?? ''));

        if ($video !== '' && !Video::esValido($video)) {
            $errores[] = 'El enlace del video debe ser de YouTube o de Facebook (video o reel).';
            $limpio['video_url'] = '';
        }

        $limpio['video_tipo'] = $video === '' ? null : self::tipoVideo($video);

        return [$limpio, $errores];
    }

    /**
     * Plataforma del video: youtube o facebook.
     */
    private static function tipoVideo(string $url): ?string
    {
        $datos = Video::detectar($url);

        return $datos === null ? null : (string) $datos['tipo'];
    }

    /**
     * @param array<int, string> $errores
     * @return array{ruta: string, nombre_original: string, tipo_mime: string, tamano: int}|null
     */
    private function recibirImagen(Request $request, array &$errores): ?array
    {
        $archivo = $request->file('archivo');

        if ($archivo === null || (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            // La imagen es opcional: puede ser solo un video.
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
