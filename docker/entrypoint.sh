#!/bin/bash
set -e

PORT="${PORT:-8080}"

echo "=========================================="
echo " Démarrage de cOllect_Pay"
echo " Port Railway : ${PORT}"
echo "=========================================="

# Faire écouter Apache sur le port fourni par Railway
cat > /etc/apache2/ports.conf <<EOF
Listen ${PORT}

<IfModule ssl_module>
    Listen 443
</IfModule>

<IfModule mod_gnutls.c>
    Listen 443
</IfModule>
EOF

# Génération du VirtualHost avec le port Railway
cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:${PORT}>
    ServerAdmin webmaster@localhost

    DocumentRoot /var/www/html/collect_pay

    <Directory /var/www/html/collect_pay>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted

        DirectoryIndex index.php index.html
    </Directory>

    Alias /collect_pay /var/www/html/collect_pay

    <Directory /var/www/html/collect_pay>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Permissions des répertoires d’écriture
mkdir -p \
    /var/www/html/collect_pay/uploads/users \
    /var/www/html/collect_pay/assets/uploads \
    /var/www/html/collect_pay/assets/qr_codes

chown -R www-data:www-data /var/www/html/collect_pay

chmod -R 775 \
    /var/www/html/collect_pay/uploads \
    /var/www/html/collect_pay/assets/uploads \
    /var/www/html/collect_pay/assets/qr_codes

# Vérifier la configuration Apache
apache2ctl configtest

echo "Apache démarre sur le port ${PORT}"

exec "$@"