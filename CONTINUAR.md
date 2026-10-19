# CONTINUAR — Estado del proyecto CSDUV3

Última actualización: **Fase 2 implementada** (autenticación, roles, portal público
de alquileres y aplicación instalable). **Pendiente de pruebas en servidor.**

> El código de la Fase 2 está escrito y revisado de forma estática, pero **no se ha
> ejecutado**: este equipo no tiene PHP 8.3 ni MariaDB. No debe darse la fase por
> terminada hasta ejecutar [docs/PRUEBAS.md](docs/PRUEBAS.md) en un servidor.

---

## 1. Resumen

| Dato | Valor |
| --- | --- |
| Proyecto | Sistema integral de gestión del Club Social Deportivo Unidad Vecinal N.º 3 |
| Carpeta local | Directorio raíz del proyecto |
| Repositorio | <https://github.com/bgrados/csduv3> |
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

---

## 3. Pruebas realizadas

Ejecutadas como **verificación estática** (ver [docs/PRUEBAS.md](docs/PRUEBAS.md)):

- `tools/verificar_estatico.ps1`: **80 archivos PHP, 0 hallazgos**.
- Cruce de métodos de modelos y núcleo citados por los controladores: correcto.
- Lista de tablas del diagnóstico (40) coincide con el esquema: correcto.
- 40 tablas definidas, sin nombres duplicados y sin `DROP`: correcto.
- Sin credenciales reales en el código ni en la documentación: correcto.
- `.htaccess` de denegación en `app/`, `config/`, `database/`, `docs/`, `storage/` y `tools/`: correcto.

**No ejecutadas:** sintaxis con `php -l`, carga de la aplicación, conexión a
MariaDB, autenticación, permisos, portal público y PWA, porque **PHP y MariaDB no
están instalados en este equipo**. No se declaran resultados que no se hayan
obtenido. La verificación estática **no** es equivalente a `php -l`.

---

## 4. Pendiente

### 4.1 Inmediato (bloquea dar la Fase 2 por terminada)

1. Instalar PHP 8.3 y MariaDB en el equipo de desarrollo (o usar Laragon/XAMPP).
2. Crear `.env` a partir de `.env.example`.
3. Importar `database/01_esquema.sql` y `database/02_datos_iniciales.sql`
   (y la migración si ya existía la Fase 1).
4. Ejecutar `php tools/crear_admin.php` y luego **eliminarlo**.
5. Ejecutar las secciones 4 a 7 de [docs/PRUEBAS.md](docs/PRUEBAS.md).

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
| 3 | Socios y junta directiva |
| 4 | Instalaciones, reservas, calendario y pagos (incluye cerrar la condición de carrera) |
| 5 | Caja, ingresos, egresos e informes financieros |
| 6 | Deportes, torneos, equipos, fixture y clasificación |
| 7 | Documentos, auditoría, reportes y mejoras de experiencia |
| 8 | Pruebas integrales, revisión de seguridad y despliegue |

---

## 5. Siguiente paso recomendado

Autorizar el inicio de la **Fase 3** (socios y junta directiva) **después** de
ejecutar las pruebas de la Fase 2 en un servidor con PHP 8.3 y MariaDB. Si se
prefiere validar primero, el orden es: instalar PHP/MariaDB → importar → primer
administrador → ejecutar las pruebas → recién entonces continuar.

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
