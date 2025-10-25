<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CategoryController {
  protected \PDO $pdo;

  public function __construct(\PDO $pdo) {
    $this->pdo = $pdo;
  }

  // 🆕 カテゴリ登録画面表示（階層付きプルダウン対応）
  public function showCategoryForm(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $categories = $this->getSortedCategoryTree();

    ob_start();
    include dirname(__DIR__, 2) . '/templates/categories/new.php';
    $html = ob_get_clean();

    $response->getBody()->write($html);
    return $response;
  }

  // 🗂️ カテゴリ一覧表示（階層構造＋並び順）
  public function showCategoryList(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $categories = $this->getSortedCategoryTree();

    ob_start();
    include dirname(__DIR__, 2) . '/templates/categories/list.php';
    $html = ob_get_clean();

    $response->getBody()->write($html);
    return $response;
  }

  // 🆕 カテゴリ登録処理
  public function createCategory(Request $request, Response $response): Response {


    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }


    // if ($_SESSION['role'] ?? null !== 'admin') {
    if ($_SESSION['role'] !== 'admin') {
      $response->getBody()->write('カテゴリ登録は管理者のみ可能です');
      return $response->withStatus(403);
    }

    $data = $request->getParsedBody();
    $name = trim($data['name'] ?? '');
    $parentId = $data['parent_id'] !== '' ? (int)$data['parent_id'] : null;
    $sortOrder = (int)($data['sort_order'] ?? 0);

    if ($name === '') {
      $error = 'カテゴリ名を入力してください';
      $categories = $this->getSortedCategoryTree();

      ob_start();
      include dirname(__DIR__, 2) . '/templates/categories/new.php';
      $html = ob_get_clean();

      $response->getBody()->write($html);
      return $response;
    }

    $stmt = $this->pdo->prepare("INSERT INTO categories (name, parent_id, sort_order) VALUES (?, ?, ?)");
    $stmt->execute([$name, $parentId, $sortOrder]);

    return $response->withHeader('Location', '/product_manager/categories')->withStatus(302);
  }

  // ✏️ カテゴリ編集画面表示（階層付きプルダウン対応）
  public function showCategoryEditForm(Request $request, Response $response, array $args): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
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

    ob_start();
    include dirname(__DIR__, 2) . '/templates/categories/edit.php';
    $html = ob_get_clean();

    $response->getBody()->write($html);
    return $response;
  }

  // 🔄 カテゴリ更新処理
  public function updateCategory(Request $request, Response $response, array $args): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
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

  // 🗑️ カテゴリ削除処理（子カテゴリがある場合は拒否）
  public function deleteCategory(Request $request, Response $response, array $args): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
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

  // ↕️ 並び順更新（ドラッグ＆ドロップ対応）
  public function updateSortOrder(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    if ($_SESSION['role'] !== 'admin') {
      $response->getBody()->write('権限がありません');
      return $response->withStatus(403);
    }

    $data = json_decode($request->getBody()->getContents(), true);
    foreach ($data as $item) {
      $stmt = $this->pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
      $stmt->execute([(int)$item['sort_order'], (int)$item['id']]);
    }

    return $response->withStatus(200);
  }

  // 🧩 階層構造を構築する再帰関数（親→子→孫）
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

  // 🧩 並び順付きカテゴリツリー取得（一覧・登録画面用）
  private function getSortedCategoryTree(): array {
    $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
    $flat = $stmt->fetchAll();
    return $this->buildCategoryTree($flat);
  }
}
