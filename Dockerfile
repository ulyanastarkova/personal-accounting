FROM php:8.2-apache

# PostgreSQL драйвер для PDO
RUN apt-get update \
  && apt-get install -y --no-install-recommends libpq-dev \
  && docker-php-ext-install pdo pdo_pgsql \
  && apt-get purge -y --auto-remove \
  && rm -rf /var/lib/apt/lists/*

# Кладем проект в /var/www/html
WORKDIR /var/www/html
COPY . /var/www/html

# Делаем public документ-рутом Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/*.conf \
  && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Безопасные права (не строго обязательно, но полезно)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
