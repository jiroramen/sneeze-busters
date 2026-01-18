# 1. Laravelに最適化されたベースイメージ（設定が楽なものに変更）
FROM php:8.2-apache

# 2. 必要なライブラリのインストール（PostgreSQL用の libpq-dev を追加しました）
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql pdo_pgsql

# 3. Apacheの設定（公開ディレクトリをpublicに変更）
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/伺服器/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN a2enmod rewrite

# 4. プロジェクトのファイルをコピー
WORKDIR /var/www/html
COPY . .

# 5. Composerをインストールして依存関係を解決
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# 6. フォルダの権限設定
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 7. ポート設定
EXPOSE 80

# 8. データベースのマイグレーションとシーディングの実行
RUN php artisan migrate --force && php artisan db:seed --force