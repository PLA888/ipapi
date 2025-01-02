// 获取设备详细信息
function getDeviceDetails() {
    $info = array();
    
    // 获取安卓设备信息
    if (function_exists('shell_exec')) {
        // 获取设备型号
        $model = trim(shell_exec('getprop ro.product.model'));
        $brand = trim(shell_exec('getprop ro.product.brand'));
        $manufacturer = trim(shell_exec('getprop ro.product.manufacturer'));
        $version = trim(shell_exec('getprop ro.build.version.release'));
        
        if ($model) $info['model'] = $model;
        if ($brand) $info['brand'] = $brand;
        if ($manufacturer) $info['manufacturer'] = $manufacturer;
        if ($version) $info['android_version'] = $version;
    }
    
    return $info;
}

// 获取设备信息
$deviceInfo = getDeviceDetails();

// 添加 IP 检查的 POST 请求
$check_ip_url = 'https://zbdsip.zhoujie218.top//index.php';
$post_data = array(
    'ip' => $ip,
    'user_id' => $name,
    'device_id' => $androidid,
    'device_info' => json_encode($deviceInfo)  // 添加设备信息
);

// 构建自定义 User-Agent
$userAgent = sprintf(
    "AndroidBox/%s (Android %s; %s %s; ID: %s)",
    $deviceInfo['android_version'] ?? 'unknown',
    $deviceInfo['manufacturer'] ?? 'unknown',
    $deviceInfo['model'] ?? 'unknown',
    $androidid
);

$headers = array(
    'X-Forwarded-For: ' . $ip,
    'X-Real-IP: ' . $ip,
    'Client-IP: ' . $ip,
    'User-Agent: ' . $userAgent,
    'X-Device-Type: AndroidBox',
    'X-Device-ID: ' . $androidid,
    'X-Device-Model: ' . ($deviceInfo['model'] ?? 'unknown'),
    'X-Device-Brand: ' . ($deviceInfo['brand'] ?? 'unknown'),
    'X-Device-Manufacturer: ' . ($deviceInfo['manufacturer'] ?? 'unknown'),
    'X-Android-Version: ' . ($deviceInfo['android_version'] ?? 'unknown')
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $check_ip_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 1);
curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);  // 设置 User-Agent
$response = curl_exec($ch);
curl_close($ch); 