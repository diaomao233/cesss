<?php
/* 全局 CF 配置 */
const CF_SITE   = '0x4AAAAAACKZ9y_Q-q8alRYy';
const CF_SECRET = '0x4AAAAAACKZ93_OeJqUBOzVRN5NQdm0TuQ';

/* 统一验证函数 */
function cf_verify(string $response): bool
{
    if (empty($response)) return false;
    $data = http_build_query([
        'secret'   => CF_SECRET,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $data
        ]
    ];
    $api  = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $json = file_get_contents($api, false, stream_context_create($opts));
    $arr  = json_decode($json, true);
    return isset($arr['success']) && $arr['success'] === true;
}

/* 首次验证成功后在 SESSION 打标记 */
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cf'])) {
    if (cf_verify($_POST['cf'])) {
        $_SESSION['cf_ok'] = 1;
        exit('ok');
    }
    exit('fail');
}