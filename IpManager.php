<?php

class IpManager {
    private $conn;
    private $allow_hours = 6; // 默认6小时内允许访问
    private $ip_db_file = 'ipdata/qqwry.dat';
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
            
            // 获取位置信息
            $location = $this->getIpLocation($ip);
            
            // 获取设备信息
            $deviceInfo = $this->getDeviceInfo($_SERVER['HTTP_USER_AGENT'] ?? '');
            
            // 记录调试信息
            error_log("Recording IP access - IP: $ip, Location: $location, Device: $deviceInfo");
            
            // 确保数据安全
            $location = mysqli_real_escape_string($this->conn, $location);
            $deviceInfo = mysqli_real_escape_string($this->conn, $deviceInfo);
            
            // 使用预处理语句来防止SQL注入
            $sql = "INSERT INTO ip_access (ip, first_access, last_access, access_count, location, device_info) 
                    VALUES (?, ?, ?, 1, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        last_access = VALUES(last_access),
                        access_count = access_count + 1,
                        location = VALUES(location),
                        device_info = VALUES(device_info)";
                    
            $stmt = mysqli_prepare($this->conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssss', $ip, $now, $now, $location, $deviceInfo);
                $result = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                
                if ($result) {
                    error_log("Successfully recorded IP: $ip");
                    return true;
                } else {
                    error_log("Failed to record IP: " . mysqli_error($this->conn));
                    return false;
                }
            } else {
                error_log("Failed to prepare statement: " . mysqli_error($this->conn));
                return false;
            }
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
        
        // 只清理IP访问记录
        $sql = "DELETE FROM ip_access 
                WHERE last_access < DATE_SUB(NOW(), INTERVAL $hours HOUR)";
        return mysqli_query($this->conn, $sql);
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

    // 获取IP地理位置
    private function getIpLocation($ip) {
        // 直接使用本地IP库
        $location = $this->getLocationFromLocalDb($ip);
        if ($location !== false) {
            return $location;
        }

        // 如果本地库查询失败，使用在线API作为备份
        $location = $this->fetchLocationFromApi($ip);
        return $location;
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
            }

            $ip_long = $this->ip2long($ip);
            $left = 0;
            $right = $this->total_records - 1;

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
                    if (!empty($record['area'])) {
                        $location[] = $record['area'];
                    }
                    
                    return implode(' ', $location) ?: '未知地区';
                }
                
                if ($record['begin_ip'] > $ip_long) {
                    $right = $mid - 1;
                } else {
                    $left = $mid + 1;
                }
            }
            
            return '未知地区';
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
        // 保存当前位置
        $current_position = ftell($this->fd);
        
        // 定位到索引位置
        $offset = $this->first_record + $index * 7;
        if (fseek($this->fd, $offset, SEEK_SET) === -1) {
            error_log("定位索引失败: offset=" . $offset);
            return false;
        }
        
        // 读取起始IP和偏移量
        $buf = fread($this->fd, 7);
        if (strlen($buf) < 7) {
            error_log("读取记录失败: 数据长度不足");
            return false;
        }
        
        $begin_ip = unpack('V', substr($buf, 0, 4))[1];
        $offset = unpack('V', substr($buf, 4, 3) . chr(0))[1];
        
        // 定位到偏移位置
        if (fseek($this->fd, $offset, SEEK_SET) === -1) {
            error_log("定位偏移失败: offset=" . $offset);
            return false;
        }
        
        // 读取结束IP
        $buf = fread($this->fd, 4);
        if (strlen($buf) < 4) {
            error_log("读取结束IP失败");
            return false;
        }
        $end_ip = unpack('V', $buf)[1];
        
        // 读取地区信息
        $mode = ord(fread($this->fd, 1));
        if ($mode === 1 || $mode === 2) { // 重定向模式
            $offset = unpack('V', fread($this->fd, 3) . chr(0))[1];
            // 保存当前位置
            $save_offset = ftell($this->fd);
            
            // 跳转到重定向位置
            fseek($this->fd, $offset, SEEK_SET);
            if ($mode === 2) {
                // 如果是双重定向，再读取一次
                $mode = ord(fread($this->fd, 1));
                if ($mode === 2) {
                    $offset = unpack('V', fread($this->fd, 3) . chr(0))[1];
                    fseek($this->fd, $offset, SEEK_SET);
                }
            }
        }
        
        // 读取地区信息
        $country = '';
        $area = '';
        
        while (($byte = ord(fread($this->fd, 1))) !== 0) {
            $country .= chr($byte);
        }
        
        // 读取区域信息
        $byte = ord(fread($this->fd, 1));
        if ($byte === 1 || $byte === 2) {
            fseek($this->fd, unpack('V', fread($this->fd, 3) . chr(0))[1]);
            while (($byte = ord(fread($this->fd, 1))) !== 0) {
                $area .= chr($byte);
            }
        } else {
            $area .= chr($byte);
            while (($byte = ord(fread($this->fd, 1))) !== 0) {
                $area .= chr($byte);
            }
        }
        
        // 转换编码
        $country = $this->convertEncoding($country);
        $area = $this->convertEncoding($area);
        
        // 恢复文件指针位置
        fseek($this->fd, $current_position, SEEK_SET);
        
        return [
            'begin_ip' => $begin_ip,
            'end_ip' => $end_ip,
            'country' => $country,
            'area' => $area !== ' CZ88.NET' ? $area : ''
        ];
    }

    // 新增编码转换方法
    private function convertEncoding($str) {
        if (empty($str)) {
            return '';
        }
        
        // 尝试直接转换
        $result = @iconv('GBK', 'UTF-8//IGNORE', $str);
        if ($result !== false && !empty($result)) {
            return $result;
        }
        
        // 如果转换失败，尝试检测编码
        $encoding = mb_detect_encoding($str, ['GBK', 'GB2312', 'UTF-8']);
        if ($encoding) {
            $result = mb_convert_encoding($str, 'UTF-8', $encoding);
            if (!empty($result)) {
                return $result;
            }
        }
        
        // 如果还是失败，返回原始的十六进制
        return '未知(' . bin2hex($str) . ')';
    }

    // 析构函数中关闭文件句柄
    public function __destruct() {
        if ($this->fd) {
            fclose($this->fd);
        }
    }

    public function updateIpDatabase() {
        $url = 'https://raw.gitmirror.com/adysec/IP_database/main/qqwry/qqwry.dat';
        $newFile = 'ipdata/qqwry.dat.new';
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

    // 添加新的辅助方法来清理和验证字符串
    private function cleanString($str) {
        if (empty($str)) {
            return '';
        }
        
        // 移除不可见字符
        $str = preg_replace('/[\x00-\x1F\x7F]/', '', $str);
        
        // 验证是否为有效的UTF-8字符串
        if (!mb_check_encoding($str, 'UTF-8')) {
            return '';
        }
        
        return trim($str);
    }
} 