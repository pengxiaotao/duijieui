<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 检查数据库配置
if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
    die("数据库配置不完整，请检查 config.php 文件");
}

// 创建数据库连接
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// 检查连接错误
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 设置字符集
if (!$conn->set_charset("utf8mb4")) {
    die("设置字符集失败: " . $conn->error);
}

// 设置错误报告模式
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);