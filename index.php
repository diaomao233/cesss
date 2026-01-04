<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$isLoggedIn = $auth->isLoggedIn();
$isVIP      = $auth->isVIP();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>VIP会员系统</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="#"><i class="bi bi-crown"></i> VIP会员系统</a>
    <div class="ms-auto">
      <?php if ($isLoggedIn): ?>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">用户中心</a>
        <a href="logout.php" class="btn btn-outline-light btn-sm">退出</a>
      <?php else: ?>
        <a href="login.php" class="btn btn-outline-light btn-sm">登录</a>
        <a href="register.php" class="btn btn-outline-light btn-sm">注册</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container mt-5">
  <div class="row align-items-center min-vh-80">
    <div class="col-lg-6">
      <h1 class="display-4 fw-bold text-primary mb-4">欢迎来到VIP会员系统</h1>
      <p class="lead mb-4">享受尊贵的VIP服务，解锁更多精彩功能。</p>
      <div class="d-grid gap-2 d-md-flex">
        <?php if (!$isLoggedIn): ?>
          <a href="register.php" class="btn btn-primary btn-lg px-4">立即注册</a>
          <a href="login.php" class="btn btn-outline-primary btn-lg px-4">用户登录</a>
        <?php else: ?>
          <a href="dashboard.php" class="btn btn-primary btn-lg px-4">进入用户中心</a>
          <?php if (!$isVIP): ?>
            <a href="vip-upgrade.php" class="btn btn-warning btn-lg px-4">升级VIP</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-6 text-center">
      <i class="bi bi-crown" style="font-size: 15rem; color: #007bff; opacity: .1;"></i>
    </div>
  </div>
</div>
<footer class="bg-dark text-light py-4 mt-5">
  <div class="container text-center"><p class="mb-0">&copy; 2025 VIP会员系统</p></div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>