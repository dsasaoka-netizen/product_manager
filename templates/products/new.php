<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>商品登録</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
  <div class="container mt-5">
    <h2>📝 商品登録</h2>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/product_manager/products" enctype="multipart/form-data">
      <div class="mb-3">
        <label for="code" class="form-label">商品コード（半角英数字）</label>
        <input type="text" name="code" id="code" class="form-control"
               value="<?= htmlspecialchars($product['code'] ?? '') ?>"
               required pattern="[A-Za-z0-9]+" autocomplete="off">
      </div>

      <div class="mb-3">
        <label for="category_id" class="form-label">カテゴリ</label>
        <select name="category_id" id="category_id" class="form-select" required>
          <?php foreach ($categories as $cat): ?>
            <?php
              $depth = isset($cat['depth']) ? (int)$cat['depth'] : 0;
              $indent = str_repeat('　', $depth);
              $prefix = $depth > 0 ? '└ ' : '';
            ?>
            <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($indent . $prefix . $cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label for="name" class="form-label">商品名</label>
        <input type="text" name="name" id="name" class="form-control"
               value="<?= htmlspecialchars($product['name'] ?? '') ?>" required>
      </div>

      <div class="mb-3">
        <label for="description" class="form-label">商品説明</label>
        <textarea name="description" id="description" class="form-control" rows="4"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
      </div>

      <div class="mb-3">
        <label for="price" class="form-label">価格</label>
        <input type="number" name="price" id="price" class="form-control"
               value="<?= htmlspecialchars($product['price'] ?? '') ?>" required>
      </div>

      <div class="mb-3">
        <label for="cost_price" class="form-label">仕入価格</label>
        <input type="number" name="cost_price" id="cost_price" class="form-control"
               value="<?= htmlspecialchars($product['cost_price'] ?? '') ?>"
               min="0" step="1" required>
      </div>

      <div class="mb-3">
        <label for="stock" class="form-label">在庫数</label>
        <input type="number" name="stock" id="stock" class="form-control"
               value="<?= htmlspecialchars($product['stock'] ?? '') ?>" required>
      </div>

      <div class="mb-3">
        <label for="images" class="form-label">商品画像（最大10枚まで）</label>
        <input type="file" name="images[]" id="images" class="form-control" multiple accept="image/*">
        <div id="imageWarning" class="text-danger mt-2" style="display: none;">
          ⚠️ 画像は最大10枚まで選択できます。
        </div>
      </div>

      <button type="submit" class="btn btn-success">登録する</button>
      <a href="/product_manager/products" class="btn btn-secondary ms-2">戻る</a>
    </form>
  </div>

  <script>
    document.getElementById('images').addEventListener('change', function () {
      const maxImages = 10;
      const warning = document.getElementById('imageWarning');

      if (this.files.length > maxImages) {
        warning.style.display = 'block';
        this.value = ''; // 選択をリセット
      } else {
        warning.style.display = 'none';
      }
    });
  </script>
</body>
</html>
