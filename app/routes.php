<?php

declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use App\Controllers\UserController;
use App\Controllers\ProductController;
use App\Controllers\CategoryController;
use App\Controllers\AdminController; // ✅ 管理機能用コントローラー
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {

    // ✅ 最初に GET / を定義（OPTIONSより前に）
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

    // ✅ OPTIONSルートは後ろに移動
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        return $response;
    });

    // 🔐 認証関連
    $app->get('/login', [UserController::class, 'showLoginForm']);
    $app->post('/login', [UserController::class, 'login']);
    $app->get('/logout', function (Request $request, Response $response) {
        session_destroy();
        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    });

    // 👤 ユーザーAPI（Slim Skeleton用）
    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });

    // 🛠️ 管理画面ダッシュボード（入り口）
    $app->get('/admin/dashboard', [AdminController::class, 'showDashboard']);

    // 👥 管理者専用ユーザー管理
    $app->get('/admin/users', [UserController::class, 'showUserList']);
    $app->get('/admin/users/new', [UserController::class, 'showUserForm']);
    $app->post('/admin/users', [UserController::class, 'registerUser']);
    $app->get('/admin/users/{id}/edit', [UserController::class, 'editUser']);
    $app->post('/admin/users/{id}/update', [UserController::class, 'updateUser']);
    $app->post('/admin/users/{id}/delete', [UserController::class, 'deleteUser']);

    // 🖼️ サムネイル再生成
    $app->get('/admin/regenerate-thumbnails', [AdminController::class, 'regenerateThumbnails']);
    $app->post('/admin/regenerate-thumbnails', [AdminController::class, 'regenerateThumbnails']);

    // 📦 商品管理
    $app->get('/products', [ProductController::class, 'showProductList']);
    $app->get('/products/new', [ProductController::class, 'showNewForm']);
    $app->post('/products', [ProductController::class, 'registerProduct']);
    $app->get('/products/{id}/edit', [ProductController::class, 'editProduct']);
    $app->post('/products/{id}/update', [ProductController::class, 'updateProduct']);
    $app->post('/products/{id}/delete', [ProductController::class, 'deleteProduct']);

    // 🗂️ カテゴリ管理
    $app->get('/categories', [CategoryController::class, 'showCategoryList']);
    $app->get('/categories/new', [CategoryController::class, 'showCategoryForm']);
    $app->post('/categories/create', [CategoryController::class, 'createCategory']);
    $app->get('/categories/{id}/edit', [CategoryController::class, 'showCategoryEditForm']);
    $app->post('/categories/{id}/update', [CategoryController::class, 'updateCategory']);
    $app->post('/categories/{id}/delete', [CategoryController::class, 'deleteCategory']);
    $app->post('/categories/sort', [CategoryController::class, 'updateSortOrder']);
};
