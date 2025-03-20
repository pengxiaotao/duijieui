<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

// 初始化错误和成功消息变量
$error = '';
$success = '';

// 获取邀请码
$invite_code = isset($_GET['invite_code']) ? trim($_GET['invite_code']) : '';
$inviter_id = null;

if (!empty($invite_code)) {
    // 查找邀请人
    $stmt = $conn->prepare("SELECT id FROM users WHERE invite_code = ?");
    $stmt->bind_param("s", $invite_code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $inviter = $result->fetch_assoc();
        $inviter_id = $inviter['id'];
    }
    $stmt->close();
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = trim($_POST['email']);
    $invite_code = trim($_POST['invite_code']); // 从表单获取邀请码
    
    // 验证表单数据
    $errors = [];
    
    // 验证用户名
    if (empty($username)) {
        $errors[] = "用户名不能为空";
    } elseif (strlen($username) < 3) {
        $errors[] = "用户名至少需要3个字符";
    } elseif (strlen($username) > 20) {
        $errors[] = "用户名不能超过20个字符";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "用户名只能包含字母、数字和下划线";
    }
    
    // 验证密码
    if (empty($password)) {
        $errors[] = "密码不能为空";
    } elseif (strlen($password) < 6) {
        $errors[] = "密码至少需要6个字符";
    } elseif ($password !== $confirm_password) {
        $errors[] = "两次输入的密码不一致";
    }
    
    // 验证邮箱
    if (empty($email)) {
        $errors[] = "邮箱不能为空";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "邮箱格式不正确";
    }
    
    // 检查用户名是否已存在
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "用户名已被使用";
    }
    $stmt->close();
    
    // 检查邮箱是否已存在
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "邮箱已被使用";
    }
    $stmt->close();
    
    // 如果没有错误，创建新用户
    if (empty($errors)) {
        // 生成邀请码
        $new_invite_code = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        
        // 处理邀请码
        $inviter_id = null;
        $invite_code_to_use = '';
        
        // 优先使用URL中的邀请码
        if (!empty($_GET['invite_code'])) {
            $invite_code_to_use = $_GET['invite_code'];
        } 
        // 其次使用表单中的邀请码
        elseif (!empty($_POST['invite_code'])) {
            $invite_code_to_use = $_POST['invite_code'];
        }
        
        // 如果存在邀请码，查找邀请人
        if (!empty($invite_code_to_use)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE invite_code = ?");
            $stmt->bind_param("s", $invite_code_to_use);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $inviter = $result->fetch_assoc();
                $inviter_id = $inviter['id'];
            }
            $stmt->close();
        }
        
        // 插入新用户
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, invite_code, invited_by) VALUES (?, ?, ?, ?, ?)");
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bind_param("ssssi", $username, $hashed_password, $email, $new_invite_code, $inviter_id);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            // 设置会话
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            
            // 记录注册日志
            log_message("新用户注册成功，用户名: $username，邮箱: $email，IP: " . $_SERVER['REMOTE_ADDR']);
            
            // 重定向到首页
            header('Location: index.php');
            exit;
        } else {
            $errors[] = "注册失败，请稍后重试";
            log_message("用户注册失败，错误信息: " . $stmt->error);
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .register-form {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .captcha-img {
            cursor: pointer;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="register-form">
            <h2 class="text-center mb-4">用户注册</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">用户名</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">邮箱</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">密码</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">确认密码</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="invite_code" class="form-label">邀请码<?php echo !empty($invite_code) ? '' : '（选填）'; ?></label>
                        <input type="text" class="form-control" id="invite_code" name="invite_code" value="<?php echo htmlspecialchars($invite_code); ?>" <?php echo !empty($invite_code) ? 'readonly' : ''; ?>>
                        <?php if (!empty($invite_code)): ?>
                            <small class="text-muted">您正在使用邀请码注册</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">注册</button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <p>已有账号？<a href="login.php">立即登录</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>