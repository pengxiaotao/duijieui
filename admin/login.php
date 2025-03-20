<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 检查数据库连接
    if ($conn->connect_error) {
        $error = '数据库连接失败';
    } else {
        // 准备 SQL 查询
        $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username = ?");
        if ($stmt === false) {
            $error = '查询准备失败: ' . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            if (!$stmt->execute()) {
                $error = '查询执行失败: ' . $stmt->error;
            } else {
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $row = $result->fetch_assoc();
                    // 验证密码
                    if (password_verify($password, $row['password'])) {
                        // 登录成功，设置会话变量
                        $_SESSION['admin_id'] = $row['id'];
                        // 重定向到管理面板首页
                        header('Location: index.php');
                        exit;
                    } else {
                        $error = '用户名或密码错误';
                    }
                } else {
                    $error = '用户名或密码错误';
                }
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 50px;
        }

        form {
            margin-top: 20px;
        }

        input[type="text"],
        input[type="password"] {
            padding: 8px;
            width: 200px;
            margin-bottom: 10px;
        }

        input[type="submit"] {
            padding: 8px 20px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #0056b3;
        }

        .error {
            color: red;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <h1>管理员登录</h1>
    <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="post">
        <label for="username">用户名：</label><br>
        <input type="text" id="username" name="username" required><br>
        <label for="password">密码：</label><br>
        <input type="password" id="password" name="password" required><br>
        <input type="submit" value="登录">
    </form>
</body>

</html>