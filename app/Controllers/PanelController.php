<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\View;
use App\Models\Instalacion;
use App\Models\Movimiento;
use App\Models\Permiso;
use App\Models\Reserva;
use App\Models\Socio;
use App\Models\SolicitudAlquiler;
use App\Models\Usuario;

/**
 * Panel principal del área administrativa.
 *
 * Los indicadores se muestran según los permisos del usuario: quien no puede
 * ver caja no ve cifras de caja, aunque conozca la URL del panel.
 */
final class PanelController
{
    public function index(Request $request): void
    {
        $datos = [
            'titulo'               => 'Panel',
            'dbDisponible'         => Database::isAvailable(),
            'indicadores'          => [],
            'solicitudesRecientes' => [],
            'proximasReservas'     => [],
            'ultimosMovimientos'   => [],
            'avisos'               => [],
            'pasos'                => [],
        ];

        if (!$datos['dbDisponible']) {
            $datos['avisos'][] = 'La base de datos no está disponible. Los indicadores se mostrarán cuando se restablezca la conexión.';
        } else {
            try {
                $this->cargarIndicadores($datos);
            } catch (\Throwable $exception) {
                Logger::error('No se pudieron cargar los indicadores del panel', ['error' => $exception->getMessage()]);
                $datos['avisos'][] = 'No fue posible calcular todos los indicadores. Revise el registro de eventos del sistema.';
            }

            try {
                if (Permiso::contar() === 0) {
                    $datos['avisos'][] = 'La tabla de permisos está vacía. Importe database/02_datos_iniciales.sql para habilitar los módulos.';
                }
            } catch (\Throwable) {
                // Si la tabla no existe, el aviso de instalación incompleta ya es evidente.
            }
        }

        View::display('panel/index', $datos);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function cargarIndicadores(array &$datos): void
    {
        if (Auth::can('alquileres.ver') || Auth::can('alquileres.gestionar')) {
            $pendientes = SolicitudAlquiler::contarPendientes();

            $datos['indicadores'][] = [
                'titulo'  => 'Solicitudes por revisar',
                'valor'   => (string) $pendientes,
                'detalle' => 'Recibidas desde el portal público',
                'icono'   => 'alquileres',
                'url'     => url('/solicitudes'),
                'tono'    => $pendientes > 0 ? 'atencion' : 'neutro',
            ];

            $datos['indicadores'][] = [
                'titulo'  => 'Reservas próximas',
                'valor'   => (string) Reserva::contarProximas(),
                'detalle' => 'Desde hoy en adelante',
                'icono'   => 'calendario',
                'url'     => null,
                'tono'    => 'neutro',
            ];

            $datos['solicitudesRecientes'] = SolicitudAlquiler::ultimas(5);
            $datos['proximasReservas'] = Reserva::proximas(5);
        }

        if (Auth::can('socios.ver')) {
            $datos['indicadores'][] = [
                'titulo'  => 'Socios activos',
                'valor'   => (string) Socio::contarActivos(),
                'detalle' => Socio::contarPendientes() . ' con registro pendiente',
                'icono'   => 'socios',
                'url'     => url('/socios'),
                'tono'    => 'neutro',
            ];
        }

        if (Auth::can('caja.ver')) {
            $resumen = Movimiento::resumenPeriodo(date('Y-m-01'), date('Y-m-t'));

            $datos['indicadores'][] = [
                'titulo'  => 'Saldo del mes',
                'valor'   => dinero($resumen['saldo']),
                'detalle' => 'Ingresos ' . dinero($resumen['ingresos']) . ' · Egresos ' . dinero($resumen['egresos']),
                'icono'   => 'caja',
                'url'     => null,
                'tono'    => $resumen['saldo'] < 0 ? 'atencion' : 'neutro',
            ];

            $datos['ultimosMovimientos'] = Movimiento::ultimos(5);
        }

        if (Auth::can('usuarios.ver')) {
            $datos['indicadores'][] = [
                'titulo'  => 'Usuarios activos',
                'valor'   => (string) Usuario::contarActivos(),
                'detalle' => 'de ' . Usuario::contar() . ' registrados',
                'icono'   => 'usuario',
                'url'     => url('/usuarios'),
                'tono'    => 'neutro',
            ];
        }

        // Primeros pasos: solo se muestran a quien puede resolverlos.
        if (Auth::can('usuarios.gestionar') && Usuario::contar() <= 1) {
            $datos['pasos'][] = [
                'titulo' => 'Cree las cuentas del personal',
                'detalle' => 'Cada persona debe usar su propia cuenta, con el rol que le corresponde.',
                'url'    => url('/usuarios/nuevo'),
                'accion' => 'Crear usuario',
            ];
        }

        if (Auth::can('alquileres.tarifas') && Instalacion::contarActivas() === 0) {
            $datos['pasos'][] = [
                'titulo' => 'Publique los ambientes y sus tarifas',
                'detalle' => 'Los ambientes sin tarifa vigente no se muestran en el portal público. La administración de ambientes se habilita en la Fase 4.',
                'url'    => null,
                'accion' => null,
            ];
        }

        if (Auth::can('alquileres.ver') && SolicitudAlquiler::contarPendientes() > 0) {
            $datos['pasos'][] = [
                'titulo' => 'Revise las solicitudes de alquiler',
                'detalle' => 'Hay solicitudes esperando respuesta. Ninguna reserva se confirma automáticamente.',
                'url'    => url('/solicitudes?estado=pendiente'),
                'accion' => 'Revisar solicitudes',
            ];
        }
    }
}
