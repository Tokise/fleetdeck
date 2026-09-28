FROM php:8.3-apache

# Install PDO MySQL support
RUN docker-php-ext-install pdo pdo_mysql

# Make sure Apache uses only ONE MPM
RUN a2dismod mpm_event || true \
    && a2dismod mpm_worker || true \
    && a2enmod mpm_prefork

# Enable Apache rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy FleetDeck source code
COPY . /var/www/html/

# Correct permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]