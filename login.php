<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 把 CF 响应带过去
    $res = $auth->login($_POST['username'], $_POST['password']);
    if ($res['success']) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = $res['message'];
    }
}
$cfSiteKey = 'xxxxxxxxxxxxxx';   // 替换成你的 Site Key
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>用户登录 - VIP会员系统</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <!-- CF Turnstile -->
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="bg-light">
<div class="container">
  <div class="row justify-content-center align-items-center min-vh-100">
    <div class="col-md-6 col-lg-4">
      <div class="card shadow-lg border-0">
        <div class="card-body p-5 text-center">
          <i class="bi bi-box-arrow-in-right text-primary" style="font-size: 3rem;"></i>
          <h2 class="mt-3 mb-1">用户登录</h2>
          <p class="text-muted">欢迎回来</p>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>

          <form method="POST" class="text-start" id="loginForm">
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-person me-1"></i>用户名</label>
              <input type="text" class="form-control" name="username" required>
            </div>
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-lock me-1"></i>密码</label>
              <input type="password" class="form-control" name="password" required minlength="6">
            </div>

            <!-- CF 验证码 -->
            <div class="mb-3">
              <div class="cf-turnstile" data-sitekey="<?= $cfSiteKey ?>" data-theme="light" data-callback="unlockBtn"></div>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg" id="loginBtn" disabled>登录</button>
            </div>
          </form>

          <hr class="my-4">
          <p class="mb-0">还没有账户？<a href="register.php">立即注册</a></p>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function unlockBtn() {
    document.getElementById('loginBtn').disabled = false;
  }
</script>
</body>
</html>