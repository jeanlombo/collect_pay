#!/bin/bash
set -e

PORT="${PORT:-8080}"

echo "=========================================="
echo " Démarrage de cOllect_Pay"
echo " Port Railway : ${PORT}"
echo "=========================================="

# ---------------------------------------------------------
# CORRECTION DU CONFLIT MPM APACHE
# ---------------------------------------------------------

echo "Nettoyage des modules MPM Apache..."

rm -f /etc/apache2/mods-enabled/mpm_event.load
rm -f /etc/apache2/mods-enabled/mpm_event.conf

rm -f /etc/apache2/mods-enabled/mpm_worker.load
rm -f /etc/apache2/mods-enabled/mpm_worker.conf

rm -f /etc/apache2/mods-enabled/mpm_prefork.load
rm -f /etc/apache2/mods-enabled/mpm_prefork.conf

# Activer uniquement mpm_prefork
ln -sf /etc/apache2/mods-available/mpm_prefork.load \
    /etc/apache2/mods-enabled/mpm_prefork.load

if [ -f /etc/apache2/mods-available/mpm_prefork.conf ]; then
    ln -sf /etc/apache2/mods-available/mpm_prefork.conf \
        /etc/apache2/mods-enabled/mpm_prefork.conf
fi

echo "Modules MPM actuellement activés :"
ls -la /etc/apache2/mods-enabled/mpm_* 2>/dev/null || true

# ---------------------------------------------------------
# CONFIGURATION DU PORT RAILWAY
# ---------------------------------------------------------

cat > /etc/apache2/ports.conf <<EOF
Listen ${PORT}
EOF

cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:${PORT}>
    ServerAdmin webmaster@localhost

    DocumentRoot /var/www/html/collect_pay

    <Directory /var/www/html/collect_pay>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>

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
# DIAGNOSTIC APACHE
# ---------------------------------------------------------

echo "Vérification des modules MPM chargés :"

apache2ctl -M 2>&1 | grep mpm || true

echo "Vérification de la configuration Apache :"

apache2ctl configtest

echo "Configuration Apache valide."
echo "Démarrage d’Apache sur le port ${PORT}..."

exec apache2-foreground