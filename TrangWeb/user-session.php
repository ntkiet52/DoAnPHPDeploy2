<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$isLoggedIn = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
$currentUserName = trim((string) ($_SESSION['user_name'] ?? 'Khách hàng'));
$currentUserEmail = trim((string) ($_SESSION['user_email'] ?? ''));
$currentUserRole = strtolower(trim((string) ($_SESSION['user_role'] ?? 'guest')));

$roleLabelMap = [
    'admin' => 'Quản trị viên',
    'khachhang' => 'Khách hàng',
    'user' => 'Người dùng',
    'guest' => 'Khách',
];

if ($currentUserName === '') {
    $currentUserName = 'Khách hàng';
}

$payload = [
    'ok' => true,
    'is_logged_in' => $isLoggedIn,
    'name' => $currentUserName,
    'email' => $currentUserEmail,
    'role' => $currentUserRole,
    'role_label' => $roleLabelMap[$currentUserRole] ?? 'Người dùng',
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
