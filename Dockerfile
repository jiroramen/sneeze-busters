# 1. 安定していて使いやすい公式ベースイメージに変更
FROM webdevops/php-apache:8.2

# 2. 作業ディレクトリを設定
WORKDIR /app

# 3. 必要なシステムファイルをインストール
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql pdo_pgsql

# 4. Apacheの設定（Laravelのpublicフォルダを公開するように設定）
ENV WEB_DOCUMENT_ROOT=/app/public

# 5. プロジェクトのファイルをすべてコピー
COPY . .

# 6. Composer（PHPのライブラリ管理）をインストールして実行
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# 7. フォルダの権限を変更（Laravelが動くために必要）
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache