<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/Database.php';

$auth    = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}
$user     = $auth->getCurrentUser();
$isVIP    = $auth->isVIP();
$vipExpire= $auth->getVIPExpireDate();
$db       = new JsonDB();
$custom   = $db->get('custom.json');

// 弹窗公告
$announce   = trim($custom['announce'] ?? '');
$showAnnounce = false;
if ($announce) {
    // 判断“今日不再显示”Cookie
    $cookieName = 'announce_closed_' . md5($announce);
    if (!isset($_COOKIE[$cookieName])) {
        $showAnnounce = true;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>用户中心 - VIP会员系统</title>
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

<!-- 弹窗公告 -->
<?php if ($showAnnounce): ?>
  <!-- 模态框 -->
  <div class="modal fade" id="announceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-megaphone"></i> 公告</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
        </div>
        <div class="modal-body">
          <?php echo $announce; ?>
        </div>
        <div class="modal-footer">
          <div class="form-check me-auto">
            <input class="form-check-input" type="checkbox" id="noMoreToday">
            <label class="form-check-label" for="noMoreToday">今日不再显示</label>
          </div>
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">知道了</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // 显示弹窗
    const announceModal = new bootstrap.Modal(document.getElementById('announceModal'));
    announceModal.show();

    // 点击关闭时记录Cookie
    document.getElementById('announceModal').addEventListener('hidden.bs.modal', function () {
      const noMore = document.getElementById('noMoreToday').checked;
      if (noMore) {
        // 设置 Cookie 过期时间为今日 23:59:59
        const end = new Date();
        end.setHours(23, 59, 59, 0);
        document.cookie = "<?php echo $cookieName; ?>=1; expires=" + end.toUTCString() + "; path=/;";
      }
    });
  </script>
<?php endif; ?>

<div class="container mt-4">
  <div class="row">
    <!-- 左侧用户信息 -->
    <div class="col-md-4">
      <div class="card">
        <div class="card-body text-center">
          <div class="mb-3">
            <?php if ($isVIP): ?>
              <i class="bi bi-crown-fill text-warning" style="font-size: 4rem;"></i>
              <h4 class="text-warning mt-2">VIP会员</h4>
            <?php else: ?>
              <i class="bi bi-person-circle text-secondary" style="font-size: 4rem;"></i>
              <h4 class="text-secondary mt-2">普通用户</h4>
            <?php endif; ?>
          </div>
          <h5><?php echo htmlspecialchars($user['username']); ?></h5>
          <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
          <hr>
          <p class="mb-1"><small>注册时间：<?php echo date('Y-m-d', strtotime($user['created_at'])); ?></small></p>
          <?php if ($isVIP): ?>
            <p class="mb-1"><small>VIP到期：<?php echo date('Y-m-d', strtotime($vipExpire)); ?></small></p>
            <div class="progress mt-2" style="height: 5px;">
              <?php
                $totalDays = (strtotime($vipExpire) - time()) / 86400;
                $percent   = min(100, max(0, ($totalDays / 30) * 100));
              ?>
              <div class="progress-bar bg-warning" style="width: <?php echo $percent; ?>%"></div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card mt-3">
        <div class="list-group list-group-flush">
          <a href="dashboard.php" class="list-group-item list-group-item-action active"><i class="bi bi-speedometer2 me-2"></i> 仪表板</a>
          <a href="vip-upgrade.php" class="list-group-item list-group-item-action"><i class="bi bi-star me-2"></i> 升级VIP</a>
          <a href="logout.php" class="list-group-item list-group-item-action text-danger"><i class="bi bi-box-arrow-right me-2"></i> 退出登录</a>
        </div>
      </div>
    </div>

    <!-- 右侧主内容 -->
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-speedometer2"></i> 仪表板</h5></div>
        <div class="card-body">
          <?php if ($isVIP): ?>
            <div class="alert alert-success">
              <h6><i class="bi bi-check-circle-fill"></i> 您当前是VIP会员</h6>
              <p class="mb-0">VIP到期时间：<?php echo date('Y年m月d日', strtotime($vipExpire)); ?></p>
            </div>
          <?php else: ?>
            <div class="alert alert-info">
              <h6><i class="bi bi-info-circle-fill"></i> 您还不是VIP会员</h6>
              <p class="mb-0">升级VIP享受更多特权服务</p>
            </div>
          <?php endif; ?>

          <!-- 统计卡片 -->
          <div class="row g-3">
            <div class="col-md-6">
              <div class="card bg-primary text-white">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                      <h6 class="mb-0">用户状态</h6>
                      <h4 class="mb-0"><?php echo $isVIP ? 'VIP会员' : '普通用户'; ?></h4>
                    </div>
                    <div><i class="bi bi-person-check" style="font-size: 2rem; opacity: .7;"></i></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card bg-info text-white">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                      <h6 class="mb-0">注册天数</h6>
                      <h4 class="mb-0"><?php $d = (time() - strtotime($user['created_at'])) / 86400; echo ceil($d) . ' 天'; ?></h4>
                    </div>
                    <div><i class="bi bi-calendar-check" style="font-size: 2rem; opacity: .7;"></i></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <hr class="my-4">

          <!-- VIP特权列表 -->
          <h6>VIP会员特权</h6>
          <div class="row g-2">
            <div class="col-6"><div class="p-2 border rounded"><i class="bi bi-speedometer2 text-primary me-2"></i>极速体验</div></div>
            <div class="col-6"><div class="p-2 border rounded"><i class="bi bi-unlock text-success me-2"></i>专属内容</div></div>
            <div class="col-6"><div class="p-2 border rounded"><i class="bi bi-headset text-info me-2"></i>优先支持</div></div>
            <div class="col-6"><div class="p-2 border rounded"><i class="bi bi-gift text-warning me-2"></i>专属优惠</div></div>
          </div>

          <?php if (!$isVIP): ?>
            <div class="text-center mt-4">
              <a href="vip-upgrade.php" class="btn btn-warning btn-lg"><i class="bi bi-star me-2"></i>立即升级VIP</a>
            </div>
          <?php endif; ?>

          <!-- 管理员自定义内容（仅VIP可见） -->
          <?php if ($isVIP): ?>
            <div class="card mt-4">
              <div class="card-header bg-warning text-dark">
                <h6 class="mb-0"><i class="bi bi-stars"></i> 专属内容</h6>
              </div>
              <div class="card-body">
                <?php
                  echo $custom['vip_content'] ?? '<p class="text-muted">管理员暂未设置专属内容</p>';
                ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>