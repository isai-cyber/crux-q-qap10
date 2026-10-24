#!/usr/bin/env bash
# Despliegue de Crux Q (reto QAP10) en un VPS Debian/Ubuntu, en un subdominio propio.
# Uso (como root, en la consola del servidor):
#   curl -fsSL https://raw.githubusercontent.com/isai-cyber/crux-q-qap10/main/deploy/install.sh | bash -s crux.prismahub.pro
# No modifica los sitios existentes: solo agrega un virtual host para el subdominio indicado.
set -euo pipefail
if [ "$(uname -s)" != "Linux" ] || [ "$(id -u)" != "0" ]; then
  echo "Este script va en el SERVIDOR (Linux, como root), no en tu Mac."
  echo "Ábrelo desde la consola web de tu proveedor del VPS (VNC/Console) y pégalo ahí."
  exit 1
fi
DOMAIN="${1:-crux.prismahub.pro}"
APP_DIR=/var/www/crux-q-qap10
PUBKEY='ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAILPtOb4d1Hjf5GDYrx3ZDw1w2TT9v7scrnaSRjt3x4il isai@MacBook-de-isai.local'

echo "==> 1/6 Autorizando la llave SSH de despliegue"
mkdir -p /root/.ssh && chmod 700 /root/.ssh
touch /root/.ssh/authorized_keys && chmod 600 /root/.ssh/authorized_keys
grep -qxF "$PUBKEY" /root/.ssh/authorized_keys || echo "$PUBKEY" >> /root/.ssh/authorized_keys

echo "==> 2/6 Detectando servidor web"
WEB=none
systemctl is-active --quiet nginx 2>/dev/null && WEB=nginx
[ "$WEB" = none ] && systemctl is-active --quiet apache2 2>/dev/null && WEB=apache
if [ "$WEB" = none ]; then
  if ss -ltnp 2>/dev/null | grep -qE ':(80|443)\b'; then
    echo "!! Algo ocupa el puerto 80/443 y no es nginx ni apache (¿Docker/Caddy/Traefik?)."
    echo "!! La llave SSH ya quedó autorizada: avisa a Claude para que configure el proxy a mano."
    exit 0
  fi
  WEB=nginx
fi
echo "    servidor web: $WEB"

echo "==> 3/6 Paquetes (git, PHP-FPM, Python venv, certbot)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
PKGS="git curl php-fpm php-cli php-mbstring php-curl php-mysql python3 python3-venv certbot"
[ "$WEB" = nginx ] && PKGS="$PKGS nginx python3-certbot-nginx" || PKGS="$PKGS python3-certbot-apache libapache2-mod-fcgid"
apt-get install -y -qq $PKGS >/dev/null

echo "==> 4/6 Código y entorno Python (Qiskit)"
if [ -d "$APP_DIR/.git" ]; then git -C "$APP_DIR" pull -q; else git clone -q https://github.com/isai-cyber/crux-q-qap10.git "$APP_DIR"; fi
[ -f "$APP_DIR/.env" ] || cat > "$APP_DIR/.env" <<ENV
APP_NAME="Crux A.I"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://$DOMAIN
SESSION_NAME=cmms_session
SESSION_LIFETIME=3600
ENV
python3 -m venv "$APP_DIR/quantum/.venv"
"$APP_DIR/quantum/.venv/bin/pip" install -q --upgrade pip
"$APP_DIR/quantum/.venv/bin/pip" install -q -r "$APP_DIR/quantum/requirements.txt" || \
  "$APP_DIR/quantum/.venv/bin/pip" install -q qiskit qiskit-aer numpy scipy
mkdir -p "$APP_DIR/storage/logs"
chown -R www-data:www-data "$APP_DIR"

echo "==> 5/6 Virtual host para $DOMAIN"
PHP_SOCK=$(ls /run/php/php*-fpm.sock | head -1)
if [ "$WEB" = nginx ]; then
  cat > /etc/nginx/sites-available/$DOMAIN <<NGX
server {
    listen 80;
    server_name $DOMAIN;
    root $APP_DIR/public;
    index index.php;
    location = / { return 302 /optimizacion-cuantica; }
    location / { try_files \$uri /index.php\$is_args\$args; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_read_timeout 120;
    }
    location ~ /\. { deny all; }
}
NGX
  ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/$DOMAIN
  nginx -t && systemctl reload nginx
else
  a2enmod -q rewrite proxy_fcgi setenvif >/dev/null
  a2enconf -q "$(basename "$(ls /etc/apache2/conf-available/php*-fpm.conf | head -1)" .conf)" >/dev/null
  cat > /etc/apache2/sites-available/$DOMAIN.conf <<APC
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $APP_DIR/public
    <Directory $APP_DIR/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
APC
  a2ensite -q $DOMAIN >/dev/null && apache2ctl configtest && systemctl reload apache2
fi

echo "==> 6/6 HTTPS (Let's Encrypt)"
if getent hosts "$DOMAIN" >/dev/null; then
  certbot --"$WEB" -d "$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email --redirect || \
    echo "!! certbot falló; el sitio queda en http. Reintenta: certbot --$WEB -d $DOMAIN"
else
  echo "!! $DOMAIN aún no resuelve: crea el registro A 'crux' -> $(curl -s ifconfig.me) en el DNS y luego ejecuta: certbot --$WEB -d $DOMAIN"
fi
echo "Listo: http(s)://$DOMAIN/optimizacion-cuantica"
