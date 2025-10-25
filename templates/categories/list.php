<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>カテゴリ一覧</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
  <div class="container mt-5">
    <h2>📂 カテゴリ一覧</h2>

    <div class="mb-3">
      <a href="/product_manager/categories/new" class="btn btn-success">＋ カテゴリを登録する</a>
      <a href="/product_manager/products" class="btn btn-secondary ms-2">← 商品一覧に戻る</a>
    </div>

    <table class="table table-bordered table-striped">
      <thead class="table-dark">
        <tr>
          <th>カテゴリ名</th>
          <th>操作</th> <!-- ✅ 追加 -->
        </tr>
      </thead>
      <tbody id="sortable-category-list">
        <?php foreach ($categories as $cat): ?>
          <tr data-id="<?= $cat['id'] ?>">
            <td style="padding-left: <?= 10 + ($cat['depth'] * 20) ?>px;">
              <?= $cat['depth'] > 0 ? '└ ' : '' ?>
              <?= htmlspecialchars($cat['name']) ?>
            </td>
            <td>
              <a href="/product_manager/categories/<?= $cat['id'] ?>/edit" class="btn btn-sm btn-outline-primary">編集</a>
              <form method="POST" action="/product_manager/categories/<?= $cat['id'] ?>/delete" style="display:inline;">
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('このカテゴリを削除しますか？');">削除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  
  <!-- ✅ Sortable.js 読み込みと並び順送信スクリプト -->
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const tbody = document.getElementById('sortable-category-list');
      Sortable.create(tbody, {
        animation: 150,
        onEnd: function () {
          const order = [];
          tbody.querySelectorAll('tr').forEach((row, index) => {
            order.push({ id: row.dataset.id, sort_order: index + 1 });
          });

          fetch('/product_manager/categories/sort', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(order)
          }).then(res => {
            if (!res.ok) {
              alert('並び順の保存に失敗しました');
            } else {
              location.reload(); // ✅ 保存成功後にリロード！
            }
          });
        }
      });
    });
  </script>
  
</body>
</html>
