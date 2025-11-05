<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Routing\RouteContext;

class UserController {
  protected \PDO $pdo;
  protected Twig $view;

  public function __construct(\PDO $pdo, Twig $view) {
    $this->pdo = $pdo;
    $this->view = $view;
  }

  // 🔐 共通セッションチェック（管理者専用）
  private function checkAdmin(Response $response): ?Response {
    if (!AuthHelper::requireLogin() || !AuthHelper::checkTimeout()) {
      AuthHelper::forceLogout();

      // ✅ 元のURLを保存
      $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
      $_SESSION['login_message'] = 'セッションが切れました。再度ログインしてください。';

      return $response->withHeader('Location', '/product_manager/login')->withStatus(302);
    }

    AuthHelper::updateActivity();

    if ($_SESSION['role'] !== 'admin') {
      return $response->withStatus(403)->write('管理者のみアクセス可能です');
    }

    return null;
  }

  // 🔐 ログイン画面表示
  public function showLoginForm(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) session_start();

    // ✅ セッションメッセージを取得して破棄
    $message = $_SESSION['login_message'] ?? '';
    unset($_SESSION['login_message']);

    $error = $request->getQueryParams()['error'] ?? '';
    $username = $request->getQueryParams()['username'] ?? '';

    return $this->view->render($response, 'users/login.twig', [
      'error' => $error,
      'message' => $message,
      'username' => $username
    ]);
  }

  // 🔐 ログイン処理
  public function login(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $data = $request->getParsedBody();
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['role'] = $user['role'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['last_activity'] = time();

      $basePath = RouteContext::fromRequest($request)->getBasePath();

      // ✅ 元のURLに戻る（なければ /products）
      $redirectTo = $_SESSION['redirect_after_login'] ?? $basePath . '/products';
      unset($_SESSION['redirect_after_login']);

      return $response->withHeader('Location', $redirectTo)->withStatus(302);
    }

    $basePath = RouteContext::fromRequest($request)->getBasePath();
    return $response->withHeader('Location', $basePath . '/login?error=ログイン情報が正しくありません&username=' . urlencode($username))->withStatus(302);
  }

  // 🔓 ログアウト処理
  public function logout(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $_SESSION = [];
    session_destroy();

    // ✅ ログアウトメッセージをセッションに保存
    session_start(); // 再度開始してメッセージ保存
    $_SESSION['login_message'] = 'ログアウトしました';

    $basePath = RouteContext::fromRequest($request)->getBasePath();
    return $response->withHeader('Location', $basePath . '/login')->withStatus(302);
  }

  // 👥 ユーザー一覧表示（管理者用）
  public function showUserList(Request $request, Response $response): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $stmt = $this->pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();

    return $this->view->render($response, 'users/user_list.twig', ['users' => $users]);
  }

  // 👤 ユーザー登録画面表示
  public function showUserForm(Request $request, Response $response): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    return $this->view->render($response, 'users/user_new.twig');
  }

  // 👤 ユーザー登録処理
  public function registerUser(Request $request, Response $response): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $data = $request->getParsedBody();
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = password_hash(trim($data['password'] ?? ''), PASSWORD_DEFAULT);
    $role = $data['role'] ?? 'user';

    $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $password, $role]);

    $basePath = RouteContext::fromRequest($request)->getBasePath();
    return $response->withHeader('Location', $basePath . '/admin/users')->withStatus(302);
  }

  // 👤 ユーザー編集画面表示
  public function editUser(Request $request, Response $response, array $args): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $id = (int)$args['id'];
    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    return $this->view->render($response, 'users/user_edit.twig', ['user' => $user]);
  }

  // 👤 ユーザー更新処理
  public function updateUser(Request $request, Response $response, array $args): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $id = (int)$args['id'];
    $data = $request->getParsedBody();
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $role = $data['role'] ?? 'user';

    $stmt = $this->pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
    $stmt->execute([$username, $email, $role, $id]);

    $basePath = RouteContext::fromRequest($request)->getBasePath();
    return $response->withHeader('Location', $basePath . '/admin/users')->withStatus(302);
  }

  // 👤 ユーザー削除処理
  public function deleteUser(Request $request, Response $response, array $args): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $id = (int)$args['id'];
    $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    $basePath = RouteContext::fromRequest($request)->getBasePath();
    return $response->withHeader('Location', $basePath . '/admin/users')->withStatus(302);
  }
}
