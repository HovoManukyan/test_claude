FROM dunglas/frankenphp:1.4.2-php8.3.16-bookworm AS base

# https://cloud.google.com/architecture/best-practices-for-building-containers

# install locales and switch to en_US.utf8 in order to enable UTF-8 support
# see https://github.com/docker-library/php/issues/240#issuecomment-305038173

ENV TZ=UTC
ENV LC_ALL=en_US.utf8 LANG=en_US.utf8 LANGUAGE=en_US.utf8
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV APP_RUNTIME="Runtime\\FrankenPhpSymfony\\Runtime"

RUN apt-get update && \
  apt-get install -q -y \
    --no-install-recommends \
    --no-install-suggests \
    --option=Dpkg::Options::=--force-confdef \
    locales git && \
  sed -i '/en_US.UTF-8/s/^# //g' /etc/locale.gen && \
  touch /usr/share/locale/locale.alias && \
  locale-gen && \
  curl -sSLf \
    -o /usr/local/bin/install-php-extensions \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
  chmod +x /usr/local/bin/install-php-extensions && \
  install-php-extensions @composer \
    opcache-^8.3@stable \
    bcmath \
    exif \
    gd-^2.1@stable \
    gettext \
    intl \
    pcntl \
    pgsql \
    pdo_pgsql \
    redis-^6.0@stable \
    sockets \
    timezonedb \
    zip && \
  addgroup --gid 1000 --system www && \
  adduser --uid 1000 --system --disabled-password --ingroup tty www && \
  # Set permissions
  mkdir -p /var/www/backend/var/cache/ && \
  mkdir -p /var/www/backend/var/log/ && \
  chown -R www:www /var/www && \
  chown -R www:www /run && \
  chown -R www:www /tmp

WORKDIR /var/www/backend

CMD ["/usr/local/bin/frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

########################################################################################################################
FROM base AS dev

RUN curl https://raw.githubusercontent.com/vishnubob/wait-for-it/master/wait-for-it.sh -o /usr/local/bin/wait-for-it.sh && \
    chmod +x /usr/local/bin/wait-for-it.sh

COPY ./php-config/dev/Caddyfile /etc/caddy/Caddyfile
RUN cp $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

########################################################################################################################
FROM base AS full

ENV GODEBUG=cgocheck=0
ENV GOMEMLIMIT=256MiB

COPY ./apps/backend/php-config/prod/Caddyfile /etc/caddy/Caddyfile
COPY ./apps/backend/php-config/prod/php.ini /usr/local/etc/php/php.ini
COPY ./apps/backend/php-config/prod/entrypoint.sh /var/entrypoint.sh
COPY ./apps/backend /var/www/backend

RUN chmod +x /var/entrypoint.sh && \
    chmod +x /var/www/backend/bin/sync-translations.sh && \
    composer install \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
        --no-dev \
        --classmap-authoritative

ENTRYPOINT ["/var/entrypoint.sh"]

CMD ["/usr/local/bin/frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
