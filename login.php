<?php
session_start();
require_once 'config.php';

// 检查记住我的cookie
if (!isset($_SESSION['admin']) && isset($_COOKIE['remember_token'])) {
    if ($_COOKIE['remember_token'] === hash('sha256', 'admin_salt_' . $_COOKIE['username'])) {
        $_SESSION['admin'] = true;
        $_SESSION['username'] = $_COOKIE['username'];
        header('Location: ip_manage.php');
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    // 从数据库验证用户
    $username = mysqli_real_escape_string($conn, $username);
    $sql = "SELECT password FROM admin_users WHERE username = '$username'";
    $result = mysqli_query($conn, $sql);
    
    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin'] = true;
            $_SESSION['username'] = $username;
            
            // 更新最后登录时间
            mysqli_query($conn, "UPDATE admin_users SET last_login = NOW() WHERE username = '$username'");
            
            // 如果选择了"记住我"
            if ($remember) {
                $token = hash('sha256', 'admin_salt_' . $username);
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
                setcookie('username', $username, time() + (30 * 24 * 60 * 60), '/');
            }
            
            header('Location: ip_manage.php');
            exit;
        }
    }
    $error = '用户名或密码错误';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>登录 - IP管理系统</title>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-box {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 300px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }
        .remember-me {
            margin: 10px 0;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2 style="text-align: center; margin-bottom: 20px;">IP管理系统</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="username">用户名：</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">密码：</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="remember-me">
                <label>
                    <input type="checkbox" name="remember"> 记住我（30天）
                </label>
            </div>
            <button type="submit">登录</button>
        </form>
    </div>
</body>
</html> 