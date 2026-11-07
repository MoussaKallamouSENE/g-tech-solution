FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev \
    && docker-php-ext-install pdo_mysql zip gd bcmath \
    && sed -i 's/^;listen = 127.0.0.1:9000/listen = 9000/' /usr/local/etc/php-fpm.d/www.conf

# L'application est montée sur un bind mount hôte : sans ce réglage, opcache
# contrôle les horodatages de plus de 8 000 fichiers PHP à chaque requête et
# chaque appel API prend plusieurs secondes. Après une modification du code
# PHP, redémarrer le service (docker compose restart php) pour recharger les
# fichiers dans opcache.
RUN printf 'opcache.validate_timestamps=0\nopcache.revalidate_freq=0\nopcache.max_accelerated_files=20000\nopcache.memory_consumption=256\n' > /usr/local/etc/php/conf.d/zz-opcache-tuning.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
