<?php
require_once 'config.php';  // 数据库配置
require_once 'IpManager.php';  // IP管理类

function validateAccess() {
    try {
        $ipManager = new IpManager();
        $clientIp = getuserip();
        
        if (!$ipManager->isIpAllowed($clientIp)) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => '访问未授权或已过期',
                'ip' => $clientIp
            ]);
            exit;
        }
        
        return true;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => '系统错误'
        ]);
        exit;
    }
} 