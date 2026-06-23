<?php
require_once __DIR__ . '/../Login/admin_auth.php';

function generateNextDepartmentId(PDO $pdo): string {
    $rows = $pdo->query("SELECT MaBP FROM bophan")->fetchAll(PDO::FETCH_COLUMN);
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

    return 'BP' . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
}

$departments = [];
$dbError = '';
$crudMessage = '';
$crudError = '';
$nextDepartmentId = '';

$dbHost = 'webbanhang-mysql.mysql.database.azure.com';
$dbName = 'qlhethongbanhangmini';
$dbUser = 'webbanhang123';
$dbPass = 'thanhkiet1234ACK@';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string) ($_POST['crud_action'] ?? ''));

        if ($action === 'add_department') {
            $id = trim((string) ($_POST['department_id'] ?? ''));
            $name = trim((string) ($_POST['department_name'] ?? ''));

            if ($id === '') {
                $id = generateNextDepartmentId($pdo);
            }

            if ($id === '' || $name === '') {
                $crudError = 'Vui lòng nhập tên bộ phận.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO bophan (MaBP, TenBP) VALUES (?, ?)");
                    $stmt->execute([$id, $name]);
                    $crudMessage = 'Đã thêm bộ phận thành công.';
                } catch (Throwable $insertError) {
                    $crudError = 'Không thể thêm bộ phận: ' . $insertError->getMessage();
                }
            }
        }

        if ($action === 'update_department') {
            $id = trim((string) ($_POST['department_id'] ?? ''));
            $name = trim((string) ($_POST['department_name'] ?? ''));

            if ($id === '' || $name === '') {
                $crudError = 'Dữ liệu cập nhật bộ phận chưa hợp lệ.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE bophan SET TenBP = ? WHERE MaBP = ?");
                    $stmt->execute([$name, $id]);
                    $crudMessage = 'Đã cập nhật bộ phận.';
                } catch (Throwable $updateError) {
                    $crudError = 'Không thể cập nhật bộ phận: ' . $updateError->getMessage();
                }
            }
        }

        if ($action === 'delete_department') {
            $id = trim((string) ($_POST['department_id'] ?? ''));
            if ($id === '') {
                $crudError = 'Không xác định được bộ phận để xóa.';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM bophan WHERE MaBP = ?");
                    $stmt->execute([$id]);
                    $crudMessage = 'Đã xóa bộ phận.';
                } catch (Throwable $deleteError) {
                    $crudError = 'Không thể xóa bộ phận (có thể đang được nhân viên sử dụng): ' . $deleteError->getMessage();
                }
            }
        }
    }

    $rows = $pdo->query("SELECT MaBP, TenBP FROM bophan ORDER BY MaBP ASC")->fetchAll();
    foreach ($rows as $row) {
        $id = (string) ($row['MaBP'] ?? '');
        $departments[] = [
            'id' => $id,
            'name' => (string) ($row['TenBP'] ?? ''),
        ];
    }

    $nextDepartmentId = generateNextDepartmentId($pdo);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$totalDepartments = count($departments);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Admin - Quản lý bộ phận</title>
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

    .main-content {
        margin-left: var(--sidebar-width);
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }

    .department-top-sticky {
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

    .department-top-sticky .alert {
        margin-bottom: 10px;
    }

    .department-content-offset {
        position: fixed;
        left: calc(var(--sidebar-width) + 40px);
        right: 40px;
        top: 325px;
        bottom: 20px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    body.modal-open #departmentContentOffset {
        overflow: visible;
    }

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

    .btn-add-department {
        background-color: #2196f3;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 8px 20px;
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 30px;
    }

    .btn-add-department:hover {
        background-color: #1976d2;
        color: white;
    }

    .table-container {
        background: white;
        border-radius: 12px;
        border: 1px solid #f0f0f0;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        overflow: hidden;
        margin-top: 2px;
        flex: 1;
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    .department-table-scroll {
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
        padding: 12px 20px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.95rem;
        color: #444;
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

    .department-img {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid #d8dee9;
    }

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

    .detail-field input {
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 0.95rem;
        color: #444;
        background-color: #f8f9fa;
    }

    .detail-field input:focus {
        background-color: #fff;
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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

    .detail-actions button {
        font-size: 0.9rem;
        font-weight: 500;
        padding: 8px 18px;
    }

    @media (max-width: 768px) {
        .department-top-sticky {
            padding: 20px 16px 10px;
        }

        .department-content-offset {
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
            <a href="admin-nhacungcap.php" class="nav-item"><i class="fas fa-building"></i> Nhà cung cấp</a>
            <a href="admin-bophan.php" class="nav-item active"><i class="fas fa-sitemap"></i> Bộ phận</a>
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

        <div class="department-top-sticky">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-primary">Bảng điều khiển Admin</h4>
                <button class="btn btn-light rounded-circle shadow-sm"><i class="fas fa-times"></i></button>
            </div>

            <div class="top-header">
                <h2 class="page-title">Quản lý bộ phận</h2>
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
                    <span class="card-title-text">Tổng bộ phận</span>
                    <p class="card-number"><?php echo $totalDepartments; ?></p>
                </div>
                <div class="card-box">
                    <span class="card-title-text">Đang sử dụng</span>
                    <p class="card-number"><?php echo $totalDepartments; ?></p>
                </div>
                <div class="card-box">
                    <span class="card-title-text">Chưa có dữ liệu</span>
                    <p class="card-number"><?php echo ($totalDepartments === 0) ? 1 : 0; ?></p>
                </div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button class="btn btn-add-department mb-0" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="fas fa-plus me-1"></i> Thêm bộ phận
                </button>
                <button class="btn btn-warning fw-semibold" id="btnEditDepartment" disabled>
                    <i class="fas fa-pen me-1"></i> Sửa bộ phận
                </button>
                <button class="btn btn-info fw-semibold text-white" id="btnViewDepartment" disabled>
                    <i class="fas fa-eye me-1"></i> Xem chi tiết
                </button>
                <button class="btn btn-danger fw-semibold" id="btnDeleteDepartment" disabled>
                    <i class="fas fa-trash me-1"></i> Xóa
                </button>
            </div>
        </div>

        <div id="departmentContentOffset" class="department-content-offset">

            <div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="addDepartmentModalLabel">Thêm bộ phận</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                            <input type="hidden" name="crud_action" value="add_department">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="departmentId" class="form-label">Mã bộ phận</label>
                                        <input type="text" class="form-control" id="departmentId" name="department_id"
                                            value="<?php echo htmlspecialchars($nextDepartmentId); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="departmentName" class="form-label">Tên bộ phận</label>
                                        <input type="text" class="form-control" id="departmentName"
                                            name="department_name" placeholder="Nhập tên bộ phận" required>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" class="btn btn-primary">Lưu bộ phận</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="detailOverlay" class="detail-overlay" onclick="closeDetailPanel()"></div>

            <div id="detailPanel" class="detail-panel">
                <div class="detail-header">
                    <h5>Chi tiết bộ phận</h5>
                    <button type="button" class="btn-close" onclick="closeDetailPanel()"></button>
                </div>
                <div class="detail-content">
                    <div class="detail-field">
                        <label>Mã bộ phận</label>
                        <input type="text" id="detailId" readonly>
                    </div>
                    <div class="detail-field">
                        <label>Tên bộ phận</label>
                        <input type="text" id="detailName">
                    </div>
                </div>
                <div class="detail-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeDetailPanel()">Đóng</button>
                    <button type="button" class="btn btn-primary" id="btnDetailSave">Lưu thay đổi</button>
                </div>
            </div>

            <div class="modal fade" id="deleteDepartmentModal" tabindex="-1"
                aria-labelledby="deleteDepartmentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteDepartmentModalLabel">Xác nhận xóa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Bạn có chắc chắn muốn xóa bộ phận <strong id="deleteDepartmentName"></strong> không?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="button" class="btn btn-danger" id="btnConfirmDeleteDepartment">Xóa</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h5>Danh sách bộ phận</h5>
                    <a href="#" class="text-dark"><i class="fas fa-download"></i></a>
                </div>
                <div class="department-table-scroll">
                    <table class="table text-center">
                        <thead>
                            <tr>
                                <th class="text-center" width="35%">Mã bộ phận</th>
                                <th class="text-center" width="65%">Tên bộ phận</th>
                            </tr>
                        </thead>
                        <tbody id="departmentTableBody">
                            <?php foreach($departments as $d): ?>
                            <tr class="department-row" data-id="<?php echo htmlspecialchars($d['id']); ?>"
                                data-name="<?php echo htmlspecialchars($d['name']); ?>">
                                <td class="text-center"><?php echo htmlspecialchars($d['id']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($d['name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="post" id="departmentEditForm" class="d-none">
                <input type="hidden" name="crud_action" value="update_department">
                <input type="hidden" name="department_id" id="editDepartmentId">
                <input type="hidden" name="department_name" id="editDepartmentName">
            </form>

        </div>

    </div>

    <script>
    let selectedDepartmentId = null;
    let pendingDeleteDepartmentId = null;
    let isDepartmentDetailReadOnly = false;

    const addDepartmentModalEl = document.getElementById('addDepartmentModal');
    if (addDepartmentModalEl && addDepartmentModalEl.parentElement !== document.body) {
        document.body.appendChild(addDepartmentModalEl);
    }

    const deleteDepartmentModalEl = document.getElementById('deleteDepartmentModal');
    if (deleteDepartmentModalEl && deleteDepartmentModalEl.parentElement !== document.body) {
        document.body.appendChild(deleteDepartmentModalEl);
    }

    const detailOverlayEl = document.getElementById('detailOverlay');
    if (detailOverlayEl && detailOverlayEl.parentElement !== document.body) {
        document.body.appendChild(detailOverlayEl);
    }

    const detailPanelEl = document.getElementById('detailPanel');
    if (detailPanelEl && detailPanelEl.parentElement !== document.body) {
        document.body.appendChild(detailPanelEl);
    }

    document.querySelectorAll('.department-row').forEach(row => {
        row.addEventListener('click', function() {
            document.querySelectorAll('.department-row').forEach(r => r.classList.remove('selected'));
            this.classList.add('selected');
            selectedDepartmentId = this.getAttribute('data-id');
            document.getElementById('btnEditDepartment').disabled = false;
            document.getElementById('btnViewDepartment').disabled = false;
            document.getElementById('btnDeleteDepartment').disabled = false;
        });
    });

    function setDepartmentDetailReadOnly(readOnly) {
        isDepartmentDetailReadOnly = !!readOnly;

        const nameField = document.getElementById('detailName');
        if (nameField) {
            nameField.readOnly = isDepartmentDetailReadOnly;
        }

        const saveBtn = document.getElementById('btnDetailSave');
        if (saveBtn) {
            saveBtn.style.display = isDepartmentDetailReadOnly ? 'none' : 'inline-block';
        }
    }

    function showDetailPanel(id, name, readOnly = false) {
        document.getElementById('detailId').value = id;
        document.getElementById('detailName').value = name;
        setDepartmentDetailReadOnly(readOnly);
        document.getElementById('detailPanel').classList.add('show');
        document.getElementById('detailOverlay').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeDetailPanel() {
        document.getElementById('detailPanel').classList.remove('show');
        document.getElementById('detailOverlay').classList.remove('show');
        document.body.style.overflow = '';
    }

    document.getElementById('btnEditDepartment').addEventListener('click', function() {
        const selectedRow = document.querySelector('.department-row.selected');
        if (!selectedRow) {
            alert('Vui lòng chọn bộ phận để sửa');
            return;
        }

        showDetailPanel(
            selectedRow.getAttribute('data-id'),
            selectedRow.getAttribute('data-name'),
            false
        );
    });

    document.getElementById('btnViewDepartment').addEventListener('click', function() {
        const selectedRow = document.querySelector('.department-row.selected');
        if (!selectedRow) {
            alert('Vui lòng chọn bộ phận để xem chi tiết');
            return;
        }

        showDetailPanel(
            selectedRow.getAttribute('data-id'),
            selectedRow.getAttribute('data-name'),
            true
        );
    });

    document.getElementById('btnDetailSave').addEventListener('click', function() {
        if (isDepartmentDetailReadOnly) {
            return;
        }

        const id = document.getElementById('detailId').value;
        const name = document.getElementById('detailName').value.trim();

        if (!name) {
            alert('Tên bộ phận không được để trống');
            return;
        }

        document.getElementById('editDepartmentId').value = id;
        document.getElementById('editDepartmentName').value = name;
        document.getElementById('departmentEditForm').submit();
    });

    document.getElementById('btnDeleteDepartment').addEventListener('click', function() {
        const selectedRow = document.querySelector('.department-row.selected');
        if (!selectedRow) {
            alert('Vui lòng chọn bộ phận để xóa');
            return;
        }

        pendingDeleteDepartmentId = selectedRow.getAttribute('data-id');
        const departmentName = selectedRow.getAttribute('data-name') || pendingDeleteDepartmentId;
        document.getElementById('deleteDepartmentName').textContent = departmentName;

        const deleteModal = bootstrap.Modal.getOrCreateInstance(document.getElementById(
            'deleteDepartmentModal'));
        deleteModal.show();
    });

    document.getElementById('btnConfirmDeleteDepartment').addEventListener('click', function() {
        if (!pendingDeleteDepartmentId) {
            return;
        }

        const deleteModalEl = document.getElementById('deleteDepartmentModal');
        const deleteModal = bootstrap.Modal.getInstance(deleteModalEl);
        if (deleteModal) {
            deleteModal.hide();
        }

        deleteDepartment(pendingDeleteDepartmentId);
    });

    document.getElementById('deleteDepartmentModal').addEventListener('hidden.bs.modal', function() {
        pendingDeleteDepartmentId = null;
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDetailPanel();
        }
    });

    document.getElementById('addDepartmentModal').addEventListener('show.bs.modal', function() {
        closeDetailPanel();
        const departmentIdInput = document.getElementById('departmentId');
        if (departmentIdInput) {
            departmentIdInput.value = '<?php echo htmlspecialchars($nextDepartmentId); ?>';
        }
    });

    function deleteDepartment(id) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="crud_action" value="delete_department">
            <input type="hidden" name="department_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    function syncFixedTopOffset() {
        const fixedTop = document.querySelector('.department-top-sticky');
        const contentOffset = document.getElementById('departmentContentOffset');
        if (!fixedTop || !contentOffset) {
            return;
        }

        const topHeight = Math.ceil(fixedTop.getBoundingClientRect().height);
        contentOffset.style.top = `${topHeight - 2}px`;
    }

    window.addEventListener('resize', syncFixedTopOffset);
    window.addEventListener('load', syncFixedTopOffset);
    syncFixedTopOffset();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="admin-search.js?v=20260414-2"></script>
</body>

</html>