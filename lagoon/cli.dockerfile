FROM amazeeio/php:8.1-cli

#######################################################
# Install PHP extensions
#######################################################
RUN docker-php-ext-install pcntl
RUN docker-php-ext-install posix
RUN docker-php-ext-install exif
COPY lagoon/php.ini /usr/local/etc/php/php.ini

#######################################################
# Install Laoon Tools Globally
#######################################################
RUN wget -O /usr/bin/lagoon https://github.com/uselagoon/lagoon-cli/releases/download/v0.11.6/lagoon-cli-v0.11.6-linux-amd64 && chmod +x /usr/bin/lagoon
RUN wget -O /usr/bin/lagoon-sync https://github.com/amazeeio/lagoon-sync/releases/download/v0.4.6/lagoon-sync_0.4.6_linux_amd64 && chmod +x /usr/bin/lagoon-sync

#######################################################
# Copy files, and run installs for composer and yarn
#######################################################
COPY . /app
RUN COMPOSER_MEMORY_LIMIT=-1 composer install

# ENV APP_ENV=${LAGOON_ENVIRONMENT_TYPE}
ENV PAGER=less
ENV PHP_MEMORY_LIMIT=8192M
