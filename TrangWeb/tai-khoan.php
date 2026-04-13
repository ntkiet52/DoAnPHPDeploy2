<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: ../Login/Dangnhap.php');
    exit;
}

require_once __DIR__ . '/../Login/connect.php';

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

function getExistingColumnsMysqli(mysqli $conn, string $table): array {
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $rows = [];
    $result = $conn->query("SHOW COLUMNS FROM `{$table}`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = strtolower((string) ($row['Field'] ?? ''));
        }
        $result->free();
    }

    $cache[$table] = $rows;
    return $rows;
}

function pickExistingColumn(array $existingColumns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (in_array(strtolower($candidate), $existingColumns, true)) {
            return $candidate;
        }
    }

    return null;
}

function generateNextCustomerIdMysqli(mysqli $conn, string $idColumn): string {
    $rows = [];
    $result = $conn->query("SELECT `{$idColumn}` AS customer_id FROM khachhang");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = (string) ($row['customer_id'] ?? '');
        }
        $result->free();
    }

    $usedNumbers = [];
    foreach ($rows as $customerId) {
        if (preg_match('/(\d+)$/', trim($customerId), $matches) !== 1) {
            continue;
        }

        $usedNumbers[(int) $matches[1]] = true;
    }

    $next = 1;
    while (isset($usedNumbers[$next])) {
        $next++;
    }

    return 'KH' . str_pad((string) $next, 2, '0', STR_PAD_LEFT);
}

function loadOrCreateAccountCustomer(mysqli $conn, string $userName, string $userEmail, string $sessionCustomerId = ''): array {
    $columns = getExistingColumnsMysqli($conn, 'khachhang');
    $idCol = pickExistingColumn($columns, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);
    $nameCol = pickExistingColumn($columns, ['tenkhachhang', 'ten_khach_hang', 'tenkh', 'hoten', 'name']);
    $taxCol = pickExistingColumn($columns, ['masothue', 'ma_so_thue', 'email']);
    $genderCol = pickExistingColumn($columns, ['gioitinh', 'gioi_tinh', 'gender']);
    $addressCol = pickExistingColumn($columns, ['diachi', 'dia_chi', 'address']);
    $phoneCol = pickExistingColumn($columns, ['sdtkh', 'sdt', 'sodienthoai', 'so_dien_thoai', 'phone']);
    $bankCol = pickExistingColumn($columns, ['sotaikhoankh', 'so_tai_khoan_kh', 'sotaikhoan', 'bank_account']);

    if ($idCol === null || $nameCol === null) {
        return [
            'id' => '',
            'name' => $userName,
            'tax' => '',
            'gender' => '',
            'address' => '',
            'phone' => '',
            'bank' => '',
            'id_col' => $idCol,
            'name_col' => $nameCol,
            'tax_col' => $taxCol,
            'gender_col' => $genderCol,
            'address_col' => $addressCol,
            'phone_col' => $phoneCol,
            'bank_col' => $bankCol,
        ];
    }

    $customerRow = null;
    $safeSessionCustomerId = $conn->real_escape_string(trim($sessionCustomerId));
    $safeEmail = $conn->real_escape_string($userEmail);
    $safeName = $conn->real_escape_string($userName);

    if ($safeSessionCustomerId !== '') {
        $result = $conn->query(
            "SELECT * FROM khachhang WHERE `{$idCol}` = '{$safeSessionCustomerId}' LIMIT 1"
        );
        if ($result) {
            $customerRow = $result->fetch_assoc() ?: null;
            $result->free();
        }
    }

    if ($taxCol !== null && $safeEmail !== '') {
        $result = $conn->query(
            "SELECT * FROM khachhang WHERE LOWER(`{$taxCol}`) = LOWER('{$safeEmail}') LIMIT 1"
        );
        if ($result) {
            $customerRow = $result->fetch_assoc() ?: null;
            $result->free();
        }
    }

    if (!is_array($customerRow) && $safeName !== '') {
        $result = $conn->query(
            "SELECT * FROM khachhang WHERE LOWER(`{$nameCol}`) = LOWER('{$safeName}') LIMIT 1"
        );
        if ($result) {
            $customerRow = $result->fetch_assoc() ?: null;
            $result->free();
        }
    }

    if (!is_array($customerRow)) {
        $newId = generateNextCustomerIdMysqli($conn, $idCol);
        $safeInsertName = mb_substr($userName !== '' ? $userName : 'Khách hàng', 0, 50);

        $insertColumns = [$idCol, $nameCol];
        $insertValues = [
            "'" . $conn->real_escape_string($newId) . "'",
            "'" . $conn->real_escape_string($safeInsertName) . "'",
        ];

        if ($taxCol !== null && $userEmail !== '') {
            $insertColumns[] = $taxCol;
            $insertValues[] = "'" . $conn->real_escape_string(mb_substr(strtolower($userEmail), 0, 30)) . "'";
        }

        $conn->query(
            'INSERT INTO khachhang (' . implode(', ', array_map(static fn($c) => "`{$c}`", $insertColumns)) . ') VALUES (' . implode(', ', $insertValues) . ')'
        );

        $result = $conn->query("SELECT * FROM khachhang WHERE `{$idCol}` = '" . $conn->real_escape_string($newId) . "' LIMIT 1");
        if ($result) {
            $customerRow = $result->fetch_assoc() ?: null;
            $result->free();
        }
    }

    $customerRow = is_array($customerRow) ? $customerRow : [];
    $customerId = (string) pickOrderValue($customerRow, [$idCol], '');

    return [
        'id' => $customerId,
        'name' => (string) pickOrderValue($customerRow, [$nameCol], $userName),
        'tax' => $taxCol !== null ? (string) pickOrderValue($customerRow, [$taxCol], '') : '',
        'gender' => $genderCol !== null ? (string) pickOrderValue($customerRow, [$genderCol], '') : '',
        'address' => $addressCol !== null ? (string) pickOrderValue($customerRow, [$addressCol], '') : '',
        'phone' => $phoneCol !== null ? (string) pickOrderValue($customerRow, [$phoneCol], '') : '',
        'bank' => $bankCol !== null ? (string) pickOrderValue($customerRow, [$bankCol], '') : '',
        'id_col' => $idCol,
        'name_col' => $nameCol,
        'tax_col' => $taxCol,
        'gender_col' => $genderCol,
        'address_col' => $addressCol,
        'phone_col' => $phoneCol,
        'bank_col' => $bankCol,
    ];
}

function currentVoucherUserKeyAccount(): string {
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

function formatVoucherLabel(array $voucher): string {
    $type = strtolower((string) ($voucher['kieu_giam'] ?? 'fixed'));
    $value = (float) ($voucher['gia_tri_giam'] ?? 0);
    $discountText = $type === 'percent'
        ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%'
        : number_format((int) round($value), 0, ',', '.') . 'đ';

    $minOrder = (float) ($voucher['tien_toi_thieu'] ?? 0);
    return (string) ($voucher['ma_voucher'] ?? '') . ' - ' . $discountText . ' (Tối thiểu ' . number_format((int) round($minOrder), 0, ',', '.') . 'đ)';
}

function isReceivedStatus(string $rawStatus): bool {
    $status = mb_strtolower(trim($rawStatus));
    if ($status === '') {
        return false;
    }

    return str_contains($status, 'nhận')
        || str_contains($status, 'nhan')
        || str_contains($status, 'received')
        || str_contains($status, 'hoàn')
        || str_contains($status, 'hoan')
        || str_contains($status, 'xong')
        || str_contains($status, 'done')
        || str_contains($status, 'success');
}

function isExcludedSpentStatus(string $rawStatus): bool {
    $status = mb_strtolower(trim($rawStatus));
    if ($status === '') {
        return false;
    }

    $excludedKeywords = [
        'hủy',
        'huy',
        'cancel',
        'void',
        'thất bại',
        'that bai',
        'failed',
        'fail',
        'refund',
        'hoàn tiền',
        'hoan tien',
    ];

    foreach ($excludedKeywords as $keyword) {
        if (str_contains($status, $keyword)) {
            return true;
        }
    }

    return false;
}

$tab = strtolower(trim((string) ($_GET['tab'] ?? 'info')));
if (!in_array($tab, ['info', 'manage', 'settings'], true)) {
    $tab = 'info';
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userName = trim((string) ($_SESSION['user_name'] ?? 'Người dùng'));
$userEmail = trim((string) ($_SESSION['user_email'] ?? ''));
$userRole = strtolower(trim((string) ($_SESSION['user_role'] ?? 'user')));
$userLanguage = strtolower(trim((string) ($_SESSION['user_language'] ?? 'vi')));
$userTheme = strtolower(trim((string) ($_SESSION['user_theme'] ?? 'light')));

if (!in_array($userLanguage, ['vi', 'en'], true)) {
    $userLanguage = 'vi';
}

if (!in_array($userTheme, ['light', 'dark', 'system'], true)) {
    $userTheme = 'light';
}

$roleLabelMap = [
    'admin' => 'Quản trị viên',
    'khachhang' => 'Khách hàng',
    'user' => 'Người dùng',
];
$roleLabel = $roleLabelMap[$userRole] ?? 'Người dùng';

$flashMessage = '';
$flashType = 'success';
$customerProfile = [
    'id' => '',
    'name' => $userName,
    'tax' => '',
    'gender' => '',
    'address' => '',
    'phone' => '',
    'bank' => '',
    'id_col' => null,
    'name_col' => null,
    'tax_col' => null,
    'gender_col' => null,
    'address_col' => null,
    'phone_col' => null,
    'bank_col' => null,
];

$availableVouchers = [];
$claimedVouchers = [];
$purchaseSummary = [
    'total_orders' => 0,
    'total_spent' => 0.0,
];
$recentOrders = [];

$voucherUserKey = currentVoucherUserKeyAccount();

if (isset($conn) && $conn instanceof mysqli) {
    $conn->query("CREATE TABLE IF NOT EXISTS voucher_nguoi_dung_da_nhan (
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile') {
    $newName = trim((string) ($_POST['display_name'] ?? ''));
    $newPhone = trim((string) ($_POST['phone'] ?? ''));
    $newAddress = trim((string) ($_POST['address'] ?? ''));
    $newGender = trim((string) ($_POST['gender'] ?? ''));
    $newBank = trim((string) ($_POST['bank_account'] ?? ''));

    if ($newName === '' || $newPhone === '' || $newAddress === '') {
        $flashMessage = 'Tên hiển thị, số điện thoại và địa chỉ không được để trống.';
        $flashType = 'danger';
    } else {
        $updatedInDb = false;
        $updatedCustomer = false;

        if (isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare('UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $newName, $userId);
                $updatedInDb = $stmt->execute();
                $stmt->close();
            }

            $customerProfile = loadOrCreateAccountCustomer(
                $conn,
                $newName,
                $userEmail,
                (string) ($_SESSION['ma_khach_hang'] ?? '')
            );
            $customerId = trim((string) ($customerProfile['id'] ?? ''));
            $idCol = $customerProfile['id_col'] ?? null;
            $nameCol = $customerProfile['name_col'] ?? null;

            if ($customerId !== '' && $idCol !== null && $nameCol !== null) {
                $updates = ["`{$nameCol}` = ?"];
                $types = 's';
                $values = [$newName];

                if (($customerProfile['phone_col'] ?? null) !== null) {
                    $updates[] = "`{$customerProfile['phone_col']}` = ?";
                    $types .= 's';
                    $values[] = $newPhone;
                }

                if (($customerProfile['address_col'] ?? null) !== null) {
                    $updates[] = "`{$customerProfile['address_col']}` = ?";
                    $types .= 's';
                    $values[] = $newAddress;
                }

                if (($customerProfile['gender_col'] ?? null) !== null) {
                    $updates[] = "`{$customerProfile['gender_col']}` = ?";
                    $types .= 's';
                    $values[] = $newGender;
                }

                if (($customerProfile['bank_col'] ?? null) !== null) {
                    $updates[] = "`{$customerProfile['bank_col']}` = ?";
                    $types .= 's';
                    $values[] = $newBank;
                }

                if (($customerProfile['tax_col'] ?? null) !== null && $userEmail !== '') {
                    $updates[] = "`{$customerProfile['tax_col']}` = ?";
                    $types .= 's';
                    $values[] = mb_substr(strtolower($userEmail), 0, 30);
                }

                $types .= 's';
                $values[] = $customerId;

                $customerStmt = $conn->prepare('UPDATE khachhang SET ' . implode(', ', $updates) . " WHERE `{$idCol}` = ?");
                if ($customerStmt) {
                    $bindArgs = [$types];
                    foreach ($values as $key => $value) {
                        $bindArgs[] = &$values[$key];
                    }
                    call_user_func_array([$customerStmt, 'bind_param'], $bindArgs);
                    $updatedCustomer = $customerStmt->execute();
                    $customerStmt->close();
                }

                $_SESSION['ma_khach_hang'] = $customerId;
            }
        }

        $_SESSION['user_name'] = $newName;
        $userName = $newName;

        if ($updatedInDb || $updatedCustomer) {
            $flashMessage = 'Đã cập nhật thông tin tài khoản và hồ sơ khách hàng.';
            $flashType = 'success';
        } else {
            $flashMessage = 'Đã cập nhật thông tin trong phiên làm việc.';
            $flashType = 'warning';
        }
    }

    $tab = 'settings';
}

if (isset($conn) && $conn instanceof mysqli) {
    $customerProfile = loadOrCreateAccountCustomer(
        $conn,
        $userName,
        $userEmail,
        (string) ($_SESSION['ma_khach_hang'] ?? '')
    );
    if (trim((string) ($customerProfile['id'] ?? '')) !== '') {
        $_SESSION['ma_khach_hang'] = (string) $customerProfile['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_system_settings') {
    $newLanguage = strtolower(trim((string) ($_POST['language'] ?? 'vi')));
    $newTheme = strtolower(trim((string) ($_POST['theme'] ?? 'light')));

    if (!in_array($newLanguage, ['vi', 'en'], true) || !in_array($newTheme, ['light', 'dark', 'system'], true)) {
        $flashMessage = 'Thiết lập hệ thống không hợp lệ.';
        $flashType = 'danger';
    } else {
        $_SESSION['user_language'] = $newLanguage;
        $_SESSION['user_theme'] = $newTheme;
        $userLanguage = $newLanguage;
        $userTheme = $newTheme;
        $flashMessage = 'Đã lưu cài đặt hệ thống.';
        $flashType = 'success';
    }

    $tab = 'settings';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'claim_voucher') {
    $voucherId = (int) ($_POST['voucher_id'] ?? 0);
    $tab = 'manage';

    if ($voucherId <= 0) {
        $flashMessage = 'Vui lòng chọn voucher để nhận.';
        $flashType = 'danger';
    } else if (!isset($conn) || !($conn instanceof mysqli)) {
        $flashMessage = 'Không thể kết nối dữ liệu voucher.';
        $flashType = 'danger';
    } else {
        $voucherStmt = $conn->prepare(
            'SELECT id_voucher, ma_voucher FROM voucher WHERE id_voucher = ? AND trang_thai = ? AND NOW() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1'
        );

        if ($voucherStmt) {
            $active = 'active';
            $voucherStmt->bind_param('is', $voucherId, $active);
            $voucherStmt->execute();
            $voucherRes = $voucherStmt->get_result();
            $voucher = $voucherRes ? $voucherRes->fetch_assoc() : null;
            $voucherStmt->close();

            if (!is_array($voucher)) {
                $flashMessage = 'Voucher này không hợp lệ hoặc đã hết hạn.';
                $flashType = 'danger';
            } else {
                $insertStmt = $conn->prepare(
                    'INSERT INTO voucher_nguoi_dung_da_nhan (user_key, id_voucher, ma_voucher) VALUES (?, ?, ?)'
                );

                if ($insertStmt) {
                    $voucherCode = (string) ($voucher['ma_voucher'] ?? '');
                    $insertStmt->bind_param('sis', $voucherUserKey, $voucherId, $voucherCode);
                    $ok = $insertStmt->execute();
                    $insertError = $insertStmt->error;
                    $insertStmt->close();

                    if ($ok) {
                        $flashMessage = 'Nhận voucher thành công: ' . $voucherCode;
                        $flashType = 'success';
                    } else if (str_contains(strtolower($insertError), 'duplicate')) {
                        $flashMessage = 'Bạn đã nhận voucher này rồi.';
                        $flashType = 'warning';
                    } else {
                        $flashMessage = 'Không thể nhận voucher lúc này.';
                        $flashType = 'danger';
                    }
                } else {
                    $flashMessage = 'Không thể lưu voucher cho tài khoản.';
                    $flashType = 'danger';
                }
            }
        } else {
            $flashMessage = 'Không thể kiểm tra voucher.';
            $flashType = 'danger';
        }
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $active = 'active';
    $voucherQuery = "
        SELECT v.id_voucher, v.ma_voucher, v.ten_voucher, v.kieu_giam, v.gia_tri_giam, v.tien_toi_thieu
        FROM voucher v
        LEFT JOIN voucher_nguoi_dung_da_nhan r
          ON r.id_voucher = v.id_voucher AND r.user_key = ?
        LEFT JOIN voucher_nguoi_dung_da_dung u
          ON u.id_voucher = v.id_voucher AND u.user_key = ?
        WHERE v.trang_thai = ?
          AND NOW() BETWEEN v.ngay_bat_dau AND v.ngay_ket_thuc
          AND r.id IS NULL
          AND u.id IS NULL
        ORDER BY v.ngay_ket_thuc ASC, v.id_voucher DESC
        LIMIT 50
    ";

    $voucherStmt = $conn->prepare($voucherQuery);
    if ($voucherStmt) {
        $voucherStmt->bind_param('sss', $voucherUserKey, $voucherUserKey, $active);
        $voucherStmt->execute();
        $voucherRes = $voucherStmt->get_result();
        while ($voucherRes && ($voucherRow = $voucherRes->fetch_assoc())) {
            $availableVouchers[] = $voucherRow;
        }
        $voucherStmt->close();
    }

    $claimedStmt = $conn->prepare(
        "SELECT ma_voucher, thoi_gian_nhan FROM voucher_nguoi_dung_da_nhan WHERE user_key = ? ORDER BY thoi_gian_nhan DESC LIMIT 8"
    );
    if ($claimedStmt) {
        $claimedStmt->bind_param('s', $voucherUserKey);
        $claimedStmt->execute();
        $claimedRes = $claimedStmt->get_result();
        while ($claimedRes && ($claimedRow = $claimedRes->fetch_assoc())) {
            $claimedVouchers[] = $claimedRow;
        }
        $claimedStmt->close();
    }

    // Thống kê mua sắm
    $customerIds = [];
    $safeName = $conn->real_escape_string($userName);
    $safeEmail = $conn->real_escape_string($userEmail);

    $khColumns = getExistingColumnsMysqli($conn, 'khachhang');
    $khIdCol = pickExistingColumn($khColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);
    $khNameCol = pickExistingColumn($khColumns, ['tenkhachhang', 'ten_khach_hang', 'tenkh', 'hoten', 'name']);
    $khTaxCol = pickExistingColumn($khColumns, ['masothue', 'ma_so_thue', 'email']);

    if ($khIdCol !== null) {
        if ($khTaxCol !== null && $safeEmail !== '') {
            $rs = $conn->query("SELECT `{$khIdCol}` AS customer_id FROM khachhang WHERE LOWER(`{$khTaxCol}`) = LOWER('{$safeEmail}')");
            while ($rs && ($row = $rs->fetch_assoc())) {
                $id = trim((string) ($row['customer_id'] ?? ''));
                if ($id !== '') {
                    $customerIds[$id] = true;
                }
            }
            if ($rs) $rs->free();
        }

        if ($khNameCol !== null && $safeName !== '') {
            $rs = $conn->query("SELECT `{$khIdCol}` AS customer_id FROM khachhang WHERE LOWER(`{$khNameCol}`) = LOWER('{$safeName}')");
            while ($rs && ($row = $rs->fetch_assoc())) {
                $id = trim((string) ($row['customer_id'] ?? ''));
                if ($id !== '') {
                    $customerIds[$id] = true;
                }
            }
            if ($rs) $rs->free();
        }
    }

    $customerIds = array_keys($customerIds);

    $pxColumns = getExistingColumnsMysqli($conn, 'phieuxuat');
    $orderIdCol = pickExistingColumn($pxColumns, ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id']);
    $orderCustomerCol = pickExistingColumn($pxColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'idkhachhang']);
    $orderDateCol = pickExistingColumn($pxColumns, ['ngayxuat', 'ngay_xuat', 'ngaydat', 'ngay_dat', 'ngaylap']);
    $orderStatusCol = pickExistingColumn($pxColumns, ['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu']);
    $orderTotalCol = pickExistingColumn($pxColumns, ['tongtien', 'tong_tien', 'thanhtien', 'thanh_tien', 'total']);

    $ctpxColumns = getExistingColumnsMysqli($conn, 'chitietphieuxuat');
    $detailOrderIdCol = pickExistingColumn($ctpxColumns, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon']);
    $detailTotalCol = pickExistingColumn($ctpxColumns, ['thanhtienpx', 'thanh_tien_px', 'thanhtien', 'thanh_tien', 'tongtien', 'tong_tien']);
    $detailQtyCol = pickExistingColumn($ctpxColumns, ['soluongpx', 'so_luong_px', 'soluong', 'so_luong']);
    $detailPriceCol = pickExistingColumn($ctpxColumns, ['giaban', 'gia_ban', 'giaxuat', 'gia_xuat', 'dongia', 'don_gia', 'gia']);

    if ($orderIdCol !== null && $orderCustomerCol !== null && count($customerIds) > 0) {
        $escapedIds = array_map(static function ($id) use ($conn) {
            return "'" . $conn->real_escape_string((string) $id) . "'";
        }, $customerIds);
        $idInClause = implode(',', $escapedIds);

        $selectTotal = $orderTotalCol !== null ? "`{$orderTotalCol}` AS order_total" : '0 AS order_total';
        $selectDate = $orderDateCol !== null ? "`{$orderDateCol}` AS order_date" : "'' AS order_date";
        $selectStatus = $orderStatusCol !== null ? "`{$orderStatusCol}` AS order_status" : "'' AS order_status";

        $sql = "SELECT `{$orderIdCol}` AS order_id, {$selectDate}, {$selectStatus}, {$selectTotal}
                FROM phieuxuat
                WHERE `{$orderCustomerCol}` IN ({$idInClause})
                ORDER BY `{$orderIdCol}` DESC";

        $orderRows = [];
        $orderIdList = [];

        $rs = $conn->query($sql);
        while ($rs && ($row = $rs->fetch_assoc())) {
            $orderRows[] = $row;
            $orderId = trim((string) ($row['order_id'] ?? ''));
            if ($orderId !== '') {
                $orderIdList[] = $orderId;
            }
        }
        if ($rs) $rs->free();

        $detailTotalByOrder = [];
        if ($detailOrderIdCol !== null && count($orderIdList) > 0) {
            $escapedOrderIds = array_map(static function ($id) use ($conn) {
                return "'" . $conn->real_escape_string((string) $id) . "'";
            }, $orderIdList);
            $orderIdInClause = implode(',', $escapedOrderIds);

            if ($detailTotalCol !== null) {
                $detailSql = "SELECT `{$detailOrderIdCol}` AS order_id, SUM(COALESCE(`{$detailTotalCol}`, 0)) AS detail_total
                              FROM chitietphieuxuat
                              WHERE `{$detailOrderIdCol}` IN ({$orderIdInClause})
                              GROUP BY `{$detailOrderIdCol}`";
            } else if ($detailQtyCol !== null && $detailPriceCol !== null) {
                $detailSql = "SELECT `{$detailOrderIdCol}` AS order_id, SUM(COALESCE(`{$detailQtyCol}`, 0) * COALESCE(`{$detailPriceCol}`, 0)) AS detail_total
                              FROM chitietphieuxuat
                              WHERE `{$detailOrderIdCol}` IN ({$orderIdInClause})
                              GROUP BY `{$detailOrderIdCol}`";
            } else {
                $detailSql = '';
            }

            if ($detailSql !== '') {
                $detailRs = $conn->query($detailSql);
                while ($detailRs && ($detailRow = $detailRs->fetch_assoc())) {
                    $detailOrderId = trim((string) ($detailRow['order_id'] ?? ''));
                    if ($detailOrderId === '') {
                        continue;
                    }

                    $detailTotalByOrder[$detailOrderId] = (float) ($detailRow['detail_total'] ?? 0);
                }
                if ($detailRs) $detailRs->free();
            }
        }

        foreach ($orderRows as $row) {
            $orderId = trim((string) ($row['order_id'] ?? ''));
            $orderTotal = (float) ($row['order_total'] ?? 0);
            if ($orderTotal <= 0 && $orderId !== '' && isset($detailTotalByOrder[$orderId])) {
                $orderTotal = (float) $detailTotalByOrder[$orderId];
            }

            $purchaseSummary['total_orders']++;

            $rawStatus = trim((string) ($row['order_status'] ?? ''));
            if (isReceivedStatus($rawStatus)) {
                $purchaseSummary['total_spent'] += max(0, $orderTotal);
            }

            if (count($recentOrders) < 5) {
                $recentOrders[] = [
                    'id' => (string) ($row['order_id'] ?? ''),
                    'date' => (string) ($row['order_date'] ?? ''),
                    'status' => trim((string) ($row['order_status'] ?? '')),
                    'total' => $orderTotal,
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản của tôi - ACK Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    body {
        background: #f5f7fb;
        font-family: 'Segoe UI', sans-serif;
    }

    .top-bar {
        background-color: #fff;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }

    .ack-logo {
        height: 40px;
        width: auto;
        object-fit: contain;
    }

    .search-box {
        position: relative;
        width: 100%;
    }

    .search-box input {
        border-radius: 20px;
        padding-right: 40px;
        background: #f1f1f1;
        border: none;
    }

    .search-box i {
        position: absolute;
        right: 15px;
        top: 10px;
        color: #666;
    }

    .location-select {
        border-radius: 20px;
        background: #eee;
        padding: 5px 15px;
        font-size: 0.9rem;
        border: none;
    }

    .main-nav {
        background-color: #007bff;
        color: white;
        padding: 0;
    }

    .main-nav .nav-link {
        color: white;
        padding: 10px 20px;
        font-weight: 500;
    }

    .main-nav .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.2);
    }

    .delivery-notice {
        font-size: 0.9rem;
        font-style: italic;
    }

    .account-page-container {
        padding-top: 10px;
        padding-bottom: 5px;
    }

    body.account-split-layout {
        overflow: hidden;
    }

    body.account-split-layout .account-page-container {
        height: calc(100vh - var(--account-header-offset, 130px));
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .account-page-heading {
        flex: 0 0 auto;
    }

    .account-layout-row {
        flex: 1 1 auto;
        min-height: 0;
    }

    body.account-split-layout .account-layout-row {
        overflow: hidden;
    }

    body.account-split-layout .account-layout-row>.account-left-col,
    body.account-split-layout .account-layout-row>.account-right-col {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    body.account-split-layout .account-layout-row>.account-right-col {
        overflow: hidden;
    }

    .account-right-scroll {
        min-height: 0;
    }

    body.account-split-layout .account-right-scroll {
        flex: 1 1 auto;
        height: 100%;
        max-height: 100%;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 0;
        padding-bottom: 5px;
        border-radius: 14px;
        box-sizing: border-box;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    body.account-split-layout .account-right-scroll .panel {
        overflow: hidden;
    }

    body.account-split-layout .account-right-scroll::-webkit-scrollbar {
        width: 0;
        height: 0;
        display: none;
    }

    @media (min-width: 768px) {
        .account-page-container {
            padding-bottom: 5px;
        }
    }

    @media (max-width: 991.98px) {
        body.account-split-layout {
            overflow: auto;
        }

        body.account-split-layout .account-page-container {
            height: auto;
            display: block;
            overflow: visible;
        }

        body.account-split-layout .account-right-scroll {
            overflow: visible;
            padding-right: 0;
        }
    }

    .panel {
        border: 1px solid #e8edf5;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 6px 20px rgba(0, 0, 0, .04);
    }

    .avatar-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff6d8;
        color: #f59f00;
        border: 1px solid #ffe7a5;
        font-size: 1.5rem;
    }

    .menu-link {
        border-radius: 10px;
        color: #334155;
        text-decoration: none;
        padding: 10px 12px;
        display: block;
    }

    .menu-link:hover {
        background: #f1f5ff;
        color: #0b74e5;
    }

    .menu-link.active {
        background: #eaf2ff;
        color: #0b74e5;
        font-weight: 600;
    }

    .mini-stat-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        background: #f8fbff;
    }

    .voucher-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        background: #eef4ff;
        color: #2952cc;
        font-size: 12px;
        font-weight: 600;
        margin: 4px 6px 0 0;
    }
    </style>
</head>

<body class="account-split-layout">
    <header class="sticky-top bg-white">
        <div class="top-bar">
            <div class="container d-flex align-items-center justify-content-between">
                <a href="trangchu.php" class="d-flex align-items-center text-decoration-none me-3">
                    <img src="../TrangUser/ack.png" alt="ACK Logo" class="ack-logo">
                </a>

                <div class="d-none d-md-block me-3">
                    <button class="location-select">
                        <i class="fas fa-map-marker-alt text-danger me-1"></i> Đồng Tháp <i
                            class="fas fa-caret-down ms-1"></i>
                    </button>
                </div>

                <div class="flex-grow-1 mx-3">
                    <div class="search-box">
                        <input type="text" class="form-control" placeholder="Tìm kiếm sản phẩm...">
                        <i class="fas fa-search"></i>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <a href="#" class="text-dark"><i class="fas fa-headset fa-lg"></i></a>
                    <a href="#" class="text-dark"><i class="fas fa-bell fa-lg"></i></a>
                    <a href="giohang.php" class="text-dark position-relative">
                        <i class="fas fa-shopping-basket"></i>
                        <span data-cart-count
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">0</span>
                    </a>
                    <a href="#" class="text-warning"><i class="fas fa-user-circle fa-2x"></i></a>
                </div>
            </div>
        </div>

        <div class="main-nav">
            <div class="container d-flex justify-content-between align-items-center">
                <ul class="nav">
                    <li class="nav-item"><a class="nav-link" href="trangchu.php">Sản phẩm</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Tin tức</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Tuyển dụng</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Chuyển nhượng</a></li>
                </ul>
                <div class="delivery-notice d-none d-md-block">
                    <i class="fas fa-truck-fast me-1"></i> Miễn phí giao hàng tại Đồng Tháp
                </div>
            </div>
        </div>
    </header>

    <div class="container account-page-container">
        <div class="d-flex align-items-center mb-3 account-page-heading">
            <h4 class="mb-0 fw-bold">Tài khoản người dùng</h4>
        </div>

        <?php if ($flashMessage !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flashType); ?>">
            <?php echo htmlspecialchars($flashMessage); ?></div>
        <?php endif; ?>

        <div class="row g-3 g-md-4 account-layout-row">
            <div class="col-lg-4 account-left-col">
                <div class="panel p-3 p-md-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="avatar-circle"><i class="fas fa-user"></i></span>
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($userName); ?></div>
                            <div class="text-muted small">
                                <?php echo htmlspecialchars($userEmail !== '' ? $userEmail : 'Chưa cập nhật email'); ?>
                            </div>
                            <div class="text-primary small fw-semibold"><?php echo htmlspecialchars($roleLabel); ?>
                            </div>
                        </div>
                    </div>

                    <a class="menu-link <?php echo $tab === 'info' ? 'active' : ''; ?>" href="tai-khoan.php?tab=info"><i
                            class="fas fa-id-card me-2"></i>Thông tin tài khoản</a>
                    <a class="menu-link <?php echo $tab === 'manage' ? 'active' : ''; ?>"
                        href="tai-khoan.php?tab=manage"><i class="fas fa-folder-tree me-2"></i>Quản lý</a>
                    <a class="menu-link" href="don-hang-cua-toi.php"><i class="fas fa-receipt me-2"></i>Đơn hàng của
                        tôi</a>
                    <a class="menu-link <?php echo $tab === 'settings' ? 'active' : ''; ?>"
                        href="tai-khoan.php?tab=settings"><i class="fas fa-gear me-2"></i>Cài đặt</a>
                    <hr>
                    <a class="menu-link text-danger" href="../Login/logout.php"><i
                            class="fas fa-right-from-bracket me-2"></i>Đăng xuất</a>
                </div>
            </div>

            <div class="col-lg-8 account-right-col">
                <div class="account-right-scroll">
                    <?php if ($tab === 'info'): ?>
                    <div class="panel p-3 p-md-4">
                        <h5 class="fw-bold mb-3">Thông tin tài khoản</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Mã người dùng</label>
                                <input class="form-control" value="<?php echo $userId; ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Vai trò</label>
                                <input class="form-control" value="<?php echo htmlspecialchars($roleLabel); ?>"
                                    readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Tên hiển thị</label>
                                <input class="form-control" value="<?php echo htmlspecialchars($userName); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Email</label>
                                <input class="form-control"
                                    value="<?php echo htmlspecialchars($userEmail !== '' ? $userEmail : 'Chưa cập nhật'); ?>"
                                    readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Số điện thoại</label>
                                <input class="form-control"
                                    value="<?php echo htmlspecialchars((string) ($customerProfile['phone'] ?? '')); ?>"
                                    readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Giới tính</label>
                                <input class="form-control"
                                    value="<?php echo htmlspecialchars((string) ($customerProfile['gender'] ?? '')); ?>"
                                    readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Số tài khoản</label>
                                <input class="form-control"
                                    value="<?php echo htmlspecialchars((string) ($customerProfile['bank'] ?? '')); ?>"
                                    readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Mã khách hàng</label>
                                <input class="form-control"
                                    value="<?php echo htmlspecialchars((string) ($customerProfile['id'] ?? '')); ?>"
                                    readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small">Địa chỉ</label>
                                <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars((string) ($customerProfile['address'] ?? '')); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($tab === 'manage'): ?>
                    <div class="panel p-3 p-md-4">
                        <h5 class="fw-bold mb-3">Quản lý tài khoản</h5>
                        <div class="list-group">
                            <a href="giohang.php"
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-cart-shopping me-2 text-primary"></i>Quản lý giỏ hàng</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <a href="drink-detail.php"
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Xem sản phẩm đã quan
                                    tâm</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <a href="don-hang-cua-toi.php"
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-receipt me-2 text-primary"></i>Theo dõi đơn hàng của tôi</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php if ($userRole === 'admin'): ?>
                            <a href="../TrangAdmin/admin.php"
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-shield me-2 text-primary"></i>Vào trang quản trị</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="mini-stat-card h-100">
                                    <h6 class="fw-bold mb-2"><i class="fas fa-ticket me-2 text-primary"></i>Nhận voucher
                                    </h6>
                                    <form method="post" class="mb-2">
                                        <input type="hidden" name="action" value="claim_voucher">
                                        <div class="input-group input-group-sm">
                                            <select class="form-select" name="voucher_id" required>
                                                <option value="">-- Chọn voucher để nhận --</option>
                                                <?php foreach ($availableVouchers as $voucher): ?>
                                                <option value="<?php echo (int) ($voucher['id_voucher'] ?? 0); ?>">
                                                    <?php echo htmlspecialchars(formatVoucherLabel($voucher)); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-primary" type="submit">Nhận</button>
                                        </div>
                                    </form>

                                    <?php if (count($availableVouchers) === 0): ?>
                                    <div class="small text-muted">Hiện không có voucher mới để nhận.</div>
                                    <?php endif; ?>

                                    <?php if (count($claimedVouchers) > 0): ?>
                                    <div class="small text-muted mt-2">Đã nhận gần đây:</div>
                                    <div>
                                        <?php foreach ($claimedVouchers as $claimed): ?>
                                        <span class="voucher-tag"><i
                                                class="fas fa-check-circle"></i><?php echo htmlspecialchars((string) ($claimed['ma_voucher'] ?? '')); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mini-stat-card h-100">
                                    <h6 class="fw-bold mb-2"><i class="fas fa-chart-line me-2 text-primary"></i>Tổng
                                        quan
                                        mua sắm</h6>
                                    <div class="small text-muted">Số đơn đã mua</div>
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo (int) ($purchaseSummary['total_orders'] ?? 0); ?> đơn</div>

                                    <div class="small text-muted">Tổng tiền đã bỏ ra</div>
                                    <div class="fs-5 fw-bold text-success mb-2">
                                        <?php echo number_format((float) ($purchaseSummary['total_spent'] ?? 0), 0, ',', '.'); ?>
                                        ₫</div>

                                    <?php if (count($recentOrders) > 0): ?>
                                    <div class="small text-muted mb-1">Đơn gần đây:</div>
                                    <ul class="small ps-3 mb-2">
                                        <?php foreach ($recentOrders as $order): ?>
                                        <li>
                                            <span
                                                class="fw-semibold"><?php echo htmlspecialchars((string) ($order['id'] ?? '')); ?></span>
                                            - <?php echo number_format((float) ($order['total'] ?? 0), 0, ',', '.'); ?>
                                            ₫
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>

                                    <a href="don-hang-cua-toi.php" class="btn btn-outline-primary btn-sm">Xem lại tất cả
                                        đơn
                                        đã mua</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="panel p-3 p-md-4">
                        <h5 class="fw-bold mb-3">Cài đặt tài khoản</h5>
                        <form method="post">
                            <input type="hidden" name="action" value="save_profile">
                            <div class="mb-3">
                                <label class="form-label">Tên hiển thị</label>
                                <input type="text" class="form-control" name="display_name"
                                    value="<?php echo htmlspecialchars($userName); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control"
                                    value="<?php echo htmlspecialchars($userEmail); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="phone"
                                    value="<?php echo htmlspecialchars((string) ($customerProfile['phone'] ?? '')); ?>"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Địa chỉ <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="address" rows="2" required><?php echo htmlspecialchars((string) ($customerProfile['address'] ?? '')); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Giới tính</label>
                                <select class="form-select" name="gender">
                                    <?php $currentGender = strtolower(trim((string) ($customerProfile['gender'] ?? ''))); ?>
                                    <option value="" <?php echo $currentGender === '' ? 'selected' : ''; ?>>Chưa chọn</option>
                                    <option value="Nam" <?php echo $currentGender === 'nam' ? 'selected' : ''; ?>>Nam</option>
                                    <option value="Nữ" <?php echo in_array($currentGender, ['nữ', 'nu'], true) ? 'selected' : ''; ?>>Nữ</option>
                                    <option value="Khác" <?php echo $currentGender === 'khác' ? 'selected' : ''; ?>>Khác</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Số tài khoản</label>
                                <input type="text" class="form-control" name="bank_account"
                                    value="<?php echo htmlspecialchars((string) ($customerProfile['bank'] ?? '')); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Lưu
                                thay
                                đổi</button>
                        </form>

                        <hr class="my-4">

                        <h6 class="fw-bold mb-3">Cài đặt hệ thống</h6>
                        <form method="post">
                            <input type="hidden" name="action" value="save_system_settings">
                            <div class="mb-3">
                                <label class="form-label">Ngôn ngữ</label>
                                <select class="form-select" name="language">
                                    <option value="vi" <?php echo $userLanguage === 'vi' ? 'selected' : ''; ?>>Tiếng
                                        Việt
                                    </option>
                                    <option value="en" <?php echo $userLanguage === 'en' ? 'selected' : ''; ?>>English
                                    </option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Theme</label>
                                <select class="form-select" name="theme">
                                    <option value="light" <?php echo $userTheme === 'light' ? 'selected' : ''; ?>>Light
                                    </option>
                                    <option value="dark" <?php echo $userTheme === 'dark' ? 'selected' : ''; ?>>Dark
                                    </option>
                                    <option value="system" <?php echo $userTheme === 'system' ? 'selected' : ''; ?>>Theo
                                        hệ
                                        thống</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-outline-primary"><i class="fas fa-sliders me-1"></i>Lưu
                                cài
                                đặt hệ thống</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        const body = document.body;
        if (!body || !body.classList.contains('account-split-layout')) {
            return;
        }

        const rightScroll = document.querySelector('.account-right-scroll');

        function updateRightScrollHeight() {
            if (!rightScroll) return;

            if (window.innerWidth <= 991) {
                rightScroll.style.height = '';
                rightScroll.style.maxHeight = '';
                return;
            }

            const rect = rightScroll.getBoundingClientRect();
            const available = Math.max(220, Math.floor(window.innerHeight - rect.top - 5));
            rightScroll.style.height = `${available}px`;
            rightScroll.style.maxHeight = `${available}px`;
        }

        function updateHeaderOffset() {
            const header = document.querySelector('header.sticky-top');
            const offset = header ? Math.ceil(header.getBoundingClientRect().height) : 130;
            body.style.setProperty('--account-header-offset', `${offset + 10}px`);
            updateRightScrollHeight();
        }

        updateHeaderOffset();
        window.addEventListener('resize', updateHeaderOffset);
        window.addEventListener('load', updateHeaderOffset);
    })();
    </script>
    <script src="web-events.js?v=20260412-3"></script>
</body>

</html>