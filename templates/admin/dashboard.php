<?php
// templates/admin/dashboard.php
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>管理画面</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <h2>🛠️ 管理者ダッシュボード</h2>

    <!-- 統計表示 -->
    <div class="mt-4">
      <div class="alert alert-info">
        未生成サムネイル画像：<strong><?= $missingThumbnailCount ?></strong> 件
      </div>
      <div class="alert alert-warning">
        画像未登録の商品：<strong><?= $noImageProductCount ?></strong> 件
      </div>
    </div>

    <!-- 管理機能リンク -->
    <ul class="list-group mt-3">
      <li class="list-group-item"><a href="<?= $basePath ?>/admin/users">👥 ユーザー管理</a></li>
      <li class="list-group-item"><a href="<?= $basePath ?>/categories">🗂️ カテゴリ管理</a></li>
      <li class="list-group-item"><a href="<?= $basePath ?>/admin/regenerate-thumbnails">🖼️ サムネイル再生成</a></li>
    </ul>

    <a href="<?= $basePath ?>/products" class="btn btn-link mt-4">← 商品一覧に戻る</a>
  </div>
</body>
</html>
