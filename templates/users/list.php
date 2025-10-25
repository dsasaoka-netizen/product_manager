<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>

<?php if (isset($_SESSION['user_id'])): ?>
  <div class="text-end mb-3">
    <a href="/product_manager/logout" class="btn btn-outline-dark">ログアウト</a>
  </div>
<?php endif; ?>

<h2>👥 ユーザー一覧</h2>

<div class="mb-3">
  <a href="/product_manager/products" class="btn btn-secondary">← 商品一覧に戻る</a>
</div>

<div class="mb-3">
  <a href="/product_manager/admin/users/new" class="btn btn-primary">＋ ユーザーを登録する</a>
</div>

<table class="table table-bordered table-striped">
  <thead>
    <tr>
      <th>ID</th>
      <th>ユーザー名</th>
      <th>権限</th>
      <th>登録日時</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($users as $user): ?>
      <tr>
        <td><?= htmlspecialchars($user['id']) ?></td>
        <td><?= htmlspecialchars($user['username']) ?></td>
        <td><?= htmlspecialchars($user['role']) ?></td>
        <td><?= htmlspecialchars($user['created_at']) ?></td>
        <td>
          <a href="/product_manager/admin/users/<?= $user['id'] ?>/edit" class="btn btn-sm btn-warning">編集</a>
          <form action="/product_manager/admin/users/<?= $user['id'] ?>/delete" method="post" style="display:inline;">
            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('削除してもよろしいですか？');">削除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
