#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$APP_DIR/.env"
BACKUP_ROOT="${BACKUP_ROOT:-$HOME/backups/portal-admin}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
STAMP="$(date +%Y%m%d-%H%M%S)"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Arquivo .env não encontrado em $ENV_FILE" >&2
    exit 1
fi

env_get() {
    local key="$1"
    local line
    line="$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 || true)"
    if [[ -z "$line" ]]; then
        printf ''
        return 0
    fi
    printf '%s' "${line#*=}" | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

DB_CONNECTION="$(env_get DB_CONNECTION)"
DB_HOST="$(env_get DB_HOST)"
DB_PORT="$(env_get DB_PORT)"
DB_DATABASE="$(env_get DB_DATABASE)"
DB_USERNAME="$(env_get DB_USERNAME)"
DB_PASSWORD="$(env_get DB_PASSWORD)"
MEDIA_ROOT="$(env_get MEDIA_ROOT)"

if [[ "$DB_CONNECTION" != "mysql" ]]; then
    echo "Backup de banco só está implementado para MySQL (DB_CONNECTION=$DB_CONNECTION)." >&2
    exit 1
fi

mkdir -p "$BACKUP_ROOT"
chmod 700 "$BACKUP_ROOT" || true

CNF="$BACKUP_ROOT/.my.cnf"
umask 077
cat > "$CNF" <<EOF
[client]
host=${DB_HOST:-127.0.0.1}
port=${DB_PORT:-3306}
user=${DB_USERNAME}
password=${DB_PASSWORD}
EOF

mysqldump --defaults-extra-file="$CNF" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    "$DB_DATABASE" | gzip > "$BACKUP_ROOT/db-${STAMP}.sql.gz"

if [[ -n "$MEDIA_ROOT" && -d "$MEDIA_ROOT" ]]; then
    tar -czf "$BACKUP_ROOT/media-${STAMP}.tar.gz" \
        --exclude='./default.php' \
        -C "$MEDIA_ROOT" .
fi

find "$BACKUP_ROOT" -maxdepth 1 -type f \( -name 'db-*.sql.gz' -o -name 'media-*.tar.gz' \) -mtime +"$RETENTION_DAYS" -delete

echo "Backup concluído em $BACKUP_ROOT (db-${STAMP}.sql.gz)"
