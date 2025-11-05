<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Helpers\AuthHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class ProductController {
  protected \PDO $pdo;
  protected Twig $view;

  public function __construct(\PDO $pdo, Twig $view) {
    $this->pdo = $pdo;
    $this->view = $view;
  }

  // 🔐 共通セッションチェック
  private function checkSession(Response $response): ?Response {
    if (!AuthHelper::requireLogin() || !AuthHelper::checkTimeout()) {
      AuthHelper::forceLogout();
      return $response->withHeader('Location', '/login')->withStatus(302);
    }
    AuthHelper::updateActivity();
    return null;
  }

  // 📝 商品登録画面表示
  public function showNewForm(Request $request, Response $response): Response {
    if ($redirect = $this->checkSession($response)) return $redirect;

    $categoryModel = new CategoryModel($this->pdo);
    $categories = $categoryModel->getSortedTree();
    $product = [];

    $basePath = RouteContext::fromRequest($request)->getBasePath();

    return $this->view->render($response, 'products/new.twig', [
      'product' => $product,
      'categories' => $categories,
      'basePath' => $basePath,
      'error' => '',
    ]);
  }

  // 📦 商品一覧表示
  public function showProductList(Request $request, Response $response): Response {
    if ($redirect = $this->checkSession($response)) return $redirect;

    $queryParams = $request->getQueryParams();
    $keyword = trim($queryParams['keyword'] ?? '');
    $sort = $queryParams['sort'] ?? ($_COOKIE['sort'] ?? '');
    $perPageRaw = $queryParams['per_page'] ?? ($_COOKIE['per_page'] ?? null);
    $perPage = in_array((int)$perPageRaw, [10, 30, 50, 100]) ? (int)$perPageRaw : 10;
    $currentPage = max(1, (int)($queryParams['page'] ?? 1));
    $offset = ($currentPage - 1) * $perPage;

    $where = '';
    $params = [];
    if ($keyword !== '') {
      $where = "WHERE p.name LIKE :kw OR p.code LIKE :kw OR c.name LIKE :kw";
      $params[':kw'] = "%$keyword%";
    }

    switch ($sort) {
      case 'code_asc':      $orderBy = 'ORDER BY p.code ASC'; break;
      case 'code_desc':     $orderBy = 'ORDER BY p.code DESC'; break;
      case 'category_asc':  $orderBy = 'ORDER BY c.name ASC'; break;
      case 'category_desc': $orderBy = 'ORDER BY c.name DESC'; break;
      case 'name_asc':      $orderBy = 'ORDER BY p.name ASC'; break;
      case 'name_desc':     $orderBy = 'ORDER BY p.name DESC'; break;
      case 'price_asc':     $orderBy = 'ORDER BY p.price ASC'; break;
      case 'price_desc':    $orderBy = 'ORDER BY p.price DESC'; break;
      case 'cost_asc':      $orderBy = 'ORDER BY p.cost_price ASC'; break;
      case 'cost_desc':     $orderBy = 'ORDER BY p.cost_price DESC'; break;
      case 'stock_asc':     $orderBy = 'ORDER BY p.stock ASC'; break;
      case 'stock_desc':    $orderBy = 'ORDER BY p.stock DESC'; break;
      case 'new':
      default:              $orderBy = 'ORDER BY p.created_at DESC'; break;
    }

    $countStmt = $this->pdo->prepare("
      SELECT COUNT(*) FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      $where
    ");
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();
    $totalPages = ($perPage > 0) ? max(1, ceil($totalCount / $perPage)) : 1;

    $stmt = $this->pdo->prepare("
      SELECT p.*, c.name AS category_name,
        (SELECT thumbnail FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC LIMIT 1) AS thumbnail
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      $where $orderBy
      LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    $imageDir = dirname(__DIR__, 2) . '/public/images/products/';
    $imageFiles = [];
    foreach ($products as $p) {
      if (!empty($p['thumbnail'])) {
        $imageFiles[$p['thumbnail']] = file_exists($imageDir . $p['thumbnail']);
      }
    }

    $routeContext = RouteContext::fromRequest($request);
    $basePath = $routeContext->getBasePath();

    return $this->view->render($response, 'products/list.twig', [
      'products' => $products,
      'basePath' => $basePath,
      'totalCount' => $totalCount,
      'totalPages' => $totalPages,
      'currentPage' => $currentPage,
      'perPage' => $perPage,
      'selectedSort' => $sort,
      'selectedPerPage' => $perPage,
      'keyword' => $keyword,
      'sortOptions' => [
        'new' => '新着順',
        'code_asc' => '商品コード 昇順',
        'code_desc' => '商品コード 降順',
        'category_asc' => 'カテゴリ 昇順',
        'category_desc' => 'カテゴリ 降順',
        'name_asc' => '商品名 昇順',
        'name_desc' => '商品名 降順',
        'price_asc' => '価格 昇順',
        'price_desc' => '価格 降順',
        'cost_asc' => '仕入価格 昇順',
        'cost_desc' => '仕入価格 降順',
        'stock_asc' => '在庫 昇順',
        'stock_desc' => '在庫 降順',
      ],
      'session' => $_SESSION,
      'queryString' => $queryParams,
      'imageFiles' => $imageFiles,
    ]);
  }

  // ✏️ 商品編集画面表示（edit.twig を使用）
  public function editProduct(Request $request, Response $response, array $args): Response {
    if ($redirect = $this->checkSession($response)) return $redirect;

    $id = (int)$args['id'];
    $userId = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? null;

    $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
      $response->getBody()->write('商品が見つかりません');
      return $response->withStatus(404);
    }

    $stmt = $this->pdo->prepare("SELECT can_manage_all_products FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $canManageAll = $user['can_manage_all_products'] ?? false;

    if ($role !== 'admin' && !$canManageAll && $product['user_id'] !== $userId) {
      $response->getBody()->write('この商品を編集する権限がありません');
      return $response->withStatus(403);
    }

    $categoryModel = new CategoryModel($this->pdo);
    $categories = $categoryModel->getSortedTree();

    $stmt = $this->pdo->prepare("SELECT id, file_name FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$id]);
    $productImages = $stmt->fetchAll();

    $imageDir = dirname(__DIR__, 2) . '/public/images/products/';
    $imageFiles = [];
    foreach ($productImages as $img) {
      $thumb = 'thumb_' . $img['file_name'];
      $imageFiles[$thumb] = file_exists($imageDir . $thumb);
    }

    $routeContext = RouteContext::fromRequest($request);
    $basePath = $routeContext->getBasePath();

    return $this->view->render($response, 'products/edit.twig', [
      'product' => $product,
      'categories' => $categories,
      'productImages' => $productImages,
      'imageFiles' => $imageFiles,
      'basePath' => $basePath,
      'session' => $_SESSION,              // ログイン情報（Twigで権限判定に使用）
    ]);
  }

  // 📦 商品登録処理（画像保存＋バリデーション＋Twig対応）
  public function registerProduct(Request $request, Response $response): Response {
    if ($redirect = $this->checkSession($response)) return $redirect;

    $data = $request->getParsedBody();
    $code = trim($data['code'] ?? '');
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $price = (int)($data['price'] ?? 0);
    $costPrice = (int)($data['cost_price'] ?? 0);
    $stock = (int)($data['stock'] ?? 0);
    $categoryId = (int)($data['category_id'] ?? 0);
    $userId = $_SESSION['user_id'] ?? null;

    $uploadedImages = $_FILES['new_images'] ?? [];
    $imageCount = is_array($uploadedImages['name']) ? count($uploadedImages['name']) : 0;

    $error = '';
    if ($code === '' || !preg_match('/^[A-Za-z0-9]+$/', $code)) {
      $error = '商品コードは半角英数字で入力してください';
    } elseif ($name === '' || $price <= 0 || $stock < 0 || $categoryId <= 0 || !$userId) {
      $error = '入力内容に誤りがあります';
    } elseif ($imageCount > 10) {
      $error = '画像は最大10枚まで登録できます';
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

      $routeContext = RouteContext::fromRequest($request);
      $basePath = $routeContext->getBasePath();

      return $this->view->render($response, 'products/new.twig', [
        'product' => $product,
        'categories' => $categories,
        'basePath' => $basePath,
        'error' => $error,
      ]);
    }

    // 商品登録処理
    $stmt = $this->pdo->prepare("
      INSERT INTO products (code, name, price, cost_price, stock, description, category_id, user_id)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$code, $name, $price, $costPrice, $stock, $description, $categoryId, $userId]);
    $productId = $this->pdo->lastInsertId();

    // 画像保存処理
    $imageDir = dirname(__DIR__, 2) . '/public/images/products/';
    $imagePaths = [];

    foreach ($uploadedImages['tmp_name'] as $i => $tmpPath) {
      if ($tmpPath === '') continue;

      $originalName = basename($uploadedImages['name'][$i]);
      $ext = pathinfo($originalName, PATHINFO_EXTENSION);
      $newName = $code . '_' . time() . '_' . $i . '.' . $ext;
      $fullPath = $imageDir . $newName;

      if (move_uploaded_file($tmpPath, $fullPath)) {
        $thumbName = 'thumb_' . $newName;
        $thumbPath = $imageDir . $thumbName;
        $thumbCreated = $this->createThumbnail($fullPath, $thumbPath);
        $imagePaths[] = ['file' => $newName, 'thumb' => $thumbCreated ? $thumbName : null];
      }
    }

    foreach ($imagePaths as $i => $img) {
      $stmt = $this->pdo->prepare("
        INSERT INTO product_images (product_id, file_name, thumbnail, sort_order)
        VALUES (?, ?, ?, ?)
      ");
      $stmt->execute([$productId, $img['file'], $img['thumb'], $i + 1]);
    }

    return $response->withHeader('Location', '/product_manager/products')->withStatus(302);
  }

  // 📦 商品削除処理
  public function deleteProduct(Request $request, Response $response, array $args): Response {
    if ($redirect = $this->checkSession($response)) return $redirect;

    $id = (int)$args['id'];
    $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);

    return $response->withHeader('Location', '/product_manager/products')->withStatus(302);
  }

  // 📦 商品更新処理（画像削除・追加・並び順変更対応）
  public function updateProduct(Request $request, Response $response, array $args): Response {
    if ($redirect = $this->checkSession($response)) return $redirect;

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

    // 画像削除処理
    $deleteIds = $data['delete_images'] ?? [];
    if (is_array($deleteIds)) {
      $stmt = $this->pdo->prepare("SELECT file_name, thumbnail FROM product_images WHERE id = ? AND product_id = ?");
      $delStmt = $this->pdo->prepare("DELETE FROM product_images WHERE id = ?");
      foreach ($deleteIds as $delId) {
        $stmt->execute([$delId, $id]);
        $row = $stmt->fetch();
        if ($row) {
          foreach (['file_name', 'thumbnail'] as $file) {
            $path = dirname(__DIR__, 2) . '/public/images/products/' . $row[$file];
            if (file_exists($path)) unlink($path);
          }
          $delStmt->execute([$delId]);
        }
      }
    }

    // 並び順更新処理
    $sortedIds = explode(',', $data['sorted_image_ids'] ?? '');
    $stmt = $this->pdo->prepare("UPDATE product_images SET sort_order = ? WHERE id = ?");
    foreach ($sortedIds as $i => $imgId) {
      if ($imgId !== '') {
        $stmt->execute([$i + 1, $imgId]);
      }
    }

    // 画像追加処理
    $newImages = $_FILES['new_images'] ?? [];
    $imageDir = dirname(__DIR__, 2) . '/public/images/products/';
    $stmt = $this->pdo->prepare("INSERT INTO product_images (product_id, file_name, thumbnail, sort_order) VALUES (?, ?, ?, ?)");

    $stmtMax = $this->pdo->prepare("SELECT MAX(sort_order) FROM product_images WHERE product_id = ?");
    $stmtMax->execute([$id]);
    $sortOrder = (int)$stmtMax->fetchColumn();

    foreach ($newImages['tmp_name'] as $i => $tmpPath) {
      if ($tmpPath === '') continue;

      $originalName = basename($newImages['name'][$i]);
      $ext = pathinfo($originalName, PATHINFO_EXTENSION);
      $newName = $code . '_' . time() . '_' . $i . '.' . $ext;
      $fullPath = $imageDir . $newName;

      if (move_uploaded_file($tmpPath, $fullPath)) {
        $thumbName = 'thumb_' . $newName;
        $thumbPath = $imageDir . $thumbName;
        $thumbCreated = $this->createThumbnail($fullPath, $thumbPath);
        $stmt->execute([$id, $newName, $thumbCreated ? $thumbName : null, ++$sortOrder]);
      }
    }

    return $response->withHeader('Location', '/product_manager/products')->withStatus(302);
  }

  // 🖼️ 共通サムネイル生成関数（GDライブラリ使用）
  private function createThumbnail(string $sourcePath, string $destPath, int $thumbWidth = 400): bool {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;

    [$width, $height] = $imageInfo;
    $mime = $imageInfo['mime'];

    switch ($mime) {
      case 'image/jpeg': $srcImage = imagecreatefromjpeg($sourcePath); break;
      case 'image/png':  $srcImage = imagecreatefrompng($sourcePath); break;
      case 'image/gif':  $srcImage = imagecreatefromgif($sourcePath); break;
      default: return false;
    }

    $thumbHeight = intval($height * $thumbWidth / $width);
    $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
    imagecopyresampled($thumbImage, $srcImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

    switch ($mime) {
      case 'image/jpeg': imagejpeg($thumbImage, $destPath); break;
      case 'image/png':  imagepng($thumbImage, $destPath); break;
      case 'image/gif':  imagegif($thumbImage, $destPath); break;
    }

    imagedestroy($srcImage);
    imagedestroy($thumbImage);
    return true;
  }
}
