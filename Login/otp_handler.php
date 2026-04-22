<?php
require_once __DIR__ . '/connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

if (function_exists('ob_start')) {
    ob_start();
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    if (function_exists('http_response_code')) {
        http_response_code($statusCode);
    }
    if (function_exists('ob_get_length') && ob_get_length()) {
        ob_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

set_exception_handler(function ($exception) {
    jsonResponse([
        'success' => false,
        'message' => 'Lỗi hệ thống. Vui lòng thử lại sau.'
    ], 500);
});

function loadEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $env = [];
    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $equalPos = strpos($trimmed, '=');
        if ($equalPos === false) {
            continue;
        }

        $key = trim(substr($trimmed, 0, $equalPos));
        $value = trim(substr($trimmed, $equalPos + 1));
        $value = trim($value, "\"'");

        if ($key !== '') {
            $env[$key] = $value;
        }
    }

    return $env;
}

function envValue(string $key, array $env, string $default = ''): string
{
    if (isset($env[$key]) && $env[$key] !== '') {
        return (string) $env[$key];
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return (string) $value;
    }

    return $default;
}

function ensureOtpTable(mysqli $conn): bool
{
    $sql = "CREATE TABLE IF NOT EXISTS `password_otp` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `email` varchar(255) NOT NULL,
        `otp_code` varchar(10) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `is_used` tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `email` (`email`),
        KEY `expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    return $conn->query($sql) === true;
}

function sendOTP($email) {
    global $conn;
    
    $email = strtolower(trim($email));
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Email không hợp lệ'];
    }
    
    $fileEnv = loadEnvFile(dirname(__DIR__) . '/.env');
    $mailFrom = envValue('OTP_MAIL_FROM', $fileEnv, '');
    $mailReplyTo = envValue('OTP_MAIL_REPLY_TO', $fileEnv, $mailFrom);
    $appName = envValue('OTP_MAIL_APP_NAME', $fileEnv, 'ACK Store');

    if (!ensureOtpTable($conn)) {
        return ['success' => false, 'message' => 'Chưa tạo bảng OTP. Vui lòng chạy SQL tạo bảng password_otp.'];
    }

    // Check if email exists in users table
    $userStmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    if (!$userStmt) {
        return ['success' => false, 'message' => 'Lỗi hệ thống'];
    }
    
    $userStmt->bind_param('s', $email);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userExists = ($userResult && $userResult->num_rows > 0);
    $userStmt->close();
    
    if (!$userExists) {
        // For security, don't reveal if email exists
        return ['success' => true, 'message' => 'Nếu email tồn tại, mã OTP sẽ được gửi'];
    }
    
    // Generate 6-digit OTP
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Delete old OTPs for this email
    $deleteStmt = $conn->prepare('DELETE FROM password_otp WHERE email = ? AND is_used = 0');
    if ($deleteStmt) {
        $deleteStmt->bind_param('s', $email);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    
    // Insert new OTP (valid for 10 minutes)
    $expiresAt = date('Y-m-d H:i:s', time() + 600);
    $insertStmt = $conn->prepare('INSERT INTO password_otp (email, otp_code, expires_at) VALUES (?, ?, ?)');
    if (!$insertStmt) {
        return ['success' => false, 'message' => 'Lỗi lưu OTP'];
    }
    
    $insertStmt->bind_param('sss', $email, $otpCode, $expiresAt);
    $insertOk = $insertStmt->execute();
    $insertStmt->close();
    
    if (!$insertOk) {
        return ['success' => false, 'message' => 'Lỗi lưu OTP'];
    }
    
    // Send OTP via email
    $subject = '[' . $appName . '] Mã OTP đặt lại mật khẩu';
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: #fff; padding: 20px; border-radius: 5px 5px 0 0; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
            .otp-box { 
                background: #fff; 
                border: 2px solid #007bff; 
                border-radius: 5px; 
                padding: 15px; 
                text-align: center; 
                margin: 20px 0;
            }
            .otp-code { 
                font-size: 32px; 
                font-weight: bold; 
                color: #007bff; 
                letter-spacing: 5px;
            }
            .expires { color: #666; font-size: 12px; margin-top: 10px; }
            .footer { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Yêu cầu đặt lại mật khẩu</h2>
            </div>
            <div class='content'>
                <p>Xin chào,</p>
                <p>Bạn vừa yêu cầu đặt lại mật khẩu cho tài khoản ACK Store của bạn.</p>
                <p>Vui lòng sử dụng mã OTP bên dưới để xác thực:</p>
                <div class='otp-box'>
                    <div class='otp-code'>" . htmlspecialchars($otpCode, ENT_QUOTES, 'UTF-8') . "</div>
                    <div class='expires'>Mã này có hiệu lực trong 10 phút</div>
                </div>
                <p><strong>Lưu ý:</strong> Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.</p>
                <p>Trân trọng,<br>ACK Store Team</p>
                <div class='footer'>
                    <p>© 2026 ACK Store. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    if ($mailFrom !== '') {
        $fromName = $appName !== '' ? $appName : 'ACK Store';
        $headers .= "From: {$fromName} <{$mailFrom}>\r\n";
        if ($mailReplyTo !== '') {
            $headers .= "Reply-To: {$mailReplyTo}\r\n";
        }
    } else {
        $headers .= "From: noreply@ackstore.com\r\n";
    }
    
    $mailResult = @mail($email, $subject, $message, $headers);
    
    if (!$mailResult) {
        return ['success' => false, 'message' => 'Lỗi khi gửi email'];
    }
    
    // Store email + OTP in session for fallback verification (local safety)
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_code'] = $otpCode;
    $_SESSION['otp_expires_at'] = time() + 600;
    
    return ['success' => true, 'message' => 'Mã OTP đã được gửi đến email của bạn'];
}

function verifyOTP($email, $otpCode) {
    global $conn;
    
    $email = strtolower(trim($email));
    $otpCode = trim($otpCode);
    
    if ($email === '' || $otpCode === '') {
        return ['success' => false, 'message' => 'Email và mã OTP không được trống'];
    }
    
    // Find valid OTP in database
    $selectStmt = $conn->prepare('
        SELECT id FROM password_otp 
        WHERE email = ? 
        AND otp_code = ? 
        AND is_used = 0 
        AND expires_at > NOW() 
        LIMIT 1
    ');
    
    if (!$selectStmt) {
        return ['success' => false, 'message' => 'Lỗi hệ thống'];
    }
    
    $selectStmt->bind_param('ss', $email, $otpCode);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $otpRecord = $result ? $result->fetch_assoc() : null;
    $selectStmt->close();
    
    if (!$otpRecord) {
        $sessionEmail = (string) ($_SESSION['otp_email'] ?? '');
        $sessionCode = (string) ($_SESSION['otp_code'] ?? '');
        $sessionExpires = (int) ($_SESSION['otp_expires_at'] ?? 0);

        if ($sessionEmail === $email && $sessionCode === $otpCode && $sessionExpires > time()) {
            $_SESSION['verified_email'] = $email;
            $_SESSION['verified_at'] = time();
            return ['success' => true, 'message' => 'Xác thực thành công'];
        }

        return ['success' => false, 'message' => 'Mã OTP không hợp lệ hoặc đã hết hạn'];
    }
    
    // Mark OTP as used
    $updateStmt = $conn->prepare('UPDATE password_otp SET is_used = 1 WHERE id = ?');
    if ($updateStmt) {
        $otpId = $otpRecord['id'];
        $updateStmt->bind_param('i', $otpId);
        $updateStmt->execute();
        $updateStmt->close();
    }
    
    // Store verified email in session
    $_SESSION['verified_email'] = $email;
    $_SESSION['verified_at'] = time();
    
    return ['success' => true, 'message' => 'Xác thực thành công'];
}

function changePassword($email, $newPassword) {
    global $conn;
    
    $email = strtolower(trim($email));
    
    // Validate password
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự'];
    }
    
    // Check verification session
    $verifiedEmail = (string) ($_SESSION['verified_email'] ?? '');
    $verifiedAt = (int) ($_SESSION['verified_at'] ?? 0);
    
    if ($verifiedEmail !== $email || $verifiedAt <= 0 || (time() - $verifiedAt) > 3600) {
        return ['success' => false, 'message' => 'Phiên xác thực không hợp lệ. Vui lòng thử lại từ đầu'];
    }
    
    // Hash password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateStmt = $conn->prepare('UPDATE users SET password = ? WHERE email = ? LIMIT 1');
    if (!$updateStmt) {
        return ['success' => false, 'message' => 'Lỗi cập nhật mật khẩu'];
    }
    
    $updateStmt->bind_param('ss', $passwordHash, $email);
    $updateOk = $updateStmt->execute();
    $updateStmt->close();
    
    if (!$updateOk) {
        return ['success' => false, 'message' => 'Lỗi cập nhật mật khẩu'];
    }
    
    // Clear session
    unset($_SESSION['verified_email']);
    unset($_SESSION['verified_at']);
    unset($_SESSION['otp_email']);
    unset($_SESSION['otp_code']);
    unset($_SESSION['otp_expires_at']);
    
    return ['success' => true, 'message' => 'Đổi mật khẩu thành công'];
}

// API endpoints
$action = strtolower(trim((string) ($_POST['action'] ?? '')));

if ($action === 'send_otp') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $result = sendOTP($email);
    jsonResponse($result);
} elseif ($action === 'verify_otp') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $otpCode = trim((string) ($_POST['otp_code'] ?? ''));
    $result = verifyOTP($email, $otpCode);
    jsonResponse($result);
} elseif ($action === 'change_password') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $result = changePassword($email, $newPassword);
    jsonResponse($result);
} else {
    jsonResponse(['success' => false, 'message' => 'Action không hợp lệ'], 400);
}
?>
