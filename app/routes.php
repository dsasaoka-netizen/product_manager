<?php

declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use App\Controllers\UserController;
use App\Controllers\ProductController;
use App\Controllers\CategoryController;
use App\Controllers\AdminController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {

    // 🏠 トップページ（テスト用）
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

    // 🌐 CORS対応（OPTIONSリクエスト）
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    });

    // 🔐 認証関連
    $app->get('/login', [UserController::class, 'showLoginForm']);   // ログイン画面表示
    $app->post('/login', [UserController::class, 'login']);          // ログイン処理
    $app->get('/logout', [UserController::class, 'logout']);         // ログアウト処理

    // 👤 ユーザーAPI（Slim Skeleton用：未使用なら削除可）
    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });

    // 🛠️ 管理ダッシュボード
    $app->get('/admin/dashboard', [AdminController::class, 'showDashboard']);

    // 👥 管理者専用ユーザー管理
    $app->get('/admin/users', [UserController::class, 'showUserList']);              // 一覧
    $app->get('/admin/users/new', [UserController::class, 'showUserForm']);          // 新規登録画面
    $app->post('/admin/users', [UserController::class, 'registerUser']);             // 登録処理
    $app->get('/admin/users/{id}/edit', [UserController::class, 'editUser']);        // 編集画面
    $app->post('/admin/users/{id}/update', [UserController::class, 'updateUser']);   // 更新処理
    $app->post('/admin/users/{id}/delete', [UserController::class, 'deleteUser']);   // 削除処理

    // 🖼️ サムネイル再生成
    $app->map(['GET', 'POST'], '/admin/regenerate-thumbnails', [AdminController::class, 'regenerateThumbnails']);

    // 📦 商品管理
    $app->get('/products', [ProductController::class, 'showProductList']);               // 一覧
    $app->get('/products/new', [ProductController::class, 'showNewForm']);               // 登録画面
    $app->post('/products', [ProductController::class, 'registerProduct']);              // 登録処理
    $app->get('/products/{id}/edit', [ProductController::class, 'editProduct']);         // 編集画面
    $app->post('/products/{id}/update', [ProductController::class, 'updateProduct']);    // 更新処理
    $app->post('/products/{id}/delete', [ProductController::class, 'deleteProduct']);    // 削除処理

    // 🗂️ カテゴリ管理
    $app->get('/categories', [CategoryController::class, 'showCategoryList']);               // 一覧
    $app->get('/categories/new', [CategoryController::class, 'showCategoryForm']);           // 登録画面
    $app->post('/categories/create', [CategoryController::class, 'createCategory']);         // 登録処理
    $app->get('/categories/{id}/edit', [CategoryController::class, 'showCategoryEditForm']); // 編集画面
    $app->post('/categories/{id}/update', [CategoryController::class, 'updateCategory']);    // 更新処理
    $app->post('/categories/{id}/delete', [CategoryController::class, 'deleteCategory']);    // 削除処理
    $app->post('/categories/sort', [CategoryController::class, 'updateSortOrder']);          // 並び順更新

    // 🧪 テスト用ルート
    $app->post('/test-post', function (Request $request, Response $response) {
        $response->getBody()->write('POST received!');
        return $response;
    });
};
