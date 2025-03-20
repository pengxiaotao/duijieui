<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'db.php';

function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

function log_message($message) {
    $log_file = '../logs/system.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

function generate_captcha() {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $captcha = '';
    for ($i = 0; $i < 6; $i++) {
        $captcha .= $characters[rand(0, strlen($characters) - 1)];
    }
    $_SESSION['captcha'] = $captcha;

    $image = imagecreatetruecolor(120, 40);
    $bg_color = imagecolorallocate($image, 255, 255, 255);
    $text_color = imagecolorallocate($image, 0, 0, 0);
    imagefill($image, 0, 0, $bg_color);
    imagettftext($image, 20, 0, 20, 30, $text_color, '../public/fonts/arial.ttf', $captcha);

    header('Content-type: image/png');
    imagepng($image);
    imagedestroy($image);
    
}

// ... 其他代码 ...

if (!function_exists('generate_sign')) {
    function generate_sign($params, $private_key) {
        unset($params['sign']);
        unset($params['sign_type']);
        $filtered_params = array_filter($params, function ($value) {
            return is_scalar($value) && !empty($value);
        });
        ksort($filtered_params);
        $sign_string = http_build_query($filtered_params);
        openssl_sign($sign_string, $signature, $private_key, OPENSSL_ALGO_SHA256);
        $sign = base64_encode($signature);
        return $sign;
    }
}

if (!function_exists('verify_sign')) {
    function verify_sign($params, $public_key) {
        $received_sign = $params['sign'];
        unset($params['sign']);
        unset($params['sign_type']);
        $filtered_params = array_filter($params, function ($value) {
            return is_scalar($value) && !empty($value);
        });
        ksort($filtered_params);
        $sign_string = http_build_query($filtered_params);
        $decoded_sign = base64_decode($received_sign);
        $result = openssl_verify($sign_string, $decoded_sign, $public_key, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }
    
        function verifyToken($token) {
        global $conn;
        $stmt = $conn->prepare("SELECT id FROM users WHERE token =?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result();
    
        if ($stmt->num_rows > 0) {
            // 这里可能遗漏了 }
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            return false;
        }
    }
}

/**
 * 发送邮件
 * @param string $to 收件人邮箱
 * @param string $subject 邮件主题
 * @param string $message 邮件内容
 * @return bool 是否发送成功
 */
function send_email($to, $subject, $message) {
    global $conn;
    
    // 获取系统配置
    $stmt = $conn->prepare("SELECT * FROM settings WHERE `key` IN ('smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure', 'smtp_from')");
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['key']] = $row['value'];
    }
    $stmt->close();
    
    // 检查SMTP配置
    if (empty($settings['smtp_host']) || empty($settings['smtp_port']) || 
        empty($settings['smtp_user']) || empty($settings['smtp_pass']) || 
        empty($settings['smtp_from'])) {
        log_message("邮件发送失败：SMTP配置不完整");
        return false;
    }
    
    // 设置邮件头
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=utf-8',
        'From: ' . $settings['smtp_from'],
        'Reply-To: ' . $settings['smtp_from'],
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // 发送邮件
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // 服务器设置
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_user'];
        $mail->Password = $settings['smtp_pass'];
        $mail->SMTPSecure = $settings['smtp_secure'];
        $mail->Port = $settings['smtp_port'];
        $mail->CharSet = 'UTF-8';
        
        // 发件人和收件人
        $mail->setFrom($settings['smtp_from'], '系统通知');
        $mail->addAddress($to);
        
        // 内容
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_message("邮件发送失败：" . $e->getMessage());
        return false;
    }
}

// ... 其他代码 ...