<?php

declare(strict_types=1);

use App\Controllers\AmbientesController;
use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\CajaController;
use App\Controllers\DeportesController;
use App\Controllers\DiagnosticoController;
use App\Controllers\DirectivaController;
use App\Controllers\EleccionesController;
use App\Controllers\HomeController;
use App\Controllers\PanelController;
use App\Controllers\PortalAlquilerController;
use App\Controllers\PortalTorneosController;
use App\Controllers\RolesController;
use App\Controllers\SociosController;
use App\Controllers\SolicitudesController;
use App\Controllers\TarifasController;
use App\Controllers\TorneosController;
use App\Controllers\UsuariosController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\PermissionMiddleware;

/*
 |--------------------------------------------------------------------------
 | Rutas de la aplicación
 |--------------------------------------------------------------------------
 | Formato:  $router->get('/ruta', [Controlador::class, 'metodo'], [middleware]);
 | Parámetros dinámicos: '/socios/{id}/editar'
 |
 | El middleware CSRF es global: ninguna petición POST/PUT/PATCH/DELETE se
 | procesa sin un token válido. Los permisos se comprueban siempre aquí, antes
 | de ejecutar el controlador.
 |
 | @var Router $router
 */

$router->middleware([
    [CsrfMiddleware::class, 'handle'],
]);

// ===========================================================================
//  Portal público (sin sesión)
// ===========================================================================

$router->get('/', [HomeController::class, 'index']);

$router->get('/alquiler', [PortalAlquilerController::class, 'index']);
$router->get('/alquiler/disponibilidad', [PortalAlquilerController::class, 'disponibilidad']);
$router->get('/alquiler/solicitud', [PortalAlquilerController::class, 'formulario']);
$router->post('/alquiler/solicitud', [PortalAlquilerController::class, 'registrar']);
$router->get('/alquiler/solicitud/recibida/{codigo}', [PortalAlquilerController::class, 'recibida']);
$router->get('/alquiler/ambiente/{id}', [PortalAlquilerController::class, 'ambiente']);

$router->get('/torneos', [PortalTorneosController::class, 'index']);
$router->get('/torneos/{id}', [PortalTorneosController::class, 'ver']);

// ===========================================================================
//  Acceso del personal
// ===========================================================================

$router->get('/ingresar', [AuthController::class, 'formulario'], [
    [GuestMiddleware::class, 'handle'],
]);

$router->post('/ingresar', [AuthController::class, 'ingresar'], [
    [GuestMiddleware::class, 'handle'],
]);

$router->post('/salir', [AuthController::class, 'salir'], [
    [AuthMiddleware::class, 'handle'],
]);

$router->get('/perfil', [AuthController::class, 'perfil'], [
    [AuthMiddleware::class, 'handle'],
]);

$router->post('/perfil', [AuthController::class, 'actualizarPerfil'], [
    [AuthMiddleware::class, 'handle'],
]);

// ===========================================================================
//  Panel administrativo
// ===========================================================================

$router->get('/panel', [PanelController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('panel.ver'),
]);

// --- Usuarios --------------------------------------------------------------

$router->get('/usuarios', [UsuariosController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('usuarios.ver'),
]);

$router->get('/usuarios/nuevo', [UsuariosController::class, 'nuevo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('usuarios.gestionar'),
]);

$router->post('/usuarios', [UsuariosController::class, 'crear'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('usuarios.gestionar'),
]);

$router->get('/usuarios/{id}/editar', [UsuariosController::class, 'editar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('usuarios.gestionar'),
]);

$router->post('/usuarios/{id}', [UsuariosController::class, 'actualizar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('usuarios.gestionar'),
]);

$router->post('/usuarios/{id}/estado', [UsuariosController::class, 'cambiarEstado'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('usuarios.gestionar'),
]);

$router->post('/usuarios/{id}/password', [UsuariosController::class, 'restablecerPassword'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('usuarios.gestionar'),
]);

// --- Roles y permisos ------------------------------------------------------

$router->get('/roles', [RolesController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('roles.gestionar'),
]);

$router->post('/roles/permisos', [RolesController::class, 'actualizarPermisos'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('roles.gestionar'),
]);

// --- Socios ----------------------------------------------------------------
//
// Las rutas estáticas se declaran antes de '/socios/{id}' para que «nuevo» y
// «categorias» no sean interpretadas como identificadores.

$router->get('/socios', [SociosController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('socios.ver'),
]);

$router->get('/socios/nuevo', [SociosController::class, 'nuevo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('socios.crear'),
]);

$router->post('/socios', [SociosController::class, 'crear'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('socios.crear'),
]);

$router->get('/socios/categorias', [SociosController::class, 'categorias'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('socios.ver'),
]);

$router->post('/socios/categorias', [SociosController::class, 'crearCategoria'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('socios.editar'),
]);

$router->post('/socios/categorias/{id}/estado', [SociosController::class, 'cambiarCategoriaActivo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('socios.editar'),
]);

$router->get('/socios/{id}', [SociosController::class, 'ver'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('socios.ver'),
]);

$router->get('/socios/{id}/editar', [SociosController::class, 'editar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('socios.editar'),
]);

$router->post('/socios/{id}', [SociosController::class, 'actualizar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('socios.editar'),
]);

$router->post('/socios/{id}/baja', [SociosController::class, 'baja'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('socios.baja'),
]);

// --- Solicitudes del portal público ---------------------------------------

$router->get('/solicitudes', [SolicitudesController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiereAlguno(['alquileres.ver', 'alquileres.gestionar']),
]);

$router->get('/solicitudes/{id}', [SolicitudesController::class, 'ver'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiereAlguno(['alquileres.ver', 'alquileres.gestionar']),
]);

$router->post('/solicitudes/{id}/en-revision', [SolicitudesController::class, 'enRevision'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.gestionar'),
]);

$router->post('/solicitudes/{id}/atender', [SolicitudesController::class, 'atender'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.gestionar'),
]);

$router->post('/solicitudes/{id}/rechazar', [SolicitudesController::class, 'rechazar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.gestionar'),
]);

// --- Ambientes y tarifas ---------------------------------------------------

$router->get('/ambientes', [AmbientesController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiereAlguno(['alquileres.ver', 'alquileres.tarifas']),
]);

$router->get('/ambientes/nuevo', [AmbientesController::class, 'nuevo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.tarifas'),
]);

$router->post('/ambientes', [AmbientesController::class, 'crear'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.tarifas'),
]);

$router->get('/ambientes/{id}/editar', [AmbientesController::class, 'editar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.tarifas'),
]);

$router->post('/ambientes/{id}', [AmbientesController::class, 'actualizar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.tarifas'),
]);

$router->post('/ambientes/{id}/estado', [AmbientesController::class, 'cambiarEstado'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.tarifas'),
]);

$router->get('/ambientes/{id}/tarifas', [TarifasController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiereAlguno(['alquileres.ver', 'alquileres.tarifas']),
]);

$router->get('/ambientes/{id}/tarifas/nueva', [TarifasController::class, 'nuevo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.tarifas'),
]);

$router->post('/ambientes/{id}/tarifas', [TarifasController::class, 'crear'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.tarifas'),
]);

$router->get('/ambientes/{id}/tarifas/{tarifaId}/editar', [TarifasController::class, 'editar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.tarifas'),
]);

$router->post('/ambientes/{id}/tarifas/{tarifaId}', [TarifasController::class, 'actualizar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.tarifas'),
]);

$router->post('/ambientes/{id}/tarifas/{tarifaId}/estado', [TarifasController::class, 'cambiarEstado'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('alquileres.tarifas'),
]);

// --- Elecciones -------------------------------------------------------------

$router->get('/elecciones', [EleccionesController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.ver'),
]);

$router->get('/elecciones/nuevo', [EleccionesController::class, 'nuevo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.gestionar'),
]);

$router->post('/elecciones', [EleccionesController::class, 'crear'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.gestionar'),
]);

$router->get('/elecciones/{id}', [EleccionesController::class, 'ver'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.ver'),
]);

$router->get('/elecciones/{id}/editar', [EleccionesController::class, 'editar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.gestionar'),
]);

$router->post('/elecciones/{id}', [EleccionesController::class, 'actualizar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.gestionar'),
]);

$router->post('/elecciones/{id}/estado', [EleccionesController::class, 'cambiarEstado'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.gestionar'),
]);

$router->post('/elecciones/{id}/listas', [EleccionesController::class, 'guardarLista'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.gestionar'),
]);

$router->post('/elecciones/{id}/listas/{listaId}/estado', [EleccionesController::class, 'estadoLista'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.gestionar'),
]);

$router->post('/elecciones/{id}/candidatos', [EleccionesController::class, 'guardarCandidato'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.gestionar'),
]);

$router->post('/elecciones/{id}/candidatos/{candidatoId}/eliminar', [EleccionesController::class, 'eliminarCandidato'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.gestionar'),
]);

$router->post('/elecciones/{id}/resultados', [EleccionesController::class, 'guardarResultado'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('elecciones.gestionar'),
]);

// --- Junta directiva --------------------------------------------------------

$router->get('/directiva', [DirectivaController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.ver'),
]);

$router->get('/directiva/cargos', [DirectivaController::class, 'cargos'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.ver'),
]);

$router->post('/directiva/cargos', [DirectivaController::class, 'guardarCargo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

$router->post('/directiva/cargos/{id}', [DirectivaController::class, 'editarCargo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

$router->post('/directiva/cargos/{id}/estado', [DirectivaController::class, 'estadoCargo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

$router->get('/directiva/nuevo', [DirectivaController::class, 'nuevo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

$router->post('/directiva', [DirectivaController::class, 'crear'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

$router->get('/directiva/{id}', [DirectivaController::class, 'ver'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.ver'),
]);

$router->get('/directiva/{id}/editar', [DirectivaController::class, 'editar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

$router->post('/directiva/{id}', [DirectivaController::class, 'actualizar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

$router->post('/directiva/{id}/estado', [DirectivaController::class, 'cambiarEstado'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

$router->post('/directiva/{id}/miembros', [DirectivaController::class, 'agregarMiembro'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

$router->get('/directiva/{id}/miembros/{miembroId}/editar', [DirectivaController::class, 'editarMiembro'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

$router->post('/directiva/{id}/miembros/{miembroId}', [DirectivaController::class, 'actualizarMiembro'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

$router->post('/directiva/{id}/miembros/{miembroId}/estado', [DirectivaController::class, 'estadoMiembro'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('directiva.gestionar'),
]);

// --- Caja -------------------------------------------------------------------

$router->get('/caja', [CajaController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.ver'),
]);

$router->get('/caja/nuevo', [CajaController::class, 'nuevo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.registrar'),
]);

$router->post('/caja', [CajaController::class, 'crear'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.registrar'),
]);

$router->get('/caja/configuracion', [CajaController::class, 'configuracion'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.ver'),
]);

$router->post('/caja/categorias', [CajaController::class, 'guardarCategoria'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.registrar'),
]);

$router->post('/caja/categorias/{id}/estado', [CajaController::class, 'estadoCategoria'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.registrar'),
]);

$router->post('/caja/medios-pago', [CajaController::class, 'guardarMedio'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.registrar'),
]);

$router->post('/caja/medios-pago/{id}/estado', [CajaController::class, 'estadoMedio'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.registrar'),
]);

$router->post('/caja/periodos', [CajaController::class, 'guardarPeriodo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.registrar'),
]);

$router->post('/caja/periodos/{id}/estado', [CajaController::class, 'estadoPeriodo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.registrar'),
]);

$router->get('/caja/reportes', [CajaController::class, 'reportes'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.reportes'),
]);

$router->get('/caja/{id}/editar', [CajaController::class, 'editar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.registrar'),
]);

$router->post('/caja/{id}', [CajaController::class, 'actualizar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.registrar'),
]);

$router->post('/caja/{id}/anular', [CajaController::class, 'anular'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('caja.anular'),
]);

// --- Deportes y torneos ----------------------------------------------------

$router->get('/deportes', [DeportesController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.ver'),
]);

$router->get('/deportes/torneos/nuevo', [TorneosController::class, 'nuevo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.gestionar'),
]);

$router->post('/deportes/torneos', [TorneosController::class, 'crear'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.gestionar'),
]);

$router->get('/deportes/torneos/{id}', [TorneosController::class, 'ver'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.ver'),
]);

$router->get('/deportes/torneos/{id}/editar', [TorneosController::class, 'editar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.gestionar'),
]);

$router->post('/deportes/torneos/{id}', [TorneosController::class, 'actualizar'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.gestionar'),
]);

$router->post('/deportes/torneos/{id}/estado', [TorneosController::class, 'cambiarEstado'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.gestionar'),
]);

$router->post('/deportes/torneos/{id}/equipos', [TorneosController::class, 'agregarEquipo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.gestionar'),
]);

$router->post('/deportes/torneos/{id}/equipos/{equipoId}/eliminar', [TorneosController::class, 'eliminarEquipo'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.gestionar'),
]);

$router->post('/deportes/torneos/{id}/fixture', [TorneosController::class, 'generarFixture'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.gestionar'),
]);

$router->get('/deportes/encuentros/{id}/editar', [TorneosController::class, 'editarEncuentro'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.gestionar'),
]);

$router->post('/deportes/encuentros/{id}', [TorneosController::class, 'actualizarEncuentro'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('deportes.gestionar'),
]);

// --- Auditoría -------------------------------------------------------------

$router->get('/auditoria', [AuditoriaController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
    PermissionMiddleware::requiere('auditoria.ver'),
]);

// ===========================================================================
//  Diagnóstico del sistema (solo entorno de desarrollo)
// ===========================================================================

$router->get('/diagnostico', [DiagnosticoController::class, 'index']);

// ===========================================================================
//  Sin coincidencia
// ===========================================================================

$router->notFound(static function (): void {
    abort(404, 'La página solicitada no existe o fue movida.');
});
