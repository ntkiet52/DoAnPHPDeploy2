<?php
require 'connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function respondLogin(string $status, array $extra = []): void
{
    echo json_encode(array_merge(['status' => $status], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeRole(string $role): string
{
    $value = mb_strtolower(trim($role), 'UTF-8');
    if ($value === '') {
        return '';
    }

    if (in_array($value, ['admin', 'administrator', 'quantri', 'quản trị'], true)) {
        return 'admin';
    }

    if (in_array($value, ['khachhang', 'khách hàng', 'customer', 'client'], true)) {
        return 'khachhang';
    }

    if (in_array($value, ['user', 'member', 'staff', 'nhanvien', 'nhân viên'], true)) {
        return 'user';
    }

    return '';
}

function roleRedirectPath(string $role): string
{
    if ($role === 'admin') {
        return '../TrangAdmin/admin.php';
    }

    if ($role === 'khachhang') {
        return '../TrangWeb/trangchu.php';
    }

    return '../TrangWeb/index.php';
}

function hasColumn(mysqli $conn, string $table, string $column): bool
{
    $tableEsc = $conn->real_escape_string($table);
    $columnEsc = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function firstExistingColumn(mysqli $conn, string $table, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (hasColumn($conn, $table, (string) $candidate)) {
            return (string) $candidate;
        }
    }

    return null;
}

function isStaffAccountFromNhanVien(mysqli $conn, string $email): bool
{
    $emailValue = trim($email);
    if ($emailValue === '') {
        return false;
    }

    $loginColumn = firstExistingColumn($conn, 'nhanvien', ['UserName', 'username', 'user_name', 'email', 'mail']);
    if ($loginColumn === null) {
        return false;
    }

    $sql = "SELECT 1 FROM nhanvien WHERE LOWER(`{$loginColumn}`) = LOWER(?) LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $emailValue);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && $result->fetch_row() !== null;
    $stmt->close();

    return $exists;
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

$roleColumn = firstExistingColumn($conn, 'users', ['role', 'user_role']);
$adminCreatedColumn = firstExistingColumn($conn, 'users', ['admin_created', 'is_admin_created', 'created_by_admin']);

if ($email === '' || $password === '') {
    respondLogin('missing');
}

$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    respondLogin('error');
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $status = strtolower(trim((string) ($row['status'] ?? 'active')));
    if ($status !== '' && $status !== 'active') {
        $stmt->close();
        respondLogin('inactive');
    }

    $storedPassword = (string) ($row['password'] ?? '');
    $passwordOk = password_verify($password, $storedPassword);

    if (!$passwordOk) {
        $passwordOk = hash_equals(trim($storedPassword), $password);

        if ($passwordOk) {
            $rehashStmt = $conn->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
            if ($rehashStmt) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $userIdForRehash = (int) ($row['id'] ?? 0);
                $rehashStmt->bind_param('si', $newHash, $userIdForRehash);
                $rehashStmt->execute();
                $rehashStmt->close();
            }
        }
    }

    if ($passwordOk) {
        $emailValue = strtolower(trim((string) ($row['email'] ?? '')));
        $isStaffAccount = isStaffAccountFromNhanVien($conn, $emailValue);
        $dbRole = normalizeRole((string) ($roleColumn !== null ? ($row[$roleColumn] ?? '') : ''));
        $resolvedRole = $dbRole;
        $isPrimaryAdmin = $emailValue === 'admin@gmail.com';
        $adminCreatedValue = $adminCreatedColumn !== null ? (int) ($row[$adminCreatedColumn] ?? 0) : 0;
        $isAdminCreatedAccount = $isPrimaryAdmin || $adminCreatedValue === 1;

        if ($isStaffAccount) {
            $resolvedRole = 'admin';
            $isAdminCreatedAccount = true;

            $setParts = [];
            $types = '';
            $values = [];
            if ($roleColumn !== null) {
                $setParts[] = "{$roleColumn} = ?";
                $types .= 's';
                $values[] = 'admin';
            }
            if ($adminCreatedColumn !== null) {
                $setParts[] = "{$adminCreatedColumn} = ?";
                $types .= 'i';
                $values[] = 1;
            }
            if (hasColumn($conn, 'users', 'updated_at')) {
                $setParts[] = 'updated_at = NOW()';
            }

            if (count($setParts) > 0) {
                $types .= 's';
                $values[] = $emailValue;
                $syncSql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE email = ? LIMIT 1';
                $syncStmt = $conn->prepare($syncSql);
                if ($syncStmt) {
                    $bindArgs = [$types];
                    foreach ($values as $idx => $value) {
                        $bindArgs[] = &$values[$idx];
                    }
                    call_user_func_array([$syncStmt, 'bind_param'], $bindArgs);
                    $syncStmt->execute();
                    $syncStmt->close();
                }
            }
        }

        if ($resolvedRole === '' && $isPrimaryAdmin) {
            $resolvedRole = 'admin';
        }

        if ($resolvedRole === 'admin' && !$isAdminCreatedAccount) {
            $stmt->close();
            respondLogin('forbidden_admin');
        }

        if ($resolvedRole === '') {
            $resolvedRole = 'khachhang';
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) ($row['id'] ?? 0);
        $_SESSION['user_name'] = (string) ($row['name'] ?? 'Admin');
        $_SESSION['user_email'] = (string) ($row['email'] ?? '');
        $_SESSION['user_role'] = $resolvedRole;
        $_SESSION['is_admin'] = $resolvedRole === 'admin' && $isAdminCreatedAccount;
        $_SESSION['admin_created_account'] = $isAdminCreatedAccount;

        // Lấy MaKhachHang từ bảng khachhang theo email
        $userEmail = (string) ($row['email'] ?? '');
        $khStmt = $conn->prepare("SELECT MaKhachHang FROM khachhang WHERE Email = ? LIMIT 1");
        if ($khStmt) {
            $khStmt->bind_param("s", $userEmail);
            $khStmt->execute();
            $khResult = $khStmt->get_result();
            if ($khRow = $khResult->fetch_assoc()) {
                $_SESSION['ma_khach_hang'] = $khRow['MaKhachHang'];
            }
            $khStmt->close();
        }

        $userId = (int) ($row['id'] ?? 0);
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $historyStmt = $conn->prepare("INSERT INTO login_history (user_id, ip_address) VALUES (?, ?)");
        if ($historyStmt) {
            $historyStmt->bind_param("is", $userId, $ip);
            $historyStmt->execute();
            $historyStmt->close();
        }

        $stmt->close();
        respondLogin('success', [
            'role' => $resolvedRole,
            'redirect' => roleRedirectPath($resolvedRole),
        ]);
    } else {
        $stmt->close();
        respondLogin('wrong_password');
    }
} else {
    $stmt->close();
    respondLogin('not_found');
}
?>