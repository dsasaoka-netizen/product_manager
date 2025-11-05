<?php

declare(strict_types=1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {

    // ✅ APP_ENV によるエラーレベル切り替え
    $isProduction = ($_ENV['APP_ENV'] ?? 'local') === 'production';
    $isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () use ($isProduction, $isDebug) {
            return new Settings([
                // ✅ 本番環境ではエラー詳細を非表示
                'displayErrorDetails' => !$isProduction,
                'logError'            => $isDebug,
                'logErrorDetails'     => $isDebug,

                // ✅ ロガー設定
                'logger' => [
                    'name'  => 'product-manager',
                    'path'  => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                    'level' => $isDebug ? Logger::DEBUG : Logger::ERROR,
                ],

                // ✅ DB接続情報
                'db' => [
                    'host'    => $_ENV['DB_HOST'] ?? '127.0.0.1',
                    'dbname'  => $_ENV['DB_NAME'] ?? 'product_manager',
                    'user'    => $_ENV['DB_USER'] ?? 'root',
                    'pass'    => $_ENV['DB_PASS'] ?? '',
                    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                ],

                // ✅ ベースパス（Twigやリダイレクトで使用可能）
                'basePath' => $_ENV['BASE_PATH'] ?? '',
            ]);
        }
    ]);
};
