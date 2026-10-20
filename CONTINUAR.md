# CONTINUAR — Estado del proyecto CSDUV3

Última actualización: **base de datos de producción recuperada y módulos de
ambientes y torneos implementados**. El portal vuelve a responder en
<https://csduv3.benweb.soy.pe> (portada, `/alquiler`, `/ingresar` y `/panel`
devuelven 200 y ya no hay aviso de mantenimiento). Sobre esa base se escribieron
los módulos **Ambientes y tarifas** (`/ambientes`) y **Deportes y torneos**
(`/deportes`, más el portal público `/torneos`).

> El código está verificado con **PHP 8.3.35 portable** (`php -l` sobre 102
> archivos de `app/`, prueba de enrutamiento y render real de las 11 vistas
> nuevas) y la verificación estática pasa **sin hallazgos**. Sigue **sin**
> ejecutarse contra MariaDB y **sin** sesión de administrador: no hay PHP ni
> MariaDB instalados de forma permanente en este equipo y no se dispone de
> credenciales de acceso al panel. Por eso los módulos nuevos **no se dan por
> terminados** hasta ejecutar [docs/PRUEBAS.md](docs/PRUEBAS.md) en el servidor.
>
> El commit **`f1de87d`** (ambientes, tarifas y torneos) ya está en `main` y el
> cron del hosting lo desplegó: `/torneos` responde 200 con el estado vacío,
> `/ambientes` y `/deportes` redirigen a `/ingresar` (las rutas existen y
> exigen sesión) y `/torneos/1` responde 404.
>
> **Pendiente que bloquea el uso real:**
> 1. Crear la cuenta de administrador en el hosting (`php tools/crear_admin.php`
>    desde el Terminal de cPanel; FileZilla no ejecuta PHP).
> 2. **Rotar la contraseña de la base de datos**, que estuvo expuesta en el
>    historial público de Git, y actualizar `.env`.
> 3. Ejecutar los casos `AM-*`, `TO-*`, `CX-*`, `DI-*`, `EL-*`, `CA-*` y `DO-*` de
   [docs/PRUEBAS.md](docs/PRUEBAS.md).

---

## 1. Resumen

| Dato | Valor |
| --- | --- |
| Proyecto | Sistema integral de gestión del Club Social Deportivo Unidad Vecinal N.º 3 |
| Carpeta local | Directorio raíz del proyecto |
| Repositorio | <https://github.com/bgrados/csduv3> (público, rama `main`) |
| Hosting | BanaHosting · Bana-Professional-Deluxe-SSD |
| URL de producción | <https://csduv3.benweb.soy.pe> |
| PHP | 8.3 |
| Base de datos | MariaDB de producción (cPanel); credenciales solo en `.env` |
| Prefijo de tablas | `csduv3_` |
| Tablas del esquema | 40 |
| Zona horaria | `America/Lima` |
| Moneda | Soles peruanos (S/) · fechas `dd/mm/aaaa` |

---

## 2. Qué se ha construido

### 2.1 Fase 1 — Infraestructura base

| Área | Contenido |
| --- | --- |
| Núcleo (`app/Core/`) | Autoloader PSR-4, `Env`, `Config`, `Database` (PDO), `Router`, `Request`, `Response`, `View`, `Session`, `Csrf`, `Validator`, `Logger`, `ErrorHandler`, `Icons`, `helpers` |
| Configuración | `.env.example`, `config/app.php`, `config/database.php`, `config/security.php` |
| Punto de entrada | `public/index.php`, `public/router.php`, `public/.htaccess` |
| Interfaz | Plantilla base, partials, páginas de error 404/419/500, `/diagnostico` (solo desarrollo) |
| Base de datos | `01_esquema.sql`, `02_datos_iniciales.sql`, `03_demo_desarrollo.sql`, `04_eliminar_demo.sql` |

### 2.2 Fase 2 — Autenticación, permisos, portal público y PWA

**Núcleo nuevo**

| Archivo | Función |
| --- | --- |
| `app/Core/Auth.php` | Inicio y cierre de sesión, usuario actual, `can()` y `canAny()` |
| `app/Core/Password.php` | Reglas de contraseña compartidas por la web y la consola |
| `app/Core/RateLimiter.php` | Límite de intentos y de envíos, con respaldo en base de datos |
| `app/Core/Audit.php` | Registro de auditoría que nunca interrumpe la operación |
| `app/Core/Model.php` | Base común de los modelos (consultas preparadas) |

**Middleware** (`app/Middleware/`)

| Archivo | Función |
| --- | --- |
| `AuthMiddleware.php` | Exige sesión iniciada |
| `GuestMiddleware.php` | Deja el formulario de acceso solo a quien no tiene sesión |
| `PermissionMiddleware.php` | Exige uno o varios permisos **en el servidor** |
| `CsrfMiddleware.php` | **Global**: valida el token en toda petición que modifica datos |

**Modelos** (`app/Models/`): `Usuario`, `Rol`, `Permiso`, `Instalacion`, `Tarifa`,
`Reserva`, `SolicitudAlquiler`, `Socio`, `Movimiento`, `Configuracion`,
`TipoEvento`, `Auditoria`.

**Controladores** (`app/Controllers/`): `AuthController`, `HomeController`,
`PortalAlquilerController`, `PanelController`, `UsuariosController`,
`RolesController`, `SolicitudesController`, `AuditoriaController`,
`DiagnosticoController` (actualizado a 40 tablas).

**Vistas** (`app/Views/`)

- Plantilla pública `layouts/publico.php` y plantilla del panel `layouts/app.php`.
- Portal: `home/index`, `alquiler/{index, ambiente, disponibilidad, solicitud, recibida, no_disponible}`.
- Acceso y cuenta: `auth/login`, `auth/perfil`.
- Panel: `panel/index`, `usuarios/{index, form}`, `roles/index`, `solicitudes/{index, ver}`, `auditoria/index`.
- Error: `errors/403` (además de 404, 419 y 500 de la Fase 1).

**Aplicación instalable (PWA)**

| Archivo | Función |
| --- | --- |
| `public/manifest.webmanifest` | Instalación en Android, iPhone y escritorio |
| `public/sw.js` | Service worker: caché **solo** de estáticos públicos |
| `public/offline.html` | Página mostrada sin conexión |
| `public/icons/*.png` | Iconos UV3 de la PWA generados con `tools/generar_iconos.ps1` |

**Herramientas** (`tools/`)

| Archivo | Función |
| --- | --- |
| `crear_admin.php` | **Solo consola.** Crea el primer administrador. Se elimina tras usarlo |
| `generar_iconos.ps1` | Regenera los iconos PNG |
| `verificar_estatico.ps1` | Verificación estática (no sustituye a `php -l`) |
| `.htaccess` | Niega todo acceso por web a la carpeta |

**Base de datos**

- Se agregó la tabla `csduv3_solicitudes_alquiler` (tabla n.º 40) a `01_esquema.sql`.
- Migración aditiva para instalaciones de la Fase 1:
  `database/migrations/0001_solicitudes_alquiler.sql`.

### 2.3 Fase 3 — Socios (corte 1)

**Alcance implementado**

| Función | Estado |
| --- | --- |
| Listado paginado con búsqueda y filtros por estado y categoría | Implementado |
| Alta con código correlativo sugerido y validación en el servidor | Implementado |
| Unicidad del código y del par tipo de documento / número | Implementado |
| Ficha individual y edición | Implementado |
| Baja lógica con historial en `csduv3_socios_bajas` | Implementado |
| Categorías: listar, crear y activar/desactivar | Implementado |
| Exportación CSV, documentos adjuntos y fotografía | Pendiente (corte 2) |

**Archivos**

| Archivo | Función |
| --- | --- |
| `app/Models/Socio.php` | Consultas del padrón, unicidad, alta, edición y baja transaccional |
| `app/Models/CategoriaSocio.php` | Categorías configurables y su uso por el padrón |
| `app/Controllers/SociosController.php` | Listado, alta, ficha, edición, baja y categorías |
| `app/Views/socios/index.php` | Listado con búsqueda, filtros y paginación |
| `app/Views/socios/form.php` | Formulario de alta y edición |
| `app/Views/socios/ver.php` | Ficha individual y registro de baja |
| `app/Views/socios/categorias.php` | Gestión mínima de categorías |
| `app/routes.php` | Rutas de socios con permisos por operación |
| `app/Views/partials/sidebar.php` | Enlace «Socios» habilitado |
| `app/Controllers/PanelController.php` | El indicador de socios enlaza al padrón |

**Decisiones**

- **No se agregó ninguna migración**: las tablas `socios`, `categorias_socio`,
  `socios_bajas` y `socios_documentos` ya existían en `01_esquema.sql`.
- **No se crearon permisos nuevos**: se usan `socios.ver`, `socios.crear`,
  `socios.editar` y `socios.baja`, ya presentes en `02_datos_iniciales.sql`.
- Un socio **nunca se elimina**: la baja registra fecha, motivo y si es renuncia.
- Las categorías **no se precargan**: las define el club según su estatuto.
- La auditoría registra códigos y nombres de categoría, **no** documentos ni otros
  datos personales.

### 2.4 Identidad visual UV3

Se aplicó la paleta del logotipo institucional `logo.jpg` (amarillo `#FEFE08`,
verde `#009F39`) a todas las pantallas existentes. La propuesta completa está en
[docs/DISENO.md](docs/DISENO.md).

| Área | Cambio |
| --- | --- |
| Paleta | Variables `:root` con marca, neutros y colores semánticos; contrastes WCAG |
| Navegación | Menú lateral verde profundo, enlace activo amarillo, logo real y `aria-current` |
| Barra superior | Fondo translúcido, línea amarilla y perfil compacto en móvil |
| Componentes | Tarjetas, botones, tablas, badges, alertas, formularios y paginación |
| Portal | Hero verde con acento amarillo, cabecera y pie institucionales |
| Acceso y errores | Tarjeta de acceso y páginas 403/404/419/500 con la nueva identidad |
| PWA | `theme_color`, `background_color`, iconos y página sin conexión actualizados |
| Responsive | Menú deslizante, tablas con scroll, botones a ancho completo y áreas seguras |
| Accesibilidad | Foco visible, `prefers-reduced-motion` y contrastes revisados |

**Archivos principales**

| Archivo | Función |
| --- | --- |
| `public/assets/css/app.css` | Paleta, componentes y reglas responsive |
| `public/assets/img/logo.jpg` | Logotipo institucional usado por la aplicación |
| `public/assets/img/logo.svg` | Respaldo vectorial con la misma paleta |
| `public/assets/img/favicon.svg` | Favicon UV3 |
| `public/icons/*.png` | Iconos PWA regenerados |
| `tools/generar_iconos.ps1` | Generador de iconos con la paleta UV3 |
| `public/manifest.webmanifest` | Colores de la aplicación instalable |
| `public/offline.html` | Página sin conexión |
| `app/Views/partials/topbar.php` | Perfil compacto para móvil |
| `app/Views/partials/sidebar.php` | Enlace activo accesible |
| `docs/DISENO.md` | Paleta, combinaciones, componentes y mantenimiento |

**Alcance:** se aplicó el diseño a **todas las pantallas que ya existen** (portal,
acceso, panel, socios, solicitudes, usuarios, roles, auditoría, diagnóstico y
errores). Los módulos de negocio nuevos son los descritos en 2.5 y 2.6; la junta
directiva, las elecciones y los demás módulos de las fases 4 a 7 siguen
deshabilitados.

### 2.5 Ambientes y tarifas (módulo nuevo)

Administración de los espacios que el club alquila y de sus tarifas. **No se
agregó ninguna migración ni se creó ningún permiso nuevo**: se usan las tablas
`instalaciones`, `tarifas` y `tipos_evento` del esquema y los permisos
`alquileres.ver` y `alquileres.tarifas`.

| Función | Estado |
| --- | --- |
| Listado con búsqueda y filtro por estado, con nº de tarifas y tarifas vigentes | Implementado |
| Alta y edición de ambientes (nombre, tipo, descripción, ubicación, aforo, adelanto, estado) | Implementado |
| Activar y desactivar ambientes | Implementado |
| Alta y edición de tarifas (tipo de uso, monto PEN, horario, vigencia, estado) | Implementado |
| Activar y desactivar tarifas | Implementado |
| Publicación automática en el portal: solo ambientes **activos** con **al menos una tarifa vigente** | Implementado |
| Permisos comprobados en el servidor en cada ruta | Implementado |

**Archivos**

| Archivo | Función |
| --- | --- |
| `app/Controllers/AmbientesController.php` | Listado, alta, edición y estado de ambientes |
| `app/Controllers/TarifasController.php` | Listado, alta, edición y estado de tarifas |
| `app/Models/Instalacion.php` | Consultas, CRUD y conteo de tarifas vigentes |
| `app/Models/Tarifa.php` | Consultas, CRUD y tarifas vigentes por instalación |
| `app/Models/TipoEvento.php` | Tipos de uso activos e inactivos |
| `app/Views/ambientes/{index,form}.php` | Listado y formulario de ambientes |
| `app/Views/tarifas/{index,form}.php` | Listado y formulario de tarifas |
| `app/routes.php` | Rutas `/ambientes` con permisos por operación |

**Decisiones**

- El nombre del ambiente es **único** (lo garantiza `uq_instalaciones_nombre`).
- Las tarifas se miden en **soles (PEN)**; el campo `moneda` queda fijo en `PEN`.
- Una tarifa con solo una de las dos horas, o con `hora_fin` anterior a
  `hora_inicio`, se rechaza en el servidor.
- Los tipos de uso inactivos se muestran en el formulario pero no se pueden
  elegir de nuevo.

### 2.6 Deportes y torneos (módulo nuevo)

Organización de torneos de fútbol fulbito y de las demás disciplinas, con
inscripción de equipos, fixture automático, programación de partidos, resultados
y tabla de posiciones. **No se agregó ninguna migración ni ningún permiso
nuevo**: se usan las tablas `deportes`, `deporte_puntos`, `torneos`,
`torneo_equipos`, `jornadas` y `encuentros` del esquema y los permisos
`deportes.ver` y `deportes.gestionar`.

| Función | Estado |
| --- | --- |
| Listado de torneos con búsqueda y filtros por disciplina y estado | Implementado |
| Alta y edición de torneo (disciplina, nombre, categoría, modalidad, reglamento, fechas, estado) | Implementado |
| Cambio de estado del torneo (planificado, en curso, finalizado, anulado) | Implementado |
| Inscripción de equipos con abreviatura, delegado y teléfono | Implementado |
| Fixture automático de todos contra todos, con jornada semanal y exe para número impar de equipos | Implementado |
| Programación de fecha, hora y sede de cada encuentro | Implementado |
| Registro de resultados y tabla de posiciones según las reglas de la disciplina | Implementado |
| Portal público de torneos (`/torneos`, `/torneos/{id}`) sin sesión | Implementado |
| Límite de 64 equipos por torneo | Implementado |

**Archivos**

| Archivo | Función |
| --- | --- |
| `app/Controllers/TorneosController.php` | Torneos, equipos, fixture, partidos y resultados |
| `app/Controllers/DeportesController.php` | Listado administrativo de torneos y disciplinas |
| `app/Controllers/PortalTorneosController.php` | Portal público de torneos |
| `app/Models/Torneo.php` | Consultas, fixture y posiciones |
| `app/Models/TorneoEquipo.php` | Equipos inscritos y control de cruces existentes |
| `app/Models/Encuentro.php` | Partidos programados y resultados |
| `app/Models/Jornada.php` | Jornadas o fechas del torneo |
| `app/Models/Deporte.php` | Disciplinas activas e inactivas |
| `app/Models/DeportePunto.php` | Reglas de puntuación por disciplina |
| `app/Views/torneos/*` | Formulario, detalle administrativo, encuentro, portal y estado sin base de datos |
| `app/Views/deportes/index.php` | Listado administrativo de torneos |
| `app/routes.php` | Rutas `/deportes` y `/torneos` con permisos por operación |

**Decisiones y límites del alcance**

- Solo se implementa la modalidad **todos contra todos**
  (`Torneo::MODALIDADES`). Las demás modalidades quedan para una fase posterior.
- Los equipos **no** se pueden agregar ni eliminar cuando ya existe un fixture:
  hay que regenerarlo (lo que borra los resultados registrados).
- Un torneo **anulado** no admite equipos, fixture ni resultados (tampoco
  falseando la URL); para volver a usarlo hay que cambiarle el estado.
- Las **estadísticas por jugador** (`encuentro_estadisticas`) y la **tabla por
  sets** (vóley) **no** están implementadas: la tabla de posiciones trabaja con
  puntos (3/1/0 por defecto; 2 puntos por victoria en básquet).
- El portal público muestra los torneos que **no** están anulados.

### 2.7 Caja, directiva, elecciones, calendario y documentos (módulos nuevos)

Se escribieron los cinco módulos que quedaban deshabilitados en el menú. **Ninguno
requiere migración**: todas sus tablas ya existían en `01_esquema.sql` y se usan
los permisos que ya define `02_datos_iniciales.sql`.

| Módulo | Permisos | Qué hace |
| --- | --- | --- |
| **Caja** (`/caja`) | `caja.ver`, `caja.registrar`, `caja.anular`, `caja.reportes` | Ingresos y egresos con filtros y resumen, catálogos (categorías, medios de pago, periodos), reportes por categoría y por mes, y anulación con motivo |
| **Directiva** (`/directiva`) | `directiva.ver`, `directiva.gestionar` | Periodos de gestión, catálogo de cargos e integrantes (socios o personas externas), con ceses y renuncias |
| **Elecciones** (`/elecciones`) | `elecciones.ver`, `elecciones.gestionar` | Procesos electorales, listas, candidatos y resultados; una sola lista ganadora por proceso |
| **Calendario** (`/calendario`) | `calendario.ver`, `calendario.gestionar` | Vista de mes con las actividades del club, programación, lugares y estados |
| **Documentos** (`/documentos`) | `documentos.ver`, `documentos.gestionar` | Repositorio institucional con categorías, adjuntos y descarga controlada |

**Archivos nuevos**

| Archivo | Función |
| --- | --- |
| `app/Controllers/CajaController.php` | Caja: movimientos, catálogos, anulación y reportes |
| `app/Controllers/DirectivaController.php` | Junta directiva: periodos, cargos e integrantes |
| `app/Controllers/EleccionesController.php` | Procesos electorales: listas, candidatos y resultados |
| `app/Controllers/CalendarioController.php` | Calendario institucional |
| `app/Controllers/DocumentosController.php` | Documentos y descarga de archivos |
| `app/Models/{Movimiento,CategoriaMovimiento,MedioPago,Periodo}.php` | Caja y sus catálogos |
| `app/Models/{Directiva,DirectivaCargo,DirectivaMiembro}.php` | Junta directiva |
| `app/Models/{ProcesoElectoral,ListaElectoral,Candidato,ResultadoElectoral}.php` | Elecciones |
| `app/Models/Actividad.php` | Calendario |
| `app/Models/Documento.php` | Documentos |
| `app/Views/{caja,directiva,elecciones,calendario,documentos}/` | Vistas de los cinco módulos |

**Decisiones y límites del alcance**

- La caja es un **registro financiero administrativo**: no es contabilidad
  tributaria ni emite comprobantes electrónicos. Los movimientos **no se
  eliminan**, se anulan con motivo y quedan en `csduv3_movimientos_auditoria`.
- No se admiten movimientos con fecha futura ni en un periodo cerrado.
- Las elecciones son un **registro administrativo** de lo que declara la
  organización: no es votación electrónica ni valida identidades.
- Los documentos se guardan en `storage/uploads/documentos`, **fuera de la
  carpeta pública**, y se entregan por el controlador después de comprobar el
  permiso. Se validan extensión, contenido real y tamaño (máximo 5 MB).
- El calendario es **interno**: las actividades no se publican en el portal
  público (el campo `visible` queda preparado para una fase posterior).

### 2.8 Fase 4 (reservas), galería, negocios y portal de la unidad

**Reservas con tarifa, adelanto y pago**

| Función | Estado |
| --- | --- |
| Alta y edición de reservas con tarifa vigente, precio acordado y adelanto | Implementado |
| Conversión de una solicitud del portal en reserva (precio y adelanto sugeridos) | Implementado |
| Registro de pagos (adelanto, saldo y devolución) con asiento en caja | Implementado |
| Bloqueo de horarios con transacción y `SELECT … FOR UPDATE` | Implementado |
| Cancelación con motivo, sin borrar el registro | Implementado |
| Estados: pendiente, confirmada, finalizada y cancelada | Implementado |

Archivos: `app/Controllers/ReservasController.php`, `app/Models/Reserva.php`
(ampliado), `app/Models/ReservaPago.php` y `app/Views/reservas/`.

**Galería de fotos y directorio de negocios**

| Función | Estado |
| --- | --- |
| Subir y publicar fotos (reuniones, campeonatos, directiva, socios) | Implementado |
| Bloque tipo «periódico» con las fotos en la portada | Implementado |
| Directorio de negocios de la unidad, todo editable, con su enlace | Implementado |
| Portal público `/galeria` y `/negocios` | Implementado |
| Enlace al portal de la Unidad Vecinal (`https://uv3-platform.vercel.app/`) | Implementado |

Archivos: `app/Models/Foto.php`, `app/Models/Negocio.php`,
`app/Controllers/{GaleriaController,NegociosController,PortalGaleriaController,PortalNegociosController}.php`,
`app/Views/{galeria,negocios}/` y `app/Core/Uploads.php` (subida de archivos
centralizada: extensión, contenido real y tamaño).

**Migración aplicada:** `database/migrations/0002_galeria_negocios.sql` crea las
tablas `csduv3_fotos` y `csduv3_negocios` y carga el parámetro
`porcentaje_adelanto`. El script de despliegue (`tools/desplegar-servidor.sh`)
ejecuta `php tools/migrar.php` después de cada `git pull`, así que se aplicó
sola. Las mismas tablas están en `database/01_esquema.sql` para las instalaciones
nuevas.

**Decisiones y límites del alcance**

- El **porcentaje de adelanto** por defecto es el 50 % del precio acordado y se
  puede cambiar en la tabla `csduv3_configuracion` (clave `porcentaje_adelanto`).
  El importe siempre es editable al crear la reserva.
- La galería y el directorio usan los permisos `documentos.ver` y
  `documentos.gestionar`: no se crean permisos nuevos.
- Las fotos y los logotipos se guardan en `storage/uploads` y se sirven por el
  controlador; nunca son archivos públicos.
- El directorio publica **solo** los negocios marcados como visibles; cada uno
  puede llevar su propio enlace.

### 2.9 La portada con formato de revista
La portada pública pasó a tener **formato de revista (magazine)**:

| Bloque | Contenido |
| --- | --- |
| Cabecera | Fecha, «Edición de la Unidad Vecinal N.º 3», masthead con las siglas y accesos rápidos (ambientes, solicitar, torneos, galería, negocios, portal UV3) |
| Portada | La **noticia en portada**: foto principal con titular, entradilla, categoría y fecha, y llamada a la galería |
| Noticias y galería | Carrusel horizontal con las noticias publicadas |
| Negocios de la unidad | Carrusel con los negocios de la portada y su enlace |
| Columna lateral | **Próximos eventos** (del calendario), **agenda deportiva** (partidos programados) y recuadro de alquiler de ambientes |
| Secciones finales | Ambientes publicados, pasos de la reserva, contacto e instalación de la aplicación |

**Módulo de noticias** (`/noticias` en el panel): cada noticia lleva una foto,
un titular, una entradilla, un pie de foto, una categoría y una fecha. Se
publica o se deja como borrador, y **una sola noticia ocupa la portada** del
portal (al elegirla, las demás salen de ella). La galería pública (`/galeria`)
muestra todas las publicadas.

**Contenido de ejemplo**: si todavía no hay noticias, eventos, partidos ni negocios
reales, la portada muestra contenido de ejemplo con la insignia **«Ejemplo»**
para poder revisar el diseño. En cuanto el club carga contenido real, se
sustituye solo. Se desactiva con el parámetro `fotos_ejemplo` de la tabla
`csduv3_configuracion` (por defecto activado).

Archivos: `app/Views/home/index.php`, `app/Controllers/HomeController.php`,
`app/Models/Actividad.php` (`proximasPublicas()`), `app/Models/Encuentro.php`
(`proximos()`), `public/assets/css/app.css` y `public/assets/js/app.js`
(carruseles sin dependencias).

### 2.10 Reserva confirmada desde la web

El portal público tiene **dos caminos** para el alquiler:

| Camino | Qué hace | Cuándo usarlo |
| --- | --- | --- |
| **Reservar ahora** (`/alquiler/reservar`) | Toma la tarifa publicada, calcula el precio, el adelanto y el saldo, **confirma la reserva** y bloquea el horario. Si el solicitante marca que ya pagó, el abono se anota en caja | El solicitante ya conoce el precio y quiere asegurar la fecha |
| **Solicitud** (`/alquiler/solicitud`) | Registra la consulta en estado «pendiente» para que el personal la revise y la convierta en reserva | El solicitante quiere consultar antes de comprometer |

La reserva directa usa la misma comprobación transaccional de solapamiento
(`SELECT … FOR UPDATE`) que el panel, con las protecciones del portal público:
trampa para robots, tiempo mínimo de llenado, límite por conexión y máximo de
3 reservas futuras por teléfono. La página de confirmación se construye con la
sesión del solicitante: **no se expone ninguna reserva ajena**.

El precio sale de la tarifa vigente del ambiente (activa y dentro de su
vigencia). El adelanto es el `porcentaje_adelanto` de la configuración
(50 % por defecto) y solo aplica si el ambiente tiene «requiere adelanto».

### 2.11 Configuración del club e imágenes en WebP

**Panel → Configuración** (permiso `configuracion.gestionar`):

| Sección | Parámetros |
| --- | --- |
| Identidad | Nombre del club, siglas, lema, descripción breve, años de mandato |
| Contacto | Teléfono, WhatsApp, correo, dirección, Facebook e Instagram |
| Portada | Titular y bajada editables, y si se muestra el contenido de ejemplo |
| Alquileres y avisos | Porcentaje de adelanto, días mínimos de anticipación, aviso de mantenimiento |
| Marca | Logotipo, favicon y foto de portada (subida y restauración de la original) |

Los valores se guardan en `csduv3_configuracion` y **sustituyen de inmediato** a
los del archivo de configuración: el portal público y el panel muestran el
nombre, las siglas, el logo y el favicon que el club defina. La marca se sirve
por la ruta pública `/marca/{tipo}` (con caché) y nunca se expone como
archivo estático.

**WebP**: `App\Core\Uploads` convierte **toda imagen subida** a WebP y la
reduce a 2000 px de ancho. Si la conversión no ahorra peso, se conserva el
original (nunca sube de tamaño). Medido con una foto de teléfono de 4000×3000:
de 2,26 MB a **267 KB (−88 %)**. Si el servidor no tiene GD con WebP, el
sistema sigue funcionando con el archivo original.

---

## 3. Pruebas realizadas
Ejecutadas con **PHP 8.3.35 portable** (descargado al directorio temporal del
equipo, fuera del repositorio) y con la verificación estática del proyecto
(ver [docs/PRUEBAS.md](docs/PRUEBAS.md)):

- `php -l` sobre los **102 archivos PHP de `app/`**: **sin errores de sintaxis**.
- Verificación estática `tools/verificar_estatico.ps1`: **109 archivos PHP
  revisados, 0 hallazgos** (se corrigió `tools/migrar.php`, que estaba roto).
- Prueba de enrutamiento real contra el `Router` del proyecto: las 16 rutas
  nuevas y modificadas (`/ambientes…`, `/deportes…`, `/deportes/encuentros/{id}`,
  `/torneos`, `/torneos/{id}`) resuelven al controlador y al método correctos,
  sin ambigüedad por el orden de declaración.
- Render real de las **11 vistas nuevas** con datos de muestra: todas se
  renderizan sin advertencias ni variables indefinidas.
- Algoritmo de fixture comprobado con 2 a 8 equipos: sin cruces repetidos y con
  el número exacto de partidos (`n(n-1)/2`), con bye cuando el número de
  equipos es impar.
- Comprobación de los parámetros vinculados de las consultas nuevas: no se
  repite ningún marcador con nombre (obligatorio con
  `PDO::ATTR_EMULATE_PREPARES = false`).
- Cruce de métodos de modelos y núcleo citados por los controladores: correcto.
- Lista de tablas del diagnóstico (40) coincide con el esquema: correcto.
- 40 tablas definidas, sin nombres duplicados y sin `DROP`: correcto.
- Sin credenciales reales en el código ni en la documentación: correcto.
- `.htaccess` de denegación en `app/`, `config/`, `database/`, `docs/`, `storage/` y `tools/`: correcto.
- Rutas de socios: las rutas estáticas (`/socios/nuevo`, `/socios/categorias`) se
  declaran antes de `/socios/{id}`; cada operación exige su permiso en el servidor.
- Revisión manual de las consultas nuevas: todas usan parámetros vinculados y los
  valores `LIMIT`/`OFFSET` se acotan antes de interpolarse.
- Revisión visual con Edge headless sobre una vista estática de referencia (panel
  y portal) a 390, 768 y 1440 px: sin desbordamiento horizontal de página; la
  tabla conserva su scroll interno; el menú público se reorganiza en móvil. **No
  sustituye** la revisión de la aplicación real.
- En producción, antes de este despliegue: portada, `/alquiler`, `/ingresar` y
  `/panel` respondían **200** y sin aviso de mantenimiento.

**No ejecutadas:** conexión a MariaDB, escritura en base de datos,
autenticación, comprobación de permisos en ejecución, alta de socios, alta de
ambientes, generación de fixture y registro de resultados, revisión en
navegador real y en dispositivos físicos. La revisión estática y el render con
datos de muestra **no** sustituyen esas pruebas. No se declaran resultados que
no se hayan obtenido.

---

## 4. Pendiente

### 4.1 Inmediato (bloquea el uso real del sistema)

1. **Crear la cuenta de administrador** desde el Terminal del hosting:
   `php tools/crear_admin.php` (después de crear `.env` si aún no existe). Si ya
   hay un administrador activo, usar `--permitir-otro`. **Eliminar el script del
   servidor** cuando exista la cuenta.
2. **Rotar la contraseña de la base de datos** en cPanel y actualizar `.env`: la
   contraseña anterior estuvo expuesta en el historial público de Git.
3. Ejecutar en el servidor los casos `AM-*` y `TO-*` de
   [docs/PRUEBAS.md](docs/PRUEBAS.md) (ambientes, tarifas, torneo, fixture,
   resultados y posiciones).
4. Ejecutar las secciones 5 a 8 de [docs/PRUEBAS.md](docs/PRUEBAS.md) para la
   Fase 2 y el corte 1 de socios.
5. Probar el corte 1 de socios: alta con código sugerido, rechazo de códigos y
   documentos duplicados, búsqueda y filtros, ficha, edición, baja con historial
   y gestión de categorías.
6. Revisar el diseño en un navegador real: móvil (360–430 px), tablet
   (768–1024 px) y escritorio (1280 px o más), además de la instalación PWA.
7. Instalar PHP 8.3 y MariaDB de forma permanente en el equipo de desarrollo
   (o usar Laragon/XAMPP) para poder repetir las pruebas sin depender del
   hosting.

### 4.2 Decisiones pendientes del club

- **Identidad visual oficial**: se aplicó el logotipo `logo.jpg` y su paleta
  (amarillo y verde). Falta que el club confirme la versión oficial y, si es
  posible, entregue el archivo en alta resolución para impresión y formatos
  grandes.
- **Ambientes y tarifas de alquiler**: ya se pueden cargar desde el panel
  (`/ambientes`); el club define los nombres, los precios y la vigencia.
- **Categorías de socio y cuotas**: no se precargan.
- **Cargos de la junta directiva**: no se precargan; dependen del estatuto.
- **Duración del mandato**: `anios_mandato` queda en `4` como valor inicial editable.
- **Correo institucional del club**: la portada muestra el valor configurado o el
  estado vacío.
- **Alcance de los torneos**: hoy solo «todos contra todos». Falta decidir si el
  club necesita otras modalidades (grupos, eliminación, ida y vuelta) y si las
  disciplinas por sets (vóley) deben tener su propia tabla de posiciones.

### 4.3 Fases siguientes

| Fase | Contenido |
| --- | --- |
| 3 | Socios (corte 1), junta directiva y elecciones — **implementados** |
| 4 | Reservas y pagos (condición de carrera **cerrada** con transacción y bloqueo) y calendario — implementados |
| 5 | Caja, ingresos, egresos e informes financieros — **implementado** (corte 1) |
| 6 | Deportes: modalidades adicionales, estadísticas por jugador y tabla por sets |
| 7 | Documentos, auditoría, reportes y mejoras de experiencia — documentos implementados |
| 8 | Pruebas integrales, revisión de seguridad y despliegue |

---

## 5. Siguiente paso recomendado

El corte 1 de socios, los módulos de ambientes y de torneos y el rediseño visual
ya están implementados. Antes de continuar con módulos nuevos:

1. Crear la cuenta de administrador y rotar la contraseña de la base de datos.
2. Ejecutar en el servidor las pruebas pendientes (`AM-*` y `TO-*` de
   [docs/PRUEBAS.md](docs/PRUEBAS.md)) con un torneo de fútbol fulbito de prueba.
3. Corregir lo que aparezca en esas pruebas.
4. Revisar el rediseño en la aplicación real, en un navegador (móvil, tablet y
   escritorio). Las capturas headless a 390, 768 y 1440 px ya se revisaron.

Después, continuar con el **corte 2 de socios** (exportación CSV, documentos
adjuntos y fotografía), con la **junta directiva** o con las **modalidades
adicionales de torneo**, según la prioridad del club.

---

## 6. Advertencias de alcance

- El sistema **no** usa Supabase, Firebase, Vercel ni Node.js en producción.
- **No** se importan datos del proyecto anterior ni se reutilizan sus credenciales.
- **No** se ha creado ningún dato real del club: socios, directivos, candidatos,
  movimientos, resultados, cuotas, categorías, cargos y tarifas los carga la
  institución. Solo existen los catálogos técnicos y los datos ficticios de
  desarrollo con prefijo `DEMO-`.
- La solicitud del portal público **no confirma** ninguna reserva: solo el club
  la convierte, y esa conversión es la que bloquea el horario.
- La comprobación de solapamiento de **reservas** usa transacción y
  `SELECT … FOR UPDATE`: dos reservas simultáneas no ocupan la misma franja.
  Entre **solicitudes** el solapamiento no se bloquea, solo se marca
  `conflicto_detectado` para revisarlo.
- Los comprobantes del módulo de alquileres serán **documentos internos**, no
  comprobantes electrónicos autorizados por SUNAT.
- El registro de resultados electorales es **administrativo**, no un sistema de
  votación electrónica certificada.
- El módulo de caja es un registro financiero administrativo, no contabilidad
  tributaria completa.
- El corte 1 de socios se publicó en `main` (commit `c54b7e3`) y el rediseño
  visual en `c76def6`, ambos con autorización expresa. El rediseño se desplegó
  en producción mediante el cron del hosting y la identidad nueva ya está
  visible. **La base de datos volvió a estar disponible**: la portada, `/alquiler`,
  `/ingresar` y `/panel` responden 200 y el portal ya no muestra mantenimiento.
  El aviso de instalación se corrigió en `f659f36` y el script de despliegue
  ahora lee las credenciales de `.env` (`211d269`). **La contraseña de la base de
  datos estuvo expuesta en el repositorio público: debe rotarse en cPanel y
  actualizarse en `.env`.** El commit `a07324c` (operador) eliminó el
  `.htaccess` raíz para corregir un 403; las rutas sensibles comprobadas
  responden 403/404, pero conviene confirmar el DocumentRoot y mantener las
  protecciones.
- Los módulos de ambientes y torneos se escribieron **sin migración** porque las
  tablas ya existían en `01_esquema.sql`; no se modificó ningún dato de
  producción.
- El fixture automático **no** es un torneo oficial ni-genera datos reales de
  personas: los equipos los inscribe el club. Los resultados que se registran
  son los que la organización declara.
- La tabla de posiciones de los torneos es **administrativa**: no es un sistema
  de Competencia certificada ni valida identidad de los participantes.
