#!/usr/bin/env bash
set -euo pipefail

# Deploy script for Ubuntu Server 24.04 - Laravel 13 / Filament 5 / PHP 8.4
# Stack: MariaDB + Apache + Cloudflare Tunnel (no public IP / port-forwarding,
# no Let's Encrypt cert on this box — TLS is terminated by Cloudflare).
# Mirrors DEPLOYMENT_GUIDE.md. See its "Deployment Lessons Learned" section.
#
# One script, two targets: pass --env staging or --env production. It is safe
# to re-run against an existing --repo-dir (e.g. for a code update) — the repo
# is fast-forward pulled instead of re-cloned, and admin creation/seeding are
# idempotent.
#
# Installation order (must not be reordered): ondrej/php PPA -> PHP 8.4 ->
# Composer -> Node 22 -> MariaDB -> clone/update repo -> composer install
# (creates vendor/autoload.php, required before ANY php artisan call) ->
# configure .env -> migrate -> seed roles/permissions (never demo/test data
# on production, see seed_database()) -> build Vite assets -> publish
# Filament assets -> optimize -> fix permissions last (so all generated
# artifacts end up owned by www-data).
#
# Usage:
#   sudo ./deploy-ubuntu-24.sh --env production --repo-dir /var/www/dmims \
#     --domain example.com --repo-url https://github.com/your/repo.git \
#     --db-password 'secret' --admin-email dm_it@datamationgroup.com
#
#   sudo ./deploy-ubuntu-24.sh --env staging --repo-dir /var/www/dmims-staging \
#     --domain staging.example.com --repo-url https://github.com/your/repo.git \
#     --db-password 'secret' --admin-email dm_it@datamationgroup.com --seed-qa-users

ENVIRONMENT=""
REPO_DIR="/var/www/dmims"
REPO_URL=""
GIT_BRANCH="main"
APP_DOMAIN=""
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_DATABASE="dmims"
DB_USERNAME="dmims"
DB_PASSWORD=""
ADMIN_EMAIL=""
ADMIN_PASSWORD=""
SEED_QA_USERS="false"
SKIP_APACHE="false"
SKIP_QUEUE="false"
SKIP_TUNNEL="false"

function usage() {
  cat <<EOF
Usage: sudo $0 --env staging|production [options]

Required:
  --env ENV            Deployment target: "staging" or "production". Controls
                        APP_ENV and gates test-data seeding (see --seed-qa-users).
  --domain DOMAIN       Application domain, e.g. dmims.example.com

Options:
  --repo-dir DIR        Repository directory (default: /var/www/dmims)
  --repo-url URL        Git repository URL (required on first run, i.e. if
                        repo directory does not already exist)
  --git-branch BRANCH   Branch to deploy/update (default: main)
  --db-host HOST        MySQL host default: 127.0.0.1
  --db-port PORT        MySQL port default: 3306
  --db-database NAME    Database name default: dmims
  --db-username USER    Database user default: dmims
  --db-password PASS    Database password default: empty
  --admin-email EMAIL   Create/verify a platform admin with this email
                        (via dmims:create-admin; skipped if it already exists)
  --admin-password PASS Password for --admin-email (generated + printed once
                        if omitted)
  --seed-qa-users       Seed the QA sample accounts (QASampleUsersSeeder).
                        Only allowed with --env staging — refused outright on
                        --env production so dev/test accounts can never land
                        on a production database.
  --skip-apache         Do not configure Apache
  --skip-queue          Do not configure queue worker service
  --skip-tunnel         Do not install/configure cloudflared
  -h, --help            Show this help
EOF
  exit 1
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --env) ENVIRONMENT="$2"; shift 2 ;;
    --repo-dir) REPO_DIR="$2"; shift 2 ;;
    --repo-url) REPO_URL="$2"; shift 2 ;;
    --git-branch) GIT_BRANCH="$2"; shift 2 ;;
    --domain) APP_DOMAIN="$2"; shift 2 ;;
    --db-host) DB_HOST="$2"; shift 2 ;;
    --db-port) DB_PORT="$2"; shift 2 ;;
    --db-database) DB_DATABASE="$2"; shift 2 ;;
    --db-username) DB_USERNAME="$2"; shift 2 ;;
    --db-password) DB_PASSWORD="$2"; shift 2 ;;
    --admin-email) ADMIN_EMAIL="$2"; shift 2 ;;
    --admin-password) ADMIN_PASSWORD="$2"; shift 2 ;;
    --seed-qa-users) SEED_QA_USERS="true"; shift ;;
    --skip-apache) SKIP_APACHE="true"; shift ;;
    --skip-queue) SKIP_QUEUE="true"; shift ;;
    --skip-tunnel) SKIP_TUNNEL="true"; shift ;;
    -h|--help) usage ;;
    *) echo "Unknown option: $1" >&2; usage ;;
  esac
done

if [[ "$ENVIRONMENT" != "staging" && "$ENVIRONMENT" != "production" ]]; then
  echo "ERROR: --env is required and must be \"staging\" or \"production\"." >&2
  usage
fi

if [[ -z "$APP_DOMAIN" ]]; then
  echo "ERROR: --domain is required." >&2
  usage
fi

if [[ ! -d "$REPO_DIR" && -z "$REPO_URL" ]]; then
  echo "ERROR: repository directory does not exist and --repo-url is required." >&2
  usage
fi

# Hard guard: QA/demo test-data seeders must never be reachable on production,
# regardless of what flags get pasted from a staging run.
if [[ "$SEED_QA_USERS" == "true" && "$ENVIRONMENT" == "production" ]]; then
  echo "ERROR: --seed-qa-users is not allowed with --env production. QA sample" >&2
  echo "accounts and demo/test data must never be seeded into production." >&2
  exit 1
fi

function install_packages() {
  apt-get update
  apt-get install -y apache2 git curl unzip software-properties-common cron
  systemctl enable --now cron

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
    git clone --branch "$GIT_BRANCH" "$REPO_URL" "$REPO_DIR"
    return
  fi

  if [[ -d "$REPO_DIR/.git" ]]; then
    echo "Repository already present in $REPO_DIR — updating to latest $GIT_BRANCH"
    git -C "$REPO_DIR" fetch origin "$GIT_BRANCH"
    # --ff-only: refuse to clobber uncommitted/diverged server-side changes
    # rather than silently discarding them.
    git -C "$REPO_DIR" checkout "$GIT_BRANCH"
    git -C "$REPO_DIR" pull --ff-only origin "$GIT_BRANCH"
  else
    echo "ERROR: $REPO_DIR exists but is not a git repository. Move it aside or choose a different --repo-dir." >&2
    exit 1
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
  sed -i "s|APP_ENV=.*|APP_ENV=$ENVIRONMENT|" .env
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

function seed_database() {
  cd "$REPO_DIR"

  # Roles & permissions only — no demo customer, no demo/test users. Safe on
  # every environment including production. NEVER run the bare `db:seed`
  # (DatabaseSeeder) here: it creates a demo customer and an
  # admin@example.com/password login that must never exist on production.
  php artisan db:seed --class=RolesAndPermissionsSeeder --force

  if [[ "$SEED_QA_USERS" == "true" ]]; then
    # Guarded again here, not just at the top of the script: this function
    # must refuse to run QA seeding on production even if called directly.
    if [[ "$ENVIRONMENT" != "staging" ]]; then
      echo "ERROR: refusing to seed QA sample users outside --env staging." >&2
      exit 1
    fi
    echo "Seeding QA sample users (staging only)..."
    php artisan db:seed --class=QASampleUsersSeeder --force
  fi
}

function create_admin() {
  if [[ -z "$ADMIN_EMAIL" ]]; then
    echo "No --admin-email given; skipping admin account creation."
    echo "Create one later with: php artisan dmims:create-admin <email>"
    return
  fi

  cd "$REPO_DIR"

  # Idempotent: CreateAdminUser refuses (non-zero exit) if the email already
  # exists, which is expected on a re-run/update — don't abort the deploy.
  local args=("dmims:create-admin" "$ADMIN_EMAIL")
  if [[ -n "$ADMIN_PASSWORD" ]]; then
    args+=("--password=$ADMIN_PASSWORD")
  fi

  if ! php artisan "${args[@]}"; then
    echo "Admin account $ADMIN_EMAIL already exists (or failed) — leaving it as-is."
  fi
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

function scheduler_cron() {
  # Notifications generation, nightly backups, weekly restore-verify and
  # Sanctum token pruning (routes/console.php) all run via the Laravel
  # scheduler and do nothing unless `schedule:run` fires every minute. Runs
  # as www-data to match fix_permissions' ownership of $REPO_DIR. Idempotent:
  # replaces any prior dmims schedule:run line rather than duplicating it.
  local cron_line="* * * * * cd $REPO_DIR && php artisan schedule:run >> /dev/null 2>&1"
  (crontab -u www-data -l 2>/dev/null | grep -vF "$REPO_DIR && php artisan schedule:run"; echo "$cron_line") \
    | crontab -u www-data -
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
  echo "Deploying DMIMS — environment: $ENVIRONMENT, domain: $APP_DOMAIN, repo: $REPO_DIR"

  install_packages
  clone_repo
  install_php_dependencies
  configure_env
  storage_and_db
  seed_database
  create_admin
  build_assets
  optimize_app
  fix_permissions
  apache_config
  queue_service
  scheduler_cron
  tunnel_setup

  echo "Deployment complete ($ENVIRONMENT)."
  echo "Verify locally: curl http://localhost"
  echo "Then finish the Cloudflare Tunnel setup above to expose https://$APP_DOMAIN."
}

main
