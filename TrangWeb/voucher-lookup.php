<?php
header('Content-Type: application/json; charset=utf-8');

function respondVoucher(bool $ok, string $message, array $extra = [], int $status = 200): void {
    http_response_code($status);
    echo json_encode(array_merge([
        'ok' => $ok,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function formatVoucherDiscount(string $type, float $value): string {
    if (strtolower(trim($type)) === 'percent') {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
    }

    return number_format((int) round($value), 0, ',', '.') . 'đ';
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

    $code = strtoupper(trim((string) ($_GET['code'] ?? '')));

    if ($code !== '') {
        $stmt = $pdo->prepare(
            'SELECT id_voucher, ma_voucher, ten_voucher, mo_ta, kieu_giam, gia_tri_giam, tien_toi_thieu, ngay_bat_dau, ngay_ket_thuc, trang_thai, so_luong_toi_da, so_luong_da_su_dung
             FROM voucher
             WHERE UPPER(ma_voucher) = ?
             LIMIT 1'
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch();

        if (!$row) {
            respondVoucher(false, 'Mã voucher không tồn tại.', [], 404);
        }

        if (strtolower((string) ($row['trang_thai'] ?? 'inactive')) !== 'active') {
            respondVoucher(false, 'Voucher hiện không khả dụng.', [], 400);
        }

        $startTs = strtotime((string) ($row['ngay_bat_dau'] ?? ''));
        $endTs = strtotime((string) ($row['ngay_ket_thuc'] ?? ''));
        $nowTs = time();
        if ($startTs === false || $endTs === false || $startTs > $nowTs || $endTs < $nowTs) {
            respondVoucher(false, 'Voucher đã hết hạn hoặc chưa tới thời gian áp dụng.', [], 400);
        }

        $maxQty = (int) ($row['so_luong_toi_da'] ?? 0);
        $usedQty = (int) ($row['so_luong_da_su_dung'] ?? 0);
        if ($maxQty > 0 && $usedQty >= $maxQty) {
            respondVoucher(false, 'Voucher đã hết lượt sử dụng.', [], 400);
        }

        $discountType = (string) ($row['kieu_giam'] ?? 'fixed');
        $discountValue = (float) ($row['gia_tri_giam'] ?? 0);
        $minOrderValue = (float) ($row['tien_toi_thieu'] ?? 0);

        respondVoucher(true, 'Áp dụng voucher thành công.', [
            'voucher' => [
                'id' => (int) ($row['id_voucher'] ?? 0),
                'code' => (string) ($row['ma_voucher'] ?? ''),
                'name' => (string) ($row['ten_voucher'] ?? ''),
                'description' => (string) ($row['mo_ta'] ?? ''),
                'discount_text' => formatVoucherDiscount($discountType, $discountValue),
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'min_order_value' => $minOrderValue,
            ],
        ]);
    }

    $rows = $pdo->query('SELECT id_voucher, ma_voucher, ten_voucher, mo_ta, kieu_giam, gia_tri_giam, tien_toi_thieu, ngay_bat_dau, ngay_ket_thuc, so_luong_toi_da, so_luong_da_su_dung
                         FROM voucher
                         WHERE trang_thai = "active"
                         AND NOW() BETWEEN ngay_bat_dau AND ngay_ket_thuc
                         ORDER BY ngay_ket_thuc ASC, id_voucher DESC
                         LIMIT 20')->fetchAll();
    $items = [];

    foreach ($rows as $row) {
        $maxQty = (int) ($row['so_luong_toi_da'] ?? 0);
        $usedQty = (int) ($row['so_luong_da_su_dung'] ?? 0);
        if ($maxQty > 0 && $usedQty >= $maxQty) {
            continue;
        }

        $discountType = (string) ($row['kieu_giam'] ?? 'fixed');
        $discountValue = (float) ($row['gia_tri_giam'] ?? 0);
        $voucherCode = (string) ($row['ma_voucher'] ?? '');
        $voucherName = trim((string) ($row['ten_voucher'] ?? ''));

        $items[] = [
            'id' => (int) ($row['id_voucher'] ?? 0),
            'code' => $voucherCode,
            'name' => $voucherName,
            'description' => (string) ($row['mo_ta'] ?? ''),
            'discount_text' => formatVoucherDiscount($discountType, $discountValue),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'min_order_value' => (float) ($row['tien_toi_thieu'] ?? 0),
            'label' => $voucherCode . ($voucherName !== '' ? ' - ' . $voucherName : '') . ' • ' . formatVoucherDiscount($discountType, $discountValue),
        ];
    }

    respondVoucher(true, 'Danh sách voucher khả dụng.', [
        'items' => $items,
    ]);
} catch (Throwable $e) {
    respondVoucher(false, 'Không thể tải dữ liệu voucher: ' . $e->getMessage(), [], 500);
}
