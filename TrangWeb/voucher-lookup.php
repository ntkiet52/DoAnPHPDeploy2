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

function parseMoneyValue(string $text): int {
    $digits = preg_replace('/[^\d]/', '', $text);
    return $digits !== '' ? (int) $digits : 0;
}

function parseDiscountMeta(string $discountText): array {
    $raw = trim($discountText);
    if ($raw === '') {
        return [
            'type' => 'fixed',
            'value' => 0,
        ];
    }

    if (preg_match('/(\d+(?:[\.,]\d+)?)\s*%/u', $raw, $m) === 1) {
        $percent = (float) str_replace(',', '.', (string) ($m[1] ?? '0'));
        return [
            'type' => 'percent',
            'value' => max(0, min(100, $percent)),
        ];
    }

    return [
        'type' => 'fixed',
        'value' => max(0, parseMoneyValue($raw)),
    ];
}

function normalizeDateString(string $raw): ?DateTimeImmutable {
    $value = trim($raw);
    if ($value === '') {
        return null;
    }

    $lower = mb_strtolower($value, 'UTF-8');
    if (str_contains($lower, 'không') || str_contains($lower, 'vo han') || str_contains($lower, 'vô hạn')) {
        return null;
    }

    $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->setTime(23, 59, 59);
        }
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }

    return (new DateTimeImmutable())->setTimestamp($ts)->setTime(23, 59, 59);
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
        $stmt = $pdo->prepare('SELECT id, code, discount, min_order, expiry, status FROM voucher WHERE UPPER(code) = UPPER(?) LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch();

        if (!$row) {
            respondVoucher(false, 'Mã voucher không tồn tại.', [], 404);
        }

        $status = trim((string) ($row['status'] ?? ''));
        if (strcasecmp($status, 'Active') !== 0) {
            respondVoucher(false, 'Voucher hiện không khả dụng.', [], 400);
        }

        $expiryRaw = (string) ($row['expiry'] ?? '');
        $expiryDate = normalizeDateString($expiryRaw);
        if ($expiryDate instanceof DateTimeImmutable) {
            $now = new DateTimeImmutable('now');
            if ($expiryDate < $now) {
                respondVoucher(false, 'Voucher đã hết hạn.', [], 400);
            }
        }

        $discountMeta = parseDiscountMeta((string) ($row['discount'] ?? ''));
        $minOrderValue = parseMoneyValue((string) ($row['min_order'] ?? ''));

        respondVoucher(true, 'Áp dụng voucher thành công.', [
            'voucher' => [
                'id' => (string) ($row['id'] ?? ''),
                'code' => (string) ($row['code'] ?? ''),
                'discount_text' => (string) ($row['discount'] ?? ''),
                'min_order_text' => (string) ($row['min_order'] ?? ''),
                'expiry_text' => $expiryRaw,
                'discount_type' => (string) ($discountMeta['type'] ?? 'fixed'),
                'discount_value' => (float) ($discountMeta['value'] ?? 0),
                'min_order_value' => $minOrderValue,
            ],
        ]);
    }

    $rows = $pdo->query('SELECT id, code, discount, min_order, expiry, status FROM voucher WHERE status = "Active" ORDER BY created_at DESC, id DESC LIMIT 8')->fetchAll();
    $items = [];
    $now = new DateTimeImmutable('now');

    foreach ($rows as $row) {
        $expiryRaw = (string) ($row['expiry'] ?? '');
        $expiryDate = normalizeDateString($expiryRaw);
        if ($expiryDate instanceof DateTimeImmutable && $expiryDate < $now) {
            continue;
        }

        $discountMeta = parseDiscountMeta((string) ($row['discount'] ?? ''));
        $items[] = [
            'id' => (string) ($row['id'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
            'discount_text' => (string) ($row['discount'] ?? ''),
            'min_order_text' => (string) ($row['min_order'] ?? ''),
            'discount_type' => (string) ($discountMeta['type'] ?? 'fixed'),
            'discount_value' => (float) ($discountMeta['value'] ?? 0),
            'min_order_value' => parseMoneyValue((string) ($row['min_order'] ?? '')),
        ];
    }

    respondVoucher(true, 'Danh sách voucher khả dụng.', [
        'items' => $items,
    ]);
} catch (Throwable $e) {
    respondVoucher(false, 'Không thể tải dữ liệu voucher: ' . $e->getMessage(), [], 500);
}
