<?php

declare(strict_types=1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;
use Dotenv\Dotenv;

return function (ContainerBuilder $containerBuilder) {

    // ✅ .env を読み込む（初回のみ）
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
    }

    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
            return new Settings([
                'displayErrorDetails' => true, // Should be set to false in production
                'logError'            => false,
                'logErrorDetails'     => false,
                'logger' => [
                    'name' => 'slim-app',
                    'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                    'level' => Logger::DEBUG,
                ],

                // ✅ DB接続情報を .env から取得
                'db' => [
                    'host'    => $_ENV['DB_HOST'] ?? '127.0.0.1',
                    'dbname'  => $_ENV['DB_NAME'] ?? 'product_manager',
                    'user'    => $_ENV['DB_USER'] ?? 'root',
                    'pass'    => $_ENV['DB_PASS'] ?? '',
                    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                ],
            ]);
        }
    ]);
};
