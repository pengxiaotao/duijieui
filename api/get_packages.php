<?php
// 设置响应头为 JSON 格式
header('Content-Type: application/json');

// 引入数据库连接文件，假设文件名为 db.php
require_once '../includes/db.php';

// 准备 SQL 查询语句
$stmt = $conn->prepare("SELECT id, name, price, description, bonus_amount FROM packages");

// 执行查询
if ($stmt->execute()) {
    // 获取查询结果
    $result = $stmt->get_result();

    // 初始化一个空数组来存储套餐包信息
    $packagePlans = [];

    // 遍历结果集，将每行数据添加到数组中
    while ($row = $result->fetch_assoc()) {
        $packagePlans[] = $row;
    }

    // 输出 JSON 格式的响应数据
    echo json_encode($packagePlans);
} else {
    // 若查询执行失败，输出错误信息
    echo json_encode(['error' => '获取套餐包信息失败']);
}

// 关闭语句
$stmt->close();

// 关闭数据库连接
$conn->close();
?>