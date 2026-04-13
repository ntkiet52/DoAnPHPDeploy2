<?php
require_once __DIR__ . '/../Login/admin_auth.php';

function pickVoucherValue(array $row, array $keys, $default = '') {
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

function getExistingColumns(PDO $pdo, string $table): array {
    static $cache = [];

    if (isset($cache[$table])) {
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

function formatDateInput(?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
        return $raw;
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

function formatDateDisplay(?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date('d/m/Y', $timestamp);
}

function generateNextVoucherCode(PDO $pdo, string $table, ?string $codeColumn): string {
    if ($codeColumn === null) {
        return 'VC01';
    }

    $rows = $pdo->query("SELECT `{$codeColumn}` FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
    $usedNumbers = [];

    foreach ($rows as $rowCode) {
        $code = strtoupper(trim((string) $rowCode));
        if ($code === '') {
            continue;
        }

        if (preg_match('/(\d+)$/', $code, $matches) !== 1) {
            continue;
        }

        $usedNumbers[(int) $matches[1]] = true;
    }

    $nextNumber = 1;
    while (isset($usedNumbers[$nextNumber])) {
        $nextNumber++;
    }

    return 'VC' . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
}

function normalizeVoucherType(string $type): string {
    $raw = strtolower(trim($type));
    return $raw === 'percent' ? 'percent' : 'fixed';
}

function normalizeVoucherStatus(string $status): string {
    $raw = strtolower(trim($status));
    return in_array($raw, ['active', 'inactive'], true) ? $raw : 'active';
}

$vouchers = [];
$dbError = '';
$crudMessage = '';
$crudError = '';
$nextVoucherCode = 'VC01';

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

    $pdo->exec("CREATE TABLE IF NOT EXISTS voucher (
        id_voucher INT AUTO_INCREMENT PRIMARY KEY,
        ma_voucher VARCHAR(100) NOT NULL UNIQUE,
        ten_voucher VARCHAR(255) NOT NULL,
        kieu_giam VARCHAR(20) NOT NULL DEFAULT 'fixed',
        gia_tri_giam DECIMAL(12,2) NOT NULL DEFAULT 0,
        tien_toi_thieu DECIMAL(12,2) NOT NULL DEFAULT 0,
        so_luong_toi_da INT NOT NULL DEFAULT 0,
        so_luong_da_su_dung INT NOT NULL DEFAULT 0,
        ngay_bat_dau DATETIME NOT NULL,
        ngay_ket_thuc DATETIME NOT NULL,
        trang_thai VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $voucherColumns = getExistingColumns($pdo, 'voucher');
    $idCol = pickExistingColumn($voucherColumns, ['id_voucher', 'idvoucher', 'id']);
    $codeCol = pickExistingColumn($voucherColumns, ['ma_voucher', 'mavoucher', 'code']);
    $nameCol = pickExistingColumn($voucherColumns, ['ten_voucher', 'tenvoucher', 'name']);
    $typeCol = pickExistingColumn($voucherColumns, ['kieu_giam', 'kieugiam', 'discount_type', 'type']);
    $valueCol = pickExistingColumn($voucherColumns, ['gia_tri_giam', 'giatrigiam', 'discount_value', 'value']);
    $minCol = pickExistingColumn($voucherColumns, ['tien_toi_thieu', 'tientoithieu', 'min_order', 'min_amount']);
    $maxUseCol = pickExistingColumn($voucherColumns, ['so_luong_toi_da', 'soluong_toi_da', 'max_usage', 'usage_limit']);
    $usedCol = pickExistingColumn($voucherColumns, ['so_luong_da_su_dung', 'so_luong_da_dung', 'used_count']);
    $startCol = pickExistingColumn($voucherColumns, ['ngay_bat_dau', 'ngaybatdau', 'start_date']);
    $endCol = pickExistingColumn($voucherColumns, ['ngay_ket_thuc', 'ngayketthuc', 'end_date']);
    $statusCol = pickExistingColumn($voucherColumns, ['trang_thai', 'trangthai', 'status']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string) ($_POST['crud_action'] ?? ''));

        if ($action === 'add_voucher') {
            $code = strtoupper(trim((string) ($_POST['voucher_code'] ?? '')));
            $name = trim((string) ($_POST['voucher_name'] ?? ''));
            $type = normalizeVoucherType((string) ($_POST['voucher_type'] ?? 'fixed'));
            $value = (float) ($_POST['voucher_value'] ?? 0);
            $minOrder = (float) ($_POST['voucher_min_order'] ?? 0);
            $maxUse = (int) ($_POST['voucher_max_use'] ?? 0);
            $startDate = trim((string) ($_POST['voucher_start_date'] ?? ''));
            $endDate = trim((string) ($_POST['voucher_end_date'] ?? ''));
            $status = normalizeVoucherStatus((string) ($_POST['voucher_status'] ?? 'active'));

            if ($code === '') {
                $code = generateNextVoucherCode($pdo, 'voucher', $codeCol);
            }

            if ($code === '' || $name === '' || $startDate === '' || $endDate === '') {
                $crudError = 'Vui lòng nhập đầy đủ thông tin voucher.';
            } else if ($value <= 0) {
                $crudError = 'Giá trị giảm phải lớn hơn 0.';
            } else if ($type === 'percent' && $value > 100) {
                $crudError = 'Voucher phần trăm chỉ được từ 1 đến 100.';
            } else if (strtotime($startDate) === false || strtotime($endDate) === false || strtotime($startDate) > strtotime($endDate)) {
                $crudError = 'Thời gian bắt đầu/kết thúc không hợp lệ.';
            } else {
                $insertColumns = [];
                $insertPlaceholders = [];
                $params = [];

                $fieldMap = [
                    $codeCol => $code,
                    $nameCol => $name,
                    $typeCol => $type,
                    $valueCol => $value,
                    $minCol => $minOrder,
                    $maxUseCol => $maxUse,
                    $startCol => $startDate,
                    $endCol => $endDate,
                    $statusCol => $status,
                    $usedCol => 0,
                ];

                foreach ($fieldMap as $column => $fieldValue) {
                    if ($column === null) {
                        continue;
                    }

                    $paramName = ':p_' . count($params);
                    $insertColumns[] = "`{$column}`";
                    $insertPlaceholders[] = $paramName;
                    $params[$paramName] = $fieldValue;
                }

                if (count($insertColumns) === 0) {
                    $crudError = 'Không tìm thấy cột dữ liệu voucher để thêm mới.';
                } else {
                    try {
                        $sql = 'INSERT INTO voucher (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $crudMessage = 'Đã thêm voucher thành công.';
                    } catch (Throwable $e) {
                        $crudError = 'Không thể thêm voucher: ' . $e->getMessage();
                    }
                }
            }
        }

        if ($action === 'update_voucher') {
            $voucherId = (int) ($_POST['voucher_id'] ?? 0);
            $code = strtoupper(trim((string) ($_POST['voucher_code'] ?? '')));
            $name = trim((string) ($_POST['voucher_name'] ?? ''));
            $type = normalizeVoucherType((string) ($_POST['voucher_type'] ?? 'fixed'));
            $value = (float) ($_POST['voucher_value'] ?? 0);
            $minOrder = (float) ($_POST['voucher_min_order'] ?? 0);
            $maxUse = (int) ($_POST['voucher_max_use'] ?? 0);
            $startDate = trim((string) ($_POST['voucher_start_date'] ?? ''));
            $endDate = trim((string) ($_POST['voucher_end_date'] ?? ''));
            $status = normalizeVoucherStatus((string) ($_POST['voucher_status'] ?? 'active'));

            if ($voucherId <= 0 || $code === '' || $name === '' || $startDate === '' || $endDate === '') {
                $crudError = 'Thông tin cập nhật voucher chưa hợp lệ.';
            } else if ($value <= 0) {
                $crudError = 'Giá trị giảm phải lớn hơn 0.';
            } else if ($type === 'percent' && $value > 100) {
                $crudError = 'Voucher phần trăm chỉ được từ 1 đến 100.';
            } else if (strtotime($startDate) === false || strtotime($endDate) === false || strtotime($startDate) > strtotime($endDate)) {
                $crudError = 'Thời gian bắt đầu/kết thúc không hợp lệ.';
            } else if ($idCol === null) {
                $crudError = 'Không tìm thấy khóa chính voucher để cập nhật.';
            } else {
                $setParts = [];
                $params = [':id' => $voucherId];

                $fieldMap = [
                    $codeCol => $code,
                    $nameCol => $name,
                    $typeCol => $type,
                    $valueCol => $value,
                    $minCol => $minOrder,
                    $maxUseCol => $maxUse,
                    $startCol => $startDate,
                    $endCol => $endDate,
                    $statusCol => $status,
                ];

                foreach ($fieldMap as $column => $fieldValue) {
                    if ($column === null) {
                        continue;
                    }

                    $paramName = ':p_' . count($params);
                    $setParts[] = "`{$column}` = {$paramName}";
                    $params[$paramName] = $fieldValue;
                }

                if (count($setParts) === 0) {
                    $crudError = 'Không tìm thấy cột dữ liệu voucher để cập nhật.';
                } else {
                    try {
                        $sql = 'UPDATE voucher SET ' . implode(', ', $setParts) . " WHERE `{$idCol}` = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $crudMessage = 'Đã cập nhật voucher thành công.';
                    } catch (Throwable $e) {
                        $crudError = 'Không thể cập nhật voucher: ' . $e->getMessage();
                    }
                }
            }
        }

        if ($action === 'delete_voucher') {
            $voucherId = (int) ($_POST['voucher_id'] ?? 0);

            if ($voucherId <= 0 || $idCol === null) {
                $crudError = 'Không xác định được voucher cần xóa.';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM voucher WHERE `{$idCol}` = :id");
                    $stmt->execute([':id' => $voucherId]);
                    $crudMessage = 'Đã xóa voucher thành công.';
                } catch (Throwable $e) {
                    $crudError = 'Không thể xóa voucher: ' . $e->getMessage();
                }
            }
        }
    }

    $rows = $pdo->query('SELECT * FROM voucher ORDER BY id_voucher DESC')->fetchAll();
    foreach ($rows as $row) {
        $rawType = normalizeVoucherType((string) pickVoucherValue($row, [$typeCol ?? 'kieu_giam'], 'fixed'));
        $rawStatus = normalizeVoucherStatus((string) pickVoucherValue($row, [$statusCol ?? 'trang_thai'], 'active'));
        $startRaw = (string) pickVoucherValue($row, [$startCol ?? 'ngay_bat_dau'], '');
        $endRaw = (string) pickVoucherValue($row, [$endCol ?? 'ngay_ket_thuc'], '');

        $vouchers[] = [
            'id' => (int) pickVoucherValue($row, [$idCol ?? 'id_voucher'], 0),
            'code' => (string) pickVoucherValue($row, [$codeCol ?? 'ma_voucher'], ''),
            'name' => (string) pickVoucherValue($row, [$nameCol ?? 'ten_voucher'], ''),
            'type' => $rawType,
            'type_label' => $rawType === 'percent' ? 'Phần trăm' : 'Tiền mặt',
            'value' => (float) pickVoucherValue($row, [$valueCol ?? 'gia_tri_giam'], 0),
            'min_order' => (float) pickVoucherValue($row, [$minCol ?? 'tien_toi_thieu'], 0),
            'max_use' => (int) pickVoucherValue($row, [$maxUseCol ?? 'so_luong_toi_da'], 0),
            'used' => (int) pickVoucherValue($row, [$usedCol ?? 'so_luong_da_su_dung'], 0),
            'start_date' => formatDateInput($startRaw),
            'start_date_display' => formatDateDisplay($startRaw),
            'end_date' => formatDateInput($endRaw),
            'end_date_display' => formatDateDisplay($endRaw),
            'status' => $rawStatus,
            'status_label' => $rawStatus === 'active' ? 'Đang áp dụng' : 'Tạm dừng',
        ];
    }

    $nextVoucherCode = generateNextVoucherCode($pdo, 'voucher', $codeCol);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$totalVouchers = count($vouchers);
$activeVouchers = 0;
$expiredVouchers = 0;
$todayTs = strtotime(date('Y-m-d')) ?: time();

foreach ($vouchers as $voucher) {
    if (($voucher['status'] ?? 'inactive') === 'active') {
        $activeVouchers++;
    }

    $endDateTs = strtotime((string) ($voucher['end_date'] ?? ''));
    if ($endDateTs !== false && $endDateTs < $todayTs) {
        $expiredVouchers++;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Admin - Quản lý voucher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --primary-blue: #007bff;
        --bg-light: #f5f7fa;
        --text-dark: #344767;
        --sidebar-width: 260px;
        --admin-layout-gap: 20px;
        --admin-content-inline-padding: 20px;
    }

    html,
    body {
        height: 100%;
        overflow: hidden;
    }

    body {
        font-family: 'Roboto', sans-serif;
        background-color: var(--bg-light);
    }

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

    .main-content {
        margin-left: var(--sidebar-width);
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }

    .voucher-top-sticky {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        z-index: 1030;
        background: var(--bg-light);
        padding: 20px 15px 12px;
        border-bottom: none;
        box-shadow: none;
    }

    .voucher-top-sticky .alert {
        margin-bottom: 10px;
    }

    .voucher-content-offset {
        position: fixed;
        left: calc(var(--sidebar-width) + 15px);
        right: 15px;
        top: 360px;
        bottom: 20px;
        overflow: hidden;
    }

    body.modal-open #voucherContentOffset {
        overflow: visible;
    }

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

    .bg-gradient-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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

    .search-input {
        border-radius: 20px;
        padding: 5px 20px;
        border: 1px solid #ddd;
        width: 250px;
    }

    .btn-add-voucher {
        background-color: var(--primary-blue);
        color: white;
        border-radius: 8px;
        padding: 10px 20px;
        border: none;
        font-weight: 500;
    }

    .btn-add-voucher:hover {
        background-color: #0069d9;
        color: white;
    }

    .btn-edit-voucher {
        background-color: #ffc107;
        color: #212529;
        border-radius: 8px;
        padding: 10px 20px;
        border: none;
        font-weight: 500;
    }

    .btn-edit-voucher:hover {
        background-color: #e0a800;
        color: #212529;
    }

    .btn-view-voucher {
        background-color: #0dcaf0;
        color: #fff;
        border-radius: 8px;
        padding: 10px 20px;
        border: none;
        font-weight: 500;
    }

    .btn-view-voucher:hover {
        background-color: #31d2f2;
        color: #fff;
    }

    .btn-delete-voucher {
        background-color: #dc3545;
        color: #fff;
        border-radius: 8px;
        padding: 10px 20px;
        border: none;
        font-weight: 500;
    }

    .btn-delete-voucher:hover {
        background-color: #c82333;
        color: #fff;
    }

    .table-container {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .voucher-table-scroll {
        flex: 1 1 auto;
        overflow: auto;
        min-height: 220px;
    }

    .table thead th {
        background-color: #f8f9fa;
        color: #344767;
        font-weight: 700;
        border-bottom: 1px solid #eee;
        padding: 15px 20px;
        position: sticky;
        top: 0;
        z-index: 6;
    }

    .table tbody td {
        padding: 15px 20px;
        vertical-align: middle;
        color: #344767;
        border-bottom: 1px solid #f0f2f5;
    }

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

    .voucher-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 700;
    }

    .voucher-status-active {
        background: #dcfce7;
        color: #166534;
    }

    .voucher-status-inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    @media (max-width: 768px) {
        .voucher-top-sticky {
            padding: 20px 15px 10px;
        }

        .voucher-content-offset {
            left: calc(var(--sidebar-width) + 15px);
            right: 15px;
            bottom: 16px;
        }
    }
    </style>
    <link rel="stylesheet" href="admin-unified-ui.css">
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
            <a href="admin-nhanvien.php" class="nav-item"><i class="fas fa-user-tie"></i> Nhân viên</a>
            <a href="admin-khachhang.php" class="nav-item"><i class="fas fa-users"></i> Khách hàng</a>
            <a href="admin-voucher.php" class="nav-item active"><i class="fas fa-ticket-alt"></i> Voucher</a>
            <a href="admin-caidat.php" class="nav-item"><i class="fas fa-cog"></i> Cài đặt</a>
        </nav>
        <a href="../Login/logout.php" class="logout-btn">Đăng xuất</a>
    </div>

    <div class="main-content">
        <div class="voucher-top-sticky">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-primary">Bảng điều khiển Admin</h4>
                <button class="btn btn-light rounded-circle shadow-sm"><i class="fas fa-times"></i></button>
            </div>

            <div class="d-flex justify-content-between align-items-end mb-4">
                <h3 class="fw-bold mb-0">Quản lý voucher</h3>
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
                        <div class="icon-box bg-gradient-primary me-3"><i class="fas fa-ticket-alt"></i></div>
                        <div>
                            <div class="stat-label">Tổng voucher</div>
                            <h4 class="stat-value"><?php echo $totalVouchers; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card d-flex align-items-center">
                        <div class="icon-box bg-gradient-success me-3"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <div class="stat-label">Đang áp dụng</div>
                            <h4 class="stat-value"><?php echo $activeVouchers; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card d-flex align-items-center">
                        <div class="icon-box bg-gradient-warning me-3"><i class="fas fa-calendar-times"></i></div>
                        <div>
                            <div class="stat-label">Đã hết hạn</div>
                            <h4 class="stat-value"><?php echo $expiredVouchers; ?></h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button class="btn-add-voucher mb-0" data-bs-toggle="modal" data-bs-target="#addVoucherModal">
                    <i class="fas fa-plus me-2"></i> Thêm voucher
                </button>
                <button class="btn-edit-voucher" id="btnEditVoucher" disabled>
                    <i class="fas fa-pen me-1"></i> Sửa voucher
                </button>
                <button class="btn-view-voucher" id="btnViewVoucher" disabled>
                    <i class="fas fa-eye me-1"></i> Xem chi tiết
                </button>
                <button class="btn-delete-voucher" id="btnDeleteVoucher" disabled>
                    <i class="fas fa-trash me-1"></i> Xóa
                </button>
            </div>
        </div>

        <div id="voucherContentOffset" class="voucher-content-offset">

            <div class="modal fade" id="addVoucherModal" tabindex="-1" aria-labelledby="addVoucherModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="addVoucherModalLabel">Thêm voucher</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                            <input type="hidden" name="crud_action" value="add_voucher">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Mã voucher</label>
                                        <input type="text" class="form-control" name="voucher_code"
                                            value="<?php echo htmlspecialchars($nextVoucherCode); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tên voucher</label>
                                        <input type="text" class="form-control" name="voucher_name"
                                            placeholder="Ví dụ: Giảm giá sinh nhật" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Kiểu giảm</label>
                                        <select class="form-select" name="voucher_type" required>
                                            <option value="fixed">Tiền mặt</option>
                                            <option value="percent">Phần trăm</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Giá trị giảm</label>
                                        <input type="number" class="form-control" name="voucher_value" min="1"
                                            step="0.01" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Đơn tối thiểu</label>
                                        <input type="number" class="form-control" name="voucher_min_order" min="0"
                                            step="0.01" value="0">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Số lượt tối đa</label>
                                        <input type="number" class="form-control" name="voucher_max_use" min="0"
                                            value="0">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ngày bắt đầu</label>
                                        <input type="date" class="form-control" name="voucher_start_date" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ngày kết thúc</label>
                                        <input type="date" class="form-control" name="voucher_end_date" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Trạng thái</label>
                                        <select class="form-select" name="voucher_status" required>
                                            <option value="active">Đang áp dụng</option>
                                            <option value="inactive">Tạm dừng</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" class="btn btn-primary">Lưu voucher</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="voucherDetailModal" tabindex="-1" aria-labelledby="voucherDetailModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="voucherDetailModalLabel">Chi tiết voucher</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" id="voucherDetailForm">
                            <input type="hidden" name="crud_action" value="update_voucher">
                            <input type="hidden" name="voucher_id" id="detailVoucherId">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Mã voucher</label>
                                        <input type="text" class="form-control" name="voucher_code"
                                            id="detailVoucherCode" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tên voucher</label>
                                        <input type="text" class="form-control" name="voucher_name"
                                            id="detailVoucherName" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Kiểu giảm</label>
                                        <select class="form-select" name="voucher_type" id="detailVoucherType" required>
                                            <option value="fixed">Tiền mặt</option>
                                            <option value="percent">Phần trăm</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Giá trị giảm</label>
                                        <input type="number" class="form-control" name="voucher_value"
                                            id="detailVoucherValue" min="1" step="0.01" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Đơn tối thiểu</label>
                                        <input type="number" class="form-control" name="voucher_min_order"
                                            id="detailVoucherMinOrder" min="0" step="0.01">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Số lượt tối đa</label>
                                        <input type="number" class="form-control" name="voucher_max_use"
                                            id="detailVoucherMaxUse" min="0">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ngày bắt đầu</label>
                                        <input type="date" class="form-control" name="voucher_start_date"
                                            id="detailVoucherStartDate" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ngày kết thúc</label>
                                        <input type="date" class="form-control" name="voucher_end_date"
                                            id="detailVoucherEndDate" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Trạng thái</label>
                                        <select class="form-select" name="voucher_status" id="detailVoucherStatus"
                                            required>
                                            <option value="active">Đang áp dụng</option>
                                            <option value="inactive">Tạm dừng</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                <button type="submit" class="btn btn-primary" id="btnSaveVoucherDetail">Lưu thay
                                    đổi</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="deleteVoucherModal" tabindex="-1" aria-labelledby="deleteVoucherModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteVoucherModalLabel">Xác nhận xóa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Bạn có chắc chắn muốn xóa voucher <strong id="deleteVoucherCode">--</strong> không?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="button" class="btn btn-danger" id="btnConfirmDeleteVoucher">Xóa</button>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" id="voucherDeleteForm" class="d-none">
                <input type="hidden" name="crud_action" value="delete_voucher">
                <input type="hidden" name="voucher_id" id="deleteVoucherId">
            </form>

            <div class="table-container">
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Danh sách voucher</h5>
                    <i class="fas fa-download text-muted"></i>
                </div>
                <div class="voucher-table-scroll">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Mã voucher</th>
                                <th>Tên voucher</th>
                                <th>Kiểu giảm</th>
                                <th>Giá trị</th>
                                <th>Đơn tối thiểu</th>
                                <th>Đã dùng / Tối đa</th>
                                <th>Hiệu lực</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($vouchers) === 0): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Chưa có voucher nào.</td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($vouchers as $voucher): ?>
                            <tr class="voucher-row" data-id="<?php echo (int) ($voucher['id'] ?? 0); ?>"
                                data-code="<?php echo htmlspecialchars((string) ($voucher['code'] ?? ''), ENT_QUOTES); ?>"
                                data-name="<?php echo htmlspecialchars((string) ($voucher['name'] ?? ''), ENT_QUOTES); ?>"
                                data-type="<?php echo htmlspecialchars((string) ($voucher['type'] ?? 'fixed'), ENT_QUOTES); ?>"
                                data-value="<?php echo htmlspecialchars((string) ($voucher['value'] ?? 0), ENT_QUOTES); ?>"
                                data-min-order="<?php echo htmlspecialchars((string) ($voucher['min_order'] ?? 0), ENT_QUOTES); ?>"
                                data-max-use="<?php echo htmlspecialchars((string) ($voucher['max_use'] ?? 0), ENT_QUOTES); ?>"
                                data-start-date="<?php echo htmlspecialchars((string) ($voucher['start_date'] ?? ''), ENT_QUOTES); ?>"
                                data-end-date="<?php echo htmlspecialchars((string) ($voucher['end_date'] ?? ''), ENT_QUOTES); ?>"
                                data-status="<?php echo htmlspecialchars((string) ($voucher['status'] ?? 'inactive'), ENT_QUOTES); ?>">
                                <td class="fw-bold"><?php echo htmlspecialchars((string) ($voucher['code'] ?? '')); ?>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($voucher['name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($voucher['type_label'] ?? 'Tiền mặt')); ?>
                                </td>
                                <td>
                                    <?php if (($voucher['type'] ?? 'fixed') === 'percent'): ?>
                                    <?php echo rtrim(rtrim(number_format((float) ($voucher['value'] ?? 0), 2, '.', ''), '0'), '.'); ?>%
                                    <?php else: ?>
                                    <?php echo number_format((float) ($voucher['value'] ?? 0), 0, ',', '.'); ?> ₫
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format((float) ($voucher['min_order'] ?? 0), 0, ',', '.'); ?> ₫
                                </td>
                                <td>
                                    <?php echo (int) ($voucher['used'] ?? 0); ?> /
                                    <?php echo (int) ($voucher['max_use'] ?? 0); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars((string) ($voucher['start_date_display'] ?? '')); ?> -
                                    <?php echo htmlspecialchars((string) ($voucher['end_date_display'] ?? '')); ?>
                                </td>
                                <td>
                                    <span
                                        class="voucher-badge <?php echo (($voucher['status'] ?? 'inactive') === 'active') ? 'voucher-status-active' : 'voucher-status-inactive'; ?>">
                                        <?php echo htmlspecialchars((string) ($voucher['status_label'] ?? 'Tạm dừng')); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let selectedVoucherRow = null;
    let voucherDetailReadOnly = false;

    const addVoucherModalEl = document.getElementById('addVoucherModal');
    if (addVoucherModalEl && addVoucherModalEl.parentElement !== document.body) {
        document.body.appendChild(addVoucherModalEl);
    }

    const voucherDetailModalEl = document.getElementById('voucherDetailModal');
    if (voucherDetailModalEl && voucherDetailModalEl.parentElement !== document.body) {
        document.body.appendChild(voucherDetailModalEl);
    }

    const deleteVoucherModalEl = document.getElementById('deleteVoucherModal');
    if (deleteVoucherModalEl && deleteVoucherModalEl.parentElement !== document.body) {
        document.body.appendChild(deleteVoucherModalEl);
    }

    function setVoucherDetailReadOnly(readOnly) {
        voucherDetailReadOnly = !!readOnly;
        const fieldIds = [
            'detailVoucherCode',
            'detailVoucherName',
            'detailVoucherType',
            'detailVoucherValue',
            'detailVoucherMinOrder',
            'detailVoucherMaxUse',
            'detailVoucherStartDate',
            'detailVoucherEndDate',
            'detailVoucherStatus'
        ];

        fieldIds.forEach((id) => {
            const field = document.getElementById(id);
            if (!field) return;
            if (field.tagName === 'SELECT') {
                field.disabled = voucherDetailReadOnly;
            } else {
                field.readOnly = voucherDetailReadOnly;
            }
        });

        const saveBtn = document.getElementById('btnSaveVoucherDetail');
        if (saveBtn) {
            saveBtn.style.display = voucherDetailReadOnly ? 'none' : 'inline-block';
        }
    }

    function fillVoucherDetailModal(row, readOnly = false) {
        document.getElementById('detailVoucherId').value = row.getAttribute('data-id') || '';
        document.getElementById('detailVoucherCode').value = row.getAttribute('data-code') || '';
        document.getElementById('detailVoucherName').value = row.getAttribute('data-name') || '';
        document.getElementById('detailVoucherType').value = row.getAttribute('data-type') || 'fixed';
        document.getElementById('detailVoucherValue').value = row.getAttribute('data-value') || '0';
        document.getElementById('detailVoucherMinOrder').value = row.getAttribute('data-min-order') || '0';
        document.getElementById('detailVoucherMaxUse').value = row.getAttribute('data-max-use') || '0';
        document.getElementById('detailVoucherStartDate').value = row.getAttribute('data-start-date') || '';
        document.getElementById('detailVoucherEndDate').value = row.getAttribute('data-end-date') || '';
        document.getElementById('detailVoucherStatus').value = row.getAttribute('data-status') || 'active';
        setVoucherDetailReadOnly(readOnly);
    }

    document.querySelectorAll('.voucher-row').forEach((row) => {
        row.addEventListener('click', function() {
            document.querySelectorAll('.voucher-row').forEach((r) => r.classList.remove('selected'));
            this.classList.add('selected');
            selectedVoucherRow = this;

            document.getElementById('btnEditVoucher').disabled = false;
            document.getElementById('btnViewVoucher').disabled = false;
            document.getElementById('btnDeleteVoucher').disabled = false;
        });
    });

    document.getElementById('btnViewVoucher').addEventListener('click', function() {
        if (!selectedVoucherRow) {
            alert('Vui lòng chọn voucher để xem chi tiết.');
            return;
        }

        fillVoucherDetailModal(selectedVoucherRow, true);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('voucherDetailModal')).show();
    });

    document.getElementById('btnEditVoucher').addEventListener('click', function() {
        if (!selectedVoucherRow) {
            alert('Vui lòng chọn voucher để sửa.');
            return;
        }

        fillVoucherDetailModal(selectedVoucherRow, false);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('voucherDetailModal')).show();
    });

    document.getElementById('btnDeleteVoucher').addEventListener('click', function() {
        if (!selectedVoucherRow) {
            alert('Vui lòng chọn voucher để xóa.');
            return;
        }

        const voucherId = selectedVoucherRow.getAttribute('data-id') || '';
        const voucherCode = selectedVoucherRow.getAttribute('data-code') || '';
        if (!voucherId) {
            return;
        }
        document.getElementById('deleteVoucherId').value = voucherId;
        document.getElementById('deleteVoucherCode').textContent = voucherCode || '--';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteVoucherModal')).show();
    });

    document.getElementById('btnConfirmDeleteVoucher').addEventListener('click', function() {
        const voucherId = document.getElementById('deleteVoucherId').value || '';
        if (!voucherId) {
            return;
        }

        document.getElementById('voucherDeleteForm').submit();
    });

    document.getElementById('voucherDetailForm').addEventListener('submit', function(event) {
        if (voucherDetailReadOnly) {
            event.preventDefault();
            return;
        }

        const type = (document.getElementById('detailVoucherType').value || 'fixed').trim();
        const value = parseFloat(document.getElementById('detailVoucherValue').value || '0');
        const startDate = document.getElementById('detailVoucherStartDate').value;
        const endDate = document.getElementById('detailVoucherEndDate').value;

        if (value <= 0) {
            event.preventDefault();
            alert('Giá trị giảm phải lớn hơn 0.');
            return;
        }

        if (type === 'percent' && value > 100) {
            event.preventDefault();
            alert('Voucher phần trăm chỉ được từ 1 đến 100.');
            return;
        }

        if (!startDate || !endDate || new Date(startDate) > new Date(endDate)) {
            event.preventDefault();
            alert('Ngày bắt đầu/kết thúc không hợp lệ.');
            return;
        }
    });

    function syncVoucherFixedTopOffset() {
        const fixedTop = document.querySelector('.voucher-top-sticky');
        const contentOffset = document.getElementById('voucherContentOffset');
        if (!fixedTop || !contentOffset) {
            return;
        }

        const topHeight = Math.ceil(fixedTop.getBoundingClientRect().height);
        contentOffset.style.top = `${topHeight + 10}px`;
    }

    window.addEventListener('resize', syncVoucherFixedTopOffset);
    window.addEventListener('load', syncVoucherFixedTopOffset);
    syncVoucherFixedTopOffset();
    </script>
    <script src="admin-search.js"></script>
</body>

</html>