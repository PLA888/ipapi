<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'ip_error.log');

require_once 'config.php';
require_once 'IpManager.php';

try {
    $ipManager = new IpManager();
    $clientIp = getuserip();
    
    error_log("Received request from IP: " . $clientIp);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 记录新的 IP 访问
        $result = $ipManager->recordIpAccess($clientIp);
        
        if ($result) {
            error_log("Successfully recorded IP: " . $clientIp);
            echo json_encode([
                'status' => 'success',
                'message' => 'IP access recorded',
                'ip' => $clientIp
            ]);
        } else {
            error_log("Failed to record IP: " . $clientIp);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to record IP',
                'ip' => $clientIp
            ]);
        }
    } else {
        // 检查 IP 是否允许访问
        $isAllowed = $ipManager->isIpAllowed($clientIp);
        error_log("Checking IP access: " . $clientIp . ", Allowed: " . ($isAllowed ? 'yes' : 'no'));
        echo json_encode([
            'status' => $isAllowed ? 'allowed' : 'denied',
            'ip' => $clientIp
        ]);
    }
} catch (Exception $e) {
    error_log("Error in check_ip.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}

// 确保所有数据都被输出
if (ob_get_level() > 0) {
    ob_end_flush();
} 