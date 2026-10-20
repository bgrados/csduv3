#!/bin/bash
# Despliegue automático CSDUV3.
# Las credenciales de la base de datos se leen de .env; nunca se guardan aquí.
set -u

APP_DIR="${APP_DIR:-/home/uclztjki/csduv3_app}"
LOG_FILE="$APP_DIR/storage/deploy.log"

mkdir -p "$APP_DIR/storage"
echo "========================================" >> "$LOG_FILE"
echo "Deploy started: $(date)" >> "$LOG_FILE"

cd "$APP_DIR" || exit 1

if [ ! -f "$APP_DIR/.env" ]; then
    echo "ERROR: no existe $APP_DIR/.env" >> "$LOG_FILE"
    exit 1
fi

get_env() {
    local key="$1"
    local value
    value="$(grep -E "^${key}=" "$APP_DIR/.env" | tail -n 1 | cut -d= -f2-)"
    value="${value%$'\r'}"
    if [[ "$value" == \"*\" && "$value" == *\" ]]; then
        value="${value:1:${#value}-2}"
    elif [[ "$value" == \'*\' && "$value" == *\' ]]; then
        value="${value:1:${#value}-2}"
    fi
    printf '%s' "$value"
}

DB_USER="$(get_env DB_USERNAME)"
DB_PASS="$(get_env DB_PASSWORD)"
DB_NAME="$(get_env DB_DATABASE)"

if [ -z "$DB_USER" ] || [ -z "$DB_NAME" ]; then
    echo "ERROR: faltan DB_USERNAME o DB_DATABASE en .env" >> "$LOG_FILE"
    exit 1
fi
if [ -z "$DB_PASS" ]; then
    echo "WARNING: DB_PASSWORD está vacío en .env" >> "$LOG_FILE"
fi

echo "Connecting to DB: $DB_NAME with user: $DB_USER" >> "$LOG_FILE"
mkdir -p "$APP_DIR/storage/backups"
BACKUP_FILE="$APP_DIR/storage/backups/backup_$(date '+%Y%m%d_%H%M%S').sql"

MYSQL_PWD="$DB_PASS" mysqldump -u "$DB_USER" "$DB_NAME" > "$BACKUP_FILE" 2>> "$LOG_FILE"
if [ $? -eq 0 ]; then
    echo "DB backup created successfully." >> "$LOG_FILE"
else
    echo "WARNING: Backup failed." >> "$LOG_FILE"
fi

echo "Updating code..." >> "$LOG_FILE"
git pull origin main >> "$LOG_FILE" 2>&1

# Se busca un PHP 8 en consola: el binario `php` puede apuntar a una versión
# anterior a la que usa la web (por ejemplo 7.4) y las migraciones fallarían.
PHP_BIN=""
for CANDIDATE in php8.3 php83 php8 php; do
    if command -v "$CANDIDATE" >/dev/null 2>&1; then
        MAJOR="$("$CANDIDATE" -r 'echo PHP_MAJOR_VERSION;' 2>/dev/null)"
        if [ -n "$MAJOR" ] && [ "$MAJOR" -ge 8 ] 2>/dev/null; then
            PHP_BIN="$CANDIDATE"
            break
        fi
    fi
done

if [ -z "$PHP_BIN" ]; then
    PHP_BIN="php"
fi

echo "Using PHP binary: $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null))" >> "$LOG_FILE"

echo "Running migrations..." >> "$LOG_FILE"
"$PHP_BIN" tools/migrar.php >> "$LOG_FILE" 2>&1
MIGRATION_STATUS=$?
echo "Migration exit code: $MIGRATION_STATUS" >> "$LOG_FILE"

# Comprobación de que las tablas de los módulos nuevos existen. El propio
# aplicador de migraciones las verifica y deja el resultado en el log.
echo "Migration summary:" >> "$LOG_FILE"
"$PHP_BIN" tools/migrar.php --verificar-tablas >> "$LOG_FILE" 2>&1

echo "Deploy finished: $(date)" >> "$LOG_FILE"
