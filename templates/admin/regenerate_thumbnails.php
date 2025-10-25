<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>サムネイル再生成</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <h2>🛠️ サムネイル一括再生成</h2>
    <?php if (!empty($message)): ?>
      <div class="alert alert-success mt-3"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="POST">
      <button type="submit" class="btn btn-primary mt-3">再生成を実行する</button>
    </form>
    <a href="/product_manager/products" class="btn btn-link mt-3">← 商品一覧に戻る</a>
  </div>
</body>
</html>
