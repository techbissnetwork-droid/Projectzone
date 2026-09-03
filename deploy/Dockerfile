# TECHBISS platform — production image.
# Builds to roughly 120 MB and needs no build step: the platform ships as source.
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache nginx supervisor \
 && docker-php-ext-install pdo pdo_mysql opcache \
 && rm -rf /var/cache/apk/*

# OPcache settings tuned for a source-shipped application.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.interned_strings_buffer=16'; \
      echo 'opcache.max_accelerated_files=8000'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.save_comments=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini \
 && { \
      echo 'expose_php=0'; \
      echo 'memory_limit=128M'; \
      echo 'upload_max_filesize=24M'; \
      echo 'post_max_size=25M'; \
      echo 'zlib.output_compression=1'; \
    } > /usr/local/etc/php/conf.d/techbiss.ini

WORKDIR /var/www/techbiss
COPY . /var/www/techbiss

RUN mkdir -p storage/db storage/cache storage/logs storage/uploads storage/backups \
 && chown -R www-data:www-data storage \
 && chmod -R 775 storage

COPY deploy/nginx.docker.conf /etc/nginx/http.d/default.conf
COPY deploy/supervisord.conf /etc/supervisord.conf

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=4s --start-period=15s \
  CMD wget -qO- http://127.0.0.1/health | grep -q '"status":"ok"' || exit 1

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
