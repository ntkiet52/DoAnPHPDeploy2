<?php
require_once __DIR__ . '/../Login/admin_auth.php';

function pickCustomerValue(array $row, array $keys, $default = '') {
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

function tableExists(PDO $pdo, string $table): bool {
    static $cache = [];

    $tableKey = strtolower($table);
    if (array_key_exists($tableKey, $cache)) {
        return $cache[$tableKey];
    }

    $tableEsc = str_replace('`', '``', $table);
    $stmt = $pdo->query("SHOW TABLES LIKE '{$tableEsc}'");
    $exists = $stmt !== false && $stmt->fetchColumn() !== false;
    $cache[$tableKey] = $exists;

    return $exists;
}

function resolveCustomerAccountEmail(PDO $pdo, string $customerId): ?string {
    if ($customerId === '') {
        return null;
    }

    $khColumns = getExistingColumns($pdo, 'khachhang');
    $khIdCol = pickExistingColumn($khColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);
    if ($khIdCol === null) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM khachhang WHERE `{$khIdCol}` = :id LIMIT 1");
    $stmt->execute([':id' => $customerId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $email = trim((string) pickCustomerValue(
        $row,
        ['masothue', 'ma_so_thue', 'tax_code', 'email', 'mail'],
        ''
    ));

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return null;
    }

    return strtolower($email);
}

function normalizeUserStatusValue(?string $raw): string {
    $status = strtolower(trim((string) $raw));
    if ($status === '') {
        return 'active';
    }

    return $status;
}

function resolveUserAccountMetaByEmail(PDO $pdo, string $email): array {
    if ($email === '' || !tableExists($pdo, 'users')) {
        return [
            'exists' => false,
            'status' => '',
            'email_col' => null,
            'status_col' => null,
        ];
    }

    $usersColumns = getExistingColumns($pdo, 'users');
    $userEmailCol = pickExistingColumn($usersColumns, ['email', 'mail', 'username', 'user_name']);
    $statusCol = pickExistingColumn($usersColumns, ['status', 'trangthai', 'trang_thai', 'state']);

    if ($userEmailCol === null || $statusCol === null) {
        return [
            'exists' => false,
            'status' => '',
            'email_col' => $userEmailCol,
            'status_col' => $statusCol,
        ];
    }

    $findStmt = $pdo->prepare("SELECT `{$statusCol}` AS account_status FROM users WHERE LOWER(`{$userEmailCol}`) = LOWER(:email) LIMIT 1");
    $findStmt->execute([':email' => $email]);
    $statusValue = $findStmt->fetchColumn();

    if ($statusValue === false) {
        return [
            'exists' => false,
            'status' => '',
            'email_col' => $userEmailCol,
            'status_col' => $statusCol,
        ];
    }

    return [
        'exists' => true,
        'status' => normalizeUserStatusValue((string) $statusValue),
        'email_col' => $userEmailCol,
        'status_col' => $statusCol,
    ];
}

function normalizeCustomerDate(?string $value): ?string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $raw);
        if ($dt instanceof DateTime && $dt->format($format) === $raw) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return null;
    }

    return date('Y-m-d', $ts);
}

function isCancelledOrderStatus(string $rawStatus): bool {
    $status = mb_strtolower(trim($rawStatus));
    if ($status === '') {
        return false;
    }

    return str_contains($status, 'hủy')
        || str_contains($status, 'huy')
        || str_contains($status, 'cancel')
        || str_contains($status, 'void');
}

function generateNextCustomerId(PDO $pdo): string {
    $rows = $pdo->query("SELECT MaKhachHang FROM khachhang")->fetchAll(PDO::FETCH_COLUMN);
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

    return 'KH' . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
}

$customers = [];
$dbError = '';
$crudMessage = '';
$crudError = '';

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $action = isset($_POST['crud_action']) ? trim((string) $_POST['crud_action']) : '';

            if ($action === 'add_customer') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $taxCode = trim((string) ($_POST['email'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $bankAccount = trim((string) ($_POST['birthday'] ?? ''));
                $address = trim((string) ($_POST['address'] ?? ''));
                $gender = trim((string) ($_POST['gender'] ?? ''));

                if ($name === '') {
                    $crudError = 'Vui lòng nhập tên khách hàng.';
                } else {
                    $columns = getExistingColumns($pdo, 'khachhang');

                    $idCol = pickExistingColumn($columns, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);
                    $nameCol = pickExistingColumn($columns, ['tenkhachhang', 'ten_khach_hang', 'hoten', 'ten', 'name']);
                    $genderCol = pickExistingColumn($columns, ['gioitinh', 'gioi_tinh', 'gender']);
                    $addressCol = pickExistingColumn($columns, ['diachi', 'dia_chi', 'address']);
                    $phoneCol = pickExistingColumn($columns, ['sdtkh', 'sdt', 'sodienthoai', 'so_dien_thoai', 'phone']);
                    $bankCol = pickExistingColumn($columns, ['sotaikhoankh', 'so_tai_khoan_kh', 'sotaikhoan', 'bank_account']);
                    $taxCol = pickExistingColumn($columns, ['masothue', 'ma_so_thue', 'tax_code']);

                    if ($idCol === null || $nameCol === null) {
                        $crudError = 'Không tìm thấy cột bắt buộc của bảng khách hàng (mã khách hàng/tên khách hàng).';
                    } else {
                        $newId = generateNextCustomerId($pdo);
                        $insertColumns = [$idCol, $nameCol];
                        $insertPlaceholders = [':id', ':name'];
                        $params = [
                            ':id' => $newId,
                            ':name' => $name,
                        ];

                        if ($genderCol !== null) {
                            $insertColumns[] = $genderCol;
                            $insertPlaceholders[] = ':gender';
                            $params[':gender'] = $gender;
                        }

                        if ($addressCol !== null) {
                            $insertColumns[] = $addressCol;
                            $insertPlaceholders[] = ':address';
                            $params[':address'] = $address;
                        }

                        if ($phoneCol !== null) {
                            $insertColumns[] = $phoneCol;
                            $insertPlaceholders[] = ':phone';
                            $params[':phone'] = $phone;
                        }

                        if ($bankCol !== null) {
                            $insertColumns[] = $bankCol;
                            $insertPlaceholders[] = ':bank';
                            $params[':bank'] = $bankAccount;
                        }

                        if ($taxCol !== null) {
                            $insertColumns[] = $taxCol;
                            $insertPlaceholders[] = ':tax';
                            $params[':tax'] = $taxCode;
                        }

                        $sql = 'INSERT INTO khachhang (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $crudMessage = 'Đã thêm khách hàng thành công.';
                    }
                }
            }

            if ($action === 'update_customer') {
                $id = trim((string) ($_POST['customer_id'] ?? ''));
                $name = trim((string) ($_POST['name'] ?? ''));
                $taxCode = trim((string) ($_POST['email'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $bankAccount = trim((string) ($_POST['birthday'] ?? ''));
                $address = trim((string) ($_POST['address'] ?? ''));
                $gender = trim((string) ($_POST['gender'] ?? ''));

                if ($id === '' || $name === '') {
                    $crudError = 'Dữ liệu cập nhật khách hàng chưa hợp lệ.';
                } else {
                    $columns = getExistingColumns($pdo, 'khachhang');

                    $idCol = pickExistingColumn($columns, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);
                    $nameCol = pickExistingColumn($columns, ['tenkhachhang', 'ten_khach_hang', 'hoten', 'ten', 'name']);
                    $genderCol = pickExistingColumn($columns, ['gioitinh', 'gioi_tinh', 'gender']);
                    $addressCol = pickExistingColumn($columns, ['diachi', 'dia_chi', 'address']);
                    $phoneCol = pickExistingColumn($columns, ['sdtkh', 'sdt', 'sodienthoai', 'so_dien_thoai', 'phone']);
                    $bankCol = pickExistingColumn($columns, ['sotaikhoankh', 'so_tai_khoan_kh', 'sotaikhoan', 'bank_account']);
                    $taxCol = pickExistingColumn($columns, ['masothue', 'ma_so_thue', 'tax_code']);

                    if ($idCol === null || $nameCol === null) {
                        $crudError = 'Không tìm thấy cột bắt buộc của bảng khách hàng để cập nhật.';
                    } else {
                        $setParts = ["{$nameCol} = :name"];
                        $params = [
                            ':id' => $id,
                            ':name' => $name,
                        ];

                        if ($genderCol !== null) {
                            $setParts[] = "{$genderCol} = :gender";
                            $params[':gender'] = $gender;
                        }

                        if ($addressCol !== null) {
                            $setParts[] = "{$addressCol} = :address";
                            $params[':address'] = $address;
                        }

                        if ($phoneCol !== null) {
                            $setParts[] = "{$phoneCol} = :phone";
                            $params[':phone'] = $phone;
                        }

                        if ($bankCol !== null) {
                            $setParts[] = "{$bankCol} = :bank";
                            $params[':bank'] = $bankAccount;
                        }

                        if ($taxCol !== null) {
                            $setParts[] = "{$taxCol} = :tax";
                            $params[':tax'] = $taxCode;
                        }

                        $sql = 'UPDATE khachhang SET ' . implode(', ', $setParts) . " WHERE {$idCol} = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $crudMessage = 'Đã cập nhật khách hàng thành công.';
                    }
                }
            }

            if ($action === 'delete_customer') {
                $id = trim((string) ($_POST['customer_id'] ?? ''));
                if ($id === '') {
                    $crudError = 'Không xác định được khách hàng để xóa.';
                } else {
                    $khColumns = getExistingColumns($pdo, 'khachhang');
                    $khIdCol = pickExistingColumn($khColumns, ['makhachhang', 'ma_khach_hang', 'makh', 'id']);

                    if ($khIdCol === null) {
                        $crudError = 'Không tìm thấy cột mã khách hàng để xóa.';
                    } else {
                        $accountEmail = resolveCustomerAccountEmail($pdo, $id);
                        $usersColumns = tableExists($pdo, 'users') ? getExistingColumns($pdo, 'users') : [];
                        $userEmailCol = !empty($usersColumns)
                            ? pickExistingColumn($usersColumns, ['email', 'mail', 'username', 'user_name'])
                            : null;
                        $userIdCol = !empty($usersColumns)
                            ? pickExistingColumn($usersColumns, ['id', 'user_id', 'userid'])
                            : null;

                        $pdo->beginTransaction();

                        try {
                            if ($accountEmail !== null && $userEmailCol !== null) {
                                $resolvedUserId = null;

                                if ($userIdCol !== null) {
                                    $findUserStmt = $pdo->prepare(
                                        "SELECT `{$userIdCol}` FROM users WHERE LOWER(`{$userEmailCol}`) = LOWER(:email) LIMIT 1"
                                    );
                                    $findUserStmt->execute([':email' => $accountEmail]);
                                    $resolvedUserId = $findUserStmt->fetchColumn();
                                }

                                if ($resolvedUserId !== false && $resolvedUserId !== null && tableExists($pdo, 'login_history')) {
                                    $loginHistoryColumns = getExistingColumns($pdo, 'login_history');
                                    $historyUserIdCol = pickExistingColumn($loginHistoryColumns, ['user_id', 'userid', 'id_user']);
                                    if ($historyUserIdCol !== null) {
                                        $historyDeleteStmt = $pdo->prepare("DELETE FROM login_history WHERE `{$historyUserIdCol}` = :user_id");
                                        $historyDeleteStmt->execute([':user_id' => $resolvedUserId]);
                                    }
                                }

                                $deleteUserStmt = $pdo->prepare("DELETE FROM users WHERE LOWER(`{$userEmailCol}`) = LOWER(:email)");
                                $deleteUserStmt->execute([':email' => $accountEmail]);
                            }

                            $deleteCustomerStmt = $pdo->prepare("DELETE FROM khachhang WHERE `{$khIdCol}` = :id");
                            $deleteCustomerStmt->execute([':id' => $id]);

                            $pdo->commit();

                            if ($accountEmail !== null) {
                                $crudMessage = 'Đã xóa vĩnh viễn khách hàng và tài khoản đăng nhập liên kết.';
                            } else {
                                $crudMessage = 'Đã xóa vĩnh viễn khách hàng. Không tìm thấy email tài khoản đăng nhập liên kết.';
                            }
                        } catch (Throwable $deleteError) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            throw $deleteError;
                        }
                    }
                }
            }

            if ($action === 'lock_customer_account') {
                $id = trim((string) ($_POST['customer_id'] ?? ''));

                if ($id === '') {
                    $crudError = 'Không xác định được khách hàng để khóa tài khoản.';
                } else {
                    if (!tableExists($pdo, 'users')) {
                        $crudError = 'Không tìm thấy bảng users để khóa tài khoản.';
                    } else {
                        $accountEmail = resolveCustomerAccountEmail($pdo, $id);
                        if ($accountEmail === null) {
                            $crudError = 'Khách hàng này chưa có email đăng nhập hợp lệ để khóa tài khoản.';
                        } else {
                            $accountMeta = resolveUserAccountMetaByEmail($pdo, $accountEmail);
                            $userEmailCol = $accountMeta['email_col'] ?? null;
                            $statusCol = $accountMeta['status_col'] ?? null;

                            if ($userEmailCol === null || $statusCol === null) {
                                $crudError = 'Thiếu cột email/status trong bảng users nên chưa thể khóa tài khoản.';
                            } else if (($accountMeta['exists'] ?? false) !== true) {
                                $crudError = 'Không tìm thấy tài khoản đăng nhập liên kết để khóa.';
                            } else if (($accountMeta['status'] ?? '') === 'inactive') {
                                $crudMessage = 'Tài khoản này đã ở trạng thái khóa trước đó.';
                            } else {
                                $usersColumns = getExistingColumns($pdo, 'users');
                                $setParts = ["`{$statusCol}` = :status"];
                                $params = [
                                    ':status' => 'inactive',
                                    ':email' => $accountEmail,
                                ];

                                if (in_array('updated_at', $usersColumns, true)) {
                                    $setParts[] = 'updated_at = NOW()';
                                }

                                $lockStmt = $pdo->prepare(
                                    'UPDATE users SET ' . implode(', ', $setParts) . " WHERE LOWER(`{$userEmailCol}`) = LOWER(:email)"
                                );
                                $lockStmt->execute($params);
                                $crudMessage = 'Đã khóa tài khoản khách hàng thành công.';
                            }
                        }
                    }
                }
            }

            if ($action === 'unlock_customer_account') {
                $id = trim((string) ($_POST['customer_id'] ?? ''));

                if ($id === '') {
                    $crudError = 'Không xác định được khách hàng để mở khóa tài khoản.';
                } else {
                    if (!tableExists($pdo, 'users')) {
                        $crudError = 'Không tìm thấy bảng users để mở khóa tài khoản.';
                    } else {
                        $accountEmail = resolveCustomerAccountEmail($pdo, $id);
                        if ($accountEmail === null) {
                            $crudError = 'Khách hàng này chưa có email đăng nhập hợp lệ để mở khóa.';
                        } else {
                            $accountMeta = resolveUserAccountMetaByEmail($pdo, $accountEmail);
                            $userEmailCol = $accountMeta['email_col'] ?? null;
                            $statusCol = $accountMeta['status_col'] ?? null;

                            if ($userEmailCol === null || $statusCol === null) {
                                $crudError = 'Thiếu cột email/status trong bảng users nên chưa thể mở khóa tài khoản.';
                            } else if (($accountMeta['exists'] ?? false) !== true) {
                                $crudError = 'Không tìm thấy tài khoản đăng nhập liên kết để mở khóa.';
                            } else if (($accountMeta['status'] ?? '') !== 'inactive') {
                                $crudMessage = 'Tài khoản này đang hoạt động, không cần mở khóa.';
                            } else {
                                $usersColumns = getExistingColumns($pdo, 'users');
                                $setParts = ["`{$statusCol}` = :status"];
                                $params = [
                                    ':status' => 'active',
                                    ':email' => $accountEmail,
                                ];

                                if (in_array('updated_at', $usersColumns, true)) {
                                    $setParts[] = 'updated_at = NOW()';
                                }

                                $unlockStmt = $pdo->prepare(
                                    'UPDATE users SET ' . implode(', ', $setParts) . " WHERE LOWER(`{$userEmailCol}`) = LOWER(:email)"
                                );
                                $unlockStmt->execute($params);
                                $crudMessage = 'Đã mở khóa tài khoản khách hàng thành công.';
                            }
                        }
                    }
                }
            }
        } catch (Throwable $postError) {
            $crudError = $postError->getMessage();
        }
    }

    $totalByCustomer = [];
    $orderCountByCustomer = [];
    try {
        $phieuXuatColumns = getExistingColumns($pdo, 'phieuxuat');
        $pxOrderIdCol = pickExistingColumn($phieuXuatColumns, ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id']);
        $pxCustomerCol = pickExistingColumn($phieuXuatColumns, ['makhachhang', 'ma_khach_hang', 'makh']);
        $pxStatusCol = pickExistingColumn($phieuXuatColumns, ['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu']);

        $detailTotalByOrder = [];
        try {
            $detailColumns = getExistingColumns($pdo, 'chitietphieuxuat');
            $detailOrderIdCol = pickExistingColumn($detailColumns, ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id']);

            if ($detailOrderIdCol !== null) {
                $detailRows = $pdo->query("SELECT * FROM chitietphieuxuat")->fetchAll();
                foreach ($detailRows as $detailRow) {
                    $orderId = (string) pickCustomerValue($detailRow, [$detailOrderIdCol], '');
                    if ($orderId === '') {
                        continue;
                    }

                    $lineTotal = (float) pickCustomerValue($detailRow, ['thanhtien', 'thanh_tien', 'tongtien', 'tong_tien', 'thanhtienpx', 'thanh_tien_px'], 0);
                    if ($lineTotal <= 0) {
                        $quantity = (float) pickCustomerValue($detailRow, ['soluong', 'so_luong', 'soluongpx', 'so_luong_px'], 0);
                        $unitPrice = (float) pickCustomerValue($detailRow, ['giaxuat', 'gia_xuat', 'dongia', 'don_gia', 'gia', 'giaban', 'gia_ban'], 0);
                        $lineTotal = $quantity * $unitPrice;
                    }

                    if (!isset($detailTotalByOrder[$orderId])) {
                        $detailTotalByOrder[$orderId] = 0;
                    }
                    $detailTotalByOrder[$orderId] += $lineTotal;
                }
            }
        } catch (Throwable $ignored) {
        }

        $pxRows = $pdo->query("SELECT * FROM phieuxuat")->fetchAll();
        foreach ($pxRows as $px) {
            $maKh = $pxCustomerCol !== null
                ? (string) pickCustomerValue($px, [$pxCustomerCol], '')
                : (string) pickCustomerValue($px, ['makhachhang', 'ma_khach_hang', 'makh'], '');

            $statusRaw = $pxStatusCol !== null
                ? (string) pickCustomerValue($px, [$pxStatusCol], '')
                : (string) pickCustomerValue($px, ['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu'], '');

            if (isCancelledOrderStatus($statusRaw)) {
                continue;
            }

            $orderId = $pxOrderIdCol !== null
                ? (string) pickCustomerValue($px, [$pxOrderIdCol], '')
                : (string) pickCustomerValue($px, ['maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'idphieuxuat', 'id_phieu_xuat', 'madon', 'id'], '');

            $tongTien = (float) pickCustomerValue($px, ['tongtien', 'tong_tien', 'thanhtien', 'thanh_tien', 'total'], 0);
            if ($tongTien <= 0 && $orderId !== '' && isset($detailTotalByOrder[$orderId])) {
                $tongTien = (float) $detailTotalByOrder[$orderId];
            }

            if ($maKh !== '') {
                if (!isset($totalByCustomer[$maKh])) {
                    $totalByCustomer[$maKh] = 0;
                }
                $totalByCustomer[$maKh] += $tongTien;

                if ($tongTien > 0) {
                    if (!isset($orderCountByCustomer[$maKh])) {
                        $orderCountByCustomer[$maKh] = 0;
                    }
                    $orderCountByCustomer[$maKh]++;
                }
            }
        }
    } catch (Throwable $ignored) {
    }

    $userAccountStatusByEmail = [];
    $usersColumns = tableExists($pdo, 'users') ? getExistingColumns($pdo, 'users') : [];
    $userEmailCol = !empty($usersColumns)
        ? pickExistingColumn($usersColumns, ['email', 'mail', 'username', 'user_name'])
        : null;
    $userStatusCol = !empty($usersColumns)
        ? pickExistingColumn($usersColumns, ['status', 'trangthai', 'trang_thai', 'state'])
        : null;

    if ($userEmailCol !== null && $userStatusCol !== null) {
        $userRows = $pdo->query('SELECT * FROM users')->fetchAll();
        foreach ($userRows as $userRow) {
            $emailKey = strtolower(trim((string) pickCustomerValue($userRow, [$userEmailCol], '')));
            if ($emailKey === '' || filter_var($emailKey, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $statusValue = normalizeUserStatusValue((string) pickCustomerValue($userRow, [$userStatusCol], 'active'));
            $userAccountStatusByEmail[$emailKey] = $statusValue;
        }
    }

    $rows = $pdo->query("SELECT * FROM khachhang")->fetchAll();
    $customersWithPhone = 0;
    $customersWithOrders = 0;
    
    foreach ($rows as $row) {
        $maKh = (string) pickCustomerValue($row, ['makhachhang', 'ma_khach_hang', 'makh', 'id'], '');
        $name = (string) pickCustomerValue($row, ['tenkhachhang', 'ten_khach_hang', 'hoten', 'ten', 'name'], '');
        $taxCode = (string) pickCustomerValue($row, ['masothue', 'ma_so_thue', 'tax_code', 'email', 'mail'], '');
        $phone = (string) pickCustomerValue($row, ['sdtkh', 'sdt', 'sodienthoai', 'so_dien_thoai', 'phone'], '');
        $total = isset($totalByCustomer[$maKh]) ? (float) $totalByCustomer[$maKh] : 0;
        $normalizedEmail = strtolower(trim($taxCode));
        $hasLoginAccount = $normalizedEmail !== ''
            && filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) !== false
            && isset($userAccountStatusByEmail[$normalizedEmail]);
        $accountStatus = $hasLoginAccount
            ? (string) $userAccountStatusByEmail[$normalizedEmail]
            : 'no_account';
        $accountStatusText = $accountStatus === 'inactive'
            ? 'Đã khóa'
            : ($accountStatus === 'active' ? 'Đang hoạt động' : 'Chưa liên kết');

        if (!empty($phone)) {
            $customersWithPhone++;
        }
        if ($total > 0) {
            $customersWithOrders++;
        }

        $customers[] = [
            'id' => $maKh,
            'name' => $name,
            'gender' => (string) pickCustomerValue($row, ['gioitinh', 'gioi_tinh', 'gender'], ''),
            'email' => $taxCode,
            'phone' => $phone,
            'birthday' => (string) pickCustomerValue($row, ['sotaikhoankh', 'so_tai_khoan_kh', 'ngaysinh', 'ngay_sinh', 'birthday', 'dob'], ''),
            'address' => (string) pickCustomerValue($row, ['diachi', 'dia_chi', 'address'], ''),
            'orders' => (int) ($orderCountByCustomer[$maKh] ?? 0),
            'total_number' => $total,
            'total' => number_format($total, 0, ',', '.') . ' đ',
            'account_status' => $accountStatus,
            'account_status_text' => $accountStatusText,
            'has_login_account' => $hasLoginAccount,
        ];
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
    <title>ACK Admin - Quản lý khách hàng</title>
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
        height: 100vh;
        overflow: hidden;
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
        margin-left: calc(var(--sidebar-width) + 20px);
        padding: 20px 20px;
        height: 100vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .customer-table-shell {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    /* --- SEARCH BAR --- */
    .search-input {
        border-radius: 20px;
        padding: 5px 20px;
        border: 1px solid #ddd;
        width: 250px;
    }

    /* --- TABLE --- */
    .table-container {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        margin-top: 8px;
    }

    .table-header {
        background-color: #ffffff;
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f0f2f5;
    }

    .customer-table-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow: auto;
    }

    .table thead th {
        background: #F8F9FA;
        color: #344767;
        font-weight: 700;
        border-bottom: 1px solid #eee;
        padding: 14px 16px;
        position: sticky;
        top: 0;
        z-index: 6;
    }

    .table tbody td {
        padding: 14px 16px;
        vertical-align: middle;
        color: #344767;
        border-bottom: 1px solid #f0f2f5;
    }

    .table tbody tr.customer-row {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .table tbody tr.customer-row:hover {
        background-color: #f8f9fa;
    }

    .table tbody tr.customer-row.selected {
        background-color: #cce3ff;
        font-weight: 500;
        border-left: 4px solid #007bff;
        box-shadow: inset 0 0 8px rgba(0, 123, 255, 0.1);
    }

    .table tbody tr.customer-row.selected td:first-child {
        padding-left: 12px;
    }

    .btn-view-detail {
        background-color: #cfe2ff;
        color: #004085;
        border: none;
        border-radius: 10px;
        padding: 8px 25px;
        font-size: 0.85rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .btn-view-detail:hover {
        background-color: #b6d4fe;
    }

    .detail-item {
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 10px 14px;
        height: 100%;
    }

    .detail-label {
        display: block;
        font-size: 0.8rem;
        color: #67748e;
        margin-bottom: 4px;
        font-weight: 600;
    }

    .detail-value {
        font-size: 0.95rem;
        color: #344767;
        font-weight: 600;
        word-break: break-word;
    }

    /* --- STAT CARD --- */
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        height: 100%;
        display: flex;
        align-items: center;
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
        margin-right: 15px;
        flex-shrink: 0;
    }

    .bg-gradient-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
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
        color: #344767;
        margin-bottom: 0;
        line-height: 1.2;
    }

    .stat-label {
        font-size: 0.9rem;
        color: #67748e;
        margin-bottom: 4px;
    }

    .stat-growth {
        font-size: 0.8rem;
        font-weight: bold;
        color: #10b981;
        margin-left: auto;
        white-space: nowrap;
        text-align: right;
    }

    .screen-notice-wrap {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 1100;
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: min(360px, calc(100vw - 24px));
        pointer-events: none;
    }

    .screen-notice-item {
        pointer-events: auto;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16);
        border-radius: 10px;
        border: 0;
        overflow: hidden;
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
            <a href="admin-chucvu.php" class="nav-item"><i class="fas fa-user-tag"></i> Chức vụ</a>
            <a href="admin-donhang.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Đơn hàng</a>
            <a href="admin-nhanvien.php" class="nav-item"><i class="fas fa-user-tie"></i> Nhân viên</a>
            <a href="admin-khachhang.php" class="nav-item active"><i class="fas fa-users"></i> Khách hàng</a>
            <a href="admin-voucher.php" class="nav-item"><i class="fas fa-ticket-alt"></i> Voucher</a>
            <a href="admin-caidat.php" class="nav-item"><i class="fas fa-cog"></i> Cài đặt</a>
        </nav>
        <a href="../Login/logout.php" class="logout-btn">Đăng xuất</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-primary">Bảng điều khiển Admin</h4>
            <button class="btn btn-light rounded-circle shadow-sm"><i class="fas fa-times"></i></button>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">Quản lý khách hàng</h3>
            <input type="text" class="search-input" placeholder="Tìm kiếm">
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center">
                    <div class="icon-box bg-gradient-primary me-3"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="stat-label">Tổng khách hàng</div>
                        <h4 class="stat-value"><?php echo count($customers); ?></h4>
                    </div>
                    <div class="stat-growth">Tất cả</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center">
                    <div class="icon-box bg-gradient-success me-3"><i class="fas fa-phone"></i></div>
                    <div>
                        <div class="stat-label">Có số điện thoại</div>
                        <h4 class="stat-value"><?php echo $customersWithPhone; ?></h4>
                    </div>
                    <div class="stat-growth">Liên hệ</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center">
                    <div class="icon-box bg-gradient-warning me-3"><i class="fas fa-shopping-bag"></i></div>
                    <div>
                        <div class="stat-label">Đã mua hàng</div>
                        <h4 class="stat-value"><?php echo $customersWithOrders; ?></h4>
                    </div>
                    <div class="stat-growth">Đơn hàng</div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <button class="btn btn-info fw-semibold text-white" id="btnViewCustomer" disabled>
                <i class="fas fa-eye me-1"></i> Xem chi tiết
            </button>
            <button class="btn btn-warning fw-semibold text-dark" id="btnLockCustomer" disabled>
                <i class="fas fa-lock me-1"></i> Khóa tài khoản
            </button>
            <button class="btn btn-success fw-semibold" id="btnUnlockCustomer" disabled>
                <i class="fas fa-unlock me-1"></i> Mở khóa tài khoản
            </button>
            <button class="btn btn-danger fw-semibold" id="btnDeleteCustomer" disabled>
                <i class="fas fa-trash me-1"></i> Xóa vĩnh viễn
            </button>
        </div>

        <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addCustomerModalLabel">Thêm khách hàng mới</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="addCustomerForm" method="post">
                        <input type="hidden" name="crud_action" value="add_customer">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="customerName" class="form-label">Tên khách hàng</label>
                                    <input type="text" class="form-control" id="customerName" placeholder="Nhập họ tên"
                                        name="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="customerEmail" class="form-label">Mã số thuế</label>
                                    <input type="text" class="form-control" id="customerEmail" placeholder="VD: MST01"
                                        name="email">
                                </div>
                                <div class="col-md-6">
                                    <label for="customerGender" class="form-label">Giới tính</label>
                                    <select class="form-select" id="customerGender" name="gender">
                                        <option value="">Chưa chọn</option>
                                        <option value="Nam">Nam</option>
                                        <option value="Nữ">Nữ</option>
                                        <option value="Khác">Khác</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="customerPhone" class="form-label">Số điện thoại</label>
                                    <input type="text" class="form-control" id="customerPhone" name="phone"
                                        placeholder="0xxxxxxxxx">
                                </div>
                                <div class="col-md-6">
                                    <label for="customerBirthday" class="form-label">Số tài khoản</label>
                                    <input type="text" class="form-control" id="customerBirthday" name="birthday">
                                </div>
                                <div class="col-12">
                                    <label for="customerAddress" class="form-label">Địa chỉ</label>
                                    <textarea class="form-control" id="customerAddress" rows="3"
                                        placeholder="Nhập địa chỉ khách hàng" name="address"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="submit" class="btn btn-primary">Lưu khách hàng</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="customerDetailModal" tabindex="-1" aria-labelledby="customerDetailModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="customerDetailModalLabel">Chi tiết khách hàng</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Mã khách hàng</span>
                                    <span class="detail-value" id="detailCustomerId">--</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Họ và tên</span>
                                    <span class="detail-value" id="detailCustomerName">--</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Giới tính</span>
                                    <span class="detail-value" id="detailCustomerGender">--</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Mã số thuế</span>
                                    <span class="detail-value" id="detailCustomerEmail">--</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Số điện thoại</span>
                                    <span class="detail-value" id="detailCustomerPhone">--</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Số tài khoản</span>
                                    <span class="detail-value" id="detailCustomerBirthday">--</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Tổng chi tiêu</span>
                                    <span class="detail-value" id="detailCustomerTotal">--</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="detail-item">
                                    <span class="detail-label">Địa chỉ</span>
                                    <span class="detail-value" id="detailCustomerAddress">--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="editCustomerModalLabel">Sửa khách hàng</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="editCustomerForm" method="post">
                        <input type="hidden" name="crud_action" value="update_customer">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="editCustomerId" class="form-label">Mã khách hàng</label>
                                    <input type="text" class="form-control" id="editCustomerId" name="customer_id"
                                        readonly>
                                </div>
                                <div class="col-md-6">
                                    <label for="editCustomerName" class="form-label">Tên khách hàng</label>
                                    <input type="text" class="form-control" id="editCustomerName" name="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="editCustomerGender" class="form-label">Giới tính</label>
                                    <select class="form-select" id="editCustomerGender" name="gender">
                                        <option value="">Chưa chọn</option>
                                        <option value="Nam">Nam</option>
                                        <option value="Nữ">Nữ</option>
                                        <option value="Khác">Khác</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="editCustomerEmail" class="form-label">Mã số thuế</label>
                                    <input type="text" class="form-control" id="editCustomerEmail" name="email">
                                </div>
                                <div class="col-md-6">
                                    <label for="editCustomerPhone" class="form-label">Số điện thoại</label>
                                    <input type="text" class="form-control" id="editCustomerPhone" name="phone">
                                </div>
                                <div class="col-md-6">
                                    <label for="editCustomerBirthday" class="form-label">Số tài khoản</label>
                                    <input type="text" class="form-control" id="editCustomerBirthday" name="birthday">
                                </div>
                                <div class="col-md-6">
                                    <label for="editCustomerTotal" class="form-label">Tổng chi tiêu</label>
                                    <input type="text" class="form-control" id="editCustomerTotal" readonly>
                                </div>
                                <div class="col-12">
                                    <label for="editCustomerAddress" class="form-label">Địa chỉ</label>
                                    <textarea class="form-control" id="editCustomerAddress" rows="3"
                                        name="address"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteCustomerModal" tabindex="-1" aria-labelledby="deleteCustomerModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteCustomerModalLabel">Xác nhận xóa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Bạn có chắc chắn muốn xóa vĩnh viễn khách hàng <strong id="deleteCustomerName"></strong>?
                        <br><span class="text-danger">Tài khoản đăng nhập liên kết cũng sẽ bị xóa và không thể đăng nhập
                            lại bằng tài khoản đó.</span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="button" class="btn btn-danger" id="btnConfirmDeleteCustomer">Xóa vĩnh
                            viễn</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="lockCustomerModal" tabindex="-1" aria-labelledby="lockCustomerModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="lockCustomerModalLabel">Xác nhận khóa tài khoản</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Bạn có chắc chắn muốn khóa tài khoản đăng nhập của khách hàng <strong
                            id="lockCustomerName"></strong> không?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="button" class="btn btn-warning" id="btnConfirmLockCustomer">Khóa tài
                            khoản</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="unlockCustomerModal" tabindex="-1" aria-labelledby="unlockCustomerModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="unlockCustomerModalLabel">Xác nhận mở khóa tài khoản</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Bạn có chắc chắn muốn mở khóa tài khoản đăng nhập của khách hàng <strong
                            id="unlockCustomerName"></strong> không?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="button" class="btn btn-success" id="btnConfirmUnlockCustomer">Mở khóa tài
                            khoản</button>
                    </div>
                </div>
            </div>
        </div>

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

        <form method="post" id="deleteCustomerForm" class="d-none">
            <input type="hidden" name="crud_action" value="delete_customer">
            <input type="hidden" name="customer_id" id="deleteCustomerId">
        </form>

        <form method="post" id="lockCustomerForm" class="d-none">
            <input type="hidden" name="crud_action" value="lock_customer_account">
            <input type="hidden" name="customer_id" id="lockCustomerId">
        </form>

        <form method="post" id="unlockCustomerForm" class="d-none">
            <input type="hidden" name="crud_action" value="unlock_customer_account">
            <input type="hidden" name="customer_id" id="unlockCustomerId">
        </form>

        <div class="table-container customer-table-shell">
            <div class="table-header">
                <h5 class="fw-bold mb-0">Danh sách khách hàng</h5>
                <i class="fas fa-download text-muted"></i>
            </div>
            <div class="customer-table-scroll">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Mã KH</th>
                            <th>Tên khách hàng</th>
                            <th>Giới tính</th>
                            <th>Mã số thuế</th>
                            <th>SĐT</th>
                            <th>Số tài khoản</th>
                            <th class="text-end">Số đơn</th>
                            <th class="text-end">Tổng chi tiêu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($customers) === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Chưa có khách hàng nào.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach($customers as $c): ?>
                        <tr class="customer-row"
                            data-customer-id="<?php echo htmlspecialchars($c['id'] ?? '', ENT_QUOTES); ?>"
                            data-customer-name="<?php echo htmlspecialchars($c['name'] ?? '', ENT_QUOTES); ?>"
                            data-customer-gender="<?php echo htmlspecialchars($c['gender'] ?? '', ENT_QUOTES); ?>"
                            data-customer-email="<?php echo htmlspecialchars($c['email'] ?? '', ENT_QUOTES); ?>"
                            data-customer-phone="<?php echo htmlspecialchars($c['phone'] ?? '', ENT_QUOTES); ?>"
                            data-customer-birthday="<?php echo htmlspecialchars($c['birthday'] ?? '', ENT_QUOTES); ?>"
                            data-customer-address="<?php echo htmlspecialchars($c['address'] ?? '', ENT_QUOTES); ?>"
                            data-customer-orders="<?php echo (int) ($c['orders'] ?? 0); ?>"
                            data-customer-total="<?php echo htmlspecialchars($c['total'] ?? '', ENT_QUOTES); ?>"
                            data-customer-account-status="<?php echo htmlspecialchars($c['account_status'] ?? 'no_account', ENT_QUOTES); ?>"
                            data-customer-has-account="<?php echo !empty($c['has_login_account']) ? '1' : '0'; ?>">
                            <td class="fw-bold"><?php echo htmlspecialchars((string) ($c['id'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($c['name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($c['gender'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($c['email'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($c['phone'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($c['birthday'] ?? '')); ?></td>
                            <td class="text-end"><?php echo (int) ($c['orders'] ?? 0); ?></td>
                            <td class="text-end fw-bold">
                                <?php echo htmlspecialchars((string) ($c['total'] ?? '0 đ')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div id="screenNoticeWrap" class="screen-notice-wrap" aria-live="polite" aria-atomic="true"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let selectedCustomerRow = null;
    let pendingDeleteCustomerRow = null;
    let pendingLockCustomerRow = null;
    let pendingUnlockCustomerRow = null;

    const btnEditCustomer = document.getElementById('btnEditCustomer');
    const btnViewCustomer = document.getElementById('btnViewCustomer');
    const btnLockCustomer = document.getElementById('btnLockCustomer');
    const btnUnlockCustomer = document.getElementById('btnUnlockCustomer');
    const btnDeleteCustomer = document.getElementById('btnDeleteCustomer');
    const screenNoticeWrap = document.getElementById('screenNoticeWrap');

    function showScreenNotice(message, type = 'warning', timeout = 2600) {
        if (!screenNoticeWrap || !message) {
            return;
        }

        const toastEl = document.createElement('div');
        toastEl.className = `alert alert-${type} alert-dismissible fade show screen-notice-item mb-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = `
            <div>${message}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        screenNoticeWrap.appendChild(toastEl);

        window.setTimeout(() => {
            if (toastEl.parentElement) {
                toastEl.classList.remove('show');
                toastEl.classList.add('hide');
                window.setTimeout(() => toastEl.remove(), 220);
            }
        }, timeout);
    }

    function getSelectedAccountStatus() {
        if (!selectedCustomerRow) {
            return {
                hasAccount: false,
                status: 'no_account',
            };
        }

        const hasAccount = (selectedCustomerRow.getAttribute('data-customer-has-account') || '0') === '1';
        const status = (selectedCustomerRow.getAttribute('data-customer-account-status') || 'no_account').toLowerCase();

        return {
            hasAccount,
            status,
        };
    }

    function syncCustomerActionButtons() {
        const hasSelected = !!selectedCustomerRow;
        if (btnEditCustomer) {
            btnEditCustomer.disabled = !hasSelected;
        }
        if (btnViewCustomer) {
            btnViewCustomer.disabled = !hasSelected;
        }
        if (btnLockCustomer) {
            btnLockCustomer.disabled = !hasSelected;
        }
        if (btnUnlockCustomer) {
            btnUnlockCustomer.disabled = !hasSelected;
        }
        if (btnDeleteCustomer) {
            btnDeleteCustomer.disabled = !hasSelected;
        }

        if (hasSelected) {
            const accountMeta = getSelectedAccountStatus();
            if (btnLockCustomer) {
                btnLockCustomer.disabled = !accountMeta.hasAccount || accountMeta.status === 'inactive';
            }
            if (btnUnlockCustomer) {
                btnUnlockCustomer.disabled = !accountMeta.hasAccount || accountMeta.status !== 'inactive';
            }
        }
    }

    function clearCustomerSelection() {
        document.querySelectorAll('.customer-row').forEach(row => row.classList.remove('selected'));
        selectedCustomerRow = null;
        syncCustomerActionButtons();
    }

    function bindCustomerRowEvents(row) {
        row.addEventListener('click', function() {
            document.querySelectorAll('.customer-row').forEach(item => item.classList.remove('selected'));
            row.classList.add('selected');
            selectedCustomerRow = row;
            syncCustomerActionButtons();
        });
    }

    document.querySelectorAll('.customer-row').forEach(bindCustomerRowEvents);

    if (btnEditCustomer) {
        btnEditCustomer.addEventListener('click', function() {
            if (!selectedCustomerRow) {
                showScreenNotice('Vui lòng chọn khách hàng để sửa.', 'warning');
                return;
            }

            document.getElementById('editCustomerId').value = selectedCustomerRow.getAttribute(
                'data-customer-id') || '';
            document.getElementById('editCustomerName').value = selectedCustomerRow.getAttribute(
                'data-customer-name') || '';
            document.getElementById('editCustomerGender').value = selectedCustomerRow.getAttribute(
                'data-customer-gender') || '';
            document.getElementById('editCustomerEmail').value = selectedCustomerRow.getAttribute(
                'data-customer-email') || '';
            document.getElementById('editCustomerPhone').value = selectedCustomerRow.getAttribute(
                'data-customer-phone') || '';
            document.getElementById('editCustomerBirthday').value = selectedCustomerRow.getAttribute(
                'data-customer-birthday') || '';
            document.getElementById('editCustomerAddress').value = selectedCustomerRow.getAttribute(
                'data-customer-address') || '';
            document.getElementById('editCustomerTotal').value = selectedCustomerRow.getAttribute(
                'data-customer-total') || '0 đ';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('editCustomerModal')).show();
        });
    }

    btnViewCustomer.addEventListener('click', function() {
        if (!selectedCustomerRow) {
            showScreenNotice('Vui lòng chọn khách hàng để xem chi tiết.', 'warning');
            return;
        }

        document.getElementById('detailCustomerId').textContent = selectedCustomerRow.getAttribute(
                'data-customer-id') ||
            '--';
        document.getElementById('detailCustomerName').textContent = selectedCustomerRow.getAttribute(
            'data-customer-name') || '--';
        document.getElementById('detailCustomerGender').textContent = selectedCustomerRow.getAttribute(
            'data-customer-gender') || '--';
        document.getElementById('detailCustomerEmail').textContent = selectedCustomerRow.getAttribute(
            'data-customer-email') || '--';
        document.getElementById('detailCustomerPhone').textContent = selectedCustomerRow.getAttribute(
            'data-customer-phone') || '--';
        document.getElementById('detailCustomerBirthday').textContent = selectedCustomerRow.getAttribute(
            'data-customer-birthday') || '--';
        document.getElementById('detailCustomerAddress').textContent = selectedCustomerRow.getAttribute(
            'data-customer-address') || '--';
        document.getElementById('detailCustomerTotal').textContent = selectedCustomerRow.getAttribute(
            'data-customer-total') || '--';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('customerDetailModal')).show();
    });

    document.getElementById('editCustomerForm').addEventListener('submit', function(event) {
        const customerName = document.getElementById('editCustomerName').value.trim();

        if (!customerName) {
            event.preventDefault();
            showScreenNotice('Vui lòng nhập tên khách hàng.', 'warning');
            return;
        }
    });

    btnDeleteCustomer.addEventListener('click', function() {
        if (!selectedCustomerRow) {
            showScreenNotice('Vui lòng chọn khách hàng để xóa.', 'warning');
            return;
        }

        pendingDeleteCustomerRow = selectedCustomerRow;
        document.getElementById('deleteCustomerName').textContent = selectedCustomerRow.getAttribute(
            'data-customer-name') || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteCustomerModal')).show();
    });

    if (btnLockCustomer) {
        btnLockCustomer.addEventListener('click', function() {
            if (!selectedCustomerRow) {
                showScreenNotice('Vui lòng chọn khách hàng để khóa tài khoản.', 'warning');
                return;
            }

            const accountMeta = getSelectedAccountStatus();
            if (!accountMeta.hasAccount) {
                showScreenNotice('Khách hàng này chưa có tài khoản đăng nhập liên kết.', 'warning');
                return;
            }
            if (accountMeta.status === 'inactive') {
                showScreenNotice('Tài khoản này đã bị khóa. Hãy dùng nút Mở khóa tài khoản.', 'info');
                return;
            }

            pendingLockCustomerRow = selectedCustomerRow;
            document.getElementById('lockCustomerName').textContent = selectedCustomerRow.getAttribute(
                'data-customer-name') || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('lockCustomerModal')).show();
        });
    }

    if (btnUnlockCustomer) {
        btnUnlockCustomer.addEventListener('click', function() {
            if (!selectedCustomerRow) {
                showScreenNotice('Vui lòng chọn khách hàng để mở khóa tài khoản.', 'warning');
                return;
            }

            const accountMeta = getSelectedAccountStatus();
            if (!accountMeta.hasAccount) {
                showScreenNotice('Khách hàng này chưa có tài khoản đăng nhập liên kết.', 'warning');
                return;
            }
            if (accountMeta.status !== 'inactive') {
                showScreenNotice('Tài khoản này đang hoạt động. Chưa cần mở khóa.', 'info');
                return;
            }

            pendingUnlockCustomerRow = selectedCustomerRow;
            document.getElementById('unlockCustomerName').textContent = selectedCustomerRow.getAttribute(
                'data-customer-name') || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('unlockCustomerModal')).show();
        });
    }

    document.getElementById('btnConfirmDeleteCustomer').addEventListener('click', function() {
        if (!pendingDeleteCustomerRow) {
            return;
        }

        const id = pendingDeleteCustomerRow.getAttribute('data-customer-id') || '';
        if (!id) {
            return;
        }

        document.getElementById('deleteCustomerId').value = id;
        document.getElementById('deleteCustomerForm').submit();
    });

    document.getElementById('deleteCustomerModal').addEventListener('hidden.bs.modal', function() {
        pendingDeleteCustomerRow = null;
    });

    document.getElementById('btnConfirmLockCustomer').addEventListener('click', function() {
        if (!pendingLockCustomerRow) {
            return;
        }

        const id = pendingLockCustomerRow.getAttribute('data-customer-id') || '';
        if (!id) {
            return;
        }

        document.getElementById('lockCustomerId').value = id;
        document.getElementById('lockCustomerForm').submit();
    });

    document.getElementById('lockCustomerModal').addEventListener('hidden.bs.modal', function() {
        pendingLockCustomerRow = null;
    });

    document.getElementById('btnConfirmUnlockCustomer').addEventListener('click', function() {
        if (!pendingUnlockCustomerRow) {
            return;
        }

        const id = pendingUnlockCustomerRow.getAttribute('data-customer-id') || '';
        if (!id) {
            return;
        }

        document.getElementById('unlockCustomerId').value = id;
        document.getElementById('unlockCustomerForm').submit();
    });

    document.getElementById('unlockCustomerModal').addEventListener('hidden.bs.modal', function() {
        pendingUnlockCustomerRow = null;
    });

    syncCustomerActionButtons();
    </script>
    <script src="admin-search.js?v=20260414-2"></script>
</body>

</html>