# Entorno de pruebas aislado (PHP 8.3 + MariaDB)

Este directorio define un **entorno desechable** para validar el sistema CSDUV3
sin instalar XAMPP ni nada parecido en el equipo, y **sin tocar nunca la base de
datos de producción** del hosting.

> **Estado actual: preparado, pero NO ejecutado todavía.**
> Los archivos existen y están revisados, pero no se ha construido el contenedor,
> no se ha inicializado ninguna base de datos y no se ha ejecutado `setup.sh`.
> Por lo tanto **no hay ninguna prueba aprobada** por este medio. Las pruebas de
> `docs/PRUEBAS.md` (secciones 5, 6 y 7) siguen **pendientes de ejecución**.

---

## 1. Contenido

| Archivo | Para qué sirve |
| --- | --- |
| `devcontainer.json` | Qué imagen construir, puerto, usuario y comandos de preparación. |
| `Dockerfile` | PHP 8.3 + Apache (mod_php) + MariaDB + extensiones requeridas. |
| `apache/000-default.conf` | Plantilla del VirtualHost. `setup.sh` sustituye `__DOCROOT__` por la ruta real del proyecto. |
| `setup.sh` | Deja el entorno listo: MariaDB, base de datos, importación, `.env`, Apache y permisos. Es **idempotente**. |
| `README.md` | Este documento. |

Ninguno de estos archivos contiene credenciales reales.

---

## 2. Requisitos del contenedor

| Componente | Versión | Nota |
| --- | --- | --- |
| PHP | 8.3.x | Extensiones `pdo_mysql` y `mbstring` obligatorias; `intl` opcional. |
| MariaDB | 10.11 (LTS) | La que trae Debian 12 «bookworm». Es la versión de referencia de las pruebas. |
| Apache | 2.4 | `mod_rewrite` y `mod_headers`, con `AllowOverride All`. |

`Dockerfile` verifica en tiempo de construcción que `pdo_mysql` y `mbstring`
existan: si faltan, la construcción **falla** en vez de generar un entorno roto.

---

## 3. Cómo se usará (más adelante)

### Opción A — GitHub Codespaces
1. El código ya está publicado en <https://github.com/bgrados/csduv3> (rama `main`).
2. *Code → Codespaces → Create codespace* sobre la rama correspondiente.
3. Codespaces construye el contenedor y ejecuta `setup.sh` automáticamente
   (`postCreateCommand`).
4. El puerto **8000** se reenvía **en privado**; la URL es
   `https://<nombre-del-codespace>-8000.<dominio-de-reenvío>`.
   Es **HTTPS**, que es justo lo que necesita la PWA (contexto seguro).

### Opción B — Dev Containers en local (Docker Desktop, sin XAMPP)
Abrir la carpeta en VS Code y elegir *Dev Containers: Reopen in Container*.
Se usan **los mismos** archivos; no se publica nada.

En ambos casos, `setup.sh` se vuelve a ejecutar en cada arranque del contenedor
(`postStartCommand`) para garantizar que MariaDB esté en marcha y que la
configuración siga siendo correcta.

---

## 4. Qué hace `setup.sh` (y por qué es seguro)

Orden de ejecución:

1. Comprobaciones previas (ver más abajo).
2. Arranca MariaDB dentro del contenedor (inicializa el directorio de datos si
   hiciera falta).
3. Crea la base de datos y el usuario de **laboratorio**.
4. Importa `database/01_esquema.sql`, `database/02_datos_iniciales.sql` y, salvo
   que se indique lo contrario, `database/03_demo_desarrollo.sql`.
5. Genera el archivo `.env` con valores de laboratorio y una `APP_KEY` aleatoria.
6. Genera el VirtualHost de Apache a partir de la plantilla y recarga Apache.
7. Ajusta los permisos de `storage/` y muestra un resumen.

Comportamiento e interruptores:

| Variable | Valor por defecto | Efecto |
| --- | --- | --- |
| `CSDUV3_DEMO` | `1` | Carga los datos ficticios de demostración. `0` para no cargarlos. |
| `CSDUV3_FORZAR_IMPORTACION` | `0` | `1` fuerza reimportar aunque ya existan las 40 tablas. |

### Garantías de seguridad

* Los valores de conexión están **fijados en el script** y el script **nunca** los
  lee del entorno ni de un `.env` existente.
* **Aborta** si no detecta que está dentro de un contenedor (`/.dockerenv`,
  `CODESPACES`, `REMOTE_CONTAINERS` o `DEVCONTAINER`). No se puede ejecutar por
  accidente en un servidor real.
* **Aborta** si encuentra un `.env` cuyo `DB_DATABASE` no sea `csduv3_test` o cuyo
  `DB_HOST` no sea `127.0.0.1`. Así es imposible empezar a probar apuntando a una
  base de datos real: si existe un `.env` ajeno, primero hay que apartarlo.
* Solo se opera sobre la base `csduv3_test`, local al contenedor y desechable.
* Los archivos SQL del proyecto usan `CREATE TABLE IF NOT EXISTS` e `INSERT IGNORE`
  y **no contienen** `CREATE DATABASE`, `DROP`, `TRUNCATE`, `DELETE` ni `USE`.
* **Nunca** se ejecuta `database/04_eliminar_demo.sql` (contiene `DELETE`; es una
  herramienta manual y solo para el entorno donde se hayan cargado los datos DEMO).
* `setup.sh` no crea usuarios del sistema, no instala paquetes y no publica nada.

### Credenciales de laboratorio

Son ficticias, locales al contenedor y se declaran explícitamente en `setup.sh`:
base de datos y usuario `csduv3_test`, contraseña `csduv3_lab_only`, host
`127.0.0.1`. El `.env` resultante está excluido por `.gitignore`.

`setup.sh` fija además `SESSION_SECURE=true`, porque el acceso habitual es por HTTPS.
Si se accediera **exclusivamente** por HTTP a un host que no sea `localhost`, la
cookie de sesión no se enviaría; en ese caso puede ponerse en `false` temporalmente.

---

## 5. Datos ficticios que se utilizan

Del proyecto se cargan únicamente:

| Archivo | Contenido | Naturaleza |
| --- | --- | --- |
| `database/01_esquema.sql` | Estructura (40 tablas) | Real, sin datos |
| `database/02_datos_iniciales.sql` | Roles, permisos, parámetros y catálogos genéricos | Técnico, no del club |
| `database/03_demo_desarrollo.sql` | Ambientes, tarifas, categorías y socios **inventados**, con prefijo `DEMO-` | **Ficticio** |

No se importa **ningún** dato real del club (socios, directivos, candidatos,
movimientos, resultados ni cuotas).

### Dato adicional para la prueba PU-6

`docs/PRUEBAS.md` PU-6 («solicitud en un horario ya reservado») necesita una
reserva real, y la conversión *solicitud → reserva* todavía no existe (es de la
Fase 4). Por eso hay que insertar **a mano**, solo en esta base de pruebas, una
reserva ficticia:

```sql
-- SOLO EN EL ENTORNO DE PRUEBAS
INSERT INTO csduv3_reservas
  (codigo, instalacion_id, solicitante_nombre, fecha_evento,
   hora_inicio, hora_fin, estado)
SELECT 'DEMO-RES-001', i.id, 'Reserva ficticia de prueba',
       CURDATE() + INTERVAL 3 DAY, '18:00:00', '20:00:00', 'confirmada'
FROM csduv3_instalaciones i
WHERE i.nombre = 'DEMO-Salón principal'
LIMIT 1;
```

Las demás pruebas del portal funcionan solo con los datos `DEMO-`.

---

## 6. Pasos manuales después de crear el contenedor

`setup.sh` **no** hace estas cosas (a propósito):

1. `php tools/crear_admin.php` — crear el primer administrador (pide la
   contraseña de forma interactiva; nunca se pasa por argumento).
2. `rm tools/crear_admin.php` — borrarlo una vez creado el administrador.
3. Ejecutar las pruebas:
   * Sintaxis real: `find app config public tools -name '*.php' -print0 | xargs -0 -n1 php -l`
   * Diagnóstico de la aplicación: entrar en `/diagnostico` (solo con
     `APP_ENV=development`).
   * Resto: `docs/PRUEBAS.md`, secciones 5 (base, autenticación, roles),
     6 (portal público) y 7 (aplicación instalable).
4. Anotar los resultados en `docs/PRUEBAS.md` (sección 8) indicando las versiones
   exactas de PHP y MariaDB.

Nota: el script `tools/verificar_estatico.ps1` es de PowerShell y **no** está
disponible en el contenedor; se sigue usando en Windows. No sustituye a `php -l`.

---

## 7. Detener y eliminar el entorno

**Detener** (conserva el contenedor y la base de datos para seguir después):
* Codespaces: panel *Codespaces* → **Stop**.
* Local: cerrar la ventana o *Dev Containers: Reopen Folder Locally*.

**Eliminar** (borra el contenedor **y** su base de datos de pruebas):
* Codespaces: panel *Codespaces* → **Delete**. La base de datos era local y
  desechable, así que desaparece sin afectar a nada más.
* Local: *Dev Containers: Rebuild Container* para reiniciar la base de datos, o
  eliminar el contenedor con Docker Desktop.

**Reiniciar la base de datos desde cero** (útil para repetir pruebas en limpio):
* Reconstruir el contenedor (*Rebuild Container* / borrar el Codespace). Con el
  contenedor nuevo, `setup.sh` vuelve a dejar todo listo.

---

## 8. Notas y limitaciones conocidas

* Dentro del contenedor se trabaja como **root**. Es deliberado: el contenedor es
  desechable y evita problemas de `sudo` y de permisos entre el desarrollo y
  Apache (`www-data`). El puerto se reenvía **en privado** y el contenedor se
  elimina al terminar.
* MariaDB vive **dentro** del contenedor: no se publica ningún puerto de base de
  datos y no es accesible desde fuera.
* La base de datos se pierde al reconstruir el contenedor; se puede recrear en
  cualquier momento con `bash .devcontainer/setup.sh`.
* Apache es el proceso principal (PID 1) del contenedor. Por eso `setup.sh` usa
  `apache2ctl graceful` y **nunca** `service apache2 restart`, que detendría el
  contenedor.
* El archivo `setup.sh` debe conservar **finales de línea LF**. Si al clonar en
  Windows se convirtieran a CRLF, `bash` fallaría con `\r: command not found`;
  se puede corregir con `sed -i 's/\r$//' .devcontainer/setup.sh`.
* Este entorno es para **pruebas**. No es un procedimiento de despliegue y no
  sustituye a `docs/DESPLIEGUE_BANAHOSTING.md`.
