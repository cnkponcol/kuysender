#!/usr/bin/env bash
set -euo pipefail

BASE=/home/openclaw/apps/kuysender
DASH="$BASE/dashboard"
DUMP=/home/openclaw/upload/kuysender/kuysender-database-20260810-0229.sql.gz
SITE=/etc/nginx/sites-available/kuysender-local

if [ "${EUID:-$(id -u)}" -ne 0 ]; then
  echo "Run with: sudo $BASE/install-root.sh"
  exit 1
fi

[ -f "$DASH/.env" ] || { echo "Missing dashboard .env"; exit 1; }
[ -f "$DUMP" ] || { echo "Missing database backup"; exit 1; }

DBPASS=$(sed -n 's/^DB_PASSWORD=//p' "$DASH/.env" | head -1)
[ -n "$DBPASS" ] || { echo "DB password is empty"; exit 1; }

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y php8.3-gd acl >/dev/null
systemctl restart php8.3-fpm

echo "[1/6] PHP GD ready"
mkdir -p "$BASE/backups"
if mariadb -NBe "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='kuysender'" | grep -qx kuysender; then
  TABLES=$(mariadb -NBe "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='kuysender'")
  if [ "${TABLES:-0}" -gt 0 ]; then
    TS=$(date +%Y%m%d-%H%M%S)
    mariadb-dump --single-transaction --routines --triggers kuysender | gzip > "$BASE/backups/kuysender-preinstall-$TS.sql.gz"
    echo "Existing database backed up"
  fi
fi

mariadb -e "CREATE DATABASE IF NOT EXISTS kuysender CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb -e "CREATE USER IF NOT EXISTS 'kuysender'@'127.0.0.1' IDENTIFIED BY '$DBPASS'; ALTER USER 'kuysender'@'127.0.0.1' IDENTIFIED BY '$DBPASS'; GRANT ALL PRIVILEGES ON kuysender.* TO 'kuysender'@'127.0.0.1'; FLUSH PRIVILEGES;"
gzip -cd "$DUMP" | mariadb kuysender

echo "[2/6] Database restored"

sudo -u openclaw -H bash -lc "cd '$DASH' && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist"
sudo -u openclaw -H bash -lc "cd '$DASH' && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache"

echo "[3/6] Laravel ready"
setfacl -m u:www-data:--x /home/openclaw
setfacl -m u:www-data:--x /home/openclaw/apps
setfacl -Rm u:www-data:rX "$DASH"
setfacl -Rm u:www-data:rwX "$DASH/storage" "$DASH/bootstrap/cache"
setfacl -Rdm u:www-data:rwX "$DASH/storage" "$DASH/bootstrap/cache"

echo "[4/6] Permissions ready"

if [ -f "$SITE" ]; then
  cp -a "$SITE" "$SITE.backup-$(date +%Y%m%d-%H%M%S)"
fi
cat > "$SITE" <<'NGINX'
server {
    listen 127.0.0.1:8081;
    listen 100.114.241.64:8081;
    server_name _;
    root /home/openclaw/apps/kuysender/dashboard/public;
    index index.php;
    client_max_body_size 16m;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. { deny all; }
}
NGINX

ln -sfn "$SITE" /etc/nginx/sites-enabled/kuysender-local
nginx -t
systemctl reload nginx

echo "[5/6] Nginx ready"

php -m | grep -qi '^gd$'
mariadb -ukuysender -p"$DBPASS" -h127.0.0.1 -NBe "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='kuysender';" >/tmp/kuysender-table-count
curl -fsS -o /dev/null http://127.0.0.1:8081/

echo "[6/6] Root install completed"
echo "URL: http://100.114.241.64:8081"
