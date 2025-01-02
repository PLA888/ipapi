<?php
header("Content-type: text/html; charset=utf-8");

// 数据库配置
$db_host = "localhost:3306";
$db_user = "ipapi";
$db_pwd = "FNsJJWhwijx5azke";
$db_database = "ipapi";

// 创建数据库连接
$conn = mysqli_connect($db_host, $db_user, $db_pwd, $db_database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 设置全局连接变量
global $conn;
mysqli_query($GLOBALS['conn'], "SET NAMES 'UTF8'");

// 设置数据库连接字符集
mysqli_set_charset($conn, 'utf8mb4');

// 设置PHP默认字符集
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// 确保设置正确的字符集
mysqli_query($conn, "SET NAMES utf8mb4");
mysqli_query($conn, "SET CHARACTER SET utf8mb4");
mysqli_query($conn, "SET character_set_connection=utf8mb4");

// 获取用户真实IP函数
function getuserip() {
    $real_ip = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_CLIENT_IP']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $_SERVER['HTTP_CLIENT_IP'])) {
        $real_ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
        foreach ($matches[0] AS $xip) {
            if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                $real_ip = $xip;
                break;
            }
        }
    }
    return $real_ip;
} 