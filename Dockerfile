FROM php:8.3-apache-bookworm

# Install MySQL PDO support
RUN docker-php-ext-install pdo_mysql

# Enable Apache rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

# Remove conflicting Apache MPMs and make Apache
# listen on Railway's dynamically assigned PORT.
CMD ["sh", "-c", "rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf && a2enmod mpm_prefork >/dev/null 2>&1 || true; sed -i \"s/Listen 80/Listen ${PORT:-8080}/\" /etc/apache2/ports.conf; sed -i \"s/<VirtualHost \\*:80>/<VirtualHost *:${PORT:-8080}>/\" /etc/apache2/sites-available/000-default.conf; exec apache2-foreground"]