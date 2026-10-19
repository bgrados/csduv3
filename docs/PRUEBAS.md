# Pruebas y verificación

Este documento registra **qué se ha probado realmente**, con qué herramienta y con
qué resultado. No se anotan resultados de pruebas que no se hayan ejecutado.

> **Resumen de la Fase 2.** El código de la Fase 2 está escrito y **verificado de
> forma estática** (no es PHP ejecutándose). Las pruebas de ejecución siguen
> pendientes porque el equipo de desarrollo no tiene PHP 8.3 ni MariaDB. Por eso la
> Fase 2 figura como «implementada — pendiente de pruebas en servidor» y no como
> terminada.

---

## 1. Estado de las herramientas del entorno

| Herramienta | Estado en el equipo de desarrollo | Consecuencia |
| --- | --- | --- |
| PHP 8.3 (`php`) | **No instalado** (no está en `PATH`, ni en XAMPP, Laragon, WAMP ni `Program Files`) | No se pudo ejecutar `php -l` ni el servidor embebido |
| MySQL / MariaDB (`mysql`, `mariadb`) | **No instalados** | No se pudo importar el esquema ni ejecutar consultas reales |
| Git | No hay repositorio local en el directorio del proyecto | No se ejecutaron comandos de Git |
| Node.js | No instalado (no se necesita) | — |
| Verificación estática propia | Disponible: `tools/verificar_estatico.ps1` | Se ejecutó sobre los 80 archivos PHP del proyecto |

> **Importante:** mientras PHP no esté instalado, las pruebas de ejecución quedan
> pendientes. La solución recomendada es instalar PHP 8.3 en el equipo (o usar
> Laragon/XAMPP) y ejecutar las secciones 4, 5 y 6.

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
| Carga de páginas, PDO y sesiones | Sin PHP ni base de datos | Secciones 4.3 a 4.6 |
| Autenticación, permisos y CSRF en ejecución | Fase 2 sin servidor | Sección 5 |
| Portal público de alquileres | Fase 2 sin servidor | Sección 6 |
| Aplicación instalable (PWA) | Requiere HTTPS o `localhost` | Sección 7 |
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

## 8. Registro de resultados

| Fecha | Prueba | Resultado | Observaciones |
| --- | --- | --- | --- |
| Fase 1 | P1 a P10 | Correcto | Pruebas estáticas sobre el código generado |
| Fase 2 | Verificación estática (`verificar_estatico.ps1`) | Correcto | 80 archivos PHP, 0 hallazgos |
| Fase 2 | E1 a E4 | Correcto | Cruce de métodos, tablas y búsqueda de credenciales |
| Pendiente | Secciones 5, 6 y 7 | — | Requiere PHP 8.3 y MariaDB (y HTTPS o `localhost` para la PWA) |

---

## 9. Limitación conocida

La comprobación de solapamiento de horarios **no usa transacción ni
`SELECT ... FOR UPDATE`**. Dos envíos exactamente simultáneos podrían crear dos
solicitudes para el mismo horario; la marca `conflicto_detectado` ayuda a
detectarlo. Cerrar esa condición de carrera con una transacción y bloqueo queda
como tarea obligatoria de la Fase 4.
