<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>商品編集</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
  <style>
    .thumbnail {
      width: 100px;
      height: auto;
      object-fit: cover;
      cursor: grab;
    }
    .sortable-container > div {
      cursor: grab;
    }
  </style>
</head>
<body class="bg-light">
  <?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
  <div class="container mt-5">

    <?php if (isset($_SESSION['user_id'])): ?>
      <div class="text-end mb-3">
        <a href="<?= $basePath ?>/logout" class="btn btn-outline-dark">ログアウト</a>
      </div>
    <?php endif; ?>

    <h2>✏️ 商品編集</h2>
    <form method="POST" action="<?= $basePath ?>/products/<?= $product['id'] ?>/update" enctype="multipart/form-data">
      <!-- 商品情報入力欄（省略なし） -->
      <div class="mb-3">
        <label for="code" class="form-label">商品コード（半角英数字）</label>
        <input type="text" name="code" id="code" class="form-control"
               value="<?= htmlspecialchars($product['code'] ?? '') ?>" required pattern="[A-Za-z0-9]+" maxlength="50" autocomplete="off">
      </div>

      <div class="mb-3">
        <label for="category_id" class="form-label">カテゴリ</label>
        <select name="category_id" id="category_id" class="form-select" required>
          <?php foreach ($categories as $cat): ?>
            <?php
              $indent = str_repeat('　', $cat['depth']);
              $prefix = $cat['depth'] > 0 ? '└ ' : '';
            ?>
            <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
              <?= $indent . $prefix . htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label for="name" class="form-label">商品名</label>
        <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
      </div>

      <div class="mb-3">
        <label for="description" class="form-label">商品説明</label>
        <textarea name="description" id="description" class="form-control" rows="4"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
      </div>

      <div class="mb-3">
        <label for="price" class="form-label">価格</label>
        <input type="number" name="price" id="price" class="form-control" value="<?= htmlspecialchars($product['price']) ?>" required>
      </div>

      <div class="mb-3">
        <label for="cost_price" class="form-label">仕入価格</label>
        <input type="number" name="cost_price" id="cost_price"
               class="form-control" min="0" step="1"
               value="<?= htmlspecialchars($product['cost_price'] ?? '') ?>" required>
      </div>

      <div class="mb-3">
        <label for="stock" class="form-label">在庫数</label>
        <input type="number" name="stock" id="stock" class="form-control" value="<?= htmlspecialchars($product['stock']) ?>" required>
      </div>

      <?php if (!empty($productImages)): ?>
        <div class="mb-3">
          <label class="form-label">登録済み画像（ドラッグで並び替え）</label>
          <div id="sortable-images" class="d-flex flex-wrap gap-3 sortable-container">
            <?php foreach ($productImages as $img): ?>
              <div class="text-center" data-id="<?= $img['id'] ?>">
                <a href="#" data-bs-toggle="modal" data-bs-target="#modal<?= $img['id'] ?>">
                  <img src="<?= $basePath ?>/images/products/<?= htmlspecialchars($img['file_name']) ?>" class="img-thumbnail thumbnail">
                </a>
                <div class="form-check mt-1">
                  <input class="form-check-input" type="checkbox" name="delete_images[]" value="<?= $img['id'] ?>" id="del<?= $img['id'] ?>">
                  <label class="form-check-label" for="del<?= $img['id'] ?>">削除</label>
                </div>
              </div>

              <!-- モーダル定義 -->
              <div class="modal fade" id="modal<?= $img['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content bg-transparent border-0">
                    <div class="modal-body text-center">
                      <img src="<?= $basePath ?>/images/products/<?= htmlspecialchars($img['file_name']) ?>" class="img-fluid">
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="sorted_image_ids" id="sorted_image_ids">
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <label for="new_images" class="form-label">画像を追加（最大10枚）</label>
        <input type="file" name="new_images[]" id="new_images" class="form-control" multiple accept="image/*">
        <div id="imageWarning" class="text-danger mt-2" style="display: none;">⚠️ 画像は最大10枚まで選択できます。</div>
      </div>

      <button type="submit" class="btn btn-primary">更新する</button>
      <a href="<?= $basePath ?>/products" class="btn btn-secondary ms-2">戻る</a>
    </form>
  </div>

  <script>
    document.getElementById('new_images').addEventListener('change', function () {
      const maxImages = 10;
      const warning = document.getElementById('imageWarning');
      if (this.files.length > maxImages) {
        warning.style.display = 'block';
        this.value = '';
      } else {
        warning.style.display = 'none';
      }
    });

    const sortable = new Sortable(document.getElementById('sortable-images'), {
      animation: 150,
      onEnd: function () {
        const ids = Array.from(document.querySelectorAll('#sortable-images > div')).map(el => el.dataset.id);
        document.getElementById('sorted_image_ids').value = ids.join(',');
      }
    });

    window.addEventListener('DOMContentLoaded', () => {
      const ids = Array.from(document.querySelectorAll('#sortable-images > div')).map(el => el.dataset.id);
      document.getElementById('sorted_image_ids').value = ids.join(',');
    });
  </script>
</body>
</html>
