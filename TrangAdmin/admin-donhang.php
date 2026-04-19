<?php
require_once __DIR__ . '/../Login/admin_auth.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

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

    if (str_contains($status, 'hủy') || str_contains($status, 'huy') || str_contains($status, 'cancel') || str_contains($status, 'void')) {
        return [
            'key' => 'cancelled',
            'label' => 'Đã hủy',
            'class' => 'status-cancelled',
        ];
    }

    if (str_contains($status, 'chờ thanh toán') || str_contains($status, 'cho thanh toan') || str_contains($status, 'qr')) {
        return [
            'key' => 'pending_payment',
            'label' => 'Chờ thanh toán QR',
            'class' => 'status-pending-payment',
        ];
    }

    if ($status === '' || $status === 'cho_duyet' || $status === 'chờ duyệt' || $status === 'chua duyet' || $status === 'chưa duyệt' || $status === 'pending' || $status === 'cd') {
        return [
            'key' => 'pending',
            'label' => 'Chờ duyệt',
            'class' => 'status-pending',
        ];
    }

    if ($status === 'da_duyet' || $status === 'đã duyệt' || $status === 'approved' || $status === 'dd' || $status === 'px') {
        return [
            'key' => 'approved',
            'label' => 'Đã duyệt',
            'class' => 'status-approved',
        ];
    }

    if (str_contains($status, 'giao') || str_contains($status, 'ship')) {
        if (str_contains($status, 'gần') || str_contains($status, 'gan') || str_contains($status, 'near')) {
            return [
                'key' => 'near_delivery',
                'label' => 'Gần giao',
                'class' => 'status-near-delivery',
            ];
        }

        return [
            'key' => 'shipping',
            'label' => 'Đang giao',
            'class' => 'status-shipping',
        ];
    }

    if (str_contains($status, 'nhận') || str_contains($status, 'nhan') || str_contains($status, 'received') || str_contains($status, 'hoan') || str_contains($status, 'xong') || str_contains($status, 'done') || str_contains($status, 'success')) {
        return [
            'key' => 'completed',
            'label' => 'Giao thành công',
            'class' => 'status-completed',
        ];
    }

    return [
        'key' => 'other',
        'label' => trim($rawStatus) !== '' ? trim($rawStatus) : 'Chờ duyệt',
        'class' => 'status-other',
    ];
}

function isQrPaymentMethod(string $rawMethod): bool {
    $method = mb_strtolower(trim($rawMethod));
    if ($method === '') {
        return false;
    }

    return str_contains($method, 'qr')
        || str_contains($method, 'chuyển khoản')
        || str_contains($method, 'chuyen khoan');
}

function isPaymentConfirmed(string $rawPaymentStatus): bool {
    $paymentStatus = mb_strtolower(trim($rawPaymentStatus));
    if ($paymentStatus === '') {
        return false;
    }

    return str_contains($paymentStatus, 'đã chuyển khoản')
        || str_contains($paymentStatus, 'da chuyen khoan')
        || str_contains($paymentStatus, 'đã thanh toán')
        || str_contains($paymentStatus, 'da thanh toan')
        || str_contains($paymentStatus, 'paid');
}

function ensureOrderPaymentMetaTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS phieuxuat_thanhtoan_map (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ma_don_hang VARCHAR(100) NOT NULL,
        phuong_thuc_thanh_toan VARCHAR(120) NOT NULL,
        trang_thai_thanh_toan VARCHAR(120) NOT NULL,
        tao_luc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        cap_nhat_luc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ma_don_hang (ma_don_hang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureOrderVoucherMetaTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS phieuxuat_voucher_map (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ma_don_hang VARCHAR(100) NOT NULL,
        ma_voucher VARCHAR(100) DEFAULT NULL,
        tong_tam_tinh DECIMAL(18,2) NOT NULL DEFAULT 0,
        so_tien_giam DECIMAL(18,2) NOT NULL DEFAULT 0,
        tong_thanh_toan DECIMAL(18,2) NOT NULL DEFAULT 0,
        tao_luc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        cap_nhat_luc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_voucher_ma_don_hang (ma_don_hang),
        INDEX idx_voucher_ma_voucher (ma_voucher)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureVoucherUsageTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS voucher_nguoi_dung_da_dung (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_key VARCHAR(100) NOT NULL,
        id_voucher INT NOT NULL,
        ma_voucher VARCHAR(100) NOT NULL,
        ma_don_hang VARCHAR(100) DEFAULT NULL,
        thoi_gian_su_dung DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_voucher (user_key, id_voucher),
        INDEX idx_user_key (user_key),
        INDEX idx_id_voucher (id_voucher),
        INDEX idx_ma_don_hang (ma_don_hang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function restoreVoucherForCancelledOrder(PDO $pdo, string $orderId): int {
    $orderId = trim($orderId);
    if ($orderId === '') {
        return 0;
    }

    $selectStmt = $pdo->prepare(
        'SELECT id_voucher
         FROM voucher_nguoi_dung_da_dung
         WHERE ma_don_hang = :order_id
         FOR UPDATE'
    );
    $selectStmt->execute([':order_id' => $orderId]);
    $usageRows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($usageRows) || count($usageRows) === 0) {
        return 0;
    }

    $voucherIdUsageCount = [];
    foreach ($usageRows as $usageRow) {
        $voucherId = (int) ($usageRow['id_voucher'] ?? 0);
        if ($voucherId <= 0) {
            continue;
        }

        if (!isset($voucherIdUsageCount[$voucherId])) {
            $voucherIdUsageCount[$voucherId] = 0;
        }
        $voucherIdUsageCount[$voucherId]++;
    }

    $deleteStmt = $pdo->prepare('DELETE FROM voucher_nguoi_dung_da_dung WHERE ma_don_hang = :order_id');
    $deleteStmt->execute([':order_id' => $orderId]);

    $deletedRows = (int) $deleteStmt->rowCount();
    if ($deletedRows <= 0) {
        return 0;
    }

    foreach ($voucherIdUsageCount as $voucherId => $usageCount) {
        if ($voucherId <= 0 || $usageCount <= 0) {
            continue;
        }

        $decStmt = $pdo->prepare(
            'UPDATE voucher
             SET so_luong_da_su_dung = CASE
                 WHEN so_luong_da_su_dung >= :usage_count THEN so_luong_da_su_dung - :usage_count
                 ELSE 0
             END
             WHERE id_voucher = :id_voucher'
        );
        $decStmt->execute([
            ':usage_count' => $usageCount,
            ':id_voucher' => $voucherId,
        ]);
    }

    return $deletedRows;
}

function restoreStockForCancelledOrder(
    PDO $pdo,
    string $orderId,
    ?string $detailOrderIdCol,
    ?string $detailProductCol,
    ?string $detailQtyCol,
    ?string $hangIdCol,
    ?string $hangStockCol
): int {
    if (
        trim($orderId) === ''
        || $detailOrderIdCol === null
        || $detailProductCol === null
        || $detailQtyCol === null
        || $hangIdCol === null
        || $hangStockCol === null
    ) {
        return 0;
    }

    $detailStmt = $pdo->prepare(
        "SELECT `{$detailProductCol}` AS product_id, SUM(`{$detailQtyCol}`) AS total_qty
         FROM chitietphieuxuat
         WHERE `{$detailOrderIdCol}` = :order_id
         GROUP BY `{$detailProductCol}`"
    );
    $detailStmt->execute([':order_id' => $orderId]);
    $detailRows = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($detailRows) || count($detailRows) === 0) {
        return 0;
    }

    $restoreStmt = $pdo->prepare(
        "UPDATE hanghoa
         SET `{$hangStockCol}` = `{$hangStockCol}` + :qty
         WHERE `{$hangIdCol}` = :product_id"
    );

    $restoredItems = 0;
    foreach ($detailRows as $detailRow) {
        $productId = trim((string) ($detailRow['product_id'] ?? ''));
        $qty = (int) ($detailRow['total_qty'] ?? 0);

        if ($productId === '' || $qty <= 0) {
            continue;
        }

        $restoreStmt->execute([
            ':qty' => $qty,
            ':product_id' => $productId,
        ]);

        if ($restoreStmt->rowCount() <= 0) {
            throw new RuntimeException('Không thể hoàn kho cho sản phẩm ' . $productId . '.');
        }

        $restoredItems++;
    }

    return $restoredItems;
}

$orders = [];
$dbError = '';
$autoCleanupNotice = '';
$crudMessage = '';
$crudError = '';
$orderDetailMap = [];

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

    ensureOrderPaymentMetaTable($pdo);
    ensureOrderVoucherMetaTable($pdo);
    ensureVoucherUsageTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $action = trim((string) ($_POST['crud_action'] ?? ''));

            if ($action === 'approve_order') {
                $orderId = trim((string) ($_POST['order_id'] ?? ''));

                if ($orderId === '') {
                    $crudError = 'Không xác định được đơn hàng để duyệt.';
                } else {
                    $phieuXuatColumnsForApprove = getExistingColumns($pdo, 'phieuxuat');
                    $orderIdColumn = pickExistingColumn(
                        $phieuXuatColumnsForApprove,
                        ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id']
                    );
                    $statusColumn = pickExistingColumn(
                        $phieuXuatColumnsForApprove,
                        ['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu']
                    );
                    $statusColumnsToSync = [];
                    foreach (['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu'] as $candidate) {
                        $resolved = pickExistingColumn($phieuXuatColumnsForApprove, [$candidate]);
                        if ($resolved !== null && !in_array($resolved, $statusColumnsToSync, true)) {
                            $statusColumnsToSync[] = $resolved;
                        }
                    }
                    $paymentMethodColumn = pickExistingColumn(
                        $phieuXuatColumnsForApprove,
                        ['hinhthucthanhtoan', 'hinh_thuc_thanh_toan', 'phuongthucthanhtoan', 'phuong_thuc_thanh_toan', 'ptthanhtoan', 'payment_method', 'thanhtoan']
                    );
                    $paymentStatusColumn = pickExistingColumn(
                        $phieuXuatColumnsForApprove,
                        ['trangthaithanhtoan', 'trang_thai_thanh_toan', 'ttthanhtoan', 'payment_status']
                    );

                    if ($orderIdColumn === null || $statusColumn === null || count($statusColumnsToSync) === 0) {
                        $crudError = 'Không tìm thấy cột cần thiết để duyệt đơn hàng.';
                    } else {
                        $selectParts = ["`{$statusColumn}` AS order_status"];
                        if ($paymentMethodColumn !== null) {
                            $selectParts[] = "`{$paymentMethodColumn}` AS payment_method";
                        }
                        if ($paymentStatusColumn !== null) {
                            $selectParts[] = "`{$paymentStatusColumn}` AS payment_status";
                        }

                        $checkStmt = $pdo->prepare(
                            "SELECT " . implode(', ', $selectParts) . "
                             FROM phieuxuat
                             WHERE `{$orderIdColumn}` = :order_id
                             LIMIT 1"
                        );
                        $checkStmt->execute([':order_id' => $orderId]);
                        $paymentRow = $checkStmt->fetch();

                        if (!is_array($paymentRow)) {
                            $crudError = 'Không tìm thấy đơn hàng cần duyệt.';
                        } else {
                            $orderStatusRaw = (string) ($paymentRow['order_status'] ?? '');
                            $orderStatusMeta = normalizeOrderStatus($orderStatusRaw);
                            $paymentMethodRaw = (string) ($paymentRow['payment_method'] ?? '');
                            $paymentStatusRaw = (string) ($paymentRow['payment_status'] ?? '');

                            $awaitingQrByStatus = (string) ($orderStatusMeta['key'] ?? '') === 'pending_payment';
                            $needsPaymentCheck = isQrPaymentMethod($paymentMethodRaw) || $awaitingQrByStatus;
                            $isReadyForApprove = $paymentStatusColumn !== null
                                ? isPaymentConfirmed($paymentStatusRaw)
                                : !$awaitingQrByStatus;

                            if ($needsPaymentCheck && !$isReadyForApprove) {
                                $crudError = 'Khách hàng chưa xác nhận chuyển khoản QR thành công. Không thể duyệt đơn này.';
                            }
                        }
                    }

                    if ($crudError === '') {
                        $setParts = [];
                        foreach ($statusColumnsToSync as $statusColSync) {
                            $setParts[] = "`{$statusColSync}` = :approved_status";
                        }

                        $approveStmt = $pdo->prepare(
                            "UPDATE phieuxuat
                             SET " . implode(', ', $setParts) . "
                             WHERE `{$orderIdColumn}` = :order_id"
                        );
                        $approveStmt->execute([
                            ':approved_status' => 'Đã duyệt',
                            ':order_id' => $orderId,
                        ]);

                        if ($approveStmt->rowCount() === 0) {
                            $crudError = 'Không tìm thấy đơn hàng để duyệt hoặc trạng thái không thay đổi.';
                        } else {
                            $crudMessage = 'Đã duyệt đơn hàng ' . $orderId . ' thành công.';
                        }
                    }
                }
            }

            if ($action === 'update_order_status') {
                $orderId = trim((string) ($_POST['order_id'] ?? ''));
                $nextStatus = trim((string) ($_POST['next_status'] ?? ''));

                $allowedStatuses = [
                    'Đã duyệt',
                    'Đang giao',
                    'Gần giao',
                    'Đã nhận',
                ];

                if ($orderId === '' || $nextStatus === '') {
                    $crudError = 'Thiếu thông tin cập nhật trạng thái đơn hàng.';
                } else if (!in_array($nextStatus, $allowedStatuses, true)) {
                    $crudError = 'Trạng thái cập nhật không hợp lệ.';
                } else {
                    $phieuXuatColumnsForUpdate = getExistingColumns($pdo, 'phieuxuat');
                    $orderIdColumn = pickExistingColumn(
                        $phieuXuatColumnsForUpdate,
                        ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id']
                    );
                    $statusColumn = pickExistingColumn(
                        $phieuXuatColumnsForUpdate,
                        ['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu']
                    );
                    $statusColumnsToSync = [];
                    foreach (['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu'] as $candidate) {
                        $resolved = pickExistingColumn($phieuXuatColumnsForUpdate, [$candidate]);
                        if ($resolved !== null && !in_array($resolved, $statusColumnsToSync, true)) {
                            $statusColumnsToSync[] = $resolved;
                        }
                    }
                    $paymentMethodColumn = pickExistingColumn(
                        $phieuXuatColumnsForUpdate,
                        ['hinhthucthanhtoan', 'hinh_thuc_thanh_toan', 'phuongthucthanhtoan', 'phuong_thuc_thanh_toan', 'ptthanhtoan', 'payment_method', 'thanhtoan']
                    );
                    $paymentStatusColumn = pickExistingColumn(
                        $phieuXuatColumnsForUpdate,
                        ['trangthaithanhtoan', 'trang_thai_thanh_toan', 'ttthanhtoan', 'payment_status']
                    );

                    if ($orderIdColumn === null || $statusColumn === null || count($statusColumnsToSync) === 0) {
                        $crudError = 'Không tìm thấy cột cần thiết để cập nhật trạng thái.';
                    } else {
                        $selectParts = ["`{$statusColumn}` AS order_status"];
                        if ($paymentMethodColumn !== null) {
                            $selectParts[] = "`{$paymentMethodColumn}` AS payment_method";
                        }
                        if ($paymentStatusColumn !== null) {
                            $selectParts[] = "`{$paymentStatusColumn}` AS payment_status";
                        }

                        $checkStmt = $pdo->prepare(
                            "SELECT " . implode(', ', $selectParts) . "
                             FROM phieuxuat
                             WHERE `{$orderIdColumn}` = :order_id
                             LIMIT 1"
                        );
                        $checkStmt->execute([':order_id' => $orderId]);
                        $paymentRow = $checkStmt->fetch();

                        if (!is_array($paymentRow)) {
                            $crudError = 'Không tìm thấy đơn hàng cần cập nhật trạng thái.';
                        } else {
                            $orderStatusRaw = (string) ($paymentRow['order_status'] ?? '');
                            $orderStatusMeta = normalizeOrderStatus($orderStatusRaw);
                            $paymentMethodRaw = (string) ($paymentRow['payment_method'] ?? '');
                            $paymentStatusRaw = (string) ($paymentRow['payment_status'] ?? '');

                            $awaitingQrByStatus = (string) ($orderStatusMeta['key'] ?? '') === 'pending_payment';
                            $needsPaymentCheck = isQrPaymentMethod($paymentMethodRaw) || $awaitingQrByStatus;
                            $isReadyForUpdate = $paymentStatusColumn !== null
                                ? isPaymentConfirmed($paymentStatusRaw)
                                : !$awaitingQrByStatus;

                            if ($needsPaymentCheck && !$isReadyForUpdate) {
                                $crudError = 'Khách hàng chưa xác nhận chuyển khoản QR thành công. Không thể cập nhật đơn này.';
                            }
                        }
                    }

                    if ($crudError === '') {
                        $setParts = [];
                        foreach ($statusColumnsToSync as $statusColSync) {
                            $setParts[] = "`{$statusColSync}` = :next_status";
                        }

                        $updateStatusStmt = $pdo->prepare(
                            "UPDATE phieuxuat
                             SET " . implode(', ', $setParts) . "
                             WHERE `{$orderIdColumn}` = :order_id"
                        );
                        $updateStatusStmt->execute([
                            ':next_status' => $nextStatus,
                            ':order_id' => $orderId,
                        ]);

                        if ($updateStatusStmt->rowCount() === 0) {
                            $crudError = 'Không tìm thấy đơn hàng để cập nhật hoặc trạng thái không thay đổi.';
                        } else {
                            $crudMessage = 'Đã cập nhật trạng thái đơn ' . $orderId . ' thành: ' . $nextStatus;
                        }
                    }
                }
            }

            if ($action === 'cancel_order') {
                $orderId = trim((string) ($_POST['order_id'] ?? ''));

                if ($orderId === '') {
                    $crudError = 'Không xác định được đơn hàng để hủy.';
                } else {
                    $phieuXuatColumnsForCancel = getExistingColumns($pdo, 'phieuxuat');
                    $ctpxColumnsForCancel = getExistingColumns($pdo, 'chitietphieuxuat');
                    $hhColumnsForCancel = getExistingColumns($pdo, 'hanghoa');

                    $orderIdColumn = pickExistingColumn(
                        $phieuXuatColumnsForCancel,
                        ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id']
                    );
                    $statusColumnsToSync = [];
                    foreach (['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu'] as $candidate) {
                        $resolved = pickExistingColumn($phieuXuatColumnsForCancel, [$candidate]);
                        if ($resolved !== null && !in_array($resolved, $statusColumnsToSync, true)) {
                            $statusColumnsToSync[] = $resolved;
                        }
                    }
                    $paymentStatusColumn = pickExistingColumn(
                        $phieuXuatColumnsForCancel,
                        ['trangthaithanhtoan', 'trang_thai_thanh_toan', 'ttthanhtoan', 'payment_status']
                    );

                    $detailOrderIdCol = pickExistingColumn($ctpxColumnsForCancel, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon']);
                    $detailProductCol = pickExistingColumn($ctpxColumnsForCancel, ['mahang', 'ma_hang', 'idhanghoa']);
                    $detailQtyCol = pickExistingColumn($ctpxColumnsForCancel, ['soluongpx', 'so_luong_px', 'soluong', 'so_luong']);
                    $hangIdCol = pickExistingColumn($hhColumnsForCancel, ['mahang', 'ma_hang', 'idhanghoa', 'id']);
                    $hangStockCol = pickExistingColumn($hhColumnsForCancel, ['soluongton', 'so_luong_ton', 'tonkho', 'ton_kho', 'soluong', 'so_luong']);

                    if ($orderIdColumn === null || count($statusColumnsToSync) === 0) {
                        $crudError = 'Không tìm thấy cột cần thiết để hủy đơn hàng.';
                    } else {
                        $pdo->beginTransaction();
                        try {
                            $checkStatusColumn = $statusColumnsToSync[0];
                            $checkStmt = $pdo->prepare(
                                "SELECT `{$checkStatusColumn}` AS current_status
                                 FROM phieuxuat
                                 WHERE `{$orderIdColumn}` = :order_id
                                 LIMIT 1
                                 FOR UPDATE"
                            );
                            $checkStmt->execute([':order_id' => $orderId]);
                            $row = $checkStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                            if (!is_array($row)) {
                                throw new RuntimeException('Không tìm thấy đơn hàng để hủy.');
                            }

                            $statusMeta = normalizeOrderStatus((string) ($row['current_status'] ?? ''));
                            if (($statusMeta['key'] ?? '') === 'cancelled') {
                                throw new RuntimeException('Đơn hàng này đã ở trạng thái hủy.');
                            }

                            if (in_array((string) ($statusMeta['key'] ?? ''), ['shipping', 'near_delivery', 'completed'], true)) {
                                throw new RuntimeException('Đơn đã giao/hoàn tất, không thể hủy.');
                            }

                            $setParts = [];
                            foreach ($statusColumnsToSync as $statusColSync) {
                                $setParts[] = "`{$statusColSync}` = :cancel_status";
                            }
                            $params = [
                                ':cancel_status' => 'Đã hủy',
                            ];

                            if ($paymentStatusColumn !== null) {
                                $setParts[] = "`{$paymentStatusColumn}` = :cancel_payment_status";
                                $params[':cancel_payment_status'] = 'Đã hủy';
                            }

                            $params[':order_id'] = $orderId;

                            $cancelStmt = $pdo->prepare(
                                "UPDATE phieuxuat
                                 SET " . implode(', ', $setParts) . "
                                 WHERE `{$orderIdColumn}` = :order_id"
                            );
                            $cancelStmt->execute($params);

                            if ($cancelStmt->rowCount() <= 0) {
                                throw new RuntimeException('Không thể hủy đơn. Vui lòng thử lại.');
                            }

                            restoreStockForCancelledOrder(
                                $pdo,
                                $orderId,
                                $detailOrderIdCol,
                                $detailProductCol,
                                $detailQtyCol,
                                $hangIdCol,
                                $hangStockCol
                            );

                            $restoredVoucherRows = restoreVoucherForCancelledOrder($pdo, $orderId);
                            $pdo->commit();

                            $crudMessage = 'Đã hủy đơn hàng ' . $orderId . ' thành công.';
                            if ($restoredVoucherRows > 0) {
                                $crudMessage .= ' Voucher đã được hoàn lại.';
                            }
                        } catch (Throwable $cancelError) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            throw $cancelError;
                        }
                    }
                }
            }

            if ($action === 'delete_order') {
                $orderId = trim((string) ($_POST['order_id'] ?? ''));

                if ($orderId === '') {
                    $crudError = 'Không xác định được đơn hàng để xóa.';
                } else {
                    $phieuXuatColumnsForDelete = getExistingColumns($pdo, 'phieuxuat');
                    $orderIdColumn = pickExistingColumn(
                        $phieuXuatColumnsForDelete,
                        ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id']
                    );

                    if ($orderIdColumn === null) {
                        $crudError = 'Không tìm thấy cột mã đơn trong bảng phiếu xuất để xóa.';
                    } else {
                        $pdo->beginTransaction();
                        try {
                            $detailOrderIdColumn = null;
                            try {
                                $detailColumns = getExistingColumns($pdo, 'chitietphieuxuat');
                                $detailOrderIdColumn = pickExistingColumn(
                                    $detailColumns,
                                    ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon']
                                );
                            } catch (Throwable $ignored) {
                            }

                            if ($detailOrderIdColumn !== null) {
                                $deleteDetailStmt = $pdo->prepare("DELETE FROM chitietphieuxuat WHERE `{$detailOrderIdColumn}` = :order_id");
                                $deleteDetailStmt->execute([':order_id' => $orderId]);
                            }

                            $deleteOrderStmt = $pdo->prepare("DELETE FROM phieuxuat WHERE `{$orderIdColumn}` = :order_id");
                            $deleteOrderStmt->execute([':order_id' => $orderId]);

                            if ($deleteOrderStmt->rowCount() === 0) {
                                throw new RuntimeException('Không tìm thấy đơn hàng để xóa.');
                            }

                            $pdo->commit();
                            $crudMessage = 'Đã xóa đơn hàng ' . $orderId . ' thành công.';
                        } catch (Throwable $deleteError) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            throw $deleteError;
                        }
                    }
                }
            }
        } catch (Throwable $postError) {
            if ($crudError === '') {
                $crudError = 'Không thể xử lý thao tác đơn hàng: ' . $postError->getMessage();
            }
        }
    }

    $phieuXuatColumns = [];
    try {
        $phieuXuatColumns = getExistingColumns($pdo, 'phieuxuat');
        $orderDateColumn = pickExistingColumn($phieuXuatColumns, ['ngayxuat', 'ngay_xuat', 'ngaydat', 'ngay_dat', 'ngaylap']);

        if ($orderDateColumn !== null) {
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM phieuxuat
                 WHERE `{$orderDateColumn}` IS NOT NULL
                 AND DATE(`{$orderDateColumn}`) <= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
            );
            $countStmt->execute();
            $oldOrderCount = (int) $countStmt->fetchColumn();

            if ($oldOrderCount > 0) {
                $autoCleanupNotice = 'Có ' . $oldOrderCount . ' đơn hàng quá 30 ngày. Vui lòng xóa thủ công khi cần.';
            }
        }
    } catch (Throwable $ignored) {
    }

    $khachHangMap = [];
    try {
        $khRows = $pdo->query("SELECT * FROM khachhang")->fetchAll();
        foreach ($khRows as $kh) {
            $maKh = (string) pickOrderValue($kh, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);
            $tenKh = (string) pickOrderValue($kh, ['tenkhachhang', 'ten_khach_hang', 'hoten', 'ten', 'name']);
            if ($maKh !== '') {
                $khachHangMap[$maKh] = $tenKh;
            }
        }
    } catch (Throwable $ignored) {
    }

    $hangHoaMap = [];
    try {
        $hangRows = $pdo->query("SELECT * FROM hanghoa")->fetchAll();
        foreach ($hangRows as $hangRow) {
            $maHang = (string) pickOrderValue($hangRow, ['mahang', 'ma_hang', 'idhanghoa', 'id']);
            $tenHang = (string) pickOrderValue($hangRow, ['tenhang', 'ten_hang', 'tensp', 'tensanpham', 'name']);
            if ($maHang !== '') {
                $hangHoaMap[$maHang] = $tenHang;
            }
        }
    } catch (Throwable $ignored) {
    }

    try {
        $detailRows = $pdo->query("SELECT * FROM chitietphieuxuat")->fetchAll();
        foreach ($detailRows as $detailRow) {
            $orderId = (string) pickOrderValue($detailRow, ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id']);
            if ($orderId === '') {
                continue;
            }

            $maHang = (string) pickOrderValue($detailRow, ['mahang', 'ma_hang', 'idhanghoa'], '');
            $tenHang = (string) pickOrderValue($detailRow, ['tenhang', 'ten_hang', 'tensp', 'tensanpham', 'name'], '');
            if ($tenHang === '' && $maHang !== '' && isset($hangHoaMap[$maHang])) {
                $tenHang = (string) $hangHoaMap[$maHang];
            }
            if ($tenHang === '') {
                $tenHang = $maHang !== '' ? $maHang : 'Sản phẩm';
            }

            $quantity = (int) pickOrderValue($detailRow, ['soluong', 'so_luong', 'soluongpx', 'so_luong_px'], 0);
            $unitPrice = (float) pickOrderValue($detailRow, ['giaxuat', 'gia_xuat', 'dongia', 'don_gia', 'gia', 'giaban', 'gia_ban'], 0);
            $lineTotal = (float) pickOrderValue($detailRow, ['thanhtien', 'thanh_tien', 'tongtien', 'tong_tien', 'thanhtienpx', 'thanh_tien_px'], 0);
            if ($lineTotal <= 0) {
                $lineTotal = $quantity * $unitPrice;
            }

            if (!isset($orderDetailMap[$orderId])) {
                $orderDetailMap[$orderId] = [];
            }

            $orderDetailMap[$orderId][] = [
                'code' => $maHang,
                'product' => $tenHang,
                'quantity' => $quantity,
                'quantity_number' => $quantity,
                'price' => number_format($unitPrice, 0, ',', '.') . ' ₫',
                'price_number' => $unitPrice,
                'total' => number_format($lineTotal, 0, ',', '.') . ' ₫',
                'total_number' => $lineTotal,
            ];
        }
    } catch (Throwable $ignored) {
    }

    $pxRows = $pdo->query("SELECT * FROM phieuxuat")->fetchAll();
    $paymentMetaMap = [];
    try {
        $metaRows = $pdo->query("SELECT ma_don_hang, phuong_thuc_thanh_toan, trang_thai_thanh_toan FROM phieuxuat_thanhtoan_map")->fetchAll();
        foreach ($metaRows as $metaRow) {
            $metaOrderId = trim((string) ($metaRow['ma_don_hang'] ?? ''));
            if ($metaOrderId === '') {
                continue;
            }
            $paymentMetaMap[$metaOrderId] = [
                'method' => trim((string) ($metaRow['phuong_thuc_thanh_toan'] ?? '')),
                'status' => trim((string) ($metaRow['trang_thai_thanh_toan'] ?? '')),
            ];
        }
    } catch (Throwable $ignored) {
    }

    $voucherMetaMap = [];
    try {
        $voucherRows = $pdo->query("SELECT ma_don_hang, ma_voucher, tong_tam_tinh, so_tien_giam, tong_thanh_toan FROM phieuxuat_voucher_map")->fetchAll();
        foreach ($voucherRows as $voucherRow) {
            $voucherOrderId = trim((string) ($voucherRow['ma_don_hang'] ?? ''));
            if ($voucherOrderId === '') {
                continue;
            }

            $voucherMetaMap[$voucherOrderId] = [
                'code' => trim((string) ($voucherRow['ma_voucher'] ?? '')),
                'subtotal' => (float) ($voucherRow['tong_tam_tinh'] ?? 0),
                'discount' => (float) ($voucherRow['so_tien_giam'] ?? 0),
                'final_total' => (float) ($voucherRow['tong_thanh_toan'] ?? 0),
            ];
        }
    } catch (Throwable $ignored) {
    }

    foreach ($pxRows as $row) {
        $id = (string) pickOrderValue($row, ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id']);

        $maKh = (string) pickOrderValue($row, ['makhachhang', 'ma_khach_hang', 'makh'], '');
        $customer = (string) pickOrderValue($row, ['tenkhachhang', 'ten_khach_hang', 'khachhang', 'tenkh'], '');
        if ($customer === '' && isset($khachHangMap[$maKh])) {
            $customer = $khachHangMap[$maKh];
        }

        $totalRaw = (float) pickOrderValue($row, ['tongtien', 'tong_tien', 'thanhtien', 'thanh_tien', 'total'], 0);
        $voucherMeta = $voucherMetaMap[$id] ?? null;
        if (is_array($voucherMeta)) {
            $totalRaw = max(0, (float) ($voucherMeta['final_total'] ?? 0));
        }
        if ($totalRaw <= 0 && isset($orderDetailMap[$id])) {
            foreach ($orderDetailMap[$id] as $detailItem) {
                $totalRaw += (float) ($detailItem['total_number'] ?? 0);
            }
        }

        $statusRaw = (string) pickOrderValue($row, ['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu'], '');
        $statusMeta = normalizeOrderStatus($statusRaw);

        $metaPaymentMethod = trim((string) (($paymentMetaMap[$id]['method'] ?? '')));
        $metaPaymentStatus = trim((string) (($paymentMetaMap[$id]['status'] ?? '')));

        $paymentMethodRaw = (string) pickOrderValue($row, ['hinhthucthanhtoan', 'hinh_thuc_thanh_toan', 'phuongthucthanhtoan', 'phuong_thuc_thanh_toan', 'ptthanhtoan', 'payment_method', 'thanhtoan'], '');
        if ($paymentMethodRaw === '' && $metaPaymentMethod !== '') {
            $paymentMethodRaw = $metaPaymentMethod;
        }

        $paymentStatusRaw = (string) pickOrderValue($row, ['trangthaithanhtoan', 'trang_thai_thanh_toan', 'ttthanhtoan', 'payment_status'], '');
        if ($paymentStatusRaw === '' && $metaPaymentStatus !== '') {
            $paymentStatusRaw = $metaPaymentStatus;
        }
        $paymentReady = true;
        if (($statusMeta['key'] ?? '') === 'pending_payment') {
            $paymentReady = false;
        } else if (isQrPaymentMethod($paymentMethodRaw)) {
            $paymentReady = isPaymentConfirmed($paymentStatusRaw);
        }

        $paymentMethodDisplay = trim($paymentMethodRaw) !== '' ? trim($paymentMethodRaw) : 'QR chuyển khoản';
        if (trim($paymentStatusRaw) !== '') {
            $paymentStatusDisplay = trim($paymentStatusRaw);
        } else if (($statusMeta['key'] ?? '') === 'pending_payment') {
            $paymentStatusDisplay = 'Chờ khách chuyển khoản';
        } else if ($paymentReady) {
            $paymentStatusDisplay = 'Đã chuyển khoản QR';
        } else {
            $paymentStatusDisplay = 'Chờ xác nhận thanh toán';
        }

        $dateRaw = (string) pickOrderValue($row, ['ngayxuat', 'ngay_xuat', 'ngaydat', 'ngay_dat', 'ngaylap'], '');
        $dateDisplay = $dateRaw;
        if ($dateRaw !== '') {
            $timestamp = strtotime($dateRaw);
            if ($timestamp !== false) {
                $dateDisplay = date('d/m/Y', $timestamp);
            }
        }

        $orders[] = [
            'id' => $id,
            'customer' => $customer,
            'total_number' => $totalRaw,
            'total' => number_format($totalRaw, 0, ',', '.') . ' ₫',
            'status' => $statusMeta['label'],
            'status_key' => $statusMeta['key'],
            'status_class' => $statusMeta['class'],
            'payment_method' => $paymentMethodDisplay,
            'payment_status' => $paymentStatusDisplay,
            'payment_ready' => $paymentReady,
            'date_raw' => $dateRaw,
            'date' => $dateDisplay,
            'ma_kh' => $maKh,
        ];
    }

    usort($orders, static function (array $a, array $b): int {
        $scoreA = in_array(($a['status_key'] ?? ''), ['pending', 'pending_payment'], true) ? 0 : 1;
        $scoreB = in_array(($b['status_key'] ?? ''), ['pending', 'pending_payment'], true) ? 0 : 1;
        if ($scoreA !== $scoreB) {
            return $scoreA <=> $scoreB;
        }

        $timeA = strtotime((string) ($a['date_raw'] ?? '')) ?: 0;
        $timeB = strtotime((string) ($b['date_raw'] ?? '')) ?: 0;
        return $timeB <=> $timeA;
    });
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$today = date('Y-m-d');
$ordersToday = 0;
$pendingCount = 0;
$shippingCount = 0;
$totalRevenue = 0;

foreach ($orders as $order) {
    if (in_array(($order['status_key'] ?? ''), ['pending', 'pending_payment'], true)) {
        $pendingCount++;
    }

    if (($order['status_key'] ?? '') === 'shipping') {
        $shippingCount++;
    }

    $orderDate = '';
    if (!empty($order['date_raw'])) {
        $ts = strtotime((string) $order['date_raw']);
        if ($ts !== false) {
            $orderDate = date('Y-m-d', $ts);
        }
    }
    if ($orderDate === $today) {
        $ordersToday++;
    }

    if (($order['status_key'] ?? '') !== 'cancelled') {
        $totalRevenue += (float) $order['total_number'];
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Admin - Quản lí đơn hàng</title>
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
        margin-left: var(--sidebar-width);
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }

    .order-top-sticky {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        z-index: 1030;
        background: #ffffff;
        padding: 20px 30px 12px;
        border-bottom: none;
        box-shadow: none;
    }

    .order-top-sticky .alert {
        margin-bottom: 10px;
    }

    .order-content-offset {
        position: fixed;
        left: calc(var(--sidebar-width) + 30px);
        right: 30px;
        top: 360px;
        bottom: 20px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    body.modal-open #orderContentOffset {
        overflow: visible;
    }

    /* --- STAT CARDS --- */
    .order-stat-card {
        background: white;
        border-radius: 15px;
        padding: 15px 20px;
        border: 1px solid #eee;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        height: 100%;
    }

    .stat-title {
        font-size: 0.8rem;
        color: #8898aa;
        margin-bottom: 5px;
    }

    .stat-value {
        font-size: 1.6rem;
        font-weight: 700;
        color: #32325d;
        margin-bottom: 2px;
    }

    .stat-desc {
        font-size: 0.7rem;
        font-weight: 600;
    }

    .text-green {
        color: #2dce89;
    }

    .text-orange {
        color: #fb6340;
    }

    .text-blue {
        color: #5e72e4;
    }

    /* --- TABLE --- */
    .table-container {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        margin-top: 2px;
        flex: 1;
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    .table-header {
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
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
        border-bottom: 1px solid #f0f2f5;
    }

    .table tbody tr.order-row {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .table tbody tr.order-row:hover {
        background-color: #f8f9fa;
    }

    .table tbody tr.order-row.selected {
        background-color: #cce3ff;
        font-weight: 500;
        border-left: 4px solid #007bff;
        box-shadow: inset 0 0 8px rgba(0, 123, 255, 0.1);
    }

    .table tbody tr.order-row.selected td:first-child {
        padding-left: 16px;
    }

    .search-input {
        border-radius: 20px;
        padding: 5px 15px;
        border: 1px solid #ddd;
        width: 220px;
        font-size: 0.9rem;
    }

    .order-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .status-pending {
        background: #fff7e6;
        color: #b45309;
        border: 1px solid #fcd34d;
    }

    .status-approved {
        background: #ecfdf3;
        color: #047857;
        border: 1px solid #6ee7b7;
    }

    .status-shipping {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #93c5fd;
    }

    .status-near-delivery {
        background: #eef2ff;
        color: #4338ca;
        border: 1px solid #a5b4fc;
    }

    .status-completed {
        background: #f5f3ff;
        color: #6d28d9;
        border: 1px solid #c4b5fd;
    }

    .status-other {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #d1d5db;
    }

    .status-pending-payment {
        background: #fff7e6;
        color: #9a3412;
        border: 1px solid #fdba74;
    }

    .status-cancelled {
        background: #fff1f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .payment-warning {
        font-size: 0.9rem;
    }

    .order-table-scroll {
        flex: 1 1 auto;
        max-height: 100%;
        overflow: auto;
        min-height: 220px;
    }

    @media (max-width: 768px) {
        .order-top-sticky {
            padding: 20px 16px 10px;
        }

        .order-content-offset {
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
            <a href="admin-donhang.php" class="nav-item active"><i class="fas fa-shopping-cart"></i> Đơn hàng</a>
            <a href="admin-nhanvien.php" class="nav-item"><i class="fas fa-user-tie"></i> Nhân viên</a>
            <a href="admin-khachhang.php" class="nav-item"><i class="fas fa-users"></i> Khách hàng</a>
            <a href="admin-voucher.php" class="nav-item"><i class="fas fa-ticket-alt"></i> Voucher</a>
            <a href="admin-caidat.php" class="nav-item"><i class="fas fa-cog"></i> Cài đặt</a>
        </nav>
        <a href="../Login/logout.php" class="logout-btn">Đăng xuất</a>
    </div>

    <div class="main-content">
        <div class="order-top-sticky">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-primary">Bảng điều khiển Admin</h4>
                <button class="btn btn-light rounded-circle border shadow-sm btn-sm"
                    style="width: 32px; height: 32px;"><i class="fas fa-times"></i></button>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Quản lí đơn hàng</h3>
                <input type="text" class="search-input" placeholder="Tìm kiếm">
            </div>

            <form method="post" id="approveOrderForm" class="d-none">
                <input type="hidden" name="crud_action" value="approve_order">
                <input type="hidden" name="order_id" id="approveOrderId" value="">
            </form>

            <form method="post" id="updateStatusForm" class="d-none">
                <input type="hidden" name="crud_action" value="update_order_status">
                <input type="hidden" name="order_id" id="updateStatusOrderId" value="">
                <input type="hidden" name="next_status" id="updateStatusValue" value="">
            </form>

            <form method="post" id="deleteOrderForm" class="d-none">
                <input type="hidden" name="crud_action" value="delete_order">
                <input type="hidden" name="order_id" id="deleteOrderId" value="">
            </form>

            <form method="post" id="cancelOrderForm" class="d-none">
                <input type="hidden" name="crud_action" value="cancel_order">
                <input type="hidden" name="order_id" id="cancelOrderId" value="">
            </form>

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

            <?php if ($autoCleanupNotice !== ''): ?>
            <div class="alert alert-info" role="alert">
                <?php echo htmlspecialchars($autoCleanupNotice); ?>
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="order-stat-card">
                        <div class="stat-title">Đơn hàng hôm nay</div>
                        <div class="stat-value"><?php echo $ordersToday; ?></div>
                        <div class="stat-desc text-green">Dữ liệu theo ngày hiện tại</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="order-stat-card">
                        <div class="stat-title">Chờ xử lí</div>
                        <div class="stat-value text-orange"><?php echo $pendingCount; ?></div>
                        <div class="stat-desc fw-bold" style="color:#333">Cần xử lí ngay</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="order-stat-card">
                        <div class="stat-title">Đang giao</div>
                        <div class="stat-value text-blue"><?php echo $shippingCount; ?></div>
                        <div class="stat-desc fw-bold" style="color:#333">Theo dõi</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="order-stat-card">
                        <div class="stat-title">Doanh thu</div>
                        <div class="stat-value text-green"><?php echo number_format($totalRevenue, 0, ',', '.'); ?> ₫
                        </div>
                        <div class="stat-desc text-green">Tổng giá trị đơn hàng</div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mb-3">
                <button type="button" class="btn btn-primary fw-semibold" id="btnViewOrderDetail" disabled>
                    <i class="fas fa-eye me-1"></i> Chi tiết
                </button>
                <button type="button" class="btn btn-add fw-semibold" id="btnApproveOrder" disabled>
                    <i class="fas fa-check-circle me-1"></i> Duyệt đơn
                </button>
                <button type="button" class="btn btn-info fw-semibold text-white" id="btnShipOrder" disabled>
                    <i class="fas fa-truck me-1"></i> Đang giao
                </button>
                <button type="button" class="btn btn-secondary fw-semibold" id="btnNearDeliveryOrder" disabled>
                    <i class="fas fa-location-dot me-1"></i> Gần giao
                </button>
                <button type="button" class="btn btn-warning fw-semibold text-dark" id="btnCancelOrder" disabled>
                    <i class="fas fa-ban me-1"></i> Hủy đơn
                </button>
                <button type="button" class="btn btn-danger fw-semibold" id="btnDeleteOrder" disabled>
                    <i class="fas fa-trash-alt me-1"></i> Xóa đơn
                </button>
            </div>

            <div class="alert alert-warning payment-warning d-none" id="orderPaymentHint" role="alert"></div>
        </div>

        <div id="orderContentOffset" class="order-content-offset">

            <div class="table-container">
                <div class="table-header">
                    <h5 class="fw-bold mb-0">Danh sách đơn hàng</h5>
                    <i class="fas fa-download text-muted"></i>
                </div>
                <div class="order-table-scroll">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Tổng tiền</th>
                                <th>Thanh toán</th>
                                <th>Trạng thái</th>
                                <th>Ngày đặt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($orders) === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Chưa có đơn hàng nào.</td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach($orders as $o): ?>
                            <tr class="order-row"
                                data-id="<?php echo htmlspecialchars((string) $o['id'], ENT_QUOTES); ?>"
                                data-customer="<?php echo htmlspecialchars((string) $o['customer'], ENT_QUOTES); ?>"
                                data-total="<?php echo htmlspecialchars((string) $o['total'], ENT_QUOTES); ?>"
                                data-status="<?php echo htmlspecialchars((string) $o['status'], ENT_QUOTES); ?>"
                                data-status-key="<?php echo htmlspecialchars((string) $o['status_key'], ENT_QUOTES); ?>"
                                data-payment-method="<?php echo htmlspecialchars((string) ($o['payment_method'] ?? ''), ENT_QUOTES); ?>"
                                data-payment-status="<?php echo htmlspecialchars((string) ($o['payment_status'] ?? ''), ENT_QUOTES); ?>"
                                data-payment-ready="<?php echo !empty($o['payment_ready']) ? '1' : '0'; ?>"
                                data-date="<?php echo htmlspecialchars((string) $o['date'], ENT_QUOTES); ?>"
                                data-customer-id="<?php echo htmlspecialchars((string) $o['ma_kh'], ENT_QUOTES); ?>">
                                <td class="fw-bold"><?php echo htmlspecialchars((string) $o['id']); ?></td>
                                <td><?php echo htmlspecialchars((string) $o['customer']); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars((string) $o['total']); ?></td>
                                <td>
                                    <div class="small fw-semibold">
                                        <?php echo htmlspecialchars((string) (($o['payment_method'] ?? '') !== '' ? $o['payment_method'] : 'QR chuyển khoản')); ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php echo htmlspecialchars((string) (($o['payment_status'] ?? '') !== '' ? $o['payment_status'] : 'Đang cập nhật thanh toán')); ?>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="order-status-badge <?php echo htmlspecialchars((string) $o['status_class']); ?>">
                                        <?php echo htmlspecialchars((string) $o['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars((string) $o['date']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="orderDetailModalLabel">Chi tiết đơn hàng</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="border rounded-3 p-2 bg-light-subtle"><strong>Mã đơn:</strong> <span
                                        id="detailOrderId">--</span></div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-2 bg-light-subtle"><strong>Khách hàng:</strong> <span
                                        id="detailOrderCustomer">--</span></div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded-3 p-2 bg-light-subtle"><strong>Tổng tiền:</strong> <span
                                        id="detailOrderTotal">0 ₫</span></div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded-3 p-2 bg-light-subtle"><strong>Trạng thái:</strong> <span
                                        id="detailOrderStatus">--</span></div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded-3 p-2 bg-light-subtle"><strong>Ngày đặt:</strong> <span
                                        id="detailOrderDate">--</span></div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-2 bg-light-subtle"><strong>Phương thức thanh
                                        toán:</strong> <span id="detailOrderPaymentMethod">--</span></div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-2 bg-light-subtle"><strong>Trạng thái thanh
                                        toán:</strong> <span id="detailOrderPaymentStatus">--</span></div>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-2">Danh sách sản phẩm</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Mã hàng</th>
                                        <th>Sản phẩm</th>
                                        <th class="text-end">Số lượng</th>
                                        <th class="text-end">Đơn giá</th>
                                        <th class="text-end">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody id="detailOrderItemsBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="orderConfirmModal" tabindex="-1" aria-labelledby="orderConfirmModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="orderConfirmModalLabel">Xác nhận thao tác</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="orderConfirmMessage">
                        Bạn có chắc chắn muốn thực hiện thao tác này không?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="button" class="btn btn-danger" id="btnConfirmOrderAction">Xác nhận</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const orderDetailMap = <?php echo json_encode($orderDetailMap, JSON_UNESCAPED_UNICODE); ?>;
    const btnViewOrderDetail = document.getElementById('btnViewOrderDetail');
    const btnApproveOrder = document.getElementById('btnApproveOrder');
    const btnShipOrder = document.getElementById('btnShipOrder');
    const btnNearDeliveryOrder = document.getElementById('btnNearDeliveryOrder');
    const updateStatusForm = document.getElementById('updateStatusForm');
    const updateStatusOrderIdInput = document.getElementById('updateStatusOrderId');
    const updateStatusValueInput = document.getElementById('updateStatusValue');
    const approveOrderForm = document.getElementById('approveOrderForm');
    const approveOrderIdInput = document.getElementById('approveOrderId');
    const btnDeleteOrder = document.getElementById('btnDeleteOrder');
    const btnCancelOrder = document.getElementById('btnCancelOrder');
    const orderPaymentHint = document.getElementById('orderPaymentHint');
    const deleteOrderForm = document.getElementById('deleteOrderForm');
    const deleteOrderIdInput = document.getElementById('deleteOrderId');
    const cancelOrderForm = document.getElementById('cancelOrderForm');
    const cancelOrderIdInput = document.getElementById('cancelOrderId');
    const orderConfirmModalEl = document.getElementById('orderConfirmModal');
    const orderConfirmModalLabel = document.getElementById('orderConfirmModalLabel');
    const orderConfirmMessage = document.getElementById('orderConfirmMessage');
    const btnConfirmOrderAction = document.getElementById('btnConfirmOrderAction');
    let selectedOrderRow = null;
    let pendingOrderAction = null;

    function ensureOrderToastStack() {
        let stack = document.getElementById('ack-admin-toast-stack');
        if (stack) {
            return stack;
        }

        stack = document.createElement('div');
        stack.id = 'ack-admin-toast-stack';
        document.body.appendChild(stack);
        return stack;
    }

    function showOrderInlineHintAsToast(message, type = 'warning') {
        const text = (message || '').toString().trim();
        if (text === '') {
            return;
        }

        const normalizedType = ['success', 'danger', 'warning', 'info'].includes(type) ? type : 'warning';
        const stack = ensureOrderToastStack();
        const toast = document.createElement('div');

        toast.className = `alert alert-${normalizedType} ack-admin-toast`;
        toast.setAttribute('role', 'alert');
        toast.textContent = text;

        stack.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 260);
        }, 3200);
    }

    function openOrderConfirmModal(actionType, orderId) {
        if (!orderConfirmModalEl || !orderConfirmModalLabel || !orderConfirmMessage || !btnConfirmOrderAction) {
            return;
        }

        pendingOrderAction = {
            type: actionType,
            orderId,
        };

        btnConfirmOrderAction.classList.remove('btn-danger', 'btn-success', 'btn-primary');

        if (actionType === 'approve') {
            orderConfirmModalLabel.textContent = 'Xác nhận duyệt';
            orderConfirmMessage.textContent = `Bạn có chắc chắn muốn duyệt đơn hàng ${orderId} không?`;
            btnConfirmOrderAction.textContent = 'Duyệt';
            btnConfirmOrderAction.classList.add('btn-success');
        } else if (actionType === 'ship') {
            orderConfirmModalLabel.textContent = 'Xác nhận giao hàng';
            orderConfirmMessage.textContent = `Cập nhật đơn hàng ${orderId} sang trạng thái "Đang giao"?`;
            btnConfirmOrderAction.textContent = 'Cập nhật';
            btnConfirmOrderAction.classList.add('btn-primary');
        } else if (actionType === 'near_delivery') {
            orderConfirmModalLabel.textContent = 'Xác nhận gần giao';
            orderConfirmMessage.textContent = `Cập nhật đơn hàng ${orderId} sang trạng thái "Gần giao"?`;
            btnConfirmOrderAction.textContent = 'Cập nhật';
            btnConfirmOrderAction.classList.add('btn-primary');
        } else if (actionType === 'cancel') {
            orderConfirmModalLabel.textContent = 'Xác nhận hủy đơn';
            orderConfirmMessage.textContent = `Bạn có chắc chắn muốn hủy đơn hàng ${orderId} không? Hệ thống sẽ hoàn tồn kho và hoàn voucher (nếu có).`;
            btnConfirmOrderAction.textContent = 'Hủy đơn';
            btnConfirmOrderAction.classList.add('btn-danger');
        } else {
            orderConfirmModalLabel.textContent = 'Xác nhận xóa';
            orderConfirmMessage.textContent = `Bạn có chắc chắn muốn xóa thủ công đơn hàng ${orderId} không?`;
            btnConfirmOrderAction.textContent = 'Xóa';
            btnConfirmOrderAction.classList.add('btn-danger');
        }

        bootstrap.Modal.getOrCreateInstance(orderConfirmModalEl).show();
    }

    function openOrderDetail(row) {
        const orderId = row.getAttribute('data-id') || '';
        const customer = row.getAttribute('data-customer') || '';
        const total = row.getAttribute('data-total') || '0 ₫';
        const status = row.getAttribute('data-status') || '--';
        const date = row.getAttribute('data-date') || '--';
        const paymentMethod = row.getAttribute('data-payment-method') || '--';
        const paymentStatus = row.getAttribute('data-payment-status') || '--';

        document.getElementById('detailOrderId').textContent = orderId || '--';
        document.getElementById('detailOrderCustomer').textContent = customer || '--';
        document.getElementById('detailOrderTotal').textContent = total;
        document.getElementById('detailOrderStatus').textContent = status;
        document.getElementById('detailOrderDate').textContent = date;
        document.getElementById('detailOrderPaymentMethod').textContent = paymentMethod;
        document.getElementById('detailOrderPaymentStatus').textContent = paymentStatus;

        const itemsBody = document.getElementById('detailOrderItemsBody');
        itemsBody.innerHTML = '';

        const items = Array.isArray(orderDetailMap[orderId]) ? orderDetailMap[orderId] : [];
        if (items.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 6;
            td.className = 'text-center text-muted py-3';
            td.textContent = 'Không có dữ liệu chi tiết cho đơn này.';
            tr.appendChild(td);
            itemsBody.appendChild(tr);
        } else {
            let totalQuantity = 0;
            let grandTotal = 0;

            items.forEach((item, index) => {
                const tr = document.createElement('tr');

                const tdIndex = document.createElement('td');
                tdIndex.textContent = String(index + 1);
                tr.appendChild(tdIndex);

                const tdCode = document.createElement('td');
                tdCode.textContent = item.code || '--';
                tr.appendChild(tdCode);

                const tdProduct = document.createElement('td');
                tdProduct.textContent = item.product || 'Sản phẩm';
                tr.appendChild(tdProduct);

                const tdQty = document.createElement('td');
                tdQty.className = 'text-end';
                tdQty.textContent = String(item.quantity ?? 0);
                tr.appendChild(tdQty);

                const tdPrice = document.createElement('td');
                tdPrice.className = 'text-end';
                tdPrice.textContent = item.price || '0 ₫';
                tr.appendChild(tdPrice);

                const tdTotal = document.createElement('td');
                tdTotal.className = 'text-end fw-semibold';
                tdTotal.textContent = item.total || '0 ₫';
                tr.appendChild(tdTotal);

                itemsBody.appendChild(tr);

                totalQuantity += Number(item.quantity_number ?? item.quantity ?? 0);
                grandTotal += Number(item.total_number ?? 0);
            });

            const trSummary = document.createElement('tr');
            trSummary.className = 'table-light';

            const tdSummaryLabel = document.createElement('td');
            tdSummaryLabel.colSpan = 3;
            tdSummaryLabel.className = 'fw-bold text-end';
            tdSummaryLabel.textContent = 'Tổng cộng';
            trSummary.appendChild(tdSummaryLabel);

            const tdSummaryQty = document.createElement('td');
            tdSummaryQty.className = 'text-end fw-bold';
            tdSummaryQty.textContent = String(totalQuantity);
            trSummary.appendChild(tdSummaryQty);

            const tdSummaryBlank = document.createElement('td');
            tdSummaryBlank.textContent = '';
            trSummary.appendChild(tdSummaryBlank);

            const tdSummaryTotal = document.createElement('td');
            tdSummaryTotal.className = 'text-end fw-bold';
            tdSummaryTotal.textContent = grandTotal > 0 ? `${grandTotal.toLocaleString('vi-VN')} ₫` : total;
            trSummary.appendChild(tdSummaryTotal);

            itemsBody.appendChild(trSummary);
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('orderDetailModal')).show();
    }

    document.querySelectorAll('tr.order-row').forEach((row) => {
        row.addEventListener('click', function() {
            document.querySelectorAll('tr.order-row').forEach(r => r.classList.remove('selected'));
            this.classList.add('selected');
            selectedOrderRow = this;
            btnViewOrderDetail.disabled = false;
            const statusKey = this.getAttribute('data-status-key') || '';
            const paymentReady = this.getAttribute('data-payment-ready') === '1';
            btnApproveOrder.disabled = !(statusKey === 'pending' && paymentReady);
            btnShipOrder.disabled = !(statusKey === 'approved' && paymentReady);
            btnNearDeliveryOrder.disabled = !(statusKey === 'shipping' && paymentReady);
            btnCancelOrder.disabled = !['pending', 'pending_payment', 'approved'].includes(statusKey);
            btnDeleteOrder.disabled = false;

            if (orderPaymentHint) {
                if (!paymentReady) {
                    const orderId = this.getAttribute('data-id') || '';
                    const hintText =
                        `Đơn ${orderId} chưa được khách xác nhận chuyển khoản QR. Chưa thể duyệt hoặc chuyển sang giao.`;
                    orderPaymentHint.classList.add('d-none');
                    orderPaymentHint.textContent = hintText;
                    showOrderInlineHintAsToast(hintText, 'warning');
                } else {
                    orderPaymentHint.classList.add('d-none');
                    orderPaymentHint.textContent = '';
                }
            }
        });

        row.addEventListener('dblclick', function() {
            openOrderDetail(this);
        });
    });

    btnViewOrderDetail.addEventListener('click', function() {
        if (!selectedOrderRow) {
            return;
        }
        openOrderDetail(selectedOrderRow);
    });

    btnApproveOrder.addEventListener('click', function() {
        if (!selectedOrderRow || !approveOrderForm || !approveOrderIdInput) {
            return;
        }

        const statusKey = selectedOrderRow.getAttribute('data-status-key') || '';
        if (statusKey !== 'pending') {
            return;
        }

        const orderId = selectedOrderRow.getAttribute('data-id') || '';
        if (!orderId) {
            return;
        }

        openOrderConfirmModal('approve', orderId);
    });

    btnDeleteOrder.addEventListener('click', function() {
        if (!selectedOrderRow || !deleteOrderForm || !deleteOrderIdInput) {
            return;
        }

        const orderId = selectedOrderRow.getAttribute('data-id') || '';
        if (!orderId) {
            return;
        }

        openOrderConfirmModal('delete', orderId);
    });

    btnCancelOrder.addEventListener('click', function() {
        if (!selectedOrderRow || !cancelOrderForm || !cancelOrderIdInput) {
            return;
        }

        const statusKey = selectedOrderRow.getAttribute('data-status-key') || '';
        if (!['pending', 'pending_payment', 'approved'].includes(statusKey)) {
            return;
        }

        const orderId = selectedOrderRow.getAttribute('data-id') || '';
        if (!orderId) {
            return;
        }

        openOrderConfirmModal('cancel', orderId);
    });

    btnShipOrder.addEventListener('click', function() {
        if (!selectedOrderRow) {
            return;
        }

        const statusKey = selectedOrderRow.getAttribute('data-status-key') || '';
        if (statusKey !== 'approved') {
            return;
        }

        const orderId = selectedOrderRow.getAttribute('data-id') || '';
        if (!orderId) {
            return;
        }

        openOrderConfirmModal('ship', orderId);
    });

    btnNearDeliveryOrder.addEventListener('click', function() {
        if (!selectedOrderRow) {
            return;
        }

        const statusKey = selectedOrderRow.getAttribute('data-status-key') || '';
        if (statusKey !== 'shipping') {
            return;
        }

        const orderId = selectedOrderRow.getAttribute('data-id') || '';
        if (!orderId) {
            return;
        }

        openOrderConfirmModal('near_delivery', orderId);
    });

    btnConfirmOrderAction.addEventListener('click', function() {
        if (!pendingOrderAction) {
            return;
        }

        const {
            type,
            orderId
        } = pendingOrderAction;

        if (type === 'approve') {
            if (!approveOrderForm || !approveOrderIdInput || !orderId) {
                return;
            }
            approveOrderIdInput.value = orderId;
            approveOrderForm.submit();
            return;
        }

        if (type === 'ship' || type === 'near_delivery') {
            if (!updateStatusForm || !updateStatusOrderIdInput || !updateStatusValueInput || !orderId) {
                return;
            }

            updateStatusOrderIdInput.value = orderId;
            updateStatusValueInput.value = type === 'ship' ? 'Đang giao' : 'Gần giao';
            updateStatusForm.submit();
            return;
        }

        if (type === 'delete') {
            if (!deleteOrderForm || !deleteOrderIdInput || !orderId) {
                return;
            }
            deleteOrderIdInput.value = orderId;
            deleteOrderForm.submit();
            return;
        }

        if (type === 'cancel') {
            if (!cancelOrderForm || !cancelOrderIdInput || !orderId) {
                return;
            }
            cancelOrderIdInput.value = orderId;
            cancelOrderForm.submit();
        }
    });

    orderConfirmModalEl.addEventListener('hidden.bs.modal', function() {
        pendingOrderAction = null;
        btnConfirmOrderAction.classList.remove('btn-danger', 'btn-success', 'btn-primary');
        btnConfirmOrderAction.classList.add('btn-danger');
        btnConfirmOrderAction.textContent = 'Xác nhận';
        orderConfirmModalLabel.textContent = 'Xác nhận thao tác';
        orderConfirmMessage.textContent = 'Bạn có chắc chắn muốn thực hiện thao tác này không?';
    });

    function syncOrderFixedTopOffset() {
        const fixedTop = document.querySelector('.order-top-sticky');
        const contentOffset = document.getElementById('orderContentOffset');
        if (!fixedTop || !contentOffset) {
            return;
        }

        const topHeight = Math.ceil(fixedTop.getBoundingClientRect().height);
        contentOffset.style.top = `${topHeight + 10}px`;
    }

    window.addEventListener('resize', syncOrderFixedTopOffset);
    window.addEventListener('load', syncOrderFixedTopOffset);
    syncOrderFixedTopOffset();
    </script>
    <script src="admin-search.js?v=20260414-2"></script>
</body>

</html>