#!/usr/bin/env sh
set -eu

PORT_VALUE="${PORT:-8080}"

sed -ri "s/Listen 80/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:8080>/<VirtualHost *:${PORT_VALUE}>/" /etc/apache2/sites-available/000-default.conf

# Les fichiers téléversés sont inscriptibles. Sur Railway, le disque local
# est éphémère : utiliser un Volume pour conserver les fichiers durablement.
mkdir -p /var/www/html/collect_pay/uploads/users \
         /var/www/html/collect_pay/assets/uploads \
         /var/www/html/collect_pay/assets/qr_codes
chown -R www-data:www-data /var/www/html/collect_pay/uploads \
                           /var/www/html/collect_pay/assets/uploads \
                           /var/www/html/collect_pay/assets/qr_codes
chmod -R 775 /var/www/html/collect_pay/uploads \
             /var/www/html/collect_pay/assets/uploads \
             /var/www/html/collect_pay/assets/qr_codes

exec "$@"
