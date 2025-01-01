<?php
session_start();

// 如果已经登录，直接跳转到管理页面
if (isset($_SESSION['admin'])) {
    header('Location: ip_manage.php');
    exit;
}

// 检查记住我的cookie
if (isset($_COOKIE['remember_token']) && isset($_COOKIE['username'])) {
    require_once 'config.php';
    
    $username = mysqli_real_escape_string($conn, $_COOKIE['username']);
    $token = $_COOKIE['remember_token'];
    
    // 验证token
    if ($token === hash('sha256', 'admin_salt_' . $username)) {
        $_SESSION['admin'] = true;
        $_SESSION['username'] = $username;
        
        // 更新最后登录时间
        mysqli_query($conn, "UPDATE admin_users SET last_login = NOW() WHERE username = '$username'");
        
        header('Location: ip_manage.php');
        exit;
    }
}

// 如果未登录且没有有效的cookie，跳转到登录页面
header('Location: login.php');
exit; 