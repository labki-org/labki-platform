ARG MEDIAWIKI_VERSION=1.44
FROM mediawiki:${MEDIAWIKI_VERSION}

SHELL ["/bin/bash", "-o", "pipefail", "-c"]
WORKDIR /var/www/html

# Install build dependencies
# git: for cloning extensions
# unzip: for composer
# mariadb-client: for checking db status
RUN apt-get update && apt-get install -y \
    git unzip ca-certificates mariadb-client curl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 1. Clone Git Extensions
# We do this before Composer to ensure they are present if they contain composer.json files (for merge-plugin)
COPY extensions-git/sources.txt /tmp/sources.txt
COPY extensions-git/clone-extensions.sh /usr/local/bin/clone-extensions.sh
RUN chmod +x /usr/local/bin/clone-extensions.sh && /usr/local/bin/clone-extensions.sh /tmp/sources.txt

# 2. Install Platform Composer Dependencies
# We copy the platform composer config to the root
COPY composer/composer.platform.json /var/www/html/composer.local.json
# Note: In a real production build, we would also copy composer.lock here.
# COPY composer/composer.lock /var/www/html/composer.lock

# Run composer update (or install if lock exists)
# We use 'update' because we haven't generated the lock file in the platform repo yet. 
# Once generated, we should switch to 'install'.
RUN composer update --no-dev --prefer-dist --no-interaction --no-progress

# 3. Setup Platform Configuration
# We copy the platform-owned settings into a protected directory
COPY mediawiki/ /opt/labki/mediawiki/
COPY scripts/ /opt/labki/scripts/
COPY docker/entrypoint.sh /entrypoint.sh

# Ensure permissions
RUN chown -R www-data:www-data /var/www/html/extensions /var/www/html/skins /var/www/html/vendor /opt/labki \
    && chmod +x /entrypoint.sh /opt/labki/scripts/*.sh

ENV APACHE_DOCUMENT_ROOT=/var/www/html

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
