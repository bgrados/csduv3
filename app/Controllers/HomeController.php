<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\View;
use App\Models\Actividad;
use App\Models\Configuracion;
use App\Models\Encuentro;
use App\Models\Foto;
use App\Models\Instalacion;
use App\Models\Negocio;

/**
 * Portada pública del club, con formato de revista.
 *
 * La portada reúne: noticias con foto (galería), próximos eventos, agenda
 * deportiva, negocios de la unidad y los ambientes publicados.
 *
 * Si todavía no hay contenido real, se muestran **contenidos de ejemplo**
 * (marcados como «Ejemplo») para que el club pueda revisar el diseño. En
 * cuanto se carguen noticias, eventos o negocios reales, estos se sustituyen
 * automáticamente.
 */
final class HomeController
{
    /** Fotos de ejemplo incluidas en el repositorio, para la maqueta. */
    private const FOTOS_EJEMPLO = [
        ['archivo' => 'assets/img/demo/asamblea-socios.webp', 'titulo' => 'Asamblea general de socios', 'categoria' => 'reunion'],
        ['archivo' => 'assets/img/demo/campeonato-fulbito.webp', 'titulo' => 'Campeonato interno de fulbito', 'categoria' => 'campeonato'],
        ['archivo' => 'assets/img/demo/junta-directiva.webp', 'titulo' => 'Junta directiva del periodo', 'categoria' => 'directiva'],
        ['archivo' => 'assets/img/demo/encuentros-deportivos.webp', 'titulo' => 'Encuentros deportivos de la unidad', 'categoria' => 'deportiva'],
        ['archivo' => 'assets/img/demo/actividad-socios.webp', 'titulo' => 'Actividad de la comunidad', 'categoria' => 'socios'],
        ['archivo' => 'assets/img/demo/mantenimiento-instalaciones.webp', 'titulo' => 'Mantenimiento de las instalaciones', 'categoria' => 'instalacion'],
    ];

    public function index(Request $request): void
    {
        $dbDisponible = Database::isAvailable();
        $instalaciones = [];
        $configuracion = [];
        $noticias = [];
        $galeria = [];
        $negocios = [];
        $eventos = [];
        $partidos = [];

        if ($dbDisponible) {
            try {
                $instalaciones = Instalacion::activasConTarifas();
                $configuracion = Configuracion::mapa();
            } catch (\Throwable $exception) {
                Logger::warning('No se pudo cargar la portada pública', ['error' => $exception->getMessage()]);
                $dbDisponible = false;
            }
        }

        // Las secciones son independientes: si una falla, la portada sigue.
        $portada = $this->cargar(fn (): ?array => Foto::portada(), 'portada');
        $noticias = $portada === null ? [] : [$portada];


        $galeria = $this->cargar(fn (): array => Foto::listarVisibles(9), 'galería');

        if ($portada !== null) {
            $galeria = array_values(array_filter(
                $galeria,
                static fn (array $foto): bool => (int) $foto['id'] !== (int) $portada['id']
            ));
        }
        $negocios = $this->cargar(fn (): array => Negocio::listarVisibles(), 'negocios');
        $eventos = $this->cargar(fn (): array => Actividad::proximasPublicas(5), 'eventos');
        $partidos = $this->cargar(fn (): array => Encuentro::proximos(4), 'agenda deportiva');

        $conEjemplo = true;

        if ($dbDisponible) {
            try {
                $conEjemplo = Configuracion::valor('fotos_ejemplo', '1') === '1';
            } catch (\Throwable $exception) {
                $conEjemplo = true;
            }
        }

        if ($noticias === [] && $conEjemplo) {
            $noticias = $this->noticiasDeEjemplo(1);
            $galeria = $this->noticiasDeEjemplo(5);
        }

        if ($eventos === [] && $conEjemplo) {
            $eventos = $this->eventosDeEjemplo();
        }

        if ($partidos === [] && $conEjemplo) {
            $partidos = $this->partidosDeEjemplo();
        }

        if ($negocios === [] && $conEjemplo) {
            $negocios = $this->negociosDeEjemplo();
        }

        View::display('home/index', [
            'titulo'         => 'Inicio',
            'dbDisponible'   => $dbDisponible,
            'instalaciones'  => $instalaciones,
            'configuracion'  => $configuracion,
            'noticias'       => $noticias,
            'galeria'        => $galeria,
            'negocios'       => $negocios,
            'eventos'        => $eventos,
            'partidos'       => $partidos,
            'contenidoEjemplo' => $conEjemplo,
        ], 'layouts/publico');
    }

    /**
     * Ejecuta una consulta opcional sin romper la portada.
     *
     * @param callable(): array<int, array<string, mixed>> $consulta
     * @return array<int, array<string, mixed>>
     */
    private function cargar(callable $consulta, string $que): array
    {
        try {
            return $consulta();
        } catch (\Throwable $exception) {
            Logger::warning('No se pudo cargar una sección de la portada', [
                'seccion' => $que,
                'error'   => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function noticiasDeEjemplo(int $cantidad): array
    {
        $noticias = [];

        foreach (array_slice(self::FOTOS_EJEMPLO, 0, $cantidad) as $indice => $foto) {
            $noticias[] = [
                'id'        => 'ejemplo-' . $indice,
                'titulo'    => $foto['titulo'],
                'resumen'   => 'Contenido de ejemplo para revisar el diseño de la portada.',
                'categoria' => $foto['categoria'],
                'fecha_foto' => date('Y-m-d', strtotime('-' . (($indice + 1) * 8) . ' days')),
                'archivo'   => $foto['archivo'],
                'ejemplo'   => true,
            ];
        }

        return $noticias;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventosDeEjemplo(): array
    {
        return [
            [
                'id'            => 'ejemplo-evento-1',
                'titulo'        => 'Asamblea general de socios',
                'tipo'          => 'institucional',
                'fecha_inicio'  => date('Y-m-d 18:30:00', strtotime('+12 days')),
                'lugar'         => 'Salón principal del club',
                'ejemplo'       => true,
            ],
            [
                'id'            => 'ejemplo-evento-2',
                'titulo'        => 'Torneo relámpago de fulbito',
                'tipo'          => 'deportiva',
                'fecha_inicio'  => date('Y-m-d 17:00:00', strtotime('+19 days')),
                'lugar'         => 'Losa deportiva',
                'ejemplo'       => true,
            ],
            [
                'id'            => 'ejemplo-evento-3',
                'titulo'        => 'Reunión de junta directiva',
                'tipo'          => 'directiva',
                'fecha_inicio'  => date('Y-m-d 19:00:00', strtotime('+26 days')),
                'lugar'         => 'Sala de juntas',
                'ejemplo'       => true,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function partidosDeEjemplo(): array
    {
        return [
            [
                'id'                => 'ejemplo-partido-1',
                'fecha'             => date('Y-m-d', strtotime('+5 days')),
                'hora'              => '18:00:00',
                'torneo_nombre'     => 'Campeonato de fulbito',
                'local_nombre'      => 'Los Tigres',
                'visitante_nombre'  => 'Los Leones',
                'instalacion_nombre' => 'Losa deportiva',
                'ejemplo'           => true,
            ],
            [
                'id'                => 'ejemplo-partido-2',
                'fecha'             => date('Y-m-d', strtotime('+7 days')),
                'hora'              => '20:00:00',
                'torneo_nombre'     => 'Campeonato de fulbito',
                'local_nombre'      => 'Águilas',
                'visitante_nombre'  => 'Los Tigres',
                'instalacion_nombre' => 'Losa deportiva',
                'ejemplo'           => true,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function negociosDeEjemplo(): array
    {
        return [
            [
                'id'          => 'ejemplo-negocio-1',
                'nombre'      => 'Panadería San Martín',
                'categoria'   => 'comercio',
                'descripcion' => 'Pan y pastelería del barrio. Datos de ejemplo.',
                'telefono'    => '999 000 001',
                'web'         => 'https://uv3-platform.vercel.app/',
                'direccion'   => 'Av. Siempre Viva 742',
                'horario'     => 'Lunes a sábado, 7:00 a 19:00',
                'ejemplo'     => true,
            ],
            [
                'id'          => 'ejemplo-negocio-2',
                'nombre'      => 'Farmacia Vida',
                'categoria'   => 'salud',
                'descripcion' => 'Atención primaria y medicamentos. Datos de ejemplo.',
                'telefono'    => '999 000 002',
                'web'         => 'https://uv3-platform.vercel.app/',
                'direccion'   => 'Jirón Unión 120',
                'horario'     => 'Lunes a sábado, 8:00 a 20:00',
                'ejemplo'     => true,
            ],
            [
                'id'          => 'ejemplo-negocio-3',
                'nombre'      => 'Librería La Plaza',
                'categoria'   => 'comercio',
                'descripcion' => 'Libros y papelería. Datos de ejemplo.',
                'telefono'    => '999 000 003',
                'web'         => 'https://uv3-platform.vercel.app/',
                'direccion'   => 'Calle Las Begonias 15',
                'horario'     => 'Lunes a sábado, 9:00 a 18:00',
                'ejemplo'     => true,
            ],
        ];
    }
}
