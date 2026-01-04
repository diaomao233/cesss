<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/validation.php';

$auth = new Auth();
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 先过验证码
    if (empty($_POST['cf-turnstile-response']) || !$_SESSION['cf_ok'] ?? false) {
        $error = '请先完成验证码';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!validateUsername($username)['valid']) {
            $error = validateUsername($username)['message'];
        } elseif (!validateEmail($email)['valid']) {
            $error = validateEmail($email)['message'];
        } elseif (!validatePassword($password)['valid']) {
            $error = validatePassword($password)['message'];
        } elseif (isTempEmail($email)) {
            $error = '请勿使用临时邮箱，请使用常用邮箱（如 QQ/163/Gmail 等）';
        } else {
            $res = $auth->register($username, $email, $password);
            if ($res['success']) {
                $success = $res['message'];
                header('Refresh: 2; url=dashboard.php');
            } else {
                $error = $res['message'];
            }
        }
    }
}
$cfSiteKey = 'xxxxxxxxxxxxxxxx';   // 替换成你的 Site Key
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>用户注册 - VIP会员系统</title>
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
          <i class="bi bi-person-plus-fill text-primary" style="font-size: 3rem;"></i>
          <h2 class="mt-3 mb-1">用户注册</h2>
          <p class="text-muted">创建您的VIP账户</p>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
          <?php endif; ?>

          <form method="POST" class="text-start" id="regForm">
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-person me-1"></i>用户名</label>
              <input type="text" class="form-control" name="username" required pattern="[a-zA-Z0-9_]{3,20}">
            </div>
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-envelope me-1"></i>邮箱地址</label>
              <input type="email" class="form-control" name="email" required>
              <div class="form-text">请勿使用临时邮箱（如 10minutemail、mailinator 等）</div>
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
              <button type="submit" class="btn btn-primary btn-lg" id="regBtn" disabled>立即注册</button>
            </div>
          </form>

          <hr class="my-4">
          <p class="mb-0">已有账户？<a href="login.php">立即登录</a></p>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function unlockBtn() {
    document.getElementById('regBtn').disabled = false;
  }
</script>
</body>
</html>