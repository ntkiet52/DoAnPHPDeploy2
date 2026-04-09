<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function respondCheckout(bool $ok, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'ok' => $ok,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function getExistingColumns(PDO $pdo, string $table): array
{
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

function pickExistingColumn(array $existingColumns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array(strtolower($candidate), $existingColumns, true)) {
            return $candidate;
        }
    }

    return null;
}

function generateNextCode(PDO $pdo, string $table, string $idColumn, string $prefix, int $padLength = 2): string
{
    $rows = $pdo->query("SELECT `{$idColumn}` AS code FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
    $usedNumbers = [];

    foreach ($rows as $rowCode) {
        $code = trim((string) $rowCode);
        if ($code === '') {
            continue;
        }

        if (strncasecmp($code, $prefix, strlen($prefix)) === 0) {
            $numericPart = substr($code, strlen($prefix));
            if ($numericPart !== '' && ctype_digit($numericPart)) {
                $usedNumbers[(int) $numericPart] = true;
                continue;
            }
        }

        if (preg_match('/(\d+)$/', $code, $matches) === 1) {
            $usedNumbers[(int) $matches[1]] = true;
        }
    }

    $next = 1;
    while (isset($usedNumbers[$next])) {
        $next++;
    }

    return $prefix . str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);
}

function resolveCustomerId(PDO $pdo, string $userName, string $userEmail): ?string
{
    $khColumns = getExistingColumns($pdo, 'khachhang');
    $idCol = pickExistingColumn($khColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);
    $nameCol = pickExistingColumn($khColumns, ['tenkhachhang', 'ten_khach_hang', 'tenkh', 'hoten', 'name']);
    $taxCol = pickExistingColumn($khColumns, ['masothue', 'ma_so_thue', 'email']);

    if ($idCol === null || $nameCol === null) {
        return null;
    }

    if ($taxCol !== null && $userEmail !== '') {
        $findByEmailStmt = $pdo->prepare(
            "SELECT `{$idCol}`
             FROM khachhang
             WHERE LOWER(`{$taxCol}`) = LOWER(:email)
             LIMIT 1"
        );
        $findByEmailStmt->execute([':email' => $userEmail]);
        $foundId = $findByEmailStmt->fetchColumn();
        if ($foundId !== false && trim((string) $foundId) !== '') {
            return trim((string) $foundId);
        }
    }

    if ($userName !== '') {
        $findByNameStmt = $pdo->prepare(
            "SELECT `{$idCol}`
             FROM khachhang
             WHERE LOWER(`{$nameCol}`) = LOWER(:name)
             LIMIT 1"
        );
        $findByNameStmt->execute([':name' => $userName]);
        $foundId = $findByNameStmt->fetchColumn();
        if ($foundId !== false && trim((string) $foundId) !== '') {
            return trim((string) $foundId);
        }
    }

    $newId = generateNextCode($pdo, 'khachhang', $idCol, 'KH', 2);
    $safeName = mb_substr($userName !== '' ? $userName : 'Khách lẻ', 0, 50);

    $insertColumns = [$idCol, $nameCol];
    $insertParams = [':id' => $newId, ':name' => $safeName];

    if ($taxCol !== null && $userEmail !== '') {
        $insertColumns[] = $taxCol;
        $insertParams[':tax'] = mb_substr(strtolower($userEmail), 0, 30);
    }

    $placeholders = [];
    foreach ($insertColumns as $column) {
        if ($column === $idCol) {
            $placeholders[] = ':id';
        } elseif ($column === $nameCol) {
            $placeholders[] = ':name';
        } else {
            $placeholders[] = ':tax';
        }
    }

    try {
        $insertStmt = $pdo->prepare(
            'INSERT INTO khachhang (' . implode(', ', array_map(static fn($c) => "`{$c}`", $insertColumns)) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $insertStmt->execute($insertParams);
    } catch (Throwable $createCustomerError) {
        if ($taxCol !== null) {
            $retryStmt = $pdo->prepare("INSERT INTO khachhang (`{$idCol}`, `{$nameCol}`) VALUES (:id, :name)");
            $retryStmt->execute([
                ':id' => $newId,
                ':name' => $safeName,
            ]);
        } else {
            throw $createCustomerError;
        }
    }

    return $newId;
}

function resolveProductId(PDO $pdo, string $rawId, string $rawName): ?string
{
    $hangColumns = getExistingColumns($pdo, 'hanghoa');
    $idCol = pickExistingColumn($hangColumns, ['mahang', 'ma_hang', 'idhanghoa', 'id']);
    $nameCol = pickExistingColumn($hangColumns, ['tenhang', 'ten_hang', 'tensp', 'tensanpham', 'name']);

    if ($idCol === null) {
        return null;
    }

    $rawId = trim($rawId);
    if ($rawId !== '') {
        $findByIdStmt = $pdo->prepare("SELECT `{$idCol}` FROM hanghoa WHERE `{$idCol}` = :id LIMIT 1");
        $findByIdStmt->execute([':id' => $rawId]);
        $found = $findByIdStmt->fetchColumn();
        if ($found !== false) {
            return (string) $found;
        }
    }

    $rawName = trim($rawName);
    if ($rawName !== '' && $nameCol !== null) {
        $findByNameStmt = $pdo->prepare("SELECT `{$idCol}` FROM hanghoa WHERE LOWER(`{$nameCol}`) = LOWER(:name) LIMIT 1");
        $findByNameStmt->execute([':name' => $rawName]);
        $found = $findByNameStmt->fetchColumn();
        if ($found !== false) {
            return (string) $found;
        }

        $findByLikeStmt = $pdo->prepare("SELECT `{$idCol}` FROM hanghoa WHERE LOWER(`{$nameCol}`) LIKE LOWER(:name) LIMIT 1");
        $findByLikeStmt->execute([':name' => '%' . $rawName . '%']);
        $found = $findByLikeStmt->fetchColumn();
        if ($found !== false) {
            return (string) $found;
        }
    }

    return null;
}

function currentVoucherUserKeyCheckout(): string
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        return 'UID_' . $userId;
    }

    $customerCode = trim((string) ($_SESSION['ma_khach_hang'] ?? ''));
    if ($customerCode === '') {
        $customerCode = 'UNKNOWN';
    }

    return 'CUST_' . $customerCode;
}

function ensureVoucherUsageTablePdo(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS voucher_nguoi_dung_da_dung (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_key VARCHAR(100) NOT NULL,
        id_voucher INT NOT NULL,
        ma_voucher VARCHAR(100) NOT NULL,
        ma_don_hang VARCHAR(100) DEFAULT NULL,
        thoi_gian_su_dung DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_voucher (user_key, id_voucher),
        INDEX idx_user_key (user_key),
        INDEX idx_id_voucher (id_voucher)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondCheckout(false, 'Phương thức không hợp lệ.', [], 405);
}

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    respondCheckout(false, 'Vui lòng đăng nhập để đặt hàng.', [], 401);
}

$payload = null;
$rawBody = file_get_contents('php://input');
if (is_string($rawBody) && trim($rawBody) !== '') {
    $payload = json_decode($rawBody, true);
}

if (!is_array($payload)) {
    $itemsJson = (string) ($_POST['items_json'] ?? '');
    $payload = $itemsJson !== '' ? json_decode($itemsJson, true) : [];
}

$voucherPayload = is_array($payload['voucher'] ?? null) ? $payload['voucher'] : null;

$rawItems = $payload['items'] ?? [];
if (!is_array($rawItems)) {
    $rawItems = [];
}

$items = [];
foreach ($rawItems as $rawItem) {
    if (!is_array($rawItem)) {
        continue;
    }

    $id = trim((string) ($rawItem['id'] ?? ''));
    $name = trim((string) ($rawItem['name'] ?? ''));
    $qty = (int) ($rawItem['qty'] ?? 0);
    $price = (float) ($rawItem['price'] ?? 0);

    if ($qty <= 0) {
        continue;
    }

    $items[] = [
        'id' => $id,
        'name' => $name,
        'qty' => $qty,
        'price' => max(0, $price),
    ];
}

if (count($items) === 0) {
    respondCheckout(false, 'Không có sản phẩm hợp lệ để đặt hàng.');
}

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

    $pxColumns = getExistingColumns($pdo, 'phieuxuat');
    $ctpxColumns = getExistingColumns($pdo, 'chitietphieuxuat');

    $orderIdCol = pickExistingColumn($pxColumns, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon', 'id']);
    $orderCustomerCol = pickExistingColumn($pxColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'idkhachhang']);
    $orderDateCol = pickExistingColumn($pxColumns, ['ngayxuat', 'ngay_xuat', 'ngaydat', 'ngay_dat', 'ngaylap']);
    $orderStaffCol = pickExistingColumn($pxColumns, ['manv', 'ma_nv', 'idnhanvien']);
    $orderSignCol = pickExistingColumn($pxColumns, ['kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu']);
    $orderStatusCol = pickExistingColumn($pxColumns, ['trangthai', 'trang_thai', 'status']);
    $orderTotalCol = pickExistingColumn($pxColumns, ['tongtien', 'tong_tien', 'thanhtien', 'thanh_tien', 'total']);

    if ($orderIdCol === null) {
        respondCheckout(false, 'Không tìm thấy cột mã đơn trong bảng phiếu xuất.');
    }

    $detailOrderIdCol = pickExistingColumn($ctpxColumns, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon']);
    $detailProductCol = pickExistingColumn($ctpxColumns, ['mahang', 'ma_hang', 'idhanghoa']);
    $detailPriceCol = pickExistingColumn($ctpxColumns, ['giaban', 'gia_ban', 'giaxuat', 'gia_xuat', 'dongia', 'don_gia', 'gia']);
    $detailQtyCol = pickExistingColumn($ctpxColumns, ['soluongpx', 'so_luong_px', 'soluong', 'so_luong']);
    $detailTotalCol = pickExistingColumn($ctpxColumns, ['thanhtienpx', 'thanh_tien_px', 'thanhtien', 'thanh_tien', 'tongtien', 'tong_tien']);

    if ($detailOrderIdCol === null || $detailProductCol === null) {
        respondCheckout(false, 'Không tìm thấy cột bắt buộc để lưu chi tiết đơn hàng.');
    }

    $userName = trim((string) ($_SESSION['user_name'] ?? 'Khách hàng'));
    $userEmail = trim((string) ($_SESSION['user_email'] ?? ''));

    $customerId = null;
    if ($orderCustomerCol !== null) {
        $customerId = resolveCustomerId($pdo, $userName, $userEmail);
        if ($customerId === null || $customerId === '') {
            respondCheckout(false, 'Không thể xác định khách hàng cho đơn hàng này.');
        }
    }

    $staffId = null;
    if ($orderStaffCol !== null) {
        try {
            $nvColumns = getExistingColumns($pdo, 'nhanvien');
            $nvIdCol = pickExistingColumn($nvColumns, ['manv', 'ma_nv', 'id']);
            if ($nvIdCol !== null) {
                $staffStmt = $pdo->query("SELECT `{$nvIdCol}` FROM nhanvien ORDER BY `{$nvIdCol}` ASC LIMIT 1");
                $staffValue = $staffStmt->fetchColumn();
                if ($staffValue !== false) {
                    $staffId = trim((string) $staffValue);
                }
            }
        } catch (Throwable $ignored) {
        }
    }

    $resolvedItems = [];
    $invalidItems = [];

    foreach ($items as $item) {
        $productId = resolveProductId($pdo, (string) ($item['id'] ?? ''), (string) ($item['name'] ?? ''));
        if ($productId === null || $productId === '') {
            $invalidItems[] = (string) (($item['id'] ?? '') !== '' ? $item['id'] : ($item['name'] ?? 'Sản phẩm'));
            continue;
        }

        $qty = max(1, (int) ($item['qty'] ?? 1));
        $price = max(0, (float) ($item['price'] ?? 0));
        $lineTotal = $price * $qty;

        $resolvedItems[] = [
            'product_id' => $productId,
            'qty' => $qty,
            'price' => $price,
            'line_total' => $lineTotal,
        ];
    }

    if (count($resolvedItems) === 0) {
        respondCheckout(false, 'Không có sản phẩm hợp lệ để tạo đơn hàng.', [
            'invalid_items' => $invalidItems,
        ]);
    }

    $orderTotal = 0.0;
    foreach ($resolvedItems as $resolvedItem) {
        $orderTotal += (float) $resolvedItem['line_total'];
    }

    ensureVoucherUsageTablePdo($pdo);
    $voucherUserKey = currentVoucherUserKeyCheckout();
    $voucherUsed = null;
    $discountAmount = 0.0;

    if (is_array($voucherPayload)) {
        $voucherCode = strtoupper(trim((string) ($voucherPayload['code'] ?? '')));
        if ($voucherCode !== '') {
            $voucherStmt = $pdo->prepare(
                "SELECT id_voucher, ma_voucher, kieu_giam, gia_tri_giam, tien_toi_thieu, so_luong_toi_da, so_luong_da_su_dung
                 FROM voucher
                 WHERE UPPER(ma_voucher) = :code
                 AND trang_thai = 'active'
                 AND NOW() BETWEEN ngay_bat_dau AND ngay_ket_thuc
                 LIMIT 1"
            );
            $voucherStmt->execute([':code' => $voucherCode]);
            $voucherRow = $voucherStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($voucherRow === null) {
                respondCheckout(false, 'Voucher không hợp lệ hoặc đã hết hạn.');
            }

            $voucherId = (int) ($voucherRow['id_voucher'] ?? 0);
            if ($voucherId <= 0) {
                respondCheckout(false, 'Voucher không hợp lệ.');
            }

            $usedCheckStmt = $pdo->prepare('SELECT id FROM voucher_nguoi_dung_da_dung WHERE user_key = :user_key AND id_voucher = :id_voucher LIMIT 1');
            $usedCheckStmt->execute([
                ':user_key' => $voucherUserKey,
                ':id_voucher' => $voucherId,
            ]);
            $alreadyUsed = $usedCheckStmt->fetchColumn() !== false;
            if ($alreadyUsed) {
                respondCheckout(false, 'Voucher này bạn đã dùng rồi.');
            }

            $maxQty = (int) ($voucherRow['so_luong_toi_da'] ?? 0);
            $usedQty = (int) ($voucherRow['so_luong_da_su_dung'] ?? 0);
            if ($maxQty > 0 && $usedQty >= $maxQty) {
                respondCheckout(false, 'Voucher đã hết lượt sử dụng.');
            }

            $minOrder = max(0, (float) ($voucherRow['tien_toi_thieu'] ?? 0));
            if ($orderTotal < $minOrder) {
                respondCheckout(false, 'Đơn hàng chưa đạt mức tối thiểu để dùng voucher này.');
            }

            $type = strtolower((string) ($voucherRow['kieu_giam'] ?? 'fixed'));
            $value = max(0, (float) ($voucherRow['gia_tri_giam'] ?? 0));
            if ($type === 'percent') {
                $discountAmount = round(($orderTotal * min(100, $value)) / 100);
            } else {
                $discountAmount = $value;
            }
            $discountAmount = min($discountAmount, $orderTotal);

            $voucherUsed = [
                'id_voucher' => $voucherId,
                'ma_voucher' => (string) ($voucherRow['ma_voucher'] ?? $voucherCode),
            ];
        }
    }

    $finalOrderTotal = max(0, $orderTotal - $discountAmount);

    $pdo->beginTransaction();

    try {
        $newOrderId = generateNextCode($pdo, 'phieuxuat', $orderIdCol, 'PX', 2);
        $orderDateTime = date('Y-m-d H:i:s');

        $insertColumns = [$orderIdCol];
        $insertPlaceholders = [':order_id'];
        $insertParams = [':order_id' => $newOrderId];

        if ($orderCustomerCol !== null && $customerId !== null) {
            $insertColumns[] = $orderCustomerCol;
            $insertPlaceholders[] = ':customer_id';
            $insertParams[':customer_id'] = $customerId;
        }

        if ($orderStaffCol !== null && $staffId !== null && $staffId !== '') {
            $insertColumns[] = $orderStaffCol;
            $insertPlaceholders[] = ':staff_id';
            $insertParams[':staff_id'] = $staffId;
        }

        if ($orderDateCol !== null) {
            $insertColumns[] = $orderDateCol;
            $insertPlaceholders[] = ':order_date';
            $insertParams[':order_date'] = $orderDateTime;
        }

        if ($orderStatusCol !== null) {
            $insertColumns[] = $orderStatusCol;
            $insertPlaceholders[] = ':order_status';
            $insertParams[':order_status'] = 'Chờ duyệt';
        }

        if ($orderSignCol !== null) {
            $insertColumns[] = $orderSignCol;
            $insertPlaceholders[] = ':order_sign';
            $insertParams[':order_sign'] = $orderStatusCol !== null ? 'PX' : 'CHO_DUYET';
        }

        if ($orderTotalCol !== null) {
            $insertColumns[] = $orderTotalCol;
            $insertPlaceholders[] = ':order_total';
            $insertParams[':order_total'] = $finalOrderTotal;
        }

        $insertOrderStmt = $pdo->prepare(
            'INSERT INTO phieuxuat (' . implode(', ', array_map(static fn($c) => "`{$c}`", $insertColumns)) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')'
        );
        $insertOrderStmt->execute($insertParams);

        foreach ($resolvedItems as $resolvedItem) {
            $detailColumns = [$detailOrderIdCol, $detailProductCol];
            $detailPlaceholders = [':order_id', ':product_id'];
            $detailParams = [
                ':order_id' => $newOrderId,
                ':product_id' => $resolvedItem['product_id'],
            ];

            if ($detailPriceCol !== null) {
                $detailColumns[] = $detailPriceCol;
                $detailPlaceholders[] = ':price';
                $detailParams[':price'] = $resolvedItem['price'];
            }

            if ($detailQtyCol !== null) {
                $detailColumns[] = $detailQtyCol;
                $detailPlaceholders[] = ':qty';
                $detailParams[':qty'] = $resolvedItem['qty'];
            }

            if ($detailTotalCol !== null) {
                $detailColumns[] = $detailTotalCol;
                $detailPlaceholders[] = ':line_total';
                $detailParams[':line_total'] = $resolvedItem['line_total'];
            }

            $insertDetailStmt = $pdo->prepare(
                'INSERT INTO chitietphieuxuat (' . implode(', ', array_map(static fn($c) => "`{$c}`", $detailColumns)) . ') VALUES (' . implode(', ', $detailPlaceholders) . ')'
            );
            $insertDetailStmt->execute($detailParams);
        }

        if (is_array($voucherUsed)) {
            $insertUsedStmt = $pdo->prepare(
                'INSERT INTO voucher_nguoi_dung_da_dung (user_key, id_voucher, ma_voucher, ma_don_hang) VALUES (:user_key, :id_voucher, :ma_voucher, :ma_don_hang)'
            );
            $insertUsedStmt->execute([
                ':user_key' => $voucherUserKey,
                ':id_voucher' => (int) $voucherUsed['id_voucher'],
                ':ma_voucher' => (string) $voucherUsed['ma_voucher'],
                ':ma_don_hang' => $newOrderId,
            ]);

            $incStmt = $pdo->prepare('UPDATE voucher SET so_luong_da_su_dung = so_luong_da_su_dung + 1 WHERE id_voucher = :id_voucher');
            $incStmt->execute([
                ':id_voucher' => (int) $voucherUsed['id_voucher'],
            ]);
        }

        $pdo->commit();

        // Xóa toàn bộ giỏ hàng active sau khi đặt đơn thành công
        $sessionCartCustomer = trim((string) ($_SESSION['ma_khach_hang'] ?? ''));
        if ($sessionCartCustomer !== '') {
            $clearCartStmt = $pdo->prepare(
                "DELETE gct
                 FROM gio_hang_chi_tiet gct
                 INNER JOIN gio_hang gh ON gh.id_gio_hang = gct.id_gio_hang
                 WHERE gh.ma_khach_hang = :ma_khach_hang
                 AND gh.trang_thai = 'active'"
            );
            $clearCartStmt->execute([
                ':ma_khach_hang' => $sessionCartCustomer,
            ]);
        }

        respondCheckout(true, 'Đặt hàng thành công.', [
            'order_id' => $newOrderId,
            'discount_amount' => $discountAmount,
            'final_total' => $finalOrderTotal,
            'voucher_used' => $voucherUsed,
            'invalid_items' => $invalidItems,
        ]);
    } catch (Throwable $txError) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $txError;
    }
} catch (Throwable $e) {
    respondCheckout(false, 'Không thể tạo đơn hàng: ' . $e->getMessage(), [], 500);
}
