ARG MEDIAWIKI_VERSION=1.44
FROM mediawiki:${MEDIAWIKI_VERSION} AS base
ARG MEDIAWIKI_VERSION

LABEL org.opencontainers.image.title="Labki Platform"
LABEL org.opencontainers.image.description="Pre-configured MediaWiki distribution with Semantic MediaWiki and curated extensions"
LABEL org.opencontainers.image.source="https://github.com/labki-org/labki-platform"
LABEL org.opencontainers.image.vendor="Labki"
LABEL org.opencontainers.image.version.mediawiki="${MEDIAWIKI_VERSION}"

SHELL ["/bin/bash", "-o", "pipefail", "-c"]
WORKDIR /var/www/html

# Install build dependencies (git: extensions, unzip: composer, mariadb-client: DB checks)
RUN apt-get update && apt-get install -y \
    git unzip ca-certificates mariadb-client curl \
    && rm -rf /var/lib/apt/lists/*

# Apache + PHP tuning for long-lived MediaWiki deployments.
# MaxConnectionsPerChild bounds Apache worker lifetime so in-process PHP
# state (SMW's in-memory caches in particular) can't accumulate stale
# entries indefinitely. Opcache + APCu settings give a sensible baseline
# for a single-VPS install serving up to ~1000 users/day.
COPY docker/apache/labki-tuning.conf /etc/apache2/conf-available/labki-tuning.conf
COPY docker/php/labki-tuning.ini /usr/local/etc/php/conf.d/zz-labki-tuning.ini
RUN a2enconf labki-tuning

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Clone git-based extensions (before Composer so merge-plugin can discover their composer.json)
COPY extensions-git/sources.txt /tmp/sources.txt
COPY extensions-git/clone-extensions.sh /usr/local/bin/clone-extensions.sh
RUN chmod +x /usr/local/bin/clone-extensions.sh && /usr/local/bin/clone-extensions.sh /tmp/sources.txt

# Install platform Composer dependencies
COPY composer/composer.platform.json /var/www/html/composer.local.json

# Setup platform configuration and assets
COPY mediawiki/ /opt/labki/mediawiki/
COPY scripts/ /opt/labki/scripts/
COPY docker/entrypoint.sh /entrypoint.sh
COPY resources/assets/ /var/www/html/resources/assets/
COPY resources/styles/ /var/www/html/resources/styles/
COPY resources/scripts/ /var/www/html/resources/scripts/

# Disable Composer audit blocking (PHPUnit 9.x advisory PKSA-z3gr-8qht-p93v)
RUN php -r ' \
    $c = json_decode(file_get_contents("composer.json"), true); \
    $c["config"]["audit"]["block-insecure"] = false; \
    file_put_contents("/tmp/composer.json", json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"); \
' && mv /tmp/composer.json composer.json

# ==============================================================================
# Production target (default)
# ==============================================================================
FROM base AS prod

RUN composer update --no-dev --prefer-dist --no-interaction --no-progress

RUN chown -R www-data:www-data /var/www/html/extensions /var/www/html/skins /var/www/html/vendor /var/www/html/resources /opt/labki \
    && chmod +x /entrypoint.sh /opt/labki/scripts/*.sh

ENV APACHE_DOCUMENT_ROOT=/var/www/html

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/wiki/Main_Page || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]

# ==============================================================================
# Development target (includes PHPUnit and dev dependencies)
# ==============================================================================
FROM base AS dev

RUN composer update --prefer-dist --no-interaction --no-progress

RUN chown -R www-data:www-data /var/www/html/extensions /var/www/html/skins /var/www/html/vendor /var/www/html/resources /opt/labki \
    && chmod +x /entrypoint.sh /opt/labki/scripts/*.sh

ENV APACHE_DOCUMENT_ROOT=/var/www/html

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/wiki/Main_Page || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
