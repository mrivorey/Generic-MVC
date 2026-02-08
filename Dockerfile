FROM php:8.5-apache

# Install required extensions (opcache is bundled in PHP 8.5)
RUN docker-php-ext-install pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite

# Configure Apache
RUN sed -i 's/80/8088/g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/80/8088/g' /etc/apache2/ports.conf

# Set document root to public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configure PHP
RUN echo "session.save_path = /var/www/html/storage/sessions" >> /usr/local/etc/php/conf.d/sessions.ini

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Expose port 8088
EXPOSE 8088

# Set proper permissions for storage
RUN mkdir -p /var/www/html/storage/sessions \
    && chown -R www-data:www-data /var/www/html/storage
