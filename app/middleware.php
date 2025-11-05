<?php

declare(strict_types=1);

use App\Application\Middleware\SessionMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app): void {
    // ✅ TwigMiddleware（Twigの環境をSlimに統合）
    $app->add(TwigMiddleware::createFromContainer($app, Twig::class));

    // ✅ セッションミドルウェア（最初に追加）
    $app->add(SessionMiddleware::class);

    // ✅ ルーティングミドルウェア（必須）
    $app->addRoutingMiddleware();

    // ✅ Body解析ミドルウェア（POSTデータを扱うために必要）
    $app->addBodyParsingMiddleware();

    // ✅ CORSミドルウェア（OPTIONSやfetch対応）
    $app->add(function (Request $request, RequestHandlerInterface $handler): Response {
        $response = $handler->handle($request);
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    });
};
