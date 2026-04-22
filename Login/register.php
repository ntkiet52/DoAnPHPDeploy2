<?php
require 'connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const REGISTER_OTP_SESSION_KEY = 'ack_register_otp';
const REGISTER_OTP_EXPIRE_SECONDS = 600;
const REGISTER_OTP_RESEND_SECONDS = 60;

function registerLoadEnvFile(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $env = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $env[$key] = $value;
    }

    return $env;
}

function registerEnv(string $key, array $fileEnv = []): string
{
    $value = getenv($key);
    if (is_string($value) && trim($value) !== '') {
        return trim($value);
    }

    if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
        return trim((string) $_ENV[$key]);
    }

    if (isset($fileEnv[$key]) && trim((string) $fileEnv[$key]) !== '') {
        return trim((string) $fileEnv[$key]);
    }

    return '';
}

function generateRegisterOtpCode(): string
{
    return (string) random_int(100000, 999999);
}

function sendRegisterOtpEmail(string $toEmail, string $otpCode): bool
{
    $toEmail = trim($toEmail);
    if ($toEmail === '') {
        return false;
    }

    static $env = null;
    if (!is_array($env)) {
        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        $env = registerLoadEnvFile($envPath);
    }

    $mailFrom = registerEnv('OTP_MAIL_FROM', $env);
    $replyTo = registerEnv('OTP_MAIL_REPLY_TO', $env);
    $appName = registerEnv('OTP_MAIL_APP_NAME', $env);

    if ($mailFrom === '') {
        $mailFrom = 'no-reply@ackstore.local';
    }

    if ($replyTo === '') {
        $replyTo = $mailFrom;
    }

    if ($appName === '') {
        $appName = 'ACK Store';
    }

    $subject = 'Ma xac thuc dang ky ACK Store';
    $message = "Xin chao,\r\n\r\n" .
        "Ma xac thuc dang ky tai khoan {$appName} cua ban la: {$otpCode}\r\n" .
        'Ma co hieu luc trong 10 phut.\r\n\r\n' .
        'Neu ban khong yeu cau dang ky, vui long bo qua email nay.\r\n\r\n' .
        $appName;

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $appName . ' <' . $mailFrom . '>',
        'Reply-To: ' . $replyTo,
        'X-Mailer: PHP/' . phpversion(),
    ];

    return @mail($toEmail, $subject, $message, implode("\r\n", $headers));
}

function hasColumn(mysqli $conn, string $table, string $column): bool
{
    $tableEsc = $conn->real_escape_string($table);
    $columnEsc = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function generateNextCustomerCode(mysqli $conn): string
{
    $result = $conn->query('SELECT MaKhachHang FROM khachhang');
    $maxNumber = 0;

    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $id = (string) ($row['MaKhachHang'] ?? '');
            if (preg_match('/(\d+)$/', $id, $m)) {
                $num = (int) $m[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }
        $result->free();
    }

    return 'KH' . str_pad((string) ($maxNumber + 1), 2, '0', STR_PAD_LEFT);
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$action = trim((string) ($_POST['action'] ?? 'register'));

if ($action === 'send_otp') {
    if ($email === '') {
        echo 'missing';
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 'invalid_email';
        exit;
    }

    $sql = 'SELECT id FROM users WHERE email = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        echo 'exists';
        exit;
    }
    $stmt->close();

    if (!isset($_SESSION[REGISTER_OTP_SESSION_KEY]) || !is_array($_SESSION[REGISTER_OTP_SESSION_KEY])) {
        $_SESSION[REGISTER_OTP_SESSION_KEY] = [];
    }

    $emailKey = strtolower($email);
    $current = $_SESSION[REGISTER_OTP_SESSION_KEY][$emailKey] ?? null;
    $now = time();

    if (is_array($current) && ($now - (int) ($current['sent_at'] ?? 0)) < REGISTER_OTP_RESEND_SECONDS) {
        echo 'otp_cooldown';
        exit;
    }

    $otpCode = generateRegisterOtpCode();
    $sent = sendRegisterOtpEmail($email, $otpCode);
    if (!$sent) {
        echo 'otp_send_failed';
        exit;
    }

    $_SESSION[REGISTER_OTP_SESSION_KEY][$emailKey] = [
        'code' => $otpCode,
        'sent_at' => $now,
        'expires_at' => $now + REGISTER_OTP_EXPIRE_SECONDS,
    ];

    echo 'otp_sent';
    exit;
}

$otp = trim((string) ($_POST['otp'] ?? ''));

if ($name === '' || $email === '' || $password === '') {
    echo "missing";
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "invalid_email";
    exit;
}

if (strlen($password) < 6) {
    echo "weak_password";
    exit;
}

if ($otp === '') {
    echo 'otp_missing';
    exit;
}

if (!preg_match('/^\d{6}$/', $otp)) {
    echo 'otp_invalid';
    exit;
}

$emailKey = strtolower($email);
$otpEntry = $_SESSION[REGISTER_OTP_SESSION_KEY][$emailKey] ?? null;

if (!is_array($otpEntry)) {
    echo 'otp_not_requested';
    exit;
}

$expiresAt = (int) ($otpEntry['expires_at'] ?? 0);
if ($expiresAt < time()) {
    unset($_SESSION[REGISTER_OTP_SESSION_KEY][$emailKey]);
    echo 'otp_expired';
    exit;
}

$sessionCode = (string) ($otpEntry['code'] ?? '');
if (!hash_equals($sessionCode, $otp)) {
    echo 'otp_invalid';
    exit;
}

// Kiểm tra email đã tồn tại chưa
$sql = "SELECT id FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo "exists";
    exit;
}
$stmt->close();

// Hash password
$hash = password_hash($password, PASSWORD_DEFAULT);
$sql = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $name, $email, $hash);
if ($stmt->execute()) {
    unset($_SESSION[REGISTER_OTP_SESSION_KEY][$emailKey]);

    if (hasColumn($conn, 'khachhang', 'MaKhachHang') && hasColumn($conn, 'khachhang', 'TenKhachHang')) {
        $newKhId = generateNextCustomerCode($conn);
        $insertKhStmt = $conn->prepare('INSERT INTO khachhang (MaKhachHang, TenKhachHang) VALUES (?, ?)');
        if ($insertKhStmt) {
            $insertKhStmt->bind_param('ss', $newKhId, $name);
            $insertKhStmt->execute();
            $insertKhStmt->close();
        }

        if (hasColumn($conn, 'khachhang', 'MaSoThue')) {
            $updateTaxStmt = $conn->prepare('UPDATE khachhang SET MaSoThue = ? WHERE MaKhachHang = ?');
            if ($updateTaxStmt) {
                $emailAsTaxCode = strtolower($email);
                $updateTaxStmt->bind_param('ss', $emailAsTaxCode, $newKhId);
                $updateTaxStmt->execute();
                $updateTaxStmt->close();
            }
        }
    }

    echo "success";
} else {
    echo "error";
}
$stmt->close();
?>
