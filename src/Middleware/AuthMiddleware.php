<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        if (!isset($_SESSION['user_id'])) {
            // セッションが切れていたらログイン画面へリダイレクト
            $response = new SlimResponse();
            $_SESSION['login_message'] = 'セッションが切れました。再度ログインしてください。';
            return $response->withHeader('Location', '/product_manager/login')->withStatus(302);
        }

        return $handler->handle($request);
    }
}
