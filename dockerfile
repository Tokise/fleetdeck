FROM php:8.3-apache

# Install MySQL support for PDO
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set application directory
WORKDIR /var/www/html

# Copy FleetDeck into Apache
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]