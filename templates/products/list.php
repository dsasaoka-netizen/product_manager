<?php
  if (session_status() === PHP_SESSION_NONE) session_start();

  // クッキーから並び順と表示件数を取得（クエリ優先）
  $selectedSort = $_GET['sort'] ?? ($_COOKIE['sort'] ?? '');
  $selectedPerPage = $_GET['per_page'] ?? ($_COOKIE['per_page'] ?? 10);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>商品一覧</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .thumbnail {
      width: 80px;
      height: auto;
      object-fit: cover;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container mt-5">

    <?php if (isset($_SESSION['user_id'])): ?>
      <div class="text-end mb-3">
        <a href="<?= $basePath ?>/logout" class="btn btn-outline-dark">ログアウト</a>
      </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>📦 商品一覧</h2>
      <div>
        <a href="<?= $basePath ?>/products/new" class="btn btn-success me-2">＋ 商品を登録する</a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <a href="<?= $basePath ?>/admin/dashboard" class="btn btn-outline-secondary">🛠️ 管理画面</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- 🔍 検索＋並び替え＋表示件数＋リセット -->
    <form method="GET" action="<?= $basePath ?>/products" class="mb-4">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <input type="text" name="keyword" class="form-control" placeholder="商品名・コード・カテゴリで検索"
                 value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <select name="sort" class="form-select">
            <option value="">並び順を選択</option>
            <?php
              $sortOptions = [
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
              ];
              foreach ($sortOptions as $value => $label):
            ?>
              <option value="<?= $value ?>" <?= $selectedSort === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="per_page" class="form-select">
            <?php foreach ([10, 30, 50, 100] as $option): ?>
              <option value="<?= $option ?>" <?= (int)$selectedPerPage === $option ? 'selected' : '' ?>><?= $option ?>件表示</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-outline-primary">検索・並び替え</button>
          <a href="<?= $basePath ?>/products?sort=code_asc&per_page=10" class="btn btn-outline-secondary">リセット</a>
        </div>
      </div>
    </form>

    <!-- 📄 件数表示 -->
    <p class="text-muted">全 <?= $totalCount ?> 件中 <?= count($products) ?> 件を表示（<?= $perPage ?>件/ページ）</p>

    <table class="table table-bordered table-striped mt-3">
      <thead class="table-dark">
        <tr>
          <th>画像</th>
          <th>商品コード</th>
          <th>カテゴリ</th>
          <th>商品名</th>
          <th>価格</th>
          <th>仕入価格</th>
          <th>在庫</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
          <tr><td colspan="8" class="text-center text-muted">該当する商品が見つかりませんでした。</td></tr>
        <?php else: ?>
          <?php foreach ($products as $product): ?>
            <tr>
              <td>
                <?php if (!empty($product['thumbnail'])): ?>
                  <img src="<?= $basePath ?>/images/products/<?= htmlspecialchars($product['thumbnail']) ?>" class="thumbnail img-thumbnail">
                <?php else: ?>
                  <span class="text-muted">No Image</span>
                <?php endif; ?>
              </td>
              
              <td><?= htmlspecialchars($product['code'] ?? '') ?: '<span class="text-muted">未設定</span>' ?></td>
              <td><?= htmlspecialchars($product['category_name'] ?? '') ?: '<span class="text-muted">未分類</span>' ?></td>
              <td><?= htmlspecialchars($product['name']) ?></td>
              <td><?= number_format((int)$product['price']) ?> 円</td>
              <td><?= number_format((int)$product['cost_price']) ?> 円</td>
              <td><?= number_format((int)$product['stock']) ?></td>
              <td>
                <?php
                  $canEdit = $_SESSION['role'] === 'admin'
                          || !empty($_SESSION['can_manage_all_products'])
                          || $product['user_id'] === ($_SESSION['user_id'] ?? null);
                ?>
                <?php if ($canEdit): ?>
                  <div class="d-flex gap-2">
                    <a href="<?= $basePath ?>/products/<?= $product['id'] ?>/edit" class="btn btn-sm btn-outline-primary">編集</a>
                    <form method="POST" action="<?= $basePath ?>/products/<?= $product['id'] ?>/delete" onsubmit="return confirm('本当に削除しますか？');">
                      <button type="submit" class="btn btn-sm btn-outline-danger">削除</button>
                    </form>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- 📦 ページネーション -->
    <?php if ($totalPages > 1): ?>
      <nav>
        <ul class="pagination justify-content-center">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
              <a class="page-link" href="<?= $basePath ?>/products?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                <?= $i ?>
              </a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</body>
</html>
