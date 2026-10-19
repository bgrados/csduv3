#!/usr/bin/env bash
# ============================================================================
#  CSDUV3 - Preparacion del entorno de PRUEBAS AISLADO
# ----------------------------------------------------------------------------
#  Este script SOLO debe ejecutarse DENTRO del contenedor definido en
#  .devcontainer/ (GitHub Codespaces o Dev Containers local). Lo invoca
#  postCreateCommand / postStartCommand de devcontainer.json.
#
#  Que hace, en orden:
#    1. Arranca MariaDB (local al contenedor).
#    2. Crea la base de datos y el usuario de LABORATORIO.
#    3. Importa el esquema, los datos tecnicos y (opcionalmente) los datos
#       ficticios de demostracion.
#    4. Genera el archivo .env con los valores de laboratorio.
#    5. Configura Apache (DocumentRoot -> public/, AllowOverride All).
#    6. Ajusta los permisos de storage/.
#    7. Muestra un resumen y los pasos manuales pendientes.
#
#  Garantias de seguridad (por que no puede tocar datos reales):
#    * Los valores de conexion estan FIJADOS aqui (laboratorio) y el script
#      NUNCA los lee del entorno ni de un .env existente.
#    * Aborta si no detecta que esta dentro de un contenedor.
#    * Aborta si encuentra un .env que no sea el de laboratorio, de modo que
#      no se puede empezar a probar apuntando sin querer a una base real.
#    * Importa unicamente sobre la base "csduv3_test".
#    * Los archivos SQL del proyecto no contienen DROP, TRUNCATE, DELETE ni
#      USE (verificado), por lo que no pueden destruir datos.
#    * Nunca se ejecuta database/04_eliminar_demo.sql.
#
#  Uso:  bash .devcontainer/setup.sh
#  Es IDEMPOTENTE: se puede ejecutar todas las veces que haga falta.
# ============================================================================
set -euo pipefail

# --- Valores de LABORATORIO (fijos a proposito) -----------------------------
#  No son secretos reales ni tienen relacion con produccion. Viven solo en el
#  contenedor desechable y en el .env (que esta excluido por .gitignore).
DB_NAME="csduv3_test"
DB_USER="csduv3_test"
DB_PASS="csduv3_lab_only"
DB_HOST_LAB="127.0.0.1"
PUERTO="8000"

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
PUBLICO="${RAIZ}/public"
TPL_VHOST="${RAIZ}/.devcontainer/apache/000-default.conf"
URL_PRUEBAS=""

titulo() { printf '\n== %s ==\n' "$1"; }
error()  { printf '\nERROR: %s\n' "$1" >&2; exit 1; }

# ---------------------------------------------------------------------------
# 0. Guardas de seguridad
# ---------------------------------------------------------------------------
titulo "0/7  Comprobaciones previas"

[ "$(id -u)" -eq 0 ] \
  || error "Este script debe ejecutarse como root dentro del contenedor (postCreateCommand ya lo hace asi)."

if [ ! -f /.dockerenv ] \
   && [ "${CODESPACES:-}" != "true" ] \
   && [ "${REMOTE_CONTAINERS:-}" != "true" ] \
   && [ "${DEVCONTAINER:-}" != "true" ]; then
  error "No se ha detectado un contenedor. Este script no debe ejecutarse en un servidor real."
fi

[ -f "${RAIZ}/.env.example" ]            || error "No se encuentra ${RAIZ}/.env.example"
[ -f "${RAIZ}/database/01_esquema.sql" ] || error "No se encuentra database/01_esquema.sql"
[ -f "${TPL_VHOST}" ]                    || error "No se encuentra la plantilla de Apache ${TPL_VHOST}"
[ -d "${PUBLICO}" ]                      || error "No se encuentra la carpeta public/"

# Un .env preexistente SOLO se admite si es el de laboratorio.
if [ -f "${RAIZ}/.env" ]; then
  env_db="$(grep -E '^DB_DATABASE=' "${RAIZ}/.env" | head -n1 | cut -d= -f2- | tr -d "\"' " || true)"
  env_host="$(grep -E '^DB_HOST=' "${RAIZ}/.env" | head -n1 | cut -d= -f2- | tr -d "\"' " || true)"
  if [ "${env_db}" != "${DB_NAME}" ] || [ "${env_host}" != "${DB_HOST_LAB}" ]; then
    error "Existe un .env que NO es el de laboratorio (DB_DATABASE='${env_db}', DB_HOST='${env_host}'). No se continua para no apuntar a una base de datos real. Apartelo con:  mv .env .env.apartado"
  fi
  echo "  .env de laboratorio detectado: se conserva."
fi

echo "  Contenedor detectado."
echo "  Ruta del proyecto: ${RAIZ}"

# ---------------------------------------------------------------------------
# 1. MariaDB
# ---------------------------------------------------------------------------
titulo "1/7  Arrancando MariaDB (local al contenedor)"

install -d -o mysql -g mysql /var/run/mysqld

if [ ! -d /var/lib/mysql/mysql ]; then
  echo "  Inicializando el directorio de datos de MariaDB..."
  mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
fi

if command -v service >/dev/null 2>&1; then
  service mariadb start >/dev/null 2>&1 || true
fi

for _ in $(seq 1 30); do
  if mariadb-admin ping --silent >/dev/null 2>&1; then break; fi
  sleep 1
done

if ! mariadb-admin ping --silent >/dev/null 2>&1; then
  ARRANQUE="$(command -v mariadbd-safe || command -v mysqld_safe || true)"
  [ -n "${ARRANQUE}" ] || error "MariaDB no arranco y no se encontro mariadbd-safe/mysqld_safe."
  echo "  Arranque directo con $(basename "${ARRANQUE}")..."
  nohup "${ARRANQUE}" --user=mysql --datadir=/var/lib/mysql \
      >/var/log/mariadb-arranque.log 2>&1 &
  for _ in $(seq 1 30); do
    if mariadb-admin ping --silent >/dev/null 2>&1; then break; fi
    sleep 1
  done
fi

mariadb-admin ping --silent >/dev/null 2>&1 \
  || error "MariaDB no responde. Revise /var/log/mariadb-arranque.log"

mariadb --version

# ---------------------------------------------------------------------------
# 2. Base de datos de laboratorio
# ---------------------------------------------------------------------------
titulo "2/7  Base de datos de laboratorio '${DB_NAME}'"

mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "  Base de datos y usuario de laboratorio listos."

# ---------------------------------------------------------------------------
# 3. Importacion
# ---------------------------------------------------------------------------
titulo "3/7  Importando esquema y datos"

EXISTENTES="$(mariadb -N -B "${DB_NAME}" -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'csduv3\\_%';" 2>/dev/null || echo 0)"

if [ "${EXISTENTES}" -ge 40 ] && [ "${CSDUV3_FORZAR_IMPORTACION:-0}" != "1" ]; then
  echo "  Ya hay ${EXISTENTES} tablas en '${DB_NAME}': no se reimporta."
  echo "  (Para forzar: CSDUV3_FORZAR_IMPORTACION=1 bash .devcontainer/setup.sh)"
else
  mariadb "${DB_NAME}" < "${RAIZ}/database/01_esquema.sql"
  echo "  Esquema importado (CREATE TABLE IF NOT EXISTS: no destruye nada)."

  mariadb "${DB_NAME}" < "${RAIZ}/database/02_datos_iniciales.sql"
  echo "  Datos tecnicos importados (roles, permisos, catalogos)."

  if [ "${CSDUV3_DEMO:-1}" = "1" ]; then
    mariadb "${DB_NAME}" < "${RAIZ}/database/03_demo_desarrollo.sql"
    echo "  Datos FICTICIOS de demostracion importados (prefijo DEMO-)."
  else
    echo "  Datos de demostracion omitidos (CSDUV3_DEMO=0)."
  fi
fi

TABLAS="$(mariadb -N -B "${DB_NAME}" -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'csduv3\\_%';" 2>/dev/null || echo 0)"
echo "  Tablas csduv3_ en '${DB_NAME}': ${TABLAS} (esperado: 40)"

# ---------------------------------------------------------------------------
# 4. Archivo .env de laboratorio
# ---------------------------------------------------------------------------
titulo "4/7  Archivo .env de laboratorio"

if [ ! -f "${RAIZ}/.env" ]; then
  cp "${RAIZ}/.env.example" "${RAIZ}/.env"
  APP_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"

  if [ -n "${CODESPACE_NAME:-}" ] && [ -n "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-}" ]; then
    URL_PRUEBAS="https://${CODESPACE_NAME}-${PUERTO}.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
  fi

  sed -i \
    -e "s|^APP_ENV=.*|APP_ENV=development|" \
    -e "s|^APP_DEBUG=.*|APP_DEBUG=true|" \
    -e "s|^APP_URL=.*|APP_URL=\"${URL_PRUEBAS}\"|" \
    -e "s|^DB_HOST=.*|DB_HOST=${DB_HOST_LAB}|" \
    -e "s|^DB_PORT=.*|DB_PORT=3306|" \
    -e "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" \
    -e "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" \
    -e "s|^SESSION_SECURE=.*|SESSION_SECURE=true|" \
    -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" \
    "${RAIZ}/.env"

  echo "  .env creado (esta excluido de git mediante .gitignore)."
  echo "  Contrasena de laboratorio: valor ficticio, sin relacion con produccion."
else
  echo "  .env ya existe y es de laboratorio: se conserva."
fi

# ---------------------------------------------------------------------------
# 5. Apache
# ---------------------------------------------------------------------------
titulo "5/7  Configurando Apache"

sed "s|__DOCROOT__|${PUBLICO}|g" "${TPL_VHOST}" > /etc/apache2/sites-available/000-default.conf
a2ensite 000-default >/dev/null 2>&1 || true
echo "  DocumentRoot: ${PUBLICO}"

# Importante: NO se usa "service apache2 restart". Apache es el proceso PID 1
# del contenedor; reiniciarlo lo mataria y el contenedor se detendria.
# "graceful" recarga la configuracion en caliente conservando el proceso.
set +u
# shellcheck disable=SC1091
. /etc/apache2/envvars
set -u

apache2ctl configtest || error "La configuracion de Apache no es valida."

if ! apache2ctl graceful; then
  apache2ctl -k graceful || error "No se pudo recargar Apache."
fi
echo "  Apache recargado en el puerto ${PUERTO}."

# ---------------------------------------------------------------------------
# 6. Permisos de escritura
# ---------------------------------------------------------------------------
titulo "6/7  Permisos de storage/"

for d in logs uploads backups cache; do
  mkdir -p "${RAIZ}/storage/${d}"
done
chown -R www-data:www-data "${RAIZ}/storage"
chmod -R 775 "${RAIZ}/storage"
echo "  storage/ pertenece a www-data (usuario de Apache) con permisos 775."

# ---------------------------------------------------------------------------
# 7. Resumen
# ---------------------------------------------------------------------------
titulo "7/7  Entorno de pruebas preparado"

RESP_LOCAL="000"
if command -v curl >/dev/null 2>&1; then
  RESP_LOCAL="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "http://127.0.0.1:${PUERTO}/" 2>/dev/null)" || RESP_LOCAL="000"
  [ -n "${RESP_LOCAL}" ] || RESP_LOCAL="000"
fi

cat <<RESUMEN

  PHP ............ $(php -v | head -n1)
  MariaDB ........ $(mariadb --version)
  Base de datos .. ${DB_NAME} (laboratorio, local al contenedor)
  Tablas ......... ${TABLAS} (esperado: 40)
  DocumentRoot ... ${PUBLICO}
  Respuesta HTTP . ${RESP_LOCAL} en http://127.0.0.1:${PUERTO}/
  URL ............ ${URL_PRUEBAS:-http://localhost:${PUERTO}}

  Pasos MANUALES pendientes (no se ejecutan solos):
    1) php tools/crear_admin.php     -> crear el primer administrador
    2) rm tools/crear_admin.php      -> borrarlo despues de usarlo
    3) ejecutar docs/PRUEBAS.md (secciones 5, 6 y 7)

  Recordatorio: este entorno es desechable y solo contiene datos ficticios
  (prefijo DEMO-). No apunta a produccion. Vease .devcontainer/README.md

RESUMEN
