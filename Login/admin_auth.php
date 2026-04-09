<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: ../Login/Dangnhap.php');
    exit;
}

$sessionEmail = strtolower(trim((string) ($_SESSION['user_email'] ?? '')));
$isPrimaryAdmin = $sessionEmail === 'admin@gmail.com';
$isAdminCreatedAccount = !empty($_SESSION['admin_created_account']);

if (!$isPrimaryAdmin && !$isAdminCreatedAccount) {
    session_unset();
    session_destroy();
    header('Location: ../Login/Dangnhap.php');
    exit;
}
