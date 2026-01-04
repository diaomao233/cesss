<?php
// 原有验证函数保持不变
function validateUsername($username) {
    if (empty($username)) return ['valid' => false, 'message' => '用户名不能为空'];
    if (strlen($username) < 3 || strlen($username) > 20) return ['valid' => false, 'message' => '用户名长度必须在3-20个字符之间'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) return ['valid' => false, 'message' => '用户名只能包含字母、数字和下划线'];
    return ['valid' => true];
}
function validateEmail($email) {
    if (empty($email)) return ['valid' => false, 'message' => '邮箱不能为空'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['valid' => false, 'message' => '邮箱格式不正确'];
    return ['valid' => true];
}
function validatePassword($password) {
    if (empty($password)) return ['valid' => false, 'message' => '密码不能为空'];
    if (strlen($password) < 6) return ['valid' => false, 'message' => '密码长度至少为6位'];
    return ['valid' => true];
}

/**
 * 临时邮箱黑名单（可继续追加）
 */
function isTempEmail($email) {
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    $tempDomains = [
        // 常见临时邮箱域
        '10minutemail.com','mailinator.com','guerrillamail.com','tempmail.org',
        'mailnesia.com','throwawaymail.com','tempmailo.com','yopmail.com',
        'maildrop.cc','dispostable.com','harakirimail.com','mailcatch.com',
        'mintemail.com','sharklasers.com','spam4.me','bccto.me','chacuo.net'
    ];
    return in_array($domain, $tempDomains, true);
}
?>