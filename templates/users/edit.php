<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>

<?php if (isset($_SESSION['user_id'])): ?>
  <div class="text-end mb-3">
    <a href="/product_manager/logout" class="btn btn-outline-dark">ログアウト</a>
  </div>
<?php endif; ?>

<h2>✏️ ユーザー編集</h2>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form action="/product_manager/admin/users/<?= $user['id'] ?>/update" method="post">
  <div class="mb-3">
    <label for="username" class="form-label">ユーザー名</label>
    <input type="text" name="username" id="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
  </div>

  <div class="mb-3">
    <label for="password" class="form-label">新しいパスワード（変更しない場合は空欄）</label>
    <input type="password" name="password" id="password" class="form-control">
  </div>

  <div class="mb-3">
    <label for="role" class="form-label">権限</label>
    <select name="role" id="role" class="form-select" required>
      <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>一般ユーザー</option>
      <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>管理者</option>
    </select>
  </div>

  <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="can_manage_all_products" id="can_manage_all_products"
             <?= !empty($user['can_manage_all_products']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="can_manage_all_products">
        他人の商品も編集・削除できる
      </label>
    </div>
  <?php endif; ?>

  <button type="submit" class="btn btn-primary">更新する</button>
  <a href="/product_manager/admin/users" class="btn btn-secondary">戻る</a>
</form>
