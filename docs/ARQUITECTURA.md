# Arquitectura

Organización del código y decisiones técnicas del sistema CSDUV3.

---

## 1. Principios

1. **Funciona en hosting compartido.** Sin SSH, sin Docker, sin procesos
   permanentes, sin Composer en el servidor y sin API externas obligatorias.
2. **Un solo punto de entrada.** Todas las peticiones pasan por `public/index.php`.
3. **Separación de responsabilidades.** Presentación, lógica y datos en capas
   distintas.
4. **Sin dependencias.** Cargador de clases propio (PSR-4) y utilidades internas.
5. **Seguridad desde el primer archivo.** Consultas preparadas, escape de salida,
   CSRF y validación en servidor.
6. **Compatible con despliegue por FTP.** Los archivos de producción están listos:
   no hay paso de compilación.

---

## 2. Ciclo de una petición

```
Navegador
   │
   ▼
public/.htaccess            Reescritura: todo va a index.php
   │
   ▼
public/index.php            Front controller
   ├─ localiza la aplicación (APP_PATH)
   ├─ Autoloader (PSR-4 App\ => app/)
   ├─ Env::load(.env)
   ├─ Config::load(config/)
   ├─ helpers.php
   ├─ ErrorHandler::register()
   ├─ Session::start()
   └─ Router::dispatch(Request::capture())
         │
         ├─ middleware (autenticación, permisos)
         ▼
      Controlador  →  Modelo (PDO)  →  Vista (+ layout)
                                        │
                                        ▼
                                     Respuesta HTML
```

---

## 3. Componentes del núcleo

| Clase | Responsabilidad |
| --- | --- |
| `App\Core\Autoloader` | Carga de clases PSR-4 sin Composer |
| `App\Core\Env` | Lectura de `.env` (el entorno real tiene prioridad) |
| `App\Core\Config` | Configuración con acceso por notación de punto |
| `App\Core\Database` | PDO único, transacciones, comprobación de tablas |
| `App\Core\Router` | Rutas con parámetros `{id}`, middleware y 404 |
| `App\Core\Request` | Método, ruta, datos de entrada, IP, detección de HTTPS |
| `App\Core\Response` | Redirecciones, JSON, texto y retorno a la página anterior |
| `App\Core\View` | Render de vistas con plantilla y datos compartidos |
| `App\Core\Session` | Sesiones seguras, mensajes de un solo uso, regeneración |
| `App\Core\Csrf` | Token por sesión y verificación con `hash_equals()` |
| `App\Core\Validator` | Reglas declarativas, incluida unicidad en base de datos |
| `App\Core\Logger` | Registro en archivo con enmascarado de datos sensibles |
| `App\Core\ErrorHandler` | Errores y excepciones: detalle solo en desarrollo |
| `App\Core\Icons` | Iconos SVG en línea (sin CDN ni fuentes externas) |
| `App\Core\Auth` | Inicio y cierre de sesión, usuario actual, comprobación de permisos |
| `App\Core\Password` | Reglas de contraseña compartidas por la web y la consola |
| `App\Core\RateLimiter` | Límite de envíos y de intentos, con respaldo en base de datos |
| `App\Core\Audit` | Registro de auditoría que nunca interrumpe la operación principal |
| `App\Middleware\AuthMiddleware` | Exige sesión iniciada |
| `App\Middleware\GuestMiddleware` | Deja el formulario de acceso solo a quien no tiene sesión |
| `App\Middleware\PermissionMiddleware` | Exige uno o varios permisos **en el servidor** |
| `App\Middleware\CsrfMiddleware` | Middleware **global**: valida el token en toda petición que modifica datos |

---

## 4. Convenciones de código

- `declare(strict_types=1)` en todos los archivos PHP.
- Espacios de nombres `App\Core`, `App\Controllers`, `App\Models`, `App\Middleware`.
- Clases y métodos en **español** cuando describen conceptos del club
  (`Socios`, `Reservas`), y en inglés cuando son infraestructura
  (`Autoloader`, `Router`, `Request`).
- Vistas en `app/Views`, con notación de punto: `socios.index` →
  `app/Views/socios/index.php`.
- Toda salida pasa por `e()`. Toda consulta usa parámetros vinculados.
- Los formularios que modifican datos incluyen `csrf_field()` y se verifican con
  `Csrf::verify()` en el controlador.
- Los procesos con varias escrituras usan `Database::transaction()`.

---

## 5. Enrutamiento

Las rutas se declaran en `app/routes.php`:

```php
$router->get('/', [HomeController::class, 'index']);
$router->get('/socios/{id}/editar', [SociosController::class, 'editar'], [$middlewarePermiso]);
```

- Los parámetros llegan al controlador como argumentos, después de `Request`.
- Los middleware son funciones que reciben la `Request`; si devuelven `false`, la
  petición se detiene (por ejemplo, redirección al formulario de acceso).
- Existe **middleware global** (`Router::middleware()`), aplicado a todas las
  rutas. El proyecto lo usa para el CSRF: así ninguna ruta nueva queda sin
  protección por olvido.
- `PermissionMiddleware::requiere('modulo.accion')` y
  `PermissionMiddleware::requiereAlguno([...])` devuelven el middleware listo
  para usar, de modo que el permiso exigido se lee junto a la ruta.
- Los formularios pueden enviar `_method` con `PUT` o `DELETE`.
- La ruta solicitada se normaliza descontando la ruta base de instalación, de modo
  que el sistema funcione igual en la raíz de un subdominio o en un subdirectorio.

### Mapa de rutas de la Fase 2

| Ámbito | Rutas | Protección |
| --- | --- | --- |
| Portal público | `/`, `/alquiler`, `/alquiler/ambiente/{id}`, `/alquiler/disponibilidad`, `/alquiler/solicitud` (GET y POST), `/alquiler/solicitud/recibida/{codigo}` | Sin sesión; validación y límite de envíos en el servidor |
| Acceso | `/ingresar` (GET y POST), `/salir` (POST) | `GuestMiddleware` / `AuthMiddleware` |
| Cuenta | `/perfil` (GET y POST) | `AuthMiddleware` |
| Panel | `/panel` | `panel.ver` |
| Usuarios | `/usuarios`, `/usuarios/nuevo`, `/usuarios/{id}/editar`, `/usuarios/{id}`, `/usuarios/{id}/estado`, `/usuarios/{id}/password` | `usuarios.ver` para consultar; `usuarios.gestionar` para modificar |
| Roles | `/roles`, `/roles/permisos` | `roles.gestionar` |
| Solicitudes | `/solicitudes`, `/solicitudes/{id}`, `/solicitudes/{id}/en-revision`, `/solicitudes/{id}/atender`, `/solicitudes/{id}/rechazar` | `alquileres.ver` / `alquileres.gestionar` |
| Auditoría | `/auditoria` | `auditoria.ver` |
| Diagnóstico | `/diagnostico` | Solo si `APP_ENV=development` (el propio controlador responde 404 en producción) |

---

## 6. Dos interfaces, una sola aplicación

| | Panel administrativo | Portal público |
| --- | --- | --- |
| Plantilla | `app/Views/layouts/app.php` | `app/Views/layouts/publico.php` |
| Sesión | Obligatoria | No se usa |
| Datos | Según permisos del rol | Solo ambientes, tarifas y horarios ocupados |
| Caché del navegador | Excluida del service worker | Páginas siempre desde la red; solo estáticos en caché |
| Errores | Mensaje genérico + registro | Página genérica (404, 403, 503) |

Comparten el núcleo (Router, PDO, Validator, View, Csrf) y el mismo archivo de
rutas, lo que evita dos bases de código.

---

## 7. Rutas de archivos en tiempo de ejecución

| Constante | Valor |
| --- | --- |
| `APP_PATH` | Directorio raíz del proyecto (el que contiene `app/`, `config/`, `public/`, etc.) |
| `PUBLIC_PATH` | Carpeta `public/` |

`public/index.php` determina `APP_PATH` en este orden:

1. Variable de entorno `CSDUV3_APP_PATH`.
2. Archivo `public/.app_path.php` (no versionado, creado en el servidor).
3. Directorio padre de `public/` (caso normal).

Esto permite desplegar con el código fuera del directorio público sin modificar
código fuente.

---

## 8. Base de datos

- PDO con `ATTR_EMULATE_PREPARES = false`: las consultas se preparan realmente en
  el servidor.
- Prefijo `csduv3_` configurable en `config/database.php`.
- Zona horaria fijada con desplazamiento (`-05:00`) para no depender de las tablas
  de zonas horarias del hosting.
- Los modelos (`app/Models/`) encapsulan las consultas de cada entidad. En la
  Fase 2 se implementaron los de usuarios, roles, permisos, instalaciones,
  tarifas, reservas, solicitudes de alquiler, socios, movimientos, configuración,
  tipos de evento y auditoría.
- `App\Models\Model` es la base común: nombre de tabla, consultas preparadas y
  ayudantes (`fetchOne`, `fetchAll`, `scalar`, `run`, `insertar`). Los modelos
  declaran columnas y límites, pero **no** contienen HTML ni reglas de permisos.

---

## 9. Aplicación instalable (PWA)

- `public/manifest.webmanifest` declara nombre, colores, iconos y accesos directos.
- `public/sw.js` es el service worker: precarga una lista fija de estáticos
  públicos y deja el resto siempre a la red (HTML incluido).
- `public/offline.html` es la página de respaldo cuando no hay conexión.
- `public/assets/js/app.js` registra el service worker y gestiona el aviso de
  instalación, además del menú lateral en móvil.
- Los iconos PNG de `public/icons/` se generan con `tools/generar_iconos.ps1` y se
  reemplazarán por los institucionales cuando el club entregue su identidad.

Detalle en [PWA.md](PWA.md).

---

## 10. Interfaz

- HTML5, CSS3 y JavaScript sin bibliotecas externas.
- Estilos centralizados en `public/assets/css/app.css`, con variables CSS en
  `:root` para adaptar la identidad institucional cuando el club la proporcione.
- Iconos SVG en línea desde `App\Core\Icons`.
- Diseño adaptable: escritorio, tableta y móvil.
- Accesibilidad: enlace «saltar al contenido», `aria-*` en menú y controles,
  foco visible y etiquetas asociadas a los campos.
- Hojas de estilo de impresión para informes.
- Todo el texto de la interfaz en español.

---

## 11. Qué no se hace

- No se usa Composer, Node.js ni frameworks.
- No se guardan estados derivados (saldos, posiciones) en la base de datos.
- No se confía en datos calculados por el navegador.
- No se sirven archivos privados desde una URL directa.
- No se mezcla lógica de presentación con acceso a datos.
