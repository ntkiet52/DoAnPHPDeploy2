<?php
require 'connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot-password.php');
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: forgot-password.php?status=invalid_email');
    exit;
}

$normalizedEmail = strtolower($email);

$userStmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
if (!$userStmt) {
    header('Location: forgot-password.php?status=sent');
    exit;
}

$userStmt->bind_param('s', $normalizedEmail);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userExists = ($userResult && $userResult->num_rows > 0);
$userStmt->close();

if ($userExists) {
    $deleteStmt = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
    if ($deleteStmt) {
        $deleteStmt->bind_param('s', $normalizedEmail);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $fallbackBytes = openssl_random_pseudo_bytes(32);
        if (!is_string($fallbackBytes) || $fallbackBytes === '') {
            $fallbackBytes = uniqid((string) mt_rand(), true);
        }
        $token = bin2hex((string) $fallbackBytes);
    }

    $tokenHash = hash('sha256', $token);

    $resetStmt = $conn->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
    if ($resetStmt) {
        $resetStmt->bind_param('ss', $normalizedEmail, $tokenHash);
        $resetStmt->execute();
        $resetStmt->close();

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/Login/request_reset.php')));
        $scriptDir = rtrim($scriptDir, '/');
        $resetLink = $scheme . '://' . $host . $scriptDir . '/reset-password.php?email=' . urlencode($normalizedEmail) . '&token=' . urlencode($token);

        $_SESSION['reset_link_preview'] = $resetLink;
    }
}

header('Location: forgot-password.php?status=sent');
exit;