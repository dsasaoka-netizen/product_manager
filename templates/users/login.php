<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ログイン | 商品管理システム</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-sm-10">
        <div class="card shadow">
          <div class="card-header text-center">
            <h4>🔒 商品管理システム ログイン</h4>
          </div>
          <div class="card-body">
            <?php if (!empty($error)): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" action="/product_manager/login">
              <div class="mb-3">
                <label for="username" class="form-label">ユーザーID</label>
                  <input type="text" name="username" id="username"
                         class="form-control" required
                         inputmode="latin" autocomplete="username" autocapitalize="none"
                         style="ime-mode:disabled;">
                </div>
                <div class="mb-3">
                  <label for="password" class="form-label">パスワード</label>
                  <input type="password" name="password" id="password"
                         class="form-control" required
                         inputmode="latin" autocomplete="current-password" autocapitalize="none"
                         style="ime-mode:disabled;">
                </div>
              <button type="submit" class="btn btn-primary w-100">ログイン</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const usernameInput = document.getElementById('username');
      const passwordInput = document.getElementById('password');
  
      if (usernameInput) {
        usernameInput.setAttribute('inputmode', 'latin');
        usernameInput.setAttribute('autocomplete', 'username');
        usernameInput.setAttribute('autocapitalize', 'none');
        usernameInput.style.imeMode = 'disabled';
      }
  
      if (passwordInput) {
        passwordInput.setAttribute('inputmode', 'latin');
        passwordInput.setAttribute('autocomplete', 'current-password');
        passwordInput.setAttribute('autocapitalize', 'none');
        passwordInput.style.imeMode = 'disabled';
      }
    });
  </script>
</body>
</html>
