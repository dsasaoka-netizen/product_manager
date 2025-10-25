<h2>✏️ カテゴリ編集</h2>
<form method="POST" action="/product_manager/categories/<?= $category['id'] ?>/update">
  <div class="mb-3">
    <label for="name" class="form-label">カテゴリ名</label>
    <input type="text" name="name" id="name" class="form-control"
           value="<?= htmlspecialchars($category['name']) ?>" required>
  </div>

  <div class="mb-3">
    <label for="parent_id" class="form-label">親カテゴリ（任意）</label>
    <select name="parent_id" id="parent_id" class="form-select">
      <option value="">なし（大分類）</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= $category['parent_id'] == $cat['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($cat['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <button type="submit" class="btn btn-primary">更新する</button>
  <a href="/product_manager/categories" class="btn btn-secondary ms-2">戻る</a>
</form>
