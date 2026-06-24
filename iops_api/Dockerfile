# iops_api/Dockerfile - PHP 8.2 LTS

# Usar imagen oficial PHP 8.2 con Apache
FROM php:8.2-apache

# ⚙️ Instalar dependencias del sistema y extensiones de PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    chromium \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath

# ⚙️ Variables de entorno para Spatie Browsershot y Puppeteer
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

# Habilitamos mod_rewrite de Apache
RUN a2enmod rewrite

# Configurar ServerName globalmente para evitar advertencias
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copiamos nuestro archivo de configuración de Apache
COPY ./docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Copiamos nuestra configuración personalizada de PHP (timeouts, memoria, etc.)
COPY ./docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Instalar Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Establecer el directorio de trabajo
WORKDIR /var/www/html

# --- MEJORA DE OPTIMIZACIÓN ---
# Copiar solo los archivos de dependencias primero para aprovechar el caché de Docker
COPY composer.json ./
COPY package.json package-lock.json* ./

# Instalar las dependencias de Composer durante la construcción
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Instalar Node Modules (necesario para Puppeteer/Browsershot)
RUN npm install

# Ahora copiar el resto del código de la aplicación
COPY . .

# --- CORRECCIÓN CRÍTICA ---
# Crear las carpetas de storage que Laravel necesita
RUN mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs

# Asignar permisos correctos a las carpetas específicas
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# Generar el autoloader de Composer y descubrir paquetes
RUN composer dump-autoload --optimize && \
    php artisan package:discover --ansi

# Exponer el puerto 80 que usa Apache
EXPOSE 80
