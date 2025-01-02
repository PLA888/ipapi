<?php
require_once 'config.php';  // 数据库配置
require_once 'IpManager.php';  // IP管理类

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $ipManager = new IpManager();
    $ip = getuserip();
    
    // 记录访问日志
    error_log("Received request - Method: " . $_SERVER['REQUEST_METHOD'] . ", IP: " . $ip);
    
    if ($ipManager->recordIpAccess($ip)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'IP recorded successfully',
            'ip' => $ip
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to record IP',
            'ip' => $ip
        ]);
    }
} catch (Exception $e) {
    error_log("Error in check_access.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error',
        'ip' => $ip ?? 'unknown'
    ]);
} 