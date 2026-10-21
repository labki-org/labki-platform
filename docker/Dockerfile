ARG MEDIAWIKI_VERSION=1.46
ARG MEDIAWIKI_PATCH_VERSION=1.46.2
# PHP base is pinned to a patch release so security fixes are an explicit,
# reviewable bump. 8.3.36 fixes CVE-2026-109618 / CVE-2026-109619 (PHP2Shell,
# unauthenticated RCE, highest risk under mod_php).
ARG PHP_IMAGE=php:8.3.36-apache

# ==============================================================================
# MediaWiki base
# ==============================================================================
# Reproduces wikimedia/mediawiki-docker's <major>/apache/Dockerfile. We build
# it ourselves rather than FROM mediawiki:<major> so the PHP base is pinned
# here: the official tag only picks up PHP security releases whenever upstream
# rebuilds it, and stops entirely once the MediaWiki branch reaches EOL (as
# happened to 1.44, frozen on a PHP vulnerable to PHP2Shell).
FROM ${PHP_IMAGE} AS mediawiki
ARG MEDIAWIKI_VERSION
ARG MEDIAWIKI_PATCH_VERSION

# System dependencies
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		git \
		librsvg2-bin \
		imagemagick \
		python3 \
	; \
	rm -rf /var/lib/apt/lists/*

# PHP extensions MediaWiki needs
RUN set -eux; \
	savedAptMark="$(apt-mark showmanual)"; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libicu-dev \
		libonig-dev \
		liblua5.1-0-dev \
	; \
	docker-php-ext-install -j "$(nproc)" \
		calendar \
		intl \
		mbstring \
		mysqli \
		opcache \
	; \
	pecl install APCu-5.1.28; \
	pecl install LuaSandbox-4.1.2; \
	docker-php-ext-enable \
		apcu \
		luasandbox \
	; \
	rm -r /tmp/pear; \
	# reset apt-mark's "manual" list so that "purge --auto-remove" removes only build deps
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
		| sort -u \
		| xargs -r dpkg-query --search \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/lib/apt/lists/*

# Short URLs
RUN set -eux; \
	a2enmod rewrite; \
	{ \
		echo "<Directory /var/www/html>"; \
		echo "  RewriteEngine On"; \
		echo "  RewriteCond %{REQUEST_FILENAME} !-f"; \
		echo "  RewriteCond %{REQUEST_FILENAME} !-d"; \
		echo "  RewriteRule ^ %{DOCUMENT_ROOT}/index.php [L]"; \
		echo "</Directory>"; \
	} > "$APACHE_CONFDIR/conf-available/short-url.conf"; \
	a2enconf short-url

# AllowEncodedSlashes for VisualEditor
RUN sed -i "s/<\/VirtualHost>/\tAllowEncodedSlashes NoDecode\n<\/VirtualHost>/" "$APACHE_CONFDIR/sites-available/000-default.conf"

RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini

RUN set -eux; \
	mkdir -p /var/www/data; \
	chown -R www-data:www-data /var/www/data

ENV MEDIAWIKI_MAJOR_VERSION=${MEDIAWIKI_VERSION}
ENV MEDIAWIKI_VERSION=${MEDIAWIKI_PATCH_VERSION}

# Fetch and verify the MediaWiki release tarball
RUN set -eux; \
	fetchDeps=" \
		gnupg \
		dirmngr \
	"; \
	apt-get update; \
	apt-get install -y --no-install-recommends $fetchDeps; \
	curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz; \
	curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz.sig" -o mediawiki.tar.gz.sig; \
	export GNUPGHOME="$(mktemp -d)"; \
	# gpg keys from https://www.mediawiki.org/keys/keys.txt
	gpg --batch --keyserver keyserver.ubuntu.com --recv-keys \
		D7D6767D135A514BEB86E9BA75682B08E8A3FEC4 \
		441276E9CCD15F44F6D97D18C119E1A64D70938E \
		F7F780D82EBFB8A56556E7EE82403E59F9F8CD79 \
		1D98867E82982C8FE0ABC25F9B69B3109D3BB7B0 \
		E059C034E7A430583C252F4AA8F734246D73B586 \
		7759DE19A22260232F251342FC4D45E75D14112B \
	; \
	gpg --batch --verify mediawiki.tar.gz.sig mediawiki.tar.gz; \
	tar -x --strip-components=1 -f mediawiki.tar.gz; \
	gpgconf --kill all; \
	rm -r "$GNUPGHOME" mediawiki.tar.gz.sig mediawiki.tar.gz; \
	chown -R www-data:www-data extensions skins cache images; \
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false $fetchDeps; \
	rm -rf /var/lib/apt/lists/*

# Refuse to build on a PHP that is still vulnerable to PHP2Shell.
RUN php -r 'exit(version_compare(PHP_VERSION, "8.3.36", ">=") ? 0 : 1);' \
	|| { echo "PHP $(php -r 'echo PHP_VERSION;') is vulnerable to CVE-2026-109618/109619; need >= 8.3.36" >&2; exit 1; }

# ==============================================================================
# Labki base
# ==============================================================================
FROM mediawiki AS base
ARG MEDIAWIKI_VERSION

LABEL org.opencontainers.image.title="Labki Platform"
LABEL org.opencontainers.image.description="Pre-configured MediaWiki distribution with Semantic MediaWiki and curated extensions"
LABEL org.opencontainers.image.source="https://github.com/labki-org/labki-platform"
LABEL org.opencontainers.image.vendor="Labki"
LABEL org.opencontainers.image.version.mediawiki="${MEDIAWIKI_VERSION}"

SHELL ["/bin/bash", "-o", "pipefail", "-c"]
WORKDIR /var/www/html

# Install build dependencies (git: extensions, unzip: composer, mariadb-client: DB checks,
# python3: SyntaxHighlight_GeSHi's bundled pygmentize is a Python script).
RUN apt-get update && apt-get install -y \
    git unzip ca-certificates mariadb-client curl python3 \
    && rm -rf /var/lib/apt/lists/*

# Apache + PHP tuning for long-lived MediaWiki deployments.
# Opcache settings give a sensible baseline for a single-VPS install
# serving up to ~1000 users/day. Apache's MaxConnectionsPerChild is set
# to a large value purely as defensive hygiene against long-tail PHP
# memory leaks. LocalSettings files are opcache-blacklisted so admin
# config edits take effect immediately. Object caching routes to the
# database (see LocalSettings.base.php).
COPY docker/apache/labki-tuning.conf /etc/apache2/conf-available/labki-tuning.conf
COPY docker/php/labki-tuning.ini /usr/local/etc/php/conf.d/zz-labki-tuning.ini
COPY docker/php/opcache-blacklist.txt /usr/local/etc/php/opcache-blacklist.txt
RUN a2enconf labki-tuning

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Clone git-based extensions (before Composer so merge-plugin can discover their composer.json)
COPY extensions-git/sources.txt /tmp/sources.txt
COPY extensions-git/clone-extensions.sh /usr/local/bin/clone-extensions.sh
RUN chmod +x /usr/local/bin/clone-extensions.sh && /usr/local/bin/clone-extensions.sh /tmp/sources.txt

# NSFileRepo's nsfr_img_auth.php must live at the document root so MediaWiki's
# img_auth rewrite can reach it. Copy (don't symlink) — PHP's __DIR__ resolves
# through symlinks to the real path, defeating the relocation.
RUN cp /var/www/html/extensions/NSFileRepo/nsfr_img_auth.php /var/www/html/nsfr_img_auth.php

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
