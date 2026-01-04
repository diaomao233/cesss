<?php
session_start();
require_once 'Database.php';

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = new JsonDB();
    }

    /* ============== 公共：CF 验证码校验 ============== */
    private function checkCaptcha(): bool
    {
        // 如果 SESSION 里已标记通过，直接放行
        if (!empty($_SESSION['cf_ok'])) return true;

        $response = $_POST['cf-turnstile-response'] ?? '';
        if (!$response) return false;

        // 调用统一验证文件（captcha.php 已定义 cf_verify）
        require_once 'captcha.php';
        if (cf_verify($response)) {
            $_SESSION['cf_ok'] = 1;   // 标记本次会话已验证
            return true;
        }
        return false;
    }

    /* ============== 注册 ============== */
    public function register($username, $email, $password)
    {
        // 【CF】先过验证码
        if (!$this->checkCaptcha()) {
            return ['success' => false, 'message' => '验证码失败'];
        }

        if ($this->db->find('users.json', 'username', $username)) {
            return ['success' => false, 'message' => '用户名已存在'];
        }
        if ($this->db->find('users.json', 'email', $email)) {
            return ['success' => false, 'message' => '邮箱已被注册'];
        }

        $user = [
            'id'          => uniqid(),
            'username'    => $username,
            'email'       => $email,
            'password'    => password_hash($password, PASSWORD_DEFAULT),
            'vip_expire'  => null,
            'vip_type'    => null,
            'created_at'  => date('Y-m-d H:i:s'),
            'status'      => 'active'
        ];
        $this->db->insert('users.json', $user);

        // 自动登录
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = false;

        return ['success' => true, 'message' => '注册成功'];
    }

    /* ============== 登录 ============== */
    public function login($username, $password)
    {
        // 【CF】先过验证码
        if (!$this->checkCaptcha()) {
            return ['success' => false, 'message' => '验证码失败'];
        }

        $user = $this->db->find('users.json', 'username', $username);
        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => '用户名或密码错误'];
        }
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => '账户已被禁用'];
        }

        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = ($username === 'admin');

        return ['success' => true, 'message' => '登录成功'];
    }

    /* ============== 其余方法保持原样 ============== */
    public function logout()
    {
        session_destroy();
    }
    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }
    public function isAdmin()
    {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
    }
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) return null;
        return $this->db->find('users.json', 'id', $_SESSION['user_id']);
    }
    public function isVIP()
    {
        $user = $this->getCurrentUser();
        if (!$user || !$user['vip_expire']) return false;
        return strtotime($user['vip_expire']) > time();
    }
    public function getVIPExpireDate()
    {
        $user = $this->getCurrentUser();
        return $user['vip_expire'] ?? null;
    }
}