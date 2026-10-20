# Pruebas y verificación

Este documento registra **qué se ha probado realmente**, con qué herramienta y con
qué resultado. No se anotan resultados de pruebas que no se hayan ejecutado.

> **Resumen de la Fase 2.** El código de la Fase 2 está escrito y **verificado de
> forma estática** (no es PHP ejecutándose). Las pruebas de ejecución siguen
> pendientes porque el equipo de desarrollo no tiene PHP 8.3 ni MariaDB. Por eso la
> Fase 2 figura como «implementada — pendiente de pruebas en servidor» y no como
> terminada.

> **Resumen del Corte 1 de Socios.** El padrón (listado, alta, ficha, edición,
> baja lógica y categorías) también está escrito y verificado de forma estática.
> Sus pruebas de ejecución están en la sección 8 y siguen pendientes por la misma
> razón: no hay PHP 8.3 ni MariaDB en el equipo de desarrollo.

---

## 1. Estado de las herramientas del entorno

| Herramienta | Estado en el equipo de desarrollo | Consecuencia |
| --- | --- | --- |
| PHP 8.3 (`php`) | **No instalado** (no está en `PATH`, ni en XAMPP, Laragon, WAMP ni `Program Files`) | No se pudo ejecutar `php -l` ni el servidor embebido |
| MySQL / MariaDB (`mysql`, `mariadb`) | **No instalados** | No se pudo importar el esquema ni ejecutar consultas reales |
| Git | Código publicado en `bgrados/csduv3` (rama `main`, commit `211d269`) | El envío del código no constituye una prueba funcional |
| Node.js | Instalado (v24), usado solo para capturas headless de revisión | No es dependencia de la aplicación ni del servidor |
| Verificación estática propia | Disponible: `tools/verificar_estatico.ps1` | Se ejecutó sobre los 80 archivos PHP de la Fase 2 y, tras el Corte 1 de Socios y el rediseño, sobre 86 archivos (excluyendo el script de servidor `tools/migrar.php`) |

> **Importante:** mientras PHP no esté instalado, las pruebas de ejecución quedan
> pendientes. La solución recomendada es instalar PHP 8.3 en el equipo (o usar
> Laragon/XAMPP) y ejecutar las secciones 5 a 8.

---

## 2. Verificación estática ejecutada (Fase 2)

Herramienta: `tools/verificar_estatico.ps1`, un analizador de texto propio que **no
sustituye a `php -l`**. Comprueba:

- Que cada archivo empiece con `<?php` y declare `strict_types=1`.
- Balance de paréntesis, llaves y corchetes dentro de los bloques PHP (ignorando
  comentarios y cadenas).
- Que el `namespace` y el nombre de clase coincidan con la ruta del archivo.
- Que cada `use App\...` apunte a un archivo existente.
- Que los controladores y métodos citados en `app/routes.php` existan.
- Que cada vista citada con `View::display()` / `View::render()` exista.
- Que las rutas de `require`/`include` con `__DIR__` o `APP_PATH` existan.
- Que las funciones auxiliares esperadas estén definidas en `app/Core/helpers.php`.

**Comando:**

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verificar_estatico.ps1
```

**Resultado de la última ejecución:**

| Métrica | Valor |
| --- | --- |
| Archivos PHP revisados | 80 |
| Hallazgos | 0 |

> La última ejecución, sobre el árbol con el Corte 1 de Socios y el rediseño UV3,
> revisó **86 archivos PHP con 0 hallazgos** al excluir `tools/migrar.php`, un
> script de servidor que tiene 2 hallazgos preexistentes (no empieza con `<?php`
> y no declara `strict_types`) y que no se modifica por estar fuera del alcance de
> la aplicación. Sigue sin sustituir a `php -l`.

Además se ejecutaron estas comprobaciones de texto:

| N.º | Prueba | Método | Resultado |
| --- | --- | --- | --- |
| E1 | Métodos de modelos citados por los controladores existen | Extracción de `function ...` de `app/Models/*.php` y de `app/Core/*.php` y cruce con las llamadas | **Correcto.** Todos los métodos usados (`Auth::can`, `Auth::canAny`, `Password::errores`, `RateLimiter::demasiadasSolicitudes`, `Audit::log`, `Usuario::contarAdministradoresActivos`, `SolicitudAlquiler::haySolicitudSolapada`, etc.) existen |
| E2 | Lista de tablas del diagnóstico coincide con el esquema | Conteo manual de `app/Controllers/DiagnosticoController.php` contra `database/01_esquema.sql` | **Correcto.** 40 tablas en ambos |
| E3 | El esquema sigue sin `DROP` ni `TRUNCATE` destructivo | Búsqueda de texto | **Correcto** |
| E4 | No hay credenciales reales nuevas | Búsqueda de contraseñas y tokens | **Correcto** |

> La verificación estática **no** demuestra que el código se ejecute sin errores:
> solo detecta errores frecuentes. La validación real es la de la sección 4.

---

## 3. Pruebas de la Fase 1 (registro histórico)

Fecha de ejecución: sesión de trabajo de la Fase 1 (33 archivos PHP).

| N.º | Prueba | Método | Resultado |
| --- | --- | --- | --- |
| P1 | Todos los archivos PHP tienen `declare(strict_types=1)` | Búsqueda de texto sobre los 33 archivos | **Correcto** |
| P2 | Llaves `{}` balanceadas | Conteo por archivo | **Correcto** |
| P3 | Paréntesis `()` balanceados | Conteo por archivo | **Correcto** |
| P4 | El esquema define las tablas esperadas | Conteo de `CREATE TABLE IF NOT EXISTS` | **Correcto.** 39 tablas en ese momento (hoy 40) |
| P5 | El esquema no destruye información | Búsqueda de `DROP` y `TRUNCATE` | **Correcto** |
| P6 | Ningún archivo SQL borra datos reales | Búsqueda de `DELETE` | **Correcto.** Solo en `04_eliminar_demo.sql`, filtrando por `DEMO-` |
| P7 | El archivo `.env` está excluido del control de versiones | Revisión de `.gitignore` | **Correcto** |
| P8 | No hay credenciales reales en el código ni en los documentos | Búsqueda de contraseñas, tokens y claves | **Correcto** |
| P9 | Los directorios sensibles están protegidos | Existencia de `.htaccess` de denegación | **Correcto** |
| P10 | La documentación mínima exigida existe | Revisión de `docs/` | **Correcto** |

---

## 4. Pruebas NO ejecutadas (y por qué)

| Prueba | Motivo | Cómo ejecutarla |
| --- | --- | --- |
| Sintaxis PHP (`php -l`) | PHP no instalado | Sección 4.1 |
| Importación del esquema SQL | Sin MariaDB | Sección 4.2 |
| Carga de páginas, PDO y sesiones | Sin PHP ni base de datos | Sección 5 |
| Autenticación, permisos y CSRF en ejecución | Fase 2 sin servidor | Sección 5 |
| Portal público de alquileres | Fase 2 sin servidor | Sección 6 |
| Aplicación instalable (PWA) | Requiere HTTPS o `localhost` | Sección 7 |
| Padrón de socios y categorías (Corte 1) | Sin PHP ni MariaDB en el equipo | Sección 8 |
| Reservas superpuestas (condición de carrera) | Fase 4 | — |
| Cálculo de pagos y saldos | Fases 4 y 5 | — |
| Fixture y clasificación | Fase 6 | — |

---

## 5. Lista de comprobación para cuando haya PHP y MariaDB

### 5.1 Sintaxis

```bash
# Linux/macOS
find app public config tools -name "*.php" -exec php -l {} \;

# Windows (PowerShell)
Get-ChildItem -Recurse -Filter *.php app,public,config,tools | ForEach-Object { php -l $_.FullName }
```

Debe mostrar «No syntax errors detected» en todos los archivos.

### 5.2 Instalación

1. Importar `database/01_esquema.sql` y `database/02_datos_iniciales.sql`.
   - Si venía de la Fase 1: ejecutar además
     `database/migrations/0001_solicitudes_alquiler.sql`.
2. Comprobar el número de tablas:

   ```sql
   SELECT COUNT(*) FROM information_schema.tables
   WHERE table_schema = DATABASE() AND table_name LIKE 'csduv3_%';
   ```

   Resultado esperado: **40**.

3. Comprobar los catálogos iniciales:

   ```sql
   SELECT COUNT(*) FROM csduv3_permisos;          -- esperado: 31
   SELECT COUNT(*) FROM csduv3_roles;             -- esperado: 7
   SELECT COUNT(*) FROM csduv3_roles_permisos;    -- esperado: 31 + 16 + 11 + 14 + 7 + 10 + 9
   ```

4. Crear el primer administrador: `php tools/crear_admin.php` y luego **eliminar**
   ese archivo del servidor.

### 5.3 Aplicación (base)

| N.º | Comprobación | Resultado esperado |
| --- | --- | --- |
| A1 | Abrir `/` | Portada pública del club |
| A2 | Abrir `/diagnostico` con `APP_ENV=development` | «Tablas del esquema: 40 de 40» y «Conexión: Conectada» |
| A3 | Abrir `/diagnostico` con `APP_ENV=production` | Error 404 |
| A4 | Abrir `/no-existe` | Página de error 404 con la plantilla del sistema |
| A5 | Solicitar `/app/Core/Database.php` desde el navegador | 403 o 404 |
| A6 | Solicitar `/storage/logs/` y `/tools/crear_admin.php` desde el navegador | 403 o 404 |
| A7 | Revisar `storage/logs/` | Se crea el archivo de registro sin datos sensibles |

### 5.4 Autenticación y sesión (Fase 2)

| N.º | Comprobación | Resultado esperado |
| --- | --- | --- |
| AU-1 | Abrir `/panel` sin sesión | Redirección a `/ingresar` |
| AU-2 | Ingresar con credenciales correctas | Entra al panel; el identificador de sesión cambia |
| AU-3 | Ingresar con contraseña incorrecta | Mensaje genérico, sin revelar si el usuario existe |
| AU-4 | Fallar varias veces seguidas | La cuenta se bloquea temporalmente y queda registrado en `csduv3_accesos` |
| AU-5 | Ingresar con una cuenta desactivada | Se rechaza el acceso |
| AU-6 | Ingresar un usuario recién creado por un administrador | Pide cambiar la contraseña antes de continuar |
| AU-7 | Cerrar sesión (`/salir`, POST) | Vuelve a la portada y `/panel` vuelve a pedir sesión |
| AU-8 | Abrir `/ingresar` con sesión iniciada | Redirige al panel (no muestra el formulario) |
| AU-9 | Enviar el formulario de ingreso sin token CSRF | Error 419 (sesión del formulario expirada) |
| AU-10 | Esperar a que caduque la sesión y enviar un formulario | Error 419 con mensaje en español |

### 5.5 Roles, permisos y usuarios (Fase 2)

| N.º | Comprobación | Resultado esperado |
| --- | --- | --- |
| RO-1 | Entrar con el rol **Consulta** a `/usuarios` | Se ve el listado; los botones de modificación no aparecen |
| RO-2 | Escribir directamente la URL de alta/edición de usuario con rol **Consulta** | Página 403 «acceso denegado» |
| RO-3 | Modificar la matriz de un rol en `/roles/permisos` | Los permisos se guardan en una sola transacción |
| RO-4 | Intentar editar el rol **Administrador del sistema** | La interfaz lo muestra en solo lectura |
| RO-5 | Desactivar la propia cuenta | Se rechaza con un mensaje claro |
| RO-6 | Intentar desactivar al último administrador activo | Se rechaza con un mensaje claro |
| RO-7 | Cambiar el propio rol | Se rechaza con un mensaje claro |
| RO-8 | Restablecer la contraseña de otro usuario | El usuario queda con «debe cambiar contraseña» |
| RO-9 | Vaciar la tabla de permisos en una copia y entrar | El panel avisa de instalación incompleta; no concede acceso total |
| RO-10 | Revisar `/auditoria` tras las acciones anteriores | Aparecen ingresos, denegaciones y cambios de usuarios y permisos |

### 5.6 Acceso directo por URL (control en servidor)

| N.º | Comprobación | Resultado esperado |
| --- | --- | --- |
| URL-1 | `GET /roles/permisos` con un rol sin `roles.gestionar` | 403 |
| URL-2 | `POST /usuarios/{id}/estado` con un rol sin `usuarios.gestionar` | 403 |
| URL-3 | `POST /solicitudes/{id}/rechazar` con un rol sin `alquileres.gestionar` | 403 |
| URL-4 | `GET /auditoria` con un rol sin `auditoria.ver` | 403 |

---

## 6. Pruebas del portal público (Fase 2)

| N.º | Comprobación | Resultado esperado |
| --- | --- | --- |
| PU-1 | Abrir `/` y `/alquiler` sin sesión | Se muestra el portal; no pide ingreso |
| PU-2 | Abrir `/alquiler` sin ambientes activos | Aviso de que todavía no hay ambientes publicados (no inventa datos) |
| PU-3 | Consultar disponibilidad de una fecha con horarios ocupados | Se listan solo horas y estado, sin nombres ni importes |
| PU-4 | Enviar una solicitud válida | Redirige a `/alquiler/solicitud/recibida/{codigo}` con un código `SOL-...` |
| PU-5 | Buscar la solicitud en `/solicitudes` | Aparece en estado **pendiente**; **no** existe ninguna fila en `csduv3_reservas` |
| PU-6 | Enviar una solicitud en un horario ya reservado | Se rechaza con un mensaje claro; no se guarda |
| PU-7 | Enviar dos solicitudes en el mismo horario libre | La segunda se guarda con «posible cruce» (`conflicto_detectado = 1`); ninguna reserva se crea |
| PU-8 | Enviar una fecha pasada o una hora de fin anterior a la de inicio | Se rechaza en el servidor, aunque el navegador lo haya permitido |
| PU-9 | Enviar con el campo trampa completo o en menos de 3 segundos | Se descarta sin crear registro |
| PU-10 | Enviar muchas solicitudes desde la misma IP | `RateLimiter` frena los envíos y muestra un aviso |
| PU-11 | Revisar en `/solicitudes`: en revisión, atender y rechazar (con motivo) | Cada acción cambia el estado y queda en `/auditoria` |
| PU-12 | Abrir un código de seguimiento inexistente | Página 404, sin revelar datos |

---

## 7. Pruebas de la aplicación instalable (Fase 2)

| N.º | Comprobación | Resultado esperado |
| --- | --- | --- |
| PP-1 | Abrir el sitio en `localhost` o HTTPS | `manifest.webmanifest` se descarga con `application/manifest+json` |
| PP-2 | Chrome (Android o PC) | Aparece la opción «Instalar aplicación» y el icono correcto |
| PP-3 | DevTools → Application → Service Workers | El service worker queda **activo** |
| PP-4 | DevTools → Application → Cache Storage | Solo aparecen estáticos públicos (`app.css`, `app.js`, iconos, `offline.html`, logotipo) |
| PP-5 | Usar el panel y luego revisar Cache Storage | **No** hay HTML del panel ni respuestas con datos de sesión, socios o caja |
| PP-6 | Activar «sin conexión» y recargar | Se muestra `offline.html`; el portal no muestra datos ficticios |
| PP-7 | Enviar una solicitud sin conexión | El envío falla con un aviso; **no** se guarda nada en el servidor |

---

## 8. Pruebas del Corte 1 de Socios (padrón y categorías)

> **Estado: todos los casos de esta sección están pendientes de ejecución.** No se
> ha ejecutado ninguno: el equipo de desarrollo no tiene PHP 8.3 ni MariaDB. Los
> resultados se anotarán en la sección 9 cuando se ejecuten en un servidor de
> pruebas.
>
> **Nunca ejecutar estas pruebas contra producción.**

### 8.0 Preparación común

**Base de datos de pruebas**

1. Crear una base **exclusiva de pruebas** (preferiblemente desechable) e importar
   `database/01_esquema.sql` y `database/02_datos_iniciales.sql`.
2. Confirmar que hay 40 tablas y que `csduv3_categorias_socio` está vacía.
3. Anotar los conteos iniciales:

```sql
SELECT COUNT(*) AS socios     FROM csduv3_socios;
SELECT COUNT(*) AS bajas      FROM csduv3_socios_bajas;
SELECT COUNT(*) AS categorias FROM csduv3_categorias_socio;
SELECT COUNT(*) AS auditoria  FROM csduv3_auditoria;
```

**Usuarios de prueba**

| Usuario | Rol | Permisos de socios |
| --- | --- | --- |
| `admin.prueba` | Administrador del sistema | Todos |
| `secretaria.prueba` | Secretaría | `socios.ver`, `socios.crear`, `socios.editar`, `socios.baja` |
| `consulta.prueba` | Consulta | Solo `socios.ver` |
| `sin.socios` | Responsable de deportes (o rol de prueba) | Ninguno |

**Datos ficticios**

- Códigos con el prefijo `SOC-PRUEBA-` (por ejemplo `SOC-PRUEBA-01`). Cumplen la
  regla `^[A-Za-z0-9._-]+$` y no alteran el correlativo `SOC-0001`, que solo
  considera sufijos numéricos.
- Documentos, teléfonos y correos inventados (`99999901`, `900000001`,
  `ana.prueba@example.test`). **No usar datos de socios reales.**

**Limpieza**

- Borrar primero las filas de `csduv3_socios_bajas` de los socios de prueba y
  después los socios: la clave foránea de bajas es `ON DELETE RESTRICT`.
- Alternativa recomendada: descartar la base de pruebas completa.

**CSRF y sesión**

Todas las peticiones POST requieren `_csrf_token` válido. Para los casos que
manipulan campos con `curl` o DevTools, obtener el token del formulario y
conservar la cookie de sesión. El token es estable durante la sesión
(`Csrf::token()` no lo rota en cada petición), por lo que un reenvío del mismo
POST conserva el token.

> **Todos los casos requieren una base de datos de pruebas**, aunque solo sea para
> autenticar la sesión y comprobar que no se modificaron datos.

### 8.1 Acceso al listado (con y sin permiso)

**PS-01 · Acceso con permiso**
- **Precondiciones:** sesión iniciada como `admin.prueba`; base de pruebas disponible.
- **Pasos:** 1) Entrar a `/panel`. 2) Pulsar «Socios» en el menú lateral. 3) Repetir con `GET /socios` directo.
- **Resultado esperado:** respuesta 200; encabezado «Socios»; enlace del menú activo; botones «Categorías» y «Nuevo socio»; tabla del padrón o estado vacío; sin errores SQL en pantalla ni en `storage/logs`.
- **¿Requiere BD de pruebas?** Sí.

**PS-02 · Acceso sin el permiso `socios.ver`**
- **Precondiciones:** sesión como `sin.socios` (rol sin ningún permiso `socios.*`).
- **Pasos:** 1) Revisar el menú lateral. 2) Abrir directamente `GET /socios` y `GET /socios/categorias`.
- **Resultado esperado:** el menú no muestra «Socios»; ambas URL responden 403 con «No tiene permisos para acceder a esta sección.»; no se muestra ningún dato del padrón; se registra `acceso_denegado` en `csduv3_auditoria`.
- **¿Requiere BD de pruebas?** Sí.

**PS-03 · Acceso sin sesión**
- **Precondiciones:** navegador sin sesión iniciada.
- **Pasos:** abrir `GET /socios`, `GET /socios/nuevo` y `GET /socios/categorias`.
- **Resultado esperado:** redirección 302 a `/ingresar`; no se muestra ningún dato del padrón.
- **¿Requiere BD de pruebas?** Sí.

**PS-04 · Rol de solo lectura**
- **Precondiciones:** sesión como `consulta.prueba` (solo `socios.ver`); al menos un socio existente.
- **Pasos:** 1) `GET /socios` y `GET /socios/{id}`. 2) `GET /socios/nuevo` y `GET /socios/{id}/editar`. 3) Enviar `POST /socios`, `POST /socios/{id}`, `POST /socios/{id}/baja` y `POST /socios/categorias` con un token CSRF válido.
- **Resultado esperado:** el listado y la ficha responden 200 sin botones de alta, edición ni baja; las peticiones de escritura responden 403 y no modifican la base.
- **¿Requiere BD de pruebas?** Sí.

### 8.2 Listado, búsqueda, filtros y paginación

**PS-05 · Listado vacío**
- **Precondiciones:** base de pruebas sin socios; sesión con `socios.ver` (idealmente `secretaria.prueba` para ver el botón de alta).
- **Pasos:** `GET /socios`.
- **Resultado esperado:** estado vacío «Todavía no hay socios registrados»; con permiso de alta, botón «Registrar el primer socio»; sin tabla ni paginación; los filtros permanecen visibles; sin error.
- **¿Requiere BD de pruebas?** Sí.

**PS-06 · Búsqueda sin resultados**
- **Precondiciones:** al menos un socio registrado.
- **Pasos:** `GET /socios?q=ZZZNOEXISTE`.
- **Resultado esperado:** estado «Sin coincidencias»; el campo Buscar conserva `ZZZNOEXISTE`; sin paginación; sin error.
- **¿Requiere BD de pruebas?** Sí.

**PS-07 · Búsqueda por campos y filtros**
- **Precondiciones:** socios ficticios conocidos: A (`SOC-PRUEBA-01`, DNI `99999901`, nombres «Ana», apellidos «Zeta», estado `activo`, categoría X); B (`SOC-PRUEBA-02`, DNI `99999902`, estado `baja`, categoría Y); C (sin documento, `activo`, sin categoría).
- **Pasos:** 1) Buscar por código parcial (`PRUEBA-01`). 2) Buscar por documento (`99999902`). 3) Buscar por nombre (`Ana`) y por apellido en minúsculas (`zeta`). 4) Filtrar `estado=activo` y luego `estado=baja`. 5) Filtrar `categoria=X`. 6) Combinar `q=Ana&estado=activo&categoria=X`. 7) Pulsar «Limpiar».
- **Resultado esperado:** cada búsqueda devuelve solo las coincidencias esperadas e ignora mayúsculas/minúsculas; los filtros se combinan con AND; «Limpiar» vuelve a `/socios` sin parámetros; el orden por defecto es apellidos y luego nombres.
- **¿Requiere BD de pruebas?** Sí.

**PS-08 · Paginación y conservación de filtros**
- **Precondiciones:** 21 socios ficticios que coincidan con `q=SOC-PRUEBA`.
- **Pasos:** 1) `GET /socios?q=SOC-PRUEBA`. 2) Revisar la paginación. 3) Abrir la página 2. 4) Añadir `estado=activo` y navegar entre páginas. 5) `GET /socios?pagina=abc`.
- **Resultado esperado:** página 1 con 20 filas y el texto «Página 1 de 2 · 21 socios»; página 2 con 1 fila y el enlace «Anterior»; al paginar se conservan `q`, `estado` y `categoria`; `pagina=abc` se trata como 1; sin error.
- **¿Requiere BD de pruebas?** Sí.

### 8.3 Alta de socio

**PS-09 · Alta válida y código sugerido**
- **Precondiciones:** sesión como `secretaria.prueba`; base sin códigos `SOC-000N` (o anotar el máximo existente); categoría activa opcional.
- **Pasos:** 1) `GET /socios/nuevo` y verificar el código sugerido. 2) Completar: nombres «Ana Prueba», apellidos «Zeta Prueba», tipo DNI, número `99999901`, nacimiento `1990-05-12`, sexo F, teléfono `900000001`, correo `ana.prueba@example.test`, ingreso `2026-01-15`, categoría, estado «Activo» y observaciones ficticias. 3) Enviar.
- **Resultado esperado:** redirección 302 a `/socios/{id}`; aviso «El socio quedó registrado con el código SOC-0001.» (o el siguiente correlativo si ya existe `SOC-0007`, por ejemplo `SOC-0008`); la ficha muestra los datos; la fila de `csduv3_socios` tiene `creado_por` y `actualizado_por` iguales al usuario; auditoría `crear_socio` con «Socio registrado: SOC-0001».
- **¿Requiere BD de pruebas?** Sí.

**PS-10 · Validaciones obligatorias, longitudes y formatos**
- **Precondiciones:** la misma de PS-09.
- **Pasos:** enviar el formulario: 1) con código vacío; 2) con nombres vacíos; 3) con apellidos de un solo carácter; 4) con correo `abc`; 5) con fecha de nacimiento posterior a hoy; 6) con código `SOC 01` (espacio); 7) con código de 31 caracteres.
- **Resultado esperado:** cada envío vuelve a `/socios/nuevo` con los errores en español («El campo código de socio es obligatorio.», «El campo nombres es obligatorio.», «El campo apellidos debe tener al menos 2 caracteres.», «El campo correo electrónico debe ser un correo electrónico válido.», «La fecha de nacimiento no puede ser posterior a hoy.», «El formato del campo código de socio no es válido.», «El campo código de socio no debe superar 30 caracteres.»); se repueblan los campos; no se inserta ninguna fila.
- **¿Requiere BD de pruebas?** Sí.

**PS-11 · Valores no permitidos enviados directamente**
- **Precondiciones:** la misma de PS-09; acceso a `curl` o DevTools.
- **Pasos:** `POST /socios` con token válido: 1) omitiendo `tipo_documento` y `estado`; 2) con `tipo_documento=XYZ`; 3) con `estado=baja`; 4) con `categoria_id=999999`.
- **Resultado esperado:** errores «El campo tipo de documento es obligatorio.», «El valor seleccionado en tipo de documento no es válido.», «El valor seleccionado en estado no es válido.» y «El valor seleccionado en categoría no existe.»; ningún caso inserta una fila.
- **¿Requiere BD de pruebas?** Sí.

### 8.4 Unicidad de código y documento

**PS-12 · Código duplicado**
- **Precondiciones:** existe el socio `SOC-PRUEBA-10`.
- **Pasos:** 1) Crear otro socio con el código `SOC-PRUEBA-10`. 2) Repetir con `soc-prueba-10`.
- **Resultado esperado:** ambos envíos se rechazan con «Ya existe un socio con el código indicado.»; no se insertan filas; se conservan los datos ingresados; el conteo de socios no cambia.
- **¿Requiere BD de pruebas?** Sí.

**PS-13 · Documento duplicado y pares permitidos**
- **Precondiciones:** socio A con DNI `99999901`; socio C sin documento.
- **Pasos:** 1) Crear otro socio con DNI `99999901` y código distinto. 2) Crear un socio con número `99999901` pero tipo `CE`. 3) Crear un segundo socio sin documento. 4) Editar A para asignarle el documento de otro socio. 5) Editar A conservando su propio documento.
- **Resultado esperado:** 1) se rechaza con «Ya existe un socio con ese tipo y número de documento.»; 2) se acepta (el par tipo/número es distinto); 3) se acepta (la clave única permite varios `NULL`); 4) se rechaza; 5) se acepta (la comprobación excluye al propio socio).
- **¿Requiere BD de pruebas?** Sí.

### 8.5 Ficha inexistente

**PS-14 · Socio inexistente**
- **Precondiciones:** sesión con `socios.ver`.
- **Pasos:** abrir `GET /socios/999999`, `GET /socios/0` y `GET /socios/abc`.
- **Resultado esperado:** respuesta 404 con la plantilla de error del sistema y el mensaje «El socio indicado no existe.»; nunca un error 500; no se revelan datos de otros socios.
- **¿Requiere BD de pruebas?** Sí.

### 8.6 Edición y reactivación

**PS-15 · Edición válida y unicidad al editar**
- **Precondiciones:** socio activo; sesión como `secretaria.prueba`.
- **Pasos:** 1) Abrir la ficha, pulsar «Editar ficha» y cambiar teléfono, dirección y categoría. 2) Guardar conservando código y documento. 3) Volver a editar y poner el código de otro socio existente.
- **Resultado esperado:** el primer guardado redirige a la ficha con «La ficha del socio se actualizó.»; los datos cambian, `updated_at` avanza y `actualizado_por` queda con el usuario; auditoría `editar_socio` con «Ficha actualizada: SOC-…». El segundo guardado se rechaza con el mensaje de código duplicado y no modifica nada.
- **¿Requiere BD de pruebas?** Sí.

**PS-16 · Reactivación de un socio dado de baja**
- **Precondiciones:** socio en estado `baja` con una fila en `csduv3_socios_bajas`.
- **Pasos:** 1) Abrir la ficha y pulsar «Editar ficha». 2) Verificar que el selector de estado incluye «Baja» (valor actual) además de Activo, Pendiente e Inactivo. 3) Elegir «Activo» y guardar.
- **Resultado esperado:** redirección a la ficha con «El socio quedó reactivado.»; `estado='activo'`; auditoría `reactivar_socio`; la fila de baja se conserva; la ficha muestra «Última baja registrada» y la nota «El socio fue reactivado posteriormente; esta baja se conserva como historial.»; el listado lo muestra como Activo.
- **¿Requiere BD de pruebas?** Sí.

**PS-17 · Guardar una baja sin cambiar el estado**
- **Precondiciones:** socio en estado `baja` con una fila de baja.
- **Pasos:** editar la ficha, dejar seleccionado «Baja», cambiar solo el teléfono y guardar.
- **Resultado esperado:** los cambios se guardan; el estado sigue siendo `baja`; se registra `editar_socio` (no `reactivar_socio`); no se crea otra fila en `socios_bajas`.
- **¿Requiere BD de pruebas?** Sí.

### 8.7 Baja lógica e historial

**PS-18 · Baja con historial y conservación del socio**
- **Precondiciones:** socio activo; sesión como `secretaria.prueba`; anotar su `id` y el número de filas de `csduv3_socios_bajas` para ese socio.
- **Pasos:** 1) Abrir la ficha y usar el formulario «Registrar baja». 2) Fecha de hoy, motivo «Baja de prueba», marcar «Es una renuncia voluntaria». 3) Enviar.
- **Resultado esperado:** redirección a la ficha con «La baja quedó registrada. El historial del socio se conserva.»; `csduv3_socios_bajas` gana exactamente una fila con `socio_id`, `fecha_baja`, `motivo`, `es_renuncia=1` y `registrado_por`; la fila de `csduv3_socios` sigue existiendo con `estado='baja'`; el listado lo muestra con la insignia «Baja» y el filtro `estado=baja` lo encuentra; auditoría `baja_socio` con «Socio dado de baja: SOC-…».
- **¿Requiere BD de pruebas?** Sí.

**PS-19 · Validaciones de la baja**
- **Precondiciones:** un socio activo y otro ya dado de baja.
- **Pasos:** 1) Baja con fecha de mañana. 2) Baja con un motivo de 301 caracteres (POST directo; el formulario limita a 300). 3) Segunda baja sobre el socio ya dado de baja.
- **Resultado esperado:** 1) «La fecha de baja no puede ser posterior a hoy.»; 2) «El motivo de la baja no debe superar los 300 caracteres.»; 3) «Este socio ya figura dado de baja.»; ningún caso inserta filas ni cambia el estado del socio.
- **¿Requiere BD de pruebas?** Sí.

### 8.8 Categorías

**PS-20 · Listado vacío**
- **Precondiciones:** `csduv3_categorias_socio` vacía; sesión con `socios.ver`.
- **Pasos:** `GET /socios/categorias` con `consulta.prueba` y con `secretaria.prueba`.
- **Resultado esperado:** estado vacío «Todavía no hay categorías»; el formulario de alta solo aparece con `socios.editar`; sin tabla.
- **¿Requiere BD de pruebas?** Sí.

**PS-21 · Crear categoría**
- **Precondiciones:** sesión como `secretaria.prueba`.
- **Pasos:** crear la categoría «Socio activo (prueba)», descripción «Categoría ficticia», orden 2.
- **Resultado esperado:** redirección a `/socios/categorias` con «La categoría quedó creada.»; la tabla muestra la categoría como Activa con 0 socios; auditoría `crear_categoria_socio` con «Categoría creada: Socio activo (prueba)»; la categoría aparece en el selector del formulario de socio.
- **¿Requiere BD de pruebas?** Sí.

**PS-22 · Validaciones y nombre duplicado en categorías**
- **Precondiciones:** sesión como `secretaria.prueba`.
- **Pasos:** 1) Crear con nombre vacío. 2) Con nombre de un carácter. 3) Con descripción de 201 caracteres. 4) Con orden `0`, `10000` y `abc`. 5) Con orden vacío. 6) Con el nombre de una categoría existente, cambiando mayúsculas/minúsculas.
- **Resultado esperado:** 1 y 2 → error de obligatorio/mínimo; 3 → error de máximo; 4 → «El orden debe ser un número entre 1 y 9999.» o «El campo orden debe ser un número entero.»; 5 → se acepta y queda en 1; 6 → «Ya existe una categoría con ese nombre.» (comparación sin distinguir mayúsculas); sin filas nuevas en los casos rechazados.
- **Nota:** los valores fuera de rango del paso 4 deben enviarse con un POST directo, porque el formulario los bloquea en el navegador (`type="number"`, `min="1"`, `max="9999"`).
- **¿Requiere BD de pruebas?** Sí.

**PS-23 · Desactivar categoría**
- **Precondiciones:** categoría con al menos un socio asignado; sesión como `secretaria.prueba`.
- **Pasos:** pulsar «Desactivar».
- **Resultado esperado:** la categoría pasa a «Inactiva»; auditoría `desactivar_categoria_socio`; el socio conserva su `categoria_id` y el contador de socios no cambia; en el formulario de socio la categoría inactiva aparece deshabilitada para nuevas asignaciones, salvo para el socio que ya la tiene; el filtro del listado la muestra con «(inactiva)».
- **¿Requiere BD de pruebas?** Sí.

**PS-24 · Activar categoría**
- **Precondiciones:** categoría inactiva; sesión como `secretaria.prueba`.
- **Pasos:** pulsar «Activar».
- **Resultado esperado:** vuelve a «Activa»; auditoría `activar_categoria_socio`; se puede asignar de nuevo en el formulario de socio.
- **¿Requiere BD de pruebas?** Sí.

### 8.9 Protección CSRF

**PS-25 · POST sin token CSRF**
- **Precondiciones:** sesión con permisos; al menos un socio y una categoría.
- **Pasos:** enviar sin `_csrf_token`, con la cookie de sesión, a: `POST /socios`, `POST /socios/{id}`, `POST /socios/{id}/baja`, `POST /socios/categorias` y `POST /socios/categorias/{id}/estado`.
- **Resultado esperado:** todas responden 419 con «La sesión del formulario expiró. Recargue la página e inténtelo nuevamente.»; no se modifica la base; `storage/logs` registra «Token CSRF ausente o inválido».
- **¿Requiere BD de pruebas?** Sí.

**PS-26 · Token inválido o sesión expirada**
- **Precondiciones:** la misma de PS-25.
- **Pasos:** 1) Enviar los mismos POST con `_csrf_token=invalido`. 2) Destruir la sesión (o esperar su caducidad) y reenviar un formulario.
- **Resultado esperado:** en ambos casos, 419; ningún cambio en la base.
- **¿Requiere BD de pruebas?** Sí.

### 8.10 Auditoría

**PS-27 · Acciones registradas**
- **Precondiciones:** haber ejecutado PS-09 (alta), PS-15 (edición), PS-16 (reactivación), PS-18 (baja), PS-21 (crear categoría), PS-23 (desactivar) y PS-24 (activar).
- **Pasos:** ejecutar:

```sql
SELECT id, accion, entidad_tipo, entidad_id, descripcion, datos
FROM csduv3_auditoria
WHERE entidad_tipo IN ('socio', 'categoria_socio')
ORDER BY id DESC;
```

- **Resultado esperado:** aparecen `crear_socio`, `editar_socio`, `reactivar_socio`, `baja_socio`, `crear_categoria_socio`, `desactivar_categoria_socio` y `activar_categoria_socio`, con `entidad_tipo` y `entidad_id` correctos, `usuario_id` del actor y descripciones que solo contienen el código del socio o el nombre de la categoría.
- **¿Requiere BD de pruebas?** Sí.

**PS-28 · Sin documentos ni datos personales**
- **Precondiciones:** haber ejecutado los casos anteriores con los datos ficticios de 8.0.
- **Pasos:** 1) Buscar el documento, teléfono, correo, dirección y motivo de la baja en la auditoría:

```sql
SELECT COUNT(*) FROM csduv3_auditoria
WHERE descripcion LIKE '%99999901%'
   OR descripcion LIKE '%900000001%'
   OR descripcion LIKE '%ana.prueba@example.test%'
   OR descripcion LIKE '%Baja de prueba%'
   OR COALESCE(datos, '') LIKE '%99999901%';
```

2) Buscar los mismos valores en `storage/logs/`.

- **Resultado esperado:** ambas búsquedas devuelven 0 coincidencias; `datos` es `NULL` en las acciones del padrón; el mensaje `baja_socio` no incluye el motivo; la IP y el agente de usuario sí pueden aparecer por ser datos técnicos.
- **¿Requiere BD de pruebas?** Sí.

### 8.11 Doble envío de la baja

**PS-29 · Reenvío secuencial del mismo POST**
- **Precondiciones:** socio activo; sesión como `secretaria.prueba`; formulario de baja abierto; capturar el POST completo (DevTools → «Copy as cURL») con `_csrf_token` y cookie de sesión.
- **Pasos:** 1) Enviar el POST una vez y esperar la respuesta. 2) Reenviar exactamente el mismo POST.
- **Resultado esperado:** el primero crea una fila en `csduv3_socios_bajas` y deja el socio en `baja`; el segundo redirige a la ficha con «Este socio ya figura dado de baja.»; el número de bajas de ese socio sigue siendo 1; el estado sigue en `baja`; solo existe una auditoría `baja_socio` para esa baja. No se duplica el historial.
- **¿Requiere BD de pruebas?** Sí.

**PS-30 · Envío simultáneo desde dos sesiones (limitación conocida)**
- **Precondiciones:** socio activo; dos sesiones distintas con `socios.baja` (dos usuarios o dos navegadores con usuarios diferentes); capturar un POST válido de cada sesión.
- **Pasos:** lanzar los dos POST en paralelo (por ejemplo, dos `curl` simultáneos). Repetir el intento varias veces sobre socios de prueba distintos.
- **Resultado esperado (comportamiento actual):** **no está garantizado**. Si la segunda petición lee el estado después de que la primera confirme, se rechaza y queda 1 fila. Si ambas leen `activo` antes de que la otra confirme, ambas insertan y pueden quedar 2 filas en `csduv3_socios_bajas` y 2 auditorías `baja_socio`; el socio termina en `baja` en cualquier caso. El comportamiento deseado es 1 sola fila.
- **Nota:** `Socio::registrarBaja()` inserta la baja y actualiza el estado dentro de una transacción, pero **no vuelve a comprobar el estado dentro de la transacción ni usa `SELECT ... FOR UPDATE`**. Es una limitación conocida; la corrección requiere bloquear la fila del socio y reverificar el estado. No conviene una clave única sobre `socio_id`, porque un socio puede ser dado de baja más de una vez a lo largo de su historia (reactivación incluida). Dos peticiones de la **misma** sesión suelen serializarse por el bloqueo de sesión de PHP, pero no debe tomarse como garantía.
- **¿Requiere BD de pruebas?** Sí.

### 8.12 Regresión de inicio de sesión y búsquedas (parámetros PDO)

> Estos casos verifican la corrección de parámetros nombrados repetidos. Con
> `PDO::ATTR_EMULATE_PREPARES => false`, repetir `:q` o `:login` en una consulta
> produce `SQLSTATE[HY093]` («Invalid parameter number»). La corrección usó
> `:q1`…`:q4` y `:login`/`:correo`. **Ninguno de estos casos se ha ejecutado.**

**PS-31 · Inicio de sesión con usuario y con correo**
- **Precondiciones:** usuario `admin.prueba` activo, con correo configurado y contraseña conocida.
- **Pasos:** 1) Ingresar con el nombre de usuario. 2) Cerrar sesión. 3) Ingresar con el correo electrónico. 4) Intentar con contraseña incorrecta.
- **Resultado esperado:** 1 y 3 entran al panel; 4 muestra el mensaje genérico de credenciales; no aparece `SQLSTATE[HY093]` en pantalla ni en `storage/logs`; `csduv3_accesos` registra los intentos.
- **¿Requiere BD de pruebas?** Sí.

**PS-32 · Búsqueda de usuarios**
- **Precondiciones:** al menos dos usuarios; sesión con `usuarios.gestionar`.
- **Pasos:** `GET /usuarios?q=...` buscando por nombres, apellidos, nombre de usuario y correo; combinar con paginación.
- **Resultado esperado:** resultados filtrados correctamente, sin `HY093`, con paginación funcional.
- **¿Requiere BD de pruebas?** Sí.

**PS-33 · Búsqueda de solicitudes**
- **Precondiciones:** al menos una solicitud del portal; sesión con `alquileres.ver`.
- **Pasos:** `GET /solicitudes?q=...` buscando por código, nombre del solicitante, teléfono y correo; combinar con el filtro de estado.
- **Resultado esperado:** resultados filtrados, sin `HY093`, con paginación funcional.
- **¿Requiere BD de pruebas?** Sí.

**PS-34 · Búsqueda de auditoría**
- **Precondiciones:** auditoría con registros; sesión con `auditoria.ver`.
- **Pasos:** `GET /auditoria?q=...` buscando por descripción, acción, nombres y apellidos del usuario; combinar con el filtro de acción.
- **Resultado esperado:** resultados filtrados, sin `HY093`, con paginación funcional.
- **¿Requiere BD de pruebas?** Sí.

**PS-35 · Búsqueda de socios y revisión de logs**
- **Precondiciones:** socios ficticios; sesión con `socios.ver`.
- **Pasos:** 1) `GET /socios?q=...` buscando por código, documento, nombres y apellidos. 2) Combinar con estado y categoría. 3) Revisar `storage/logs/`.
- **Resultado esperado:** resultados filtrados, sin `HY093`; los logs no contienen «Invalid parameter number» ni «SQLSTATE[HY093]».
- **¿Requiere BD de pruebas?** Sí.

---

## 9. Registro de resultados

| Fecha | Prueba | Resultado | Observaciones |
| --- | --- | --- | --- |
| Fase 1 | P1 a P10 | Correcto | Pruebas estáticas sobre el código generado |
| Fase 2 | Verificación estática (`verificar_estatico.ps1`) | Correcto | 80 archivos PHP, 0 hallazgos |
| Fase 2 | E1 a E4 | Correcto | Cruce de métodos, tablas y búsqueda de credenciales |
| Corte 1 de Socios | Verificación estática (`verificar_estatico.ps1`) | Correcto | 86 archivos PHP, 0 hallazgos (excluye `tools/migrar.php`); no sustituye a `php -l` |
| Rediseño UV3 | Verificación estática (`verificar_estatico.ps1`) | Correcto | 86 archivos PHP, 0 hallazgos; CSS con 282/282 llaves; capturas headless de referencia a 390, 768 y 1440 px; sin pruebas en navegador real ni dispositivos |
| Producción | Verificación HTTP (2026-10-19) | **Fallo** | Rediseño visible (tema `#063B22`, `logo.jpg`, CSS y SW v3); la base de datos no responde: portada con mantenimiento y `/alquiler` 503. Pendiente restaurar la BD y rotar la contraseña expuesta. |
| Pendiente | Secciones 5 a 8 (incluye PS-01 a PS-35) | — | Requiere PHP 8.3 y MariaDB; las pruebas de socios requieren una base de datos de pruebas |

---

## 10. Limitaciones conocidas

La comprobación de solapamiento de horarios **no usa transacción ni
`SELECT ... FOR UPDATE`**. Dos envíos exactamente simultáneos podrían crear dos
solicitudes para el mismo horario; la marca `conflicto_detectado` ayuda a
detectarlo. Cerrar esa condición de carrera con una transacción y bloqueo queda
como tarea obligatoria de la Fase 4.

La baja de socio registra el historial y el cambio de estado dentro de una
transacción, pero **no vuelve a comprobar el estado dentro de la transacción ni
usa `SELECT ... FOR UPDATE`**. El reenvío secuencial se rechaza, pero dos envíos
realmente simultáneos desde sesiones distintas podrían insertar dos filas en
`csduv3_socios_bajas` (véanse PS-29 y PS-30). La corrección requiere bloquear la
fila del socio y reverificar su estado antes de insertar.

La identidad visual UV3 se revisó de forma estática (hoja de estilos y
plantillas) y con capturas headless de una vista de referencia a 390, 768 y
1440 px. **No se validó en un navegador real ni en dispositivos físicos**; esa
revisión queda pendiente y debe hacerse sobre un servidor de pruebas, nunca
sobre producción.
