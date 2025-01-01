<?php

class IpManager {
    private $conn;
    private $allow_hours = 6; // 默认6小时内允许访问
    private $ip_db_file = 'qqwry.dat';
    private $fd;
    private $total_records;
    private $first_record;
    private $last_record;

    public function __construct() {
        require_once 'config.php';
        global $conn;
        $this->conn = $conn;
        
        if (!$this->conn) {
            throw new Exception("数据库连接失败");
        }
        
        // 设置错误处理
        error_reporting(E_ALL & ~E_NOTICE);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
    }

    public function recordIpAccess($ip) {
        try {
            $ip = mysqli_real_escape_string($this->conn, $ip);
            $now = date('Y-m-d H:i:s');
            
            // 获取位置信息并记录日志
            $location = $this->getIpLocation($ip);
            error_log("IP: $ip, Location: $location");
            
            // 获取设备信息并记录日志
            $deviceInfo = $this->getDeviceInfo($_SERVER['HTTP_USER_AGENT'] ?? '');
            error_log("IP: $ip, Device: $deviceInfo, UserAgent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'none'));
            
            $location = mysqli_real_escape_string($this->conn, $location);
            $deviceInfo = mysqli_real_escape_string($this->conn, $deviceInfo);
            
            $sql = "INSERT INTO ip_access (ip, first_access, last_access, access_count, location, device_info) 
                    VALUES ('$ip', '$now', '$now', 1, '$location', '$deviceInfo')
                    ON DUPLICATE KEY UPDATE 
                        last_access = '$now',
                        access_count = access_count + 1,
                        location = '$location',
                        device_info = '$deviceInfo'";
            
            $result = mysqli_query($this->conn, $sql);
            if (!$result) {
                error_log("IP记录失败: " . mysqli_error($this->conn));
                return false;
            }
            return true;
        } catch (Exception $e) {
            error_log("IP记录异常: " . $e->getMessage());
            return false;
        }
    }

    public function isIpAllowed($ip) {
        $ip = mysqli_real_escape_string($this->conn, $ip);
        $sql = "SELECT 1 FROM ip_access 
                WHERE ip = '$ip' 
                AND last_access >= DATE_SUB(NOW(), INTERVAL {$this->allow_hours} HOUR) 
                LIMIT 1";
        $result = mysqli_query($this->conn, $sql);
        
        return mysqli_num_rows($result) > 0;
    }

    public function getIpList($limit = 1000, $offset = 0, $search = '', $sort_field = 'last_access', $sort_order = 'desc') {
        // 验证排序字段
        $allowed_fields = ['ip', 'first_access', 'last_access', 'access_count'];
        if (!in_array($sort_field, $allowed_fields)) {
            $sort_field = 'last_access';
        }
        
        // 验证排序方向
        $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
        
        $where = '';
        if ($search) {
            $search = mysqli_real_escape_string($this->conn, $search);
            $where = "WHERE ip LIKE '%$search%'";
        }
        
        // 修改SQL查询，包含location和device_info字段
        $sql = "SELECT ip, first_access, last_access, access_count, location, device_info 
                FROM ip_access 
                $where
                ORDER BY $sort_field $sort_order 
                LIMIT $limit OFFSET $offset";
        
        $result = mysqli_query($this->conn, $sql);
        if (!$result) {
            error_log("获取IP列表失败: " . mysqli_error($this->conn));
            return [];
        }
        
        $list = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // 确保所有字段都存在
            $row['location'] = $row['location'] ?? '未知位置';
            $row['device_info'] = $row['device_info'] ?? '未知设备';
            $list[] = $row;
        }
        
        mysqli_free_result($result);
        return $list;
    }

    public function deleteIp($ip) {
        $ip = mysqli_real_escape_string($this->conn, $ip);
        $sql = "DELETE FROM ip_access WHERE ip = '$ip'";
        return mysqli_query($this->conn, $sql);
    }

    public function cleanOldRecords($hours = 72) {
        $hours = (int)$hours;
        
        // 清理IP访问记录
        $sql = "DELETE FROM ip_access 
                WHERE last_access < DATE_SUB(NOW(), INTERVAL $hours HOUR)";
        $result1 = mysqli_query($this->conn, $sql);
        
        // 清理超过30天的位置缓存
        $result2 = $this->cleanLocationCache(30);
        
        return $result1 && $result2;
    }

    public function getTotalIps($search = '') {
        $where = '';
        if ($search) {
            $search = mysqli_real_escape_string($this->conn, $search);
            $where = "WHERE ip LIKE '%$search%'";
        }
        
        $sql = "SELECT COUNT(*) as total FROM ip_access $where";
        $result = mysqli_query($this->conn, $sql);
        $row = mysqli_fetch_assoc($result);
        return $row['total'];
    }

    // 获取指定日期的IP数量
    public function getIpCountByDate($date) {
        $date = mysqli_real_escape_string($this->conn, $date);
        $sql = "SELECT COUNT(DISTINCT ip) as count 
                FROM ip_access 
                WHERE DATE(last_access) = '$date'";
        $result = mysqli_query($this->conn, $sql);
        $row = mysqli_fetch_assoc($result);
        return $row['count'];
    }

    // 获取最近三天的IP统计
    public function getRecentDaysStats() {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 day'));

        return [
            'today' => $this->getIpCountByDate($today),
            'yesterday' => $this->getIpCountByDate($yesterday),
            'dayBeforeYesterday' => $this->getIpCountByDate($dayBeforeYesterday)
        ];
    }

    // 获取IP地理位置（带缓存）
    private function getIpLocation($ip) {
        // 先检查缓存
        $cached = $this->getLocationFromCache($ip);
        if ($cached !== false) {
            return $cached;
        }

        // 尝试使用本地IP库
        $location = $this->getLocationFromLocalDb($ip);
        if ($location !== false) {
            $this->saveLocationToCache($ip, $location);
            return $location;
        }

        // 本地库查询失败，使用在线API
        $location = $this->fetchLocationFromApi($ip);
        $this->saveLocationToCache($ip, $location);
        return $location;
    }

    // 从缓存中获取位置信息
    private function getLocationFromCache($ip) {
        $ip = mysqli_real_escape_string($this->conn, $ip);
        $sql = "SELECT location FROM ip_location_cache 
                WHERE ip = '$ip' 
                AND update_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                LIMIT 1";
        
        $result = mysqli_query($this->conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            return $row['location'];
        }
        return false;
    }

    // 从API获取位置信息
    private function fetchLocationFromApi($ip) {
        // 使用 ip-api.com 的完整字段
        $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city,isp&lang=zh-CN";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5秒超时
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                $location = [];
                if (!empty($data['country'])) $location[] = $data['country'];
                if (!empty($data['regionName'])) $location[] = $data['regionName'];
                if (!empty($data['city'])) $location[] = $data['city'];
                if (!empty($data['isp'])) $location[] = $data['isp'];
                
                return implode(' ', $location);
            }
        }
        
        // 如果API调用失败，尝试使用备用API
        $backup_url = "https://api.ip.sb/geoip/{$ip}";
        $response = @file_get_contents($backup_url);
        if ($response) {
            $data = json_decode($response, true);
            if ($data) {
                $location = [];
                if (!empty($data['country'])) $location[] = $data['country'];
                if (!empty($data['region'])) $location[] = $data['region'];
                if (!empty($data['city'])) $location[] = $data['city'];
                if (!empty($data['organization'])) $location[] = $data['organization'];
                
                return implode(' ', $location);
            }
        }

        return '未知位置';
    }

    // 保存位置信息到缓存
    private function saveLocationToCache($ip, $location) {
        $ip = mysqli_real_escape_string($this->conn, $ip);
        $location = mysqli_real_escape_string($this->conn, $location);
        $sql = "INSERT INTO ip_location_cache (ip, location, update_time) 
                VALUES ('$ip', '$location', NOW())
                ON DUPLICATE KEY UPDATE 
                    location = VALUES(location),
                    update_time = NOW()";
        
        mysqli_query($this->conn, $sql);
    }

    // 清理旧的缓存记录
    public function cleanLocationCache($days = 30) {
        $sql = "DELETE FROM ip_location_cache 
                WHERE update_time < DATE_SUB(NOW(), INTERVAL $days DAY)";
        return mysqli_query($this->conn, $sql);
    }

    // 获取设备信息
    private function getDeviceInfo($userAgent) {
        if (empty($userAgent)) {
            return '未知设备';
        }

        $device = '';
        $os = '';
        $browser = '';

        // 检测移动设备
        if (preg_match('/(iPhone|iPad|iPod)/i', $userAgent)) {
            $device = preg_match('/iPad/i', $userAgent) ? 'iPad' : 'iPhone';
        } elseif (preg_match('/Android/i', $userAgent)) {
            if (preg_match('/Mobile/i', $userAgent)) {
                $device = 'Android手机';
            } else {
                $device = 'Android平板';
            }
        } elseif (preg_match('/(Windows Phone|Windows Mobile)/i', $userAgent)) {
            $device = 'Windows手机';
        } else {
            // 检测桌面操作系统
            if (preg_match('/Windows NT/i', $userAgent)) {
                $device = 'Windows';
                if (preg_match('/Windows NT 10.0/i', $userAgent)) $device .= ' 10';
                elseif (preg_match('/Windows NT 6.3/i', $userAgent)) $device .= ' 8.1';
                elseif (preg_match('/Windows NT 6.2/i', $userAgent)) $device .= ' 8';
                elseif (preg_match('/Windows NT 6.1/i', $userAgent)) $device .= ' 7';
            } elseif (preg_match('/Macintosh/i', $userAgent)) {
                $device = 'MacOS';
            } elseif (preg_match('/Linux/i', $userAgent)) {
                $device = 'Linux';
            }
        }

        // 检测浏览器
        if (preg_match('/MicroMessenger/i', $userAgent)) {
            $browser = '微信浏览器';
        } elseif (preg_match('/QQ/i', $userAgent)) {
            $browser = 'QQ浏览器';
        } elseif (preg_match('/Alipay/i', $userAgent)) {
            $browser = '支付宝';
        } elseif (preg_match('/DingTalk/i', $userAgent)) {
            $browser = '钉钉';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge浏览器';
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome浏览器';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox浏览器';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari浏览器';
        } elseif (preg_match('/MSIE|Trident/i', $userAgent)) {
            $browser = 'IE浏览器';
        }

        // 检测特殊客户端
        if (preg_match('/curl/i', $userAgent)) {
            return 'CURL请求';
        } elseif (preg_match('/Postman/i', $userAgent)) {
            return 'Postman请求';
        } elseif (preg_match('/Python/i', $userAgent)) {
            return 'Python脚本';
        } elseif (preg_match('/wget/i', $userAgent)) {
            return 'Wget请求';
        } elseif (empty($userAgent)) {
            return '直接请求';
        }

        // 组合设备和浏览器信息
        $info = [];
        if ($device) $info[] = $device;
        if ($browser) $info[] = $browser;

        return $info ? implode(' / ', $info) : '未知设备';
    }

    // 从本地IP库获取位置信息
    private function getLocationFromLocalDb($ip) {
        if (!file_exists($this->ip_db_file)) {
            error_log("IP库文件不存在: " . $this->ip_db_file);
            return false;
        }

        try {
            if (!$this->fd) {
                $this->fd = fopen($this->ip_db_file, 'rb');
                if ($this->fd === false) {
                    error_log("无法打开IP库文件");
                    return false;
                }
                
                // 读取文件头
                $buf = fread($this->fd, 8);
                if (strlen($buf) < 8) {
                    error_log("IP库文件头读取失败");
                    return false;
                }
                
                $this->first_record = unpack('V', substr($buf, 0, 4))[1];
                $this->last_record = unpack('V', substr($buf, 4, 4))[1];
                $this->total_records = ($this->last_record - $this->first_record) / 7 + 1;
                
                error_log("IP库加载成功: 总记录数 " . $this->total_records);
            }

            $ip_long = $this->ip2long($ip);
            $left = 0;
            $right = $this->total_records - 1;

            // 二分查找
            while ($left <= $right) {
                $mid = floor(($left + $right) / 2);
                $record = $this->get_record($mid);
                
                if ($record === false) {
                    error_log("无法读取记录: index=" . $mid);
                    return false;
                }
                
                if ($record['begin_ip'] <= $ip_long && $ip_long <= $record['end_ip']) {
                    $location = [];
                    if (!empty($record['country'])) {
                        $location[] = $record['country'];
                    }
                    if (!empty($record['area']) && $record['area'] !== ' CZ88.NET') {
                        $location[] = $record['area'];
                    }
                    
                    $result = implode(' ', $location);
                    error_log("IP: $ip 位置解析结果: $result");
                    $location = implode(' ', $location);
                    error_log("原始位置信息: " . bin2hex($location));
                    error_log("转换后位置信息: " . $location);
                    return $location ?: '未知地区';
                }
                
                if ($record['begin_ip'] > $ip_long) {
                    $right = $mid - 1;
                } else {
                    $left = $mid + 1;
                }
            }
            
            error_log("未找到IP: $ip 的位置信息");
            return false;
        } catch (Exception $e) {
            error_log("本地IP库查询错误: " . $e->getMessage());
            return false;
        }
    }

    // IP地址转长整型
    private function ip2long($ip) {
        $ip_parts = explode('.', $ip);
        if (count($ip_parts) !== 4) {
            error_log("无效的IP地址格式: " . $ip);
            return 0;
        }
        
        $ip_long = 0;
        for($i = 0; $i < 4; $i++) {
            $ip_long += intval($ip_parts[$i]) * pow(256, 3-$i);
        }
        return $ip_long;
    }

    // 获取记录
    private function get_record($index) {
        fseek($this->fd, $this->first_record + $index * 7);
        $buf = fread($this->fd, 7);
        $begin_ip = unpack('V', substr($buf, 0, 4))[1];
        $offset = unpack('V', substr($buf, 4, 3).chr(0))[1];
        
        fseek($this->fd, $offset);
        $buf = fread($this->fd, 4);
        $end_ip = unpack('V', $buf)[1];
        
        // 读取地区信息
        $country = $this->read_string();
        $area = $this->read_string();
        
        return [
            'begin_ip' => $begin_ip,
            'end_ip' => $end_ip,
            'country' => $country,
            'area' => $area
        ];
    }

    // 读取字符串
    private function read_string() {
        $str = '';
        $chr = fread($this->fd, 1);
        $byte = ord($chr);
        
        // 检查是否为重定向
        if ($byte == 0x01 || $byte == 0x02) {
            $p = fread($this->fd, 3);
            if ($byte == 0x01) {
                fseek($this->fd, unpack('V', $p.chr(0))[1]);
            } else {
                fseek($this->fd, unpack('V', $p.chr(0))[1]);
            }
            $chr = fread($this->fd, 1);
            $byte = ord($chr);
        }
        
        // 读取字符串直到遇到0x00
        while ($byte != 0x00) {
            $str .= $chr;
            $chr = fread($this->fd, 1);
            $byte = ord($chr);
        }
        
        // 添加详细的调试信息
        error_log("原始字符串(hex): " . bin2hex($str));
        
        try {
            // 使用 mb_convert_encoding 替代 iconv
            $converted = mb_convert_encoding($str, 'UTF-8', 'GBK');
            if ($converted !== false) {
                $result = trim($converted);
                error_log("转换后字符串: " . $result);
                return $result;
            }
            
            // 如果转换失败，尝试直接使用 GBK 解码
            $converted = iconv('GBK', 'UTF-8//TRANSLIT', $str);
            if ($converted !== false) {
                $result = trim($converted);
                error_log("iconv转换后字符串: " . $result);
                return $result;
            }
        } catch (Exception $e) {
            error_log("编码转换错误: " . $e->getMessage());
        }
        
        return '未知地区';
    }

    // 析构函数中关闭文件句柄
    public function __destruct() {
        if ($this->fd) {
            fclose($this->fd);
        }
    }

    public function updateIpDatabase() {
        $url = 'https://raw.gitmirror.com/adysec/IP_database/main/qqwry/qqwry.dat';
        $newFile = 'qqwry.dat.new';
        $currentFile = $this->ip_db_file;

        try {
            // 下载新文件
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5分钟超时
            
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || empty($data)) {
                throw new Exception("下载IP库失败，HTTP状态码：" . $httpCode);
            }

            // 保存新文件
            if (file_put_contents($newFile, $data) === false) {
                throw new Exception("保存新IP库文件失败");
            }

            // 验证新文件
            if (!$this->verifyIpDatabase($newFile)) {
                unlink($newFile);
                throw new Exception("新IP库文件验证失败");
            }

            // 备份当前文件（如果存在）
            if (file_exists($currentFile)) {
                rename($currentFile, $currentFile . '.bak');
            }

            // 启用新文件
            rename($newFile, $currentFile);

            // 删除备份（可选）
            if (file_exists($currentFile . '.bak')) {
                unlink($currentFile . '.bak');
            }

            return true;
        } catch (Exception $e) {
            error_log("更新IP库失败: " . $e->getMessage());
            return false;
        }
    }

    // 验证IP库文件
    private function verifyIpDatabase($file) {
        if (!file_exists($file)) {
            return false;
        }

        $fp = fopen($file, 'rb');
        if (!$fp) {
            return false;
        }

        // 读取文件头
        $buf = fread($fp, 8);
        $first = unpack('V', substr($buf, 0, 4))[1];
        $last = unpack('V', substr($buf, 4, 4))[1];
        
        fclose($fp);

        // 简单验证文件格式
        return $first > 0 && $last > $first;
    }
} 