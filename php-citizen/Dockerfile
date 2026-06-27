FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

# Enable mod_rewrite for Apache
RUN a2enmod rewrite

# Copy application
COPY . /var/www/html/

# Configure Apache to use index.php
RUN echo "DirectoryIndex index.php" >> /etc/apache2/apache2.conf

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:8000/health || exit 1

EXPOSE 8000