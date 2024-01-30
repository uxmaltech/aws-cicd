FROM php:8.2.13-apache

# Declare maintainer
LABEL \
    Maintainer="Enrique Martinez <https://uxmal.tech>" \
    Description="NGINX with extensions on top of Alpine Linux."

# Timezone
ENV TIMEZONE America/Monterrey

RUN apt-get update -yqq \
  && apt-get install -yqq --no-install-recommends \
    git \
    zip \
    unzip \
    libpng-dev \
    libbz2-dev \
    libxml2-dev \
    libxslt-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    locate \
    locales \
    locales-all \
    vim \
  && rm -rf /var/lib/apt/lists

ENV LC_ALL en_US.UTF-8
ENV LANG en_US.UTF-8
ENV LANGUAGE en_US.UTF-8

# Enable PHP extensions
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql
RUN docker-php-ext-install bz2 && docker-php-ext-enable bz2
RUN docker-php-ext-install calendar && docker-php-ext-enable calendar
RUN docker-php-ext-install gettext && docker-php-ext-enable gettext
#RUN docker-php-ext-install igbinary && docker-php-ext-enable igbinary
RUN docker-php-ext-install pcntl && docker-php-ext-enable pcntl
RUN docker-php-ext-install shmop && docker-php-ext-enable shmop
RUN docker-php-ext-install soap && docker-php-ext-enable soap
RUN docker-php-ext-install sockets && docker-php-ext-enable sockets
RUN docker-php-ext-install sysvmsg && docker-php-ext-enable sysvmsg
RUN docker-php-ext-install sysvsem && docker-php-ext-enable sysvsem
RUN docker-php-ext-install sysvshm && docker-php-ext-enable sysvshm
#RUN docker-php-ext-install wddx && docker-php-ext-enable wddx
RUN docker-php-ext-install xsl && docker-php-ext-enable xsl
#RUN docker-php-ext-install zip && docker-php-ext-enable zip

RUN apt-get update -y && \
    pecl install igbinary && \
    docker-php-ext-enable igbinary

RUN apt-get -yqq update
RUN apt-get -yqq install exiftool
RUN docker-php-ext-configure exif
RUN docker-php-ext-install exif
RUN docker-php-ext-enable exif

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# Enable Apache modules and restart
RUN a2enmod rewrite \
 && a2enmod headers \
 && sed -E -i -e 's/max_file_uploads = 20/max_file_uploads = 256/' /usr/local/etc/php/php.ini-development \
 && sed -E -i -e 's/; max_input_vars = 1000/max_input_vars = 1000000/' /usr/local/etc/php/php.ini-development \
 && sed -E -i -e 's/post_max_size = 8M/post_max_size = 512M/' /usr/local/etc/php/php.ini-development \
 && sed -E -i -e 's/upload_max_filesize = 2M/upload_max_filesize = 256M/' /usr/local/etc/php/php.ini-development \
 && sed -E -i -e 's/memory_limit = 128M/memory_limit = -1/' /usr/local/etc/php/php.ini-development \
 && sed -E -i -e 's/max_file_uploads = 20/max_file_uploads = 256/' /usr/local/etc/php/php.ini-production \
 && sed -E -i -e 's/; max_input_vars = 1000/max_input_vars = 1000000/' /usr/local/etc/php/php.ini-production \
 && sed -E -i -e 's/post_max_size = 8M/post_max_size = 512M/' /usr/local/etc/php/php.ini-production \
 && sed -E -i -e 's/upload_max_filesize = 2M/upload_max_filesize = 256M/' /usr/local/etc/php/php.ini-production \
 && sed -E -i -e 's/memory_limit = 128M/memory_limit = -1/' /usr/local/etc/php/php.ini-production \
 && cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
 && service apache2 restart

EXPOSE 80
