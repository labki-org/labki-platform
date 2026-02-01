ARG MEDIAWIKI_VERSION=1.44
FROM mediawiki:${MEDIAWIKI_VERSION} AS base

LABEL org.opencontainers.image.title="Labki Platform"
LABEL org.opencontainers.image.description="Pre-configured MediaWiki distribution with Semantic MediaWiki and curated extensions"
LABEL org.opencontainers.image.source="https://github.com/labki-org/labki-platform"
LABEL org.opencontainers.image.vendor="Labki"

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

# 3. Setup Platform Configuration
# We copy the platform-owned settings into a protected directory
COPY mediawiki/ /opt/labki/mediawiki/
COPY scripts/ /opt/labki/scripts/
COPY docker/entrypoint.sh /entrypoint.sh

# 4. Copy Platform Assets (badge, logos, etc.)
COPY resources/assets/ /var/www/html/resources/assets/

# ==============================================================================
# Production target (default)
# ==============================================================================
FROM base AS prod

# Disable Composer blocking packages with security advisories during resolution
# (PHPUnit 9.x has advisory PKSA-z3gr-8qht-p93v; dev-only, not shipped in prod)
RUN php -r ' \
    $c = json_decode(file_get_contents("composer.json"), true); \
    $c["config"]["audit"]["block-insecure"] = false; \
    file_put_contents("/tmp/composer.json", json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"); \
' && mv /tmp/composer.json composer.json

# Run composer without dev dependencies
RUN composer update --no-dev --prefer-dist --no-interaction --no-progress

# Ensure permissions
RUN chown -R www-data:www-data /var/www/html/extensions /var/www/html/skins /var/www/html/vendor /var/www/html/resources/assets /opt/labki \
    && chmod +x /entrypoint.sh /opt/labki/scripts/*.sh

ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Health check - verify Apache is responding
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/wiki/Main_Page || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]

# ==============================================================================
# Development target (includes PHPUnit and dev dependencies)
# ==============================================================================
FROM base AS dev

# Disable Composer blocking packages with security advisories during resolution
# (PHPUnit 9.x has advisory PKSA-z3gr-8qht-p93v; accepted risk for dev/test)
RUN php -r ' \
    $c = json_decode(file_get_contents("composer.json"), true); \
    $c["config"]["audit"]["block-insecure"] = false; \
    file_put_contents("/tmp/composer.json", json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"); \
' && mv /tmp/composer.json composer.json

# Run composer WITH dev dependencies (includes PHPUnit, etc.)
RUN composer update --prefer-dist --no-interaction --no-progress

# Ensure permissions
RUN chown -R www-data:www-data /var/www/html/extensions /var/www/html/skins /var/www/html/vendor /var/www/html/resources/assets /opt/labki \
    && chmod +x /entrypoint.sh /opt/labki/scripts/*.sh

ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Health check - verify Apache is responding
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/wiki/Main_Page || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
