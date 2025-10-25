<h2>📂 カテゴリ登録</h2>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="/product_manager/categories/create">
  <div class="mb-3">
    <label for="name" class="form-label">カテゴリ名</label>
    <input type="text" name="name" id="name" class="form-control"
           value="<?= htmlspecialchars($name ?? '') ?>" required>
  </div>

  <div class="mb-3">
    <label for="parent_id" class="form-label">親カテゴリ（任意）</label>
    <select name="parent_id" id="parent_id" class="form-select">
      <option value="">なし（大分類）</option>
      <?php foreach ($categories as $cat): ?>
        <?php
          $indent = str_repeat('　', $cat['depth']); // 全角スペースでインデント
          $prefix = $cat['depth'] > 0 ? '└ ' : '';   // 階層記号
        ?>
        <option value="<?= $cat['id'] ?>" <?= ($parentId ?? '') == $cat['id'] ? 'selected' : '' ?>>
          <?= $indent . $prefix . htmlspecialchars($cat['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

  </div>

  <button type="submit" class="btn btn-primary">登録する</button>
  <a href="/product_manager/categories" class="btn btn-secondary ms-2">戻る</a>
</form>
