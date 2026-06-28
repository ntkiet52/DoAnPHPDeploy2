<?php
// ============================================================
// HELPER DEBUG — ghi 1 dòng vào checkout-debug.txt
// ============================================================
function dbg(string $label, string $extra = ''): void
{
    $line = date('Y-m-d H:i:s') . "\t" . $label;
    if ($extra !== '') {
        $line .= "\t" . $extra;
    }
    file_put_contents(__DIR__ . '/checkout-debug.txt', $line . "\n", FILE_APPEND);
}

dbg('START');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function respondCheckout(bool $ok, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'ok'      => $ok,
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
    $cache[$table] = array_map(static fn($col) => strtolower((string) ($col['Field'] ?? '')), $rows);
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

function resolveCustomerId(PDO $pdo, string $userName, string $userEmail, string $sessionCustomerId = ''): ?string
{
    $khColumns = getExistingColumns($pdo, 'khachhang');
    $idCol   = pickExistingColumn($khColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);
    $nameCol = pickExistingColumn($khColumns, ['tenkhachhang', 'ten_khach_hang', 'tenkh', 'hoten', 'name']);
    $taxCol  = pickExistingColumn($khColumns, ['email', 'masothue', 'ma_so_thue']);

    dbg('CUSTOMER_COLS', "id={$idCol} name={$nameCol} tax={$taxCol}");

    if ($idCol === null || $nameCol === null) {
        dbg('CUSTOMER_COLS_MISSING');
        return null;
    }

    $sessionCustomerId = trim($sessionCustomerId);
    if ($sessionCustomerId !== '') {
        $stmt = $pdo->prepare("SELECT `{$idCol}` FROM khachhang WHERE `{$idCol}` = :id LIMIT 1");
        $stmt->execute([':id' => $sessionCustomerId]);
        $found = $stmt->fetchColumn();
        if ($found !== false && trim((string) $found) !== '') {
            dbg('CUSTOMER_FOUND_BY_SESSION', $found);
            return trim((string) $found);
        }
    }

    if ($taxCol !== null && $userEmail !== '') {
        $stmt = $pdo->prepare("SELECT `{$idCol}` FROM khachhang WHERE LOWER(`{$taxCol}`) = LOWER(:email) LIMIT 1");
        $stmt->execute([':email' => $userEmail]);
        $found = $stmt->fetchColumn();
        if ($found !== false && trim((string) $found) !== '') {
            dbg('CUSTOMER_FOUND_BY_EMAIL', $found);
            return trim((string) $found);
        }
    }

    if ($userName !== '') {
        $stmt = $pdo->prepare("SELECT `{$idCol}` FROM khachhang WHERE LOWER(`{$nameCol}`) = LOWER(:name) LIMIT 1");
        $stmt->execute([':name' => $userName]);
        $found = $stmt->fetchColumn();
        if ($found !== false && trim((string) $found) !== '') {
            dbg('CUSTOMER_FOUND_BY_NAME', $found);
            return trim((string) $found);
        }
    }

    $newId    = generateNextCode($pdo, 'khachhang', $idCol, 'KH', 2);
    $safeName = mb_substr($userName !== '' ? $userName : 'Khách lẻ', 0, 50);

    dbg('CUSTOMER_CREATE', "newId={$newId} name={$safeName} email={$userEmail}");

    $insertColumns = [$idCol, $nameCol];
    $insertParams  = [':id' => $newId, ':name' => $safeName];

    if ($taxCol !== null && $userEmail !== '') {
        $insertColumns[]    = $taxCol;
        $insertParams[':tax'] = strtolower($userEmail);
    }

    $placeholders = [];
    foreach ($insertColumns as $column) {
        if ($column === $idCol)   { $placeholders[] = ':id'; }
        elseif ($column === $nameCol) { $placeholders[] = ':name'; }
        else                          { $placeholders[] = ':tax'; }
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO khachhang (' .
            implode(', ', array_map(static fn($c) => "`{$c}`", $insertColumns)) .
            ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($insertParams);
        dbg('CUSTOMER_CREATED', $newId);
    } catch (Throwable $e) {
        dbg('CUSTOMER_CREATE_ERROR', $e->getMessage());
        if ($taxCol !== null) {
            dbg('CUSTOMER_CREATE_RETRY_WITHOUT_TAX');
            $retry = $pdo->prepare("INSERT INTO khachhang (`{$idCol}`, `{$nameCol}`) VALUES (:id, :name)");
            $retry->execute([':id' => $newId, ':name' => $safeName]);
            dbg('CUSTOMER_CREATE_RETRY_OK', $newId);
        } else {
            throw $e;
        }
    }
    return $newId;
}

function fetchCustomerProfileById(PDO $pdo, string $customerId): array
{
    $customerId = trim($customerId);
    if ($customerId === '') {
        return [];
    }
    $khColumns = getExistingColumns($pdo, 'khachhang');
    $idCol = pickExistingColumn($khColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);
    if ($idCol === null) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT * FROM khachhang WHERE `{$idCol}` = :id LIMIT 1");
    $stmt->execute([':id' => $customerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
}

function resolveProductId(PDO $pdo, string $rawId, string $rawName): ?string
{
    dbg('RESOLVE_PRODUCT', "id={$rawId} name={$rawName}");

    $hangColumns = getExistingColumns($pdo, 'hanghoa');
    $idCol   = pickExistingColumn($hangColumns, ['mahang', 'ma_hang', 'idhanghoa', 'id']);
    $nameCol = pickExistingColumn($hangColumns, ['tenhang', 'ten_hang', 'tensp', 'tensanpham', 'name']);

    dbg('PRODUCT_COLS', "idCol={$idCol} nameCol={$nameCol}");

    if ($idCol === null) {
        dbg('PRODUCT_IDCOL_MISSING');
        return null;
    }

    $rawId = trim($rawId);
    if ($rawId !== '') {
        try {
            $stmt = $pdo->prepare("SELECT `{$idCol}` FROM hanghoa WHERE `{$idCol}` = :id LIMIT 1");
            $stmt->execute([':id' => $rawId]);
            $found = $stmt->fetchColumn();
            dbg('PRODUCT_FIND_BY_ID', "found=" . ($found === false ? 'false' : $found));
            if ($found !== false) {
                return (string) $found;
            }
        } catch (Throwable $e) {
            dbg('PRODUCT_FIND_BY_ID_ERROR', $e->getMessage());
            throw $e;
        }
    }

    $rawName = trim($rawName);
    if ($rawName !== '' && $nameCol !== null) {
        $stmt = $pdo->prepare("SELECT `{$idCol}` FROM hanghoa WHERE LOWER(`{$nameCol}`) = LOWER(:name) LIMIT 1");
        $stmt->execute([':name' => $rawName]);
        $found = $stmt->fetchColumn();
        dbg('PRODUCT_FIND_BY_NAME_EXACT', "found=" . ($found === false ? 'false' : $found));
        if ($found !== false) {
            return (string) $found;
        }

        $stmt = $pdo->prepare("SELECT `{$idCol}` FROM hanghoa WHERE LOWER(`{$nameCol}`) LIKE LOWER(:name) LIMIT 1");
        $stmt->execute([':name' => '%' . $rawName . '%']);
        $found = $stmt->fetchColumn();
        dbg('PRODUCT_FIND_BY_NAME_LIKE', "found=" . ($found === false ? 'false' : $found));
        if ($found !== false) {
            return (string) $found;
        }
    }

    dbg('PRODUCT_NOT_FOUND', "id={$rawId} name={$rawName}");
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

function ensureVoucherClaimTablePdo(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS voucher_nguoi_dung_da_nhan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_key VARCHAR(100) NOT NULL,
        id_voucher INT NOT NULL,
        ma_voucher VARCHAR(100) NOT NULL,
        thoi_gian_nhan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_claim_user_voucher (user_key, id_voucher),
        INDEX idx_claim_user_key (user_key),
        INDEX idx_claim_voucher_id (id_voucher)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureOrderPaymentMetaTablePdo(PDO $pdo): void
{
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

function ensureOrderVoucherMetaTablePdo(PDO $pdo): void
{
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

function fetchAvailableStockForProduct(
    PDO $pdo,
    string $productId,
    string $importProductCol,
    string $importQtyCol,
    string $exportProductCol,
    string $exportQtyCol
): int {
    $productId = trim($productId);
    if ($productId === '') {
        return 0;
    }

    $hangColumns  = getExistingColumns($pdo, 'hanghoa');
    $ctpxColumns  = getExistingColumns($pdo, 'chitietphieuxuat');
    $pxColumns    = getExistingColumns($pdo, 'phieuxuat');

    $hangIdCol        = pickExistingColumn($hangColumns, ['mahang', 'ma_hang', 'idhanghoa', 'id']);
    $hangStockCol     = pickExistingColumn($hangColumns, ['soluongton', 'so_luong_ton', 'tonkho', 'ton_kho']);
    $detailOrderIdCol = pickExistingColumn($ctpxColumns, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon']) ?? 'IdPhieuXuat';
    $orderIdCol       = pickExistingColumn($pxColumns,   ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon', 'id']) ?? $detailOrderIdCol;
    $orderStatusCol   = pickExistingColumn($pxColumns,   ['kyhieupx', 'ky_hieu_px', 'trangthai', 'trang_thai', 'status']) ?? 'KyHieuPX';

    if ($hangIdCol !== null) {
        $selectStockSql = $hangStockCol !== null ? "hh.`{$hangStockCol}` AS so_luong_ton," : '';
        $stockStmt = $pdo->prepare(
            "SELECT
                {$selectStockSql}
                COALESCE((SELECT SUM(`{$importQtyCol}`) FROM chitietnhaphang WHERE `{$importProductCol}` = :product_id), 0) AS tong_nhap,
                COALESCE((
                    SELECT SUM(ctx.`{$exportQtyCol}`)
                    FROM chitietphieuxuat ctx
                    LEFT JOIN phieuxuat px ON px.`{$orderIdCol}` = ctx.`{$detailOrderIdCol}`
                    WHERE ctx.`{$exportProductCol}` = :product_id
                      AND (
                          px.`{$orderStatusCol}` IS NULL
                          OR (
                              LOWER(px.`{$orderStatusCol}`) NOT LIKE '%hủy%'
                              AND LOWER(px.`{$orderStatusCol}`) NOT LIKE '%huy%'
                              AND LOWER(px.`{$orderStatusCol}`) NOT LIKE '%cancel%'
                          )
                      )
                ), 0) AS tong_xuat
             FROM hanghoa hh
             WHERE hh.`{$hangIdCol}` = :product_id
             LIMIT 1"
        );
    } else {
        $stockStmt = $pdo->prepare(
            "SELECT
                COALESCE((SELECT SUM(`{$importQtyCol}`) FROM chitietnhaphang WHERE `{$importProductCol}` = :product_id), 0) AS tong_nhap,
                COALESCE((
                    SELECT SUM(ctx.`{$exportQtyCol}`)
                    FROM chitietphieuxuat ctx
                    LEFT JOIN phieuxuat px ON px.`{$orderIdCol}` = ctx.`{$detailOrderIdCol}`
                    WHERE ctx.`{$exportProductCol}` = :product_id
                      AND (
                          px.`{$orderStatusCol}` IS NULL
                          OR (
                              LOWER(px.`{$orderStatusCol}`) NOT LIKE '%hủy%'
                              AND LOWER(px.`{$orderStatusCol}`) NOT LIKE '%huy%'
                              AND LOWER(px.`{$orderStatusCol}`) NOT LIKE '%cancel%'
                          )
                      )
                ), 0) AS tong_xuat"
        );
    }
    $stockStmt->execute([':product_id' => $productId]);
    $stockRow = $stockStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $lowerStockRow  = array_change_key_case($stockRow, CASE_LOWER);
    $stockCandidates = ['soluongton', 'so_luong_ton', 'tonkho', 'ton_kho'];
    foreach ($stockCandidates as $candidate) {
        if (array_key_exists($candidate, $lowerStockRow)) {
            $stockFromTable = max(0, (int) $lowerStockRow[$candidate]);
            $tongNhap       = (int) ($stockRow['tong_nhap'] ?? 0);
            $tongXuat       = (int) ($stockRow['tong_xuat'] ?? 0);
            $stockFromFlow  = max(0, $tongNhap - $tongXuat);
            dbg('STOCK_CALC', "product={$productId} table={$stockFromTable} nhap={$tongNhap} xuat={$tongXuat} flow={$stockFromFlow}");
            return $stockFromFlow > 0 ? $stockFromFlow : $stockFromTable;
        }
    }

    $tongNhap = (int) ($stockRow['tong_nhap'] ?? 0);
    $tongXuat = (int) ($stockRow['tong_xuat'] ?? 0);
    $result   = max(0, $tongNhap - $tongXuat);
    dbg('STOCK_CALC_FLOW_ONLY', "product={$productId} nhap={$tongNhap} xuat={$tongXuat} result={$result}");
    return $result;
}

// ============================================================
// MAIN FLOW
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondCheckout(false, 'Phương thức không hợp lệ.', [], 405);
}

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    respondCheckout(false, 'Vui lòng đăng nhập để đặt hàng.', [], 401);
}

dbg('SESSION_OK', 'user_id=' . ($_SESSION['user_id'] ?? 'null'));

// ── Parse payload ──────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$payload = null;
if (is_string($rawBody) && trim($rawBody) !== '') {
    $payload = json_decode($rawBody, true);
    dbg('PAYLOAD_FROM_BODY', 'json_last_error=' . json_last_error());
}
if (!is_array($payload)) {
    $itemsJson = (string) ($_POST['items_json'] ?? '');
    $payload   = $itemsJson !== '' ? json_decode($itemsJson, true) : [];
    dbg('PAYLOAD_FROM_POST', 'items_json=' . ($itemsJson !== '' ? 'yes' : 'empty'));
}
if (!is_array($payload)) {
    $payload = [];
}

$voucherPayload = is_array($payload['voucher'] ?? null) ? $payload['voucher'] : null;

$paymentMethodRaw   = strtolower(trim((string) ($payload['payment_method'] ?? $_POST['payment_method'] ?? 'cod')));
$paymentMethod      = $paymentMethodRaw === 'qr' ? 'qr' : 'cod';
$paymentMethodLabel = $paymentMethod === 'qr' ? 'QR chuyển khoản' : 'Thanh toán khi nhận hàng';
$paymentStatusLabel = $paymentMethod === 'qr' ? 'Chờ khách chuyển khoản' : 'Thanh toán khi nhận hàng';
$orderStatusLabel   = $paymentMethod === 'qr' ? 'Chờ thanh toán QR' : 'Chờ duyệt';

$qrPaidConfirmedRaw = $payload['qr_paid_confirmed'] ?? $_POST['qr_paid_confirmed'] ?? false;
$qrPaidConfirmed    = filter_var($qrPaidConfirmedRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

dbg('PAYMENT', "method={$paymentMethod} qr_confirmed=" . ($qrPaidConfirmed ? 'true' : 'false'));

if ($paymentMethod === 'qr' && $qrPaidConfirmed !== true) {
    respondCheckout(false, 'Bạn cần xác nhận đã chuyển khoản QR trước khi đặt hàng.', [], 400);
}

// ── Parse items ────────────────────────────────────────────
$rawItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
$items = [];
foreach ($rawItems as $rawItem) {
    if (!is_array($rawItem)) {
        continue;
    }
    $id    = trim((string) ($rawItem['id'] ?? ''));
    $name  = trim((string) ($rawItem['name'] ?? ''));
    $qty   = (int) ($rawItem['qty'] ?? 0);
    $price = (float) ($rawItem['price'] ?? 0);
    if ($qty <= 0) {
        continue;
    }
    $items[] = ['id' => $id, 'name' => $name, 'qty' => $qty, 'price' => max(0, $price)];
}
dbg('ITEMS_PARSED', 'count=' . count($items));

if (count($items) === 0) {
    respondCheckout(false, 'Không có sản phẩm hợp lệ để đặt hàng.');
}

// ── DB connect ─────────────────────────────────────────────
$dbHost = 'webbanhang-mysql.mysql.database.azure.com';
$dbName = 'qlhethongbanhangmini';
$dbUser = 'webbanhang123';
$dbPass = 'thanhkiet1234ACK@';

try {
    dbg('PDO_CONNECT_START', "host={$dbHost} db={$dbName}");
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]
    );
    dbg('PDO_CONNECT_OK');

    // ── Detect columns ──────────────────────────────────────
    dbg('COLUMNS_DETECT_START');
    $pxColumns   = getExistingColumns($pdo, 'phieuxuat');
    $ctpxColumns = getExistingColumns($pdo, 'chitietphieuxuat');
    $ctnhColumns = getExistingColumns($pdo, 'chitietnhaphang');
    $hhColumns   = getExistingColumns($pdo, 'hanghoa');
    dbg('COLUMNS_DETECT_OK');

    $orderIdCol            = pickExistingColumn($pxColumns, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon', 'id']);
    $orderCustomerCol      = pickExistingColumn($pxColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'idkhachhang']);
    $orderDateCol          = pickExistingColumn($pxColumns, ['ngayxuat', 'ngay_xuat', 'ngaydat', 'ngay_dat', 'ngaylap']);
    $orderStaffCol         = pickExistingColumn($pxColumns, ['manv', 'ma_nv', 'idnhanvien']);
    $orderSignCol          = pickExistingColumn($pxColumns, ['kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu']);
    $orderStatusCol        = pickExistingColumn($pxColumns, ['trangthai', 'trang_thai', 'status']);
    $orderTotalCol         = pickExistingColumn($pxColumns, ['tongtien', 'tong_tien', 'thanhtien', 'thanh_tien', 'total']);
    $orderPaymentMethodCol = pickExistingColumn($pxColumns, ['hinhthucthanhtoan', 'hinh_thuc_thanh_toan', 'phuongthucthanhtoan', 'phuong_thuc_thanh_toan', 'ptthanhtoan', 'payment_method', 'thanhtoan']);
    $orderPaymentStatusCol = pickExistingColumn($pxColumns, ['trangthaithanhtoan', 'trang_thai_thanh_toan', 'ttthanhtoan', 'payment_status']);

    dbg('PX_COLS',
        "id={$orderIdCol} customer={$orderCustomerCol} date={$orderDateCol} " .
        "staff={$orderStaffCol} sign={$orderSignCol} status={$orderStatusCol} " .
        "total={$orderTotalCol} payMethod={$orderPaymentMethodCol} payStatus={$orderPaymentStatusCol}"
    );

    if ($orderIdCol === null) {
        respondCheckout(false, 'Không tìm thấy cột mã đơn trong bảng phiếu xuất.');
    }

    $detailOrderIdCol = pickExistingColumn($ctpxColumns, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon']);
    $detailProductCol = pickExistingColumn($ctpxColumns, ['mahang', 'ma_hang', 'idhanghoa']);
    $detailPriceCol   = pickExistingColumn($ctpxColumns, ['giaban', 'gia_ban', 'giaxuat', 'gia_xuat', 'dongia', 'don_gia', 'gia']);
    $detailQtyCol     = pickExistingColumn($ctpxColumns, ['soluongpx', 'so_luong_px', 'soluong', 'so_luong']);
    $detailTotalCol   = pickExistingColumn($ctpxColumns, ['thanhtienpx', 'thanh_tien_px', 'thanhtien', 'thanh_tien', 'tongtien', 'tong_tien']);
    $importProductCol = pickExistingColumn($ctnhColumns, ['mahang', 'ma_hang', 'idhanghoa']);
    $importQtyCol     = pickExistingColumn($ctnhColumns, ['soluongnhap', 'so_luong_nhap', 'soluong', 'so_luong']);
    $hangIdCol        = pickExistingColumn($hhColumns,   ['mahang', 'ma_hang', 'idhanghoa', 'id']);
    $hangStockCol     = pickExistingColumn($hhColumns,   ['soluongton', 'so_luong_ton', 'tonkho', 'ton_kho', 'soluong', 'so_luong']);

    dbg('CTPX_COLS',
        "orderId={$detailOrderIdCol} product={$detailProductCol} price={$detailPriceCol} " .
        "qty={$detailQtyCol} total={$detailTotalCol}"
    );
    dbg('CTNH_COLS', "product={$importProductCol} qty={$importQtyCol}");
    dbg('HH_COLS',   "id={$hangIdCol} stock={$hangStockCol}");

    if ($detailOrderIdCol === null || $detailProductCol === null) {
        respondCheckout(false, 'Không tìm thấy cột bắt buộc để lưu chi tiết đơn hàng.');
    }
    if ($detailQtyCol === null || $importProductCol === null || $importQtyCol === null) {
        respondCheckout(false, 'Không đủ cấu hình cột kho để kiểm tra tồn hàng trước khi đặt đơn.');
    }

    // ── Resolve customer ────────────────────────────────────
    $userName  = trim((string) ($_SESSION['user_name']  ?? 'Khách hàng'));
    $userEmail = trim((string) ($_SESSION['user_email'] ?? ''));
    dbg('SESSION_USER', "name={$userName} email={$userEmail}");

    $customerId = null;
    if ($orderCustomerCol !== null) {
        dbg('CUSTOMER_RESOLVE_START');
        $customerId = resolveCustomerId(
            $pdo,
            $userName,
            $userEmail,
            (string) ($_SESSION['ma_khach_hang'] ?? '')
        );
        dbg('CUSTOMER_RESOLVE_DONE', "customerId={$customerId}");

        if ($customerId === null || $customerId === '') {
            respondCheckout(false, 'Không thể xác định khách hàng cho đơn hàng này.');
        }

        dbg('CUSTOMER_PROFILE_FETCH_START', $customerId);
        $customerProfile = fetchCustomerProfileById($pdo, $customerId);
        dbg('CUSTOMER_PROFILE_FETCH_DONE', 'keys=' . implode(',', array_keys($customerProfile)));

        $phoneValue   = '';
        $addressValue = '';
        if (is_array($customerProfile) && count($customerProfile) > 0) {
            $lowerProfile = array_change_key_case($customerProfile, CASE_LOWER);
            foreach (['sdtkh', 'sdt', 'sodienthoai', 'so_dien_thoai', 'phone'] as $c) {
                if (array_key_exists($c, $lowerProfile)) {
                    $phoneValue = trim((string) $lowerProfile[$c]);
                    break;
                }
            }
            foreach (['diachi', 'dia_chi', 'address'] as $c) {
                if (array_key_exists($c, $lowerProfile)) {
                    $addressValue = trim((string) $lowerProfile[$c]);
                    break;
                }
            }
        }
        dbg('CUSTOMER_PROFILE_FIELDS', "phone=" . ($phoneValue !== '' ? 'ok' : 'MISSING') . " address=" . ($addressValue !== '' ? 'ok' : 'MISSING'));

        $missingFields = [];
        if ($phoneValue   === '') { $missingFields[] = 'Số điện thoại'; }
        if ($addressValue === '') { $missingFields[] = 'Địa chỉ'; }

        if (count($missingFields) > 0) {
            respondCheckout(false,
                'Vui lòng cập nhật hồ sơ trước khi đặt hàng: thiếu ' . implode(', ', $missingFields) . '.',
                ['needs_profile_completion' => true, 'missing_fields' => $missingFields, 'profile_url' => 'tai-khoan.php?tab=settings'],
                400
            );
        }
    }

    // ── Staff ───────────────────────────────────────────────
    $staffId = null;
    if ($orderStaffCol !== null) {
        try {
            $nvColumns = getExistingColumns($pdo, 'nhanvien');
            $nvIdCol   = pickExistingColumn($nvColumns, ['manv', 'ma_nv', 'id']);
            if ($nvIdCol !== null) {
                $staffValue = $pdo->query("SELECT `{$nvIdCol}` FROM nhanvien ORDER BY `{$nvIdCol}` ASC LIMIT 1")->fetchColumn();
                if ($staffValue !== false) {
                    $staffId = trim((string) $staffValue);
                }
            }
            dbg('STAFF', "staffId={$staffId}");
        } catch (Throwable $ignored) {
            dbg('STAFF_ERROR', $ignored->getMessage());
        }
    }

    // ── Resolve products ────────────────────────────────────
    dbg('PRODUCTS_RESOLVE_START', 'count=' . count($items));
    $resolvedItems = [];
    $invalidItems  = [];
    foreach ($items as $item) {
        $productId = resolveProductId($pdo, (string) ($item['id'] ?? ''), (string) ($item['name'] ?? ''));
        if ($productId === null || $productId === '') {
            $label = (string) (($item['id'] ?? '') !== '' ? $item['id'] : ($item['name'] ?? 'Sản phẩm'));
            dbg('PRODUCT_INVALID', $label);
            $invalidItems[] = $label;
            continue;
        }
        $qty       = max(1, (int) ($item['qty'] ?? 1));
        $price     = max(0, (float) ($item['price'] ?? 0));
        $lineTotal = $price * $qty;
        $resolvedItems[] = ['product_id' => $productId, 'qty' => $qty, 'price' => $price, 'line_total' => $lineTotal];
        dbg('PRODUCT_RESOLVED', "id={$productId} qty={$qty} price={$price}");
    }
    dbg('PRODUCTS_RESOLVE_DONE', 'resolved=' . count($resolvedItems) . ' invalid=' . count($invalidItems));

    if (count($resolvedItems) === 0) {
        respondCheckout(false, 'Không có sản phẩm hợp lệ để tạo đơn hàng.', ['invalid_items' => $invalidItems]);
    }

    // ── Aggregate qty per product ───────────────────────────
    $requestedQtyByProduct = [];
    foreach ($resolvedItems as $ri) {
        $key = (string) ($ri['product_id'] ?? '');
        if ($key === '' || (int) ($ri['qty'] ?? 0) <= 0) { continue; }
        $requestedQtyByProduct[$key] = ($requestedQtyByProduct[$key] ?? 0) + (int) $ri['qty'];
    }

    // ── Check stock ─────────────────────────────────────────
    dbg('STOCK_CHECK_START', 'products=' . count($requestedQtyByProduct));
    $insufficientItems = [];
    foreach ($resolvedItems as $ri) {
        $stock = fetchAvailableStockForProduct(
            $pdo,
            $ri['product_id'],
            $importProductCol,
            $importQtyCol,
            $detailProductCol,
            $detailQtyCol
        );
        dbg('STOCK_RESULT', "product={$ri['product_id']} stock={$stock} requested={$ri['qty']}");
        if ((int) ($ri['qty'] ?? 0) > $stock) {
            $insufficientItems[] = ['product_id' => $ri['product_id'], 'requested_qty' => $ri['qty'], 'available_stock' => $stock];
        }
    }
    if (count($insufficientItems) > 0) {
        $first = $insufficientItems[0];
        dbg('STOCK_INSUFFICIENT', "product={$first['product_id']} stock={$first['available_stock']}");
        respondCheckout(false,
            'Một số sản phẩm đã hết hoặc không đủ tồn kho. Sản phẩm ' . $first['product_id'] . ' chỉ còn ' . $first['available_stock'] . '.',
            ['insufficient_items' => $insufficientItems],
            400
        );
    }
    dbg('STOCK_CHECK_OK');

    // ── Subtotal ────────────────────────────────────────────
    $orderTotal = 0.0;
    foreach ($resolvedItems as $ri) {
        $orderTotal += (float) $ri['line_total'];
    }
    dbg('SUBTOTAL', (string) $orderTotal);

    // ── Ensure meta tables ──────────────────────────────────
    dbg('META_TABLES_START');
    ensureVoucherUsageTablePdo($pdo);
    ensureVoucherClaimTablePdo($pdo);
    ensureOrderPaymentMetaTablePdo($pdo);
    ensureOrderVoucherMetaTablePdo($pdo);
    dbg('META_TABLES_OK');

    // ── Voucher ─────────────────────────────────────────────
    $voucherUserKey  = currentVoucherUserKeyCheckout();
    $voucherUsed     = null;
    $discountAmount  = 0.0;

    if (is_array($voucherPayload)) {
        $voucherCode = strtoupper(trim((string) ($voucherPayload['code'] ?? '')));
        dbg('VOUCHER_TRY', $voucherCode);

        if ($voucherCode !== '') {
            $voucherStmt = $pdo->prepare(
                "SELECT id_voucher, ma_voucher, ten_voucher, kieu_giam, gia_tri_giam, tien_toi_thieu, so_luong_toi_da, so_luong_da_su_dung
                 FROM voucher
                 WHERE UPPER(ma_voucher) = :code
                 AND trang_thai = 'active'
                 AND NOW() BETWEEN ngay_bat_dau AND ngay_ket_thuc
                 LIMIT 1"
            );
            $voucherStmt->execute([':code' => $voucherCode]);
            $voucherRow = $voucherStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($voucherRow === null) {
                dbg('VOUCHER_NOT_FOUND', $voucherCode);
                respondCheckout(false, 'Voucher không hợp lệ hoặc đã hết hạn.');
            }

            $voucherId = (int) ($voucherRow['id_voucher'] ?? 0);
            dbg('VOUCHER_FOUND', "id={$voucherId} code={$voucherRow['ma_voucher']}");

            if ($voucherId <= 0) {
                respondCheckout(false, 'Voucher không hợp lệ.');
            }

            // Claim check
            $claimCheck = $pdo->prepare(
                'SELECT id FROM voucher_nguoi_dung_da_nhan WHERE user_key = :user_key AND (id_voucher = :id_voucher OR UPPER(ma_voucher) = :ma_voucher) LIMIT 1'
            );
            $claimCheck->execute([
                ':user_key'   => $voucherUserKey,
                ':id_voucher' => $voucherId,
                ':ma_voucher' => strtoupper((string) ($voucherRow['ma_voucher'] ?? $voucherCode)),
            ]);
            if ($claimCheck->fetchColumn() === false) {
                dbg('VOUCHER_NOT_CLAIMED', "user={$voucherUserKey} voucher={$voucherId}");
                respondCheckout(false, 'Bạn chưa nhận voucher này. Vui lòng nhận voucher trong mục Quản lý tài khoản trước.');
            }

            // Usage check
            $usedCheck = $pdo->prepare(
                'SELECT id FROM voucher_nguoi_dung_da_dung WHERE user_key = :user_key AND (id_voucher = :id_voucher OR UPPER(ma_voucher) = :ma_voucher) LIMIT 1'
            );
            $usedCheck->execute([
                ':user_key'   => $voucherUserKey,
                ':id_voucher' => $voucherId,
                ':ma_voucher' => strtoupper((string) ($voucherRow['ma_voucher'] ?? $voucherCode)),
            ]);
            if ($usedCheck->fetchColumn() !== false) {
                dbg('VOUCHER_ALREADY_USED', "user={$voucherUserKey} voucher={$voucherId}");
                respondCheckout(false, 'Voucher này bạn đã dùng rồi.');
            }

            $maxQty  = (int)   ($voucherRow['so_luong_toi_da']    ?? 0);
            $usedQty = (int)   ($voucherRow['so_luong_da_su_dung'] ?? 0);
            $minOrder = max(0, (float) ($voucherRow['tien_toi_thieu'] ?? 0));
            dbg('VOUCHER_LIMITS', "maxQty={$maxQty} usedQty={$usedQty} minOrder={$minOrder} orderTotal={$orderTotal}");

            if ($maxQty > 0 && $usedQty >= $maxQty) {
                respondCheckout(false, 'Voucher đã hết lượt sử dụng.');
            }
            if ($orderTotal < $minOrder) {
                respondCheckout(false, 'Đơn hàng chưa đạt mức tối thiểu để dùng voucher này.');
            }

            $type  = strtolower((string) ($voucherRow['kieu_giam'] ?? 'fixed'));
            $value = max(0, (float) ($voucherRow['gia_tri_giam'] ?? 0));
            $discountAmount = $type === 'percent'
                ? round(($orderTotal * min(100, $value)) / 100)
                : $value;
            $discountAmount = min($discountAmount, $orderTotal);

            dbg('VOUCHER_DISCOUNT', "type={$type} value={$value} discount={$discountAmount}");

            $voucherUsed = [
                'id_voucher' => $voucherId,
                'ma_voucher' => (string) ($voucherRow['ma_voucher'] ?? $voucherCode),
                'ten_voucher' => (string) ($voucherRow['ten_voucher'] ?? ''),
            ];
        }
    }

    $finalOrderTotal = max(0, $orderTotal - $discountAmount);
    dbg('FINAL_TOTAL', "subtotal={$orderTotal} discount={$discountAmount} final={$finalOrderTotal}");

    // ── Transaction ─────────────────────────────────────────
    dbg('TRANSACTION_BEGIN');
    $pdo->beginTransaction();
    dbg('TRANSACTION_OK');

    try {
        // Decrease stock
        if ($hangIdCol !== null && $hangStockCol !== null && count($requestedQtyByProduct) > 0) {
            dbg('STOCK_DECREASE_START', 'products=' . count($requestedQtyByProduct));
            $decreaseStmt = $pdo->prepare(
                "UPDATE hanghoa SET `{$hangStockCol}` = `{$hangStockCol}` - :qty
                 WHERE `{$hangIdCol}` = :product_id AND `{$hangStockCol}` >= :qty"
            );
            foreach ($requestedQtyByProduct as $productId => $buyQty) {
                $decreaseStmt->execute([':qty' => (int) $buyQty, ':product_id' => (string) $productId]);
                $affected = $decreaseStmt->rowCount();
                dbg('STOCK_DECREASE', "product={$productId} qty={$buyQty} affected={$affected}");
                if ($affected <= 0) {
                    throw new RuntimeException('Tồn kho sản phẩm ' . $productId . ' không đủ để trừ sau khi chốt đơn.');
                }
            }
            dbg('STOCK_DECREASE_OK');
        }

        // Generate order ID
        dbg('ORDER_ID_GENERATE_START');
        $newOrderId = generateNextCode($pdo, 'phieuxuat', $orderIdCol, 'PX', 2);
        dbg('ORDER_ID_GENERATED', $newOrderId);

        $orderDateTime = date('Y-m-d H:i:s');

        // Build INSERT phieuxuat
        $insertColumns      = [$orderIdCol];
        $insertPlaceholders = [':order_id'];
        $insertParams       = [':order_id' => $newOrderId];

        if ($orderCustomerCol !== null && $customerId !== null) {
            $insertColumns[]        = $orderCustomerCol;
            $insertPlaceholders[]   = ':customer_id';
            $insertParams[':customer_id'] = $customerId;
        }
        if ($orderStaffCol !== null && $staffId !== null && $staffId !== '') {
            $insertColumns[]      = $orderStaffCol;
            $insertPlaceholders[] = ':staff_id';
            $insertParams[':staff_id'] = $staffId;
        }
        if ($orderDateCol !== null) {
            $insertColumns[]      = $orderDateCol;
            $insertPlaceholders[] = ':order_date';
            $insertParams[':order_date'] = $orderDateTime;
        }
        if ($orderStatusCol !== null) {
            $insertColumns[]      = $orderStatusCol;
            $insertPlaceholders[] = ':order_status';
            $insertParams[':order_status'] = $orderStatusLabel;
        }
        if ($orderSignCol !== null) {
            $insertColumns[]      = $orderSignCol;
            $insertPlaceholders[] = ':order_sign';
            $insertParams[':order_sign'] = $orderStatusCol !== null
                ? ($paymentMethod === 'qr' ? 'CHO_THANH_TOAN_QR' : 'PX')
                : 'CHO_DUYET';
        }
        if ($orderTotalCol !== null) {
            $insertColumns[]      = $orderTotalCol;
            $insertPlaceholders[] = ':order_total';
            $insertParams[':order_total'] = $finalOrderTotal;
        }
        if ($orderPaymentMethodCol !== null) {
            $insertColumns[]      = $orderPaymentMethodCol;
            $insertPlaceholders[] = ':payment_method';
            $insertParams[':payment_method'] = $paymentMethodLabel;
        }
        if ($orderPaymentStatusCol !== null) {
            $insertColumns[]      = $orderPaymentStatusCol;
            $insertPlaceholders[] = ':payment_status';
            $insertParams[':payment_status'] = $paymentStatusLabel;
        }

        $sqlInsertPx =
            'INSERT INTO phieuxuat (' .
            implode(', ', array_map(static fn($c) => "`{$c}`", $insertColumns)) .
            ') VALUES (' .
            implode(', ', $insertPlaceholders) .
            ')';

        dbg('INSERT_PX_SQL', $sqlInsertPx);

        try {
            $pdo->prepare($sqlInsertPx)->execute($insertParams);
            dbg('INSERT_PX_OK', $newOrderId);
        } catch (PDOException $e) {
            dbg('INSERT_PX_ERROR', $e->getMessage());
            throw $e;
        }

        // Insert details
        dbg('INSERT_DETAILS_START', 'count=' . count($resolvedItems));
        foreach ($resolvedItems as $idx => $ri) {
            $detailColumns      = [$detailOrderIdCol, $detailProductCol];
            $detailPlaceholders = [':order_id', ':product_id'];
            $detailParams       = [':order_id' => $newOrderId, ':product_id' => $ri['product_id']];

            if ($detailPriceCol !== null) {
                $detailColumns[]      = $detailPriceCol;
                $detailPlaceholders[] = ':price';
                $detailParams[':price'] = $ri['price'];
            }
            if ($detailQtyCol !== null) {
                $detailColumns[]      = $detailQtyCol;
                $detailPlaceholders[] = ':qty';
                $detailParams[':qty'] = $ri['qty'];
            }
            if ($detailTotalCol !== null) {
                $detailColumns[]      = $detailTotalCol;
                $detailPlaceholders[] = ':line_total';
                $detailParams[':line_total'] = $ri['line_total'];
            }

            $sqlDetail =
                'INSERT INTO chitietphieuxuat (' .
                implode(', ', array_map(static fn($c) => "`{$c}`", $detailColumns)) .
                ') VALUES (' .
                implode(', ', $detailPlaceholders) .
                ')';

            dbg('INSERT_DETAIL_' . $idx, "product={$ri['product_id']} qty={$ri['qty']}");
            try {
                $pdo->prepare($sqlDetail)->execute($detailParams);
                dbg('INSERT_DETAIL_OK_' . $idx, $ri['product_id']);
            } catch (PDOException $e) {
                dbg('INSERT_DETAIL_ERROR_' . $idx, $e->getMessage());
                throw $e;
            }
        }
        dbg('INSERT_DETAILS_DONE');

        // Voucher usage
        if (is_array($voucherUsed)) {
            dbg('VOUCHER_USAGE_INSERT', "voucher={$voucherUsed['id_voucher']} order={$newOrderId}");
            $pdo->prepare('INSERT INTO voucher_nguoi_dung_da_dung (user_key, id_voucher, ma_voucher, ma_don_hang) VALUES (:user_key, :id_voucher, :ma_voucher, :ma_don_hang)')
                ->execute([
                    ':user_key'    => $voucherUserKey,
                    ':id_voucher'  => (int)    $voucherUsed['id_voucher'],
                    ':ma_voucher'  => (string) $voucherUsed['ma_voucher'],
                    ':ma_don_hang' => $newOrderId,
                ]);
            $pdo->prepare('UPDATE voucher SET so_luong_da_su_dung = so_luong_da_su_dung + 1 WHERE id_voucher = :id_voucher')
                ->execute([':id_voucher' => (int) $voucherUsed['id_voucher']]);
            dbg('VOUCHER_USAGE_OK');
        }

        // Payment meta
        dbg('PAYMENT_MAP_INSERT', "order={$newOrderId}");
        try {
            $pdo->prepare(
                "INSERT INTO phieuxuat_thanhtoan_map (ma_don_hang, phuong_thuc_thanh_toan, trang_thai_thanh_toan)
                 VALUES (:ma_don_hang, :phuong_thuc_thanh_toan, :trang_thai_thanh_toan)
                 ON DUPLICATE KEY UPDATE
                    phuong_thuc_thanh_toan = VALUES(phuong_thuc_thanh_toan),
                    trang_thai_thanh_toan  = VALUES(trang_thai_thanh_toan)"
            )->execute([
                ':ma_don_hang'            => $newOrderId,
                ':phuong_thuc_thanh_toan' => $paymentMethodLabel,
                ':trang_thai_thanh_toan'  => $paymentStatusLabel,
            ]);
            dbg('PAYMENT_MAP_OK');
        } catch (PDOException $e) {
            dbg('PAYMENT_MAP_ERROR', $e->getMessage());
            throw $e;
        }

        // Voucher meta
        $voucherCodeForMap = is_array($voucherUsed) ? trim((string) ($voucherUsed['ma_voucher'] ?? '')) : '';
        if ($voucherCodeForMap === '') { $voucherCodeForMap = null; }

        dbg('VOUCHER_MAP_INSERT', "order={$newOrderId} voucher=" . ($voucherCodeForMap ?? 'null'));
        try {
            $pdo->prepare(
                "INSERT INTO phieuxuat_voucher_map (ma_don_hang, ma_voucher, tong_tam_tinh, so_tien_giam, tong_thanh_toan)
                 VALUES (:ma_don_hang, :ma_voucher, :tong_tam_tinh, :so_tien_giam, :tong_thanh_toan)
                 ON DUPLICATE KEY UPDATE
                    ma_voucher      = VALUES(ma_voucher),
                    tong_tam_tinh   = VALUES(tong_tam_tinh),
                    so_tien_giam    = VALUES(so_tien_giam),
                    tong_thanh_toan = VALUES(tong_thanh_toan)"
            )->execute([
                ':ma_don_hang'    => $newOrderId,
                ':ma_voucher'     => $voucherCodeForMap,
                ':tong_tam_tinh'  => $orderTotal,
                ':so_tien_giam'   => $discountAmount,
                ':tong_thanh_toan'=> $finalOrderTotal,
            ]);
            dbg('VOUCHER_MAP_OK');
        } catch (PDOException $e) {
            dbg('VOUCHER_MAP_ERROR', $e->getMessage());
            throw $e;
        }

        // Commit
        dbg('COMMIT_START');
        $pdo->commit();
        dbg('COMMIT_OK', $newOrderId);

        // Clear cart
        $sessionCartCustomer = trim((string) ($_SESSION['ma_khach_hang'] ?? ''));
        if ($sessionCartCustomer !== '') {
            dbg('CLEAR_CART_START', $sessionCartCustomer);
            try {
                $pdo->prepare(
                    "DELETE gct FROM gio_hang_chi_tiet gct
                     INNER JOIN gio_hang gh ON gh.id_gio_hang = gct.id_gio_hang
                     WHERE gh.ma_khach_hang = :ma_khach_hang AND gh.trang_thai = 'active'"
                )->execute([':ma_khach_hang' => $sessionCartCustomer]);
                dbg('CLEAR_CART_OK');
            } catch (Throwable $cartErr) {
                dbg('CLEAR_CART_ERROR', $cartErr->getMessage());
                // Non-fatal — order đã commit, chỉ log
            }
        }

        dbg('RESPONSE_SUCCESS', $newOrderId);
        respondCheckout(true, 'Đặt hàng thành công.', [
            'order_id'            => $newOrderId,
            'subtotal'            => $orderTotal,
            'discount_amount'     => $discountAmount,
            'final_total'         => $finalOrderTotal,
            'voucher_used'        => $voucherUsed,
            'payment_method'      => $paymentMethod,
            'payment_method_label'=> $paymentMethodLabel,
            'invalid_items'       => $invalidItems,
        ]);

    } catch (Throwable $txError) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            dbg('ROLLBACK', $txError->getMessage());
        }
        throw $txError;
    }

} catch (Throwable $e) {
    dbg('FATAL_ERROR',
        'msg='  . $e->getMessage() .
        ' file=' . basename($e->getFile()) .
        ' line=' . $e->getLine()
    );
    respondCheckout(false, 'Không thể tạo đơn hàng: ' . $e->getMessage(), [], 500);
}