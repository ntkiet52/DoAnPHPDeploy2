<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: ../Login/Dangnhap.php');
    exit;
}

function pickOrderValue(array $row, array $keys, $default = '') {
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

function normalizeOrderStatus(string $rawStatus): array {
    $status = mb_strtolower(trim($rawStatus));

    if ($status === '' || in_array($status, ['cho_duyet', 'chờ duyệt', 'chua duyet', 'chưa duyệt', 'pending', 'cd'], true)) {
        return ['key' => 'pending', 'label' => 'Chờ duyệt', 'class' => 'status-pending', 'progress' => 'Đơn đang chờ shop duyệt.'];
    }

    if (in_array($status, ['da_duyet', 'đã duyệt', 'approved', 'dd', 'px'], true)) {
        return ['key' => 'approved', 'label' => 'Đã duyệt', 'class' => 'status-approved', 'progress' => 'Shop đã xác nhận đơn hàng.'];
    }

    if (str_contains($status, 'giao') || str_contains($status, 'ship')) {
        if (str_contains($status, 'gần') || str_contains($status, 'gan') || str_contains($status, 'near')) {
            return ['key' => 'near_delivery', 'label' => 'Gần giao', 'class' => 'status-near-delivery', 'progress' => 'Shipper sắp giao tới bạn.'];
        }

        return ['key' => 'shipping', 'label' => 'Đang giao', 'class' => 'status-shipping', 'progress' => 'Đơn đang trên đường giao đến bạn.'];
    }

    if (str_contains($status, 'nhận') || str_contains($status, 'nhan') || str_contains($status, 'received') || str_contains($status, 'hoan') || str_contains($status, 'xong') || str_contains($status, 'done') || str_contains($status, 'success')) {
        return ['key' => 'completed', 'label' => 'Đã nhận', 'class' => 'status-completed', 'progress' => 'Bạn đã nhận hàng thành công.'];
    }

    return [
        'key' => 'other',
        'label' => trim($rawStatus) !== '' ? trim($rawStatus) : 'Chờ duyệt',
        'class' => 'status-other',
        'progress' => 'Đơn hàng đang được xử lý.',
    ];
}

$userName = trim((string) ($_SESSION['user_name'] ?? 'Khách hàng'));
$userEmail = strtolower(trim((string) ($_SESSION['user_email'] ?? '')));

$orders = [];
$dbError = '';
$flashMessage = '';
$flashType = 'success';

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

    $khColumns = getExistingColumns($pdo, 'khachhang');
    $khIdCol = pickExistingColumn($khColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);
    $khNameCol = pickExistingColumn($khColumns, ['tenkhachhang', 'ten_khach_hang', 'tenkh', 'hoten', 'name']);
    $khTaxCol = pickExistingColumn($khColumns, ['masothue', 'ma_so_thue', 'email']);

    $customerIds = [];

    if ($khIdCol !== null) {
        if ($khTaxCol !== null && $userEmail !== '') {
            $customerByEmailStmt = $pdo->prepare("SELECT `{$khIdCol}` FROM khachhang WHERE LOWER(`{$khTaxCol}`) = LOWER(:email)");
            $customerByEmailStmt->execute([':email' => $userEmail]);
            foreach ($customerByEmailStmt->fetchAll(PDO::FETCH_COLUMN) as $customerId) {
                $customerId = trim((string) $customerId);
                if ($customerId !== '') {
                    $customerIds[$customerId] = true;
                }
            }
        }

        if ($khNameCol !== null && $userName !== '') {
            $customerByNameStmt = $pdo->prepare("SELECT `{$khIdCol}` FROM khachhang WHERE LOWER(`{$khNameCol}`) = LOWER(:name)");
            $customerByNameStmt->execute([':name' => $userName]);
            foreach ($customerByNameStmt->fetchAll(PDO::FETCH_COLUMN) as $customerId) {
                $customerId = trim((string) $customerId);
                if ($customerId !== '') {
                    $customerIds[$customerId] = true;
                }
            }
        }
    }

    $customerIds = array_keys($customerIds);

    $pxColumns = getExistingColumns($pdo, 'phieuxuat');
    $ctpxColumns = getExistingColumns($pdo, 'chitietphieuxuat');

    $orderIdCol = pickExistingColumn($pxColumns, ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id']);
    $orderCustomerCol = pickExistingColumn($pxColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'idkhachhang']);
    $orderDateCol = pickExistingColumn($pxColumns, ['ngayxuat', 'ngay_xuat', 'ngaydat', 'ngay_dat', 'ngaylap']);
    $orderStatusCol = pickExistingColumn($pxColumns, ['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu']);
    $orderTotalCol = pickExistingColumn($pxColumns, ['tongtien', 'tong_tien', 'thanhtien', 'thanh_tien', 'total']);

    $detailOrderIdCol = pickExistingColumn($ctpxColumns, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon']);
    $detailProductCol = pickExistingColumn($ctpxColumns, ['mahang', 'ma_hang', 'idhanghoa']);
    $detailPriceCol = pickExistingColumn($ctpxColumns, ['giaban', 'gia_ban', 'giaxuat', 'gia_xuat', 'dongia', 'don_gia', 'gia']);
    $detailQtyCol = pickExistingColumn($ctpxColumns, ['soluongpx', 'so_luong_px', 'soluong', 'so_luong']);
    $detailTotalCol = pickExistingColumn($ctpxColumns, ['thanhtienpx', 'thanh_tien_px', 'thanhtien', 'thanh_tien', 'tongtien', 'tong_tien']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_received') {
        $confirmOrderId = trim((string) ($_POST['order_id'] ?? ''));
        if ($confirmOrderId === '') {
            $flashMessage = 'Không xác định được đơn hàng để xác nhận.';
            $flashType = 'danger';
        } else if ($orderIdCol !== null && $orderCustomerCol !== null && $orderStatusCol !== null && count($customerIds) > 0) {
            $placeholders = implode(', ', array_fill(0, count($customerIds), '?'));
            $types = str_repeat('s', count($customerIds) + 2);
            $params = array_merge(['Đã nhận', $confirmOrderId], $customerIds);

            $sqlConfirm = "UPDATE phieuxuat
                           SET `{$orderStatusCol}` = ?
                           WHERE `{$orderIdCol}` = ?
                           AND `{$orderCustomerCol}` IN ({$placeholders})";

            $confirmStmt = $pdo->prepare($sqlConfirm);
            $confirmStmt->execute($params);

            if ($confirmStmt->rowCount() > 0) {
                $flashMessage = 'Đã xác nhận đơn ' . $confirmOrderId . ' là đã nhận.';
                $flashType = 'success';
            } else {
                $flashMessage = 'Không thể cập nhật đơn hàng. Vui lòng thử lại.';
                $flashType = 'warning';
            }
        } else {
            $flashMessage = 'Không thể cập nhật trạng thái đơn hàng với cấu trúc dữ liệu hiện tại.';
            $flashType = 'danger';
        }
    }

    if ($orderIdCol !== null && $orderCustomerCol !== null && count($customerIds) > 0) {
        $placeholders = implode(', ', array_fill(0, count($customerIds), '?'));
        $orderStmt = $pdo->prepare("SELECT * FROM phieuxuat WHERE `{$orderCustomerCol}` IN ({$placeholders}) ORDER BY `{$orderIdCol}` DESC");
        $orderStmt->execute($customerIds);
        $orderRows = $orderStmt->fetchAll();

        $orderIdList = [];
        foreach ($orderRows as $orderRow) {
            $orderId = trim((string) pickOrderValue($orderRow, [$orderIdCol], ''));
            if ($orderId !== '') {
                $orderIdList[] = $orderId;
            }
        }

        $productMap = [];
        try {
            $hangRows = $pdo->query('SELECT * FROM hanghoa')->fetchAll();
            foreach ($hangRows as $hangRow) {
                $maHang = (string) pickOrderValue($hangRow, ['mahang', 'ma_hang', 'idhanghoa', 'id'], '');
                $tenHang = (string) pickOrderValue($hangRow, ['tenhang', 'ten_hang', 'tensp', 'tensanpham', 'name'], 'Sản phẩm');
                if ($maHang !== '') {
                    $productMap[$maHang] = $tenHang;
                }
            }
        } catch (Throwable $ignored) {
        }

        $orderDetailMap = [];
        if ($detailOrderIdCol !== null && count($orderIdList) > 0) {
            $detailPlaceholders = implode(', ', array_fill(0, count($orderIdList), '?'));
            $detailStmt = $pdo->prepare("SELECT * FROM chitietphieuxuat WHERE `{$detailOrderIdCol}` IN ({$detailPlaceholders})");
            $detailStmt->execute($orderIdList);
            $detailRows = $detailStmt->fetchAll();

            foreach ($detailRows as $detailRow) {
                $detailOrderId = (string) pickOrderValue($detailRow, [$detailOrderIdCol], '');
                if ($detailOrderId === '') {
                    continue;
                }

                $maHang = $detailProductCol !== null ? (string) pickOrderValue($detailRow, [$detailProductCol], '') : '';
                $tenHang = $maHang !== '' && isset($productMap[$maHang]) ? (string) $productMap[$maHang] : ($maHang !== '' ? $maHang : 'Sản phẩm');
                $qty = $detailQtyCol !== null ? (int) pickOrderValue($detailRow, [$detailQtyCol], 0) : 0;
                $price = $detailPriceCol !== null ? (float) pickOrderValue($detailRow, [$detailPriceCol], 0) : 0;
                $lineTotal = $detailTotalCol !== null ? (float) pickOrderValue($detailRow, [$detailTotalCol], 0) : 0;
                if ($lineTotal <= 0) {
                    $lineTotal = $qty * $price;
                }

                if (!isset($orderDetailMap[$detailOrderId])) {
                    $orderDetailMap[$detailOrderId] = [];
                }

                $orderDetailMap[$detailOrderId][] = [
                    'name' => $tenHang,
                    'qty' => $qty,
                    'price' => $price,
                    'line_total' => $lineTotal,
                ];
            }
        }

        foreach ($orderRows as $orderRow) {
            $orderId = (string) pickOrderValue($orderRow, [$orderIdCol], '');
            if ($orderId === '') {
                continue;
            }

            $rawDate = $orderDateCol !== null ? (string) pickOrderValue($orderRow, [$orderDateCol], '') : '';
            $displayDate = $rawDate;
            if ($rawDate !== '') {
                $timestamp = strtotime($rawDate);
                if ($timestamp !== false) {
                    $displayDate = date('d/m/Y H:i', $timestamp);
                }
            }

            $rawStatus = $orderStatusCol !== null ? (string) pickOrderValue($orderRow, [$orderStatusCol], '') : '';
            $statusMeta = normalizeOrderStatus($rawStatus);

            $total = $orderTotalCol !== null ? (float) pickOrderValue($orderRow, [$orderTotalCol], 0) : 0;
            if ($total <= 0 && isset($orderDetailMap[$orderId])) {
                foreach ($orderDetailMap[$orderId] as $detailItem) {
                    $total += (float) ($detailItem['line_total'] ?? 0);
                }
            }

            $orders[] = [
                'id' => $orderId,
                'date' => $displayDate,
                'status_key' => $statusMeta['key'] ?? 'other',
                'status_label' => $statusMeta['label'],
                'status_class' => $statusMeta['class'],
                'shipping_progress' => $statusMeta['progress'] ?? '',
                'total' => $total,
                'details' => $orderDetailMap[$orderId] ?? [],
            ];
        }
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
    <title>Đơn hàng của tôi - ACK Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f7fb; font-family: 'Segoe UI', sans-serif; }
        .panel { border: 1px solid #e8edf5; border-radius: 14px; background: #fff; box-shadow: 0 6px 20px rgba(0,0,0,.04); }
        .status-badge { padding: 6px 10px; border-radius: 999px; font-size: .8rem; font-weight: 600; display: inline-block; }
        .status-pending { background: #fff3cd; color: #8a6d3b; }
        .status-approved { background: #d1ecf1; color: #0c5460; }
        .status-shipping { background: #e2e3ff; color: #3f51b5; }
        .status-near-delivery { background: #eef2ff; color: #4338ca; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-other { background: #e9ecef; color: #495057; }
        .order-detail-row { border-top: 1px dashed #e5e7eb; padding-top: 10px; margin-top: 10px; }

        .table tbody tr.order-row {
            cursor: pointer;
            transition: background-color .18s ease;
        }

        .table tbody tr.order-row:hover {
            background: #f8f9ff;
        }

        .table tbody tr.order-row.selected {
            background: #eaf2ff;
            box-shadow: inset 4px 0 0 #2563eb;
        }

        .table tbody tr.order-row[data-status-key="pending"] td:first-child {
            border-left: 3px solid #f59e0b;
        }

        .table tbody tr.order-row[data-status-key="approved"] td:first-child {
            border-left: 3px solid #10b981;
        }

        .table tbody tr.order-row[data-status-key="shipping"] td:first-child,
        .table tbody tr.order-row[data-status-key="near_delivery"] td:first-child {
            border-left: 3px solid #3b82f6;
        }

        .table tbody tr.order-row[data-status-key="completed"] td:first-child {
            border-left: 3px solid #22c55e;
        }
    </style>
</head>
<body>
    <div class="container py-4 py-md-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0 fw-bold">Đơn hàng của tôi</h4>
            <div class="d-flex gap-2">
                <a href="tai-khoan.php?tab=manage" class="btn btn-outline-primary btn-sm"><i class="fas fa-user me-1"></i>Tài khoản</a>
                <a href="trangchu.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-house me-1"></i>Trang chủ</a>
            </div>
        </div>

        <?php if ($dbError !== ''): ?>
            <div class="alert alert-danger">Không thể tải đơn hàng: <?php echo htmlspecialchars($dbError); ?></div>
        <?php endif; ?>

        <?php if ($flashMessage !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flashType); ?>"><?php echo htmlspecialchars($flashMessage); ?></div>
        <?php endif; ?>

        <div class="panel p-3 p-md-4">
            <?php if (count($orders) === 0): ?>
                <div class="text-center py-4">
                    <div class="mb-2"><i class="fas fa-receipt fa-2x text-muted"></i></div>
                    <h6 class="fw-bold">Bạn chưa có đơn hàng nào</h6>
                    <p class="text-muted mb-3">Sau khi đặt mua, trạng thái đơn sẽ hiển thị ở đây.</p>
                    <a href="trangchu.php" class="btn btn-primary btn-sm">Mua sắm ngay</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Ngày đặt</th>
                                <th>Trạng thái</th>
                                <th>Vận chuyển</th>
                                <th class="text-end">Tổng tiền</th>
                                <th>Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr class="order-row" data-status-key="<?php echo htmlspecialchars((string) ($order['status_key'] ?? 'other'), ENT_QUOTES); ?>">
                                    <td class="fw-semibold"><?php echo htmlspecialchars((string) ($order['id'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($order['date'] ?? '')); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo htmlspecialchars((string) ($order['status_class'] ?? 'status-other')); ?>">
                                            <?php echo htmlspecialchars((string) ($order['status_label'] ?? 'Đang xử lý')); ?>
                                        </span>
                                    </td>
                                    <td class="small"><?php echo htmlspecialchars((string) ($order['shipping_progress'] ?? '')); ?></td>
                                    <td class="text-end fw-semibold"><?php echo number_format((float) ($order['total'] ?? 0), 0, ',', '.'); ?> ₫</td>
                                    <td>
                                        <details>
                                            <summary class="text-primary" style="cursor:pointer;">Xem sản phẩm</summary>
                                            <div class="order-detail-row">
                                                <?php if (count($order['details'] ?? []) === 0): ?>
                                                    <div class="text-muted small">Chưa có chi tiết sản phẩm.</div>
                                                <?php else: ?>
                                                    <?php foreach (($order['details'] ?? []) as $detail): ?>
                                                        <div class="d-flex justify-content-between small mb-1">
                                                            <span><?php echo htmlspecialchars((string) ($detail['name'] ?? 'Sản phẩm')); ?> × <?php echo (int) ($detail['qty'] ?? 0); ?></span>
                                                            <span><?php echo number_format((float) ($detail['line_total'] ?? 0), 0, ',', '.'); ?> ₫</span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </details>

                                        <?php if (in_array((string) ($order['status_key'] ?? ''), ['shipping', 'near_delivery'], true)): ?>
                                            <form method="post" class="mt-2">
                                                <input type="hidden" name="action" value="confirm_received">
                                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars((string) ($order['id'] ?? ''), ENT_QUOTES); ?>">
                                                <button type="submit" class="btn btn-sm btn-success">Đã nhận hàng</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const rows = document.querySelectorAll('tr.order-row');
            if (!rows.length) return;

            rows.forEach((row) => {
                row.addEventListener('click', function (event) {
                    if (event.target.closest('details, summary, button, a, form')) {
                        return;
                    }

                    rows.forEach((r) => r.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });
        })();
    </script>
    <script src="web-events.js"></script>
</body>
</html>
