<?php
require_once __DIR__ . '/../Login/connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

function redirectWithStatus(string $status): void
{
    $fallback = 'tuyen-dung.php';
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $base = $referer !== '' ? $referer : $fallback;

    $separator = str_contains($base, '?') ? '&' : '?';
    header('Location: ' . $base . $separator . 'newsletter=' . urlencode($status));
    exit;
}

function isAjaxRequest(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($requestedWith === 'xmlhttprequest') {
        return true;
    }

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    return str_contains($accept, 'application/json');
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    if (function_exists('http_response_code')) {
        http_response_code($statusCode);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isAjaxRequest()) {
        jsonResponse(['success' => false, 'message' => 'Yêu cầu không hợp lệ.'], 405);
    }
    redirectWithStatus('error');
}

$email = trim((string) ($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    if (isAjaxRequest()) {
        jsonResponse(['success' => false, 'message' => 'Email không hợp lệ. Vui lòng kiểm tra lại.'], 422);
    }
    redirectWithStatus('invalid');
}

$fileEnv = loadEnvFile(dirname(__DIR__) . '/.env');
$notifyEmail = envValue('NEWSLETTER_NOTIFY_EMAIL', $fileEnv, '');
$mailFrom = envValue('OTP_MAIL_FROM', $fileEnv, '');
$replyTo = envValue('OTP_MAIL_REPLY_TO', $fileEnv, $mailFrom);
$appName = envValue('OTP_MAIL_APP_NAME', $fileEnv, 'ACK Store');

if ($notifyEmail === '' && $mailFrom !== '') {
    $notifyEmail = $mailFrom;
}

if ($notifyEmail === '') {
    if (isAjaxRequest()) {
        jsonResponse(['success' => false, 'message' => 'Chưa cấu hình email nhận thông báo.'], 500);
    }
    redirectWithStatus('error');
}

$subject = '[' . $appName . '] Khách hàng đăng ký nhận khuyến mãi';
$submittedAt = date('d/m/Y H:i:s');
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

$message = "<html><body>";
$message .= "<h3>Đăng ký nhận khuyến mãi</h3>";
$message .= "<p><strong>Email khách hàng:</strong> " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</p>";
$message .= "<p><strong>Thời gian:</strong> {$submittedAt}</p>";
$message .= "<p><strong>IP:</strong> {$clientIp}</p>";
$message .= "</body></html>";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";

if ($mailFrom !== '') {
    $fromName = $appName !== '' ? $appName : 'ACK Store';
    $headers .= "From: {$fromName} <{$mailFrom}>\r\n";
    if ($replyTo !== '') {
        $headers .= "Reply-To: {$replyTo}\r\n";
    }
} else {
    $headers .= "From: noreply@ackstore.com\r\n";
}

$sent = @mail($notifyEmail, $subject, $message, $headers);
if (!$sent) {
    if (isAjaxRequest()) {
        jsonResponse(['success' => false, 'message' => 'Không gửi được. Vui lòng thử lại sau.'], 500);
    }
    redirectWithStatus('error');
}

if (isAjaxRequest()) {
    jsonResponse(['success' => true, 'message' => 'Đã gửi thông tin về email của bạn. Cảm ơn bạn!']);
}

redirectWithStatus('sent');
