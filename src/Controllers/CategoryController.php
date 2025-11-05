<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class CategoryController {
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
      return $response->withHeader('Location', '/login')->withStatus(302);
    }
    AuthHelper::updateActivity();
    if ($_SESSION['role'] !== 'admin') {
      $response->getBody()->write('管理者のみアクセス可能です');
      return $response->withStatus(403);
    }
    return null;
  }

  // 🆕 カテゴリ登録画面表示
  public function showCategoryForm(Request $request, Response $response): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $categories = $this->getSortedCategoryTree();
    $basePath = RouteContext::fromRequest($request)->getBasePath();

    return $this->view->render($response, 'categories/category_new.twig', [
      'categories' => $categories,
      'basePath' => $basePath,
      'session' => $_SESSION
    ]);
  }

  // 🗂️ カテゴリ一覧表示
  public function showCategoryList(Request $request, Response $response): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $categories = $this->getSortedCategoryTree();
    $basePath = RouteContext::fromRequest($request)->getBasePath();

    return $this->view->render($response, 'categories/category_list.twig', [
      'categories' => $categories,
      'basePath' => $basePath,
      'session' => $_SESSION
    ]);
  }

  // 🆕 カテゴリ登録処理
  public function createCategory(Request $request, Response $response): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $data = $request->getParsedBody();
    $name = trim($data['name'] ?? '');
    $parentId = $data['parent_id'] !== '' ? (int)$data['parent_id'] : null;
    $sortOrder = (int)($data['sort_order'] ?? 0);

    if ($name === '') {
      $categories = $this->getSortedCategoryTree();
      $basePath = RouteContext::fromRequest($request)->getBasePath();

      return $this->view->render($response, 'categories/category_new.twig', [
        'categories' => $categories,
        'basePath' => $basePath,
        'session' => $_SESSION,
        'error' => 'カテゴリ名を入力してください'
      ]);
    }

    $stmt = $this->pdo->prepare("INSERT INTO categories (name, parent_id, sort_order) VALUES (?, ?, ?)");
    $stmt->execute([$name, $parentId, $sortOrder]);

    return $response->withHeader('Location', '/product_manager/categories')->withStatus(302);
  }

  // ✏️ カテゴリ編集画面表示
  public function showCategoryEditForm(Request $request, Response $response, array $args): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $id = (int)$args['id'];

    $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if (!$category) {
      $response->getBody()->write('カテゴリが見つかりません');
      return $response->withStatus(404);
    }

    $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE id != ? ORDER BY sort_order ASC, name ASC");
    $stmt->execute([$id]);
    $flat = $stmt->fetchAll();
    $categories = $this->buildCategoryTree($flat);
    $basePath = RouteContext::fromRequest($request)->getBasePath();

    return $this->view->render($response, 'categories/category_edit.twig', [
      'category' => $category,
      'categories' => $categories,
      'basePath' => $basePath,
      'session' => $_SESSION
    ]);
  }

  // 🔄 カテゴリ更新処理
  public function updateCategory(Request $request, Response $response, array $args): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $id = (int)$args['id'];
    $data = $request->getParsedBody();
    $name = trim($data['name'] ?? '');
    $parentId = $data['parent_id'] !== '' ? (int)$data['parent_id'] : null;
    $sortOrder = (int)($data['sort_order'] ?? 0);

    if ($name === '') {
      $response->getBody()->write('カテゴリ名を入力してください');
      return $response->withStatus(400);
    }

    $stmt = $this->pdo->prepare("UPDATE categories SET name = ?, parent_id = ?, sort_order = ? WHERE id = ?");
    $stmt->execute([$name, $parentId, $sortOrder, $id]);

    return $response->withHeader('Location', '/product_manager/categories')->withStatus(302);
  }

  // 🗑️ カテゴリ削除処理
  public function deleteCategory(Request $request, Response $response, array $args): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $id = (int)$args['id'];

    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
      $response->getBody()->write('子カテゴリが存在するため削除できません');
      return $response->withStatus(400);
    }

    $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);

    return $response->withHeader('Location', '/product_manager/categories')->withStatus(302);
  }

  // ↕️ 並び順更新
  public function updateSortOrder(Request $request, Response $response): Response {
    if ($redirect = $this->checkAdmin($response)) return $redirect;

    $data = json_decode($request->getBody()->getContents(), true);
    foreach ($data as $item) {
      $stmt = $this->pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
      $stmt->execute([(int)$item['sort_order'], (int)$item['id']]);
    }

    return $response->withStatus(200);
  }

  // 🧩 階層構造を構築する再帰関数
  private function buildCategoryTree(array $categories, $parentId = null, $depth = 0): array {
    $tree = [];
    foreach ($categories as $cat) {
      if ($cat['parent_id'] == $parentId) {
        $cat['depth'] = $depth;
        $tree[] = $cat;
        $children = $this->buildCategoryTree($categories, $cat['id'], $depth + 1);
        $tree = array_merge($tree, $children);
      }
    }
    return $tree;
  }

  // 🧩 並び順付きカテゴリツリー取得
  private function getSortedCategoryTree(): array {
    $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
    $flat = $stmt->fetchAll();
    return $this->buildCategoryTree($flat);
  }
}
