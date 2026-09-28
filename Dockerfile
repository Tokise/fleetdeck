FROM php:8.3-apache-bookworm

# Install MySQL PDO support
RUN docker-php-ext-install pdo_mysql

# Enable URL rewriting
RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

# At container startup, make sure ONLY prefork is enabled
CMD ["sh", "-c", "rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf && a2enmod mpm_prefork >/dev/null 2>&1 || true; exec apache2-foreground"]