<?php
require_once __DIR__ . '/../Login/admin_auth.php';

function pickStaffValue(array $row, array $keys, $default = '') {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row)) {
            return $row[$key];
        }
    }

    $lowerRow = array_change_key_case($row, CASE_LOWER);
    foreach ($keys as $key) {
        $lowerKey = strtolower($key);
        if (array_key_exists($lowerKey, $lowerRow)) {
            return $lowerRow[$lowerKey];
        }
    }

    return $default;
}

function normalizeStaffBirthDate(?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
        return $raw;
    }

    if (preg_match('/^\d{4}$/', $raw) === 1) {
        return $raw . '-01-01';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

function normalizeStaffDateInputValue(?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
        return $raw;
    }

    if (preg_match('/^\d{4}$/', $raw) === 1) {
        return $raw . '-01-01';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

function formatStaffDateDisplay(?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/^\d{4}$/', $raw) === 1) {
        return '01/01/' . $raw;
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date('d/m/Y', $timestamp);
}

function generateNextStaffId(PDO $pdo): string {
    $rows = $pdo->query("SELECT MaNV FROM nhanvien")->fetchAll(PDO::FETCH_COLUMN);
    $usedNumbers = [];

    foreach ($rows as $rowId) {
        $id = (string) $rowId;
        if (preg_match('/(\d+)$/', $id, $matches) !== 1) {
            continue;
        }

        $usedNumbers[(int) $matches[1]] = true;
    }

    $nextNumber = 1;
    while (isset($usedNumbers[$nextNumber])) {
        $nextNumber++;
    }

    return 'NV' . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
}

function getExistingColumns(PDO $pdo, string $table, bool $forceRefresh = false): array {
    static $cache = [];

    if (!$forceRefresh && isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cache[$table] = array_map(static function ($col) {
        return strtolower((string) ($col['Field'] ?? ''));
    }, $rows);

    return $cache[$table];
}

function pickExistingColumn(array $existingColumns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (in_array(strtolower($candidate), $existingColumns, true)) {
            return $candidate;
        }
    }

    return null;
}

function ensureStaffImageColumn(PDO $pdo): ?string {
    $columns = getExistingColumns($pdo, 'nhanvien', true);
    $imageColumn = pickExistingColumn($columns, ['hinhanh', 'hinh_anh', 'avatar', 'image', 'img', 'anh']);
    if ($imageColumn !== null) {
        return $imageColumn;
    }

    try {
        $pdo->exec("ALTER TABLE nhanvien ADD COLUMN HinhAnh VARCHAR(255) NULL");
    } catch (Throwable $ignored) {
    }

    $columns = getExistingColumns($pdo, 'nhanvien', true);
    return pickExistingColumn($columns, ['hinhanh', 'hinh_anh', 'avatar', 'image', 'img', 'anh']);
}

function ensureUsersRoleColumn(PDO $pdo): ?string {
    $columns = getExistingColumns($pdo, 'users', true);
    $roleColumn = pickExistingColumn($columns, ['role', 'user_role']);
    if ($roleColumn !== null) {
        return $roleColumn;
    }

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'khachhang'");
    } catch (Throwable $ignored) {
    }

    $columns = getExistingColumns($pdo, 'users', true);
    return pickExistingColumn($columns, ['role', 'user_role']);
}

function ensureUsersAdminCreatedColumn(PDO $pdo): ?string {
    $columns = getExistingColumns($pdo, 'users', true);
    $adminCreatedColumn = pickExistingColumn($columns, ['admin_created', 'is_admin_created', 'created_by_admin']);
    if ($adminCreatedColumn !== null) {
        return $adminCreatedColumn;
    }

    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN admin_created TINYINT(1) NOT NULL DEFAULT 0');
    } catch (Throwable $ignored) {
    }

    $columns = getExistingColumns($pdo, 'users', true);
    return pickExistingColumn($columns, ['admin_created', 'is_admin_created', 'created_by_admin']);
}

$staff = [];
$dbError = '';
$crudMessage = '';
$crudError = '';

$dbHost = '127.0.0.1';
$dbName = 'qlhethongbanhangmini';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $staffImageColumn = ensureStaffImageColumn($pdo);
    $usersRoleColumn = ensureUsersRoleColumn($pdo);
    $usersAdminCreatedColumn = ensureUsersAdminCreatedColumn($pdo);
    $usersColumns = getExistingColumns($pdo, 'users', true);
    $usersStatusColumn = pickExistingColumn($usersColumns, ['status']);
    $canProvisionAdminAccounts = $usersRoleColumn !== null && $usersAdminCreatedColumn !== null;

    $chucVuMap = [];
    try {
        $cvRows = $pdo->query("SELECT * FROM chucvu")->fetchAll();
        foreach ($cvRows as $cv) {
            $maCv = (string) pickStaffValue($cv, ['machucvu', 'ma_chuc_vu', 'macv', 'id']);
            $tenCv = (string) pickStaffValue($cv, ['tenchucvu', 'ten_chuc_vu', 'tencv', 'name', 'tencv']);
            if ($maCv !== '') {
                $chucVuMap[$maCv] = $tenCv;
            }
        }
    } catch (Throwable $ignored) {
    }

    $boPhanMap = [];
    $boPhanDefault = '';
    try {
        $bpRows = $pdo->query("SELECT * FROM bophan")->fetchAll();
        foreach ($bpRows as $bp) {
            $maBp = (string) pickStaffValue($bp, ['mabp', 'ma_bp', 'id'], '');
            $tenBp = (string) pickStaffValue($bp, ['tenbp', 'ten_bo_phan', 'tenbophan', 'name'], '');
            if ($maBp !== '') {
                $boPhanMap[$maBp] = $tenBp;
            }
        }
        if (count($boPhanMap) > 0) {
            $boPhanDefault = (string) array_key_first($boPhanMap);
        }
    } catch (Throwable $ignored) {
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $action = isset($_POST['crud_action']) ? trim((string) $_POST['crud_action']) : '';

            if ($action === 'add_staff') {
                if (!$canProvisionAdminAccounts) {
                    $crudError = 'Không thể cấu hình phân quyền tài khoản admin trên bảng users. Vui lòng kiểm tra quyền ALTER TABLE.';
                }

                $id = trim((string) ($_POST['staff_id'] ?? ''));
                $name = trim((string) ($_POST['name'] ?? ''));
                $loginEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
                $maCv = trim((string) ($_POST['ma_cv'] ?? $_POST['role'] ?? ''));
                $maBp = trim((string) ($_POST['ma_bp'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $gender = trim((string) ($_POST['status'] ?? ''));
                $birthDate = normalizeStaffBirthDate((string) ($_POST['date'] ?? ''));
                $hinhAnh = trim((string) ($_POST['hinh_anh'] ?? ''));
                $accountPassword = (string) ($_POST['password'] ?? '');

                if ($id === '') {
                    $id = generateNextStaffId($pdo);
                }

                if ($name === '' || $loginEmail === '' || $maCv === '' || $maBp === '' || $phone === '' || $gender === '' || $birthDate === '') {
                    $crudError = 'Vui lòng nhập đầy đủ thông tin nhân viên.';
                } else {
                    if (filter_var($loginEmail, FILTER_VALIDATE_EMAIL) === false) {
                        $crudError = 'Email đăng nhập không hợp lệ.';
                    }

                    if ($crudError === '' && strlen($accountPassword) < 6) {
                        $crudError = 'Mật khẩu nhân viên phải có ít nhất 6 ký tự.';
                    }

                    if (!isset($chucVuMap[$maCv])) {
                        $crudError = 'Chức vụ không hợp lệ.';
                    }

                    if ($crudError === '' && !isset($boPhanMap[$maBp])) {
                        $crudError = 'Bộ phận không hợp lệ.';
                    }

                    if ($crudError === '') {
                        $existingUserStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                        $existingUserStmt->execute([$loginEmail]);
                        if ($existingUserStmt->fetch(PDO::FETCH_ASSOC)) {
                            $crudError = 'Email đăng nhập đã tồn tại trong hệ thống.';
                        } else {
                            $pdo->beginTransaction();
                            try {
                                if ($staffImageColumn !== null) {
                                    $stmt = $pdo->prepare(
                                        "INSERT INTO nhanvien (MaNV, TenNV, GioiTinhNV, NamSinh, SDTNV, DiaChiNV, UserName, Pass, PhanQuyen, MaBP, MaCV, {$staffImageColumn})
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                                    );
                                    $stmt->execute([
                                        $id,
                                        $name,
                                        $gender,
                                        $birthDate,
                                        $phone,
                                        '',
                                        $loginEmail,
                                        $accountPassword,
                                        2,
                                        $maBp,
                                        $maCv,
                                        $hinhAnh !== '' ? $hinhAnh : null,
                                    ]);
                                } else {
                                    $stmt = $pdo->prepare(
                                        "INSERT INTO nhanvien (MaNV, TenNV, GioiTinhNV, NamSinh, SDTNV, DiaChiNV, UserName, Pass, PhanQuyen, MaBP, MaCV)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                                    );
                                    $stmt->execute([
                                        $id,
                                        $name,
                                        $gender,
                                        $birthDate,
                                        $phone,
                                        '',
                                        $loginEmail,
                                        $accountPassword,
                                        2,
                                        $maBp,
                                        $maCv,
                                    ]);
                                }

                                $userColumns = ['name', 'email', 'password'];
                                $userValues = [$name, $loginEmail, password_hash($accountPassword, PASSWORD_DEFAULT)];
                                if ($usersStatusColumn !== null) {
                                    $userColumns[] = $usersStatusColumn;
                                    $userValues[] = 'active';
                                }
                                if ($usersRoleColumn !== null) {
                                    $userColumns[] = $usersRoleColumn;
                                    $userValues[] = 'admin';
                                }
                                if ($usersAdminCreatedColumn !== null) {
                                    $userColumns[] = $usersAdminCreatedColumn;
                                    $userValues[] = 1;
                                }

                                $placeholders = implode(', ', array_fill(0, count($userColumns), '?'));
                                $insertUserSql = 'INSERT INTO users (' . implode(', ', $userColumns) . ') VALUES (' . $placeholders . ')';
                                $insertUserStmt = $pdo->prepare($insertUserSql);
                                $insertUserStmt->execute($userValues);

                                $pdo->commit();
                                $crudMessage = 'Đã thêm nhân viên thành công và tạo tài khoản đăng nhập admin.';
                            } catch (Throwable $insertError) {
                                if ($pdo->inTransaction()) {
                                    $pdo->rollBack();
                                }
                                throw $insertError;
                            }
                        }
                    }
                }
            }

            if ($action === 'update_staff') {
                if (!$canProvisionAdminAccounts) {
                    $crudError = 'Không thể cấu hình phân quyền tài khoản admin trên bảng users. Vui lòng kiểm tra quyền ALTER TABLE.';
                }

                $id = trim((string) ($_POST['staff_id'] ?? ''));
                $name = trim((string) ($_POST['name'] ?? ''));
                $loginEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
                $maCv = trim((string) ($_POST['ma_cv'] ?? $_POST['role'] ?? ''));
                $maBp = trim((string) ($_POST['ma_bp'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $gender = trim((string) ($_POST['status'] ?? ''));
                $birthDate = normalizeStaffBirthDate((string) ($_POST['date'] ?? ''));
                $hinhAnh = trim((string) ($_POST['hinh_anh'] ?? ''));
                $newPassword = (string) ($_POST['password'] ?? '');

                if ($id === '' || $name === '' || $loginEmail === '' || $maCv === '' || $maBp === '' || $phone === '' || $gender === '' || $birthDate === '') {
                    $crudError = 'Dữ liệu cập nhật nhân viên chưa hợp lệ.';
                } else {
                    if (filter_var($loginEmail, FILTER_VALIDATE_EMAIL) === false) {
                        $crudError = 'Email đăng nhập không hợp lệ.';
                    }

                    if ($crudError === '' && $newPassword !== '' && strlen($newPassword) < 6) {
                        $crudError = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
                    }

                    if (!isset($chucVuMap[$maCv])) {
                        $crudError = 'Chức vụ không hợp lệ.';
                    }

                    if ($crudError === '' && !isset($boPhanMap[$maBp])) {
                        $crudError = 'Bộ phận không hợp lệ.';
                    }

                    if ($crudError === '') {
                        $currentStaffStmt = $pdo->prepare('SELECT UserName FROM nhanvien WHERE MaNV = ? LIMIT 1');
                        $currentStaffStmt->execute([$id]);
                        $currentStaffRow = $currentStaffStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                        $oldLoginEmail = strtolower(trim((string) pickStaffValue($currentStaffRow, ['UserName', 'username', 'user_name'], '')));

                        $checkUserStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                        $checkUserStmt->execute([$loginEmail]);
                        $existingUser = $checkUserStmt->fetch(PDO::FETCH_ASSOC);
                        if ($existingUser && $oldLoginEmail !== $loginEmail) {
                            $crudError = 'Email đăng nhập đã tồn tại trong hệ thống.';
                        }
                    }

                    if ($crudError === '') {
                        $pdo->beginTransaction();
                        try {
                            if ($staffImageColumn !== null) {
                                $stmt = $pdo->prepare(
                                    "UPDATE nhanvien SET TenNV = ?, GioiTinhNV = ?, NamSinh = ?, SDTNV = ?, UserName = ?, MaBP = ?, MaCV = ?, {$staffImageColumn} = ? WHERE MaNV = ?"
                                );
                                $stmt->execute([$name, $gender, $birthDate, $phone, $loginEmail, $maBp, $maCv, $hinhAnh !== '' ? $hinhAnh : null, $id]);
                            } else {
                                $stmt = $pdo->prepare(
                                    'UPDATE nhanvien SET TenNV = ?, GioiTinhNV = ?, NamSinh = ?, SDTNV = ?, UserName = ?, MaBP = ?, MaCV = ? WHERE MaNV = ?'
                                );
                                $stmt->execute([$name, $gender, $birthDate, $phone, $loginEmail, $maBp, $maCv, $id]);
                            }

                            if ($newPassword !== '') {
                                $updateStaffPasswordStmt = $pdo->prepare('UPDATE nhanvien SET Pass = ? WHERE MaNV = ?');
                                $updateStaffPasswordStmt->execute([$newPassword, $id]);
                            }

                            $userSelectStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                            $userSelectStmt->execute([$oldLoginEmail !== '' ? $oldLoginEmail : $loginEmail]);
                            $targetUser = $userSelectStmt->fetch(PDO::FETCH_ASSOC);

                            if ($targetUser) {
                                $setParts = ['name = ?', 'email = ?'];
                                $setValues = [$name, $loginEmail];
                                if ($usersRoleColumn !== null) {
                                    $setParts[] = $usersRoleColumn . ' = ?';
                                    $setValues[] = 'admin';
                                }
                                if ($usersAdminCreatedColumn !== null) {
                                    $setParts[] = $usersAdminCreatedColumn . ' = ?';
                                    $setValues[] = 1;
                                }
                                if ($newPassword !== '') {
                                    $setParts[] = 'password = ?';
                                    $setValues[] = password_hash($newPassword, PASSWORD_DEFAULT);
                                }
                                $setValues[] = (int) ($targetUser['id'] ?? 0);

                                $updateUserStmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?');
                                $updateUserStmt->execute($setValues);
                            } else {
                                $generatedPassword = $newPassword !== '' ? $newPassword : '123456';
                                $userColumns = ['name', 'email', 'password'];
                                $userValues = [$name, $loginEmail, password_hash($generatedPassword, PASSWORD_DEFAULT)];
                                if ($usersStatusColumn !== null) {
                                    $userColumns[] = $usersStatusColumn;
                                    $userValues[] = 'active';
                                }
                                if ($usersRoleColumn !== null) {
                                    $userColumns[] = $usersRoleColumn;
                                    $userValues[] = 'admin';
                                }
                                if ($usersAdminCreatedColumn !== null) {
                                    $userColumns[] = $usersAdminCreatedColumn;
                                    $userValues[] = 1;
                                }

                                $placeholders = implode(', ', array_fill(0, count($userColumns), '?'));
                                $insertUserSql = 'INSERT INTO users (' . implode(', ', $userColumns) . ') VALUES (' . $placeholders . ')';
                                $insertUserStmt = $pdo->prepare($insertUserSql);
                                $insertUserStmt->execute($userValues);
                            }

                            $pdo->commit();
                            $crudMessage = 'Đã cập nhật nhân viên thành công.';
                        } catch (Throwable $updateError) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            throw $updateError;
                        }
                    }
                }
            }

            if ($action === 'delete_staff') {
                $id = trim((string) ($_POST['staff_id'] ?? ''));
                if ($id === '') {
                    $crudError = 'Không xác định được nhân viên để xóa.';
                } else {
                    $staffEmailStmt = $pdo->prepare('SELECT UserName FROM nhanvien WHERE MaNV = ? LIMIT 1');
                    $staffEmailStmt->execute([$id]);
                    $staffEmailRow = $staffEmailStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    $staffEmail = strtolower(trim((string) pickStaffValue($staffEmailRow, ['UserName', 'username', 'user_name'], '')));

                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare('DELETE FROM nhanvien WHERE MaNV = ?');
                        $stmt->execute([$id]);

                        if ($staffEmail !== '') {
                            $deleteUserStmt = $pdo->prepare('DELETE FROM users WHERE email = ? LIMIT 1');
                            $deleteUserStmt->execute([$staffEmail]);
                        }

                        $pdo->commit();
                        $crudMessage = 'Đã xóa nhân viên thành công.';
                    } catch (Throwable $deleteError) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $deleteError;
                    }
                }
            }
        } catch (Throwable $postError) {
            $crudError = $postError->getMessage();
        }
    }

    $rows = $pdo->query("SELECT * FROM nhanvien")->fetchAll();
    foreach ($rows as $row) {
        $role = (string) pickStaffValue($row, ['tenchucvu', 'ten_chuc_vu', 'chucvu', 'role'], '');
        $maCv = (string) pickStaffValue($row, ['machucvu', 'ma_chuc_vu', 'macv'], '');
        if ($role === '' && isset($chucVuMap[$maCv])) {
            $role = $chucVuMap[$maCv];
        }

        $dateRaw = (string) pickStaffValue($row, ['namsinh', 'nam_sinh', 'ngayvao', 'ngay_vao', 'ngaylam', 'created_at'], '');
        $birthDateInput = normalizeStaffDateInputValue($dateRaw);
        $dateDisplay = formatStaffDateDisplay($dateRaw);
        $staffId = (string) pickStaffValue($row, ['manhanvien', 'ma_nhan_vien', 'manv', 'id'], '');
        $maBp = (string) pickStaffValue($row, ['mabp', 'ma_bp'], '');
        $boPhan = isset($boPhanMap[$maBp]) ? (string) $boPhanMap[$maBp] : '';
        $hinhAnh = $staffImageColumn !== null
            ? trim((string) pickStaffValue($row, [$staffImageColumn], ''))
            : '';
        $hinhAnhDisplay = $hinhAnh !== '' ? $hinhAnh : '../TrangUser/ack.png';

        $staff[] = [
            'id' => $staffId,
            'name' => (string) pickStaffValue($row, ['tennv', 'tennhanvien', 'ten_nhan_vien', 'hoten', 'ten', 'name']),
            'email' => (string) pickStaffValue($row, ['username', 'user_name', 'email', 'mail'], ''),
            'role_code' => $maCv,
            'role' => $role,
            'department_code' => $maBp,
            'department' => $boPhan,
            'phone' => (string) pickStaffValue($row, ['sdtnv', 'sdt', 'sodienthoai', 'so_dien_thoai', 'phone'], ''),
            'status' => (string) pickStaffValue($row, ['gioitinhnv', 'gioi_tinh_nv', 'gioitinh', 'trangthai', 'trang_thai', 'status'], ''),
            'birth_date' => $birthDateInput,
            'date' => $dateDisplay,
            'image' => $hinhAnh,
            'image_display' => $hinhAnhDisplay,
        ];
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$totalStaff = count($staff);
$activeStaff = 0;
$pausedStaff = 0;

$chucVuOptions = [];
if (isset($chucVuMap) && is_array($chucVuMap)) {
    foreach ($chucVuMap as $maCvOption => $tenCvOption) {
        if (trim((string) $maCvOption) === '') {
            continue;
        }
        $chucVuOptions[] = [
            'code' => (string) $maCvOption,
            'name' => (string) $tenCvOption,
        ];
    }
}

$boPhanOptions = [];
if (isset($boPhanMap) && is_array($boPhanMap)) {
    foreach ($boPhanMap as $maBpOption => $tenBpOption) {
        if (trim((string) $maBpOption) === '') {
            continue;
        }
        $boPhanOptions[] = [
            'code' => (string) $maBpOption,
            'name' => (string) $tenBpOption,
        ];
    }
}

foreach ($staff as $member) {
    $status = strtolower((string) $member['status']);
    if (str_contains($status, 'nữ') || str_contains($status, 'nu')) {
        $pausedStaff++;
    } elseif (str_contains($status, 'nam')) {
        $activeStaff++;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Admin - Quản lý nhân viên</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --primary-blue: #007bff;
        --bg-light: #f5f7fa;
        --text-dark: #344767;
        --sidebar-width: 260px;
        --admin-layout-gap: 10px;
        --admin-content-inline-padding: 10px;
    }

    body {
        font-family: 'Roboto', sans-serif;
        background-color: var(--bg-light);
        height: 100vh;
        overflow: hidden;
    }

    /* --- SIDEBAR --- */
    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        background: white;
        padding: 20px;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        z-index: 100;
        overflow-y: auto;
        overflow-x: hidden;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    .sidebar::-webkit-scrollbar {
        display: none;
    }

    .brand-logo {
        display: flex;
        align-items: center;
        margin-bottom: 40px;
        padding-left: 10px;
        flex-shrink: 0;
    }

    .sidebar nav {
        flex: 1;
        overflow-y: auto;
        margin-right: -10px;
        padding-right: 10px;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    .sidebar nav::-webkit-scrollbar {
        display: none;
    }

    .nav-item {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        margin-bottom: 8px;
        border-radius: 8px;
        color: #67748e;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .nav-item:hover {
        background-color: #f0f2f5;
        color: var(--text-dark);
    }

    .nav-item.active {
        background-color: var(--primary-blue);
        color: white;
        box-shadow: 0 4px 6px rgba(0, 123, 255, 0.3);
    }

    .nav-item i {
        width: 25px;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .logout-btn {
        background: var(--primary-blue);
        color: white;
        text-align: center;
        padding: 12px;
        border-radius: 8px;
        font-weight: bold;
        text-decoration: none;
        margin-top: 20px;
    }

    /* --- MAIN CONTENT --- */
    .main-content {
        margin-left: calc(var(--sidebar-width) + 20px);
        padding: 20px 20px;
        height: 100vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    /* --- STAFF STAT CARDS --- */
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        height: 100%;
        display: flex;
        align-items: center;
    }

    .icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
        margin-right: 15px;
        flex-shrink: 0;
    }

    .bg-gradient-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }

    .bg-gradient-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .stat-label {
        color: #67748e;
        font-size: 0.9rem;
        font-weight: 500;
        margin-bottom: 4px;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #344767;
        margin-bottom: 0;
        line-height: 1.2;
    }

    .stat-growth {
        font-size: 0.8rem;
        font-weight: bold;
        color: #10b981;
        margin-left: auto;
        white-space: nowrap;
        text-align: right;
    }

    /* --- SEARCH & BUTTON --- */
    .search-input {
        border-radius: 20px;
        padding: 5px 20px;
        border: 1px solid #ddd;
        width: 250px;
    }

    .btn-add {
        background-color: var(--primary-blue);
        color: white;
        border-radius: 8px;
        padding: 10px 20px;
        border: none;
        font-weight: 500;
    }

    /* --- TABLE --- */
    .table-container {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);

        display: flex;
        flex-direction: column;
    }

    .staff-table-shell {
        flex: 1 1 auto;
        min-height: 0;
    }

    .table-scroll-area {
        flex: 1;
        min-height: 0;
        overflow: auto;
    }

    .table thead th {
        background-color: #f8f9fa;
        color: #344767;
        font-weight: 700;
        border-bottom: 1px solid #eee;
        padding: 15px 20px;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .table tbody td {
        padding: 15px 20px;
        vertical-align: middle;
        color: #344767;
        border-bottom: 1px solid #f0f2f5;
    }

    /* Row selection styles */
    .table tbody tr {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .table tbody tr.selected {
        background-color: #cce3ff;
        font-weight: 500;
        border-left: 4px solid #007bff;
        box-shadow: inset 0 0 8px rgba(0, 123, 255, 0.1);
    }

    .table tbody tr.selected td:first-child {
        padding-left: 16px;
    }

    .staff-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid #d8dee9;
        background: #fff;
    }

    /* Detail Panel */
    .detail-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.08);
        z-index: 1500;
        display: none;
    }

    .detail-overlay.show {
        display: block;
    }

    .detail-panel {
        background: white;
        border-radius: 14px;
        border: 1px solid #e9ecef;
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.22);
        padding: 25px;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: min(900px, calc(100vw - 40px));
        max-height: calc(100vh - 80px);
        overflow-y: auto;
        z-index: 1600;
        display: none;
    }

    .detail-panel.show {
        display: block;
        animation: fadeScaleIn 0.25s ease-out;
    }

    @keyframes fadeScaleIn {
        from {
            opacity: 0;
            transform: translate(-50%, -53%);
        }

        to {
            opacity: 1;
            transform: translate(-50%, -50%);
        }
    }

    .detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f2f5;
    }

    .detail-header h5 {
        margin: 0;
        font-weight: 700;
        color: #344767;
        font-size: 1.2rem;
    }

    .detail-content {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        align-items: end;
    }

    .detail-field {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .detail-field label {
        font-weight: 600;
        color: #667eea;
        font-size: 0.85rem;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-field input,
    .detail-field select,
    .detail-field textarea {
        border: 1px solid #d7dce3;
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 0.95rem;
        color: #1f2937;
        background-color: #fff;
        width: 100%;
        min-height: 46px;
    }

    .detail-field input[readonly] {
        background-color: #f3f6fb;
        color: #66748b;
    }

    .detail-field input:focus,
    .detail-field select:focus,
    .detail-field textarea:focus {
        background-color: #fff;
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .detail-field.detail-field--full {
        grid-column: 1 / -1;
    }

    .detail-image-preview {
        width: 100%;
        min-height: 170px;
        border: 1px solid #d7dce3;
        border-radius: 8px;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px;
    }

    .detail-image-preview img {
        max-width: 100%;
        max-height: 150px;
        object-fit: contain;
        border-radius: 8px;
    }

    .detail-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 22px;
        padding-top: 20px;
        border-top: 1px solid #f0f2f5;
    }

    .detail-actions button {
        font-size: 0.9rem;
        font-weight: 500;
        padding: 8px 18px;
    }

    @media (max-width: 1200px) {
        .detail-content {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .detail-content {
            grid-template-columns: 1fr;
        }

        .detail-panel {
            width: calc(100vw - 20px);
            max-height: calc(100vh - 20px);
            padding: 18px;
        }
    }

    /* --- DELETE CONFIRMATION MODAL --- */
    .delete-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.08);
        z-index: 1900;
        display: none;
    }

    .delete-overlay.show {
        display: block;
    }

    .delete-modal {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 12px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18);
        z-index: 2000;
        max-width: 400px;
        width: 90%;
        animation: slideDownDelete 0.3s ease-out;
    }

    .delete-modal.show {
        display: block;
    }

    @keyframes slideDownDelete {
        from {
            opacity: 0;
            transform: translate(-50%, -60%);
        }

        to {
            opacity: 1;
            transform: translate(-50%, -50%);
        }
    }

    .delete-modal-header {
        padding: 20px;
        border-bottom: 1px solid #f0f2f5;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .delete-modal-icon {
        width: 50px;
        height: 50px;
        background: #ffe5e5;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        color: #dc3545;
    }

    .delete-modal-title {
        margin: 0;
        color: #344767;
        font-weight: 700;
        font-size: 1.05rem;
    }

    .delete-modal-body {
        padding: 20px;
        color: #344767;
        line-height: 1.6;
    }

    .delete-modal-footer {
        padding: 20px;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        border-top: 1px solid #f0f2f5;
    }

    .delete-modal-footer button {
        padding: 8px 20px;
        border-radius: 6px;
        border: none;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .delete-modal-footer .btn-cancel {
        background: #e9ecef;
        color: #495057;
    }

    .delete-modal-footer .btn-cancel:hover {
        background: #dee2e6;
    }

    .delete-modal-footer .btn-confirm {
        background: #dc3545;
        color: white;
    }

    .delete-modal-footer .btn-confirm:hover {
        background: #c82333;
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
    }
    </style>
    <link rel="stylesheet" href="admin-unified-ui.css?v=20260414-2">
</head>

<body>

    <div class="sidebar">
        <div class="brand-logo">
            <img src="../TrangUser/ack.png" alt="Logo" height="40">
            <h4 class="fw-bold ms-2 mb-0" style="color: #344767;">Admin</h4>
        </div>
        <nav>
            <a href="admin.php" class="nav-item"><i class="fas fa-chart-bar"></i> Tổng quan</a>
            <a href="admin-sanpham.php" class="nav-item"><i class="fas fa-box"></i> Sản phẩm</a>
            <a href="admin-nhomhang.php" class="nav-item"><i class="fas fa-folder"></i> Nhóm hàng</a>
            <a href="admin-nhaphang.php" class="nav-item"><i class="fas fa-truck-loading"></i> Nhập hàng</a>
            <a href="admin-nhacungcap.php" class="nav-item"><i class="fas fa-building"></i> Nhà cung cấp</a>
            <a href="admin-bophan.php" class="nav-item"><i class="fas fa-sitemap"></i> Bộ phận</a>
            <a href="admin-donhang.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Đơn hàng</a>
            <a href="admin-nhanvien.php" class="nav-item active"><i class="fas fa-user-tie"></i> Nhân viên</a>
            <a href="admin-khachhang.php" class="nav-item"><i class="fas fa-users"></i> Khách hàng</a>
            <a href="admin-voucher.php" class="nav-item"><i class="fas fa-ticket-alt"></i> Voucher</a>
            <a href="admin-caidat.php" class="nav-item"><i class="fas fa-cog"></i> Cài đặt</a>
        </nav>
        <a href="../Login/logout.php" class="logout-btn">Đăng xuất</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-primary">Bảng điều khiển Admin</h4>
            <button class="btn btn-light rounded-circle shadow-sm"><i class="fas fa-times"></i></button>
        </div>

        <div class="d-flex justify-content-between align-items-end mb-4">
            <h3 class="fw-bold mb-0">Quản lý nhân viên</h3>
            <input type="text" class="search-input" placeholder="Tìm kiếm">
        </div>

        <?php if ($dbError !== ''): ?>
        <div class="alert alert-warning" role="alert">
            Không thể kết nối/lấy dữ liệu từ MySQL: <?php echo htmlspecialchars($dbError); ?>
        </div>
        <?php endif; ?>

        <?php if ($crudError !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($crudError); ?>
        </div>
        <?php endif; ?>

        <?php if ($crudMessage !== ''): ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($crudMessage); ?>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center">
                    <div class="icon-box bg-gradient-primary me-3"><i class="fas fa-mars"></i></div>
                    <div>
                        <div class="stat-label">Nhân viên nam</div>
                        <h4 class="stat-value"><?php echo $activeStaff; ?></h4>
                    </div>
                    <div class="stat-growth">Nam</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center">
                    <div class="icon-box bg-gradient-success me-3"><i class="fas fa-venus"></i></div>
                    <div>
                        <div class="stat-label">Nhân viên nữ</div>
                        <h4 class="stat-value"><?php echo $pausedStaff; ?></h4>
                    </div>
                    <div class="stat-growth">Nữ</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center">
                    <div class="icon-box bg-gradient-primary me-3"><i class="fas fa-people-group"></i></div>
                    <div>
                        <div class="stat-label">Tổng nhân viên</div>
                        <h4 class="stat-value"><?php echo $totalStaff; ?></h4>
                    </div>
                    <div class="stat-growth">Tất cả</div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <button class="btn-add mb-0" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                <i class="fas fa-plus me-2"></i> Thêm nhân viên
            </button>
            <button class="btn btn-warning fw-semibold" id="btnEditStaff" disabled>
                <i class="fas fa-pen me-1"></i> Sửa nhân viên
            </button>
            <button class="btn btn-info fw-semibold text-white" id="btnViewStaff" disabled>
                <i class="fas fa-eye me-1"></i> Xem chi tiết
            </button>
            <button class="btn btn-danger fw-semibold" id="btnDeleteStaff" disabled>
                <i class="fas fa-trash me-1"></i> Xóa
            </button>
        </div>

        <div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addStaffModalLabel">Thêm nhân viên</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="addStaffForm" method="post">
                        <input type="hidden" name="crud_action" value="add_staff">
                        <input type="hidden" name="staff_id" id="staffId" value="">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="staffName" class="form-label">Tên nhân viên</label>
                                    <input type="text" class="form-control" id="staffName"
                                        placeholder="Nhập tên nhân viên" name="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="staffEmail" class="form-label">Tên đăng nhập</label>
                                    <input type="email" class="form-control" id="staffEmail"
                                        placeholder="nhanvien@gmail.com" name="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="staffRole" class="form-label">Chức vụ</label>
                                    <select class="form-select" id="staffRole" name="ma_cv" required>
                                        <option value="">Chọn chức vụ</option>
                                        <?php foreach ($chucVuOptions as $roleOption): ?>
                                        <option
                                            value="<?php echo htmlspecialchars((string) ($roleOption['code'] ?? '')); ?>">
                                            <?php echo htmlspecialchars((string) ($roleOption['code'] ?? '') . ' - ' . (string) ($roleOption['name'] ?? '')); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="staffDepartment" class="form-label">Bộ phận</label>
                                    <select class="form-select" id="staffDepartment" name="ma_bp" required>
                                        <option value="">Chọn bộ phận</option>
                                        <?php foreach ($boPhanOptions as $departmentOption): ?>
                                        <option
                                            value="<?php echo htmlspecialchars((string) ($departmentOption['code'] ?? '')); ?>">
                                            <?php echo htmlspecialchars((string) ($departmentOption['code'] ?? '') . ' - ' . (string) ($departmentOption['name'] ?? '')); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="staffPhone" class="form-label">Điện thoại</label>
                                    <input type="text" class="form-control" id="staffPhone" placeholder="0xxxxxxxxx"
                                        name="phone" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="staffImage" class="form-label">Link ảnh</label>
                                    <input type="text" class="form-control" id="staffImage" name="hinh_anh"
                                        placeholder="VD: ../AnhTrangChu/staff-admin.png">
                                </div>
                                <div class="col-md-6">
                                    <label for="staffPassword" class="form-label">Mật khẩu đăng nhập</label>
                                    <input type="password" class="form-control" id="staffPassword" name="password"
                                        minlength="6" placeholder="Ít nhất 6 ký tự" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="staffStatus" class="form-label">Giới tính</label>
                                    <select id="staffStatus" class="form-select" name="status" required>
                                        <option value="">Chọn giới tính</option>
                                        <option value="Nam">Nam</option>
                                        <option value="Nữ">Nữ</option>
                                        <option value="Khác">Khác</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="staffDate" class="form-label">Ngày sinh</label>
                                    <input type="date" class="form-control" id="staffDate" name="date" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="submit" class="btn btn-primary">Lưu nhân viên</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="detailOverlay" class="detail-overlay" onclick="closeDetailPanel()"></div>

        <!-- Detail Panel -->
        <div id="detailPanel" class="detail-panel">
            <div class="detail-header">
                <h5>Chi tiết nhân viên</h5>
                <button type="button" class="btn-close" onclick="closeDetailPanel()"></button>
            </div>
            <div class="detail-content">
                <div class="detail-field">
                    <label>Mã nhân viên</label>
                    <input type="text" id="detailId" readonly>
                </div>
                <div class="detail-field">
                    <label>Tên nhân viên</label>
                    <input type="text" id="detailName">
                </div>
                <div class="detail-field">
                    <label>Tên đăng nhập</label>
                    <input type="email" id="detailEmail">
                </div>
                <div class="detail-field">
                    <label>Số điện thoại</label>
                    <input type="text" id="detailPhone">
                </div>
                <div class="detail-field">
                    <label>Chức vụ</label>
                    <select id="detailRole" class="form-select">
                        <option value="">Chọn chức vụ</option>
                        <?php foreach ($chucVuOptions as $roleOption): ?>
                        <option value="<?php echo htmlspecialchars((string) ($roleOption['code'] ?? '')); ?>">
                            <?php echo htmlspecialchars((string) ($roleOption['code'] ?? '') . ' - ' . (string) ($roleOption['name'] ?? '')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="detail-field">
                    <label>Bộ phận</label>
                    <select id="detailDepartment" class="form-select">
                        <option value="">Chọn bộ phận</option>
                        <?php foreach ($boPhanOptions as $departmentOption): ?>
                        <option value="<?php echo htmlspecialchars((string) ($departmentOption['code'] ?? '')); ?>">
                            <?php echo htmlspecialchars((string) ($departmentOption['code'] ?? '') . ' - ' . (string) ($departmentOption['name'] ?? '')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="detail-field">
                    <label>Link ảnh</label>
                    <input type="text" id="detailImage" placeholder="VD: ../AnhTrangChu/staff-admin.png">
                </div>
                <div class="detail-field detail-field--full">
                    <label>Xem ảnh</label>
                    <div class="detail-image-preview">
                        <img id="detailImagePreview" src="../TrangUser/ack.png" alt="Ảnh nhân viên"
                            onerror="this.src=this.dataset.fallback || '../TrangUser/ack.png'"
                            data-fallback="../TrangUser/ack.png">
                    </div>
                </div>
                <div class="detail-field">
                    <label>Giới tính</label>
                    <select id="detailGender" class="form-select">
                        <option value="">Chọn giới tính</option>
                        <option value="Nam">Nam</option>
                        <option value="Nữ">Nữ</option>
                        <option value="Khác">Khác</option>
                    </select>
                </div>
                <div class="detail-field">
                    <label>Ngày sinh</label>
                    <input type="date" id="detailBirthYear">
                </div>
                <div class="detail-field detail-field--full">
                    <label>Mật khẩu mới (nếu đổi)</label>
                    <input type="password" id="detailPassword" placeholder="Để trống nếu không đổi">
                </div>
            </div>
            <div class="detail-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDetailPanel()">Đóng</button>
                <button type="button" class="btn btn-primary" id="btnDetailSave">Lưu thay đổi</button>
            </div>
        </div>

        <div id="deleteOverlay" class="delete-overlay" onclick="closeDeleteModal()"></div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="delete-modal">
            <div class="delete-modal-header">
                <div class="delete-modal-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h5 class="delete-modal-title">Xác nhận xóa</h5>
            </div>
            <div class="delete-modal-body">
                Bạn có chắc chắn muốn xóa nhân viên này? Hành động này không thể hoàn tác.
            </div>
            <div class="delete-modal-footer">
                <button class="btn-cancel" onclick="closeDeleteModal()">Hủy</button>
                <button class="btn-confirm" id="confirmDeleteBtn">Xóa</button>
            </div>
        </div>

        <div class="table-container staff-table-shell">
            <div class="p-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Danh sách nhân viên</h5>
                <i class="fas fa-download text-muted"></i>
            </div>
            <div class="table-scroll-area">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Tên nhân viên</th>
                            <th>Ảnh</th>
                            <th>Tên đăng nhập</th>
                            <th>Chức vụ</th>
                            <th>Bộ phận</th>
                            <th>Điện thoại</th>
                            <th>Giới tính</th>
                            <th>Ngày sinh</th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody">
                        <?php if (count($staff) === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Chưa có nhân viên nào.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach($staff as $index => $s): ?>
                        <tr class="staff-row"
                            data-id="<?php echo htmlspecialchars($s['id'] !== '' ? $s['id'] : ('NV' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT))); ?>"
                            data-name="<?php echo htmlspecialchars($s['name'] ?? ''); ?>"
                            data-email="<?php echo htmlspecialchars($s['email'] ?? ''); ?>"
                            data-role-code="<?php echo htmlspecialchars((string) ($s['role_code'] ?? '')); ?>"
                            data-role="<?php echo htmlspecialchars($s['role'] ?? ''); ?>"
                            data-department-code="<?php echo htmlspecialchars((string) ($s['department_code'] ?? '')); ?>"
                            data-department="<?php echo htmlspecialchars((string) ($s['department'] ?? '')); ?>"
                            data-phone="<?php echo htmlspecialchars($s['phone'] ?? ''); ?>"
                            data-status="<?php echo htmlspecialchars($s['status'] ?? 'Hoạt động'); ?>"
                            data-birth-date="<?php echo htmlspecialchars((string) ($s['birth_date'] ?? '')); ?>"
                            data-date="<?php echo htmlspecialchars($s['date'] ?? ''); ?>"
                            data-image="<?php echo htmlspecialchars((string) ($s['image'] ?? ''), ENT_QUOTES); ?>">
                            <td class="fw-bold"><?php echo htmlspecialchars((string) ($s['name'] ?? '')); ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars((string) ($s['image_display'] ?? '../TrangUser/ack.png'), ENT_QUOTES); ?>"
                                    alt="<?php echo htmlspecialchars((string) ($s['name'] ?? 'Nhân viên'), ENT_QUOTES); ?>"
                                    class="staff-avatar" onerror="this.src='../TrangUser/ack.png'">
                            </td>
                            <td><?php echo htmlspecialchars((string) ($s['email'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($s['role'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($s['department'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($s['phone'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($s['status'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($s['date'] ?? '')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <form method="post" id="staffEditForm" class="d-none">
            <input type="hidden" name="crud_action" value="update_staff">
            <input type="hidden" name="staff_id" id="editStaffId">
            <input type="hidden" name="name" id="editStaffName">
            <input type="hidden" name="email" id="editStaffEmail">
            <input type="hidden" name="phone" id="editStaffPhone">
            <input type="hidden" name="ma_cv" id="editStaffRoleCode">
            <input type="hidden" name="ma_bp" id="editStaffDepartmentCode">
            <input type="hidden" name="status" id="editStaffStatus">
            <input type="hidden" name="date" id="editStaffDate">
            <input type="hidden" name="password" id="editStaffPassword">
            <input type="hidden" name="hinh_anh" id="editStaffImage">
        </form>

        <form method="post" id="staffDeleteForm" class="d-none">
            <input type="hidden" name="crud_action" value="delete_staff">
            <input type="hidden" name="staff_id" id="deleteStaffId">
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let selectedStaffId = null;
    let pendingDeleteRow = null;
    let isStaffDetailReadOnly = false;
    const staffRoleMap = <?php echo json_encode($chucVuMap ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const staffDepartmentMap =
        <?php echo json_encode($boPhanMap ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function syncBodyScrollLock() {
        const isDetailOpen = document.getElementById('detailPanel').classList.contains('show');
        const isDeleteOpen = document.getElementById('deleteModal').classList.contains('show');
        document.body.style.overflow = (isDetailOpen || isDeleteOpen) ? 'hidden' : '';
    }

    function bindStaffRowEvents(row) {
        row.addEventListener('click', function() {
            document.querySelectorAll('.staff-row').forEach(r => r.classList.remove('selected'));
            this.classList.add('selected');
            selectedStaffId = this.getAttribute('data-id');
            document.getElementById('btnEditStaff').disabled = false;
            document.getElementById('btnViewStaff').disabled = false;
            document.getElementById('btnDeleteStaff').disabled = false;
        });
    }

    document.querySelectorAll('.staff-row').forEach(bindStaffRowEvents);

    function updateStaffDetailImagePreview(imagePath) {
        const previewImage = document.getElementById('detailImagePreview');
        if (!previewImage) {
            return;
        }

        const fallback = previewImage.dataset.fallback || '../TrangUser/ack.png';
        const normalizedPath = (imagePath || '').trim();
        previewImage.src = normalizedPath !== '' ? normalizedPath : fallback;
    }

    function setStaffDetailReadOnly(readOnly) {
        isStaffDetailReadOnly = !!readOnly;

        const editableFields = ['detailName', 'detailEmail', 'detailPhone', 'detailRole', 'detailDepartment',
            'detailImage', 'detailGender', 'detailBirthYear', 'detailPassword'
        ];
        editableFields.forEach((fieldId) => {
            const field = document.getElementById(fieldId);
            if (!field) {
                return;
            }
            if (field.tagName === 'SELECT') {
                field.disabled = isStaffDetailReadOnly;
            } else {
                field.readOnly = isStaffDetailReadOnly;
            }
        });

        const saveBtn = document.getElementById('btnDetailSave');
        if (saveBtn) {
            saveBtn.style.display = isStaffDetailReadOnly ? 'none' : 'inline-block';
        }

        const passwordField = document.getElementById('detailPassword');
        if (passwordField) {
            passwordField.value = '';
        }
    }

    function ensureSelectOption(selectId, rawValue, labelMap = {}) {
        const select = document.getElementById(selectId);
        if (!select) {
            return;
        }

        const normalizedValue = (rawValue || '').trim();
        if (normalizedValue === '') {
            select.value = '';
            return;
        }

        const exists = Array.from(select.options).some((option) => option.value === normalizedValue);
        if (!exists) {
            const label = labelMap[normalizedValue] ? `${normalizedValue} - ${labelMap[normalizedValue]}` :
                normalizedValue;
            select.appendChild(new Option(label, normalizedValue, true, true));
        }

        select.value = normalizedValue;
    }

    function showDetailPanel(id, name, email, phone, roleCode, departmentCode, image, gender, birthDate, readOnly =
        false) {
        document.getElementById('detailId').value = id;
        document.getElementById('detailName').value = name;
        document.getElementById('detailEmail').value = email;
        document.getElementById('detailPhone').value = phone;
        ensureSelectOption('detailRole', roleCode, staffRoleMap);
        ensureSelectOption('detailDepartment', departmentCode, staffDepartmentMap);
        document.getElementById('detailImage').value = image || '';
        updateStaffDetailImagePreview(image || '');
        ensureSelectOption('detailGender', (gender || '').trim());
        document.getElementById('detailBirthYear').value = birthDate || '';
        setStaffDetailReadOnly(readOnly);
        document.getElementById('detailOverlay').classList.add('show');
        document.getElementById('detailPanel').classList.add('show');
        syncBodyScrollLock();
    }

    function closeDetailPanel() {
        document.getElementById('detailPanel').classList.remove('show');
        document.getElementById('detailOverlay').classList.remove('show');
        syncBodyScrollLock();
    }

    // Edit button click
    document.getElementById('btnEditStaff').addEventListener('click', function() {
        const selectedRow = document.querySelector('.staff-row.selected');
        if (!selectedRow) {
            alert('Vui lòng chọn nhân viên để sửa');
            return;
        }

        const id = selectedRow.getAttribute('data-id');
        const name = selectedRow.getAttribute('data-name');
        const email = selectedRow.getAttribute('data-email');
        const phone = selectedRow.getAttribute('data-phone');
        const roleCode = selectedRow.getAttribute('data-role-code');
        const departmentCode = selectedRow.getAttribute('data-department-code');
        const image = selectedRow.getAttribute('data-image');
        const gender = selectedRow.getAttribute('data-status');
        const birthDate = selectedRow.getAttribute('data-birth-date') || '';
        showDetailPanel(id, name, email, phone, roleCode, departmentCode, image, gender, birthDate, false);
    });

    document.getElementById('btnViewStaff').addEventListener('click', function() {
        const selectedRow = document.querySelector('.staff-row.selected');
        if (!selectedRow) {
            alert('Vui lòng chọn nhân viên để xem chi tiết');
            return;
        }

        const id = selectedRow.getAttribute('data-id');
        const name = selectedRow.getAttribute('data-name');
        const email = selectedRow.getAttribute('data-email');
        const phone = selectedRow.getAttribute('data-phone');
        const roleCode = selectedRow.getAttribute('data-role-code');
        const departmentCode = selectedRow.getAttribute('data-department-code');
        const image = selectedRow.getAttribute('data-image');
        const gender = selectedRow.getAttribute('data-status');
        const birthDate = selectedRow.getAttribute('data-birth-date') || '';
        showDetailPanel(id, name, email, phone, roleCode, departmentCode, image, gender, birthDate, true);
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeDetailPanel();
            closeDeleteModal();
        }
    });

    document.getElementById('addStaffModal').addEventListener('show.bs.modal', function() {
        closeDetailPanel();
    });

    const detailImageInput = document.getElementById('detailImage');
    if (detailImageInput) {
        detailImageInput.addEventListener('input', function() {
            updateStaffDetailImagePreview(detailImageInput.value);
        });
    }

    // Save button click - reads from detail panel
    document.getElementById('btnDetailSave').addEventListener('click', function() {
        if (isStaffDetailReadOnly) {
            return;
        }

        const id = document.getElementById('detailId').value;
        const name = document.getElementById('detailName').value.trim();
        const email = document.getElementById('detailEmail').value.trim();
        const phone = document.getElementById('detailPhone').value.trim();
        const roleCode = document.getElementById('detailRole').value.trim();
        const departmentCode = document.getElementById('detailDepartment').value.trim();
        const gender = document.getElementById('detailGender').value.trim();
        const birthDate = document.getElementById('detailBirthYear').value.trim();
        const newPassword = document.getElementById('detailPassword').value;
        const image = document.getElementById('detailImage').value.trim();

        // Validation
        if (!name) {
            alert('Tên nhân viên không được để trống');
            return;
        }
        if (!email) {
            alert('Tên đăng nhập không được để trống');
            return;
        }
        if (!/^\S+@\S+\.\S+$/.test(email)) {
            alert('Email đăng nhập không hợp lệ');
            return;
        }
        if (!roleCode || !departmentCode) {
            alert('Vui lòng chọn chức vụ và bộ phận.');
            return;
        }
        if (!gender) {
            alert('Vui lòng chọn giới tính.');
            return;
        }
        if (!birthDate) {
            alert('Vui lòng chọn ngày sinh.');
            return;
        }
        if (newPassword && newPassword.length < 6) {
            alert('Mật khẩu mới phải có ít nhất 6 ký tự.');
            return;
        }

        document.getElementById('editStaffId').value = id;
        document.getElementById('editStaffName').value = name;
        document.getElementById('editStaffEmail').value = email;
        document.getElementById('editStaffPhone').value = phone;
        document.getElementById('editStaffRoleCode').value = roleCode;
        document.getElementById('editStaffDepartmentCode').value = departmentCode;
        document.getElementById('editStaffStatus').value = gender;
        document.getElementById('editStaffDate').value = birthDate;
        document.getElementById('editStaffPassword').value = newPassword;
        document.getElementById('editStaffImage').value = image;
        document.getElementById('staffEditForm').submit();
    });

    // Delete modal functions
    function showDeleteModal(row) {
        pendingDeleteRow = row;
        document.getElementById('deleteOverlay').classList.add('show');
        document.getElementById('deleteModal').classList.add('show');
        syncBodyScrollLock();
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
        document.getElementById('deleteOverlay').classList.remove('show');
        pendingDeleteRow = null;
        syncBodyScrollLock();
    }

    // Delete button click
    document.getElementById('btnDeleteStaff').addEventListener('click', function() {
        const selectedRow = document.querySelector('.staff-row.selected');
        if (!selectedRow) {
            alert('Vui lòng chọn nhân viên để xóa');
            return;
        }
        showDeleteModal(selectedRow);
    });

    // Confirm delete button click
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (!pendingDeleteRow) {
            return;
        }

        const id = pendingDeleteRow.getAttribute('data-id') || '';
        if (!id) {
            return;
        }

        document.getElementById('deleteStaffId').value = id;
        document.getElementById('staffDeleteForm').submit();
    });

    // Close modal when clicking outside
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });

    // Add staff (UI fallback mode)
    document.getElementById('addStaffForm').addEventListener('submit', function(e) {
        const nameInput = document.getElementById('staffName');
        const emailInput = document.getElementById('staffEmail');
        const roleInput = document.getElementById('staffRole');
        const departmentInput = document.getElementById('staffDepartment');
        const phoneInput = document.getElementById('staffPhone');

        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        const role = roleInput.value.trim();
        const department = departmentInput.value.trim();
        const phone = phoneInput.value.trim();
        const status = document.getElementById('staffStatus').value.trim();
        const birthDate = document.getElementById('staffDate').value.trim();
        const password = document.getElementById('staffPassword').value;

        if (!name || !email || !role || !department || !phone || !status || !birthDate || !password) {
            e.preventDefault();
            alert('Vui lòng nhập đầy đủ thông tin nhân viên.');
            return;
        }
        if (!/^\S+@\S+\.\S+$/.test(email)) {
            e.preventDefault();
            alert('Email đăng nhập không hợp lệ.');
            return;
        }
        if (password.length < 6) {
            e.preventDefault();
            alert('Mật khẩu phải có ít nhất 6 ký tự.');
            return;
        }
    });
    </script>
    <script src="admin-search.js?v=20260414-2"></script>
</body>

</html>