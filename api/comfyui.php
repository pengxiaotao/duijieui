<?php
require_once('../includes/common.php');
require_once('../includes/auth.php');

// 检查用户是否登录
if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['error' => '请先登录']));
}

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// 获取服务器ID
$server_id = isset($_GET['server_id']) ? intval($_GET['server_id']) : 0;
if (!$server_id) {
    http_response_code(400);
    die(json_encode(['error' => '缺少服务器ID']));
}

try {
    require_once('../includes/ComfyUIServer.php');
    $server = new ComfyUIServer($db, $server_id);
    
    switch ($method) {
        case 'GET':
            // 获取队列状态
            if (isset($_GET['action']) && $_GET['action'] === 'queue') {
                $response = $server->getQueueStatus();
                echo json_encode($response);
                break;
            }
            
            // 获取历史记录
            if (isset($_GET['action']) && $_GET['action'] === 'history') {
                $response = $server->getHistory();
                echo json_encode($response);
                break;
            }
            
            // 获取图片
            if (isset($_GET['action']) && $_GET['action'] === 'image' && isset($_GET['filename'])) {
                $image_data = $server->getImage($_GET['filename']);
                header('Content-Type: image/png');
                echo $image_data;
                break;
            }
            
            http_response_code(400);
            die(json_encode(['error' => '无效的请求']));
            break;
            
        case 'POST':
            // 提交工作流
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                http_response_code(400);
                die(json_encode(['error' => '无效的请求数据']));
            }
            
            // 记录使用情况
            $server->logUsage($_SESSION['user_id']);
            
            // 提交工作流
            $response = $server->submitWorkflow($input);
            echo json_encode($response);
            break;
            
        default:
            http_response_code(405);
            die(json_encode(['error' => '不支持的请求方法']));
    }
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => $e->getMessage()]));
} 