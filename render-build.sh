#!/usr/bin/env bash
# エラーが起きたら停止させる設定
set -o errexit

# 依存関係のインストール
composer install --no-dev --optimize-autoloader

# 【重要】設定とキャッシュをクリア（APIキーの反映のため）
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# 本番環境でテーブルを作り、デモユーザーを入れる
php artisan migrate --force
php artisan db:seed --force

# フロントエンドのビルド
npm install
npm run build