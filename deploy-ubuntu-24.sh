#!/usr/bin/env bash
set -euo pipefail

# Deploy script for Ubuntu Server 24.04 - Laravel 13 / Filament 5 / PHP 8.4
# Stack: MariaDB + Apache + Cloudflare Tunnel (no public IP / port-forwarding,
# no Let's Encrypt cert on this box — TLS is terminated by Cloudflare).
# Mirrors DEPLOYMENT_GUIDE.md. See its "Deployment Lessons Learned" section.
#
# Installation order (must not be reordered): ondrej/php PPA -> PHP 8.4 ->
# Composer -> Node 22 -> MariaDB -> clone repo -> composer install (creates
# vendor/autoload.php, required before ANY php artisan call) -> configure .env
# -> migrate -> build Vite assets -> publish Filament assets -> optimize ->
# fix permissions last (so all generated artifacts end up owned by www-data).
#
# Usage:
#   sudo ./deploy-ubuntu-24.sh --repo-dir /var/www/dmims --domain example.com --repo-url https://github.com/your/repo.git

REPO_DIR="/var/www/dmims"
REPO_URL=""
APP_DOMAIN=""
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_DATABASE="dmims"
DB_USERNAME="dmims"
DB_PASSWORD=""
SKIP_APACHE="false"
SKIP_QUEUE="false"
SKIP_TUNNEL="false"

function usage() {
  cat <<EOF
Usage: sudo $0 [options]

Options:
  --repo-dir DIR       Repository directory (default: /var/www/dmims)
  --repo-url URL       Git repository URL (required if repo directory does not exist)
  --domain DOMAIN      Application domain, e.g. dmims.example.com (required)
  --db-host HOST       MySQL host default: 127.0.0.1
  --db-port PORT       MySQL port default: 3306
  --db-database NAME   Database name default: dmims
  --db-username USER   Database user default: dmims
  --db-password PASS   Database password default: empty
  --skip-apache        Do not configure Apache
  --skip-queue         Do not configure queue worker service
  --skip-tunnel        Do not install/configure cloudflared
  -h, --help           Show this help
EOF
  exit 1
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo-dir) REPO_DIR="$2"; shift 2 ;;
    --repo-url) REPO_URL="$2"; shift 2 ;;
    --domain) APP_DOMAIN="$2"; shift 2 ;;
    --db-host) DB_HOST="$2"; shift 2 ;;
    --db-port) DB_PORT="$2"; shift 2 ;;
    --db-database) DB_DATABASE="$2"; shift 2 ;;
    --db-username) DB_USERNAME="$2"; shift 2 ;;
    --db-password) DB_PASSWORD="$2"; shift 2 ;;
    --skip-apache) SKIP_APACHE="true"; shift ;;
    --skip-queue) SKIP_QUEUE="true"; shift ;;
    --skip-tunnel) SKIP_TUNNEL="true"; shift ;;
    -h|--help) usage ;;
    *) echo "Unknown option: $1" >&2; usage ;;
  esac
done

if [[ -z "$APP_DOMAIN" ]]; then
  echo "ERROR: --domain is required." >&2
  usage
fi

if [[ ! -d "$REPO_DIR" && -z "$REPO_URL" ]]; then
  echo "ERROR: repository directory does not exist and --repo-url is required." >&2
  usage
fi

function install_packages() {
  apt-get update
  apt-get install -y apache2 git curl unzip software-properties-common

  # Ubuntu 24.04 ships PHP 8.3; this app requires PHP 8.4 (Laravel 13 + Filament 5).
  add-apt-repository -y ppa:ondrej/php
  apt-get update
  apt-get install -y php8.4 php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath php8.4-sqlite3
  update-alternatives --set php /usr/bin/php8.4

  # MariaDB (MySQL-compatible; provides the mysql/mysqldump clients used by backups)
  apt-get install -y mariadb-server

  # Node.js 22 LTS (Vite 8 requires Node 20.19+/22+)
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
  apt-get install -y nodejs build-essential
}

function install_composer() {
  if ! command -v composer >/dev/null 2>&1; then
    echo "Installing Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f composer-setup.php
  fi
}

function clone_repo() {
  if [[ ! -d "$REPO_DIR" ]]; then
    echo "Cloning repository into $REPO_DIR"
    git clone "$REPO_URL" "$REPO_DIR"
  fi
}

function install_php_dependencies() {
  cd "$REPO_DIR"
  install_composer
  # composer install must run before any `php artisan` call: artisan boots from
  # vendor/autoload.php, which does not exist until Composer creates it.
  composer install --no-dev --optimize-autoloader
}

function build_assets() {
  cd "$REPO_DIR"
  npm ci
  npm run build                # Vite assets
  php artisan filament:assets   # publish Filament admin-panel CSS/JS/fonts
}

function configure_env() {
  cd "$REPO_DIR"
  if [[ ! -f .env ]]; then
    cp .env.example .env
  fi

  php artisan key:generate --force

  sed -i "s|APP_NAME=.*|APP_NAME=DMIMS|" .env
  sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
  sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env
  sed -i "s|APP_URL=.*|APP_URL=https://$APP_DOMAIN|" .env
  # MariaDB is MySQL-compatible; use the mysql driver.
  sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
  sed -i "s|DB_HOST=.*|DB_HOST=$DB_HOST|" .env
  sed -i "s|DB_PORT=.*|DB_PORT=$DB_PORT|" .env
  sed -i "s|DB_DATABASE=.*|DB_DATABASE=$DB_DATABASE|" .env
  sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_USERNAME|" .env
  if [[ -n "$DB_PASSWORD" ]]; then
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" .env
  fi

  if grep -q "^TRUSTED_PROXIES=" .env; then
    sed -i "s|TRUSTED_PROXIES=.*|TRUSTED_PROXIES=*|" .env
  else
    echo "TRUSTED_PROXIES=*" >> .env
  fi

  # Reachable both via the Cloudflare Tunnel (HTTPS) and directly on
  # localhost/LAN IP (plain HTTP, no cert on this box) — a forced secure
  # cookie would break login on the plain-HTTP path. See DEPLOYMENT_GUIDE.md.
  if grep -q "^SESSION_SECURE_COOKIE=" .env; then
    sed -i "s|SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=false|" .env
  else
    echo "SESSION_SECURE_COOKIE=false" >> .env
  fi

  if grep -q "^SESSION_SAME_SITE=" .env; then
    sed -i "s|SESSION_SAME_SITE=.*|SESSION_SAME_SITE=lax|" .env
  else
    echo "SESSION_SAME_SITE=lax" >> .env
  fi
}

function storage_and_db() {
  cd "$REPO_DIR"
  php artisan storage:link --force
  php artisan migrate --force
}

function optimize_app() {
  cd "$REPO_DIR"
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
}

function fix_permissions() {
  chown -R www-data:www-data "$REPO_DIR"
  find "$REPO_DIR/storage" -type d -exec chmod 2775 {} \;
  find "$REPO_DIR/bootstrap/cache" -type d -exec chmod 2775 {} \;
}

function apache_config() {
  if [[ "$SKIP_APACHE" == "true" ]]; then
    echo "Skipping Apache config.";
    return
  fi

  a2enmod rewrite proxy proxy_fcgi setenvif headers expires

  local site_conf="/etc/apache2/sites-available/dmims.conf"
  cat > "$site_conf" <<EOF
<VirtualHost *:80>
    # Cloudflare Tunnel forwards \$APP_DOMAIN here; the same vhost also
    # answers on localhost/the machine's LAN IP for local access (no cert
    # needed here — TLS is terminated by Cloudflare).
    ServerName $APP_DOMAIN
    ServerAlias localhost 127.0.0.1
    DocumentRoot $REPO_DIR/public

    <Directory $REPO_DIR/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    <FilesMatch \.php\$>
        SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"
    </FilesMatch>

    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "no-referrer-when-downgrade"

    <IfModule mod_expires.c>
        ExpiresActive On
        <FilesMatch "\.(css|js|jpg|jpeg|gif|png|svg|webp|woff2?|ttf|ico)\$">
            ExpiresDefault "access plus 7 days"
            Header set Cache-Control "public, must-revalidate, proxy-revalidate"
        </FilesMatch>
    </IfModule>

    ErrorLog \${APACHE_LOG_DIR}/dmims_error.log
    CustomLog \${APACHE_LOG_DIR}/dmims_access.log combined
</VirtualHost>
EOF

  a2dissite 000-default.conf || true
  a2ensite dmims.conf
  apache2ctl configtest
  systemctl restart apache2
}

function queue_service() {
  if [[ "$SKIP_QUEUE" == "true" ]]; then
    echo "Skipping queue worker setup.";
    return
  fi

  local unit_file="/etc/systemd/system/dmims-worker.service"
  cat > "$unit_file" <<EOF
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php $REPO_DIR/artisan queue:work --sleep=3 --tries=3 --daemon
TimeoutStopSec=300

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable --now dmims-worker
}

function tunnel_setup() {
  if [[ "$SKIP_TUNNEL" == "true" ]]; then
    echo "Skipping cloudflared install.";
    return
  fi

  if ! command -v cloudflared >/dev/null 2>&1; then
    echo "Installing cloudflared..."
    curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | gpg --yes --dearmor -o /usr/share/keyrings/cloudflare-main.gpg
    echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared $(lsb_release -cs) main" > /etc/apt/sources.list.d/cloudflared.list
    apt-get update
    apt-get install -y cloudflared
  fi

  echo
  echo "cloudflared is installed but NOT configured — tunnel login/create needs"
  echo "an interactive browser auth step, so finish it manually:"
  echo "  cloudflared tunnel login"
  echo "  cloudflared tunnel create dmims"
  echo "  # write /etc/cloudflared/config.yml mapping $APP_DOMAIN -> http://localhost:80"
  echo "  cloudflared tunnel route dns dmims $APP_DOMAIN"
  echo "  cloudflared service install && systemctl enable --now cloudflared"
  echo "See DEPLOYMENT_GUIDE.md Part 9 for the full config.yml example."
}

function main() {
  install_packages
  clone_repo
  install_php_dependencies
  configure_env
  storage_and_db
  build_assets
  optimize_app
  fix_permissions
  apache_config
  queue_service
  tunnel_setup

  echo "Deployment complete."
  echo "Verify locally: curl http://localhost"
  echo "Then finish the Cloudflare Tunnel setup above to expose https://$APP_DOMAIN."
}

main
