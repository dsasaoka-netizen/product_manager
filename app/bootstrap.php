<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// コンテナ構築
$containerBuilder = new ContainerBuilder();

// 設定の読み込み
$settings = require __DIR__ . '/settings.php';
$settings($containerBuilder);

// 依存性の定義
$dependencies = require __DIR__ . '/dependencies.php';
$dependencies($containerBuilder);

// コンテナをアプリにセット
$container = $containerBuilder->build();
AppFactory::setContainer($container);
$app = AppFactory::create();

// エラーミドルウェア（開発中は true）
$app->addErrorMiddleware(true, true, true);

// ルートの登録
$routes = require __DIR__ . '/routes.php';
$routes($app);

return $app;
