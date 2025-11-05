use App\Middleware\AuthMiddleware;

$app->group('/admin', function ($group) {
    $group->get('/dashboard', \App\Controller\AdminController::class . ':dashboard');
    $group->get('/users', \App\Controller\AdminController::class . ':userList');
    // 他の管理画面ルートもここに追加
})->add(new AuthMiddleware());
