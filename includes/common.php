<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 定义常量
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('TEMP_PATH', ROOT_PATH . '/temp');

// 确保上传目录存在
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// 确保临时目录存在
if (!file_exists(TEMP_PATH)) {
    mkdir(TEMP_PATH, 0755, true);
}

// 加载配置文件
require_once __DIR__ . '/config.php';

// 加载数据库连接
require_once __DIR__ . '/db.php';

// 检查管理员登录状态
function check_admin_login() {
    session_start();
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

// 获取管理员信息
function get_admin_info($admin_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// 记录管理员操作日志
function log_admin_action($admin_id, $action, $details = '') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $admin_id, $action, $details);
    $stmt->execute();
}

// 获取服务器状态
function get_server_status($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $http_code === 200;
}

// 格式化金额
function format_money($amount) {
    return number_format($amount, 2, '.', ',');
}

// 生成随机字符串
function generate_random_string($length = 32) {
    return bin2hex(random_bytes($length));
}

// 检查文件类型
function check_file_type($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    return in_array($mime_type, $allowed_types);
}

// 获取文件扩展名
function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// 清理临时文件
function clean_temp_files() {
    $files = glob(TEMP_PATH . '/*');
    $now = time();
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= 3600) { // 1小时前的文件
                unlink($file);
            }
        }
    }
}

// 获取用户IP地址
function get_client_ip() {
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// 检查是否是AJAX请求
function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// 返回JSON响应
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// 检查目录权限
function check_directory_permissions() {
    $directories = [
        UPLOAD_PATH,
        TEMP_PATH,
        ROOT_PATH . '/logs'
    ];
    
    foreach ($directories as $dir) {
        if (!is_writable($dir)) {
            return false;
        }
    }
    return true;
}

// 获取系统信息
function get_system_info() {
    $info = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'mysql_version' => mysqli_get_server_info($GLOBALS['conn']),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit')
    ];
    return $info;
} 