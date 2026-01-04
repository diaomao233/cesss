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
$custom = $db->get('custom.json');

// ---- 加密/解密函数 ----
function encryptCDK($plain, $key = 'CDK_ENCRYPT_KEY_2025'): string {
    return openssl_encrypt($plain, 'AES-256-CTR', $key, 0, substr($key, 0, 16));
}
function decryptCDK($cipher, $key = 'CDK_ENCRYPT_KEY_2025'): string {
    return openssl_decrypt($cipher, 'AES-256-CTR', $key, 0, substr($key, 0, 16));
}

// 兑换处理
$success = '';
$error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cdk = trim($_POST['cdk'] ?? '');
    if (!$cdk) {
        $error = '请输入CDK卡密';
        goto showPage;
    }
    $cdks = $db->get('cdk-encrypted.json');
    $found = null;
    foreach ($cdks as $k => $c) {
        if ($c['status'] === 'unused') {
            $plain = decryptCDK($c['cipher']);
            if ($plain === $cdk) {
                $found = $c;
                $foundKey = $k;
                break;
            }
        }
    }
    if (!$found) {
        $error = 'CDK无效或已被使用';
        goto showPage;
    }

    // 写入待审核订单
    $order = [
        'id'        => uniqid('cdk_'),
        'user_id'   => $user['id'],
        'username'=> $user['username'],
        'cdk'       => $cdk,
        'plan_id'   => $found['plan_id'],
        'plan_name' => $found['plan_name'],
        'months'    => $found['months'],
        'status'    => 'pending',
        'created_at'=> date('Y-m-d H:i:s')
    ];
    $db->insert('cdk-orders.json', $order);

    // 标记CDK已使用
    $cdks[$foundKey]['status'] = 'used';
    $cdks[$foundKey]['used_at']= date('Y-m-d H:i:s');
    $db->save('cdk-encrypted.json', $cdks);

    $success = '兑换成功！请等待管理员审核。';
}
showPage:
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>CDK兑换 - VIP会员系统</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
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
    <div class="col-md-6">
      <div class="card shadow-lg">
        <div class="card-body text-center">
          <i class="bi bi-key text-primary" style="font-size: 3rem;"></i>
          <h4 class="mt-2">CDK兑换VIP</h4>
          <p class="text-muted">输入加密卡密，兑换VIP时长</p>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
          <?php endif; ?>

          <form method="POST" class="text-start mt-3">
            <div class="mb-3">
              <label class="form-label">CDK卡密</label>
              <input type="text" class="form-control" name="cdk" placeholder="请输入CDK卡密" required>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg">立即兑换</button>
            </div>
          </form>

          <hr class="my-4">
          <p class="mb-0"><a href="dashboard.php" class="btn btn-outline-secondary btn-sm">返回仪表盘</a></p>
        </div>
      </div>

      <!-- 待审核提示 -->
      <?php
      $cdkOrders = $db->get('cdk-orders.json');
      $myPending = array_filter($cdkOrders, fn($o) => $o['user_id'] === $user['id'] && $o['status'] === 'pending');
      if ($myPending): ?>
        <div class="card mt-4">
          <div class="card-header bg-warning text-dark"><h6 class="mb-0"><i class="bi bi-clock"></i> 待审核CDK订单</h6></div>
          <ul class="list-group list-group-flush">
            <?php foreach ($myPending as $o): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><?php echo $o['plan_name']; ?> (<?php echo $o['months']; ?> 个月)</span>
                <span class="badge bg-warning text-dark">待审核</span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>