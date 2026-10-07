FROM php:8.4-fpm-bookworm AS base
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev libonig-dev libxml2-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libaio1 unzip libfcgi-bin \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip mbstring opcache gd xmlreader \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

FROM base AS dependencies
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-interaction --no-progress
COPY . ./
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && mkdir -p var/cache var/log var/sessions && chown -R www-data:www-data var

FROM base AS test
COPY . ./
RUN composer install --prefer-dist --no-interaction --no-scripts --no-progress
CMD ["php", "vendor/bin/phpunit"]

FROM base AS runtime
# Instant Client 19 supports older Winthor Oracle installations; validate the server version with DBA.
ARG ORACLE_BASIC_URL=https://download.oracle.com/otn_software/linux/instantclient/1932000/instantclient-basic-linux.x64-19.32.0.0.0dbru.zip
ARG ORACLE_SDK_URL=https://download.oracle.com/otn_software/linux/instantclient/1932000/instantclient-sdk-linux.x64-19.32.0.0.0dbru.zip
ARG ORACLE_BASIC_SHA256=1749ca1eb5f75b038f2b7ce0abc42eb4575928159259fc4fc851c54a8ca7b002
ARG ORACLE_SDK_SHA256=1698b3be6dde8b7e90501a62122cb9c160c712eb709deb7129d915a504b76158
RUN mkdir -p /opt/oracle \
    && curl --fail --retry 3 -L "$ORACLE_BASIC_URL" -o /tmp/basic.zip \
    && curl --fail --retry 3 -L "$ORACLE_SDK_URL" -o /tmp/sdk.zip \
    && printf '%s  %s\n' "$ORACLE_BASIC_SHA256" /tmp/basic.zip "$ORACLE_SDK_SHA256" /tmp/sdk.zip | sha256sum -c - \
    && unzip -oq /tmp/basic.zip -d /opt/oracle && unzip -oq /tmp/sdk.zip -d /opt/oracle \
    && printf '/opt/oracle/instantclient_19_32\n' > /etc/ld.so.conf.d/oracle.conf && ldconfig \
    && printf 'instantclient,/opt/oracle/instantclient_19_32\n' | pecl install pdo_oci-1.2.0 \
    && docker-php-ext-enable pdo_oci && rm /tmp/basic.zip /tmp/sdk.zip
COPY --from=dependencies --chown=www-data:www-data /app /app
COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/gcom-entrypoint
USER www-data
ENTRYPOINT ["/usr/local/bin/gcom-entrypoint"]
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s CMD SCRIPT_FILENAME=/app/public/index.php SCRIPT_NAME=/index.php REQUEST_URI=/api/health REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q '"ok"' || exit 1
CMD ["php-fpm", "-F"]
