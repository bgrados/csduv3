#!/bin/bash
APP_DIR="/home/uclztjki/csduv3_app"
LOG_FILE="$APP_DIR/storage/deploy.log"
echo "========================================" >> "$LOG_FILE"
echo "Deploy started: $(date)" >> "$LOG_FILE"
cd "$APP_DIR" || exit
DB_USER="uclztjki_csduv3"
DB_PASS="8tHdR@,KPv4A"
DB_NAME="uclztjki_csduv3"
echo "Connecting to DB: $DB_NAME with user: $DB_USER" >> "$LOG_FILE"
mkdir -p storage/backups
BACKUP_FILE="storage/backups/backup_$(date '+%Y%m%d_%H%M%S').sql"
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>> "$LOG_FILE"
if [ $? -eq 0 ]; then echo "DB backup created successfully." >> "$LOG_FILE"; else echo "WARNING: Backup failed. Check password." >> "$LOG_FILE"; fi
echo "Updating code..." >> "$LOG_FILE"
git pull origin main >> "$LOG_FILE" 2>&1
echo "Running migrations..." >> "$LOG_FILE"
php tools/migrar.php >> "$LOG_FILE" 2>&1
echo "Deploy finished: $(date)" >> "$LOG_FILE"
