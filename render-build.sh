#!/usr/bin/env bash
# エラーが起きたら停止させる設定
set -o errexit

# 依存関係のインストール
composer install --no-dev --optimize-autoloader

# 【重要】設定とキャッシュをクリア（APIキーの反映のため）
php artisan config:clear
php artisan cache:clear

# 【重要】データベースの準備（ログインエラーの解消のため）
# 既存のテーブルを一度消して作り直す「:refresh」を使い、確実にデモユーザーを入れます
php artisan migrate:refresh --force --seed