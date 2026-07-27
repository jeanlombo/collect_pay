FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd mbstring zip intl \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html/collect_pay

COPY . /var/www/html/collect_pay

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/collect-pay-entrypoint

RUN chmod +x /usr/local/bin/collect-pay-entrypoint \
    && mkdir -p /var/www/html/collect_pay/uploads/users \
                /var/www/html/collect_pay/assets/uploads \
                /var/www/html/collect_pay/assets/qr_codes \
    && chown -R www-data:www-data /var/www/html/collect_pay \
    && find /var/www/html/collect_pay -type d -exec chmod 755 {} \; \
    && chmod -R 775 /var/www/html/collect_pay/uploads \
                    /var/www/html/collect_pay/assets/uploads \
                    /var/www/html/collect_pay/assets/qr_codes

EXPOSE 8080

ENTRYPOINT ["collect-pay-entrypoint"]
CMD ["apache2-foreground"]