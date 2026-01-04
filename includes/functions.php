<?php
// 原有函数保持不变
function now() {
    return date('Y-m-d H:i:s');
}
function formatDate($datetime, $format = 'Y-m-d H:i') {
    return date($format, strtotime($datetime));
}
function timeDiff($start, $end = null) {
    if ($end === null) $end = now();
    $diff = strtotime($end) - strtotime($start);
    return [
        'days'    => floor($diff / 86400),
        'hours'   => floor(($diff % 86400) / 3600),
        'minutes' => floor(($diff % 3600) / 60),
        'seconds' => $diff % 60,
        'total_seconds' => $diff
    ];
}
function isVIPExpired($expireDate) {
    if (empty($expireDate)) return true;
    return strtotime($expireDate) < time();
}
function generateUniqueId() {
    return uniqid('', true) . '_' . mt_rand(1000, 9999);
}
function showError($message) {
    return '<div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>' . htmlspecialchars($message) . '
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}
function showSuccess($message) {
    return '<div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i>' . htmlspecialchars($message) . '
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}
function sanitizeInput($input) {
    return htmlspecialchars(trim(stripslashes($input)), ENT_QUOTES, 'UTF-8');
}
function getClientIP() {
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
function safeRedirect($url, $delay = 0) {
    if (!headers_sent()) {
        if ($delay > 0) {
            header("Refresh: $delay; url=$url");
        } else {
            header("Location: $url");
        }
    } else {
        echo "<meta http-equiv=\"refresh\" content=\"$delay; url=$url\">";
    }
    exit;
}
?>