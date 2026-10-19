   #!/bin/bash
   APP_DIR="/home/uclztjki/csduv3_app"
LOG_FILE="$APP_DIR/storage/deploy.log"
echo "========================================" >> "$LOG_FILE"
echo "Despliegue iniciado: $(date)" >> "$LOG_FILE"
cd "$APP_DIR" || exit
DB_USER=$(awk -F'=' '/^DB_USER=/ {print $2}' .env | tr -d '\r' | tr -d ' ')
DB_PASS=$(awk -F'=' '/^DB_PASS=/ {print $2}' .env | tr -d '\r' | tr -d ' ')
DB_NAME=$(awk -F'=' '/^DB_NAME=/ {print $2}' .env | tr -d '\r' | tr -d ' ')
echo "Conectando a BD: $DB_NAME con usuario: $DB_USER" >> "$LOG_FILE"
mkdir -p storage/backups
BACKUP_FILE="storage/backups/backup_$(date +\%Y\%m\%d_\%H\%M\%S).sql"
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>> "$LOG_FILE"
if [ $? -eq 0 ]; then echo "Respaldo de BD creado exitosamente." >> "$LOG_FILE"; else echo "ADVERTENCIA: 
Respaldo falló." >> "$LOG_FILE"; fi
echo "Actualizando código..." >> "$LOG_FILE"
git pull origin main >> "$LOG_FILE" 2>&1
echo "Ejecutando migraciones..." >> "$LOG_FILE"
php tools/migrar.php >> "$LOG_FILE" 2>&1
echo "Despliegue finalizado: $(date)" >> "$LOG_FILE"
