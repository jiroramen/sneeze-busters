FROM richarvey/php-apache-heroku:latest

# Laravelのファイルをコピー
COPY . /var/www/html

# 必要な設定
ENV WEBROOT /var/www/html/public
ENV APP_ENV production

# 依存関係のインストール
RUN composer install --no-dev --optimize-autoloader

# 権限の設定
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache