<?php
require_once __DIR__ . '/../Login/admin_auth.php';

function pickValue(array $row, array $keys, $default = '') {
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

function getExistingColumns(PDO $pdo, string $table, bool $forceRefresh = false): array {
    static $cache = [];

    if (!$forceRefresh && isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cache[$table] = array_map(static function ($col) {
        return strtolower((string) ($col['Field'] ?? ''));
    }, $rows);

    return $cache[$table];
}

function ensureProductImageColumn(PDO $pdo): ?string {
    $columns = getExistingColumns($pdo, 'hanghoa', true);
    $imageColumn = pickExistingColumn($columns, ['hinhanh', 'hinh_anh', 'image', 'img', 'anh', 'duongdananh', 'duong_dan_anh']);
    if ($imageColumn !== null) {
        return $imageColumn;
    }

    try {
        $pdo->exec("ALTER TABLE hanghoa ADD COLUMN HinhAnh VARCHAR(255) NULL");
    } catch (Throwable $ignored) {
    }

    $columns = getExistingColumns($pdo, 'hanghoa', true);
    return pickExistingColumn($columns, ['hinhanh', 'hinh_anh', 'image', 'img', 'anh', 'duongdananh', 'duong_dan_anh']);
}

function pickExistingColumn(array $existingColumns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (in_array(strtolower($candidate), $existingColumns, true)) {
            return $candidate;
        }
    }

    return null;
}

function generateNextCode(PDO $pdo, string $table, array $columnCandidates, string $prefix, int $padLength = 2): string {
    $columns = getExistingColumns($pdo, $table);
    $idColumn = pickExistingColumn($columns, $columnCandidates);
    if ($idColumn === null) {
        return '';
    }

    $rows = $pdo->query("SELECT {$idColumn} AS code FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
    $usedNumbers = [];

    foreach ($rows as $rowCode) {
        $code = trim((string) $rowCode);
        if ($code === '') {
            continue;
        }

        if ($prefix !== '' && strncasecmp($code, $prefix, strlen($prefix)) === 0) {
            $numericPart = substr($code, strlen($prefix));
            if ($numericPart !== '' && ctype_digit($numericPart)) {
                $usedNumbers[(int) $numericPart] = true;
                continue;
            }
        }

        if (preg_match('/(\d+)$/', $code, $matches) !== 1) {
            continue;
        }

        $usedNumbers[(int) $matches[1]] = true;
    }

    $nextNumber = 1;
    while (isset($usedNumbers[$nextNumber])) {
        $nextNumber++;
    }

    return $prefix . str_pad((string) $nextNumber, $padLength, '0', STR_PAD_LEFT);
}

function sanitizeReturnToPath(string $rawPath): string {
    $rawPath = trim($rawPath);
    if ($rawPath === '') {
        return '';
    }

    if (preg_match('/[\r\n]/', $rawPath)) {
        return '';
    }

    $decoded = rawurldecode($rawPath);
    $parts = parse_url($decoded);
    if ($parts === false) {
        return '';
    }

    $path = (string) ($parts['path'] ?? '');
    if ($path === '') {
        return '';
    }

    $normalizedPath = str_replace('\\', '/', $path);
    if (!preg_match('#(^|/)TrangWeb/trangchu\.php$#i', $normalizedPath)) {
        return '';
    }

    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return $path . $query;
}

$products = [];
$dbError = '';
$crudMessage = '';
$crudError = '';
$nextProductId = '';

$dbHost = '127.0.0.1';
$dbName = 'qlhethongbanhangmini';
$dbUser = 'root';
$dbPass = '';
$requestedReturnTo = isset($_REQUEST['return_to']) ? (string) $_REQUEST['return_to'] : '';
$safeReturnTo = sanitizeReturnToPath($requestedReturnTo);
$action = '';

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

    $imageColumnForHangHoa = ensureProductImageColumn($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['crud_action']) ? trim((string) $_POST['crud_action']) : '';

        if ($action === 'add_product') {
            $maHang = trim((string) ($_POST['ma_hang'] ?? ''));
            $maNhomHang = trim((string) ($_POST['ma_nhom_hang'] ?? ''));
            $tenHang = trim((string) ($_POST['ten_hang'] ?? ''));
            $dvt = trim((string) ($_POST['dvt'] ?? ''));
            $donGia = (float) ($_POST['don_gia'] ?? 0);
            $vat = (float) ($_POST['vat'] ?? 0);
            $hinhAnh = trim((string) ($_POST['hinh_anh'] ?? ''));

            try {
                $columns = getExistingColumns($pdo, 'hanghoa');

                $maHangCol = pickExistingColumn($columns, ['mahang', 'ma_hang', 'idhanghoa', 'id']);
                $maNhomHangCol = pickExistingColumn($columns, ['manhomhang', 'ma_nhom_hang', 'manhom']);
                $tenHangCol = pickExistingColumn($columns, ['tenhang', 'ten_hang', 'tensp', 'tensanpham', 'name']);
                $dvtCol = pickExistingColumn($columns, ['dvt', 'donvitinh', 'don_vi_tinh']);
                $donGiaCol = pickExistingColumn($columns, ['dongia', 'don_gia', 'giaban', 'gia']);
                $vatCol = pickExistingColumn($columns, ['vat', 'thuevat', 'thue_vat']);
                $giaCoThueCol = pickExistingColumn($columns, ['sotiencothue', 'so_tien_co_thue', 'giacothue', 'gia_co_thue']);

                if ($maHang === '') {
                    $maHang = generateNextCode($pdo, 'hanghoa', ['mahang', 'ma_hang', 'idhanghoa', 'id'], 'HH', 2);
                }

                if ($maHang === '' || $tenHang === '' || $maNhomHang === '') {
                    $crudError = 'Vui lòng nhập đầy đủ thông tin sản phẩm và chọn nhóm hàng.';
                } elseif ($maHangCol === null || $tenHangCol === null) {
                    $crudError = 'Không tìm thấy cột bắt buộc để thêm sản phẩm (mã hàng/tên hàng).';
                } else {
                    $insertColumns = [];
                    $insertPlaceholders = [];
                    $params = [];

                    $insertColumns[] = $maHangCol;
                    $insertPlaceholders[] = ':mahang';
                    $params[':mahang'] = $maHang;

                    if ($maNhomHangCol !== null) {
                        $insertColumns[] = $maNhomHangCol;
                        $insertPlaceholders[] = ':manhomhang';
                        $params[':manhomhang'] = $maNhomHang;
                    }

                    $insertColumns[] = $tenHangCol;
                    $insertPlaceholders[] = ':tenhang';
                    $params[':tenhang'] = $tenHang;

                    if ($dvtCol !== null) {
                        $insertColumns[] = $dvtCol;
                        $insertPlaceholders[] = ':dvt';
                        $params[':dvt'] = $dvt;
                    }

                    if ($donGiaCol !== null) {
                        $insertColumns[] = $donGiaCol;
                        $insertPlaceholders[] = ':dongia';
                        $params[':dongia'] = $donGia;
                    }

                    if ($vatCol !== null) {
                        $insertColumns[] = $vatCol;
                        $insertPlaceholders[] = ':vat';
                        $params[':vat'] = $vat;
                    }

                    if ($giaCoThueCol !== null) {
                        $insertColumns[] = $giaCoThueCol;
                        $insertPlaceholders[] = ':giacothue';
                        $params[':giacothue'] = $donGia > 0 ? ($donGia * (1 + ($vat / 100))) : 0;
                    }

                    if ($imageColumnForHangHoa !== null) {
                        $insertColumns[] = $imageColumnForHangHoa;
                        $insertPlaceholders[] = ':hinhanh';
                        $params[':hinhanh'] = $hinhAnh !== '' ? $hinhAnh : null;
                    }

                    $sql = 'INSERT INTO hanghoa (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $crudMessage = 'Đã thêm sản phẩm thành công.';
                }
            } catch (Throwable $insertError) {
                $crudError = 'Không thể thêm sản phẩm: ' . $insertError->getMessage();
            }
        }

        if ($action === 'update_product') {
            $maHang = trim((string) ($_POST['ma_hang'] ?? ''));
            $maNhomHang = trim((string) ($_POST['ma_nhom_hang'] ?? ''));
            $tenHang = trim((string) ($_POST['ten_hang'] ?? ''));
            $dvt = trim((string) ($_POST['dvt'] ?? ''));
            $donGia = (float) ($_POST['don_gia'] ?? 0);
            $vat = (float) ($_POST['vat'] ?? 0);
            $hinhAnh = trim((string) ($_POST['hinh_anh'] ?? ''));

            if ($maHang === '' || $tenHang === '') {
                $crudError = 'Dữ liệu cập nhật sản phẩm không hợp lệ.';
            } else {
                try {
                    $columns = getExistingColumns($pdo, 'hanghoa');

                    $maHangCol = pickExistingColumn($columns, ['mahang', 'ma_hang', 'idhanghoa', 'id']);
                    $maNhomHangCol = pickExistingColumn($columns, ['manhomhang', 'ma_nhom_hang', 'manhom']);
                    $tenHangCol = pickExistingColumn($columns, ['tenhang', 'ten_hang', 'tensp', 'tensanpham', 'name']);
                    $dvtCol = pickExistingColumn($columns, ['dvt', 'donvitinh', 'don_vi_tinh']);
                    $donGiaCol = pickExistingColumn($columns, ['dongia', 'don_gia', 'giaban', 'gia']);
                    $vatCol = pickExistingColumn($columns, ['vat', 'thuevat', 'thue_vat']);
                    $giaCoThueCol = pickExistingColumn($columns, ['sotiencothue', 'so_tien_co_thue', 'giacothue', 'gia_co_thue']);

                    if ($maHangCol === null || $tenHangCol === null) {
                        $crudError = 'Không tìm thấy cột bắt buộc để cập nhật sản phẩm.';
                    } else {
                        $setParts = ["{$tenHangCol} = :tenhang"];
                        $params = [
                            ':mahang' => $maHang,
                            ':tenhang' => $tenHang,
                        ];

                        if ($maNhomHangCol !== null) {
                            $setParts[] = "{$maNhomHangCol} = :manhomhang";
                            $params[':manhomhang'] = $maNhomHang;
                        }

                        if ($dvtCol !== null) {
                            $setParts[] = "{$dvtCol} = :dvt";
                            $params[':dvt'] = $dvt;
                        }

                        if ($donGiaCol !== null) {
                            $setParts[] = "{$donGiaCol} = :dongia";
                            $params[':dongia'] = $donGia;
                        }

                        if ($vatCol !== null) {
                            $setParts[] = "{$vatCol} = :vat";
                            $params[':vat'] = $vat;
                        }

                        if ($giaCoThueCol !== null) {
                            $setParts[] = "{$giaCoThueCol} = :giacothue";
                            $params[':giacothue'] = $donGia > 0 ? ($donGia * (1 + ($vat / 100))) : 0;
                        }

                        if ($imageColumnForHangHoa !== null) {
                            $setParts[] = "{$imageColumnForHangHoa} = :hinhanh";
                            $params[':hinhanh'] = $hinhAnh !== '' ? $hinhAnh : null;
                        }

                        $sql = 'UPDATE hanghoa SET ' . implode(', ', $setParts) . " WHERE {$maHangCol} = :mahang";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $crudMessage = 'Đã cập nhật sản phẩm.';
                    }
                } catch (Throwable $updateError) {
                    $crudError = 'Không thể cập nhật sản phẩm: ' . $updateError->getMessage();
                }
            }
        }

        if ($action === 'delete_product') {
            $maHang = trim((string) ($_POST['ma_hang'] ?? ''));
            if ($maHang === '') {
                $crudError = 'Không xác định được sản phẩm để xóa.';
            } else {
                try {
                    $hangHoaColumns = getExistingColumns($pdo, 'hanghoa');
                    $maHangCol = pickExistingColumn($hangHoaColumns, ['mahang', 'ma_hang', 'idhanghoa', 'id']);

                    if ($maHangCol === null) {
                        $crudError = 'Không tìm thấy cột mã hàng để xóa sản phẩm.';
                    } else {
                        $linkedRefs = [];

                        try {
                            $nhapCols = getExistingColumns($pdo, 'chitietnhaphang');
                            $nhapMaHangCol = pickExistingColumn($nhapCols, ['mahang', 'ma_hang', 'idhanghoa']);
                            if ($nhapMaHangCol !== null) {
                                $nhapCountStmt = $pdo->prepare("SELECT COUNT(*) FROM chitietnhaphang WHERE {$nhapMaHangCol} = :mahang");
                                $nhapCountStmt->execute([':mahang' => $maHang]);
                                $nhapCount = (int) $nhapCountStmt->fetchColumn();
                                if ($nhapCount > 0) {
                                    $linkedRefs[] = $nhapCount . ' dòng chi tiết nhập hàng';
                                }
                            }
                        } catch (Throwable $ignored) {
                        }

                        try {
                            $xuatCols = getExistingColumns($pdo, 'chitietphieuxuat');
                            $xuatMaHangCol = pickExistingColumn($xuatCols, ['mahang', 'ma_hang', 'idhanghoa']);
                            if ($xuatMaHangCol !== null) {
                                $xuatCountStmt = $pdo->prepare("SELECT COUNT(*) FROM chitietphieuxuat WHERE {$xuatMaHangCol} = :mahang");
                                $xuatCountStmt->execute([':mahang' => $maHang]);
                                $xuatCount = (int) $xuatCountStmt->fetchColumn();
                                if ($xuatCount > 0) {
                                    $linkedRefs[] = $xuatCount . ' dòng chi tiết xuất hàng';
                                }
                            }
                        } catch (Throwable $ignored) {
                        }

                        if (count($linkedRefs) > 0) {
                            $crudError = 'Không thể xóa sản phẩm vì còn dữ liệu liên kết: ' . implode(', ', $linkedRefs) . '. Vui lòng xóa/chỉnh dữ liệu liên quan trước.';
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM hanghoa WHERE {$maHangCol} = :mahang");
                            $stmt->execute([':mahang' => $maHang]);

                            if ($stmt->rowCount() > 0) {
                                $crudMessage = 'Đã xóa sản phẩm.';
                            } else {
                                $crudError = 'Không tìm thấy sản phẩm để xóa hoặc dữ liệu đã thay đổi.';
                            }
                        }
                    }
                } catch (Throwable $deleteError) {
                    $deleteMessage = (string) $deleteError->getMessage();
                    if (str_contains($deleteMessage, 'SQLSTATE[23000]') || str_contains($deleteMessage, '1451')) {
                        $crudError = 'Không thể xóa sản phẩm vì còn dữ liệu nhập/xuất liên kết. Vui lòng xử lý dữ liệu liên quan trước.';
                    } else {
                        $crudError = 'Không thể xóa sản phẩm: ' . $deleteMessage;
                    }
                }
            }

            if ($action === 'update_product' && $crudError === '' && $safeReturnTo !== '') {
                header('Location: ' . $safeReturnTo);
                exit;
            }
        }

    }

    $nhomHangMap = [];
    try {
        $nhomRows = $pdo->query("SELECT * FROM nhomhang")->fetchAll();
        foreach ($nhomRows as $nhom) {
            $nhomCode = (string) pickValue($nhom, ['manhomhang', 'ma_nhom_hang', 'manhom', 'id'], '');
            $nhomName = (string) pickValue($nhom, ['tennhomhang', 'ten_nhom_hang', 'tennhom', 'name'], '');
            if ($nhomCode !== '') {
                $nhomHangMap[$nhomCode] = $nhomName;
            }
        }
    } catch (Throwable $ignored) {
    }

    try {
        $nextProductId = generateNextCode($pdo, 'hanghoa', ['mahang', 'ma_hang', 'idhanghoa', 'id'], 'HH', 2);
    } catch (Throwable $ignored) {
        $nextProductId = '';
    }

    $productImageFolderPresets = [
        'Hàng hóa' => '../file anh/',
        'Đồ uống có cồn' => '../file anh/AnhDoUongCoCon/',
        'Gia dụng' => '../file anh/AnhDoGiaDung/',
        'Kem' => '../file anh/AnhKem/',
        'Mì gói' => '../file anh/AnhMi/',
        'Mỹ phẩm' => '../file anh/AnhMyPham/',
        'Nước ngọt' => '../file anh/AnhNuocNgot/',
        'Rau củ' => '../file anh/AnhRauCu/',
        'Sữa' => '../file anh/AnhSua/',
        'Đồ ăn nhanh' => '../file anh/AnhDoAnNhanh/',
        'Trái cây' => '../file anh/Anhtraicay/',
        'Thịt cá' => '../file anh/AnhTuoiSong/',
    ];

    $stockColumn = null;
    $stockColumns = getExistingColumns($pdo, 'hanghoa');
    $stockColumn = pickExistingColumn($stockColumns, ['soluongton', 'so_luong_ton', 'soluong', 'tonkho']);

    $stockBalanceByProduct = [];
    try {
        $ctnhColumns = getExistingColumns($pdo, 'chitietnhaphang');
        $importProductCol = pickExistingColumn($ctnhColumns, ['mahang', 'ma_hang', 'idhanghoa']);
        $importQtyCol = pickExistingColumn($ctnhColumns, ['soluongnhap', 'so_luong_nhap', 'soluong', 'so_luong']);

        $ctpxColumns = getExistingColumns($pdo, 'chitietphieuxuat');
        $exportProductCol = pickExistingColumn($ctpxColumns, ['mahang', 'ma_hang', 'idhanghoa']);
        $exportQtyCol = pickExistingColumn($ctpxColumns, ['soluongpx', 'so_luong_px', 'soluong', 'so_luong']);
        $exportOrderIdCol = pickExistingColumn($ctpxColumns, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon']);

        $pxColumns = getExistingColumns($pdo, 'phieuxuat');
        $orderIdCol = pickExistingColumn($pxColumns, ['idphieuxuat', 'id_phieu_xuat', 'maphieuxuat', 'ma_phieu_xuat', 'maphieu', 'ma_phieu', 'madon', 'id']);
        $orderStatusCol = pickExistingColumn($pxColumns, ['trangthai', 'trang_thai', 'status', 'kyhieupx', 'ky_hieu_px', 'kyhieu', 'ky_hieu']);

        if ($importProductCol === null || $importQtyCol === null || $exportProductCol === null || $exportQtyCol === null) {
            throw new RuntimeException('Không đủ cột để tính tồn kho từ nhập/xuất.');
        }

        $stockRows = $pdo->query(
            "SELECT `{$importProductCol}` AS MaHang, COALESCE(SUM(`{$importQtyCol}`), 0) AS TongNhap
             FROM chitietnhaphang
             GROUP BY `{$importProductCol}`"
        )->fetchAll();
        foreach ($stockRows as $stockRow) {
            $maHangStock = (string) ($stockRow['MaHang'] ?? '');
            if ($maHangStock === '') {
                continue;
            }
            if (!isset($stockBalanceByProduct[$maHangStock])) {
                $stockBalanceByProduct[$maHangStock] = 0;
            }
            $stockBalanceByProduct[$maHangStock] += (int) ($stockRow['TongNhap'] ?? 0);
        }

        if ($exportOrderIdCol !== null && $orderIdCol !== null && $orderStatusCol !== null) {
            $exportRows = $pdo->query(
                "SELECT ctx.`{$exportProductCol}` AS MaHang, COALESCE(SUM(ctx.`{$exportQtyCol}`), 0) AS TongXuat
                 FROM chitietphieuxuat ctx
                 LEFT JOIN phieuxuat px ON px.`{$orderIdCol}` = ctx.`{$exportOrderIdCol}`
                 WHERE px.`{$orderStatusCol}` IS NULL
                    OR (
                        LOWER(px.`{$orderStatusCol}`) NOT LIKE '%hủy%'
                        AND LOWER(px.`{$orderStatusCol}`) NOT LIKE '%huy%'
                        AND LOWER(px.`{$orderStatusCol}`) NOT LIKE '%cancel%'
                    )
                 GROUP BY ctx.`{$exportProductCol}`"
            )->fetchAll();
        } else {
            $exportRows = $pdo->query(
                "SELECT `{$exportProductCol}` AS MaHang, COALESCE(SUM(`{$exportQtyCol}`), 0) AS TongXuat
                 FROM chitietphieuxuat
                 GROUP BY `{$exportProductCol}`"
            )->fetchAll();
        }

        foreach ($exportRows as $exportRow) {
            $maHangStock = (string) ($exportRow['MaHang'] ?? '');
            if ($maHangStock === '') {
                continue;
            }
            if (!isset($stockBalanceByProduct[$maHangStock])) {
                $stockBalanceByProduct[$maHangStock] = 0;
            }
            $stockBalanceByProduct[$maHangStock] -= (int) ($exportRow['TongXuat'] ?? 0);
        }
    } catch (Throwable $ignored) {
    }

    $hangHoaRows = $pdo->query("SELECT * FROM hanghoa")->fetchAll();
    foreach ($hangHoaRows as $row) {
        $maHang = (string) pickValue($row, ['mahang', 'ma_hang', 'idhanghoa', 'id']);
        $maNhomHang = (string) pickValue($row, ['manhomhang', 'ma_nhom_hang', 'manhom']);
        $tenNhomHang = (string) pickValue($row, ['tennhomhang', 'ten_nhom_hang', 'tennhom'], '');
        if ($tenNhomHang === '' && isset($nhomHangMap[$maNhomHang])) {
            $tenNhomHang = $nhomHangMap[$maNhomHang];
        }

        $tenHang = (string) pickValue($row, ['tenhang', 'ten_hang', 'tensp', 'tensanpham', 'name']);
        $dvt = (string) pickValue($row, ['dvt', 'donvitinh', 'don_vi_tinh'], '');
        $donGia = (float) pickValue($row, ['dongia', 'don_gia', 'giaban', 'gia'], 0);
        $vatRaw = pickValue($row, ['vat', 'thuevat', 'thue_vat'], 0);
        $vat = (float) str_replace('%', '', (string) $vatRaw);
        if ($stockColumn !== null) {
            $soLuongTon = (int) pickValue($row, ['soluongton', 'so_luong_ton', 'soluong', 'tonkho'], 0);
        } else {
            $soLuongTon = max(0, (int) ($stockBalanceByProduct[$maHang] ?? 0));
        }

        $giaCoThue = $donGia * (1 + ($vat / 100));
        $hinhAnh = trim((string) pickValue($row, ['hinhanh', 'hinh_anh', 'image', 'img', 'anh', 'duongdananh', 'duong_dan_anh'], ''));
        $hinhAnhDisplay = $hinhAnh !== '' ? $hinhAnh : '../TrangUser/ack.png';

        $products[] = [
            'MaHang' => $maHang,
            'MaNhomHang' => $maNhomHang,
            'TenNhomHang' => $tenNhomHang,
            'TenHang' => $tenHang,
            'HinhAnh' => $hinhAnh,
            'HinhAnhDisplay' => $hinhAnhDisplay,
            'DVT' => $dvt,
            'DonGiaRaw' => $donGia,
            'DonGia' => number_format($donGia, 0, ',', '.'),
            'VATRaw' => $vat,
            'VAT' => rtrim(rtrim(number_format($vat, 2, '.', ''), '0'), '.') . '%',
            'SoTienCoThue' => number_format($giaCoThue, 0, ',', '.'),
            'SoLuongTon' => $soLuongTon,
        ];
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

if (!isset($productImageFolderPresets) || !is_array($productImageFolderPresets)) {
    $productImageFolderPresets = [
        'Hàng hóa' => '../file anh/',
        'Đồ uống có cồn' => '../file anh/AnhDoUongCoCon/',
        'Gia dụng' => '../file anh/AnhDoGiaDung/',
        'Kem' => '../file anh/AnhKem/',
        'Mì gói' => '../file anh/AnhMi/',
        'Mỹ phẩm' => '../file anh/AnhMyPham/',
        'Nước ngọt' => '../file anh/AnhNuocNgot/',
        'Rau củ' => '../file anh/AnhRauCu/',
        'Sữa' => '../file anh/AnhSua/',
        'Đồ ăn nhanh' => '../file anh/AnhDoAnNhanh/',
        'Trái cây' => '../file anh/Anhtraicay/',
        'Thịt cá' => '../file anh/AnhTuoiSong/',
    ];
}

$totalProducts = count($products);
$inStock = 0;
$outOfStock = 0;

foreach ($products as $p) {
    if ($p['SoLuongTon'] > 0) {
        $inStock++;
    } else {
        $outOfStock++;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Admin - Quản lý sản phẩm</title>
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
        padding: 20px 30px;
        height: 100vh;
        overflow: hidden;
    }

    .product-top-sticky {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        z-index: 1030;
        background: var(--bg-light);
        padding: 20px 30px 14px;
        border-bottom: none;
        box-shadow: none;
        transition: none !important;
    }

    .product-top-sticky .alert {
        margin-bottom: 10px;
    }

    .product-content-offset {
        position: fixed;
        left: calc(var(--sidebar-width) + 30px);
        right: 30px;
        top: 260px;
        bottom: 20px;
        overflow: hidden;
        margin-top: 0;
    }

    body.modal-open #productContentOffset {
        overflow: visible;
    }

    /* --- PRODUCT SPECIFIC STYLES --- */
    .product-stat-card {
        background: #cfe2ff;
        border-radius: 12px;
        padding: 20px;
        border: none;
    }

    .product-stat-label {
        color: #67748e;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .product-stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #344767;
    }

    .search-input {
        border-radius: 20px;
        padding: 5px 20px;
        border: 1px solid #ddd;
        width: 250px;
    }

    .btn-add-product {
        background-color: var(--primary-blue);
        color: white;
        border-radius: 8px;
        padding: 10px 20px;
        border: none;
        font-weight: 500;
    }

    .btn-add-product:hover {
        background-color: #0069d9;
        color: white;
    }

    .btn-edit-product {
        background-color: #ffc107;
        color: #212529;
        border-radius: 8px;
        padding: 10px 20px;
        border: none;
        font-weight: 500;
    }

    .btn-edit-product:hover {
        background-color: #e0a800;
        color: #212529;
    }

    .btn-delete-product {
        background-color: #dc3545;
        color: white;
        border-radius: 8px;
        padding: 10px 20px;
        border: none;
        font-weight: 500;
    }

    .btn-delete-product:hover {
        background-color: #c82333;
        color: white;
    }

    @media (max-width: 768px) {
        .product-top-sticky {
            left: var(--sidebar-width);
            right: 0;
            padding-bottom: 10px;
        }

        .product-content-offset {
            left: calc(var(--sidebar-width) + 16px);
            right: 16px;
            bottom: 16px;
        }

        .search-input {
            width: 100%;
            margin-top: 10px;
        }
    }

    .product-table-container {
        background: white;
        border-radius: 15px;
        padding: 0;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        margin-top: 0;
    }

    .product-table-container .table-responsive {
        flex: 1;
        overflow: auto;
    }

    .table {
        margin-bottom: 0;
        min-width: 900px;
    }

    .table thead th {
        background-color: #f8f9fa;
        color: #344767;
        font-weight: 700;
        text-transform: none;
        border-bottom: 1px solid #eee;
        padding: 15px 15px;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .table tbody td {
        padding: 15px 15px;
        vertical-align: middle;
        color: #344767;
        border-bottom: 1px solid #f0f2f5;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-weight: 500;
        white-space: nowrap;
    }

    .status-col {
        white-space: nowrap;
        min-width: 120px;
    }

    .text-success {
        color: #2dce89 !important;
    }

    .text-danger {
        color: #f5365c !important;
    }

    /* Row selection styles */
    .table tbody tr {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .table tbody tr.selected {
        background-color: #cce3ff;
        font-weight: 500;
        border-left: 4px solid #007bff;
        box-shadow: inset 0 0 8px rgba(0, 123, 255, 0.1);
    }

    .table tbody tr.selected td:first-child {
        padding-left: 16px;
    }

    .product-thumb {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid #dbe2ea;
        background: #fff;
    }

    /* Detail Panel */
    .detail-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 1040;
    }

    .detail-overlay.show {
        display: block;
    }

    .detail-panel {
        background: white;
        border-radius: 12px;
        border: 1px solid #e9ecef;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        padding: 18px;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: min(860px, calc(100vw - 24px));
        max-height: calc(100vh - 24px);
        overflow-y: auto;
        overflow-x: hidden;
        z-index: 1055;
        display: none;
    }

    .detail-panel.show {
        display: block;
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translate(-50%, -60%);
        }

        to {
            opacity: 1;
            transform: translate(-50%, -50%);
        }
    }

    .detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f2f5;
    }

    .detail-header h5 {
        margin: 0;
        font-weight: 700;
        color: #344767;
        font-size: 1.2rem;
    }

    .detail-content {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px 10px;
    }

    .detail-panel.read-only-compact .detail-content {
        grid-template-columns: 190px repeat(2, minmax(0, 1fr));
        gap: 8px 10px;
        align-items: start;
    }

    .detail-panel.read-only-compact .detail-field--image.detail-field--full {
        grid-column: 1;
        grid-row: 1 / span 5;
        align-items: flex-start;
    }

    .detail-panel.read-only-compact .detail-field--image>label {
        text-align: left;
    }

    .detail-field {
        display: flex;
        flex-direction: column;
    }

    .detail-field.detail-field--full {
        grid-column: 1 / -1;
    }

    .detail-field label {
        font-weight: 600;
        color: #667eea;
        font-size: 0.8rem;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.35px;
    }

    .detail-field input,
    .detail-field select,
    .detail-field textarea {
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 7px 9px;
        font-size: 0.92rem;
        color: #444;
        background-color: #f8f9fa;
        width: 100%;
        min-width: 0;
    }

    .detail-field input:focus,
    .detail-field select:focus,
    .detail-field textarea:focus {
        background-color: #fff;
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .detail-field input[readonly] {
        background-color: #eef2f7;
    }

    #detailPanel .detail-field--image {
        align-items: center;
    }

    #detailPanel .detail-field--image>label {
        width: 100%;
        text-align: center;
    }

    #detailPanel .detail-field-preview {
        width: 100%;
        min-height: 96px;
        border: none !important;
        border-radius: 0 !important;
        background: transparent !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
    }

    .detail-panel.read-only-compact .detail-field-preview {
        justify-content: flex-start !important;
    }

    #detailPanel .detail-field-preview img {
        display: block;
        margin: 0 auto;
        max-width: 100%;
        max-height: 100px;
        object-fit: contain;
        border-radius: 6px;
    }

    .detail-panel.read-only-compact .detail-field-preview img {
        margin: 0;
        max-height: 120px;
    }

    .detail-panel.read-only-compact .detail-field--link {
        display: none;
    }

    .detail-panel.read-only-compact .detail-field--status {
        display: flex;
        grid-column: 2;
        grid-row: 5;
    }

    .detail-panel.read-only-compact .detail-field--stock {
        grid-column: 3;
        grid-row: 5;
    }

    @media (max-width: 900px) {
        .detail-panel.read-only-compact .detail-content {
            grid-template-columns: 165px repeat(2, minmax(0, 1fr));
        }

        .detail-panel.read-only-compact .detail-field--image.detail-field--full {
            grid-row: 1 / span 5;
        }

        .detail-panel.read-only-compact .detail-field--status {
            grid-column: 2;
        }

        .detail-panel.read-only-compact .detail-field--stock {
            grid-column: 3;
        }
    }

    @media (max-width: 768px) {
        .detail-content {
            grid-template-columns: 1fr;
        }

        .detail-panel.read-only-compact .detail-content {
            grid-template-columns: 1fr;
        }

        .detail-panel.read-only-compact .detail-field--image.detail-field--full {
            grid-column: 1;
            grid-row: auto;
            align-items: center;
        }

        .detail-panel.read-only-compact .detail-field--image>label {
            text-align: center;
        }

        .detail-panel.read-only-compact .detail-field-preview {
            justify-content: center !important;
        }

        .detail-panel.read-only-compact .detail-field-preview img {
            margin: 0 auto;
        }

        .detail-panel.read-only-compact .detail-field--status,
        .detail-panel.read-only-compact .detail-field--stock {
            grid-column: auto;
            grid-row: auto;
        }
    }

    .detail-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid #f0f2f5;
    }

    .detail-actions button {
        font-size: 0.9rem;
        font-weight: 500;
        padding: 8px 18px;
    }

    #editProductModal .modal-body {
        padding-top: 1rem;
        padding-bottom: 1rem;
    }

    #editProductModal .modal-body .row {
        --bs-gutter-x: 0.85rem;
        --bs-gutter-y: 0.65rem;
    }

    #editProductModal .form-label {
        margin-bottom: 0.3rem;
    }

    #editProductModal .form-control,
    #editProductModal .form-select {
        padding-top: 0.45rem;
        padding-bottom: 0.45rem;
    }

    #editProductModal .modal-footer {
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
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
            <a href="admin.php" class="nav-item"><i class="fas fa-chart-bar"></i> Tổng quan</a>
            <a href="admin-sanpham.php" class="nav-item active"><i class="fas fa-box"></i> Sản phẩm</a>
            <a href="admin-nhomhang.php" class="nav-item"><i class="fas fa-folder"></i> Nhóm hàng</a>
            <a href="admin-nhaphang.php" class="nav-item"><i class="fas fa-truck-loading"></i> Nhập hàng</a>
            <a href="admin-nhacungcap.php" class="nav-item"><i class="fas fa-building"></i> Nhà cung cấp</a>
            <a href="admin-bophan.php" class="nav-item"><i class="fas fa-sitemap"></i> Bộ phận</a>
            <a href="admin-donhang.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Đơn hàng</a>
            <a href="admin-nhanvien.php" class="nav-item"><i class="fas fa-user-tie"></i> Nhân viên</a>
            <a href="admin-khachhang.php" class="nav-item"><i class="fas fa-users"></i> Khách hàng</a>
            <a href="admin-voucher.php" class="nav-item"><i class="fas fa-ticket-alt"></i> Voucher</a>
            <a href="admin-caidat.php" class="nav-item"><i class="fas fa-cog"></i> Cài đặt</a>
        </nav>
        <a href="../Login/logout.php" class="logout-btn">Đăng xuất</a>
    </div>

    <div class="main-content">
        <div class="product-top-sticky">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-primary">Bảng điều khiển Admin</h4>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-light rounded-circle shadow-sm"><i class="fas fa-times"></i></button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-end mb-4">
                <h3 class="fw-bold mb-0">Quản lý sản phẩm</h3>
                <input type="text" class="search-input" placeholder="Tìm kiếm mã/tên hàng...">
            </div>

            <?php if ($dbError !== ''): ?>
            <div class="alert alert-warning" role="alert">
                Không thể kết nối/lấy dữ liệu từ MySQL: <?php echo htmlspecialchars($dbError); ?>
            </div>
            <?php endif; ?>

            <?php if ($crudMessage !== ''): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($crudMessage); ?>
            </div>
            <?php endif; ?>

            <?php if ($crudError !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($crudError); ?>
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="product-stat-card">
                        <div class="product-stat-label">Tổng sản phẩm</div>
                        <div class="product-stat-value"><?php echo $totalProducts; ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="product-stat-card" style="background-color: #d1e7dd;">
                        <div class="product-stat-label">Còn hàng</div>
                        <div class="product-stat-value text-success"><?php echo $inStock; ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="product-stat-card" style="background-color: #f8d7da;">
                        <div class="product-stat-label">Cần nhập hàng</div>
                        <div class="product-stat-value text-danger"><?php echo $outOfStock; ?></div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap mb-0">
                <button class="btn-add-product" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-plus me-2"></i> Thêm sản phẩm
                </button>
                <button class="btn-edit-product" id="btnEditProduct" disabled>
                    <i class="fas fa-pen me-2"></i> Sửa sản phẩm
                </button>
                <button class="btn btn-info fw-semibold text-white" id="btnViewProduct" disabled>
                    <i class="fas fa-eye me-2"></i> Xem chi tiết
                </button>
                <button class="btn-delete-product" id="btnDeleteProduct" disabled>
                    <i class="fas fa-trash me-2"></i> Xóa sản phẩm
                </button>
            </div>
        </div>

        <div id="productContentOffset" class="product-content-offset">
            <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="addProductModalLabel">Thêm sản phẩm mới</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                            <input type="hidden" name="crud_action" value="add_product">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="maHang" class="form-label">Mã hàng</label>
                                        <input type="text" class="form-control" id="maHang" name="ma_hang"
                                            value="<?php echo htmlspecialchars($nextProductId); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="maNhomHang" class="form-label">Mã nhóm hàng</label>
                                        <select class="form-select" id="maNhomHang" name="ma_nhom_hang" required>
                                            <option value="">Chọn nhóm hàng</option>
                                            <?php foreach ($nhomHangMap as $nhomCode => $nhomName): ?>
                                            <option value="<?php echo htmlspecialchars($nhomCode); ?>">
                                                <?php echo htmlspecialchars($nhomCode . ' - ' . $nhomName); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="tenHang" class="form-label">Tên hàng</label>
                                        <input type="text" class="form-control" id="tenHang" name="ten_hang"
                                            placeholder="Nhập tên sản phẩm" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="hinhAnh" class="form-label">Link ảnh</label>
                                        <input type="text" class="form-control" id="hinhAnh" name="hinh_anh"
                                            list="productImageFolderList"
                                            placeholder="VD: ../file anh/AnhNuocNgot/ten-anh.png">
                                        <div class="form-text">Chọn nhóm hàng để tự gợi ý đúng thư mục ảnh.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="dvt" class="form-label">Đơn vị tính</label>
                                        <input type="text" class="form-control" id="dvt" name="dvt"
                                            placeholder="VD: Chai" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="donGia" class="form-label">Đơn giá (₫)</label>
                                        <input type="number" class="form-control" id="donGia" name="don_gia" min="0"
                                            placeholder="0" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="vat" class="form-label">VAT (%)</label>
                                        <input type="number" class="form-control" id="vat" name="vat" min="0" max="100"
                                            placeholder="10" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="giaCoThue" class="form-label">Giá có thuế (₫)</label>
                                        <input type="number" class="form-control" id="giaCoThue" min="0"
                                            placeholder="Tự nhập hoặc tính sẵn">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" class="btn btn-primary">Lưu sản phẩm</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="detailOverlay" class="detail-overlay" onclick="closeDetailPanel()"></div>

            <datalist id="productImageFolderList">
                <?php foreach ($productImageFolderPresets as $folderLabel => $folderPath): ?>
                <option value="<?php echo htmlspecialchars($folderPath, ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars($folderLabel); ?>
                </option>
                <?php endforeach; ?>
            </datalist>

            <!-- Detail Panel -->
            <div id="detailPanel" class="detail-panel">
                <div class="detail-header">
                    <h5>Chi tiết sản phẩm</h5>
                    <button type="button" class="btn-close" onclick="closeDetailPanel()"></button>
                </div>
                <div class="detail-content">
                    <div class="detail-field detail-field--full detail-field--image">
                        <label>Ảnh sản phẩm</label>
                        <div class="detail-field-preview">
                            <img id="detailHinhAnhPreview" src="../TrangUser/ack.png" alt="Ảnh sản phẩm"
                                onerror="this.src=this.dataset.fallback || '../TrangUser/ack.png'"
                                data-fallback="../TrangUser/ack.png">
                        </div>
                    </div>
                    <div class="detail-field">
                        <label>Mã hàng</label>
                        <input type="text" id="detailMaHang" readonly>
                    </div>
                    <div class="detail-field">
                        <label>Mã nhóm hàng</label>
                        <select id="detailMaNhomHang" class="form-select">
                            <option value="">Chọn nhóm hàng</option>
                            <?php foreach ($nhomHangMap as $nhomCode => $nhomName): ?>
                            <option value="<?php echo htmlspecialchars($nhomCode); ?>">
                                <?php echo htmlspecialchars($nhomCode . ' - ' . $nhomName); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="detail-field">
                        <label>Tên nhóm hàng</label>
                        <input type="text" id="detailTenNhomHang" readonly>
                    </div>
                    <div class="detail-field">
                        <label>Tên hàng</label>
                        <input type="text" id="detailTenHang">
                    </div>
                    <div class="detail-field detail-field--link">
                        <label>Link ảnh</label>
                        <input type="text" id="detailHinhAnh" list="productImageFolderList"
                            placeholder="VD: ../file anh/AnhNuocNgot/ten-anh.png">
                    </div>
                    <div class="detail-field">
                        <label>Đơn vị tính</label>
                        <input type="text" id="detailDvt">
                    </div>
                    <div class="detail-field">
                        <label>Đơn giá (₫)</label>
                        <input type="text" id="detailDonGia">
                    </div>
                    <div class="detail-field">
                        <label>VAT (%)</label>
                        <input type="text" id="detailVat">
                    </div>
                    <div class="detail-field">
                        <label>Giá có thuế (₫)</label>
                        <input type="text" id="detailGiaCoThue" readonly>
                    </div>
                    <div class="detail-field detail-field--stock">
                        <label>SL tồn</label>
                        <input type="text" id="detailSoLuongTon" readonly>
                    </div>
                    <div class="detail-field detail-field--status">
                        <label>Trạng thái</label>
                        <input type="text" id="detailTrangThai" readonly>
                    </div>
                </div>
                <div class="detail-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeDetailPanel()">Đóng</button>
                    <button type="button" class="btn btn-primary" id="btnDetailSave">Lưu thay đổi</button>
                </div>
            </div>

            <div class="product-table-container">
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Bảng thông tin hàng hóa</h5>
                    <button class="btn btn-link text-dark"><i class="fas fa-download"></i> Tải báo cáo</button>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Mã Hàng</th>
                                <th>Mã Nhóm</th>
                                <th>Tên Nhóm</th>
                                <th>Tên Hàng</th>
                                <th>Ảnh</th>
                                <th>ĐVT</th>
                                <th>Đơn Giá</th>
                                <th>VAT</th>
                                <th>Giá Có Thuế</th>
                                <th>SL Tồn</th>
                                <th class="status-col">Trạng Thái</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody">
                            <?php foreach($products as $p): ?>
                            <tr class="product-row" data-id="<?php echo htmlspecialchars($p['MaHang']); ?>"
                                data-image="<?php echo htmlspecialchars((string) $p['HinhAnh'], ENT_QUOTES); ?>">
                                <td class="fw-bold text-primary"><?php echo $p['MaHang']; ?></td>
                                <td><?php echo $p['MaNhomHang']; ?></td>
                                <td><?php echo $p['TenNhomHang']; ?></td>
                                <td class="fw-bold"><?php echo $p['TenHang']; ?></td>
                                <td>
                                    <img src="<?php echo htmlspecialchars((string) $p['HinhAnhDisplay'], ENT_QUOTES); ?>"
                                        alt="<?php echo htmlspecialchars((string) $p['TenHang'], ENT_QUOTES); ?>"
                                        class="product-thumb" onerror="this.src='../TrangUser/ack.png'">
                                </td>
                                <td><?php echo $p['DVT']; ?></td>
                                <td><?php echo $p['DonGia']; ?> ₫</td>
                                <td><?php echo $p['VAT']; ?></td>
                                <td class="fw-bold text-danger"><?php echo $p['SoTienCoThue']; ?> ₫</td>
                                <td><?php echo $p['SoLuongTon']; ?></td>
                                <td class="status-col">
                                    <span
                                        class="status-badge text-<?php echo ($p['SoLuongTon'] > 0) ? 'success' : 'danger'; ?>">
                                        <?php echo ($p['SoLuongTon'] > 0) ? '<i class="fas fa-check-circle me-1"></i> Còn hàng' : '<i class="fas fa-times-circle me-1"></i> Hết hàng'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="post" id="productEditForm" class="d-none">
                <input type="hidden" name="crud_action" value="update_product">
                <input type="hidden" name="ma_hang" id="editMaHang">
                <input type="hidden" name="ma_nhom_hang" id="editMaNhomHang">
                <input type="hidden" name="ten_hang" id="editTenHang">
                <input type="hidden" name="hinh_anh" id="editHinhAnh">
                <input type="hidden" name="dvt" id="editDvt">
                <input type="hidden" name="don_gia" id="editDonGia">
                <input type="hidden" name="vat" id="editVat">
            </form>
        </div>
    </div>

    <script>
    const productGroupNameMap =
        <?php echo json_encode($nhomHangMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const imageFolderPresetByCategory =
        <?php echo json_encode($productImageFolderPresets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const defaultImageFolderPreset = imageFolderPresetByCategory['Hàng hóa'] || '../file anh/';

    const addProductModalEl = document.getElementById('addProductModal');
    if (addProductModalEl && addProductModalEl.parentElement !== document.body) {
        document.body.appendChild(addProductModalEl);
    }

    const detailOverlayEl = document.getElementById('detailOverlay');
    if (detailOverlayEl && detailOverlayEl.parentElement !== document.body) {
        document.body.appendChild(detailOverlayEl);
    }

    const detailPanelEl = document.getElementById('detailPanel');
    if (detailPanelEl && detailPanelEl.parentElement !== document.body) {
        document.body.appendChild(detailPanelEl);
    }

    const imageFolderKeywordMap = [{
            keywords: ['do uong co con', 'bia', 'ruou', 'co con'],
            path: imageFolderPresetByCategory['Đồ uống có cồn'] || '../file anh/AnhDoUongCoCon/'
        },
        {
            keywords: ['gia dung'],
            path: imageFolderPresetByCategory['Gia dụng'] || '../file anh/AnhDoGiaDung/'
        },
        {
            keywords: ['kem'],
            path: imageFolderPresetByCategory['Kem'] || '../file anh/AnhKem/'
        },
        {
            keywords: ['mi', 'mi goi', 'mian'],
            path: imageFolderPresetByCategory['Mì gói'] || '../file anh/AnhMi/'
        },
        {
            keywords: ['my pham'],
            path: imageFolderPresetByCategory['Mỹ phẩm'] || '../file anh/AnhMyPham/'
        },
        {
            keywords: ['nuoc ngot'],
            path: imageFolderPresetByCategory['Nước ngọt'] || '../file anh/AnhNuocNgot/'
        },
        {
            keywords: ['rau cu', 'rau', 'cu'],
            path: imageFolderPresetByCategory['Rau củ'] || '../file anh/AnhRauCu/'
        },
        {
            keywords: ['sua'],
            path: imageFolderPresetByCategory['Sữa'] || '../file anh/AnhSua/'
        },
        {
            keywords: ['do an nhanh', 'thuc an nhanh', 'an nhanh'],
            path: imageFolderPresetByCategory['Đồ ăn nhanh'] || '../file anh/AnhDoAnNhanh/'
        },
        {
            keywords: ['trai cay', 'hoa qua'],
            path: imageFolderPresetByCategory['Trái cây'] || '../file anh/Anhtraicay/'
        },
        {
            keywords: ['thit', 'ca', 'tuoi song', 'hai san'],
            path: imageFolderPresetByCategory['Thịt cá'] || '../file anh/AnhTuoiSong/'
        }
    ];

    function normalizeVietnameseText(value) {
        return (value || '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'd')
            .toLowerCase();
    }

    function guessImageFolderFromGroup(optionText) {
        const normalizedText = normalizeVietnameseText(optionText);
        if (!normalizedText) {
            return '';
        }

        for (const rule of imageFolderKeywordMap) {
            if (rule.keywords.some((keyword) => normalizedText.includes(keyword))) {
                return rule.path;
            }
        }

        return defaultImageFolderPreset;
    }

    function maybeApplyImageFolderPreset(inputId, groupSelectId) {
        const input = document.getElementById(inputId);
        const groupSelect = document.getElementById(groupSelectId);
        if (!input || !groupSelect) {
            return;
        }

        const selectedValue = (groupSelect.value || '').trim();
        if (selectedValue === '') {
            input.placeholder = 'VD: ../file anh/AnhNuocNgot/ten-anh.png';
            return;
        }

        const optionText = groupSelect.options[groupSelect.selectedIndex]?.text || '';
        const suggestedPath = guessImageFolderFromGroup(optionText);
        if (!suggestedPath) {
            return;
        }

        const currentValue = (input.value || '').trim();
        const allPresetValues = Object.values(imageFolderPresetByCategory);
        if (currentValue === '' || allPresetValues.includes(currentValue)) {
            input.value = suggestedPath;
        }

        input.placeholder = `${suggestedPath}ten-anh.png`;
    }

    let selectedProductRow = null;
    let selectedProductId = null;
    let pendingDeleteProductId = null;
    let isProductDetailReadOnly = false;
    document.querySelectorAll('.product-row').forEach(row => {
        row.addEventListener('click', function() {
            document.querySelectorAll('.product-row').forEach(r => r.classList.remove('selected'));
            this.classList.add('selected');
            selectedProductRow = this;
            selectedProductId = this.getAttribute('data-id');
            document.getElementById('btnEditProduct').disabled = false;
            document.getElementById('btnViewProduct').disabled = false;
            document.getElementById('btnDeleteProduct').disabled = false;
        });
    });

    function setProductDetailReadOnly(readOnly) {
        isProductDetailReadOnly = !!readOnly;

        const editableFields = [
            'detailMaNhomHang',
            'detailTenHang',
            'detailHinhAnh',
            'detailDvt',
            'detailDonGia',
            'detailVat'
        ];

        editableFields.forEach((fieldId) => {
            const field = document.getElementById(fieldId);
            if (!field) {
                return;
            }
            if (field.tagName === 'SELECT') {
                field.disabled = isProductDetailReadOnly;
            } else {
                field.readOnly = isProductDetailReadOnly;
            }
        });

        const saveButton = document.getElementById('btnDetailSave');
        if (saveButton) {
            saveButton.style.display = isProductDetailReadOnly ? 'none' : 'inline-block';
        }
    }

    function ensureGroupSelectValue(selectId, groupId) {
        const select = document.getElementById(selectId);
        if (!select) {
            return;
        }

        const normalizedValue = (groupId || '').trim();
        if (normalizedValue === '') {
            select.value = '';
            return;
        }

        const hasOption = Array.from(select.options).some((option) => option.value === normalizedValue);
        if (!hasOption) {
            const textLabel = productGroupNameMap[normalizedValue] ?
                `${normalizedValue} - ${productGroupNameMap[normalizedValue]}` : normalizedValue;
            const dynamicOption = new Option(textLabel, normalizedValue, true, true);
            dynamicOption.dataset.dynamic = '1';
            select.appendChild(dynamicOption);
        }

        select.value = normalizedValue;
    }

    function syncEditGroupName() {
        const groupSelect = document.getElementById('editProductGroupId');
        const groupNameInput = document.getElementById('editProductGroupName');
        if (!groupSelect || !groupNameInput) {
            return;
        }

        const selectedValue = groupSelect.value;
        groupNameInput.value = selectedValue && productGroupNameMap[selectedValue] ? productGroupNameMap[
            selectedValue] : '';
        maybeApplyImageFolderPreset('editProductImage', 'editProductGroupId');
    }

    function syncDetailGroupName() {
        const groupSelect = document.getElementById('detailMaNhomHang');
        const groupNameInput = document.getElementById('detailTenNhomHang');
        if (!groupSelect || !groupNameInput) {
            return;
        }

        const selectedValue = (groupSelect.value || '').trim();
        groupNameInput.value = selectedValue && productGroupNameMap[selectedValue] ? productGroupNameMap[
            selectedValue] : '';
    }

    function updateDetailComputedFromInputs() {
        const donGiaInput = document.getElementById('detailDonGia');
        const vatInput = document.getElementById('detailVat');
        const giaCoThueInput = document.getElementById('detailGiaCoThue');
        if (!donGiaInput || !vatInput || !giaCoThueInput) {
            return;
        }

        const donGia = parseFloat((donGiaInput.value || '').replace(/[^\d.]/g, '')) || 0;
        const vat = parseFloat((vatInput.value || '').replace(/[^\d.]/g, '')) || 0;
        const giaCoThue = Math.round(donGia * (1 + vat / 100));
        giaCoThueInput.value = giaCoThue > 0 ? giaCoThue.toLocaleString('vi-VN') : '';
    }

    function updateProductDetailImagePreview(imagePath) {
        const previewImage = document.getElementById('detailHinhAnhPreview');
        if (!previewImage) {
            return;
        }

        const fallback = previewImage.dataset.fallback || '../TrangUser/ack.png';
        const normalizedPath = (imagePath || '').trim();
        previewImage.src = normalizedPath !== '' ? normalizedPath : fallback;
    }

    // Show detail panel with data
    function showDetailPanel(maHang, maNhomHang, tenHang, hinhAnh, dvt, donGia, vat, giaCoThue = '', soLuongTon =
        '', trangThai = '', readOnly = false) {
        const detailPanel = document.getElementById('detailPanel');
        if (detailPanel) {
            detailPanel.classList.toggle('read-only-compact', !!readOnly);
        }

        document.getElementById('detailMaHang').value = maHang;
        ensureGroupSelectValue('detailMaNhomHang', maNhomHang);
        document.getElementById('detailTenNhomHang').value = productGroupNameMap[maNhomHang] || '';
        document.getElementById('detailTenHang').value = tenHang;
        document.getElementById('detailHinhAnh').value = hinhAnh || '';
        document.getElementById('detailDvt').value = dvt;
        document.getElementById('detailDonGia').value = donGia;
        document.getElementById('detailVat').value = vat;
        document.getElementById('detailGiaCoThue').value = giaCoThue;
        document.getElementById('detailSoLuongTon').value = soLuongTon;
        document.getElementById('detailTrangThai').value = trangThai;
        updateDetailComputedFromInputs();
        updateProductDetailImagePreview(hinhAnh || '');
        maybeApplyImageFolderPreset('detailHinhAnh', 'detailMaNhomHang');
        syncDetailGroupName();
        setProductDetailReadOnly(readOnly);
        document.getElementById('detailPanel').classList.add('show');
        document.getElementById('detailOverlay').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    // Close detail panel
    function closeDetailPanel() {
        document.getElementById('detailPanel').classList.remove('show', 'read-only-compact');
        document.getElementById('detailOverlay').classList.remove('show');
        document.body.style.overflow = '';
    }

    // Edit button click
    document.getElementById('btnEditProduct').addEventListener('click', function() {
        if (!selectedProductRow) {
            alert('Vui lòng chọn sản phẩm để sửa');
            return;
        }
        const cells = selectedProductRow.querySelectorAll('td');

        // Đổ dữ liệu đầy đủ vào modal
        const maHang = cells[0].textContent.trim();
        const maNhom = cells[1].textContent.trim();
        const tenNhom = cells[2].textContent.trim();
        const tenHang = cells[3].textContent.trim();
        const hinhAnh = selectedProductRow.getAttribute('data-image') || '';
        const dvt = cells[5].textContent.trim();
        const donGia = parseInt(cells[6].textContent.replace('₫', '').replace(/\D/g, '')) || 0;
        const vat = parseFloat(cells[7].textContent.replace(',', '.').replace(/[^\d.]/g, '')) || 0;
        const giaCoThue = parseInt(cells[8].textContent.replace('₫', '').replace(/\D/g, '')) || 0;
        const trangThai = cells[10].textContent.replace(/\s+/g, ' ').trim();

        document.getElementById('editProductId').value = maHang;
        ensureGroupSelectValue('editProductGroupId', maNhom);
        document.getElementById('editProductGroupName').value = tenNhom || productGroupNameMap[maNhom] || '';
        document.getElementById('editProductName').value = tenHang;
        document.getElementById('editProductImage').value = hinhAnh;
        document.getElementById('editProductUnit').value = dvt;
        document.getElementById('editProductPrice').value = donGia;
        document.getElementById('editProductVat').value = vat;
        document.getElementById('editProductTaxedPrice').value = giaCoThue;
        document.getElementById('editProductStatus').value = trangThai;

        // Đồng thời cập nhật các trường ẩn dùng để submit
        document.getElementById('editMaHang').value = maHang;
        document.getElementById('editMaNhomHang').value = maNhom;
        document.getElementById('editTenHang').value = tenHang;
        document.getElementById('editHinhAnh').value = hinhAnh;
        document.getElementById('editDvt').value = dvt;
        document.getElementById('editDonGia').value = donGia;
        document.getElementById('editVat').value = vat;

        updateEditModalComputed();

        // Mở modal
        var editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
        editModal.show();
    });

    document.getElementById('btnViewProduct').addEventListener('click', function() {
        if (!selectedProductRow) {
            alert('Vui lòng chọn sản phẩm để xem chi tiết');
            return;
        }

        const cells = selectedProductRow.querySelectorAll('td');
        const maHang = cells[0].textContent.trim();
        const maNhom = cells[1].textContent.trim();
        const tenHang = cells[3].textContent.trim();
        const hinhAnh = selectedProductRow.getAttribute('data-image') || '';
        const dvt = cells[5].textContent.trim();
        const donGia = parseInt(cells[6].textContent.replace('₫', '').replace(/\D/g, '')) || 0;
        const vat = parseFloat(cells[7].textContent.replace(',', '.').replace(/[^\d.]/g, '')) || 0;
        const giaCoThue = cells[8].textContent.replace('₫', '').trim();
        const soLuongTon = cells[9].textContent.trim();
        const trangThai = cells[10].textContent.replace(/\s+/g, ' ').trim();
        showDetailPanel(maHang, maNhom, tenHang, hinhAnh, dvt, donGia, vat, giaCoThue, soLuongTon, trangThai,
            true);
    });

    function updateEditModalComputed() {
        const donGia = parseFloat(document.getElementById('editProductPrice').value) || 0;
        const vat = parseFloat(document.getElementById('editProductVat').value) || 0;

        const giaCoThue = Math.round(donGia * (1 + vat / 100));
        document.getElementById('editProductTaxedPrice').value = giaCoThue;
    }

    function bindEditProductModalEvents() {
        const priceInput = document.getElementById('editProductPrice');
        const vatInput = document.getElementById('editProductVat');
        const groupSelect = document.getElementById('editProductGroupId');
        const addGroupSelect = document.getElementById('maNhomHang');
        const detailGroupSelect = document.getElementById('detailMaNhomHang');
        const detailImageInput = document.getElementById('detailHinhAnh');
        const detailDonGiaInput = document.getElementById('detailDonGia');
        const detailVatInput = document.getElementById('detailVat');
        const editForm = document.getElementById('editProductForm');

        if (priceInput) {
            priceInput.addEventListener('input', updateEditModalComputed);
        }
        if (vatInput) {
            vatInput.addEventListener('input', updateEditModalComputed);
        }
        if (groupSelect) {
            groupSelect.addEventListener('change', syncEditGroupName);
        }
        if (addGroupSelect) {
            addGroupSelect.addEventListener('change', function() {
                maybeApplyImageFolderPreset('hinhAnh', 'maNhomHang');
            });
            maybeApplyImageFolderPreset('hinhAnh', 'maNhomHang');
        }
        if (detailGroupSelect) {
            detailGroupSelect.addEventListener('change', function() {
                maybeApplyImageFolderPreset('detailHinhAnh', 'detailMaNhomHang');
                syncDetailGroupName();
                updateProductDetailImagePreview(document.getElementById('detailHinhAnh')?.value || '');
            });
        }

        if (detailImageInput) {
            detailImageInput.addEventListener('input', function() {
                updateProductDetailImagePreview(detailImageInput.value);
            });
        }

        if (detailDonGiaInput) {
            detailDonGiaInput.addEventListener('input', updateDetailComputedFromInputs);
        }

        if (detailVatInput) {
            detailVatInput.addEventListener('input', updateDetailComputedFromInputs);
        }

        if (editForm) {
            // Khi submit modal sửa, đổ giá trị vào form ẩn và submit
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Lấy giá trị từ modal
                const id = document.getElementById('editProductId').value.trim();
                const groupId = document.getElementById('editProductGroupId').value.trim();
                const name = document.getElementById('editProductName').value.trim();
                const image = document.getElementById('editProductImage').value.trim();
                const unit = document.getElementById('editProductUnit').value.trim();
                const price = document.getElementById('editProductPrice').value;
                const vat = document.getElementById('editProductVat').value;

                if (!name) {
                    alert('Tên sản phẩm không được để trống');
                    return;
                }

                if (!unit) {
                    alert('Đơn vị tính không được để trống');
                    return;
                }

                if (!groupId) {
                    alert('Vui lòng chọn nhóm hàng');
                    return;
                }

                // Cập nhật các trường ẩn
                document.getElementById('editMaHang').value = id;
                document.getElementById('editMaNhomHang').value = groupId;
                document.getElementById('editTenHang').value = name;
                document.getElementById('editHinhAnh').value = image;
                document.getElementById('editDvt').value = unit;
                document.getElementById('editDonGia').value = price;
                document.getElementById('editVat').value = vat;

                // Submit form ẩn
                document.getElementById('productEditForm').submit();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindEditProductModalEvents, {
            once: true
        });
    } else {
        bindEditProductModalEvents();
    }

    // Detail Save button
    document.getElementById('btnDetailSave').addEventListener('click', function() {
        if (isProductDetailReadOnly) {
            return;
        }

        const maHang = document.getElementById('detailMaHang').value;
        const maNhomHang = document.getElementById('detailMaNhomHang').value.trim();
        const tenHang = document.getElementById('detailTenHang').value.trim();
        const hinhAnh = document.getElementById('detailHinhAnh').value.trim();
        const dvt = document.getElementById('detailDvt').value.trim();
        const donGia = document.getElementById('detailDonGia').value.trim();
        const vat = document.getElementById('detailVat').value.trim();

        if (!tenHang) {
            alert('Tên hàng không được để trống');
            return;
        }

        if (!maNhomHang) {
            alert('Vui lòng chọn nhóm hàng');
            return;
        }

        document.getElementById('editMaHang').value = maHang;
        document.getElementById('editMaNhomHang').value = maNhomHang;
        document.getElementById('editTenHang').value = tenHang;
        document.getElementById('editHinhAnh').value = hinhAnh;
        document.getElementById('editDvt').value = dvt;
        document.getElementById('editDonGia').value = donGia;
        document.getElementById('editVat').value = vat;
        document.getElementById('productEditForm').submit();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDetailPanel();
        }
    });

    document.getElementById('addProductModal').addEventListener('show.bs.modal', function() {
        closeDetailPanel();
    });

    // Delete button click
    document.getElementById('btnDeleteProduct').addEventListener('click', function() {
        if (!selectedProductRow) {
            alert('Vui lòng chọn sản phẩm để xóa');
            return;
        }

        pendingDeleteProductId = selectedProductRow.getAttribute('data-id');
        const productName = selectedProductRow.querySelectorAll('td')[3]?.textContent.trim() || '';
        const deleteNameEl = document.getElementById('deleteProductName');
        if (deleteNameEl) {
            deleteNameEl.textContent = productName || pendingDeleteProductId;
        }

        const deleteModalEl = document.getElementById('deleteProductModal');
        if (!deleteModalEl) {
            alert('Không tìm thấy hộp xác nhận xóa.');
            return;
        }

        const deleteModal = bootstrap.Modal.getOrCreateInstance(deleteModalEl);
        deleteModal.show();
    });

    document.addEventListener('click', function(event) {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const confirmDeleteBtn = target.closest('#btnConfirmDeleteProduct');
        if (!confirmDeleteBtn) {
            return;
        }

        if (!pendingDeleteProductId) {
            return;
        }

        const deleteModalEl = document.getElementById('deleteProductModal');
        if (deleteModalEl) {
            const deleteModal = bootstrap.Modal.getInstance(deleteModalEl);
            if (deleteModal) {
                deleteModal.hide();
            }
        }

        deleteProduct(pendingDeleteProductId);
    });

    document.addEventListener('hidden.bs.modal', function(event) {
        const target = event.target;
        if (target && target.id === 'deleteProductModal') {
            pendingDeleteProductId = null;
        }
    });

    function deleteProduct(id) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="crud_action" value="delete_product">
            <input type="hidden" name="ma_hang" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    function syncFixedTopOffset() {
        const fixedTop = document.querySelector('.product-top-sticky');
        const contentOffset = document.getElementById('productContentOffset');
        if (!fixedTop || !contentOffset) {
            return;
        }

        const topHeight = Math.ceil(fixedTop.getBoundingClientRect().height);
        contentOffset.style.top = `${topHeight + 10}px`;
    }

    window.addEventListener('resize', syncFixedTopOffset);
    window.addEventListener('load', syncFixedTopOffset);
    syncFixedTopOffset();

    window.addEventListener('load', function() {
        const params = new URLSearchParams(window.location.search);
        const productIdFromUrl = (params.get('edit') || '').trim();
        if (!productIdFromUrl) {
            return;
        }

        const rows = Array.from(document.querySelectorAll('.product-row'));
        const targetRow = rows.find((row) => (row.getAttribute('data-id') || '').trim() === productIdFromUrl);
        if (!targetRow) {
            return;
        }

        targetRow.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
        targetRow.click();

        setTimeout(() => {
            const editButton = document.getElementById('btnEditProduct');
            if (editButton && !editButton.disabled) {
                editButton.click();
            }
        }, 220);
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="admin-search.js"></script>
</body>

<!-- Modal Sửa Sản Phẩm -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProductModalLabel">Sửa sản phẩm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editProductForm">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="editProductId" class="form-label">Mã hàng</label>
                            <input type="text" class="form-control" id="editProductId" name="id" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="editProductGroupId" class="form-label">Mã nhóm hàng</label>
                            <select class="form-select" id="editProductGroupId" required>
                                <option value="">Chọn nhóm hàng</option>
                                <?php foreach ($nhomHangMap as $nhomCode => $nhomName): ?>
                                <option value="<?php echo htmlspecialchars($nhomCode); ?>">
                                    <?php echo htmlspecialchars($nhomCode . ' - ' . $nhomName); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editProductGroupName" class="form-label">Tên nhóm hàng</label>
                            <input type="text" class="form-control" id="editProductGroupName" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="editProductName" class="form-label">Tên sản phẩm</label>
                            <input type="text" class="form-control" id="editProductName" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editProductImage" class="form-label">Link ảnh</label>
                            <input type="text" class="form-control" id="editProductImage" list="productImageFolderList"
                                placeholder="VD: ../file anh/AnhNuocNgot/ten-anh.png">
                        </div>
                        <div class="col-md-6">
                            <label for="editProductUnit" class="form-label">Đơn vị tính</label>
                            <input type="text" class="form-control" id="editProductUnit" required>
                        </div>
                        <div class="col-md-4">
                            <label for="editProductPrice" class="form-label">Giá</label>
                            <input type="number" min="0" class="form-control" id="editProductPrice" name="price"
                                required>
                        </div>
                        <div class="col-md-4">
                            <label for="editProductVat" class="form-label">VAT (%)</label>
                            <input type="number" min="0" max="100" class="form-control" id="editProductVat" required>
                        </div>
                        <div class="col-md-4">
                            <label for="editProductTaxedPrice" class="form-label">Giá có thuế</label>
                            <input type="number" class="form-control" id="editProductTaxedPrice" readonly>
                        </div>
                        <div class="col-md-8">
                            <label for="editProductStatus" class="form-label">Trạng thái</label>
                            <input type="text" class="form-control" id="editProductStatus" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Xác nhận Xóa Sản Phẩm -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProductModalLabel">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Bạn có chắc chắn muốn xóa sản phẩm <strong id="deleteProductName"></strong> không?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDeleteProduct">Xóa</button>
            </div>
        </div>
    </div>
</div>

</html>