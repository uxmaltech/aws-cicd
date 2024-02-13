ARG ALPINE_VERSION="3.19"
ARG PHP_VERSION="8.2.15"

FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION}

LABEL \
    Maintainer="Enrique Martinez <https://uxmal.tech>" \
    Description="PHP-FPM v8.2 with extensions on top of Alpine Linux."

# Composer - https://getcomposer.org/download/
ARG COMPOSER_VERSION="2.6.6"
ARG COMPOSER_SUM="72600201c73c7c4b218f1c0511b36d8537963e36aafa244757f52309f885b314"

# Swoole - https://github.com/swoole/swoole-src
ARG SWOOLE_VERSION="5.1.1"

# Install dependencies
RUN set -eux \
    && apk add --no-cache \
        c-client \
        ca-certificates \
        freetype \
        gettext \
        gmp \
        icu-libs \
        imagemagick \
        libffi \
        libgmpxx \
        libintl \
        libjpeg-turbo \
        libpng \
        libpq \
        librdkafka \
        libssh2 \
        libstdc++ \
        libtool \
        libxpm \
        libxslt \
        libzip \
        lz4-libs \
        make \
        rabbitmq-c \
        tidyhtml \
        tzdata \
        unixodbc \
        vips \
        yaml \
        zstd-libs \
    && true

#############################################
### Install and enable PHP extensions
#############################################

# Development dependencies
RUN set -eux \
    && apk add --no-cache --virtual .build-deps \
        autoconf \
        bzip2-dev \
        cmake \
        curl-dev \
        freetype-dev \
        g++ \
        gcc \
        gettext-dev \
        git \
        gmp-dev \
        icu-dev \
        imagemagick-dev \
        krb5-dev \
        libc-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        librdkafka-dev \
        libssh2-dev \
        libwebp-dev \
        libxml2-dev \
        libxpm-dev \
        libxslt-dev \
        libzip-dev \
        lz4-dev \
        openssl-dev \
        pcre-dev \
        pkgconf \
        rabbitmq-c-dev \
        tidyhtml-dev \
        unixodbc-dev \
        vips-dev \
        yaml-dev \
        zlib-dev \
        zstd-dev \
    && true

# Install PHP extensions
RUN set -eux \
# Install rdkafka
#    && pecl install rdkafka \
#    && docker-php-ext-enable rdkafka \
#    && true \
#\
# Install php-rdkafka - oauthbearer-set-token (TODO: Remove when merged)
    && curl -Lo /tmp/php-rdkafka.zip  https://github.com/cb-freddysart/php-rdkafka/archive/refs/heads/feat/oauthbearer-set-token.zip \
    && unzip /tmp/php-rdkafka.zip -d /tmp \
    && cd /tmp/php-rdkafka-feat-oauthbearer-set-token \
    && phpize \
    && ./configure \
    && make -j$(nproc) \
    && make install \
    && rm -f /tmp/php-rdkafka.zip \
    && rm -rf /tmp/php-rdkafka-feat-oauthbearer-set-token \
    && docker-php-ext-enable rdkafka \
    && true

RUN set -eux \
# Workaround for rabbitmq linking issue
    && ln -s /usr/lib /usr/local/lib64 \
\
# Enable ffi if it exists
    && set -eux \
    && if [ -f /usr/local/etc/php/conf.d/docker-php-ext-ffi.ini ]; then \
        echo "ffi.enable = 1" >> /usr/local/etc/php/conf.d/docker-php-ext-ffi.ini; \
    fi \
\
################################
# Install PHP extensions
################################
\
# Install gd
    && ln -s /usr/lib/$(apk --print-arch)-linux-gnu/libXpm.* /usr/lib/ \
    && docker-php-ext-configure gd \
        --enable-gd \
        --with-webp \
        --with-jpeg \
        --with-xpm \
        --with-freetype \
        --enable-gd-jis-conv \
    && docker-php-ext-install -j$(nproc) gd \
    && true \
\
# Install amqp
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && true \
\
# Install apcu
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && true \
\
# Install gettext
    && docker-php-ext-install -j$(nproc) gettext \
    && true \
\
# Install gmp
    && docker-php-ext-install -j$(nproc) gmp \
    && true \
\
# Install bcmath
    && docker-php-ext-install -j$(nproc) bcmath \
    && true \
\
# Install bz2
    && docker-php-ext-install -j$(nproc) bz2 \
    && true \
\
# Install exif
    && docker-php-ext-install -j$(nproc) exif \
    && true \
\
# Install imagick
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && true \
\
# Install igbinary
    && pecl install igbinary \
    && docker-php-ext-enable igbinary \
\
# Install intl
    && docker-php-ext-install -j$(nproc) intl \
    && true \
\
# Install lz4
    && git clone https://github.com/kjdev/php-ext-lz4 /tmp/lz4 \
    && cd /tmp/lz4 \
    && git checkout $(git tag | grep -E '^[.0-9]+$' | sort -V | tail -1) \
    && phpize \
    && ./configure --enable-lz4 --with-lz4-includedir=/usr \
    && make -j$(nproc) \
    && make install \
    && docker-php-ext-enable lz4 \
    && true \
\
# Install memcache
    && pecl install memcache \
    && docker-php-ext-enable memcache \
    && true \
\
# Install mongodb
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && true \
\
# Install msgpack
    && pecl install msgpack \
    && docker-php-ext-enable msgpack \
    && true \
\
# Install mysqli
    && docker-php-ext-install -j$(nproc) mysqli \
    && true \
\
# Install oauth
    && pecl install oauth \
    && docker-php-ext-enable oauth \
    && true \
\
# Install opcache
    && docker-php-ext-install -j$(nproc) opcache \
    && true \
\
# Install pdo_mysql
    && docker-php-ext-configure pdo_mysql --with-zlib-dir=/usr \
    && docker-php-ext-install -j$(nproc) pdo_mysql \
    && true \
\
# Install pcntl
    && docker-php-ext-install -j$(nproc) pcntl \
    && true \
\
# Install redis
    && pecl install --configureoptions \
        'enable-redis-igbinary="yes" enable-redis-zstd="yes" enable-redis-msgpack="yes" enable-redis-lz4="yes"' \
        redis \
    && docker-php-ext-enable redis \
    && true \
\
# Install soap
    && docker-php-ext-install -j$(nproc) soap \
    && true \
\
# Install ssh2
    && pecl install ssh2-1.3.1 \
    && docker-php-ext-enable ssh2 \
    && true \
\
# Install sockets, sysvmsg, sysvsem, sysvshm (also needed by swoole)
    && CFLAGS="${CFLAGS:=} -D_GNU_SOURCE" docker-php-ext-install -j$(nproc) \
        sockets \
        sysvmsg \
        sysvsem \
        sysvshm \
    && docker-php-source extract \
    && true \
\
# Install swoole (64-bit targets only)
# https://github.com/swoole/swoole-src/issues/5158
    && if [ "$(uname -m)" = "x86_64" ] || [ "$(uname -m)" = "aarch64" ]; then \
        mkdir /usr/src/php/ext/swoole; \
        curl -Lo swoole.tar.gz https://github.com/swoole/swoole-src/archive/v${SWOOLE_VERSION}.tar.gz; \
        tar xfz swoole.tar.gz --strip-components=1 -C /usr/src/php/ext/swoole; \
        docker-php-ext-configure swoole \
                --enable-mysqlnd \
                --enable-sockets \
                --enable-openssl \
                --enable-swoole-curl; \
        docker-php-ext-install -j$(nproc) swoole; \
        rm -rf swoole.tar.gz $HOME/.composer/*-old.phar; \
        docker-php-ext-enable swoole; \
        true; \
    fi \
\
# Install tidy
    && docker-php-ext-install -j$(nproc) tidy \
    && true \
\
# Install uuid
    && pecl install uuid \
    && docker-php-ext-enable uuid \
    && true \
\
# Install xsl
    && docker-php-ext-install -j$(nproc) xsl \
    && true \
\
# Install xdebug
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && true \
\
# Install yaml
    && pecl install yaml \
    && docker-php-ext-enable yaml \
    && true \
\
# Install vips
    && pecl install vips \
    && docker-php-ext-enable vips \
    && true \
\
# Install zip
    && docker-php-ext-configure zip --with-zip \
    && docker-php-ext-install -j$(nproc) zip \
    && true \
\
# Install zstd
    && pecl install zstd \
    && docker-php-ext-enable zstd \
    && true \
\
# Clean up build packages
    && docker-php-source delete \
    && apk del .build-deps \
    && true

RUN set -eux \
# Fix php.ini settings for enabled extensions
    && chmod +x "$(php -r 'echo ini_get("extension_dir");')"/* \
# Shrink binaries
    && (find /usr/local/bin -type f -print0 | xargs -n1 -0 strip --strip-all -p 2>/dev/null || true) \
    && (find /usr/local/lib -type f -print0 | xargs -n1 -0 strip --strip-all -p 2>/dev/null || true) \
    && (find /usr/local/sbin -type f -print0 | xargs -n1 -0 strip --strip-all -p 2>/dev/null || true) \
    && true

# Install Composer
RUN set -eux \
    && curl -LO "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" \
    && echo "${COMPOSER_SUM}  composer.phar" | sha256sum -c - \
    && chmod +x composer.phar \
    && mv composer.phar /usr/local/bin/composer \
    && composer --version \
    && true

RUN set -eux \
# PHP-FPM templates directory
    && mkdir -p /var/data/php-fpm \
# Remove few PHP-FPM default config files
    && rm -rf /usr/local/etc/php-fpm.d/zz-docker.conf \
    && rm -rf /usr/local/etc/php-fpm.d/docker.conf \
\
# Perform PHP-FPM testing
    && echo "Performing PHP-FPM tests..." \
    && echo "date.timezone=UTC" > /usr/local/etc/php/php.ini \
    && php -v | grep -oE 'PHP\s[.0-9]+' | grep -oE '[.0-9]+' | grep '^8.2' \
    && /usr/local/sbin/php-fpm --test \
\
    && PHP_ERROR="$( php -v 2>&1 1>/dev/null )" \
    && if [ -n "${PHP_ERROR}" ]; then echo "${PHP_ERROR}"; false; fi \
    && PHP_ERROR="$( php -i 2>&1 1>/dev/null )" \
    && if [ -n "${PHP_ERROR}" ]; then echo "${PHP_ERROR}"; false; fi \
\
    && PHP_FPM_ERROR="$( php-fpm -v 2>&1 1>/dev/null )" \
    && if [ -n "${PHP_FPM_ERROR}" ]; then echo "${PHP_FPM_ERROR}"; false; fi \
    && PHP_FPM_ERROR="$( php-fpm -i 2>&1 1>/dev/null )" \
    && if [ -n "${PHP_FPM_ERROR}" ]; then echo "${PHP_FPM_ERROR}"; false; fi \
    && rm -f /usr/local/etc/php/php.ini \
    && true

EXPOSE 9000

STOPSIGNAL SIGQUIT

CMD ["/bin/sh"]
