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
use App\Models\CategoriaSocio;
use App\Models\Socio;
use DateTimeImmutable;
use PDOException;
use Throwable;

/**
 * Padrón de socios.
 *
 * Un socio nunca se elimina: la baja se registra en `socios_bajas` y el
 * historial se conserva. Todas las operaciones exigen el permiso
 * correspondiente en app/routes.php.
 */
final class SociosController
{
    private const POR_PAGINA = 20;

    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $busqueda = $request->string('q');
        $busqueda = $busqueda === '' ? null : $busqueda;

        $estado = $request->string('estado');
        $estado = isset(Socio::ESTADOS[$estado]) ? $estado : '';

        $categoria = $request->integer('categoria', 0);
        $categoria = $categoria > 0 ? $categoria : 0;

        $pagina = max(1, $request->integer('pagina', 1));
        $total = Socio::total($busqueda, $estado === '' ? null : $estado, $categoria);

        View::display('socios/index', [
            'titulo'     => 'Socios',
            'socios'     => Socio::listar(
                $busqueda,
                $estado === '' ? null : $estado,
                $categoria,
                self::POR_PAGINA,
                ($pagina - 1) * self::POR_PAGINA
            ),
            'categorias' => CategoriaSocio::todas(),
            'estados'    => Socio::ESTADOS,
            'busqueda'   => $busqueda ?? '',
            'estado'     => $estado,
            'categoria'  => $categoria,
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

        View::display('socios/form', [
            'titulo'          => 'Nuevo socio',
            'socio'           => null,
            'modo'            => 'crear',
            'categorias'      => CategoriaSocio::todas(),
            'estados'         => Socio::ESTADOS_EDITABLES,
            'tiposDocumento'  => Socio::TIPOS_DOCUMENTO,
            'sexos'           => Socio::SEXOS,
            'codigoSugerido'  => Socio::siguienteCodigo(),
        ]);
    }

    public function crear(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $request->only([
            'codigo',
            'nombres',
            'apellidos',
            'tipo_documento',
            'numero_documento',
            'fecha_nacimiento',
            'sexo',
            'estado_civil',
            'direccion',
            'distrito',
            'telefono',
            'telefono_alterno',
            'email',
            'fecha_ingreso',
            'categoria_id',
            'estado',
            'observaciones',
        ]);

        [$limpio, $errores] = $this->validar($datos, null, array_keys(Socio::ESTADOS_EDITABLES));

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/socios/nuevo'));
        }

        try {
            $id = Socio::crear($this->registro($limpio));
        } catch (PDOException $exception) {
            if ($this->esDuplicado($exception)) {
                remember_old_input($datos);
                Session::flash(
                    'error',
                    'No se pudo registrar el socio: el código o el documento ya están en uso.'
                );
                Response::redirect(url('/socios/nuevo'));
            }

            Logger::error('Error al registrar un socio', ['error' => $exception->getMessage()]);

            remember_old_input($datos);
            Session::flash('error', 'No se pudo registrar el socio. Intente nuevamente.');
            Response::redirect(url('/socios/nuevo'));
        }

        Audit::log(
            Auth::id(),
            'crear_socio',
            'socio',
            $id,
            'Socio registrado: ' . (string) $limpio['codigo']
        );

        Session::flash('exito', 'El socio quedó registrado con el código ' . (string) $limpio['codigo'] . '.');
        Response::redirect(url('/socios/' . $id));
    }

    public function ver(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $socio = $this->obtener((int) $id);

        View::display('socios/ver', [
            'titulo'      => 'Socio ' . (string) $socio['codigo'],
            'socio'       => $socio,
            'baja'        => Socio::bajaDe((int) $socio['id']),
            'estados'     => Socio::ESTADOS,
            'tiposDocumento' => Socio::TIPOS_DOCUMENTO,
            'sexos'       => Socio::SEXOS,
        ]);
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $socio = $this->obtener((int) $id);
        $estados = Socio::ESTADOS_EDITABLES;

        if ((string) $socio['estado'] === 'baja') {
            $estados['baja'] = Socio::ESTADOS['baja'];
        }

        View::display('socios/form', [
            'titulo'         => 'Editar socio',
            'socio'          => $socio,
            'modo'           => 'editar',
            'categorias'     => CategoriaSocio::todas(),
            'estados'        => $estados,
            'tiposDocumento' => Socio::TIPOS_DOCUMENTO,
            'sexos'          => Socio::SEXOS,
            'codigoSugerido' => (string) $socio['codigo'],
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $socio = $this->obtener((int) $id);
        $socioId = (int) $socio['id'];

        $datos = $request->only([
            'codigo',
            'nombres',
            'apellidos',
            'tipo_documento',
            'numero_documento',
            'fecha_nacimiento',
            'sexo',
            'estado_civil',
            'direccion',
            'distrito',
            'telefono',
            'telefono_alterno',
            'email',
            'fecha_ingreso',
            'categoria_id',
            'estado',
            'observaciones',
        ]);

        $estadosPermitidos = array_keys(Socio::ESTADOS_EDITABLES);

        if ((string) $socio['estado'] === 'baja') {
            $estadosPermitidos[] = 'baja';
        }

        [$limpio, $errores] = $this->validar($datos, $socioId, $estadosPermitidos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/socios/' . $socioId . '/editar'));
        }

        $registro = $this->registro($limpio);
        unset($registro['creado_por']);

        try {
            Socio::actualizar($socioId, $registro);
        } catch (PDOException $exception) {
            if ($this->esDuplicado($exception)) {
                remember_old_input($datos);
                Session::flash(
                    'error',
                    'No se pudo guardar: el código o el documento ya pertenecen a otro socio.'
                );
                Response::redirect(url('/socios/' . $socioId . '/editar'));
            }

            Logger::error('Error al actualizar un socio', ['socio_id' => $socioId, 'error' => $exception->getMessage()]);

            remember_old_input($datos);
            Session::flash('error', 'No se pudo guardar la ficha del socio. Intente nuevamente.');
            Response::redirect(url('/socios/' . $socioId . '/editar'));
        }

        $reactivado = (string) $socio['estado'] === 'baja' && (string) $limpio['estado'] !== 'baja';

        Audit::log(
            Auth::id(),
            $reactivado ? 'reactivar_socio' : 'editar_socio',
            'socio',
            $socioId,
            ($reactivado ? 'Socio reactivado: ' : 'Ficha actualizada: ') . (string) $socio['codigo']
        );

        Session::flash('exito', $reactivado ? 'El socio quedó reactivado.' : 'La ficha del socio se actualizó.');
        Response::redirect(url('/socios/' . $socioId));
    }

    public function baja(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $socio = $this->obtener((int) $id);
        $socioId = (int) $socio['id'];

        if ((string) $socio['estado'] === 'baja') {
            Session::flash('error', 'Este socio ya figura dado de baja.');
            Response::redirect(url('/socios/' . $socioId));
        }

        $fecha = $request->string('fecha_baja');
        $fecha = $fecha === '' ? date('Y-m-d') : $fecha;
        $motivo = $request->string('motivo');
        $renuncia = $request->boolean('es_renuncia');

        $errores = [];

        if (!$this->fechaValida($fecha)) {
            $errores[] = 'Indique una fecha de baja válida.';
        } elseif ($fecha > date('Y-m-d')) {
            $errores[] = 'La fecha de baja no puede ser posterior a hoy.';
        }

        if (mb_strlen($motivo) > 300) {
            $errores[] = 'El motivo de la baja no debe superar los 300 caracteres.';
        }

        if ($errores !== []) {
            Session::flash('errores', $errores);
            Response::redirect(url('/socios/' . $socioId));
        }

        try {
            Socio::registrarBaja(
                $socioId,
                $fecha,
                $motivo === '' ? null : $motivo,
                $renuncia,
                Auth::id()
            );
        } catch (Throwable $exception) {
            Logger::error('Error al registrar la baja de un socio', [
                'socio_id' => $socioId,
                'error'    => $exception->getMessage(),
            ]);

            Session::flash('error', 'No se pudo registrar la baja. Intente nuevamente.');
            Response::redirect(url('/socios/' . $socioId));
        }

        Audit::log(
            Auth::id(),
            'baja_socio',
            'socio',
            $socioId,
            'Socio dado de baja: ' . (string) $socio['codigo']
        );

        Session::flash('exito', 'La baja quedó registrada. El historial del socio se conserva.');
        Response::redirect(url('/socios/' . $socioId));
    }

    public function categorias(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('socios/categorias', [
            'titulo'     => 'Categorías de socio',
            'categorias' => CategoriaSocio::todasConUso(),
        ]);
    }

    public function crearCategoria(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $request->only(['nombre', 'descripcion', 'orden']);

        $validador = Validator::make($datos, [
            'nombre'      => 'required|string|min:2|max:80',
            'descripcion' => 'nullable|string|max:200',
            'orden'       => 'nullable|integer',
        ], [
            'nombre'      => 'nombre de la categoría',
            'descripcion' => 'descripción',
            'orden'       => 'orden',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();
        $orden = (int) ($limpio['orden'] ?? 1);

        if ($errores === [] && CategoriaSocio::nombreExiste((string) $limpio['nombre'])) {
            $errores[] = 'Ya existe una categoría con ese nombre.';
        }

        if ($errores === [] && ($orden < 1 || $orden > 9999)) {
            $errores[] = 'El orden debe ser un número entre 1 y 9999.';
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/socios/categorias'));
        }

        try {
            $id = CategoriaSocio::crear([
                'nombre'      => (string) $limpio['nombre'],
                'descripcion' => (string) ($limpio['descripcion'] ?? ''),
                'orden'       => $orden,
            ]);
        } catch (PDOException $exception) {
            if ($this->esDuplicado($exception)) {
                Session::flash('error', 'Ya existe una categoría con ese nombre.');
                Response::redirect(url('/socios/categorias'));
            }

            Logger::error('Error al crear una categoría de socio', ['error' => $exception->getMessage()]);

            Session::flash('error', 'No se pudo crear la categoría. Intente nuevamente.');
            Response::redirect(url('/socios/categorias'));
        }

        Audit::log(
            Auth::id(),
            'crear_categoria_socio',
            'categoria_socio',
            $id,
            'Categoría creada: ' . (string) $limpio['nombre']
        );

        Session::flash('exito', 'La categoría quedó creada.');
        Response::redirect(url('/socios/categorias'));
    }

    public function cambiarCategoriaActivo(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $categoria = CategoriaSocio::find((int) $id);

        if ($categoria === null) {
            abort(404, 'La categoría indicada no existe.');
        }

        $activar = $request->string('accion') === 'activar';

        CategoriaSocio::cambiarActivo((int) $categoria['id'], $activar);

        Audit::log(
            Auth::id(),
            $activar ? 'activar_categoria_socio' : 'desactivar_categoria_socio',
            'categoria_socio',
            (int) $categoria['id'],
            ($activar ? 'Categoría activada: ' : 'Categoría desactivada: ') . (string) $categoria['nombre']
        );

        Session::flash('exito', $activar ? 'La categoría quedó activa.' : 'La categoría quedó desactivada.');
        Response::redirect(url('/socios/categorias'));
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<int, string>   $estadosPermitidos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validar(array $datos, ?int $socioId, array $estadosPermitidos): array
    {
        $validador = Validator::make($datos, [
            'codigo'           => 'required|string|min:3|max:30|regex:/^[A-Za-z0-9._-]+$/',
            'nombres'          => 'required|string|min:2|max:120',
            'apellidos'        => 'required|string|min:2|max:120',
            'tipo_documento'   => 'required|in:DNI,CE,PASAPORTE,OTRO',
            'numero_documento' => 'nullable|string|max:30',
            'fecha_nacimiento' => 'nullable|date:Y-m-d',
            'sexo'             => 'nullable|in:M,F,OTRO',
            'estado_civil'     => 'nullable|string|max:30',
            'direccion'        => 'nullable|string|max:200',
            'distrito'         => 'nullable|string|max:80',
            'telefono'         => 'nullable|string|max:30',
            'telefono_alterno' => 'nullable|string|max:30',
            'email'            => 'nullable|email|max:160',
            'fecha_ingreso'    => 'nullable|date:Y-m-d',
            'categoria_id'     => 'nullable|integer|exists:categorias_socio,id',
            'estado'           => 'required|in:' . implode(',', $estadosPermitidos),
            'observaciones'    => 'nullable|string|max:2000',
        ], [
            'codigo'           => 'código de socio',
            'nombres'          => 'nombres',
            'apellidos'        => 'apellidos',
            'tipo_documento'   => 'tipo de documento',
            'numero_documento' => 'número de documento',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'sexo'             => 'sexo',
            'estado_civil'     => 'estado civil',
            'direccion'        => 'dirección',
            'distrito'         => 'distrito',
            'telefono'         => 'teléfono',
            'telefono_alterno' => 'teléfono alterno',
            'email'            => 'correo electrónico',
            'fecha_ingreso'    => 'fecha de ingreso',
            'categoria_id'     => 'categoría',
            'estado'           => 'estado',
            'observaciones'    => 'observaciones',
        ]);

        $errores = array_values($validador->errors());

        if ($validador->fails()) {
            return [$validador->validated(), $errores];
        }

        $limpio = $validador->validated();

        if (Socio::existeCodigo((string) $limpio['codigo'], $socioId)) {
            $errores[] = 'Ya existe un socio con el código indicado.';
        }

        $documento = (string) ($limpio['numero_documento'] ?? '');

        if (
            $documento !== ''
            && Socio::existeDocumento((string) $limpio['tipo_documento'], $documento, $socioId)
        ) {
            $errores[] = 'Ya existe un socio con ese tipo y número de documento.';
        }

        $nacimiento = (string) ($limpio['fecha_nacimiento'] ?? '');

        if ($nacimiento !== '' && $nacimiento > date('Y-m-d')) {
            $errores[] = 'La fecha de nacimiento no puede ser posterior a hoy.';
        }

        return [$limpio, $errores];
    }

    /**
     * @param array<string, mixed> $limpio
     * @return array<string, mixed>
     */
    private function registro(array $limpio): array
    {
        return [
            'codigo'           => (string) $limpio['codigo'],
            'nombres'          => (string) $limpio['nombres'],
            'apellidos'        => (string) $limpio['apellidos'],
            'tipo_documento'   => (string) $limpio['tipo_documento'],
            'numero_documento' => (string) ($limpio['numero_documento'] ?? ''),
            'fecha_nacimiento' => (string) ($limpio['fecha_nacimiento'] ?? ''),
            'sexo'             => (string) ($limpio['sexo'] ?? ''),
            'estado_civil'     => (string) ($limpio['estado_civil'] ?? ''),
            'direccion'        => (string) ($limpio['direccion'] ?? ''),
            'distrito'         => (string) ($limpio['distrito'] ?? ''),
            'telefono'         => (string) ($limpio['telefono'] ?? ''),
            'telefono_alterno' => (string) ($limpio['telefono_alterno'] ?? ''),
            'email'            => (string) ($limpio['email'] ?? ''),
            'fecha_ingreso'    => (string) ($limpio['fecha_ingreso'] ?? ''),
            'categoria_id'     => (int) ($limpio['categoria_id'] ?? 0),
            'estado'           => (string) $limpio['estado'],
            'observaciones'    => (string) ($limpio['observaciones'] ?? ''),
            'creado_por'       => Auth::id(),
            'actualizado_por'  => Auth::id(),
        ];
    }

    private function fechaValida(string $fecha): bool
    {
        $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $valor instanceof DateTimeImmutable && $valor->format('Y-m-d') === $fecha;
    }

    /**
     * ¿El error corresponde a una clave única duplicada? (MySQL/MariaDB 1062).
     */
    private function esDuplicado(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return is_array($errorInfo) && (int) ($errorInfo[1] ?? 0) === 1062;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtener(int $id): array
    {
        $socio = Socio::findConCategoria($id);

        if ($socio === null) {
            abort(404, 'El socio indicado no existe.');
        }

        return $socio;
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
