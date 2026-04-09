<?php
require 'connect.php';

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
