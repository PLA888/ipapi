<?php
header("Content-type: text/html; charset=utf-8");

// 数据库配置
$db_host = "localhost:3306";
$db_user = "ipapi";
$db_pwd = "FNsJJWhwijx5azke";
$db_database = "ipapi";

// 创建数据库连接
$conn = mysqli_connect($db_host, $db_user, $db_pwd, $db_database) 
    OR die('无法登录MYSQL服务器！');

// 设置全局连接变量
global $conn;
mysqli_query($GLOBALS['conn'], "SET NAMES 'UTF8'");

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