# CONTINUAR — Estado del proyecto CSDUV3

Última actualización: **Fase 3, corte 1 (padrón de socios) implementado**. La Fase 2
y este corte están **pendientes de pruebas en servidor.**

> El código de la Fase 2 y del corte 1 de socios está escrito y revisado de forma
> estática, pero **no se ha ejecutado**: este equipo no tiene PHP 8.3 ni MariaDB.
> Ninguna de las dos fases debe darse por terminada hasta ejecutar
> [docs/PRUEBAS.md](docs/PRUEBAS.md) en un servidor.

---

## 1. Resumen

| Dato | Valor |
| --- | --- |
| Proyecto | Sistema integral de gestión del Club Social Deportivo Unidad Vecinal N.º 3 |
| Carpeta local | Directorio raíz del proyecto |
| Repositorio | <https://github.com/bgrados/csduv3> (público, rama `main`) |
| Hosting previsto | BanaHosting · Bana-Professional-Deluxe-SSD |
| URL prevista | El subdominio HTTPS que se defina en el hosting |
| PHP | 8.3 |
| Base de datos | MariaDB · base `TU_BASE_DE_DATOS` / usuario `TU_USUARIO_BD` |
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
| `public/icons/*.png` | Iconos (provisionales) generados con `tools/generar_iconos.ps1` |

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

---

## 3. Pruebas realizadas

Ejecutadas como **verificación estática** (ver [docs/PRUEBAS.md](docs/PRUEBAS.md)):

- `tools/verificar_estatico.ps1`: **86 archivos PHP, 0 hallazgos**.
- Cruce de métodos de modelos y núcleo citados por los controladores: correcto.
- Lista de tablas del diagnóstico (40) coincide con el esquema: correcto.
- 40 tablas definidas, sin nombres duplicados y sin `DROP`: correcto.
- Sin credenciales reales en el código ni en la documentación: correcto.
- `.htaccess` de denegación en `app/`, `config/`, `database/`, `docs/`, `storage/` y `tools/`: correcto.
- Rutas de socios: las rutas estáticas (`/socios/nuevo`, `/socios/categorias`) se
  declaran antes de `/socios/{id}`; cada operación exige su permiso en el servidor.
- Revisión manual de las consultas nuevas: todas usan parámetros vinculados y los
  valores `LIMIT`/`OFFSET` se acotan antes de interpolarse.

**No ejecutadas:** sintaxis con `php -l`, carga de la aplicación, conexión a
MariaDB, autenticación, permisos, portal público, PWA y módulo de socios, porque
**PHP y MariaDB no están instalados en este equipo**. No se declaran resultados
que no se hayan obtenido. La verificación estática **no** es equivalente a
`php -l`.

---

## 4. Pendiente

### 4.1 Inmediato (bloquea dar la Fase 2 por terminada)

1. Instalar PHP 8.3 y MariaDB en el equipo de desarrollo (o usar Laragon/XAMPP).
2. Crear `.env` a partir de `.env.example`.
3. Importar `database/01_esquema.sql` y `database/02_datos_iniciales.sql`
   (y la migración si ya existía la Fase 1).
4. Ejecutar `php tools/crear_admin.php` y luego **eliminarlo**.
5. Ejecutar las secciones 4 a 7 de [docs/PRUEBAS.md](docs/PRUEBAS.md).
6. Probar el corte 1 de socios: alta con código sugerido, rechazo de códigos y
   documentos duplicados, búsqueda y filtros, ficha, edición, baja con historial
   y gestión de categorías.

### 4.2 Decisiones pendientes del club

- **Identidad visual oficial**: logotipo y colores. Los actuales son provisionales
  y **no** representan al club.
- **Ambientes y tarifas de alquiler**: el portal no los inventa; se cargan en la
  base de datos.
- **Categorías de socio y cuotas**: no se precargan.
- **Cargos de la junta directiva**: no se precargan; dependen del estatuto.
- **Duración del mandato**: `anios_mandato` queda en `4` como valor inicial editable.
- **Correo institucional del club**: la portada muestra el valor configurado o el
  estado vacío.

### 4.3 Fases siguientes

| Fase | Contenido |
| --- | --- |
| 3 | Socios (corte 1 implementado) y junta directiva |
| 4 | Instalaciones, reservas, calendario y pagos (incluye cerrar la condición de carrera) |
| 5 | Caja, ingresos, egresos e informes financieros |
| 6 | Deportes, torneos, equipos, fixture y clasificación |
| 7 | Documentos, auditoría, reportes y mejoras de experiencia |
| 8 | Pruebas integrales, revisión de seguridad y despliegue |

---

## 5. Siguiente paso recomendado

El corte 1 de socios ya está implementado. Antes de continuar:

1. Ejecutar en un servidor con PHP 8.3 y MariaDB las pruebas pendientes de la
   Fase 2 y del corte 1 de socios.
2. Corregir lo que aparezca en esas pruebas.

Después, continuar con el **corte 2 de socios** (exportación CSV, documentos
adjuntos y fotografía) o con la **junta directiva**, según la prioridad del club.

---

## 6. Advertencias de alcance

- El sistema **no** usa Supabase, Firebase, Vercel ni Node.js en producción.
- **No** se importan datos del proyecto anterior ni se reutilizan sus credenciales.
- **No** se ha creado ningún dato real del club: socios, directivos, candidatos,
  movimientos, resultados, cuotas, categorías, cargos y tarifas los carga la
  institución. Solo existen los catálogos técnicos y los datos ficticios de
  desarrollo con prefijo `DEMO-`.
- La solicitud del portal público **no confirma** ninguna reserva.
- La comprobación de solapamiento no usa transacción ni `FOR UPDATE`: la condición
  de carrera se cierra en la Fase 4.
- Los comprobantes del módulo de alquileres serán **documentos internos**, no
  comprobantes electrónicos autorizados por SUNAT.
- El registro de resultados electorales es **administrativo**, no un sistema de
  votación electrónica certificada.
- El módulo de caja es un registro financiero administrativo, no contabilidad
  tributaria completa.
- **No se han publicado cambios.** El repositorio y el despliegue se actualizarán
  únicamente con autorización expresa.
