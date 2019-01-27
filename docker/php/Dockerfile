# Use PHP 7 running on Debian stretch
FROM php:7.3.0RC4-stretch

# Get the proxy address from the docker-compose.yml file
ARG http_proxy
ARG https_proxy
ARG HTTP_PROXY
ARG HTTPS_PROXY

# Set the proxy address environment variables
ENV http_proxy $http_proxy
ENV https_proxy $https_proxy
ENV HTTP_PROXY $HTTP_PROXY
ENV HTTPS_PROXY $HTTPS_PROXY

# Use the custom PHP config
COPY php.ini /usr/local/etc/php/

# Also copy the entrypoint script
RUN if [ ! -d /var/www/testtaker ]; then echo mkdir -p /var/www/testtaker; fi
RUN mkdir -p /var/www/testtaker/docker/php/
COPY entrypoint.sh /var/www/testtaker/docker/php/

# Install required packages
RUN apt-get update \
  && apt-get install -y build-essential libssl-dev git libpng-dev vim zip unzip libjpeg62-turbo-dev libmcrypt-dev \
     sudo wget nodejs nodejs-legacy exim4 g++ net-tools locales gnupg2 make gcc libzip-dev pngquant redis-server\
  && docker-php-ext-install pdo_mysql mysqli mbstring gd iconv zip bcmath

# Install latest node version
RUN curl -sL https://deb.nodesource.com/setup_11.x | bash -
RUN apt-get update && apt-get install -y nodejs

# Install composer system-wide using curl (php command does not honor proxy environment variables)
RUN curl 'https://getcomposer.org/installer' -o 'composer-setup.php' \
  && php -r "if (hash_file('SHA384', 'composer-setup.php') === \
        '93b54496392c062774670ac18b134c3b3a95e5a5e5c8f1a9f115f203b75bf9a129d5daa8ba6a13e2cc8a1da0806388a8') \
        { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
  && php composer-setup.php \
  && php -r "unlink('composer-setup.php');" \
  && mv composer.phar /usr/local/bin/composer \
  && echo 'export PATH="$HOME/.composer/vendor/bin:$PATH"' >> ~/.bashrc

# Install chrome and dusk devDependencies
RUN curl -sL https://dl-ssl.google.com/linux/linux_signing_key.pub | apt-key add -
RUN sh -c 'echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" >> /etc/apt/sources.list.d/google-chrome.list'
RUN apt-get update && apt-get install -y google-chrome-stable
RUN apt-get install -y xvfb
RUN apt-get install -y libnss3-dev libxi6 libgconf-2-4

# Add a cron for laravel schedule tasks
RUN echo "* * * * * testtaker cd /var/www/testtaker/ && php artisan schedule:run >> /dev/null 2>&1" >> /etc/crontab

# Add an testtaker user, add it to the sudoers and use it
RUN useradd -m -d /home/testtaker -s /bin/bash testtaker && echo "testtaker:testtaker" | chpasswd && adduser testtaker sudo
RUN echo "testtaker	ALL=(ALL:ALL) NOPASSWD:ALL" >> /etc/sudoers
USER testtaker
WORKDIR /home/testtaker

# Install laravel using composer
RUN composer global require "laravel/installer"

# Change work directory
WORKDIR /var/www/testtaker
RUN sudo chown testtaker -R /var/www/testtaker/

# Switch back to root otherwise apache won't start
USER root

# But run the entrypoint script as the testtaker user
ENTRYPOINT sudo -E -s -u testtaker docker/php/entrypoint.sh
