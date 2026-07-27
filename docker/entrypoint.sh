#!/bin/bash
set -e

PORT="${PORT:-8080}"

PUBLIC_DOMAIN="${RAILWAY_PUBLIC_DOMAIN:-collectpay-production.up.railway.app}"

# Nettoyer au cas où Railway fournirait une URL complète
PUBLIC_DOMAIN="${PUBLIC_DOMAIN#http://}"
PUBLIC_DOMAIN="${PUBLIC_DOMAIN#https://}"
PUBLIC_DOMAIN="${PUBLIC_DOMAIN%%/*}"
PUBLIC_DOMAIN="${PUBLIC_DOMAIN%%:*}"

echo "=========================================="
echo " Démarrage de cOllect_Pay"
echo " Port interne Railway : ${PORT}"
echo " Domaine public : ${PUBLIC_DOMAIN}"
echo "=========================================="

# ---------------------------------------------------------
# CONFIGURATION DU MPM APACHE
# ---------------------------------------------------------

echo "Configuration du module MPM Apache..."

rm -f /etc/apache2/mods-enabled/mpm_event.load
rm -f /etc/apache2/mods-enabled/mpm_event.conf

rm -f /etc/apache2/mods-enabled/mpm_worker.load
rm -f /etc/apache2/mods-enabled/mpm_worker.conf

rm -f /etc/apache2/mods-enabled/mpm_prefork.load
rm -f /etc/apache2/mods-enabled/mpm_prefork.conf

ln -sf \
    /etc/apache2/mods-available/mpm_prefork.load \
    /etc/apache2/mods-enabled/mpm_prefork.load

if [ -f /etc/apache2/mods-available/mpm_prefork.conf ]; then
    ln -sf \
        /etc/apache2/mods-available/mpm_prefork.conf \
        /etc/apache2/mods-enabled/mpm_prefork.conf
fi

# ---------------------------------------------------------
# MODULES APACHE NÉCESSAIRES
# ---------------------------------------------------------

a2enmod rewrite >/dev/null 2>&1 || true
a2enmod headers >/dev/null 2>&1 || true
a2enmod expires >/dev/null 2>&1 || true
a2enmod setenvif >/dev/null 2>&1 || true

echo "Modules Apache configurés."

# ---------------------------------------------------------
# PORT INTERNE RAILWAY
# ---------------------------------------------------------

cat > /etc/apache2/ports.conf <<EOF
Listen ${PORT}
EOF

# ---------------------------------------------------------
# VIRTUAL HOST RAILWAY
# ---------------------------------------------------------

cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:${PORT}>
    ServerAdmin webmaster@localhost

    ServerName https://${PUBLIC_DOMAIN}:443

    DocumentRoot /var/www/html/collect_pay

    UseCanonicalName On
    UseCanonicalPhysicalPort Off

    SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on
    SetEnvIf X-Forwarded-Proto "^https$" SERVER_PORT=443

    <Directory /var/www/html/collect_pay>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>

    Header always edit Location "^https://([^/:]+):${PORT}/(.*)$" "https://\$1/\$2"
    Header always edit Location "^http://([^/:]+):${PORT}/(.*)$" "https://\$1/\$2"

    Header always edit Location "^https://([^/:]+):8080/(.*)$" "https://\$1/\$2"
    Header always edit Location "^http://([^/:]+):8080/(.*)$" "https://\$1/\$2"

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# ---------------------------------------------------------
# DOSSIERS ET PERMISSIONS
# ---------------------------------------------------------

mkdir -p \
    /var/www/html/collect_pay/uploads/users \
    /var/www/html/collect_pay/assets/uploads \
    /var/www/html/collect_pay/assets/qr_codes

chown -R www-data:www-data /var/www/html/collect_pay

chmod -R 775 \
    /var/www/html/collect_pay/uploads \
    /var/www/html/collect_pay/assets/uploads \
    /var/www/html/collect_pay/assets/qr_codes

# ---------------------------------------------------------
# DIAGNOSTIC
# ---------------------------------------------------------

echo "Modules MPM chargés :"
apache2ctl -M 2>&1 | grep mpm || true

echo "Vérification de la configuration Apache..."
apache2ctl configtest

echo "Configuration Apache valide."
echo "Démarrage sur le port interne ${PORT}..."
echo "Adresse publique : https://${PUBLIC_DOMAIN}"

exec "$@"