<h2>🆕 ユーザー登録</h2>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form action="/product_manager/admin/users" method="post">
  <div class="mb-3">
    <label for="username" class="form-label">ユーザー名</label>
    <input type="text" name="username" id="username" class="form-control" required>
  </div>

  <div class="mb-3">
    <label for="password" class="form-label">パスワード</label>
    <input type="password" name="password" id="password" class="form-control" required>
  </div>

  <div class="mb-3">
    <label for="role" class="form-label">権限</label>
    <select name="role" id="role" class="form-select" required>
      <option value="user">一般ユーザー</option>
      <option value="admin">管理者</option>
    </select>
  </div>

  <button type="submit" class="btn btn-primary">登録する</button>
  <a href="/product_manager/admin/users" class="btn btn-secondary">戻る</a>
</form>
