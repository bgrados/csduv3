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
use App\Models\Candidato;
use App\Models\Configuracion;
use App\Models\DirectivaCargo;
use App\Models\ListaElectoral;
use App\Models\ProcesoElectoral;
use App\Models\ResultadoElectoral;
use App\Models\Socio;
use Throwable;

/**
 * Procesos electorales: listas, candidatos y resultados.
 *
 * Es un registro **administrativo** de lo que la organización declara: no es
 * un sistema de votación electrónica ni certifica identidades.
 *
 * Los permisos (`elecciones.ver`, `elecciones.gestionar`) se comprueban en
 * app/routes.php.
 */
final class EleccionesController
{
    public function index(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('elecciones/index', [
            'titulo'   => 'Elecciones',
            'procesos' => ProcesoElectoral::todos(),
            'estados'  => ProcesoElectoral::ESTADOS,
        ]);
    }

    public function nuevo(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        View::display('elecciones/form', [
            'titulo'   => 'Nuevo proceso electoral',
            'proceso'  => null,
            'modo'     => 'crear',
            'estados'  => ProcesoElectoral::ESTADOS,
            'anios'    => $this->aniosMandato(),
        ]);
    }

    public function crear(Request $request): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $datos = $this->datosProceso($request);
        [$limpio, $errores] = $this->validarProceso($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/elecciones/nuevo'));
        }

        try {
            $id = ProcesoElectoral::crear($this->registroProceso($limpio));
        } catch (Throwable $exception) {
            Logger::error('Error al crear un proceso electoral', ['error' => $exception->getMessage()]);
            remember_old_input($datos);
            Session::flash('error', 'No se pudo crear el proceso electoral.');
            Response::redirect(url('/elecciones/nuevo'));
        }

        Audit::log(Auth::id(), 'crear_proceso_electoral', 'proceso_electoral', $id, 'Proceso electoral creado: ' . (string) $limpio['nombre']);

        Session::flash('exito', 'El proceso electoral quedó creado.');
        Response::redirect(url('/elecciones/' . $id));
    }

    public function ver(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $proceso = $this->obtener((int) $id);

        View::display('elecciones/ver', [
            'titulo'     => 'Proceso electoral',
            'proceso'    => $proceso,
            'listas'     => ListaElectoral::listarPorProceso((int) $proceso['id']),
            'candidatos' => Candidato::listarPorProceso((int) $proceso['id']),
            'resultados' => ResultadoElectoral::listarPorProceso((int) $proceso['id']),
            'estados'    => ProcesoElectoral::ESTADOS,
            'estadosLista' => ListaElectoral::ESTADOS,
            'cargos'     => DirectivaCargo::activos(),
        ]);
    }

    public function editar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $proceso = $this->obtener((int) $id);

        View::display('elecciones/form', [
            'titulo'  => 'Editar proceso electoral',
            'proceso' => $proceso,
            'modo'    => 'editar',
            'estados' => ProcesoElectoral::ESTADOS,
            'anios'   => $this->aniosMandato(),
        ]);
    }

    public function actualizar(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $proceso = $this->obtener((int) $id);
        $datos = $this->datosProceso($request);
        [$limpio, $errores] = $this->validarProceso($datos);

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/elecciones/' . (int) $proceso['id'] . '/editar'));
        }

        ProcesoElectoral::actualizar((int) $proceso['id'], $this->registroProceso($limpio));

        Audit::log(Auth::id(), 'editar_proceso_electoral', 'proceso_electoral', (int) $proceso['id'], 'Proceso electoral actualizado: ' . (string) $limpio['nombre']);

        Session::flash('exito', 'El proceso electoral se actualizó.');
        Response::redirect(url('/elecciones/' . (int) $proceso['id']));
    }

    public function cambiarEstado(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $proceso = $this->obtener((int) $id);
        $estado = $request->string('estado');

        if (!isset(ProcesoElectoral::ESTADOS[$estado])) {
            Session::flash('error', 'El estado indicado no es válido.');
            Response::redirect(url('/elecciones/' . (int) $proceso['id']));
        }

        ProcesoElectoral::cambiarEstado((int) $proceso['id'], $estado);

        Audit::log(
            Auth::id(),
            'cambiar_estado_proceso_electoral',
            'proceso_electoral',
            (int) $proceso['id'],
            'Proceso electoral pasó a ' . ProcesoElectoral::ESTADOS[$estado]
        );

        Session::flash('exito', 'El estado del proceso se actualizó.');
        Response::redirect(url('/elecciones/' . (int) $proceso['id']));
    }

    public function guardarLista(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $proceso = $this->obtener((int) $id);
        $procesoId = (int) $proceso['id'];

        $datos = $request->only(['nombre', 'lema', 'orden', 'observaciones']);
        $validador = Validator::make($datos, [
            'nombre'        => 'required|string|min:2|max:120',
            'lema'          => 'nullable|string|max:200',
            'orden'         => 'nullable|integer|min:1|max:999',
            'observaciones' => 'nullable|string|max:300',
        ], [
            'nombre'        => 'nombre de la lista',
            'lema'          => 'lema',
            'orden'         => 'orden',
            'observaciones' => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes() && ListaElectoral::nombreExiste($procesoId, (string) $limpio['nombre'])) {
            $errores[] = 'Ya existe una lista con ese nombre en el proceso.';
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/elecciones/' . $procesoId));
        }

        ListaElectoral::crear([
            'proceso_id'    => $procesoId,
            'nombre'        => (string) $limpio['nombre'],
            'lema'          => (string) ($limpio['lema'] ?? ''),
            'orden'         => (int) ($limpio['orden'] ?? 1),
            'estado'        => 'inscrita',
            'observaciones' => (string) ($limpio['observaciones'] ?? ''),
        ]);

        Audit::log(Auth::id(), 'crear_lista_electoral', 'lista_electoral', null, 'Lista inscrita: ' . (string) $limpio['nombre']);

        Session::flash('exito', 'La lista quedó inscrita.');
        Response::redirect(url('/elecciones/' . $procesoId));
    }

    public function estadoLista(Request $request, string $id, string $listaId): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $proceso = $this->obtener((int) $id);
        $procesoId = (int) $proceso['id'];
        $lista = $this->obtenerLista((int) $listaId, $procesoId);

        $estado = $request->string('estado');

        if (!isset(ListaElectoral::ESTADOS[$estado])) {
            Session::flash('error', 'El estado indicado no es válido.');
            Response::redirect(url('/elecciones/' . $procesoId));
        }

        ListaElectoral::cambiarEstado((int) $lista['id'], $estado);

        Audit::log(
            Auth::id(),
            'cambiar_estado_lista_electoral',
            'lista_electoral',
            (int) $lista['id'],
            'Lista pasó a ' . ListaElectoral::ESTADOS[$estado]
        );

        Session::flash('exito', 'El estado de la lista se actualizó.');
        Response::redirect(url('/elecciones/' . $procesoId));
    }

    public function guardarCandidato(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $proceso = $this->obtener((int) $id);
        $procesoId = (int) $proceso['id'];

        $datos = $request->only([
            'lista_id',
            'socio_codigo',
            'nombres',
            'apellidos',
            'documento',
            'cargo_id',
            'orden',
        ]);
        $datos['es_cabeza_lista'] = $request->boolean('es_cabeza_lista') ? '1' : '0';

        $validador = Validator::make($datos, [
            'lista_id'        => 'nullable|integer|exists:listas_electorales,id',
            'socio_codigo'    => 'nullable|string|max:30',
            'nombres'         => 'required|string|min:2|max:120',
            'apellidos'       => 'required|string|min:2|max:120',
            'documento'       => 'nullable|string|max:30',
            'cargo_id'        => 'nullable|integer|exists:directiva_cargos,id',
            'orden'           => 'nullable|integer|min:1|max:999',
            'es_cabeza_lista' => 'required|in:0,1',
        ], [
            'lista_id'        => 'lista',
            'socio_codigo'    => 'código de socio',
            'nombres'         => 'nombres',
            'apellidos'       => 'apellidos',
            'documento'       => 'documento',
            'cargo_id'        => 'cargo',
            'orden'           => 'orden',
            'es_cabeza_lista' => 'cabeza de lista',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $codigo = trim((string) ($limpio['socio_codigo'] ?? ''));

            if ($codigo !== '' && Socio::findPorCodigo($codigo) === null) {
                $errores[] = 'No existe un socio con ese código.';
            }
        }

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/elecciones/' . $procesoId));
        }

        $socio = Socio::findPorCodigo(trim((string) ($limpio['socio_codigo'] ?? '')));

        Candidato::crear([
            'proceso_id'      => $procesoId,
            'lista_id'        => (int) ($limpio['lista_id'] ?? 0),
            'socio_id'        => $socio === null ? 0 : (int) $socio['id'],
            'nombres'         => (string) $limpio['nombres'],
            'apellidos'       => (string) $limpio['apellidos'],
            'documento'       => (string) ($limpio['documento'] ?? ''),
            'cargo_id'        => (int) ($limpio['cargo_id'] ?? 0),
            'orden'           => (int) ($limpio['orden'] ?? 1),
            'es_cabeza_lista' => (int) ($limpio['es_cabeza_lista'] ?? 0),
            'observaciones'   => '',
        ]);

        Audit::log(
            Auth::id(),
            'crear_candidato',
            'candidato',
            null,
            'Candidato inscrito: ' . trim((string) $limpio['nombres'] . ' ' . (string) $limpio['apellidos'])
        );

        Session::flash('exito', 'El candidato quedó inscrito.');
        Response::redirect(url('/elecciones/' . $procesoId));
    }

    public function eliminarCandidato(Request $request, string $id, string $candidatoId): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $proceso = $this->obtener((int) $id);
        $procesoId = (int) $proceso['id'];
        $candidato = Candidato::find((int) $candidatoId);

        if ($candidato === null || (int) $candidato['proceso_id'] !== $procesoId) {
            Session::flash('error', 'El candidato indicado no existe en este proceso.');
            Response::redirect(url('/elecciones/' . $procesoId));
        }

        Candidato::eliminar((int) $candidato['id']);

        Audit::log(Auth::id(), 'eliminar_candidato', 'candidato', (int) $candidato['id'], 'Candidato retirado: ' . (string) $candidato['nombres'] . ' ' . (string) $candidato['apellidos']);

        Session::flash('exito', 'El candidato se retiró de la lista.');
        Response::redirect(url('/elecciones/' . $procesoId));
    }

    public function guardarResultado(Request $request, string $id): void
    {
        if (!$this->baseDisponible()) {
            return;
        }

        $proceso = $this->obtener((int) $id);
        $procesoId = (int) $proceso['id'];

        $datos = $request->only([
            'lista_id',
            'votos',
            'votos_nulos',
            'votos_blancos',
            'es_ganadora',
            'observaciones',
        ]);

        $validador = Validator::make($datos, [
            'lista_id'      => 'required|integer|exists:listas_electorales,id',
            'votos'         => 'required|integer|min:0|max:1000000',
            'votos_nulos'   => 'nullable|integer|min:0|max:1000000',
            'votos_blancos' => 'nullable|integer|min:0|max:1000000',
            'es_ganadora'   => 'required|in:0,1',
            'observaciones' => 'nullable|string|max:1000',
        ], [
            'lista_id'      => 'lista',
            'votos'         => 'votos',
            'votos_nulos'   => 'votos nulos',
            'votos_blancos' => 'votos en blanco',
            'es_ganadora'   => 'lista ganadora',
            'observaciones' => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($errores !== []) {
            remember_old_input($datos);
            Session::flash('errores', $errores);
            Response::redirect(url('/elecciones/' . $procesoId));
        }

        $listaId = (int) $limpio['lista_id'];
        $lista = $this->obtenerLista($listaId, $procesoId);

        if ((int) $limpio['es_ganadora'] === 1) {
            ResultadoElectoral::guardar($procesoId, $listaId, $limpio, (int) Auth::id());

            // Solo puede haber una lista ganadora: se descabecen las demás.
            foreach (ListaElectoral::listarPorProceso($procesoId) as $otra) {
                if ((int) $otra['id'] === $listaId) {
                    continue;
                }

                ResultadoElectoral::guardar(
                    $procesoId,
                    (int) $otra['id'],
                    [
                        'votos'         => (int) ($otra['votos'] ?? 0),
                        'votos_nulos'   => (int) ($otra['votos_nulos'] ?? 0),
                        'votos_blancos' => (int) ($otra['votos_blancos'] ?? 0),
                        'es_ganadora'   => 0,
                        'observaciones' => (string) ($otra['resultado_observaciones'] ?? ''),
                    ],
                    (int) Auth::id()
                );
            }

            ListaElectoral::cambiarEstado((int) $lista['id'], 'ganadora');
        } else {
            ResultadoElectoral::guardar($procesoId, $listaId, $limpio, (int) Auth::id());
        }

        Audit::log(Auth::id(), 'registrar_resultado_electoral', 'proceso_electoral', $procesoId, 'Resultado electoral registrado');

        Session::flash('exito', 'El resultado quedó registrado.');
        Response::redirect(url('/elecciones/' . $procesoId));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosProceso(Request $request): array
    {
        return $request->only([
            'nombre',
            'descripcion',
            'fecha_convocatoria',
            'fecha_eleccion',
            'periodo_inicio',
            'periodo_fin',
            'anios_mandato',
            'estado',
            'reglamento',
            'observaciones',
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validarProceso(array $datos): array
    {
        $validador = Validator::make($datos, [
            'nombre'             => 'required|string|min:3|max:150',
            'descripcion'        => 'nullable|string|max:1000',
            'fecha_convocatoria' => 'nullable|date',
            'fecha_eleccion'     => 'nullable|date',
            'periodo_inicio'     => 'nullable|date',
            'periodo_fin'        => 'nullable|date',
            'anios_mandato'      => 'nullable|integer|min:1|max:10',
            'estado'             => 'required|in:planificado,convocado,en_proceso,finalizado,anulado',
            'reglamento'         => 'nullable|string|max:5000',
            'observaciones'      => 'nullable|string|max:2000',
        ], [
            'nombre'             => 'nombre del proceso',
            'descripcion'        => 'descripción',
            'fecha_convocatoria' => 'fecha de convocatoria',
            'fecha_eleccion'     => 'fecha de elección',
            'periodo_inicio'     => 'inicio del mandato',
            'periodo_fin'        => 'fin del mandato',
            'anios_mandato'      => 'años de mandato',
            'estado'             => 'estado',
            'reglamento'         => 'reglamento',
            'observaciones'      => 'observaciones',
        ]);

        $errores = array_values($validador->errors());
        $limpio = $validador->validated();

        if ($validador->passes()) {
            $convocatoria = (string) ($limpio['fecha_convocatoria'] ?? '');
            $eleccion = (string) ($limpio['fecha_eleccion'] ?? '');

            if ($convocatoria !== '' && $eleccion !== '' && $eleccion < $convocatoria) {
                $errores[] = 'La fecha de elección no puede ser anterior a la de convocatoria.';
            }

            $inicio = (string) ($limpio['periodo_inicio'] ?? '');
            $fin = (string) ($limpio['periodo_fin'] ?? '');

            if ($inicio !== '' && $fin !== '' && $fin < $inicio) {
                $errores[] = 'El fin del mandato no puede ser anterior a su inicio.';
            }
        }

        return [$limpio, $errores];
    }

    /**
     * @param array<string, mixed> $limpio
     * @return array<string, mixed>
     */
    private function registroProceso(array $limpio): array
    {
        return [
            'nombre'             => (string) $limpio['nombre'],
            'descripcion'        => (string) ($limpio['descripcion'] ?? ''),
            'fecha_convocatoria' => (string) ($limpio['fecha_convocatoria'] ?? ''),
            'fecha_eleccion'     => (string) ($limpio['fecha_eleccion'] ?? ''),
            'periodo_inicio'     => (string) ($limpio['periodo_inicio'] ?? ''),
            'periodo_fin'        => (string) ($limpio['periodo_fin'] ?? ''),
            'anios_mandato'      => (int) ($limpio['anios_mandato'] ?? $this->aniosMandato()),
            'estado'             => (string) $limpio['estado'],
            'reglamento'         => (string) ($limpio['reglamento'] ?? ''),
            'observaciones'      => (string) ($limpio['observaciones'] ?? ''),
        ];
    }

    private function aniosMandato(): int
    {
        $configuracion = Configuracion::mapa();
        $anios = (int) ($configuracion['anios_mandato'] ?? 4);

        return $anios > 0 ? $anios : 4;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtener(int $id): array
    {
        $proceso = ProcesoElectoral::find($id);

        if ($proceso === null) {
            abort(404, 'El proceso electoral indicado no existe.');
        }

        return $proceso;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerLista(int $listaId, int $procesoId): array
    {
        $lista = ListaElectoral::find($listaId);

        if ($lista === null || (int) $lista['proceso_id'] !== $procesoId) {
            abort(404, 'La lista indicada no existe en este proceso.');
        }

        return $lista;
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
