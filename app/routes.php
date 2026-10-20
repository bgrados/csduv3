<?php

declare(strict_types=1);

use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\DiagnosticoController;
use App\Controllers\HomeController;
use App\Controllers\PanelController;
use App\Controllers\PortalAlquilerController;
use App\Controllers\RolesController;
use App\Controllers\SociosController;
use App\Controllers\SolicitudesController;
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
