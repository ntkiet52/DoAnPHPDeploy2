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

function upsertOrderPaymentMeta(PDO $pdo, string $orderId, string $method, string $status): void {
    if (trim($orderId) === '') {
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO phieuxuat_thanhtoan_map (ma_don_hang, phuong_thuc_thanh_toan, trang_thai_thanh_toan)
         VALUES (:order_id, :method, :status)
         ON DUPLICATE KEY UPDATE
            phuong_thuc_thanh_toan = VALUES(phuong_thuc_thanh_toan),
            trang_thai_thanh_toan = VALUES(trang_thai_thanh_toan)"
    );
    $stmt->execute([
        ':order_id' => $orderId,
        ':method' => $method,
        ':status' => $status,
    ]);
}

function normalizeOrderStatus(string $rawStatus): array {
    $status = mb_strtolower(trim($rawStatus));

    if (str_contains($status, 'hủy') || str_contains($status, 'huy') || str_contains($status, 'cancel')) {
        return ['key' => 'cancelled', 'label' => 'Đã hủy', 'class' => 'status-cancelled', 'progress' => 'Đơn đã được bạn hủy trước khi duyệt.'];
    }

    if (str_contains($status, 'chờ thanh toán') || str_contains($status, 'cho thanh toan') || str_contains($status, 'qr')) {
        return ['key' => 'pending_payment', 'label' => 'Chờ thanh toán QR', 'class' => 'status-waiting-payment', 'progress' => 'Vui lòng chuyển khoản để shop duyệt và giao hàng.'];
    }

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
$orderStats = [
    'total' => 0,
    'pending' => 0,
    'shipping' => 0,
    'completed' => 0,
];

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

    // Fallback theo session để không bỏ sót đơn hàng khi dữ liệu khách hàng chưa đồng bộ đủ cột
    $sessionCustomerCode = trim((string) ($_SESSION['ma_khach_hang'] ?? ''));
    if ($sessionCustomerCode !== '') {
        $customerIds[$sessionCustomerCode] = true;
    }

    $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
    if ($sessionUserId > 0) {
        $customerIds['KH' . str_pad((string) $sessionUserId, 3, '0', STR_PAD_LEFT)] = true;
    }

    $customerIds = array_keys($customerIds);

    $pxColumns = getExistingColumns($pdo, 'phieuxuat');
    $ctpxColumns = getExistingColumns($pdo, 'chitietphieuxuat');

    $orderIdCol = pickExistingColumn($pxColumns, ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id']);
    $orderCustomerCol = pickExistingColumn($pxColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'idkhachhang']);
    $orderDateCol = pickExistingColumn($pxColumns, ['ngayxuat', 'ngay_xuat', 'ngaydat', 'ngay_dat', 'ngaylap']);
    $orderStatusCol = pickExistingColumn($pxColumns, ['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu']);
    $orderTotalCol = pickExistingColumn($pxColumns, ['tongtien', 'tong_tien', 'thanhtien', 'thanh_tien', 'total']);
    $orderPaymentMethodCol = pickExistingColumn($pxColumns, ['hinhthucthanhtoan', 'hinh_thuc_thanh_toan', 'phuongthucthanhtoan', 'phuong_thuc_thanh_toan', 'ptthanhtoan', 'payment_method', 'thanhtoan']);
    $orderPaymentStatusCol = pickExistingColumn($pxColumns, ['trangthaithanhtoan', 'trang_thai_thanh_toan', 'ttthanhtoan', 'payment_status']);
    $orderCancelReasonCol = pickExistingColumn($pxColumns, ['lydohuy', 'ly_do_huy', 'ghichu', 'ghi_chu', 'ghichupx', 'ghi_chu_px', 'note', 'mo_ta']);

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_qr_paid') {
        $confirmOrderId = trim((string) ($_POST['order_id'] ?? ''));
        if ($confirmOrderId === '') {
            $flashMessage = 'Không xác định được đơn hàng để xác nhận chuyển khoản.';
            $flashType = 'danger';
        } else if ($orderIdCol !== null && $orderCustomerCol !== null && count($customerIds) > 0 && $orderPaymentStatusCol !== null) {
            $placeholders = implode(', ', array_fill(0, count($customerIds), '?'));

            $setSql = "`{$orderPaymentStatusCol}` = ?";
            $params = ['Đã chuyển khoản QR'];

            if ($orderStatusCol !== null) {
                $setSql .= ", `{$orderStatusCol}` = ?";
                $params[] = 'Chờ duyệt';
            }

            $params[] = $confirmOrderId;
            $params = array_merge($params, $customerIds);

            $sqlConfirmQr = "UPDATE phieuxuat
                             SET {$setSql}
                             WHERE `{$orderIdCol}` = ?
                             AND `{$orderCustomerCol}` IN ({$placeholders})";

            $confirmQrStmt = $pdo->prepare($sqlConfirmQr);
            $confirmQrStmt->execute($params);

            if ($confirmQrStmt->rowCount() > 0) {
                upsertOrderPaymentMeta($pdo, $confirmOrderId, 'QR chuyển khoản', 'Đã chuyển khoản QR');
                $flashMessage = 'Đã xác nhận chuyển khoản đơn ' . $confirmOrderId . '. Shop sẽ duyệt để chuyển sang giao hàng.';
                $flashType = 'success';
            } else {
                $flashMessage = 'Không thể cập nhật trạng thái thanh toán. Vui lòng thử lại.';
                $flashType = 'warning';
            }
        } else if ($orderIdCol !== null && $orderCustomerCol !== null && $orderStatusCol !== null && count($customerIds) > 0) {
            $placeholders = implode(', ', array_fill(0, count($customerIds), '?'));
            $params = [$confirmOrderId];
            $params = array_merge($params, $customerIds);

            $sqlFallback = "UPDATE phieuxuat
                            SET `{$orderStatusCol}` = 'Chờ duyệt'
                            WHERE `{$orderIdCol}` = ?
                            AND `{$orderCustomerCol}` IN ({$placeholders})";

            $confirmFallbackStmt = $pdo->prepare($sqlFallback);
            $confirmFallbackStmt->execute($params);

            if ($confirmFallbackStmt->rowCount() > 0) {
                upsertOrderPaymentMeta($pdo, $confirmOrderId, 'QR chuyển khoản', 'Đã chuyển khoản QR');
                $flashMessage = 'Đã xác nhận chuyển khoản đơn ' . $confirmOrderId . '. Shop sẽ duyệt để chuyển sang giao hàng.';
                $flashType = 'success';
            } else {
                $flashMessage = 'Không thể cập nhật đơn hàng. Vui lòng thử lại.';
                $flashType = 'warning';
            }
        } else {
            $flashMessage = 'Không thể xác nhận chuyển khoản với cấu trúc dữ liệu hiện tại.';
            $flashType = 'danger';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_order') {
        $cancelOrderId = trim((string) ($_POST['order_id'] ?? ''));
        $cancelReason = trim((string) ($_POST['cancel_reason'] ?? ''));
        $cancelNoteExtra = trim((string) ($_POST['cancel_note'] ?? ''));

        if ($cancelOrderId === '') {
            $flashMessage = 'Không xác định được đơn hàng để hủy.';
            $flashType = 'danger';
        } else if ($cancelReason === '') {
            $flashMessage = 'Vui lòng chọn lý do hủy đơn.';
            $flashType = 'warning';
        } else if ($orderIdCol !== null && $orderCustomerCol !== null && $orderStatusCol !== null && count($customerIds) > 0) {
            $placeholders = implode(', ', array_fill(0, count($customerIds), '?'));
            $selectParams = array_merge([$cancelOrderId], $customerIds);

            $sqlCheckCancelable = "SELECT `{$orderStatusCol}` AS current_status
                                 FROM phieuxuat
                                 WHERE `{$orderIdCol}` = ?
                                 AND `{$orderCustomerCol}` IN ({$placeholders})
                                 LIMIT 1";
            $checkStmt = $pdo->prepare($sqlCheckCancelable);
            $checkStmt->execute($selectParams);
            $checkRow = $checkStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!is_array($checkRow)) {
                $flashMessage = 'Không tìm thấy đơn hàng cần hủy.';
                $flashType = 'danger';
            } else {
                $statusMeta = normalizeOrderStatus((string) ($checkRow['current_status'] ?? ''));
                $statusKey = (string) ($statusMeta['key'] ?? 'other');

                if (!in_array($statusKey, ['pending', 'pending_payment'], true)) {
                    $flashMessage = 'Đơn đã được duyệt hoặc đang giao, không thể hủy từ phía khách hàng.';
                    $flashType = 'warning';
                } else {
                    $reasonText = 'Khách hủy: ' . $cancelReason;
                    if ($cancelNoteExtra !== '') {
                        $reasonText .= ' | Ghi chú: ' . mb_substr($cancelNoteExtra, 0, 250);
                    }

                    $setParts = ["`{$orderStatusCol}` = ?"];
                    $updateParams = ['Đã hủy'];

                    if ($orderPaymentStatusCol !== null) {
                        $setParts[] = "`{$orderPaymentStatusCol}` = ?";
                        $updateParams[] = 'Đã hủy';
                    }

                    if ($orderCancelReasonCol !== null) {
                        $setParts[] = "`{$orderCancelReasonCol}` = ?";
                        $updateParams[] = $reasonText;
                    }

                    $updateParams[] = $cancelOrderId;
                    $updateParams = array_merge($updateParams, $customerIds);

                    $sqlCancel = "UPDATE phieuxuat
                                SET " . implode(', ', $setParts) . "
                                WHERE `{$orderIdCol}` = ?
                                AND `{$orderCustomerCol}` IN ({$placeholders})";

                    $cancelStmt = $pdo->prepare($sqlCancel);
                    $cancelStmt->execute($updateParams);

                    if ($cancelStmt->rowCount() > 0) {
                        $cancelMethod = $statusKey === 'pending_payment' ? 'QR chuyển khoản' : 'Thanh toán khi nhận hàng';
                        upsertOrderPaymentMeta($pdo, $cancelOrderId, $cancelMethod, 'Đã hủy');
                        $flashMessage = 'Đã hủy đơn ' . $cancelOrderId . ' thành công.';
                        $flashType = 'success';
                    } else {
                        $flashMessage = 'Không thể hủy đơn. Vui lòng thử lại.';
                        $flashType = 'warning';
                    }
                }
            }
        } else {
            $flashMessage = 'Không thể hủy đơn với cấu trúc dữ liệu hiện tại.';
            $flashType = 'danger';
        }
    }

    if ($orderIdCol !== null && $orderCustomerCol !== null && count($customerIds) > 0) {
        $placeholders = implode(', ', array_fill(0, count($customerIds), '?'));
        $orderOrderBy = $orderDateCol !== null ? "`{$orderDateCol}` DESC" : "`{$orderIdCol}` DESC";
        $orderStmt = $pdo->prepare("SELECT * FROM phieuxuat WHERE `{$orderCustomerCol}` IN ({$placeholders}) ORDER BY {$orderOrderBy}");
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

        $paymentMetaMap = [];
        if (count($orderIdList) > 0) {
            $metaPlaceholders = implode(', ', array_fill(0, count($orderIdList), '?'));
            $metaStmt = $pdo->prepare("SELECT ma_don_hang, phuong_thuc_thanh_toan, trang_thai_thanh_toan FROM phieuxuat_thanhtoan_map WHERE ma_don_hang IN ({$metaPlaceholders})");
            $metaStmt->execute($orderIdList);
            foreach ($metaStmt->fetchAll(PDO::FETCH_ASSOC) as $metaRow) {
                $metaOrderId = trim((string) ($metaRow['ma_don_hang'] ?? ''));
                if ($metaOrderId === '') {
                    continue;
                }
                $paymentMetaMap[$metaOrderId] = [
                    'method' => trim((string) ($metaRow['phuong_thuc_thanh_toan'] ?? '')),
                    'status' => trim((string) ($metaRow['trang_thai_thanh_toan'] ?? '')),
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
            $dateTimestamp = 0;
            if ($rawDate !== '') {
                $timestamp = strtotime($rawDate);
                if ($timestamp !== false) {
                    $displayDate = date('d/m/Y H:i', $timestamp);
                    $dateTimestamp = (int) $timestamp;
                }
            }

            $rawStatus = $orderStatusCol !== null ? (string) pickOrderValue($orderRow, [$orderStatusCol], '') : '';
            $statusMeta = normalizeOrderStatus($rawStatus);

            $metaPaymentMethod = trim((string) (($paymentMetaMap[$orderId]['method'] ?? '')));
            $metaPaymentStatus = trim((string) (($paymentMetaMap[$orderId]['status'] ?? '')));

            $rawPaymentMethod = $orderPaymentMethodCol !== null ? trim((string) pickOrderValue($orderRow, [$orderPaymentMethodCol], '')) : '';
            if ($rawPaymentMethod === '' && $metaPaymentMethod !== '') {
                $rawPaymentMethod = $metaPaymentMethod;
            }

            $rawPaymentStatus = $orderPaymentStatusCol !== null ? trim((string) pickOrderValue($orderRow, [$orderPaymentStatusCol], '')) : '';
            if ($rawPaymentStatus === '' && $metaPaymentStatus !== '') {
                $rawPaymentStatus = $metaPaymentStatus;
            }
            $paymentMethodLower = mb_strtolower($rawPaymentMethod);
            $paymentStatusLower = mb_strtolower($rawPaymentStatus);

            $isQrOrder = str_contains($paymentMethodLower, 'qr') || str_contains($paymentMethodLower, 'chuyển khoản') || str_contains($paymentMethodLower, 'chuyen khoan');
            if (!$isQrOrder && (string) ($statusMeta['key'] ?? '') === 'pending_payment') {
                $isQrOrder = true;
            }

            if ($rawPaymentMethod === '') {
                $rawPaymentMethod = $isQrOrder ? 'QR chuyển khoản' : 'Thanh toán khi nhận hàng';
                $paymentMethodLower = mb_strtolower($rawPaymentMethod);
            }
            if ($rawPaymentStatus === '') {
                $rawPaymentStatus = $isQrOrder ? 'Chờ khách chuyển khoản' : 'Thanh toán khi nhận hàng';
                $paymentStatusLower = mb_strtolower($rawPaymentStatus);
            }
            $canConfirmQrPaid = $isQrOrder && (
                $rawPaymentStatus === ''
                || str_contains($paymentStatusLower, 'chờ')
                || str_contains($paymentStatusLower, 'cho')
                || str_contains($paymentStatusLower, 'chưa')
                || str_contains($paymentStatusLower, 'chua')
            );

            if ($isQrOrder && $canConfirmQrPaid) {
                $statusMeta = ['key' => 'pending_payment', 'label' => 'Chờ thanh toán QR', 'class' => 'status-waiting-payment', 'progress' => 'Bạn cần xác nhận đã chuyển khoản để shop duyệt giao hàng.'];
            }

            $total = $orderTotalCol !== null ? (float) pickOrderValue($orderRow, [$orderTotalCol], 0) : 0;
            if ($total <= 0 && isset($orderDetailMap[$orderId])) {
                foreach ($orderDetailMap[$orderId] as $detailItem) {
                    $total += (float) ($detailItem['line_total'] ?? 0);
                }
            }

            $orders[] = [
                'id' => $orderId,
                'date' => $displayDate,
                'date_ts' => $dateTimestamp,
                'status_key' => $statusMeta['key'] ?? 'other',
                'status_label' => $statusMeta['label'],
                'status_class' => $statusMeta['class'],
                'shipping_progress' => $statusMeta['progress'] ?? '',
                'total' => $total,
                'payment_method' => $rawPaymentMethod,
                'payment_status' => $rawPaymentStatus,
                'is_qr_order' => $isQrOrder,
                'can_confirm_qr_paid' => $canConfirmQrPaid,
                'details' => $orderDetailMap[$orderId] ?? [],
            ];
        }

        usort($orders, static function (array $a, array $b): int {
            return (int) ($b['date_ts'] ?? 0) <=> (int) ($a['date_ts'] ?? 0);
        });

        foreach ($orders as $order) {
            $orderStats['total']++;
            $statusKey = (string) ($order['status_key'] ?? 'other');

            if ($statusKey === 'pending' || $statusKey === 'approved' || $statusKey === 'pending_payment') {
                $orderStats['pending']++;
            }

            if ($statusKey === 'shipping' || $statusKey === 'near_delivery') {
                $orderStats['shipping']++;
            }

            if ($statusKey === 'completed') {
                $orderStats['completed']++;
            }
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
        :root {
            --bg-main: #f1f4fb;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --brand: #3b82f6;
            --brand-2: #0ea5e9;
            --line: #e2e8f0;
            --panel-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        }

        body {
            background: radial-gradient(circle at 12% 0%, #eef2ff 0%, var(--bg-main) 40%, #eef3fb 100%);
            font-family: 'Segoe UI', sans-serif;
            color: var(--text-main);
        }

        .orders-hero {
            border-radius: 18px;
            background: linear-gradient(130deg, #60a5fa 0%, #38bdf8 50%, #22d3ee 100%);
            color: #fff;
            padding: 22px 22px;
            box-shadow: 0 18px 35px rgba(14, 165, 233, .28);
            margin-bottom: 16px;
        }

        .orders-hero .title {
            font-size: clamp(1.25rem, 2.2vw, 1.9rem);
            font-weight: 800;
            margin: 0;
        }

        .orders-hero .subtitle {
            margin: 6px 0 0;
            color: rgba(255, 255, 255, .82);
            font-size: .95rem;
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, .18);
            border: 1px solid rgba(255, 255, 255, .24);
            color: #fff;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: .82rem;
            font-weight: 600;
        }

        .panel {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 18px;
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(10px);
            box-shadow: var(--panel-shadow);
        }
        .status-badge { padding: 6px 10px; border-radius: 999px; font-size: .8rem; font-weight: 600; display: inline-block; }
        .status-pending { background: #fff3cd; color: #8a6d3b; }
        .status-approved { background: #d1ecf1; color: #0c5460; }
        .status-shipping { background: #e2e3ff; color: #3f51b5; }
        .status-near-delivery { background: #eef2ff; color: #4338ca; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-other { background: #e9ecef; color: #495057; }
        .status-waiting-payment { background: #fef3c7; color: #92400e; }
        .order-detail-row { border-top: 1px dashed #e5e7eb; padding-top: 10px; margin-top: 10px; }

        .order-stat {
            border: 1px solid #e7ebf5;
            border-radius: 14px;
            padding: 14px 14px;
            background: #ffffff;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .order-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(15, 23, 42, .09);
        }

        .order-stat .icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e0f2fe;
            color: #0c4a6e;
            flex-shrink: 0;
        }

        .order-stat .label {
            font-size: .82rem;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .order-stat .value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.1;
        }

        .order-toolbar {
            background: linear-gradient(180deg, #fbfcff 0%, #f6f8ff 100%);
            border: 1px solid #e4e9f5;
            border-radius: 14px;
            padding: 12px;
        }

        .order-toolbar .form-control,
        .order-toolbar .form-select {
            border-radius: 10px;
            min-height: 42px;
            border: 1px solid #dbe3f1;
            box-shadow: none;
        }

        .order-toolbar .form-control:focus,
        .order-toolbar .form-select:focus {
            border-color: #7dd3fc;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, .18);
        }

        .filter-chip-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .filter-chip {
            border: 1px solid #d7dff0;
            color: #334155;
            background: #fff;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: .82rem;
            font-weight: 600;
            transition: all .2s ease;
        }

        .filter-chip.active,
        .filter-chip:hover {
            border-color: #38bdf8;
            color: #0369a1;
            background: #e0f2fe;
        }

        .order-result-hint {
            margin-top: 8px;
            color: #64748b;
            font-size: .82rem;
        }

        .order-empty-filter {
            display: none;
            text-align: center;
            color: #6b7280;
            padding: 18px 8px;
        }

        .table tbody tr.order-row {
            cursor: pointer;
            transition: background-color .18s ease;
        }

        .table thead th {
            background: #f8fafc;
            border-bottom: 1px solid var(--line);
            color: #334155;
            font-size: .86rem;
            text-transform: uppercase;
            letter-spacing: .02em;
            font-weight: 700;
        }

        .table tbody tr.order-row:hover {
            background: #f5f8ff;
        }

        .table tbody tr.order-row.selected {
            background: #ebf1ff;
            box-shadow: inset 4px 0 0 var(--brand);
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

        details > summary {
            list-style: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #0284c7 !important;
            font-weight: 600;
        }

        details > summary::-webkit-details-marker {
            display: none;
        }

        details > summary::before {
            content: "▶";
            font-size: .75rem;
            transition: transform .2s ease;
        }

        details[open] > summary::before {
            transform: rotate(90deg);
        }

        @media (max-width: 768px) {
            .orders-hero {
                padding: 18px 16px;
            }

            .order-toolbar {
                padding: 10px;
            }

            .table thead th,
            .table tbody td {
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4 py-md-5">
        <div class="orders-hero">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h4 class="title">Đơn hàng của tôi</h4>
                    <p class="subtitle mb-0">Theo dõi trạng thái đơn theo thời gian thực, lọc nhanh và tìm kiếm tức thì.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="tai-khoan.php?tab=manage" class="hero-chip text-decoration-none"><i class="fas fa-user"></i>Tài khoản</a>
                    <a href="trangchu.php" class="hero-chip text-decoration-none"><i class="fas fa-house"></i>Trang chủ</a>
                </div>
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
                <div class="row g-2 g-md-3 mb-3">
                    <div class="col-6 col-lg-3">
                        <div class="order-stat">
                            <span class="icon"><i class="fas fa-receipt"></i></span>
                            <div>
                                <div class="label">Tổng đơn</div>
                                <div class="value"><?php echo (int) ($orderStats['total'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="order-stat">
                            <span class="icon" style="background:#fff7e6;color:#d97706;"><i class="fas fa-hourglass-half"></i></span>
                            <div>
                                <div class="label">Đang xử lý</div>
                                <div class="value text-warning"><?php echo (int) ($orderStats['pending'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="order-stat">
                            <span class="icon" style="background:#ecfeff;color:#0891b2;"><i class="fas fa-truck-fast"></i></span>
                            <div>
                                <div class="label">Đang giao</div>
                                <div class="value text-primary"><?php echo (int) ($orderStats['shipping'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="order-stat">
                            <span class="icon" style="background:#ecfdf5;color:#16a34a;"><i class="fas fa-circle-check"></i></span>
                            <div>
                                <div class="label">Hoàn tất</div>
                                <div class="value text-success"><?php echo (int) ($orderStats['completed'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="order-toolbar mb-3">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <input type="text" id="orderSearchInput" class="form-control" placeholder="Tìm mã đơn, ngày đặt, ghi chú vận chuyển...">
                        </div>
                        <div class="col-6 col-md-3">
                            <select id="orderStatusFilter" class="form-select">
                                <option value="all">Tất cả trạng thái</option>
                                <option value="pending">Đang xử lý</option>
                                <option value="shipping">Đang giao</option>
                                <option value="completed">Đã nhận</option>
                                <option value="cancelled">Đã hủy</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <select id="orderSortSelect" class="form-select">
                                <option value="date_desc">Mới nhất</option>
                                <option value="date_asc">Cũ nhất</option>
                                <option value="total_desc">Giá trị cao → thấp</option>
                                <option value="total_asc">Giá trị thấp → cao</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-chip-wrap" id="statusChipWrap">
                        <button type="button" class="filter-chip active" data-status-chip="all">Tất cả</button>
                        <button type="button" class="filter-chip" data-status-chip="pending">Đang xử lý</button>
                        <button type="button" class="filter-chip" data-status-chip="shipping">Đang giao</button>
                        <button type="button" class="filter-chip" data-status-chip="completed">Đã nhận</button>
                        <button type="button" class="filter-chip" data-status-chip="cancelled">Đã hủy</button>
                    </div>
                    <div class="order-result-hint">Mẹo: bấm <kbd>/</kbd> để focus ô tìm kiếm nhanh.</div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Ngày đặt</th>
                                <th>Trạng thái</th>
                                <th>Thanh toán</th>
                                <th>Vận chuyển</th>
                                <th class="text-end">Tổng tiền</th>
                                <th>Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <?php foreach ($orders as $order): ?>
                                <?php
                                    $statusKey = (string) ($order['status_key'] ?? 'other');
                                    $statusGroup = 'pending';
                                    if (in_array($statusKey, ['shipping', 'near_delivery'], true)) {
                                        $statusGroup = 'shipping';
                                    } elseif ($statusKey === 'completed') {
                                        $statusGroup = 'completed';
                                    } elseif ($statusKey === 'cancelled') {
                                        $statusGroup = 'cancelled';
                                    }

                                    $searchText = implode(' ', [
                                        (string) ($order['id'] ?? ''),
                                        (string) ($order['date'] ?? ''),
                                        (string) ($order['status_label'] ?? ''),
                                        (string) ($order['shipping_progress'] ?? ''),
                                    ]);
                                ?>
                                <tr class="order-row"
                                    data-status-key="<?php echo htmlspecialchars($statusKey, ENT_QUOTES); ?>"
                                    data-status-group="<?php echo htmlspecialchars($statusGroup, ENT_QUOTES); ?>"
                                    data-order-id="<?php echo htmlspecialchars((string) ($order['id'] ?? ''), ENT_QUOTES); ?>"
                                    data-date-ts="<?php echo (int) ($order['date_ts'] ?? 0); ?>"
                                    data-total="<?php echo (float) ($order['total'] ?? 0); ?>"
                                    data-search="<?php echo htmlspecialchars(mb_strtolower($searchText), ENT_QUOTES); ?>">
                                    <td class="fw-semibold"><?php echo htmlspecialchars((string) ($order['id'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($order['date'] ?? '')); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo htmlspecialchars((string) ($order['status_class'] ?? 'status-other')); ?>">
                                            <?php echo htmlspecialchars((string) ($order['status_label'] ?? 'Đang xử lý')); ?>
                                        </span>
                                    </td>
                                    <td class="small">
                                        <div><?php echo htmlspecialchars((string) (($order['payment_method'] ?? '') !== '' ? $order['payment_method'] : 'Chưa rõ')); ?></div>
                                        <div class="text-muted"><?php echo htmlspecialchars((string) (($order['payment_status'] ?? '') !== '' ? $order['payment_status'] : 'Chưa cập nhật')); ?></div>
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

                                        <?php if (!empty($order['can_confirm_qr_paid'])): ?>
                                            <form method="post" class="mt-2">
                                                <input type="hidden" name="action" value="confirm_qr_paid">
                                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars((string) ($order['id'] ?? ''), ENT_QUOTES); ?>">
                                                <button type="submit" class="btn btn-sm btn-warning">
                                                    Tôi đã chuyển khoản thành công
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (in_array((string) ($order['status_key'] ?? ''), ['shipping', 'near_delivery'], true)): ?>
                                            <form method="post" class="mt-2">
                                                <input type="hidden" name="action" value="confirm_received">
                                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars((string) ($order['id'] ?? ''), ENT_QUOTES); ?>">
                                                <button type="submit" class="btn btn-sm btn-success">Đã nhận hàng</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (in_array((string) ($order['status_key'] ?? ''), ['pending', 'pending_payment'], true)): ?>
                                            <form method="post" class="mt-2 d-grid gap-1">
                                                <input type="hidden" name="action" value="cancel_order">
                                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars((string) ($order['id'] ?? ''), ENT_QUOTES); ?>">
                                                <select name="cancel_reason" class="form-select form-select-sm" required>
                                                    <option value="">Chọn lý do hủy đơn...</option>
                                                    <option value="Đặt nhầm sản phẩm">Đặt nhầm sản phẩm</option>
                                                    <option value="Muốn thay đổi sản phẩm/số lượng">Muốn thay đổi sản phẩm/số lượng</option>
                                                    <option value="Thời gian giao không phù hợp">Thời gian giao không phù hợp</option>
                                                    <option value="Tìm được giá tốt hơn">Tìm được giá tốt hơn</option>
                                                    <option value="Không còn nhu cầu">Không còn nhu cầu</option>
                                                    <option value="Lý do khác">Lý do khác</option>
                                                </select>
                                                <input type="text" name="cancel_note" class="form-control form-control-sm" maxlength="250" placeholder="Ghi chú thêm (không bắt buộc)">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Hủy đơn hàng</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="orderEmptyFilter" class="order-empty-filter">
                    Không có đơn hàng phù hợp với bộ lọc hiện tại.
                </div>
                <div id="orderResultCount" class="order-result-hint"></div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const rows = Array.from(document.querySelectorAll('tr.order-row'));
            if (!rows.length) return;

            const tbody = document.getElementById('ordersTableBody');
            const searchInput = document.getElementById('orderSearchInput');
            const statusFilter = document.getElementById('orderStatusFilter');
            const sortSelect = document.getElementById('orderSortSelect');
            const emptyFilter = document.getElementById('orderEmptyFilter');
            const resultCount = document.getElementById('orderResultCount');
            const chips = Array.from(document.querySelectorAll('[data-status-chip]'));

            function compareRows(a, b, sortValue) {
                const dateA = Number(a.dataset.dateTs || 0);
                const dateB = Number(b.dataset.dateTs || 0);
                const totalA = Number(a.dataset.total || 0);
                const totalB = Number(b.dataset.total || 0);

                switch (sortValue) {
                    case 'date_asc':
                        return dateA - dateB;
                    case 'total_desc':
                        return totalB - totalA;
                    case 'total_asc':
                        return totalA - totalB;
                    case 'date_desc':
                    default:
                        return dateB - dateA;
                }
            }

            function applyFilterAndSort() {
                const keyword = (searchInput?.value || '').trim().toLowerCase();
                const selectedStatus = statusFilter?.value || 'all';
                const sortValue = sortSelect?.value || 'date_desc';

                const visibleRows = rows.filter((row) => {
                    const haystack = String(row.dataset.search || '').toLowerCase();
                    const statusGroup = String(row.dataset.statusGroup || 'pending');

                    const matchKeyword = keyword === '' || haystack.includes(keyword);
                    const matchStatus = selectedStatus === 'all' || statusGroup === selectedStatus;

                    return matchKeyword && matchStatus;
                });

                rows.forEach((row) => {
                    row.style.display = 'none';
                    row.classList.remove('selected');
                });

                visibleRows.sort((a, b) => compareRows(a, b, sortValue));
                visibleRows.forEach((row) => {
                    row.style.display = '';
                    tbody?.appendChild(row);
                });

                if (emptyFilter) {
                    emptyFilter.style.display = visibleRows.length === 0 ? 'block' : 'none';
                }

                if (resultCount) {
                    resultCount.textContent = `Hiển thị ${visibleRows.length}/${rows.length} đơn hàng`;
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', applyFilterAndSort);
            }

            if (statusFilter) {
                statusFilter.addEventListener('change', applyFilterAndSort);
            }

            chips.forEach((chip) => {
                chip.addEventListener('click', () => {
                    const value = chip.dataset.statusChip || 'all';
                    if (statusFilter) {
                        statusFilter.value = value;
                    }

                    chips.forEach((btn) => btn.classList.remove('active'));
                    chip.classList.add('active');
                    applyFilterAndSort();
                });
            });

            if (sortSelect) {
                sortSelect.addEventListener('change', applyFilterAndSort);
            }

            rows.forEach((row) => {
                row.addEventListener('click', function (event) {
                    if (this.style.display === 'none') {
                        return;
                    }

                    if (event.target.closest('details, summary, button, a, form')) {
                        return;
                    }

                    rows.forEach((r) => r.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey) {
                    const tag = String(document.activeElement?.tagName || '').toLowerCase();
                    if (tag !== 'input' && tag !== 'textarea' && tag !== 'select') {
                        event.preventDefault();
                        searchInput?.focus();
                    }
                }
            });

            if (statusFilter) {
                statusFilter.addEventListener('change', () => {
                    const value = statusFilter.value || 'all';
                    chips.forEach((btn) => {
                        btn.classList.toggle('active', (btn.dataset.statusChip || 'all') === value);
                    });
                });
            }

            applyFilterAndSort();
        })();
    </script>
    <script src="web-events.js?v=20260412-2"></script>
</body>
</html>
