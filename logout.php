<?php
session_start();
require_once 'includes/auth.php';
$auth = new Auth();
$auth->logout();
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
?>