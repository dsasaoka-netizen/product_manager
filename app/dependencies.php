<?php

declare(strict_types=1);

use App\Controllers\UserController;
use App\Controllers\AdminController;
use App\Controllers\CategoryController;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        // ✅ ロガー定義（Monolog）
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);
            $loggerSettings = $settings->get('logger');

            $logger = new Logger($loggerSettings['name']);
            $logger->pushProcessor(new UidProcessor());
            $logger->pushHandler(new StreamHandler($loggerSettings['path'], $loggerSettings['level']));

            return $logger;
        },

        // ✅ PDO定義（DB接続）
        PDO::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class)->get('db');
            $dsn = "mysql:host={$settings['host']};dbname={$settings['dbname']};charset={$settings['charset']}";
            return new PDO($dsn, $settings['user'], $settings['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        },

        // ✅ Twigテンプレートエンジン定義（basePathとsessionを注入）
        Twig::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);
            $twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);

            // basePath を SettingsInterface 経由で注入
            $twig->getEnvironment()->addGlobal('basePath', $settings->get('basePath'));

            // セッションをグローバル変数として注入
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $twig->getEnvironment()->addGlobal('session', $_SESSION);

            return $twig;
        },

        // ✅ UserController定義（Twig・PDO・Settingsを注入）
        UserController::class => function (ContainerInterface $c) {
            return new UserController(
                $c->get(PDO::class),
                $c->get(Twig::class),
                $c->get(SettingsInterface::class)
            );
        },

        // ✅ AdminController定義（Twig・PDOを注入）
        AdminController::class => function (ContainerInterface $c) {
            return new AdminController(
                $c->get(PDO::class),
                $c->get(Twig::class)
            );
        },

        // ✅ CategoryController定義（Twig・PDOを注入）
        CategoryController::class => function (ContainerInterface $c) {
            return new CategoryController(
                $c->get(PDO::class),
                $c->get(Twig::class)
            );
        },

        // ✅ 他のコントローラーも同様に追加可能（例：ProductController）
        // ProductController::class => function (ContainerInterface $c) {
        //     return new ProductController(
        //         $c->get(PDO::class),
        //         $c->get(Twig::class),
        //         $c->get(SettingsInterface::class)
        //     );
        // },
    ]);
};
