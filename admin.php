<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/Database.php';

$auth = new Auth();
if (!$auth->isAdmin()) {
    header('Location: login.php');
    exit;
}
$db      = new JsonDB();
$users   = $db->get('users.json');
$orders  = $db->get('orders.json');
$pending = array_filter($orders, fn($o) => $o['status'] === 'pending');
$cdkOrders= $db->get('cdk-orders.json');
$cdkPending= array_filter($cdkOrders, fn($o) => $o['status'] === 'pending');
$custom  = $db->get('custom.json');

// 即将到期VIP判定（7天内，可改）
$expireDays = 7;
$now        = time();
$nearExpire = [];
$normalUsers= [];

foreach ($users as $u) {
    if ($u['vip_expire'] && strtotime($u['vip_expire']) > $now && strtotime($u['vip_expire']) <= $now + $expireDays * 86400) {
        $nearExpire[] = $u;
    } else {
        $normalUsers[] = $u;
    }
}

// ---- 加密/解密函数 ----
function encryptCDK($plain, $key = 'CDK_ENCRYPT_KEY_2025'): string {
    return openssl_encrypt($plain, 'AES-256-CTR', $key, 0, substr($key, 0, 16));
}
function decryptCDK($cipher, $key = 'CDK_ENCRYPT_KEY_2025'): string {
    return openssl_decrypt($cipher, 'AES-256-CTR', $key, 0, substr($key, 0, 16));
}

// ---- 处理所有POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. 删除用户
    if ($action === 'delete_user' && !empty($_POST['user_id'])) {
        $uid = $_POST['user_id'];
        $newUsers = array_filter($users, fn($u) => $u['id'] !== $uid);
        $db->save('users.json', array_values($newUsers));
        // 清理订单
        $newOrders = array_filter($orders, fn($o) => $o['user_id'] !== $uid);
        $db->save('orders.json', array_values($newOrders));
        header('Location: admin.php');
        exit;
    }

    // 2. 直接设置/修改VIP
    if ($action === 'set_vip' && !empty($_POST['user_id'])) {
        $uid     = $_POST['user_id'];
        $months  = (int)($_POST['months'] ?? 0);
        $vipType = trim($_POST['vip_type'] ?? '管理员设置');
        if ($months <= 0) {
            $errorVIP = '时长必须大于0';
        } else {
            $currentExpire = $db->find('users.json', 'id', $uid)['vip_expire'] ?? date('Y-m-d H:i:s');
            $newExpire = date('Y-m-d H:i:s', strtotime($currentExpire . " + $months months"));
            $db->update('users.json', 'id', $uid, [
                'vip_type'   => $vipType,
                'vip_expire' => $newExpire
            ]);
            $successVIP = 'VIP时长已更新';
        }
        header('Location: admin.php');
        exit;
    }

    // 3. CDK生成（加密存储）
    if ($action === 'generate_cdk') {
        $planId   = $_POST['plan_id'] ?? '';
        $qty      = (int)($_POST['qty'] ?? 1);
        $customCode = trim($_POST['custom_code'] ?? '');
        $plans    = $db->get('vip-plans.json');
        $plan     = null;
        foreach ($plans as $p) if ($p['id'] === $planId) {$plan = $p;break;}
        if (!$plan || $qty <= 0) {
            $errorCDK = '参数错误';
        } else {
            $cdks = $db->get('cdk-encrypted.json');
            for ($i = 0; $i < $qty; $i++) {
                $plain = $customCode && $qty === 1 ? $customCode : 'CDK-' . strtoupper(bin2hex(random_bytes(8)));
                $cipher = encryptCDK($plain);
                $cdks[] = [
                    'cipher'    => $cipher,
                    'plan_id'   => $plan['id'],
                    'plan_name' => $plan['name'],
                    'months'    => $plan['months'],
                    'status'    => 'unused',
                    'created_at'=> date('Y-m-d H:i:s')
                ];
            }
            $db->save('cdk-encrypted.json', $cdks);
            $successCDK = '已生成 ' . $qty . ' 个加密CDK';
        }
        header('Location: admin.php');
        exit;
    }

    // 4. CDK导出（解密后明文导出）
    if ($action === 'export_cdk') {
        $format = $_POST['export_format'] ?? 'txt';
        $cdks   = $db->get('cdk-encrypted.json');
        $output = '';
        foreach ($cdks as $c) {
            $plain = decryptCDK($c['cipher']);
            $line = $plain . "\t" . $c['plan_name'] . "\t" . $c['months'] . "\t" . $c['status'] . "\t" . $c['created_at'] . PHP_EOL;
            $output .= $line;
        }
        if ($format === 'txt') {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename=cdk_' . date('Ymd') . '.txt');
            echo $output;
            exit;
        } elseif ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename=cdk_' . date('Ymd') . '.csv');
            echo "CDK,套餐,月数,状态,创建时间\n";
            echo str_replace("\t", ',', $output);
            exit;
        }
    }

    // 5. CDK兑换审核
    if ($action === 'approve_cdk' && !empty($_POST['cdk_order_id'])) {
        $oid = $_POST['cdk_order_id'];
        foreach ($cdkOrders as &$o) {
            if ($o['id'] === $oid && $o['status'] === 'pending') {
                $o['status'] = 'approved';
                $o['approved_at'] = date('Y-m-d H:i:s');
                $user = $db->find('users.json', 'id', $o['user_id']);
                $currentExpire = $user['vip_expire'] ?? date('Y-m-d H:i:s');
                $newExpire = date('Y-m-d H:i:s', strtotime($currentExpire . ' + ' . $o['months'] . ' months'));
                $db->update('users.json', 'id', $o['user_id'], [
                    'vip_type'   => $o['plan_name'],
                    'vip_expire' => $newExpire
                ]);
                break;
            }
        }
        $db->save('cdk-orders.json', $cdkOrders);
        header('Location: admin.php');
        exit;
    }
    if ($action === 'reject_cdk' && !empty($_POST['cdk_order_id'])) {
        $oid = $_POST['cdk_order_id'];
        foreach ($cdkOrders as &$o) {
            if ($o['id'] === $oid && $o['status'] === 'pending') {
                $o['status'] = 'rejected';
                $o['rejected_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        $db->save('cdk-orders.json', $cdkOrders);
        header('Location: admin.php');
        exit;
    }

    // 6. 保存自定义内容（含弹窗公告）
    if ($action === 'save_custom_content') {
        $content = trim($_POST['vip_content'] ?? '');
        $announce= trim($_POST['announce'] ?? '');
        $db->save('custom.json', ['vip_content' => $content, 'announce' => $announce]);
        header('Location: admin.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>管理员后台 - VIP会员系统</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <div class="container">
    <span class="navbar-brand mb-0 h1"><i class="bi bi-shield-lock"></i> 管理员后台</span>
    <div class="ms-auto text-light me-3">欢迎，<?php echo htmlspecialchars($_SESSION['username']); ?></div>
    <a href="logout.php" class="btn btn-outline-light btn-sm">退出</a>
  </div>
</nav>
<div class="container-fluid mt-4">
  <div class="row">
    <!-- 统计 -->
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">快速统计</h5>
          <div class="list-group list-group-flush">
            <div class="list-group-item d-flex justify-content-between">总用户数<span class="badge bg-primary rounded-pill"><?php echo count($users); ?></span></div>
            <div class="list-group-item d-flex justify-content-between">VIP用户<span class="badge bg-success rounded-pill"><?php echo count(array_filter($users, fn($u) => $u['vip_expire'] && strtotime($u['vip_expire']) > time())); ?></span></div>
            <div class="list-group-item d-flex justify-content-between">即将到期<span class="badge bg-warning text-dark rounded-pill"><?php echo count($nearExpire); ?></span></div>
            <div class="list-group-item d-flex justify-content-between">待审核订单<span class="badge bg-info rounded-pill"><?php echo count($pending) + count($cdkPending); ?></span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- 用户管理：置顶显示即将到期VIP -->
    <div class="col-md-9">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="bi bi-people"></i> 用户管理</h5>
          <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus"></i> 添加用户</button>
        </div>
        <div class="card-body">
          <!-- 即将到期VIP置顶 -->
          <?php if ($nearExpire): ?>
            <h6 class="text-warning mb-2"><i class="bi bi-exclamation-triangle-fill"></i> 即将到期（≤<?php echo $expireDays; ?> 天）</h6>
            <div class="table-responsive mb-4">
              <table class="table table-sm table-warning align-middle">
                <thead><tr><th>用户名</th><th>邮箱</th><th>VIP到期</th><th>剩余天数</th><th>操作</th></tr></thead>
                <tbody>
                  <?php foreach ($nearExpire as $u): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($u['username']); ?></td>
                      <td><?php echo htmlspecialchars($u['email']); ?></td>
                      <td><span class="badge bg-warning text-dark"><?php echo date('Y-m-d', strtotime($u['vip_expire'])); ?></span></td>
                      <td><strong class="text-danger"><?php echo ceil((strtotime($u['vip_expire']) - time()) / 86400); ?></strong> 天</td>
                      <td>
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#vipModal<?php echo $u['id']; ?>">续VIP</button>
                          <form method="POST" class="d-inline" onsubmit="return confirm('确定删除该用户？');">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button class="btn btn-danger btn-sm">删除</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <hr>
          <?php endif; ?>

          <!-- 其余用户 -->
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead><tr><th>用户名</th><th>邮箱</th><th>VIP状态</th><th>注册时间</th><th>状态</th><th>操作</th></tr></thead>
              <tbody>
                <?php foreach ($normalUsers as $u): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                      <?php if ($u['vip_expire'] && strtotime($u['vip_expire']) > time()): ?>
                        <span class="badge bg-success">VIP <?php echo $u['vip_type']; ?><br><small>到期: <?php echo date('Y-m-d', strtotime($u['vip_expire'])); ?></small></span>
                      <?php else: ?>
                        <span class="badge bg-secondary">普通用户</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></td>
                    <td><span class="badge bg-<?php echo $u['status'] === 'active' ? 'success' : 'danger'; ?>"><?php echo $u['status'] === 'active' ? '正常' : '封禁'; ?></span></td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#vipModal<?php echo $u['id']; ?>">VIP</button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('确定删除该用户？');">
                          <input type="hidden" name="action" value="delete_user">
                          <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                          <button class="btn btn-danger btn-sm">删除</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- 订单审核（原购买 + CDK） -->
    <div class="col-md-12 mt-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-cart-check"></i> 订单审核</h5></div>
        <div class="card-body">
          <!-- CDK待审核 -->
          <?php if ($cdkPending): ?>
            <h6 class="mb-2">CDK兑换订单</h6>
            <table class="table table-sm align-middle">
              <thead><tr><th>用户</th><th>CDK</th><th>套餐</th><th>月数</th><th>提交时间</th><th>操作</th></tr></thead>
              <tbody>
                <?php foreach ($cdkPending as $o): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($o['username']); ?></td>
                    <td><code><?php echo $o['cdk']; ?></code></td>
                    <td><?php echo $o['plan_name']; ?></td>
                    <td><?php echo $o['months']; ?></td>
                    <td><?php echo $o['created_at']; ?></td>
                    <td>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="approve_cdk">
                        <input type="hidden" name="cdk_order_id" value="<?php echo $o['id']; ?>">
                        <button class="btn btn-success btn-sm">通过</button>
                      </form>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="reject_cdk">
                        <input type="hidden" name="cdk_order_id" value="<?php echo $o['id']; ?>">
                        <button class="btn btn-danger btn-sm">拒绝</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <hr>
          <?php endif; ?>

          <!-- 原购买订单待审核 -->
          <?php if ($pending): ?>
            <h6 class="mb-2">直接购买订单</h6>
            <table class="table table-sm align-middle">
              <thead><tr><th>用户</th><th>套餐</th><th>价格</th><th>提交时间</th><th>操作</th></tr></thead>
              <tbody>
                <?php foreach ($pending as $o): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($o['username']); ?></td>
                    <td><?php echo $o['plan_name']; ?> (<?php echo $o['months']; ?> 个月)</td>
                    <td><?php echo $o['price'] == 0 ? '免费' : '¥' . $o['price']; ?></td>
                    <td><?php echo $o['created_at']; ?></td>
                    <td>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="approve_order">
                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                        <button class="btn btn-success btn-sm">通过</button>
                      </form>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="reject_order">
                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                        <button class="btn btn-danger btn-sm">拒绝</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- CDK生成与管理（加密存储） -->
    <div class="col-md-12 mt-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-key"></i> CDK卡密管理（加密存储）</h5></div>
        <div class="card-body">
          <!-- 生成区域 -->
          <form method="POST" class="row g-3 mb-4">
            <input type="hidden" name="action" value="generate_cdk">
            <div class="col-md-4">
              <label class="form-label">选择套餐</label>
              <select class="form-select" name="plan_id" required>
                <?php foreach ($db->get('vip-plans.json') as $p): ?>
                  <option value="<?php echo $p['id']; ?>"><?php echo $p['name']; ?> (<?php echo $p['months']; ?> 月)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">生成数量</label>
              <input type="number" class="form-control" name="qty" value="10" min="1" max="1000" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">自定义卡密（留空=随机）</label>
              <input type="text" class="form-control" name="custom_code" placeholder="单次有效">
            </div>
            <div class="col-md-1 d-flex align-items-end">
              <button type="submit" class="btn btn-primary w-100">生成</button>
            </div>
          </form>

          <!-- 导出按钮 -->
          <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="export_cdk">
            <button type="submit" class="btn btn-success btn-sm" name="export_format" value="txt"><i class="bi bi-download"></i> 导出TXT</button>
            <button type="submit" class="btn btn-success btn-sm ms-2" name="export_format" value="csv"><i class="bi bi-download"></i> 导出CSV</button>
          </form>

          <!-- 现有CDK列表（解密后显示） -->
          <div class="table-responsive mt-4">
            <table class="table table-sm align-middle">
              <thead><tr><th>CDK（解密后）</th><th>套餐</th><th>月数</th><th>状态</th><th>创建时间</th></tr></thead>
              <tbody>
                <?php
                $cdks = $db->get('cdk-encrypted.json');
                foreach (array_slice(array_reverse($cdks), 0, 100) as $c):
                  $plain = decryptCDK($c['cipher']);
                ?>
                  <tr>
                    <td><code><?php echo htmlspecialchars($plain); ?></code></td>
                    <td><?php echo $c['plan_name']; ?></td>
                    <td><?php echo $c['months']; ?></td>
                    <td><span class="badge bg-<?php echo $c['status'] === 'unused' ? 'success' : 'secondary'; ?>"><?php echo $c['status']; ?></span></td>
                    <td><?php echo $c['created_at']; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- 自定义内容（含弹窗公告） -->
    <div class="col-md-12 mt-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> 自定义会员内容 & 弹窗公告</h5></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="save_custom_content">
            <div class="mb-3">
              <label class="form-label">VIP可见内容（支持HTML）</label>
              <textarea class="form-control" name="vip_content" rows="4" placeholder="仅VIP用户可见的专属内容"><?php
                echo htmlspecialchars($custom['vip_content'] ?? '');
              ?></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">弹窗公告（留空则不显示）</label>
              <textarea class="form-control" name="announce" rows="4" placeholder="用户首次进入仪表盘时弹出，支持HTML"><?php
                echo htmlspecialchars($custom['announce'] ?? '');
              ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 一键设置VIP模态框（每用户） -->
<?php foreach ($users as $u): ?>
  <div class="modal fade" id="vipModal<?php echo $u['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">设置VIP - <?php echo htmlspecialchars($u['username']); ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="set_vip">
            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
            <div class="mb-3">
              <label class="form-label">VIP类型</label>
              <input type="text" class="form-control" name="vip_type" value="管理员设置" required>
            </div>
            <div class="mb-3">
              <label class="form-label">时长（月）</label>
              <input type="number" class="form-control" name="months" value="1" min="1" max="120" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
            <button type="submit" class="btn btn-primary">设置</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>