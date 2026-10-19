# Despliegue en BanaHosting (cPanel)

Procedimiento para publicar el sistema en el plan
**Bana-Professional-Deluxe-SSD** con el subdominio HTTPS del club, sin SSH,
usando el **Administrador de archivos** o **FTP**.

> **Marcadores que debe reemplazar el administrador.** A lo largo de esta guía,
> `TU_SUBDOMINIO`, `TU_BASE_DE_DATOS` y `TU_USUARIO_BD` representan los datos
> reales de la cuenta. Sustitúyalos por los valores que le indique cPanel.

---

## 1. Datos previstos del hosting

| Dato | Valor |
| --- | --- |
| Subdominio | `TU_SUBDOMINIO` |
| Versión de PHP | 8.3 |
| Base de datos | `TU_BASE_DE_DATOS` |
| Usuario de base de datos | `TU_USUARIO_BD` |
| Contraseña de base de datos | La definida en cPanel (**no se escribe en archivos del proyecto**) |

> Las versiones, el subdominio, el nombre de la base de datos y el usuario exactos
> dependen de su cuenta: **confírmelos siempre en cPanel** y complételos en los
> pasos siguientes.

---

## 2. Elegir el modelo de despliegue

### Modelo A — DocumentRoot apuntando a `/public` (recomendado)

Es el modelo más seguro: solo `public/` es accesible desde el navegador y el
resto del código queda fuera del alcance público.

1. Suba el proyecto completo a una carpeta **fuera** de `public_html`.
   Ejemplo: `/home/USUARIO/csduv3_app`
2. En cPanel → **Dominios** (o *Subdominios*), configure el subdominio
   `TU_SUBDOMINIO` con **Document Root**:
   `/home/USUARIO/csduv3_app/public`

### Modelo B — DocumentRoot fijo, código dentro del directorio público

Use este modelo solo si el hosting no permite cambiar el DocumentRoot.

1. Cree la estructura con dos carpetas:
   - `/home/USUARIO/public_html/csduv3/` → aquí va **el contenido de `public/`**
   - `/home/USUARIO/csduv3_app/` → aquí va **el resto del proyecto**
     (`app/`, `config/`, `database/`, `docs/`, `storage/`)
2. Cree en la carpeta pública el archivo `public/.app_path.php` con la ruta de la
   aplicación:

   ```php
   <?php
   return '/home/USUARIO/csduv3_app';
   ```

   Ese archivo **no se versiona** (ya figura en `.gitignore`).
3. Compruebe que el proyecto incluye las protecciones `.htaccess` en `app/`,
   `config/`, `database/`, `docs/` y `storage/`. Si por alguna razón el código
   quedara dentro del directorio público, esas reglas impiden su descarga.

> En ambos modelos, `storage/` debe quedar fuera del acceso público. Los archivos
> privados se sirven mediante controladores con verificación de permisos.

---

## 3. Subir los archivos

**Con el Administrador de archivos de cPanel**

1. Comprima el proyecto en local como `csduv3.zip`.
2. Suba el archivo y extráigalo en la carpeta elegida.
3. Elimine el `.zip` del servidor después de extraer.

**Con FTP (FileZilla u otro cliente)**

1. Conecte con los datos de FTP de cPanel.
2. Suba el contenido respetando la estructura de carpetas.
3. No suba: `.git/`, `.env`, `database/03_demo_desarrollo.sql`, ni archivos de
   copia de seguridad.

---

## 4. Configurar PHP 8.3

En cPanel → **Select PHP Version** (o *MultiPHP Manager*):

1. Seleccione **PHP 8.3** para el subdominio.
2. Verifique que estén activas: `pdo_mysql`, `mbstring`, `openssl`, `json`,
   `fileinfo`, `session`.
3. Valores recomendados:
   - `display_errors = Off`
   - `log_errors = On`
   - `upload_max_filesize` y `post_max_size` ≥ 5M
   - `date.timezone = America/Lima` (la aplicación también la fija)

---

## 5. Crear o verificar la base de datos

1. En cPanel → **MySQL Databases**, confirme que existen:
   - Base de datos: `TU_BASE_DE_DATOS`
   - Usuario: `TU_USUARIO_BD` con **todos los privilegios** sobre esa base
2. Anote la contraseña; se usará únicamente en `.env` dentro del servidor.
3. Importe el esquema desde **phpMyAdmin**:
   1. `database/01_esquema.sql`
   2. `database/02_datos_iniciales.sql`
   - Si actualiza una instalación de la Fase 1, ejecute también
     `database/migrations/0001_solicitudes_alquiler.sql`.
   - **No importe** `database/03_demo_desarrollo.sql` en producción.

---

## 6. Crear el archivo `.env` en el servidor

El archivo `.env` se crea **directamente en el servidor** (Administrador de
archivos → *Nuevo archivo*), nunca se sube desde el repositorio.

Ejemplo de contenido a completar:

```
APP_NAME="Club Social Deportivo Unidad Vecinal N.º 3"
APP_SHORT_NAME="CSDUV3"
APP_ENV=production
APP_DEBUG=false
APP_URL="https://TU_SUBDOMINIO"
APP_TIMEZONE=America/Lima
APP_LOCALE=es_PE

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=TU_BASE_DE_DATOS
DB_USERNAME=TU_USUARIO_BD
DB_PASSWORD=<contraseña definida en cPanel>
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_TIMEZONE_OFFSET=-05:00

SESSION_NAME=csduv3_sesion
SESSION_LIFETIME=7200
SESSION_SECURE=true

APP_KEY=<cadena aleatoria de 32 o más caracteres>
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15

UPLOAD_MAX_SIZE_MB=5
LOG_LEVEL=warning
```

---

## 7. Permisos

Debe existir escritura en:

```
storage/logs
storage/uploads
storage/backups
storage/cache
```

Desde el Administrador de archivos: seleccione la carpeta → **Permisos** → `755`
(o `751`). No use `777`.

---

## 8. Habilitar HTTPS

1. En cPanel → **SSL/TLS Status**, ejecute *Run AutoSSL* para el subdominio.
2. Cuando el certificado esté activo, confirme `SESSION_SECURE=true` en `.env`.
3. `public/.htaccess` ya incluye la redirección de HTTP a HTTPS.

---

## 9. Crear el primer administrador y retirar el script

1. Ejecute `php tools/crear_admin.php` desde la consola que ofrezca el hosting (o
   desde el entorno local apuntando a la base de datos del servidor, si la
   conexión remota está permitida).
2. Ingrese en `https://TU_SUBDOMINIO/ingresar` y cambie la contraseña.
3. **Elimine `tools/crear_admin.php` del servidor** en cuanto exista un
   administrador. Puede conservar `tools/generar_iconos.ps1` solo como referencia
   local; no es necesario en producción.

Si el hosting no ofrece consola, la alternativa es crear la cuenta desde un
entorno local con PHP que se conecte a la base del servidor (variables `DB_*` de
`.env`). No existe ningún procedimiento por navegador, a propósito.

---

## 10. Verificación posterior al despliegue

| Comprobación | Resultado esperado |
| --- | --- |
| `https://TU_SUBDOMINIO` | Portada del portal público del club |
| `https://TU_SUBDOMINIO/alquiler` | Ambientes y tarifas publicadas (o aviso de que aún no hay) |
| `https://TU_SUBDOMINIO/alquiler/disponibilidad` | Consulta de disponibilidad por fecha |
| `https://TU_SUBDOMINIO/alquiler/solicitud` | Formulario público de solicitud |
| `https://TU_SUBDOMINIO/panel` (sin sesión) | Redirección a `/ingresar` |
| `https://TU_SUBDOMINIO/ingresar` | Formulario de acceso del personal |
| `https://TU_SUBDOMINIO/diagnostico` | 404 (no revela información en producción) |
| `https://TU_SUBDOMINIO/.env` | 403 o 404 |
| `https://TU_SUBDOMINIO/app/Core/Database.php` | 403 o 404 |
| `https://TU_SUBDOMINIO/storage/logs/` | 403 o 404 |
| `https://TU_SUBDOMINIO/tools/crear_admin.php` | 403 o 404 (y el archivo debe eliminarse) |
| `https://TU_SUBDOMINIO/manifest.webmanifest` | Se descarga con tipo `application/manifest+json` |
| `https://TU_SUBDOMINIO/sw.js` | Se descarga con `Cache-Control: no-cache` |
| Diagnóstico con `APP_ENV=development` | «Tablas del esquema: 40 de 40» |
| Certificado | Válido, sin advertencias |

Si alguna comprobación falla, revise `storage/logs/app-AAAA-MM-DD.log`.

---

## 11. Actualizaciones posteriores

1. Suba **solo** los archivos modificados (FTP o Administrador de archivos).
2. Aplique los scripts de `database/migrations/` que correspondan, en orden.
3. No sobrescriba `.env` ni el contenido de `storage/`.
4. Si cambió los archivos estáticos (`app.css`, `app.js`, iconos), aumente la
   versión de la caché en `public/sw.js` (`csduv3-publica-vN`) para que los
   navegadores que ya instalaron la aplicación reciban los cambios.
5. Consulte [MANTENIMIENTO.md](MANTENIMIENTO.md).
