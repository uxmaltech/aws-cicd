ARG ALPINE_VERSION="3.19"
ARG PHP_VERSION="8.2.15"

# Using alpine linux as the base image
FROM alpine:${ALPINE_VERSION}

# Declare maintainer
LABEL \
    Maintainer="Enrique Martinez <https://uxmal.tech>" \
    Description="NGINX with extensions on top of Alpine Linux."

# Install the required resources for the apache container to run
RUN apk add --no-cache \
	    bash \
        sed \
		vim \
        ca-certificates \
        apache2 \
        php82 \
        php82-apache2 \
        php82-mysqlnd \
        php82-mysqli \
        php82-zlib \
        php82-bz2 \
        php82-xml \
        php82-simplexml \
        php82-json \
        php82-gd \
        php82-iconv \
        php82-curl \
        php82-ldap \
        php82-mbstring \
        php82-gettext \
        php82-calendar \
        php82-openssl \
	    php82-ctype \
        php82-session \
        php82-dom \
        php82-pdo \
        php82-xsl \
        php82-pdo_mysql

# Default volumes to organise all the files under one roof (all of them are bind mounted into the container)

EXPOSE 80

STOPSIGNAL SIGQUIT

# To start apache in the background as a service
CMD ["/bin/sh"]
