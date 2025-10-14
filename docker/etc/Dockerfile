FROM php:8.3-fpm-bullseye

RUN usermod -u 1000 www-data \
    && groupmod -g 1000 www-data

RUN apt-get update \
    && apt-get install -y \
        git \
        bash \
        jpegoptim \
        less \
        nginx \
        sudo \
        supervisor \
        wget \
        zip \
        unzip \
        sudo \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        libxslt1-dev \
        ca-certificates \
    	procps \
    	nano \
        --no-install-recommends \
    && rm -f /etc/nginx/conf.d/default.conf \
    && rm -rf /var/lib/apt/lists/*

COPY docker/etc/php/conf.d/docker-php-ext-xdebug.ini $PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini

RUN docker-php-source extract; \
    pecl install redis-6.1.0 amqp-2.1.2 xdebug-3.4.5; \
    docker-php-ext-install bcmath intl opcache pdo pdo_mysql pdo_pgsql sockets xsl zip ftp; \
    docker-php-ext-enable redis amqp xdebug; \
    docker-php-ext-configure pcntl --enable-pcntl && docker-php-ext-install pcntl && rm -f $PHP_INI_DIR/conf.d/docker-php-ext-pcntl.ini; \
    docker-php-source delete; \
    echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini;

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"; \
    php -r "if (hash_file('SHA384', 'composer-setup.php') === '$(wget -q -O - https://composer.github.io/installer.sig)') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"; \
    php composer-setup.php --filename=composer; \
    php -r "unlink('composer-setup.php');"; \
    mv composer /usr/local/bin/composer;

# Configure nginx
COPY docker/etc/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/etc/nginx/conf.d/service.conf /etc/nginx/conf.d/service.conf

# Configure PHP-FPM
COPY docker/etc/php/php.ini-development /usr/local/etc/php/php.ini
COPY docker/etc/php/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf

# Configure supervisord
COPY docker/etc/supervisord/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Make sure files/folders needed by the processes are accessable when they run under the www-data user
RUN set -eux; \
    mkdir -p /var/www/html; \
    mkdir -p /var/www/.composer; \
    mkdir -p /var/www/html/var/cache; \
    chown -R www-data:www-data /var/www/html /var/www/.composer /run /var/lib/nginx /var/log/nginx; \
    chmod 777 /var/www/html; \
    git config --global --add safe.directory /var/www/html

WORKDIR /var/www/html
RUN PATH=$PATH:/var/www/html/vendor/bin:bin
EXPOSE 80

# add www-data user to sudo so it can start supervisor as root
RUN echo "www-data  ALL=(ALL) NOPASSWD: /usr/bin/supervisord" >> /etc/sudoers

USER www-data

# Let supervisord start nginx & php-fpm
CMD ["sudo", "/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
