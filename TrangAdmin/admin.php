<?php
require_once __DIR__ . '/../Login/admin_auth.php';

function pickDashboardValue(array $row, array $keys, $default = '') {
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

function normalizeDashboardStatus(string $status): string {
    $normalized = trim(str_replace(['_', '-'], ' ', $status));
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    return mb_strtolower($normalized, 'UTF-8');
}

function formatDashboardStatusLabel(string $status): string {
    $normalized = normalizeDashboardStatus($status);
    if ($normalized === '') {
        return 'Chờ duyệt';
    }

    $map = [
        'cho duyet' => 'Chờ duyệt',
        'chua duyet' => 'Chờ duyệt',
        'pending' => 'Chờ duyệt',
        'da duyet' => 'Đã duyệt',
        'approved' => 'Đã duyệt',
        'dang giao' => 'Đang giao',
        'shipping' => 'Đang giao',
        'gan giao' => 'Gần giao',
        'near delivery' => 'Gần giao',
        'da nhan' => 'Đã nhận',
        'received' => 'Đã nhận',
        'da huy' => 'Đã hủy',
        'cancelled' => 'Đã hủy',
        'canceled' => 'Đã hủy',
    ];

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
}

function isCompletedDashboardOrder(string $status): bool {
    $statusLower = normalizeDashboardStatus($status);
    if ($statusLower === '') {
        return false;
    }

    $completedKeywords = ['đã nhận', 'da nhan', 'received', 'giao thành công', 'giao thanh cong', 'hoàn thành', 'hoan thanh', 'done', 'completed', 'success'];
    foreach ($completedKeywords as $keyword) {
        if (str_contains($statusLower, $keyword)) {
            return true;
        }
    }

    return false;
}

function isShippingDashboardOrder(string $status): bool {
    $statusLower = normalizeDashboardStatus($status);
    if ($statusLower === '' || isCompletedDashboardOrder($status)) {
        return false;
    }

    return str_contains($statusLower, 'giao') || str_contains($statusLower, 'ship') || str_contains($statusLower, 'vận') || str_contains($statusLower, 'van chuyen');
}

function isPendingDashboardOrder(string $status): bool {
    $statusLower = normalizeDashboardStatus($status);
    if ($statusLower === '') {
        return true;
    }

    $pendingKeywords = ['chờ', 'cho', 'chưa', 'chua', 'pending'];
    foreach ($pendingKeywords as $keyword) {
        if (str_contains($statusLower, $keyword)) {
            return true;
        }
    }

    return false;
}

$dbError = '';
$recent_orders = [];

$ordersToday = 0;
$totalProducts = 0;
$totalCustomers = 0;
$totalRevenue = 0;
$pendingOrders = 0;
$shippingOrders = 0;

$barLabels = [];
$barValues = [];
$lineLabels = [];
$lineValues = [];
$donutLabels = [];
$donutValues = [];

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

    try {
        $totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM hanghoa")->fetchColumn();
    } catch (Throwable $ignored) {
    }

    try {
        $totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM khachhang")->fetchColumn();
    } catch (Throwable $ignored) {
    }

    $customerMap = [];
    try {
        $customerRows = $pdo->query("SELECT * FROM khachhang")->fetchAll();
        foreach ($customerRows as $row) {
            $maKh = (string) pickDashboardValue($row, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);
            $tenKh = (string) pickDashboardValue($row, ['tenkhachhang', 'ten_khach_hang', 'hoten', 'ten', 'name']);
            if ($maKh !== '') {
                $customerMap[$maKh] = $tenKh;
            }
        }
    } catch (Throwable $ignored) {
    }

    $pxRows = [];
    try {
        $pxRows = $pdo->query("SELECT * FROM phieuxuat")->fetchAll();
    } catch (Throwable $ignored) {
    }

    $detailRows = [];
    try {
        $detailRows = $pdo->query("SELECT * FROM chitietphieuxuat")->fetchAll();
    } catch (Throwable $ignored) {
    }

    $orderTotalsFromDetails = [];
    foreach ($detailRows as $detailRow) {
        $detailOrderId = trim((string) pickDashboardValue($detailRow, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'ma_phieu', 'maphieu', 'madon', 'id'], ''));
        if ($detailOrderId === '') {
            continue;
        }

        $quantity = (float) pickDashboardValue($detailRow, ['soluong', 'so_luong', 'soluongpx', 'so_luong_px'], 0);
        $unitPrice = (float) pickDashboardValue($detailRow, ['giaxuat', 'gia_xuat', 'dongia', 'don_gia', 'gia', 'giaban', 'gia_ban'], 0);
        $lineTotal = (float) pickDashboardValue($detailRow, ['thanhtien', 'thanh_tien', 'tongtien', 'tong_tien', 'thanhtienpx', 'thanh_tien_px'], 0);
        if ($lineTotal <= 0) {
            $lineTotal = $quantity * $unitPrice;
        }

        if (!isset($orderTotalsFromDetails[$detailOrderId])) {
            $orderTotalsFromDetails[$detailOrderId] = 0;
        }
        $orderTotalsFromDetails[$detailOrderId] += $lineTotal;
    }

    $today = date('Y-m-d');
    $latestOrderTs = null;
    $orderMetrics = [];
    $successfulOrderMap = [];

    $buildChartWindows = static function (int $endTs): array {
        $barMap = [];
        $barLabels = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = strtotime("-{$i} days", strtotime(date('Y-m-d', $endTs)));
            $key = date('Y-m-d', $day);
            $barMap[$key] = 0;
            $barLabels[] = date('d/m', $day);
        }

        $lineMap = [];
        $lineLabels = [];
        $baseMonth = strtotime(date('Y-m-01', $endTs));
        for ($i = 5; $i >= 0; $i--) {
            $month = strtotime("-{$i} months", $baseMonth);
            $key = date('Y-m', $month);
            $lineMap[$key] = 0;
            $lineLabels[] = 'Tháng ' . date('n', $month);
        }

        return [$barMap, $barLabels, $lineMap, $lineLabels];
    };

    [$barMap, $barLabels, $lineMap, $lineLabels] = $buildChartWindows(time());

    foreach ($pxRows as $row) {
        $maDon = trim((string) pickDashboardValue($row, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon', 'id']));
        $tongTien = (float) pickDashboardValue($row, ['tongtien', 'tong_tien', 'thanhtien', 'thanh_tien', 'total'], 0);
        if ($tongTien <= 0 && $maDon !== '' && isset($orderTotalsFromDetails[$maDon])) {
            $tongTien = (float) $orderTotalsFromDetails[$maDon];
        }

        $status = (string) pickDashboardValue($row, ['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu'], 'Chưa duyệt');
        $displayStatus = formatDashboardStatusLabel($status);
        $statusLower = normalizeDashboardStatus($status);
        $isCompleted = isCompletedDashboardOrder($status);
        $isShipping = isShippingDashboardOrder($status);
        $isPending = isPendingDashboardOrder($status);

        if ($isCompleted) {
            $totalRevenue += $tongTien;
            if ($maDon !== '') {
                $successfulOrderMap[$maDon] = true;
            }
        }

        if ($isPending) {
            $pendingOrders++;
        }
        if ($isShipping) {
            $shippingOrders++;
        }

        $dateRaw = (string) pickDashboardValue($row, ['ngaydat', 'ngay_dat', 'ngayxuat', 'ngay_xuat', 'ngaylap'], '');
        if ($dateRaw !== '') {
            $ts = strtotime($dateRaw);
            if ($ts !== false) {
                if ($isCompleted && ($latestOrderTs === null || $ts > $latestOrderTs)) {
                    $latestOrderTs = $ts;
                }

                $dayKey = date('Y-m-d', $ts);
                if ($isCompleted && array_key_exists($dayKey, $barMap)) {
                    $barMap[$dayKey] += $tongTien;
                }

                $monthKey = date('Y-m', $ts);
                if ($isCompleted && array_key_exists($monthKey, $lineMap)) {
                    $lineMap[$monthKey] += $tongTien;
                }

                if ($dayKey === $today) {
                    $ordersToday++;
                }

                if ($isCompleted) {
                    $orderMetrics[] = [
                        'day' => $dayKey,
                        'month' => date('Y-m', $ts),
                        'amount' => $tongTien,
                    ];
                }
            }
        }

        $maKh = (string) pickDashboardValue($row, ['makhachhang', 'ma_khach_hang', 'makh'], '');
        $tenKh = (string) pickDashboardValue($row, ['tenkhachhang', 'ten_khach_hang', 'khachhang', 'tenkh'], '');
        if ($tenKh === '' && isset($customerMap[$maKh])) {
            $tenKh = $customerMap[$maKh];
        }
        if ($tenKh === '' && $maKh !== '') {
            $tenKh = $maKh;
        }

        $color = 'warning';
        if (str_contains($statusLower, 'xử') || str_contains($statusLower, 'xu ly') || str_contains($statusLower, 'xuly') || str_contains($statusLower, 'duyệt')) {
            $color = 'info';
        }
        if ($isCompleted) {
            $color = 'success';
        } else if ($isShipping) {
            $color = 'info';
        }

        $recent_orders[] = [
            'id' => $maDon,
            'name' => $tenKh,
            'amount' => number_format($tongTien, 0, ',', '.') . ' ₫',
            'status' => $displayStatus,
            'color' => $color,
            'sort_ts' => ($dateRaw !== '' && strtotime($dateRaw) !== false) ? strtotime($dateRaw) : 0,
        ];
    }

    if (count($orderMetrics) > 0 && array_sum($barMap) <= 0 && $latestOrderTs !== null) {
        [$barMap, $barLabels, $lineMap, $lineLabels] = $buildChartWindows($latestOrderTs);
        foreach ($orderMetrics as $metric) {
            $dayKey = $metric['day'];
            $monthKey = $metric['month'];
            $amount = (float) $metric['amount'];

            if (array_key_exists($dayKey, $barMap)) {
                $barMap[$dayKey] += $amount;
            }
            if (array_key_exists($monthKey, $lineMap)) {
                $lineMap[$monthKey] += $amount;
            }
        }
    }

    usort($recent_orders, function ($a, $b) {
        return ($b['sort_ts'] ?? 0) <=> ($a['sort_ts'] ?? 0);
    });
    $recent_orders = array_slice($recent_orders, 0, 5);

    $barValues = array_values($barMap);
    $lineValues = array_values($lineMap);

    $groupNameMap = [];
    $productGroupMap = [];
    $groupPurchasedMap = [];

    try {
        $groupRows = $pdo->query("SELECT * FROM nhomhang")->fetchAll();
        foreach ($groupRows as $groupRow) {
            $groupCode = (string) pickDashboardValue($groupRow, ['manhomhang', 'ma_nhom_hang', 'manhom', 'id'], '');
            $groupName = (string) pickDashboardValue($groupRow, ['tennhomhang', 'ten_nhom_hang', 'tennhom', 'name'], '');
            if ($groupCode !== '') {
                $groupNameMap[$groupCode] = $groupName !== '' ? $groupName : $groupCode;
            }
        }
    } catch (Throwable $ignored) {
    }

    try {
        $productRows = $pdo->query("SELECT * FROM hanghoa")->fetchAll();
        foreach ($productRows as $productRow) {
            $productCode = (string) pickDashboardValue($productRow, ['mahang', 'ma_hang', 'id'], '');
            $groupCode = (string) pickDashboardValue($productRow, ['manhomhang', 'ma_nhom_hang', 'manhom'], '');
            if ($productCode !== '') {
                $productGroupMap[$productCode] = $groupCode;
            }
        }
    } catch (Throwable $ignored) {
    }

    foreach ($detailRows as $detailRow) {
        $detailOrderId = trim((string) pickDashboardValue($detailRow, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'ma_phieu', 'maphieu', 'madon', 'id'], ''));
        if ($detailOrderId === '' || !isset($successfulOrderMap[$detailOrderId])) {
            continue;
        }

        $productCode = (string) pickDashboardValue($detailRow, ['mahang', 'ma_hang', 'idhanghoa'], '');
        $groupCode = $productGroupMap[$productCode] ?? 'KHAC';
        $groupName = $groupNameMap[$groupCode] ?? ($groupCode !== '' ? $groupCode : 'Khác');

        $quantity = (float) pickDashboardValue($detailRow, ['soluong', 'so_luong', 'soluongpx', 'so_luong_px'], 0);
        $quantityForChart = $quantity > 0 ? $quantity : 1;

        if (!isset($groupPurchasedMap[$groupName])) {
            $groupPurchasedMap[$groupName] = 0;
        }
        $groupPurchasedMap[$groupName] += $quantityForChart;
    }

    arsort($groupPurchasedMap);
    $groupPurchasedMap = array_slice($groupPurchasedMap, 0, 6, true);
    $donutLabels = array_keys($groupPurchasedMap);
    $donutValues = array_values($groupPurchasedMap);

    if (count($donutLabels) === 0) {
        $donutLabels = ['Chưa có dữ liệu'];
        $donutValues = [1];
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Admin - Dashboard</title>
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
        padding: 20px 30px;
    }

    /* --- CARDS --- */
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        height: 100%;
        flex-wrap: nowrap;
        overflow: hidden;
    }

    .stat-card>div:nth-child(2) {
        min-width: 0;
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
        margin-bottom: 15px;
    }

    .bg-gradient-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }

    .bg-gradient-pink {
        background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
    }

    .bg-gradient-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .bg-gradient-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0;
        white-space: nowrap;
        line-height: 1.1;
    }

    .stat-label {
        font-size: 0.9rem;
        color: #67748e;
        white-space: nowrap;
    }

    .stat-growth {
        font-size: 0.8rem;
        font-weight: bold;
        color: #10b981;
        margin-left: auto;
        white-space: nowrap;
        flex-shrink: 0;
    }

    /* --- CHARTS & TABLES --- */
    .chart-container {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        margin-bottom: 30px;
        height: 100%;
    }

    .section-title {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 20px;
        font-size: 1.1rem;
    }

    /* Order List Item */
    .order-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 0;
        border-bottom: none;
    }

    .order-item:last-child {
        border-bottom: none;
    }

    .badge-status {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        line-height: 1.2;
        margin-top: 4px;
    }

    .bg-soft-warning {
        background: #fff8dd;
        color: #f59e0b;
    }

    .bg-soft-info {
        background: #e0f2fe;
        color: #0ea5e9;
    }

    .bg-soft-success {
        background: #dcfce7;
        color: #10b981;
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
            <a href="admin.php" class="nav-item active">
                <i class="fas fa-chart-bar"></i> Tổng quan
            </a>
            <a href="admin-sanpham.php" class="nav-item">
                <i class="fas fa-box"></i> Sản phẩm
            </a>
            <a href="admin-nhomhang.php" class="nav-item"><i class="fas fa-folder"></i> Nhóm hàng</a>
            <a href="admin-nhaphang.php" class="nav-item"><i class="fas fa-truck-loading"></i> Nhập hàng</a>
            <a href="admin-nhacungcap.php" class="nav-item"><i class="fas fa-building"></i> Nhà cung cấp</a>
            <a href="admin-bophan.php" class="nav-item"><i class="fas fa-sitemap"></i> Bộ phận</a>
            <a href="admin-donhang.php" class="nav-item">
                <i class="fas fa-shopping-cart"></i> Đơn hàng
            </a>
            <a href="admin-nhanvien.php" class="nav-item">
                <i class="fas fa-user-tie"></i> Nhân viên
            </a>
            <a href="admin-khachhang.php" class="nav-item">
                <i class="fas fa-users"></i> Khách hàng
            </a>
            <a href="admin-voucher.php" class="nav-item">
                <i class="fas fa-ticket-alt"></i> Voucher
            </a>
            <a href="admin-caidat.php" class="nav-item">
                <i class="fas fa-cog"></i> Cài đặt
            </a>
        </nav>

        <a href="../Login/logout.php" class="logout-btn">Đăng xuất</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-primary">Bảng điều khiển Admin</h4>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-light rounded-circle shadow-sm"><i class="fas fa-times"></i></button>
            </div>
        </div>

        <?php if ($dbError !== ''): ?>
        <div class="alert alert-warning" role="alert">
            Không thể kết nối/lấy dữ liệu từ MySQL: <?php echo htmlspecialchars($dbError); ?>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card d-flex align-items-center">
                    <div class="icon-box bg-gradient-primary me-3"><i class="fas fa-shopping-cart"></i></div>
                    <div>
                        <div class="stat-label">Đơn hàng hôm nay</div>
                        <h4 class="stat-value"><?php echo $ordersToday; ?></h4>
                    </div>
                    <div class="stat-growth"><?php echo $pendingOrders; ?> chờ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card d-flex align-items-center">
                    <div class="icon-box bg-gradient-pink me-3"><i class="fas fa-box-open"></i></div>
                    <div>
                        <div class="stat-label">Sản phẩm</div>
                        <h4 class="stat-value"><?php echo number_format($totalProducts, 0, ',', '.'); ?></h4>
                    </div>
                    <div class="stat-growth">Kho hàng</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card d-flex align-items-center">
                    <div class="icon-box bg-gradient-warning me-3"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="stat-label">Khách hàng</div>
                        <h4 class="stat-value"><?php echo number_format($totalCustomers, 0, ',', '.'); ?></h4>
                    </div>
                    <div class="stat-growth"><?php echo $shippingOrders; ?> đang giao</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card d-flex align-items-center">
                    <div class="icon-box bg-gradient-success me-3"><i class="fas fa-wallet"></i></div>
                    <div>
                        <div class="stat-label">Doanh thu</div>
                        <h4 class="stat-value"><?php echo number_format($totalRevenue, 0, ',', '.'); ?>&nbsp;₫</h4>
                    </div>
                    <div class="stat-growth">Tổng đơn</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <div class="chart-container">
                    <h5 class="section-title">Doanh số bán hàng</h5>
                    <canvas id="barChart" height="120"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-container">
                    <h5 class="section-title">Danh mục khách đã mua</h5>
                    <div style="height: 250px; display: flex; justify-content: center;">
                        <canvas id="donutChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-7">
                <div class="chart-container">
                    <h5 class="section-title">Doanh thu 6 tháng</h5>
                    <canvas id="lineChart" height="150"></canvas>
                </div>
            </div>
            <div class="col-md-5">
                <div class="chart-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="section-title mb-0">Đơn hàng gần đây</h5>
                        <a href="#" class="text-primary text-decoration-none small">Xem tất cả</a>
                    </div>

                    <div>
                        <?php foreach($recent_orders as $order): ?>
                        <div class="order-item">
                            <div>
                                <div class="fw-bold text-dark"><?php echo $order['id']; ?></div>
                                <div class="small text-muted"><?php echo $order['name']; ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?php echo $order['amount']; ?></div>
                                <span class="badge-status bg-soft-<?php echo $order['color']; ?>">
                                    <?php echo $order['status']; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center text-muted small mt-4 mb-3">

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    const barLabels = <?php echo json_encode($barLabels, JSON_UNESCAPED_UNICODE); ?>;
    const barValues = <?php echo json_encode($barValues, JSON_UNESCAPED_UNICODE); ?>;
    const donutLabels = <?php echo json_encode($donutLabels, JSON_UNESCAPED_UNICODE); ?>;
    const donutValues = <?php echo json_encode($donutValues, JSON_UNESCAPED_UNICODE); ?>;
    const lineLabels = <?php echo json_encode($lineLabels, JSON_UNESCAPED_UNICODE); ?>;
    const lineValues = <?php echo json_encode($lineValues, JSON_UNESCAPED_UNICODE); ?>;

    // 1. BAR CHART (Cột)
    const ctxBar = document.getElementById('barChart').getContext('2d');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: barLabels,
            datasets: [{
                label: 'Sales',
                data: barValues,
                backgroundColor: '#007bff',
                borderRadius: 5,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        borderDash: [2, 4]
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // 2. DONUT CHART (Tròn)
    const ctxDonut = document.getElementById('donutChart').getContext('2d');
    new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: donutLabels,
            datasets: [{
                data: donutValues,
                backgroundColor: ['#9333ea', '#f59e0b', '#007bff', '#db2777', '#10b981', '#6366f1'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            }
        }
    });

    // 3. LINE CHART (Đường)
    const ctxLine = document.getElementById('lineChart').getContext('2d');
    new Chart(ctxLine, {
        type: 'line',
        data: {
            labels: lineLabels,
            datasets: [{
                label: 'Doanh thu (Triệu)',
                data: lineValues,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4, // Tạo đường cong mềm mại
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#007bff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    grid: {
                        borderDash: [5, 5]
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    </script>
    <script src="admin-search.js"></script>
</body>

</html>