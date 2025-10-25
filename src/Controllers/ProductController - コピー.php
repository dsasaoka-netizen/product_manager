<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductController {
  protected \PDO $pdo;

  public function __construct(\PDO $pdo) {
    $this->pdo = $pdo;
  }

  // 📦 商品一覧表示
  public function showProductList(Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $userId = $_SESSION['user_id'] ?? null;

    if (!isset($_SESSION['can_manage_all_products'])) {
      $stmt = $this->pdo->prepare("SELECT can_manage_all_products FROM users WHERE id = ?");
      $stmt->execute([$userId]);
      $_SESSION['can_manage_all_products'] = $stmt->fetchColumn();
    }

    $stmt = $this->pdo->query("
      SELECT p.*, c.name AS category_name
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      ORDER BY p.created_at DESC
    ");
    $products = $stmt->fetchAll();

    ob_start();
    include dirname(__DIR__, 2) . '/templates/products/list.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response;
  }

  // 📦 商品登録画面表示
  // public function showProductForm(Request $request, Response $response): Response {
  //   if (session_status() === PHP_SESSION_NONE) {
  //     session_start();
  //   }
  //   $stmt = $this->pdo->query("SELECT id, name, parent_id FROM categories ORDER BY sort_order ASC");
  //   $categories = $stmt->fetchAll();
// 
  //   ob_start();
  //   include dirname(__DIR__, 2) . '/templates/products/new.php';
  //   $html = ob_get_clean();
  //   $response->getBody()->write($html);
  //   return $response;
  // }

// 📦 商品登録処理（画像保存付き）
public function registerProduct(Request $request, Response $response): Response {
  if (session_status() === PHP_SESSION_NONE) session_start();

  $data = $request->getParsedBody();
  $code = trim($data['code'] ?? '');
  $name = trim($data['name'] ?? '');
  $description = trim($data['description'] ?? '');
  $price = (int)($data['price'] ?? 0);
  $costPrice = (int)($data['cost_price'] ?? 0);
  $stock = (int)($data['stock'] ?? 0);
  $categoryId = (int)($data['category_id'] ?? 0);
  $userId = $_SESSION['user_id'] ?? null;

  $error = '';
  if ($code === '' || !preg_match('/^[A-Za-z0-9]+$/', $code)) {
    $error = '商品コードは半角英数字で入力してください';
  } elseif ($name === '' || $price <= 0 || $stock < 0 || $categoryId <= 0 || !$userId) {
    $error = '入力内容に誤りがあります';
  } else {
    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE code = ?");
    $stmt->execute([$code]);
    if ($stmt->fetchColumn() > 0) {
      $error = 'この商品コードはすでに登録されています';
    }
  }

  if ($error !== '') {
    $categoryModel = new CategoryModel($this->pdo);
    $categories = $categoryModel->getSortedTree();
    $product = $data;

    ob_start();
    include dirname(__DIR__, 2) . '/templates/products/new.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response;
  }

  // 商品登録
  $stmt = $this->pdo->prepare("
    INSERT INTO products (code, name, price, cost_price, stock, description, category_id, user_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([$code, $name, $price, $costPrice, $stock, $description, $categoryId, $userId]);

  $productId = $this->pdo->lastInsertId();

  // 画像保存処理
  $uploadedImages = $_FILES['images'] ?? [];
  $imageDir = dirname(__DIR__, 2) . '/public/images/products/';
  $imagePaths = [];

  foreach ($uploadedImages['tmp_name'] as $i => $tmpPath) {
    if ($tmpPath === '') continue;

    $originalName = basename($uploadedImages['name'][$i]);
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $newName = $code . '_' . time() . '_' . $i . '.' . $ext;
    $fullPath = $imageDir . $newName;

    if (move_uploaded_file($tmpPath, $fullPath)) {
      $imagePaths[] = $newName;
    } else {
      error_log("画像保存失敗: $fullPath");
    }
  }

  foreach ($imagePaths as $i => $fileName) {
    $stmt = $this->pdo->prepare("
      INSERT INTO product_images (product_id, file_name, sort_order)
      VALUES (?, ?, ?)
    ");
    $stmt->execute([$productId, $fileName, $i + 1]);
  }

  return $response->withHeader('Location', '/product_manager/products')->withStatus(302);
}


  // 📦 商品編集画面表示
  public function editProduct(Request $request, Response $response, array $args): Response {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $id = (int)$args['id'];
    $userId = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? null;

    $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
      return $response->withStatus(404)->write('商品が見つかりません');
    }

    $stmt = $this->pdo->prepare("SELECT can_manage_all_products FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $canManageAll = $user['can_manage_all_products'] ?? false;

    if ($role !== 'admin' && !$canManageAll && $product['user_id'] !== $userId) {
      return $response->withStatus(403)->write('この商品を編集する権限がありません');
    }

    $categoryModel = new CategoryModel($this->pdo);
    $categories = $categoryModel->getSortedTree();

    ob_start();
    include dirname(__DIR__, 2) . '/templates/products/edit.php';
    $html = ob_get_clean();
    $response->getBody()->write($html);
    return $response;
  }


  // 📦 商品更新処理
  public function updateProduct(Request $request, Response $response, array $args): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $id = (int)$args['id'];
    $data = $request->getParsedBody();
    $code = trim($data['code'] ?? '');
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $price = (int)($data['price'] ?? 0);
    $costPrice = (int)($data['cost_price'] ?? 0);
    $stock = (int)($data['stock'] ?? 0);
    $categoryId = (int)($data['category_id'] ?? 0);

    if ($code === '' || !preg_match('/^[A-Za-z0-9]+$/', $code) || $name === '' || $price <= 0 || $stock < 0 || $categoryId <= 0) {
      return $response->withStatus(400)->write('入力内容に誤りがあります');
    }

    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE code = ? AND id != ?");
    $stmt->execute([$code, $id]);
    if ($stmt->fetchColumn() > 0) {
      return $response->withStatus(400)->write('この商品コードはすでに他の商品に使われています');
    }

    $stmt = $this->pdo->prepare("
      UPDATE products SET code = ?, name = ?, price = ?, cost_price = ?, stock = ?, description = ?, category_id = ?
      WHERE id = ?
    ");
    $stmt->execute([$code, $name, $price, $costPrice, $stock, $description, $categoryId, $id]);

    return $response->withHeader('Location', '/product_manager/products')->withStatus(302);
  }

  // 📦 商品削除処理
  public function deleteProduct(Request $request, Response $response, array $args): Response {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $id = (int)$args['id'];

    $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);

    return $response->withHeader('Location', '/product_manager/products')->withStatus(302);
  }
  
  // 📦 商品登録画面表示
  public function showNewForm(Request $request, Response $response): Response {
  if (session_status() === PHP_SESSION_NONE) session_start();

  $categoryModel = new CategoryModel($this->pdo);
  $categories = $categoryModel->getSortedTree();

  $product = []; // 新規登録なので空

  ob_start();
  include dirname(__DIR__, 2) . '/templates/products/new.php';
  $html = ob_get_clean();

  $response->getBody()->write($html);
  return $response;
}

}
