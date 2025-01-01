<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($new_password !== $confirm_password) {
        $error = '新密码两次输入不一致';
    } elseif (strlen($new_password) < 6) {
        $error = '新密码长度不能小于6位';
    } else {
        $username = $_SESSION['username'];
        $sql = "SELECT password FROM admin_users WHERE username = '$username'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        
        if (password_verify($old_password, $row['password'])) {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE admin_users SET password = '$new_password_hash' WHERE username = '$username'";
            
            if (mysqli_query($conn, $sql)) {
                $message = '密码修改成功';
                // 清除记住我的cookie
                setcookie('remember_token', '', time() - 3600, '/');
                setcookie('username', '', time() - 3600, '/');
            } else {
                $error = '密码修改失败，请重试';
            }
        } else {
            $error = '原密码错误';
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>修改密码 - IP访问管理系统</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://lf26-cdn-tos.bytecdntp.com/cdn/expire-1-M/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://lf3-cdn-tos.bytecdntp.com/cdn/expire-1-M/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fc; 
            padding: 20px;
        }
        .container {
            max-width: 500px;
            background-color: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-top: 50px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .btn-back {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="ip_manage.php" class="btn btn-outline-primary btn-back">
            <i class="fas fa-arrow-left"></i> 返回管理页面
        </a>
        
        <div class="header">
            <h2>修改管理员密码</h2>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">原密码</label>
                <input type="password" name="old_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">新密码</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">确认新密码</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">修改密码</button>
        </form>
    </div>
</body>
</html> 