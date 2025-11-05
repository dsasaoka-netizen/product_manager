<?php
declare(strict_types=1);

// ✅ エラー表示（開発用）
ini_set('display_errors', '1');
error_reporting(E_ALL);

// ✅ セッション開始（30分保持）
session_start([
    'cookie_lifetime' => 0,        // ブラウザを閉じるとセッション終了
    'gc_maxlifetime' => 1800       // サーバー側で30分保持（1800秒）
]);

use Dotenv\Dotenv;
use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\ShutdownHandler;
use App\Application\ResponseEmitter\ResponseEmitter;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

require __DIR__ . '/../vendor/autoload.php';

// ✅ .env 読み込み
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// ✅ コンテナ構築
$containerBuilder = new ContainerBuilder();

if ($_ENV['APP_ENV'] === 'production') {
    $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

// ✅ 設定・依存登録
(require __DIR__ . '/../app/settings.php')($containerBuilder);
(require __DIR__ . '/../app/dependencies.php')($containerBuilder);
(require __DIR__ . '/../app/repositories.php')($containerBuilder);

$container = $containerBuilder->build();

// ✅ Slim アプリ生成
AppFactory::setContainer($container);
$app = AppFactory::create();

// ✅ ベースパス設定（例：/product_manager）
$app->setBasePath($_ENV['BASE_PATH'] ?? '');

$callableResolver = $app->getCallableResolver();

// ✅ ミドルウェア・ルート登録
(require __DIR__ . '/../app/middleware.php')($app);
(require __DIR__ . '/../app/routes.php')($app);

/** @var SettingsInterface $settings */
$settings = $container->get(SettingsInterface::class);

$displayErrorDetails = $settings->get('displayErrorDetails');
$logError = $settings->get('logError');
$logErrorDetails = $settings->get('logErrorDetails');

// ✅ リクエスト生成
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// ✅ エラーハンドラ設定
$responseFactory = $app->getResponseFactory();
$errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);
$shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
register_shutdown_function($shutdownHandler);

// ✅ ミドルウェア追加
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// ✅ 実行
$response = $app->handle($request);
$responseEmitter = new ResponseEmitter();
$responseEmitter->emit($response);
