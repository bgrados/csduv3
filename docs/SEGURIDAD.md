# Seguridad

Medidas de seguridad implementadas en el sistema y recomendaciones operativas.

---

## 1. Medidas ya implementadas en el código

| Medida | Implementación |
| --- | --- |
| Consultas preparadas | PDO con `ATTR_EMULATE_PREPARES = false`. No se concatenan valores en SQL |
| Contraseñas | `password_hash()` con el algoritmo por defecto y verificación con `password_verify()` |
| CSRF | Clase `App\Core\Csrf`: token por sesión, `hash_equals()`, campo oculto en cada formulario |
| XSS | Escape obligatorio de toda salida con la función `e()` |
| Validación en servidor | Clase `App\Core\Validator` con reglas declarativas |
| Sesiones | Cookie `HttpOnly`, `SameSite=Lax`, `Secure` cuando hay HTTPS, regeneración del identificador al iniciar sesión |
| Errores | Detalle técnico solo con `APP_DEBUG=true`; en producción mensaje genérico y registro en archivo |
| Archivos sensibles | `.htaccess` de denegación en `app/`, `config/`, `database/`, `docs/` y `storage/` |
| Cabeceras | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` |
| Enrutamiento | Un único punto de entrada (`public/index.php`) |
| Registro | `App\Core\Logger` enmascara contraseñas, tokens y claves antes de escribir en el archivo |
| Transacciones | `Database::transaction()` para operaciones financieras y de reservas |
| Autenticación | `App\Core\Auth`: verificación con `password_verify()`, sesión regenerada al ingresar y permiso recalculado en cada petición |
| Autorización | `App\Middleware\PermissionMiddleware` exige el permiso **en el servidor** antes de ejecutar el controlador |
| CSRF global | `App\Middleware\CsrfMiddleware` se aplica a **todas** las rutas: ninguna petición POST/PUT/PATCH/DELETE se procesa sin token válido |
| Límite de solicitudes | `App\Core\RateLimiter` limita los envíos del portal público por conexión (tabla de respaldo, no requiere Redis) |
| Cuenta inactiva | Un usuario con `activo = 0` no puede iniciar sesión aunque la contraseña sea correcta |
| Cambio forzado de contraseña | Las cuentas creadas o con contraseña restablecida por un administrador quedan con `debe_cambiar_password = 1` |
| Protección contra bloqueo | No se puede desactivar la propia cuenta, cambiar el propio rol, ni desactivar al último administrador activo |
| Rol administrador protegido | El rol «Administrador del sistema» conserva siempre todos los permisos y no puede modificarse desde la interfaz |
| Caché del navegador | El service worker **solo** almacena archivos estáticos públicos; las páginas HTML y todas las rutas del panel quedan fuera de la caché |

### Lo que la Fase 2 todavía **no** cubre

- **Condiciones de carrera en reservas.** La comprobación de solapamiento no usa
  transacción ni `SELECT ... FOR UPDATE`. Dos envíos exactamente simultáneos
  podrían crear dos solicitudes para el mismo horario. El sistema lo mitiga
  marcando `conflicto_detectado`, pero **cerrarlo es tarea de la Fase 4**, antes de
  crear reservas desde el panel.
- **Bloqueo por IP.** El límite de intentos de acceso es por cuenta
  (`csduv3_accesos`); el bloqueo por dirección IP es una mejora pendiente.
- **Verificación en dos pasos** para el rol administrador.

---

## 2. Credenciales: qué no debe hacerse

- **Nunca** subir el archivo `.env` al repositorio ni a un directorio público.
- **Nunca** escribir contraseñas en `config/*.php`, JavaScript, HTML o plantillas.
- **Nunca** crear usuarios con contraseñas fijas en el código.
- **Nunca** reutilizar credenciales de otro proyecto del club.
- **Nunca** crear un instalador público que pueda reiniciar la base de datos.

La aplicación lee todos los secretos desde `.env`, que está excluido en
`.gitignore`.

---

## 3. Primer usuario administrador

Se implementó en la Fase 2 mediante `tools/crear_admin.php`, un script **solo de
línea de comandos**:

1. Si se abre por el navegador, responde 404 y no hace nada.
2. La carpeta `tools/` está además bloqueada con `.htaccess`.
3. **No acepta la contraseña como argumento** (evita que quede en el historial del
   shell): la solicita de forma interactiva y, cuando el sistema lo permite, sin
   mostrarla en pantalla.
4. Aplica las mismas reglas de contraseña que la interfaz web.
5. Crea la cuenta con `password_hash()` y con la marca «debe cambiar contraseña».
6. Se detiene si ya existe un administrador activo, salvo que se indique
   `--permitir-otro`.
7. **Se elimina del servidor inmediatamente después de usarlo.**

---

## 4. Roles y permisos

- Los roles iniciales están en `csduv3_roles` y sus permisos en
  `csduv3_permisos` / `csduv3_roles_permisos` (7 roles y 31 permisos cargados por
  `02_datos_iniciales.sql`).
- Los permisos se verifican **en el servidor** en cada petición, mediante el
  middleware declarado junto a la ruta. Ocultar un botón no es un control de
  seguridad: un usuario sin permiso recibe «acceso denegado» (página 403) aunque
  escriba la URL directamente.
- Los usuarios que no necesiten modificar información deben usar el rol
  **Consulta** (solo lectura).
- Editar la matriz de permisos se hace en una **transacción**: se reemplazan los
  permisos del rol elegido y, si algo falla, no queda un estado intermedio.
- El rol **Administrador del sistema** se muestra en solo lectura para que un
  error de configuración no deje al club sin ninguna cuenta capaz de administrar
  el sistema.
- Si la tabla de permisos estuviera vacía, el panel muestra un aviso de
  instalación incompleta en lugar de conceder acceso total en silencio.

---

## 5. Gestión de accesos

- `csduv3_accesos` registra los intentos de inicio de sesión, con IP y resultado.
- `LOGIN_MAX_ATTEMPTS` limita los intentos fallidos; al superarlos, la cuenta se
  bloquea temporalmente durante `LOGIN_LOCKOUT_MINUTES`.
- `csduv3_auditoria` registra las acciones importantes (quién, qué, cuándo y desde qué IP).
- Las anulaciones de operaciones financieras registran usuario, fecha y motivo, y
  quedan además en `csduv3_movimientos_auditoria`.

---

## 6. Portal público de alquileres

El portal **no requiere sesión**, por lo que sus reglas de seguridad son distintas:

| Riesgo | Medida implementada |
| --- | --- |
| Datos privados expuestos | Solo se publican ambientes activos, tarifas vigentes y **horarios ocupados**. Nunca se muestran nombres, teléfonos ni importes de terceros |
| Confirmación indebida de reservas | Enviar una solicitud **nunca** crea una reserva. La solicitud queda en estado `pendiente` hasta que el personal la revise |
| Manipulación del formulario | Todo se valida otra vez en el servidor: ambiente activo, fecha no pasada, hora de fin posterior a la de inicio, formato de teléfono, cantidad de personas |
| Doble reserva del mismo horario | Antes de guardar, el servidor vuelve a comprobar el solapamiento contra las reservas registradas |
| Envíos automatizados | Campo trampa invisible, tiempo mínimo de llenado y límite de solicitudes por conexión (`RateLimiter`) |
| Falsificación de peticiones | El middleware CSRF global también protege este formulario |
| Fuga de datos en la URL de seguimiento | La página «solicitud recibida» muestra solo ambiente, fecha, horario y estado; se accede por un código aleatorio |
| Detalles técnicos en errores | Si la base de datos no responde, se muestra una página genérica de mantenimiento (503), no el error real |

El personal que revisa las solicitudes necesita el permiso `alquileres.ver` para
consultarlas y `alquileres.gestionar` para marcarlas en revisión, atenderlas o
rechazarlas. Los rechazos guardan el motivo.

---

## 7. Aplicación instalable (PWA)

- El `service worker` (`public/sw.js`) guarda en caché **solo** una lista fija de
  archivos estáticos públicos: hoja de estilos, script, logotipo, manifiesto,
  página sin conexión e iconos.
- **Nunca** se cachean páginas HTML, respuestas del panel administrativo, sesiones
  ni datos de socios, caja o reservas. Las rutas `/panel`, `/usuarios`, `/roles`,
  `/solicitudes`, `/auditoria`, `/ingresar` y `/perfil` se excluyen de forma
  explícita y se resuelven siempre contra el servidor.
- Las peticiones que no son `GET` (incluido el envío de una solicitud) no pasan
  por el service worker.
- `public/.htaccess` evita que `sw.js` quede almacenado por el navegador o por
  intermediarios, para que las actualizaciones lleguen.
- Detalle completo en [PWA.md](PWA.md).

---

## 8. Archivos subidos

- Se validan por **extensión**, **tamaño** (`UPLOAD_MAX_SIZE_MB`) y **tipo MIME real**
  (extensión `fileinfo`).
- Se guardan en `storage/uploads/` con un nombre generado, nunca con el nombre
  original recibido del navegador.
- Se sirven mediante un controlador que verifica permisos antes de entregar el
  contenido. No existe una URL pública predecible hacia el archivo.
- Nunca se permite la subida de archivos ejecutables (`.php`, `.phtml`, `.sh`, etc.).

---

## 9. Endurecimiento en el servidor (cPanel)

1. Fije `APP_ENV=production` y `APP_DEBUG=false`.
2. Active HTTPS con AutoSSL y ponga `SESSION_SECURE=true`.
3. Verifique que estas URLs devuelvan 403 o 404:

   ```
   https://TU_SUBDOMINIO/.env
   https://TU_SUBDOMINIO/.git/config
   https://TU_SUBDOMINIO/config/database.php
   https://TU_SUBDOMINIO/storage/logs/
   ```

4. Use contraseñas de base de datos largas y distintas de las de otros servicios.
5. Restrinja el acceso a phpMyAdmin a su dirección IP, si el panel lo permite.
6. Mantenga PHP y las extensiones actualizadas.

---

## 10. Registro de eventos

| Archivo | Contenido |
| --- | --- |
| `storage/logs/app-AAAA-MM-DD.log` | Eventos de la aplicación (con datos sensibles enmascarados) |
| `storage/logs/php-AAAA-MM-DD.log` | Errores crudos de PHP |

`storage/` está protegido por `.htaccess`. Estos archivos **no** deben copiarse al
repositorio (ya están excluidos).

---

## 11. Recomendaciones pendientes para fases siguientes

- Verificación en dos pasos para el rol administrador.
- Caducidad por inactividad con aviso al usuario.
- Bloqueo por IP tras varios intentos fallidos.
- Cerrar la condición de carrera de reservas con transacción y `SELECT ... FOR UPDATE` (Fase 4).
- Revisión de la bitácora de auditoría con alertas ante anulaciones frecuentes.
- Política de complejidad mínima de contraseñas configurable.

---

## 12. Advertencias de alcance

- Los **comprobantes internos** que genere el módulo de alquileres **no** son
  comprobantes electrónicos autorizados por SUNAT.
- El **registro de resultados electorales** es administrativo; no es un sistema de
  votación electrónica certificada.
- El **módulo de caja** es un registro financiero administrativo, no contabilidad
  tributaria completa.
