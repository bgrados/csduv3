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

echo "Running migrations..." >> "$LOG_FILE"
php tools/migrar.php >> "$LOG_FILE" 2>&1
MIGRATION_STATUS=$?
echo "Migration exit code: $MIGRATION_STATUS" >> "$LOG_FILE"

# Comprobación de que las tablas de los módulos nuevos existen. Si faltan, la
# aplicación los muestra como no disponibles hasta aplicar la migración.
for TABLE in fotos negocios; do
    TABLE_COUNT="$(MYSQL_PWD="$DB_PASS" mysql -N -B -u "$DB_USER" "$DB_NAME" \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'csduv3_${TABLE}';" 2>>"$LOG_FILE")"
    echo "Table csduv3_${TABLE}: ${TABLE_COUNT:-0}" >> "$LOG_FILE"
    if [ "${TABLE_COUNT:-0}" = "0" ]; then
        echo "WARNING: falta la tabla csduv3_${TABLE}. Ejecute: php tools/migrar.php" >> "$LOG_FILE"
    fi
done

echo "Deploy finished: $(date)" >> "$LOG_FILE"
