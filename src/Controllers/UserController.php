<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController {
  protected \PDO $pdo;

  public function __construct(\PDO $pdo) {
    $this->pdo = $pdo;
  }

  // 🔐 ログイン画面表示
  public function showLoginForm(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    ob_start();
    include dirname(__DIR__, 2) . '/templates/users/login.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response;
  }

  // 🔐 ログイン処理
  public function login(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    $data = $request->getParsedBody();
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['role'] = $user['role'];
      return $response->withHeader('Location', '/product_manager/products')->withStatus(302);
    }

    $error = 'ログイン情報が正しくありません';
    ob_start();
    include dirname(__DIR__, 2) . '/templates/users/login.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response;
  }

  // 👥 ユーザー一覧表示（管理者用）
  public function showUserList(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $stmt = $this->pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();

    ob_start();
    include dirname(__DIR__, 2) . '/templates/users/list.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response;
  }

  // 👤 ユーザー登録画面表示
  public function showUserForm(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    ob_start();
    include dirname(__DIR__, 2) . '/templates/users/new.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response;
  }

  // 👤 ユーザー登録処理
  public function registerUser(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $data = $request->getParsedBody();
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = password_hash(trim($data['password'] ?? ''), PASSWORD_DEFAULT);
    $role = $data['role'] ?? 'user';

    $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $password, $role]);

    return $response->withHeader('Location', '/product_manager/admin/users')->withStatus(302);
  }

  // 👤 ユーザー編集画面表示
  public function editUser(Request $request, Response $response, array $args): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $id = (int)$args['id'];

    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    ob_start();
    include dirname(__DIR__, 2) . '/templates/users/edit.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response;
  }

  // 👤 ユーザー更新処理
  public function updateUser(Request $request, Response $response, array $args): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $id = (int)$args['id'];
    $data = $request->getParsedBody();
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $role = $data['role'] ?? 'user';

    $stmt = $this->pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
    $stmt->execute([$username, $email, $role, $id]);

    return $response->withHeader('Location', '/product_manager/admin/users')->withStatus(302);
  }

  // 👤 ユーザー削除処理
  public function deleteUser(Request $request, Response $response, array $args): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $id = (int)$args['id'];

    $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    return $response->withHeader('Location', '/product_manager/admin/users')->withStatus(302);
  }
}
