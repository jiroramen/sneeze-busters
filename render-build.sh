#!/usr/bin/env bash
exit on error
set -o errexit

composer install --no-dev --optimize-autoloader

# データベースのテーブル作成（初回のみでOKですが、入れておくと安心）
php artisan migrate --force