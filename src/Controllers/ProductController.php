<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


class ProductController {
  protected \PDO $pdo;

  public function __construct(\PDO $pdo) {
    $this->pdo = $pdo;
  }

// 📦 商品一覧表示（サムネイル画像付き）
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

  // 🔍 クエリパラメータ取得
  $queryParams = $request->getQueryParams();
  $keyword = trim($queryParams['keyword'] ?? '');

  // ✅ クエリがあればクッキーに保存（30日間）
  if (isset($queryParams['sort'])) {
    setcookie('sort', $queryParams['sort'], time() + 60 * 60 * 24 * 30, '/');
  }
  if (isset($queryParams['per_page'])) {
    setcookie('per_page', $queryParams['per_page'], time() + 60 * 60 * 24 * 30, '/');
  }

  // ✅ クエリ or クッキーから読み込み
  $sort = $queryParams['sort'] ?? ($_COOKIE['sort'] ?? '');
  $perPageRaw = $queryParams['per_page'] ?? ($_COOKIE['per_page'] ?? null);
  $perPage = in_array((int)$perPageRaw, [10, 30, 50, 100]) ? (int)$perPageRaw : 10;
  error_log("perPage: $perPage");

  $currentPage = max(1, (int)($queryParams['page'] ?? 1));
  $offset = ($currentPage - 1) * $perPage;

  // 🔍 WHERE句の構築
  $where = '';
  $params = [];

  if ($keyword !== '') {
    $where = "WHERE p.name LIKE :kw OR p.code LIKE :kw OR c.name LIKE :kw";
    $params[':kw'] = "%$keyword%";
  }

  // 🔁 ORDER BY句の構築
  switch ($sort) {
    case 'code_asc': $orderBy = 'ORDER BY p.code ASC'; break;
    case 'code_desc': $orderBy = 'ORDER BY p.code DESC'; break;
    case 'category_asc': $orderBy = 'ORDER BY c.name ASC'; break;
    case 'category_desc': $orderBy = 'ORDER BY c.name DESC'; break;
    case 'name_asc': $orderBy = 'ORDER BY p.name ASC'; break;
    case 'name_desc': $orderBy = 'ORDER BY p.name DESC'; break;
    case 'price_asc': $orderBy = 'ORDER BY p.price ASC'; break;
    case 'price_desc': $orderBy = 'ORDER BY p.price DESC'; break;
    case 'cost_asc': $orderBy = 'ORDER BY p.cost_price ASC'; break;
    case 'cost_desc': $orderBy = 'ORDER BY p.cost_price DESC'; break;
    case 'stock_asc': $orderBy = 'ORDER BY p.stock ASC'; break;
    case 'stock_desc': $orderBy = 'ORDER BY p.stock DESC'; break;
    case 'new':
    default: $orderBy = 'ORDER BY p.created_at DESC'; break;
  }

  // 📊 件数取得
  $countSql = "
    SELECT COUNT(*) FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $where
  ";
  $countStmt = $this->pdo->prepare($countSql);
  $countStmt->execute($params);
  $totalCount = (int)$countStmt->fetchColumn();
  error_log("totalCount: $totalCount");

  // ✅ ページ数の安全な計算（0除算防止）
  $totalPages = ($perPage > 0) ? max(1, ceil($totalCount / $perPage)) : 1;
  error_log("totalPages: $totalPages");

  // 📦 商品取得（LIMIT付き）＋ サムネイル取得
  $sql = "
    SELECT p.*, c.name AS category_name,
      (
        SELECT thumbnail
        FROM product_images
        WHERE product_id = p.id
        ORDER BY sort_order ASC
        LIMIT 1
      ) AS thumbnail
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $where
    $orderBy
    LIMIT $perPage OFFSET $offset
  ";
  $stmt = $this->pdo->prepare($sql);
  $stmt->execute($params);
  $products = $stmt->fetchAll();

  // 🔍 商品データの中身を確認（1件目）
  if (!empty($products)) {
    error_log("商品データ: " . print_r($products[0], true));
  }

  // ✅ ベースパス取得
  $routeContext = RouteContext::fromRequest($request);
  $basePath = $routeContext->getBasePath();

  // ✅ テンプレートに渡す
  extract([
    'products' => $products,
    'basePath' => $basePath,
    'totalCount' => $totalCount,
    'totalPages' => $totalPages,
    'currentPage' => $currentPage,
    'perPage' => $perPage,
  ]);

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

// 📦 商品登録処理（画像保存＋ログ付き）
public function registerProduct(Request $request, Response $response): Response {
  if (session_status() === PHP_SESSION_NONE) session_start();

  // 🔧 サムネイル生成関数（GD使用）
  function createThumbnail(string $sourcePath, string $destPath, int $thumbWidth = 200): bool {
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

  $data = $request->getParsedBody();
  $code = trim($data['code'] ?? '');
  $name = trim($data['name'] ?? '');
  $description = trim($data['description'] ?? '');
  $price = (int)($data['price'] ?? 0);
  $costPrice = (int)($data['cost_price'] ?? 0);
  $stock = (int)($data['stock'] ?? 0);
  $categoryId = (int)($data['category_id'] ?? 0);
  $userId = $_SESSION['user_id'] ?? null;

  $uploadedImages = $_FILES['images'] ?? [];
  $imageCount = is_array($uploadedImages['name']) ? count($uploadedImages['name']) : 0;

  error_log("商品コード: $code");
  error_log("画像枚数: $imageCount");
  error_log("画像アップロード: " . print_r($uploadedImages, true));

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
    error_log("バリデーションエラー: $error");

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
  error_log("商品登録完了: ID=$productId");

  // 画像保存処理
  $imageDir = dirname(__DIR__, 2) . '/public/images/products/';
  $imagePaths = [];

  foreach ($uploadedImages['tmp_name'] as $i => $tmpPath) {
    if ($tmpPath === '') {
      error_log("画像[$i] は空。スキップ");
      continue;
    }

    $originalName = basename($uploadedImages['name'][$i]);
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $newName = $code . '_' . time() . '_' . $i . '.' . $ext;
    $fullPath = $imageDir . $newName;

    if (move_uploaded_file($tmpPath, $fullPath)) {
      error_log("画像[$i] 保存成功: $newName");

      // ✅ サムネイル生成
      $thumbName = 'thumb_' . $newName;
      $thumbPath = $imageDir . $thumbName;
      if (createThumbnail($fullPath, $thumbPath)) {
        error_log("画像[$i] サムネイル生成成功: $thumbName");
      } else {
        error_log("画像[$i] サムネイル生成失敗");
        $thumbName = null;
      }

      $imagePaths[] = ['file' => $newName, 'thumb' => $thumbName];
    } else {
      error_log("画像[$i] 保存失敗: $fullPath");
    }
  }

  error_log("保存された画像数: " . count($imagePaths));

  foreach ($imagePaths as $i => $img) {
    $stmt = $this->pdo->prepare("
      INSERT INTO product_images (product_id, file_name, thumbnail, sort_order)
      VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$productId, $img['file'], $img['thumb'], $i + 1]);
    error_log("画像[$i] DB登録完了: {$img['file']} / {$img['thumb']}");
  }

  return $response->withHeader('Location', '/product_manager/products')->withStatus(302);
}


// 📦 商品編集画面表示（画像一覧付き）
public function editProduct(Request $request, Response $response, array $args): Response {
  if (session_status() === PHP_SESSION_NONE) session_start();

  $id = (int)$args['id'];
  $userId = $_SESSION['user_id'] ?? null;
  $role = $_SESSION['role'] ?? null;

  // 商品情報取得
  $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
  $stmt->execute([$id]);
  $product = $stmt->fetch();

  if (!$product) {
    $response->getBody()->write('商品が見つかりません');
    return $response->withStatus(404);
  }

  // 権限チェック
  $stmt = $this->pdo->prepare("SELECT can_manage_all_products FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $user = $stmt->fetch();
  $canManageAll = $user['can_manage_all_products'] ?? false;

  if ($role !== 'admin' && !$canManageAll && $product['user_id'] !== $userId) {
    $response->getBody()->write('この商品を編集する権限がありません');
    return $response->withStatus(403);
  }

  // カテゴリ取得
  $categoryModel = new CategoryModel($this->pdo);
  $categories = $categoryModel->getSortedTree();

  // 商品画像一覧取得
  $stmt = $this->pdo->prepare("SELECT id, file_name FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
  $stmt->execute([$id]);
  $productImages = $stmt->fetchAll();

  // ✅ ベースパス取得（Slim v4）
  $routeContext = RouteContext::fromRequest($request);
  $basePath = $routeContext->getBasePath();

  // ✅ テンプレートに変数を渡す
  extract([
    'product' => $product,
    'categories' => $categories,
    'productImages' => $productImages,
    'basePath' => $basePath,
  ]);

  ob_start();
  include dirname(__DIR__, 2) . '/templates/products/edit.php';
  $html = ob_get_clean();
  $response->getBody()->write($html);
  return $response;
}

// 📦 商品更新処理（画像削除・追加・並び順変更対応）
public function updateProduct(Request $request, Response $response, array $args): Response {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  // ✅ サムネイル生成関数（GD使用）
  function createThumbnail(string $sourcePath, string $destPath, int $thumbWidth = 200): bool {
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

  // 📸 画像削除処理（サムネイルも削除）
  $deleteIds = $data['delete_images'] ?? [];
  if (is_array($deleteIds) && count($deleteIds) > 0) {
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

  // 📸 並び順更新処理
  $sortedIds = explode(',', $data['sorted_image_ids'] ?? '');
  if (is_array($sortedIds)) {
    $stmt = $this->pdo->prepare("UPDATE product_images SET sort_order = ? WHERE id = ?");
    foreach ($sortedIds as $i => $imgId) {
      if ($imgId !== '') {
        $stmt->execute([$i + 1, $imgId]);
      }
    }
  }

  // 📸 画像追加処理（サムネイル生成付き）
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
      $thumbCreated = createThumbnail($fullPath, $thumbPath);
      $stmt->execute([$id, $newName, $thumbCreated ? $thumbName : null, ++$sortOrder]);
    }
  }

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
  
    // 📦 サムネイル作成処理
  function createThumbnail(string $sourcePath, string $destPath, int $thumbWidth = 200): bool {
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
