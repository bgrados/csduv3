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
use App\Core\Validator;
use App\Core\View;
use App\Models\Documento;
use App\Models\Socio;
use DateTimeImmutable;
use Throwable;

/**
 * Documentos institucionales.
 *
 * Los archivos se guardan en `storage/uploads/documentos`, fuera de la carpeta
 * pública, y se entregan por el propio controlador después de comprobar los
 * permisos. Nunca se sirven desde `public/`.
 *
 * Los permisos (`documentos.ver`, `documentos.gestionar`) se comprueban en
 * app/routes.php.
 */
final class DocumentosController
{
    private const POR_PAGINA = 20;

    /** Tamaño máximo permitido: 5 MB. */
    private const TAMANO_MAXIMO = 5242880;

    /** Extensiones admitidas. */
    private const EXTENSIONES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'txt' => 'text/plain',
    ];

    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $filtros = $this->filtros($request);
        $total = Documento::total($filtros);
        $pagina = max(1, $request->integer('pagina', 1));

        View::display('documentos/index', [
            'titulo'     => 'Documentos',
            'documentos' => Documento::listar(
                $filtros,
                self::POR_PAGINA,
                ($pagina - 1) * self::POR_PAGINA
            ),
            'categorias' => Documento::CATEGORIAS,
            'filtros'    => $filtros,
            'total'      => $total,
            'pagina'     => $pagina,
            'paginas'    => max(1, (int) ceil($total / self::POR_PAGINA)),
        ]);
    }

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('documentos/form', [
            'titulo'    => 'Nuevo documento',
            'documento' => null,
            'modo'      => 'crear',
            'categorias' => Documento::CATEGORIAS,
            'socios'    => Socio::listar(null, 'activo', 0, 100, 0),
        ]);
    }

    public function crear(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        $archivo = $this->recibirArchivo($request, $errores);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/documentos/nuevo'));
        }

        $socio = $this->socioPorCodigo(trim((string) ($limpio['socio_codigo'] ?? '')));

        try {
            $id = Documento::crear([
                'titulo'          => (string) $limpio['titulo'],
                'categoria'       => (string) $limpio['categoria'],
                'descripcion'     => (string) ($limpio['descripcion'] ?? ''),
                'archivo'         => $archivo === null ? '' : (string) $archivo['ruta'],
                'nombre_original' => $archivo === null ? '' : (string) $archivo['nombre_original'],
                'tipo_mime'       => $archivo === null ? '' : (string) $archivo['tipo_mime'],
                'tamano_bytes'    => $archivo === null ? 0 : (int) $archivo['tamano'],
                'fecha_documento' => (string) ($limpio['fecha_documento'] ?? ''),
                'es_privado'      => (int) ($limpio['es_privado'] ?? 1),
                'socio_id'        => $socio === null ? 0 : (int) $socio['id'],
                'subido_por'      => (int) Auth::id(),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Error al registrar un documento', ['error' => $exception->getMessage()]);

            if ($archivo !== null) {
                $this->borrarArchivo((string) $archivo['ruta']);
            }

            remember_old_input($datos);
            Session::flash('error', 'No se pudo registrar el documento.');
            Response::redirect(url('/documentos/nuevo'));
        }

        Audit::log(Auth::id(), 'crear_documento', 'documento', $id, 'Documento registrado: ' . (string) $limpio['titulo']);

        Session::flash('exito', 'El documento quedó registrado.');
        Response::redirect(url('/documentos'));
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $documento = $this->obtener((int) $id);

        View::display('documentos/form', [
            'titulo'    => 'Editar documento',
            'documento' => $documento,
            'modo'      => 'editar',
            'categorias' => Documento::CATEGORIAS,
            'socios'    => Socio::listar(null, 'activo', 0, 100, 0),
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $documento = $this->obtener((int) $id);
        $documentoId = (int) $documento['id'];

        $datos = $this->datosFormulario($request);
        [$limpio, $errores] = $this->validar($datos);

        $archivo = $this->recibirArchivo($request, $errores);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/documentos/' . $documentoId . '/editar'));
        }

        $socio = $this->socioPorCodigo(trim((string) ($limpio['socio_codigo'] ?? '')));

        $rutaAnterior = (string) ($documento['archivo'] ?? '');

        Documento::actualizar($documentoId, [
            'titulo'          => (string) $limpio['titulo'],
            'categoria'       => (string) $limpio['categoria'],
            'descripcion'     => (string) ($limpio['descripcion'] ?? ''),
            'archivo'         => $archivo === null ? $rutaAnterior : (string) $archivo['ruta'],
            'nombre_original' => $archivo === null
                ? (string) ($documento['nombre_original'] ?? '')
                : (string) $archivo['nombre_original'],
            'tipo_mime'       => $archivo === null
                ? (string) ($documento['tipo_mime'] ?? '')
                : (string) $archivo['tipo_mime'],
            'tamano_bytes'    => $archivo === null
                ? (int) ($documento['tamano_bytes'] ?? 0)
                : (int) $archivo['tamano'],
            'fecha_documento' => (string) ($limpio['fecha_documento'] ?? ''),
            'es_privado'      => (int) ($limpio['es_privado'] ?? 1),
            'socio_id'        => $socio === null ? 0 : (int) $socio['id'],
        ]);

        if ($archivo !== null && $rutaAnterior !== '') {
            $this->borrarArchivo($rutaAnterior);
        }

        Audit::log(Auth::id(), 'editar_documento', 'documento', $documentoId, 'Documento actualizado: ' . (string) $limpio['titulo']);

        Session::flash('exito', 'El documento se actualizó.');
        Response::redirect(url('/documentos'));
    }

    public function eliminar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $documento = $this->obtener((int) $id);

        Documento::eliminar((int) $documento['id']);

        $ruta = (string) ($documento['archivo'] ?? '');

        if ($ruta !== '') {
            $this->borrarArchivo($ruta);
        }

        Audit::log(Auth::id(), 'eliminar_documento', 'documento', (int) $documento['id'], 'Documento eliminado: ' . (string) $documento['titulo']);

        Session::flash('exito', 'El documento se eliminó.');
        Response::redirect(url('/documentos'));
    }

    /**
     * Entrega el archivo del documento comprobando los permisos.
     */
    public function descargar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $documento = $this->obtener((int) $id);
        $ruta = trim((string) ($documento['archivo'] ?? ''));

        if ($ruta === '') {
            Session::flash('error', 'Este documento no tiene archivo adjunto.');
            Response::redirect(url('/documentos'));
        }

        $absoluta = $this->rutaAbsoluta($ruta);

        if ($absoluta === null || !is_file($absoluta)) {
            Session::flash('error', 'El archivo del documento no está disponible.');
            Response::redirect(url('/documentos'));
        }

        Audit::log(Auth::id(), 'descargar_documento', 'documento', (int) $documento['id'], 'Descarga: ' . (string) $documento['titulo']);

        $nombre = (string) ($documento['nombre_original'] ?? basename($ruta));
        $mime = (string) ($documento['tipo_mime'] ?? 'application/octet-stream');

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) filesize($absoluta));
            header('Content-Disposition: attachment; filename="' . rawurlencode($nombre) . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');
        }

        readfile($absoluta);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Request $request): array
    {
        $categoria = $request->string('categoria');
        $categoria = isset(Documento::CATEGORIAS[$categoria]) ? $categoria : '';

        $privacidad = $request->string('privacidad');
        $privacidad = in_array($privacidad, ['privado', 'publico'], true) ? $privacidad : '';

        return [
            'q'          => $request->string('q'),
            'categoria'  => $categoria,
            'privacidad' => $privacidad,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request): array
    {
        $datos = $request->only([
            'titulo',
            'categoria',
            'descripcion',
            'fecha_documento',
            'socio_codigo',
        ]);

        $datos['es_privado'] = $request->boolean('es_privado') ? '1' : '0';

        return $datos;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validar(array $datos): array
    {
        $validador = Validator::make($datos, [
            'titulo'          => 'required|string|min:3|max:180',
            'categoria'       => 'required|in:acta,resolucion,reglamento,contrato,informe,eleccion,socio,general',
            'descripcion'     => 'nullable|string|max:2000',
            'fecha_documento' => 'nullable|date',
            'socio_codigo'    => 'nullable|string|max:30',
            'es_privado'      => 'required|in:0,1',
        ], [
            'titulo'          => 'título',
            'categoria'       => 'categoría',
            'descripcion'     => 'descripción',
            'fecha_documento' => 'fecha del documento',
            'socio_codigo'    => 'código de socio',
            'es_privado'      => 'privacidad',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $codigo = trim((string) ($limpio['socio_codigo'] ?? ''));

            if ($codigo !== '' && Socio::findPorCodigo($codigo) === null) {
                $errores[] = 'No existe un socio con ese código.';
            }
        }

        return [$limpio, $errores];
    }

    /**
     * Recibe y guarda el archivo subido, si lo hay.
     *
     * @param array<int, string> $errores
     * @return array{ruta: string, nombre_original: string, tipo_mime: string, tamano: int}|null
     */
    private function recibirArchivo(Request $request, array &$errores): ?array
    {
        $archivo = $request->file('archivo');

        if ($archivo === null) {
            return null;
        }

        $codigo = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($codigo === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($codigo !== UPLOAD_ERR_OK) {
            $errores[] = 'No se pudo subir el archivo. Intente nuevamente.';
            return null;
        }

        $tamano = (int) ($archivo['size'] ?? 0);

        if ($tamano <= 0) {
            $errores[] = 'El archivo está vacío.';
            return null;
        }

        if ($tamano > self::TAMANO_MAXIMO) {
            $errores[] = 'El archivo supera el máximo de 5 MB.';
            return null;
        }

        $original = (string) ($archivo['name'] ?? 'archivo');
        $extension = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));

        if (!isset(self::EXTENSIONES[$extension])) {
            $errores[] = 'El tipo de archivo no está permitido (PDF, imágenes o documentos de Office).';
            return null;
        }

        $temporal = (string) ($archivo['tmp_name'] ?? '');

        if ($temporal === '' || !is_uploaded_file($temporal)) {
            $errores[] = 'La carga del archivo no es válida.';
            return null;
        }

        $mimeReal = $this->mimeReal($temporal);
        $mime = $mimeReal !== null ? $mimeReal : (string) ($archivo['type'] ?? self::EXTENSIONES[$extension]);

        if (!in_array($mime, self::EXTENSIONES, true)) {
            $errores[] = 'El contenido del archivo no corresponde a un tipo permitido.';
            return null;
        }

        $carpeta = 'documentos/' . date('Y/m');
        $directorio = storage_path('uploads/' . $carpeta);

        if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
            $errores[] = 'No se pudo preparar la carpeta de archivos.';
            return null;
        }

        $nombre = bin2hex(random_bytes(16)) . '.' . $extension;
        $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;

        if (!move_uploaded_file($temporal, $destino)) {
            $errores[] = 'No se pudo guardar el archivo en el servidor.';
            return null;
        }

        @chmod($destino, 0640);

        return [
            'ruta'            => $carpeta . '/' . $nombre,
            'nombre_original' => $original,
            'tipo_mime'       => $mime,
            'tamano'          => $tamano,
        ];
    }

    private function mimeReal(string $ruta): ?string
    {
        if (!function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mime = finfo_file($finfo, $ruta);
        finfo_close($finfo);

        return is_string($mime) ? $mime : null;
    }

    /**
     * Traduce la ruta relativa guardada a una ruta segura del disco.
     */
    private function rutaAbsoluta(string $ruta): ?string
    {
        $ruta = str_replace(['..', "\0"], '', $ruta);
        $base = storage_path('uploads');

        if (!str_starts_with($ruta, 'documentos/')) {
            return null;
        }

        return $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ruta);
    }

    private function borrarArchivo(string $ruta): void
    {
        $absoluta = $this->rutaAbsoluta($ruta);

        if ($absoluta !== null && is_file($absoluta)) {
            @unlink($absoluta);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function socioPorCodigo(string $codigo): ?array
    {
        if ($codigo === '') {
            return null;
        }

        return Socio::findPorCodigo($codigo);
    }

    /**
     * @return array<string, mixed>
     */
    private function obtener(int $id): array
    {
        $documento = Documento::find($id);

        if ($documento === null) {
            abort(404, 'El documento indicado no existe.');
        }

        return $documento;
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
