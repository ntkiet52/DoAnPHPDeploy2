<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../Login/connect.php';

header('Content-Type: application/json; charset=utf-8');

$isLoggedIn = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
$currentUserName = trim((string) ($_SESSION['user_name'] ?? 'Khách hàng'));
$currentUserEmail = trim((string) ($_SESSION['user_email'] ?? ''));
$currentUserRole = strtolower(trim((string) ($_SESSION['user_role'] ?? 'guest')));
$currentUserAvatar = trim((string) ($_SESSION['user_avatar'] ?? ''));

if (!$isLoggedIn && $currentUserEmail !== '' && isset($conn) && $conn instanceof mysqli) {
    $idColumn = null;
    foreach (['id', 'user_id', 'userid'] as $candidate) {
        $tableEsc = $conn->real_escape_string('users');
        $columnEsc = $conn->real_escape_string($candidate);
        $check = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
        if ($check instanceof mysqli_result && $check->num_rows > 0) {
            $idColumn = $candidate;
            break;
        }
    }

    $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $currentUserEmail);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        $resolvedId = 0;
        if (is_array($row)) {
            foreach ($row as $key => $value) {
                $keyText = strtolower((string) $key);
                if (!preg_match('/(^id$|_id$|^id_|userid|iduser)/i', $keyText)) {
                    continue;
                }

                $numeric = (int) $value;
                if ($numeric > 0) {
                    $resolvedId = $numeric;
                    break;
                }
            }

            if ($resolvedId <= 0 && $idColumn !== null) {
                $maxRes = $conn->query("SELECT MAX(CAST(`{$idColumn}` AS UNSIGNED)) AS max_id FROM users");
                $maxId = 0;
                if ($maxRes instanceof mysqli_result) {
                    $maxRow = $maxRes->fetch_assoc() ?: [];
                    $maxId = (int) ($maxRow['max_id'] ?? 0);
                }
                $nextId = max(1, $maxId + 1);

                $updateSql = "UPDATE users SET `{$idColumn}` = ? WHERE email = ? AND (CAST(`{$idColumn}` AS SIGNED) <= 0 OR `{$idColumn}` IS NULL OR `{$idColumn}` = '') LIMIT 1";
                $updateStmt = $conn->prepare($updateSql);
                if ($updateStmt) {
                    $updateStmt->bind_param('is', $nextId, $currentUserEmail);
                    $updateStmt->execute();
                    $updateStmt->close();
                    $resolvedId = $nextId;
                }
            }

            if ($resolvedId <= 0) {
                $resolvedId = (int) (abs(crc32($currentUserEmail)) + 100000);
            }

            $_SESSION['user_id'] = $resolvedId;
            if (!empty($row['name'])) {
                $_SESSION['user_name'] = (string) $row['name'];
                $currentUserName = (string) $row['name'];
            }
            if (!empty($row['email'])) {
                $_SESSION['user_email'] = (string) $row['email'];
                $currentUserEmail = (string) $row['email'];
            }
            $isLoggedIn = true;
        }
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->query("CREATE TABLE IF NOT EXISTS tai_khoan_anh_dai_dien (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL DEFAULT 0,
        user_email VARCHAR(191) NOT NULL DEFAULT '',
        avatar_path VARCHAR(255) NOT NULL,
        tao_luc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        cap_nhat_luc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_avatar_user_id (user_id),
        UNIQUE KEY uq_avatar_user_email (user_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($currentUserAvatar === '') {
        $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
        $safeEmailLower = strtolower(trim((string) ($_SESSION['user_email'] ?? '')));

        if ($sessionUserId > 0) {
            $avatarStmt = $conn->prepare('SELECT avatar_path FROM tai_khoan_anh_dai_dien WHERE user_id = ? LIMIT 1');
            if ($avatarStmt) {
                $avatarStmt->bind_param('i', $sessionUserId);
                $avatarStmt->execute();
                $avatarRes = $avatarStmt->get_result();
                $avatarRow = $avatarRes ? $avatarRes->fetch_assoc() : null;
                $avatarStmt->close();

                if (is_array($avatarRow)) {
                    $currentUserAvatar = trim((string) ($avatarRow['avatar_path'] ?? ''));
                }
            }
        }

        if ($currentUserAvatar === '' && $safeEmailLower !== '') {
            $avatarStmt = $conn->prepare('SELECT avatar_path FROM tai_khoan_anh_dai_dien WHERE user_email = ? LIMIT 1');
            if ($avatarStmt) {
                $avatarStmt->bind_param('s', $safeEmailLower);
                $avatarStmt->execute();
                $avatarRes = $avatarStmt->get_result();
                $avatarRow = $avatarRes ? $avatarRes->fetch_assoc() : null;
                $avatarStmt->close();

                if (is_array($avatarRow)) {
                    $currentUserAvatar = trim((string) ($avatarRow['avatar_path'] ?? ''));
                }
            }
        }

        if ($currentUserAvatar !== '') {
            $_SESSION['user_avatar'] = $currentUserAvatar;
        }
    }
}

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
    'user_id' => (int) ($_SESSION['user_id'] ?? 0),
    'name' => $currentUserName,
    'email' => $currentUserEmail,
    'avatar' => $currentUserAvatar,
    'role' => $currentUserRole,
    'role_label' => $roleLabelMap[$currentUserRole] ?? 'Người dùng',
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
