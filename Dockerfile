FROM php:8.4-apache

# Sistem bağımlılıklarını yükle
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    sqlite3 \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP uzantılarını yükle
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_sqlite \
    gd \
    zip

# Apache mod_rewrite'ı etkinleştir
RUN a2enmod rewrite

# Composer'ı yükle
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Çalışma dizinini ayarla
WORKDIR /var/www/html

# Composer dosyalarını kopyala
COPY composer.json composer.lock ./

# Bağımlılıkları yükle
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Proje dosyalarını kopyala
COPY . .

# Apache yapılandırmasını kopyala
COPY docker/apache-config.conf /etc/apache2/sites-available/000-default.conf

# Veritabanı dizinini oluştur ve izinleri ayarla
RUN mkdir -p /var/www/html/data && \
    chmod -R 775 /var/www/html/data && \
    chown -R www-data:www-data /var/www/html/data

# Public dizini izinlerini ayarla
RUN chown -R www-data:www-data /var/www/html/public && \
    chmod -R 755 /var/www/html/public

# Veritabanı dosyasını kopyala (eğer yoksa)
RUN if [ ! -f /var/www/html/data/vatanbilet.db ]; then \
    touch /var/www/html/data/vatanbilet.db && \
    chown www-data:www-data /var/www/html/data/vatanbilet.db && \
    chmod 664 /var/www/html/data/vatanbilet.db; \
    fi

# Port 80'i aç
EXPOSE 80

# Apache'yi başlat
CMD ["apache2-foreground"]