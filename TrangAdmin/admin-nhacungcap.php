<?php
require_once __DIR__ . '/../Login/admin_auth.php';

function pickSupplierValue(array $row, array $keys, $default = '') {
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

function generateNextSupplierId(PDO $pdo): string {
    $rows = $pdo->query("SELECT MaNCC FROM nhacc")->fetchAll(PDO::FETCH_COLUMN);
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

    return 'NCC' . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
}

$suppliers = [];
$dbError = '';
$crudMessage = '';
$crudError = '';
$nextSupplierId = '';

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['crud_action']) ? trim((string) $_POST['crud_action']) : '';

        if ($action === 'add_supplier') {
            $id = trim((string) ($_POST['supplier_id'] ?? ''));
            $name = trim((string) ($_POST['supplier_name'] ?? ''));
            $phone = trim((string) ($_POST['supplier_phone'] ?? ''));
            $address = trim((string) ($_POST['supplier_address'] ?? ''));
            $email = trim((string) ($_POST['supplier_email'] ?? ''));

            if ($id === '') {
                $id = generateNextSupplierId($pdo);
            }

            if ($id === '' || $name === '' || $phone === '' || $address === '' || $email === '') {
                $crudError = 'Vui lòng nhập đầy đủ thông tin nhà cung cấp.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $crudError = 'Email nhà cung cấp không hợp lệ.';
            } else {
                try {
                    $columns = getExistingColumns($pdo, 'nhacc');
                    $idCol = pickExistingColumn($columns, ['mancc', 'ma_ncc', 'manhacungcap', 'id']);
                    $nameCol = pickExistingColumn($columns, ['tenncc', 'ten_ncc', 'tennhacungcap', 'name']);
                    $phoneCol = pickExistingColumn($columns, ['sodienthoai', 'so_dien_thoai', 'sdt', 'dienthoai', 'phone']);
                    $addressCol = pickExistingColumn($columns, ['diachi', 'dia_chi', 'address']);
                    $emailCol = pickExistingColumn($columns, ['email', 'mail']);

                    if ($idCol === null || $nameCol === null || $phoneCol === null || $addressCol === null || $emailCol === null) {
                        $crudError = 'Không tìm thấy đủ cột bắt buộc của nhà cung cấp (mã/tên/số điện thoại/địa chỉ/email).';
                    } else {
                        $insertColumns = [$idCol, $nameCol, $phoneCol, $addressCol, $emailCol];
                        $insertPlaceholders = [':id', ':name', ':phone', ':address', ':email'];
                        $params = [
                            ':id' => $id,
                            ':name' => $name,
                            ':phone' => $phone,
                            ':address' => $address,
                            ':email' => $email,
                        ];

                        $sql = 'INSERT INTO nhacc (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        $crudMessage = 'Đã thêm nhà cung cấp thành công.';
                    }
                } catch (Throwable $insertError) {
                    $crudError = 'Không thể thêm nhà cung cấp: ' . $insertError->getMessage();
                }
            }
        }

        if ($action === 'update_supplier') {
            $id = trim((string) ($_POST['supplier_id'] ?? ''));
            $name = trim((string) ($_POST['supplier_name'] ?? ''));
            $phone = trim((string) ($_POST['supplier_phone'] ?? ''));
            $address = trim((string) ($_POST['supplier_address'] ?? ''));
            $email = trim((string) ($_POST['supplier_email'] ?? ''));

            if ($id === '' || $name === '' || $phone === '' || $address === '' || $email === '') {
                $crudError = 'Dữ liệu cập nhật không hợp lệ.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $crudError = 'Email nhà cung cấp không hợp lệ.';
            } else {
                try {
                    $columns = getExistingColumns($pdo, 'nhacc');
                    $idCol = pickExistingColumn($columns, ['mancc', 'ma_ncc', 'manhacungcap', 'id']);
                    $nameCol = pickExistingColumn($columns, ['tenncc', 'ten_ncc', 'tennhacungcap', 'name']);
                    $phoneCol = pickExistingColumn($columns, ['sodienthoai', 'so_dien_thoai', 'sdt', 'dienthoai', 'phone']);
                    $addressCol = pickExistingColumn($columns, ['diachi', 'dia_chi', 'address']);
                    $emailCol = pickExistingColumn($columns, ['email', 'mail']);

                    if ($idCol === null || $nameCol === null || $phoneCol === null || $addressCol === null || $emailCol === null) {
                        $crudError = 'Không tìm thấy cột bắt buộc để cập nhật nhà cung cấp.';
                    } else {
                        $setParts = [
                            "{$nameCol} = :name",
                            "{$phoneCol} = :phone",
                            "{$addressCol} = :address",
                            "{$emailCol} = :email",
                        ];
                        $params = [
                            ':id' => $id,
                            ':name' => $name,
                            ':phone' => $phone,
                            ':address' => $address,
                            ':email' => $email,
                        ];

                        $sql = 'UPDATE nhacc SET ' . implode(', ', $setParts) . " WHERE {$idCol} = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        $crudMessage = 'Đã cập nhật nhà cung cấp.';
                    }
                } catch (Throwable $updateError) {
                    $crudError = 'Không thể cập nhật nhà cung cấp: ' . $updateError->getMessage();
                }
            }
        }

        if ($action === 'delete_supplier') {
            $id = trim((string) ($_POST['supplier_id'] ?? ''));
            if ($id === '') {
                $crudError = 'Không xác định được nhà cung cấp cần xóa.';
            } else {
                try {
                    $columns = getExistingColumns($pdo, 'nhacc');
                    $idCol = pickExistingColumn($columns, ['mancc', 'ma_ncc', 'manhacungcap', 'id']);

                    if ($idCol === null) {
                        $crudError = 'Không tìm thấy cột mã nhà cung cấp để xóa.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM nhacc WHERE {$idCol} = :id");
                        $stmt->execute([':id' => $id]);
                        $crudMessage = 'Đã xóa nhà cung cấp.';
                    }
                } catch (Throwable $deleteError) {
                    $crudError = 'Không thể xóa nhà cung cấp: ' . $deleteError->getMessage();
                }
            }
        }
    }

    $rows = $pdo->query("SELECT * FROM nhacc")->fetchAll();
    foreach ($rows as $row) {
        $id = (string) pickSupplierValue($row, ['mancc', 'ma_ncc', 'manhacungcap', 'id']);
        $name = (string) pickSupplierValue($row, ['tenncc', 'ten_ncc', 'tennhacungcap', 'name']);
        $phone = (string) pickSupplierValue($row, ['sodienthoai', 'so_dien_thoai', 'sdt', 'dienthoai', 'phone'], '');
        $address = (string) pickSupplierValue($row, ['diachi', 'dia_chi', 'address'], '');
        $email = (string) pickSupplierValue($row, ['email', 'mail'], '');

        $suppliers[] = [
            'id' => $id,
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'email' => $email,
        ];
    }

    $nextSupplierId = generateNextSupplierId($pdo);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$totalSuppliers = count($suppliers);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Admin - Quản lí nhà cung cấp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --primary-blue: #007bff;
        --bg-light: #f5f7fa;
        --text-dark: #344767;
        --sidebar-width: 260px;
    }

    html,
    body {
        height: 100%;
        overflow: hidden;
    }

    body {
        font-family: 'Roboto', sans-serif;
        background-color: #ffffff;
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
        border-right: 1px solid #f0f2f5;
        overflow-y: auto;
        overflow-x: hidden;
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
        margin-left: var(--sidebar-width);
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }

    .supplier-top-sticky {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        z-index: 1030;
        background: #ffffff;
        padding: 20px 40px 14px;
        border-bottom: none;
        box-shadow: none;
    }

    .supplier-top-sticky .alert {
        margin-bottom: 10px;
    }

    .supplier-content-offset {
        position: fixed;
        left: calc(var(--sidebar-width) + 40px);
        right: 40px;
        top: 340px;
        bottom: 20px;
        overflow: hidden;
    }

    body.modal-open #supplierContentOffset {
        overflow: visible;
    }

    /* HEADER & SEARCH */
    .top-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .page-title {
        font-weight: 700;
        color: #000;
        font-size: 1.5rem;
        margin: 0;
    }

    .search-top {
        border: 1px solid #ccc;
        border-radius: 20px;
        padding: 6px 15px;
        width: 250px;
        outline: none;
        font-size: 0.9rem;
    }

    /* SUMMARY CARDS */
    .summary-cards {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .card-box {
        background-color: #cce3ff;
        border: 1px solid #a8cfff;
        border-radius: 12px;
        padding: 15px 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .card-title-text {
        color: #555;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .card-number {
        color: #222;
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
    }

    /* BUTTON THÊM */
    .btn-add-supplier {
        background-color: #2196f3;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 8px 20px;
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 30px;
    }

    .btn-add-supplier:hover {
        background-color: #1976d2;
        color: white;
    }

    /* TABLE CONTAINER */
    .table-container {
        background: white;
        border-radius: 12px;
        border: 1px solid #f0f0f0;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        overflow: hidden;
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    .supplier-table-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow: auto;
    }

    .table-header {
        background-color: #ffffff;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f0f0f0;
    }

    .table-header h5 {
        margin: 0;
        font-weight: 700;
        font-size: 1.1rem;
        color: #222;
    }

    /* TABLE STYLES */
    .table {
        margin-bottom: 0;
    }

    .table thead th {
        border-bottom: 1px solid #f0f0f0;
        padding: 15px 20px;
        font-weight: 700;
        font-size: 0.9rem;
        color: #333;
        position: sticky;
        top: 0;
        z-index: 6;
        background: #F8F9FA;
    }

    .table tbody td {
        padding: 10px 20px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.95rem;
        color: #444;
    }

    .table tbody tr:last-child td {
        border-bottom: none;
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
    }

    .supplier-logo {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: contain;
        border: 1px solid #ddd;
        background-color: #fff;
        padding: 2px;
    }

    /* Row selection enhancement */
    .table tbody tr.selected {
        background-color: #cce3ff;
        font-weight: 500;
        border-left: 4px solid #007bff;
        box-shadow: inset 0 0 8px rgba(0, 123, 255, 0.1);
    }

    .table tbody tr.selected td:first-child {
        padding-left: 16px;
    }

    /* Detail Panel */
    .detail-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 1040;
    }

    .detail-overlay.show {
        display: block;
    }

    .detail-panel {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: min(900px, 92vw);
        max-height: 85vh;
        overflow-y: auto;
        background: white;
        border-radius: 12px;
        border: 1px solid #e9ecef;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        padding: 25px;
        z-index: 1055;
    }

    .detail-panel.show {
        display: block;
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translate(-50%, -60%);
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
        grid-template-columns: repeat(2, minmax(260px, 1fr));
        gap: 20px;
    }

    .detail-field {
        display: flex;
        flex-direction: column;
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
    .detail-field textarea {
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 0.95rem;
        color: #444;
        background-color: #f8f9fa;
    }

    .detail-field input:focus,
    .detail-field textarea:focus {
        background-color: #fff;
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .detail-field-preview {
        text-align: center;
    }

    .detail-field-preview img {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: contain;
        border: 2px solid #e9ecef;
        background-color: #fff;
        padding: 5px;
    }

    .detail-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        position: sticky;
        bottom: 0;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #f0f2f5;
        background: #fff;
    }

    @media (max-width: 992px) {
        .detail-content {
            grid-template-columns: 1fr;
        }
    }

    .detail-actions button {
        font-size: 0.9rem;
        font-weight: 500;
        padding: 8px 18px;
    }

    /* --- DELETE CONFIRMATION MODAL --- */
    .delete-modal {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
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

    .delete-modal::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: -1;
        display: none;
    }

    .delete-modal.show::before {
        display: block;
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
        color: #67748e;
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

    @media (max-width: 768px) {
        .supplier-top-sticky {
            padding: 20px 16px 10px;
        }

        .supplier-content-offset {
            left: calc(var(--sidebar-width) + 16px);
            right: 16px;
            bottom: 16px;
        }
    }
    </style>
    <link rel="stylesheet" href="admin-unified-ui.css?v=20260414-2">
</head>

<body>

    <div class="sidebar">
        <div class="brand-logo">
            <img src="../TrangUser/ack.png" alt="Logo" height="40" onerror="this.src='https://via.placeholder.com/40'">
            <h4 class="fw-bold ms-2 mb-0" style="color: #344767;">Admin</h4>
        </div>
        <nav>
            <a href="admin.php" class="nav-item"><i class="fas fa-chart-bar"></i> Tổng quan</a>
            <a href="admin-sanpham.php" class="nav-item"><i class="fas fa-box"></i> Sản phẩm</a>
            <a href="admin-nhomhang.php" class="nav-item"><i class="fas fa-folder"></i> Nhóm hàng</a>
            <a href="admin-nhaphang.php" class="nav-item"><i class="fas fa-truck-loading"></i> Nhập hàng</a>
            <a href="admin-nhacungcap.php" class="nav-item active"><i class="fas fa-building"></i> Nhà cung cấp</a>
            <a href="admin-bophan.php" class="nav-item"><i class="fas fa-sitemap"></i> Bộ phận</a>
            <a href="admin-chucvu.php" class="nav-item"><i class="fas fa-user-tag"></i> Chức vụ</a>
            <a href="admin-donhang.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Đơn hàng</a>
            <a href="admin-nhanvien.php" class="nav-item"><i class="fas fa-user-tie"></i> Nhân viên</a>
            <a href="admin-khachhang.php" class="nav-item"><i class="fas fa-users"></i> Khách hàng</a>
            <a href="admin-voucher.php" class="nav-item"><i class="fas fa-ticket-alt"></i> Voucher</a>
            <a href="admin-caidat.php" class="nav-item"><i class="fas fa-cog"></i> Cài đặt</a>
        </nav>
        <a href="../Login/logout.php" class="logout-btn">Đăng xuất</a>
    </div>

    <div class="main-content">

        <div class="supplier-top-sticky">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-primary">Bảng điều khiển Admin</h4>
                <button class="btn btn-light rounded-circle shadow-sm"><i class="fas fa-times"></i></button>
            </div>

            <div class="top-header">
                <h2 class="page-title">Quản lí nhà cung cấp</h2>
                <input type="text" class="search-top" placeholder="Tìm kiếm">
            </div>

            <?php if ($dbError !== ''): ?>
            <div class="alert alert-warning" role="alert">
                Không thể kết nối/lấy dữ liệu từ MySQL: <?php echo htmlspecialchars($dbError); ?>
            </div>
            <?php endif; ?>

            <?php if ($crudMessage !== ''): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($crudMessage); ?>
            </div>
            <?php endif; ?>

            <?php if ($crudError !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($crudError); ?>
            </div>
            <?php endif; ?>

            <div class="summary-cards">
                <div class="card-box">
                    <span class="card-title-text">Tổng nhà cung cấp</span>
                    <p class="card-number"><?php echo $totalSuppliers; ?></p>
                </div>
                <div class="card-box">
                    <span class="card-title-text">Đang hợp tác</span>
                    <p class="card-number"><?php echo $totalSuppliers; ?></p>
                </div>
                <div class="card-box">
                    <span class="card-title-text">Cần đánh giá lại</span>
                    <p class="card-number"><?php echo ($totalSuppliers === 0) ? 1 : 0; ?></p>
                </div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button class="btn btn-add-supplier mb-0" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                    <i class="fas fa-plus me-1"></i> Thêm nhà cung cấp
                </button>
                <button class="btn btn-warning fw-semibold" id="btnEditSupplier" disabled>
                    <i class="fas fa-pen me-1"></i> Sửa nhà cung cấp
                </button>
                <button class="btn btn-info fw-semibold text-white" id="btnViewSupplier" disabled>
                    <i class="fas fa-eye me-1"></i> Xem chi tiết
                </button>
                <button class="btn btn-danger fw-semibold" id="btnDeleteSupplier" disabled>
                    <i class="fas fa-trash me-1"></i> Xóa
                </button>
            </div>
        </div>

        <div id="supplierContentOffset" class="supplier-content-offset">

            <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="addSupplierModalLabel">Thêm nhà cung cấp</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                            <input type="hidden" name="crud_action" value="add_supplier">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="supplierId" class="form-label">Mã nhà cung cấp</label>
                                        <input type="text" class="form-control" id="supplierId" name="supplier_id"
                                            value="<?php echo htmlspecialchars($nextSupplierId); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="supplierName" class="form-label">Tên nhà cung cấp</label>
                                        <input type="text" class="form-control" id="supplierName" name="supplier_name"
                                            placeholder="Nhập tên nhà cung cấp" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="supplierPhone" class="form-label">Số điện thoại</label>
                                        <input type="text" class="form-control" id="supplierPhone" name="supplier_phone"
                                            placeholder="Nhập số điện thoại" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="supplierEmail" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="supplierEmail"
                                            name="supplier_email" placeholder="ncc@example.com" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="supplierAddress" class="form-label">Địa chỉ</label>
                                        <input type="text" class="form-control" id="supplierAddress"
                                            name="supplier_address" placeholder="Nhập địa chỉ" required>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" class="btn btn-primary">Lưu nhà cung cấp</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="detailOverlay" class="detail-overlay" onclick="closeDetailPanel()"></div>

            <!-- Detail Panel -->
            <div id="detailPanel" class="detail-panel">
                <div class="detail-header">
                    <h5>Chi tiết nhà cung cấp</h5>
                    <button type="button" class="btn-close" onclick="closeDetailPanel()"></button>
                </div>
                <div class="detail-content">
                    <div class="detail-field">
                        <label>Mã nhà cung cấp</label>
                        <input type="text" id="detailId" readonly>
                    </div>
                    <div class="detail-field">
                        <label>Tên nhà cung cấp</label>
                        <input type="text" id="detailName">
                    </div>
                    <div class="detail-field">
                        <label>Số điện thoại</label>
                        <input type="text" id="detailPhone">
                    </div>
                    <div class="detail-field">
                        <label>Email</label>
                        <input type="email" id="detailEmail">
                    </div>
                    <div class="detail-field" style="grid-column: 1 / -1;">
                        <label>Địa chỉ</label>
                        <textarea id="detailAddress" rows="2"></textarea>
                    </div>
                </div>
                <div class="detail-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeDetailPanel()">Đóng</button>
                    <button type="button" class="btn btn-primary" id="btnDetailSave">Lưu thay đổi</button>
                </div>
            </div>

            <div class="modal fade" id="deleteSupplierModal" tabindex="-1" aria-labelledby="deleteSupplierModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteSupplierModalLabel">Xác nhận xóa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Bạn có chắc chắn muốn xóa nhà cung cấp <strong id="deleteSupplierName"></strong> không?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="button" class="btn btn-danger" id="btnConfirmDeleteSupplier">Xóa</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h5>Danh sách nhà cung cấp</h5>
                    <a href="#" class="text-dark"><i class="fas fa-download"></i></a>
                </div>
                <div class="supplier-table-scroll">
                    <table class="table text-center">
                        <thead>
                            <tr>
                                <th class="text-center" width="14%">Mã nhà cung cấp</th>
                                <th class="text-center" width="20%">Tên nhà cung cấp</th>
                                <th class="text-center" width="18%">Số điện thoại</th>
                                <th class="text-center" width="23%">Địa chỉ</th>
                                <th class="text-center" width="25%">Email</th>
                            </tr>
                        </thead>
                        <tbody id="supplierTableBody">
                            <?php foreach($suppliers as $s): ?>
                            <tr class="supplier-row" data-id="<?php echo htmlspecialchars($s['id']); ?>"
                                data-name="<?php echo htmlspecialchars($s['name']); ?>"
                                data-phone="<?php echo htmlspecialchars($s['phone']); ?>"
                                data-address="<?php echo htmlspecialchars($s['address']); ?>"
                                data-email="<?php echo htmlspecialchars($s['email']); ?>">
                                <td class="text-center"><?php echo $s['id']; ?></td>
                                <td class="text-center"><?php echo $s['name']; ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($s['phone']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($s['address']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($s['email']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="post" id="supplierEditForm" class="d-none">
                <input type="hidden" name="crud_action" value="update_supplier">
                <input type="hidden" name="supplier_id" id="editSupplierId">
                <input type="hidden" name="supplier_name" id="editSupplierName">
                <input type="hidden" name="supplier_phone" id="editSupplierPhone">
                <input type="hidden" name="supplier_address" id="editSupplierAddress">
                <input type="hidden" name="supplier_email" id="editSupplierEmail">
            </form>

        </div>

    </div>
    <script>
    let selectedSupplierId = null;
    let pendingDeleteSupplierId = null;
    let isSupplierDetailReadOnly = false;

    const addSupplierModalEl = document.getElementById('addSupplierModal');
    if (addSupplierModalEl && addSupplierModalEl.parentElement !== document.body) {
        document.body.appendChild(addSupplierModalEl);
    }

    const deleteSupplierModalEl = document.getElementById('deleteSupplierModal');
    if (deleteSupplierModalEl && deleteSupplierModalEl.parentElement !== document.body) {
        document.body.appendChild(deleteSupplierModalEl);
    }

    const detailOverlayEl = document.getElementById('detailOverlay');
    if (detailOverlayEl && detailOverlayEl.parentElement !== document.body) {
        document.body.appendChild(detailOverlayEl);
    }

    const detailPanelEl = document.getElementById('detailPanel');
    if (detailPanelEl && detailPanelEl.parentElement !== document.body) {
        document.body.appendChild(detailPanelEl);
    }

    // Handle row selection
    document.querySelectorAll('.supplier-row').forEach(row => {
        row.addEventListener('click', function() {
            document.querySelectorAll('.supplier-row').forEach(r => r.classList.remove('selected'));
            this.classList.add('selected');
            selectedSupplierId = this.getAttribute('data-id');
            document.getElementById('btnEditSupplier').disabled = false;
            document.getElementById('btnViewSupplier').disabled = false;
            document.getElementById('btnDeleteSupplier').disabled = false;
        });
    });

    function setSupplierDetailReadOnly(readOnly) {
        isSupplierDetailReadOnly = !!readOnly;

        const nameField = document.getElementById('detailName');
        const phoneField = document.getElementById('detailPhone');
        const addressField = document.getElementById('detailAddress');
        const emailField = document.getElementById('detailEmail');
        if (nameField) {
            nameField.readOnly = isSupplierDetailReadOnly;
        }
        if (phoneField) {
            phoneField.readOnly = isSupplierDetailReadOnly;
        }
        if (addressField) {
            addressField.readOnly = isSupplierDetailReadOnly;
        }
        if (emailField) {
            emailField.readOnly = isSupplierDetailReadOnly;
        }

        const saveBtn = document.getElementById('btnDetailSave');
        if (saveBtn) {
            saveBtn.style.display = isSupplierDetailReadOnly ? 'none' : 'inline-block';
        }
    }

    // Show detail panel with data
    function showDetailPanel(id, name, phone, address, email, readOnly = false) {
        document.getElementById('detailId').value = id;
        document.getElementById('detailName').value = name;
        document.getElementById('detailPhone').value = phone;
        document.getElementById('detailAddress').value = address;
        document.getElementById('detailEmail').value = email;
        setSupplierDetailReadOnly(readOnly);
        document.getElementById('detailPanel').classList.add('show');
        document.getElementById('detailOverlay').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    // Close detail panel
    function closeDetailPanel() {
        document.getElementById('detailPanel').classList.remove('show');
        document.getElementById('detailOverlay').classList.remove('show');
        document.body.style.overflow = '';
    }

    // Edit button click
    document.getElementById('btnEditSupplier').addEventListener('click', function() {
        const selectedRow = document.querySelector('.supplier-row.selected');
        if (!selectedRow) {
            alert('Vui lòng chọn nhà cung cấp để sửa');
            return;
        }

        showDetailPanel(
            selectedRow.getAttribute('data-id'),
            selectedRow.getAttribute('data-name'),
            selectedRow.getAttribute('data-phone'),
            selectedRow.getAttribute('data-address'),
            selectedRow.getAttribute('data-email'),
            false
        );
    });

    document.getElementById('btnViewSupplier').addEventListener('click', function() {
        const selectedRow = document.querySelector('.supplier-row.selected');
        if (!selectedRow) {
            alert('Vui lòng chọn nhà cung cấp để xem chi tiết');
            return;
        }

        showDetailPanel(
            selectedRow.getAttribute('data-id'),
            selectedRow.getAttribute('data-name'),
            selectedRow.getAttribute('data-phone'),
            selectedRow.getAttribute('data-address'),
            selectedRow.getAttribute('data-email'),
            true
        );
    });

    // Detail Save button
    document.getElementById('btnDetailSave').addEventListener('click', function() {
        if (isSupplierDetailReadOnly) {
            return;
        }

        const id = document.getElementById('detailId').value;
        const name = document.getElementById('detailName').value.trim();
        const phone = document.getElementById('detailPhone').value.trim();
        const address = document.getElementById('detailAddress').value.trim();
        const email = document.getElementById('detailEmail').value.trim();

        if (!name) {
            alert('Tên nhà cung cấp không được để trống');
            return;
        }

        if (!phone || !address || !email) {
            alert('Vui lòng nhập đầy đủ thông tin nhà cung cấp.');
            return;
        }

        if (!/^\S+@\S+\.\S+$/.test(email)) {
            alert('Email nhà cung cấp không hợp lệ.');
            return;
        }

        document.getElementById('editSupplierId').value = id;
        document.getElementById('editSupplierName').value = name;
        document.getElementById('editSupplierPhone').value = phone;
        document.getElementById('editSupplierAddress').value = address;
        document.getElementById('editSupplierEmail').value = email;
        document.getElementById('supplierEditForm').submit();
    });

    // Delete button click
    document.getElementById('btnDeleteSupplier').addEventListener('click', function() {
        const selectedRow = document.querySelector('.supplier-row.selected');
        if (!selectedRow) {
            alert('Vui lòng chọn nhà cung cấp để xóa');
            return;
        }

        pendingDeleteSupplierId = selectedRow.getAttribute('data-id');
        document.getElementById('deleteSupplierName').textContent = selectedRow.getAttribute('data-name') ||
            pendingDeleteSupplierId;
        const deleteModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteSupplierModal'));
        deleteModal.show();
    });

    document.getElementById('btnConfirmDeleteSupplier').addEventListener('click', function() {
        if (!pendingDeleteSupplierId) {
            return;
        }

        const id = pendingDeleteSupplierId;
        const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteSupplierModal'));
        if (deleteModal) {
            deleteModal.hide();
        }
        deleteSupplier(id);
    });

    document.getElementById('deleteSupplierModal').addEventListener('hidden.bs.modal', function() {
        pendingDeleteSupplierId = null;
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDetailPanel();
        }
    });

    document.getElementById('addSupplierModal').addEventListener('show.bs.modal', function() {
        closeDetailPanel();
        const supplierIdInput = document.getElementById('supplierId');
        if (supplierIdInput) {
            supplierIdInput.value = '<?php echo htmlspecialchars($nextSupplierId); ?>';
        }
    });

    function deleteSupplier(id) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="crud_action" value="delete_supplier">
            <input type="hidden" name="supplier_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    function syncFixedTopOffset() {
        const fixedTop = document.querySelector('.supplier-top-sticky');
        const contentOffset = document.getElementById('supplierContentOffset');
        if (!fixedTop || !contentOffset) {
            return;
        }

        const topHeight = Math.ceil(fixedTop.getBoundingClientRect().height);
        contentOffset.style.top = `${topHeight }px`;
    }

    window.addEventListener('resize', syncFixedTopOffset);
    window.addEventListener('load', syncFixedTopOffset);
    syncFixedTopOffset();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="admin-search.js?v=20260414-2"></script>
</body>

</html>