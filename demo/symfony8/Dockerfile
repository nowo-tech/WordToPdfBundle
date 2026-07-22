# FrankenPHP + LibreOffice Writer for Word → PDF demo
# Prefer the newest PHP available in official FrankenPHP images when Symfony 8 allows it (REQ-DEMO-010).
FROM dunglas/frankenphp:1-php8.5-alpine

# LibreOffice needs real TTF/OTF fonts; without them PDF text becomes tofu boxes (□□□).
# procps provides pkill used to reap orphaned soffice children after Process timeouts.
RUN apk add --no-cache \
    git unzip bash \
    libreoffice \
    procps \
    fontconfig \
    font-dejavu \
    font-liberation \
    font-noto \
    font-noto-emoji \
    font-noto-cjk \
    && fc-cache -f

RUN install-php-extensions intl zip fileinfo

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN git config --global --add safe.directory '*'

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
ENV SERVER_NAME=:80

COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile.dev
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
