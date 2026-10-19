# Instalación y configuración

Guía para poner en marcha el sistema CSDUV3 en un equipo de desarrollo o en un
servidor con PHP 8.3 y MariaDB.

---

## 1. Requisitos

| Componente | Versión mínima | Notas |
| --- | --- | --- |
| PHP | 8.3 | Con `pdo_mysql`, `mbstring`, `openssl`, `json`, `fileinfo`, `session` |
| MariaDB / MySQL | 10.2.2 / 8.0.16 | Se recomienda **MariaDB 10.11 LTS**. Con MySQL, 8.0.16 o superior: la versión 5.7 ignora las restricciones `CHECK` del esquema |
| Apache | 2.4 | Con `mod_rewrite`; también funciona Nginx con reglas equivalentes |
| Composer | — | **No se usa**. El proyecto no requiere dependencias externas |

---

## 2. Obtener el código

```bash
git clone https://github.com/bgrados/csduv3.git
cd csduv3
```

Si trabaja con el repositorio ya clonado en el directorio raíz del proyecto,
continúe con el paso 3.

---

## 3. Configurar el entorno

1. Copie la plantilla:

   ```bash
   cp .env.example .env
   ```

2. Complete los valores en `.env`:

   | Variable | Descripción |
   | --- | --- |
   | `APP_ENV` | `development` en local, `production` en el servidor |
   | `APP_DEBUG` | `true` en local, `false` en producción |
   | `APP_URL` | URL base del sitio (por ejemplo, `https://TU_SUBDOMINIO`); puede quedar vacía para usar rutas relativas |
   | `DB_HOST` | Normalmente `localhost` |
   | `DB_DATABASE` | Nombre de la base de datos |
   | `DB_USERNAME` | Usuario de la base de datos |
   | `DB_PASSWORD` | Contraseña (solo en `.env`, nunca en Git) |
   | `APP_KEY` | Cadena aleatoria de 32 o más caracteres |
   | `SESSION_SECURE` | `true` cuando el sitio funciona por HTTPS |

   Generar `APP_KEY` (ejemplo en Linux/macOS):

   ```bash
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

3. **Nunca** suba el archivo `.env` al repositorio. Ya está excluido en `.gitignore`.

---

## 4. Crear la base de datos e importar el esquema

1. Cree la base de datos con cotejamiento `utf8mb4_unicode_ci`.
2. Importe, en este orden:

   | Orden | Archivo | Obligatorio |
   | --- | --- | --- |
   | 1 | `database/01_esquema.sql` | Sí |
   | 2 | `database/02_datos_iniciales.sql` | Sí |
   | 3 | `database/03_demo_desarrollo.sql` | Solo desarrollo |
   | 4 | `database/04_eliminar_demo.sql` | Solo si cargó el paso 3 |

   Si ya tenía instalada la Fase 1 (esquema de 39 tablas), ejecute además **una
   sola vez** `database/migrations/0001_solicitudes_alquiler.sql`. Si importa el
   esquema actual desde cero, ese archivo no es necesario.

Los detalles y el diagrama de relaciones están en
[BASE_DE_DATOS.md](BASE_DE_DATOS.md).

---

## 5. Apuntar el servidor a la carpeta pública

El único directorio que debe quedar expuesto es `public/`.

**Apache con VirtualHost**

```apache
<VirtualHost *:80>
    ServerName csduv3.local
    DocumentRoot "/ruta/al/proyecto/public"

    <Directory "/ruta/al/proyecto/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Servidor embebido de PHP** (solo para pruebas rápidas)

```bash
php -S localhost:8000 -t public public/router.php
```

Luego abra <http://localhost:8000>.

---

## 6. Verificar la instalación

1. Abra la página inicial. Debe mostrarse el portal público del club.
2. Con `APP_ENV=development`, abra `/diagnostico`. Comprueba versión de PHP,
   extensiones, conexión, tablas y permisos de escritura en `storage/`.
   - *Conexión a base de datos*: **Conectada**
   - *Tablas del esquema*: **40 de 40** (si importó el esquema completo)
3. En producción, `/diagnostico` devuelve 404 de forma intencional.

---

## 7. Permisos de carpetas

El proceso de PHP necesita escritura en:

```
storage/logs
storage/uploads
storage/backups
storage/cache
```

Recomendación en servidores Linux: `751` para directorios y propietario igual al
usuario del servidor web. **No** use `777`.

---

## 8. Primer usuario administrador (procedimiento temporal)

El sistema **no** incluye un instalador web que pueda reiniciar la base de datos.
El primer administrador se crea con un script de línea de comandos que se ejecuta
en el propio servidor:

### 8.1. Ejecutar el script

```bash
php tools/crear_admin.php
```

El script:

1. Comprueba que la base de datos esté disponible y que existan los roles.
2. Solicita nombres, apellidos, nombre de usuario, correo (opcional) y contraseña.
3. **No acepta la contraseña como argumento** (quedaría en el historial del shell):
   siempre la pide de forma interactiva y, cuando el sistema lo permite, sin
   mostrarla en pantalla.
4. Aplica las mismas reglas de contraseña que la interfaz web.
5. Crea la cuenta con el rol **Administrador del sistema** y con la marca
   «debe cambiar contraseña».
6. Escribe el hecho en la tabla de auditoría.

Si ya existe un administrador activo, el script se detiene e indica que las demás
cuentas deben crearse desde el panel. Para forzar una segunda cuenta
administradora existe `--permitir-otro`, que debe usarse solo si se perdió el
acceso.

### 8.2. Eliminar el script

Una vez creada la cuenta:

1. Ingrese en `/ingresar` y cambie la contraseña cuando el sistema lo pida.
2. Cree el resto de cuentas del personal desde **Usuarios**, con el rol de cada
   persona.
3. **Elimine `tools/crear_admin.php` del servidor.** El archivo no debe quedar
   publicado.

La carpeta `tools/` incluye un `.htaccess` que niega todo acceso por web, pero eso
es una protección adicional, no un sustituto de la eliminación.

Reglas de diseño que se mantienen:

- No hay cuentas con contraseñas fijas en el código.
- No existe un instalador público capaz de reiniciar la base de datos.
- El procedimiento temporal requiere ejecutarse en el propio servidor.

---

## 9. Problemas frecuentes

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| «No se pudo conectar a la base de datos» | Datos de `.env` incorrectos | Verifique `DB_*`. En cPanel, el usuario y la base suelen llevar el prefijo de la cuenta |
| «Tablas del esquema: 0 de 40» | Falta importar el esquema | Ejecute `database/01_esquema.sql` en phpMyAdmin |
| «Tablas del esquema: 39 de 40» | Falta la migración de la Fase 2 | Ejecute `database/migrations/0001_solicitudes_alquiler.sql` |
| Las rutas dan 404 | `mod_rewrite` inactivo o falta `.htaccess` | Habilite el módulo o revise `AllowOverride` |
| «Error interno» con `APP_DEBUG=false` | Hay un error real | Revise `storage/logs/app-AAAA-MM-DD.log` |
| Acentos mal mostrados | Cotejamiento incorrecto | La base de datos debe ser `utf8mb4` |
| No se puede escribir en `storage/` | Permisos | Ajuste permisos del directorio |
| El panel entra en un ciclo de redirección a `/ingresar` | Cookies de sesión bloqueadas | Revise `SESSION_SECURE` y que `APP_URL` sea la URL real |
| No aparece el aviso de instalación de la PWA | Falta HTTPS o el navegador no lo admite | Instale desde el menú del navegador; el portal funciona igual |

---

## Siguiente paso

Con la instalación verificada y el primer administrador creado, ejecute las pruebas
de [PRUEBAS.md](PRUEBAS.md) para la Fase 2. Después continúe con la **Fase 3**:
socios y junta directiva.
