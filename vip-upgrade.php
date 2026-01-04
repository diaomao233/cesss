<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/Database.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}
$db   = new JsonDB();
$user = $auth->getCurrentUser();
$isVIP = $auth->isVIP();
$custom = $db->get('custom.json');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>升级VIP - VIP会员系统</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="index.php"><i class="bi bi-crown"></i> VIP会员系统</a>
    <div class="ms-auto">
      <span class="text-light me-3">欢迎，<?php echo htmlspecialchars($user['username']); ?></span>
      <a href="logout.php" class="btn btn-outline-light btn-sm">退出</a>
    </div>
  </div>
</nav>
<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card shadow-lg">
        <div class="card-body text-center">
          <i class="bi bi-star text-warning" style="font-size: 3rem;"></i>
          <h4 class="mt-2">升级VIP</h4>
          <p class="text-muted">使用CDK卡密兑换VIP时长</p>

          <?php if ($isVIP): ?>
            <div class="alert alert-info">
              <h6><i class="bi bi-info-circle-fill"></i> 您当前是VIP会员</h6>
              <p class="mb-0">VIP到期时间：<?php echo date('Y年m月d日', strtotime($user['vip_expire'])); ?></p>
            </div>
          <?php endif; ?>

          <div class="d-grid gap-2 d-md-flex justify-content-center mt-4">
            <a href="cdk-exchange.php" class="btn btn-primary btn-lg px-4"><i class="bi bi-key me-2"></i>CDK兑换</a>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-lg px-4">返回仪表盘</a>
          </div>

          <hr class="my-4">
          <p class="mb-0 text-muted">没有CDK？请联系管理员或参与活动获取。</p>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>