<?php
require_once 'config.php';
require_once 'IpManager.php';

// 检查管理员登录状态
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$ipManager = new IpManager();

// 处理删除操作
if (isset($_POST['delete_ip'])) {
    $ipManager->deleteIp($_POST['delete_ip']);
}

// 处理清理操作
if (isset($_POST['clean_hours'])) {
    $ipManager->cleanOldRecords((int)$_POST['clean_hours']);
}

// 获取每页显示数量（从cookie中获取，默认为50）
$page_sizes = [10, 20, 50, 100, 200];
$default_page_size = 50;

if (isset($_POST['page_size'])) {
    $limit = (int)$_POST['page_size'];
    setcookie('page_size', $limit, time() + (30 * 24 * 60 * 60));
} elseif (isset($_COOKIE['page_size'])) {
    $limit = (int)$_COOKIE['page_size'];
} else {
    $limit = $default_page_size;
}

// 分页参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_field = isset($_GET['search_field']) ? $_GET['search_field'] : 'all';

// 获取排序参数
$sort_field = isset($_GET['sort']) ? $_GET['sort'] : 'last_access';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// 获取IP列表
$ipList = $ipManager->getIpList($limit, $offset, $search, $sort_field, $sort_order, $search_field);
$totalIps = $ipManager->getTotalIps($search, $search_field);
$totalPages = max(1, ceil($totalIps / $limit));

// 确保页码在有效范围内
if ($page > $totalPages) {
    $page = $totalPages;
} elseif ($page < 1) {
    $page = 1;
}

// 计算显示的页码范围
$page_range = 5; // 显示当前页前后5页
$start_page = max(1, $page - $page_range);
$end_page = min($totalPages, $page + $page_range);

// 获取最近三天的统计
$recentStats = $ipManager->getRecentDaysStats();

// 生成排序URL的函数
function getSortUrl($field, $currentSort, $currentOrder, $search) {
    $order = ($currentSort === $field && $currentOrder === 'asc') ? 'desc' : 'asc';
    return "?sort={$field}&order={$order}&search=" . urlencode($search);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>IP验证管理系统</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/img/favicon.png" type="image/png">
    <link href="https://lf26-cdn-tos.bytecdntp.com/cdn/expire-1-M/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://lf3-cdn-tos.bytecdntp.com/cdn/expire-1-M/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="d-flex align-items-center">
                <h2>
                    <a href="ip_manage.php" class="text-decoration-none">
                        <i class="fas fa-shield-alt me-2"></i>IP验证管理系统
                    </a>
                </h2>
                <button onclick="window.location.reload()" class="btn btn-primary ms-3" title="刷新页面">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div>
                <a href="change_password.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-key me-1"></i>修改密码
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>退出登录
                </a>
            </div>
        </div>

        <!-- 在统计卡片前添加更新消息提示 -->
        <?php if (isset($updateMessage)): ?>
            <div class="alert alert-<?php echo $updateMessage['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $updateMessage['text']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- 统计卡片 -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <h4><i class="fas fa-users me-2"></i>总访问IP数</h4>
                    <div class="number"><?php echo $totalIps; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-left: 4px solid var(--success-color);">
                    <a href="?search=<?php echo date('Y-m-d'); ?>&search_field=date" class="text-decoration-none">
                        <h4><i class="fas fa-calendar-day me-2"></i>今日IP数</h4>
                        <div class="number" style="color: var(--success-color);">
                            <?php echo $recentStats['today']; ?>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-left: 4px solid var(--warning-color);">
                    <a href="?search=<?php echo date('Y-m-d', strtotime('-1 day')); ?>&search_field=date" class="text-decoration-none">
                        <h4><i class="fas fa-calendar-minus me-2"></i>昨日IP数</h4>
                        <div class="number" style="color: var(--warning-color);">
                            <?php echo $recentStats['yesterday']; ?>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-left: 4px solid var(--secondary-color);">
                    <a href="?search=<?php echo date('Y-m-d', strtotime('-2 day')); ?>&search_field=date" class="text-decoration-none">
                        <h4><i class="fas fa-calendar-week me-2"></i>前日IP数</h4>
                        <div class="number" style="color: var(--secondary-color);">
                            <?php echo $recentStats['dayBeforeYesterday']; ?>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- 搜索和清理表单 -->
        <div class="row g-3 align-items-center">
            <div class="col-auto" style="width: 400px;">
                <form method="GET" class="search-box">
                    <div class="input-group">
                        <select name="search_field" class="form-select" style="max-width: 120px;">
                            <option value="all" <?php echo $search_field == 'all' ? 'selected' : ''; ?>>全部</option>
                            <option value="ip" <?php echo $search_field == 'ip' ? 'selected' : ''; ?>>IP地址</option>
                            <option value="location" <?php echo $search_field == 'location' ? 'selected' : ''; ?>>地理位置</option>
                            <option value="device_info" <?php echo $search_field == 'device_info' ? 'selected' : ''; ?>>设备信息</option>
                            <option value="date" <?php echo $search_field == 'date' ? 'selected' : ''; ?>>访问日期</option>
                        </select>
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="输入搜索关键词...">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div class="col-auto">
                <form method="POST" class="d-flex align-items-center">
                    <select name="page_size" class="form-select" onchange="this.form.submit()" style="width: 80px;">
                        <?php foreach ($page_sizes as $size): ?>
                            <option value="<?php echo $size; ?>" <?php echo $limit == $size ? 'selected' : ''; ?>>
                                <?php echo $size; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="col">
                <form method="POST" class="clean-form d-flex" onsubmit="return confirm('确定要清理数据吗？');">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-clock"></i></span>
                        <input type="number" class="form-control" name="clean_hours" value="72" style="width: 70px;">
                        <span class="input-group-text">小时未访问的记录</span>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-broom"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- IP列表表格 -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th onclick="window.location.href='<?php echo getSortUrl('ip', $sort_field, $sort_order, $search); ?>'">
                            IP地址
                            <?php if ($sort_field === 'ip'): ?>
                                <span class="sort-icon <?php echo $sort_order; ?>"></span>
                            <?php endif; ?>
                        </th>
                        <th>地理位置</th>
                        <th>设备信息</th>
                        <th onclick="window.location.href='<?php echo getSortUrl('first_access', $sort_field, $sort_order, $search); ?>'">
                            首次访问时间
                            <?php if ($sort_field === 'first_access'): ?>
                                <span class="sort-icon <?php echo $sort_order; ?>"></span>
                            <?php endif; ?>
                        </th>
                        <th onclick="window.location.href='<?php echo getSortUrl('last_access', $sort_field, $sort_order, $search); ?>'">
                            最后访问时间
                            <?php if ($sort_field === 'last_access'): ?>
                                <span class="sort-icon <?php echo $sort_order; ?>"></span>
                            <?php endif; ?>
                        </th>
                        <th onclick="window.location.href='<?php echo getSortUrl('access_count', $sort_field, $sort_order, $search); ?>'">
                            访问次数
                            <?php if ($sort_field === 'access_count'): ?>
                                <span class="sort-icon <?php echo $sort_order; ?>"></span>
                            <?php endif; ?>
                        </th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ipList as $ip): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ip['ip']); ?></td>
                        <td><?php echo htmlspecialchars($ip['location'] ?? '未知位置'); ?></td>
                        <td><?php echo htmlspecialchars($ip['device_info'] ?? '未知设备'); ?></td>
                        <td><?php echo $ip['first_access']; ?></td>
                        <td><?php echo $ip['last_access']; ?></td>
                        <td>
                            <span class="badge bg-primary rounded-pill">
                                <?php echo $ip['access_count']; ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="d-inline" onsubmit="return confirm('确定要删除这个IP吗？');">
                                <input type="hidden" name="delete_ip" value="<?php echo htmlspecialchars($ip['ip']); ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <!-- 首页 -->
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                </li>
                
                <!-- 上一页 -->
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                </li>

                <!-- 页码 -->
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <!-- 下一页 -->
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </li>

                <!-- 末页 -->
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </li>
            </ul>
            
            <!-- 显示页码信息 -->
            <div class="text-center mt-2">
                <span class="text-muted">
                    共 <?php echo $totalIps; ?> 条记录，
                    第 <?php echo $page; ?>/<?php echo $totalPages; ?> 页
                </span>
            </div>
        </nav>
        <?php endif; ?>

        <!-- 版权信息 -->
        <footer class="mt-5 text-center text-muted">
            <hr>
            <p>
                <small>
                    © <?php echo date('Y'); ?> IP验证管理系统 | 
                    <a href="https://github.com/vbskycn/ipapi" target="_blank" class="text-decoration-none">
                        <i class="fab fa-github"></i> GitHub
                    </a>
                    | 
                    <a href="https://hub.docker.com/repository/docker/zhoujie218/ipapi/" target="_blank" class="text-decoration-none">
                        <i class="fab fa-docker"></i> Docker Hub
                    </a>
                </small>
            </p>
        </footer>
    </div>

    <script src="https://lf26-cdn-tos.bytecdntp.com/cdn/expire-1-M/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html> 