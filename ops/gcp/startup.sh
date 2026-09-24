#!/bin/bash
set -euo pipefail
[ ! -f /var/lib/visit-nyumba/setup-complete ] || exit 0
exec > >(tee -a /var/log/visit-nyumba-setup.log) 2>&1
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y nginx php8.3-fpm php8.3-sqlite3 php8.3-mbstring php8.3-curl php8.3-xml sqlite3 unzip unattended-upgrades certbot python3-certbot-nginx
timedatectl set-timezone Africa/Nairobi
install -d -o root -g www-data -m 0750 /srv/visit-nyumba
install -d -o www-data -g www-data -m 0700 /var/lib/visit-nyumba /var/lib/visit-nyumba/sessions
install -d -o root -g root -m 0700 /var/backups/visit-nyumba
if [ ! -f /swapfile ]; then
  fallocate -l 1G /swapfile
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
cat > /etc/php/8.3/fpm/conf.d/99-visit-nyumba.ini <<'EOF'
expose_php = Off
display_errors = Off
log_errors = On
memory_limit = 128M
date.timezone = Africa/Nairobi
session.cookie_httponly = 1
session.cookie_samesite = Lax
session.use_strict_mode = 1
EOF
cat > /etc/php/8.3/fpm/pool.d/zz-visit-nyumba.conf <<'EOF'
[www]
pm = ondemand
pm.max_children = 5
pm.process_idle_timeout = 10s
pm.max_requests = 500
env[BOOKING_DB] = /var/lib/visit-nyumba/bookings.sqlite
env[BOOKING_SESSION_PATH] = /var/lib/visit-nyumba/sessions
EOF
cat > /etc/nginx/sites-available/default <<'EOF'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    server_tokens off;
    default_type text/plain;
    location / { return 200 'Visit Nyumba server ready. Application deployment pending.\n'; }
}
EOF
cat > /usr/local/sbin/visit-nyumba-backup <<'EOF'
#!/bin/bash
set -euo pipefail
umask 077
db=/var/lib/visit-nyumba/bookings.sqlite
[ -f "$db" ] || exit 0
backup="/var/backups/visit-nyumba/bookings-$(date -u +%Y%m%dT%H%M%SZ).sqlite"
sqlite3 "$db" ".backup '$backup'"
test "$(sqlite3 "$backup" 'PRAGMA integrity_check;')" = ok
find /var/backups/visit-nyumba -maxdepth 1 -type f -name 'bookings-*.sqlite' -mtime +14 -delete
EOF
chmod 750 /usr/local/sbin/visit-nyumba-backup
cat > /etc/cron.d/visit-nyumba-backup <<'EOF'
15 3 * * * root /usr/local/sbin/visit-nyumba-backup
EOF
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
nginx -t
php-fpm8.3 -t
systemctl enable nginx php8.3-fpm
systemctl restart nginx php8.3-fpm
touch /var/lib/visit-nyumba/setup-complete
echo 'VISIT_NYUMBA_SETUP_COMPLETE'
