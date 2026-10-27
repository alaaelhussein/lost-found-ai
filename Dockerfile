FROM php:8.2-apache

# Extensions PHP :
#   pdo_mysql : base de données
#   mbstring  : chaînes UTF-8
#   gd + exif : réencodage des photos (suppression des métadonnées GPS)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring gd exif \
    && apt-get purge -y --auto-remove libonig-dev \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Durcissement (préfixe « zz- » : chargé APRÈS le security.conf de Debian,
# qui sinon réécrirait ServerTokens) : ne pas annoncer les versions d'Apache et de PHP, limiter la
# taille des envois (photo de 3 Mo max + champs du formulaire).
RUN { \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo 'TraceEnable Off'; \
    } > /etc/apache2/conf-available/zz-security-hardening.conf \
    && a2enconf zz-security-hardening \
    && { \
        echo 'expose_php = Off'; \
        echo 'upload_max_filesize = 4M'; \
        echo 'post_max_size = 5M'; \
        echo 'session.cookie_httponly = 1'; \
    } > /usr/local/etc/php/conf.d/security.ini

WORKDIR /var/www/html

COPY . /var/www/html

# Dossiers où Apache doit pouvoir écrire : photos publiques, et storage/
# (photos de preuve PRIVÉES et journal des emails), hors de public/
RUN mkdir -p /var/www/html/public/uploads /var/www/html/storage/proofs /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/public/uploads /var/www/html/storage
