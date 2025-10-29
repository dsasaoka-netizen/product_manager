# Slim Framework 4 Skeleton Application

[![Coverage Status](https://coveralls.io/repos/github/slimphp/Slim-Skeleton/badge.svg?branch=master)](https://coveralls.io/github/slimphp/Slim-Skeleton?branch=master)

Use this skeleton application to quickly setup and start working on a new Slim Framework 4 application. This application uses the latest Slim 4 with Slim PSR-7 implementation and PHP-DI container implementation. It also uses the Monolog logger.

This skeleton application was built for Composer. This makes setting up a new Slim Framework application quick and easy.

## Install the Application

Run this command from the directory in which you want to install your new Slim Framework application. You will require PHP 7.4 or newer.

```bash
composer create-project slim/slim-skeleton [my-app-name]
```

Replace `[my-app-name]` with the desired directory name for your new application. You'll want to:

* Point your virtual host document root to your new application's `public/` directory.
* Ensure `logs/` is web writable.

To run the application in development, you can run these commands 

```bash
cd [my-app-name]
composer start
```

Or you can use `docker-compose` to run the app with `docker`, so you can run these commands:
```bash
cd [my-app-name]
docker-compose up -d
```
After that, open `http://localhost:8080` in your browser.

Run this command in the application directory to run the test suite

```bash
composer test
```

That's it! Now go build something cool.


# Product Manager System

## 概要
Rakuten出品者向けの商品管理システム。Slim Framework + MySQLで構築。

## セットアップ手順（ローカル）
1. `.env.example` をコピーして `.env` を作成
2. `composer install` を実行
3. `php -S localhost:8000 -t public` で起動
4. `http://localhost:8000/login` にアクセス

## 環境設定ファイル（.env）の作成方法

本プロジェクトでは、データベース接続や環境設定を `.env` ファイルで管理しています。  
セキュリティ上の理由から `.env` は Git 管理対象外となっており、代わりに `.env.example` をテンプレートとして提供しています。

### 作成手順

1. プロジェクトルートにある `.env.example` をコピーして `.env` を作成します  
   Windowsの場合：copy .env.example .env


2. `.env` ファイル内の各項目を自身の環境に合わせて編集します：

```env
DB_HOST=mysql80.yamashichi.sakura.ne.jp
DB_NAME=yamashichi_product_manager
DB_USER=your_db_user
DB_PASS=your_db_password


## 本番環境URL
- ログイン画面: https://yamashichi.sakura.ne.jp/product_manager/login
- 商品一覧: https://yamashichi.sakura.ne.jp/product_manager/products

## 管理機能
- サムネイル再生成: `/admin/regenerate-thumbnails`
- ダッシュボード: `/admin/dashboard`

## ディレクトリ構成
- `/public` : Webルート
- `/src` : コントローラー・モデル
- `/templates` : Twigテンプレート
- `/vendor` : Composer依存ライブラリ

## 注意点
- `.env` はGit管理しないこと（`.gitignore` に含める）
- DB接続情報は `.env.example` にテンプレート化

## 環境設定ファイル（.env）の作成方法

本プロジェクトでは、データベース接続や環境設定を `.env` ファイルで管理しています。  
セキュリティ上の理由から `.env` は Git 管理対象外となっており、代わりに `.env.example` をテンプレートとして提供しています。

### 作成手順

1. プロジェクトルートにある `.env.example` をコピーして `.env` を作成します  
   Windowsの場合：copy .env.example .env


2. `.env` ファイル内の各項目を自身の環境に合わせて編集します：

```env
DB_HOST=mysql80.yamashichi.sakura.ne.jp
DB_NAME=yamashichi_product_manager
DB_USER=your_db_user
DB_PASS=your_db_password


3.編集後、アプリケーションを再起動してください

