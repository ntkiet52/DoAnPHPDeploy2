<?php
require_once __DIR__ . '/../Login/admin_auth.php';

function pickNhapValue(array $row, array $keys, $default = '') {
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

function normalizeNhapText(string $value): string {
    return mb_strtolower(trim($value), 'UTF-8');
}

function generateNextNhapId(PDO $pdo): string {
    $rows = $pdo->query("SELECT IdPhieuNhap FROM phieunhap")->fetchAll(PDO::FETCH_COLUMN);
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

    return 'PN' . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
}

function detectHangHoaStockColumn(PDO $pdo): ?string {
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM hanghoa")->fetchAll(PDO::FETCH_ASSOC);
        $columnMap = [];
        foreach ($columns as $column) {
            $field = (string) ($column['Field'] ?? '');
            if ($field !== '') {
                $columnMap[strtolower($field)] = $field;
            }
        }

        foreach (['soluongton', 'soluong_ton', 'tonkho'] as $candidate) {
            if (isset($columnMap[$candidate])) {
                return $columnMap[$candidate];
            }
        }
    } catch (Throwable $ignored) {
    }

    return null;
}

function syncInventoryFromImportDetails(PDO $pdo, ?string $stockColumn): void {
    if ($stockColumn === null || $stockColumn === '') {
        return;
    }

    $safeColumn = '`' . str_replace('`', '``', $stockColumn) . '`';

    $pdo->exec("UPDATE hanghoa SET {$safeColumn} = 0");
    $pdo->exec(
        "UPDATE hanghoa h
         LEFT JOIN (
            SELECT MaHang, COALESCE(SUM(SoLuongNhap), 0) AS TongNhap
            FROM chitietnhaphang
            GROUP BY MaHang
         ) ct ON h.MaHang = ct.MaHang
         SET h.{$safeColumn} = COALESCE(ct.TongNhap, 0)"
    );
}

function parseNhapVatPercent($vatValue): float {
    $vatText = trim((string) $vatValue);
    if ($vatText === '') {
        return 0.0;
    }

    $normalized = str_replace(',', '.', $vatText);
    $normalized = str_replace('%', '', $normalized);
    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

function resolveGiaCoThueFromHangHoaRow(array $row): float {
    $giaCoThue = (float) pickNhapValue($row, ['sotiencothue', 'so_tien_co_thue', 'giacothue', 'gia_co_thue'], 0);
    if ($giaCoThue > 0) {
        return $giaCoThue;
    }

    $donGia = (float) pickNhapValue($row, ['dongia', 'don_gia', 'gia'], 0);
    $vatPercent = parseNhapVatPercent(pickNhapValue($row, ['vat', 'thuevat', 'thue_vat'], 0));

    if ($donGia <= 0) {
        return 0;
    }

    return round($donGia * (1 + ($vatPercent / 100)), 2);
}

$phieuNhaps = [];
$importItems = [];
$dbError = '';
$crudMessage = '';
$crudError = '';
$nextImportId = '';
$tongMatHangTongCong = 0;
$tongSoLuongTongCong = 0;
$tongChiPhiTongCong = 0;
$nhomHangMap = [];

$selectedMaPhieu = isset($_GET['maPhieu']) ? trim((string) $_GET['maPhieu']) : '';

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

    $nhanVienMap = [];
    $hangHoaStockColumn = detectHangHoaStockColumn($pdo);
    $nhanVienNameMap = [];
    try {
        $nhanVienRows = $pdo->query("SELECT * FROM nhanvien")->fetchAll();
        foreach ($nhanVienRows as $nv) {
            $maNhanVien = (string) pickNhapValue($nv, ['manv', 'manhanvien', 'ma_nhan_vien', 'id']);
            $tenNhanVien = (string) pickNhapValue($nv, ['tennv', 'tennhanvien', 'ten_nhan_vien', 'hoten', 'ten', 'name']);
            if ($maNhanVien !== '') {
                $nhanVienMap[$maNhanVien] = $tenNhanVien;
                if ($tenNhanVien !== '') {
                    $nhanVienNameMap[normalizeNhapText($tenNhanVien)] = $maNhanVien;
                }
            }
        }
    } catch (Throwable $ignored) {
    }

    $nhaCungCapMap = [];
    $nhaCungCapNameMap = [];
    try {
        $nccRows = $pdo->query("SELECT * FROM nhacc")->fetchAll();
        foreach ($nccRows as $ncc) {
            $maNcc = (string) pickNhapValue($ncc, ['mancc', 'ma_ncc', 'manhacungcap', 'id']);
            $tenNcc = (string) pickNhapValue($ncc, ['tenncc', 'ten_ncc', 'tennhacungcap', 'name']);
            if ($maNcc !== '') {
                $nhaCungCapMap[$maNcc] = $tenNcc;
                if ($tenNcc !== '') {
                    $nhaCungCapNameMap[normalizeNhapText($tenNcc)] = $maNcc;
                }
            }
        }
    } catch (Throwable $ignored) {
    }

    try {
        $nhomRows = $pdo->query("SELECT * FROM nhomhang")->fetchAll();
        foreach ($nhomRows as $nhom) {
            $maNhom = (string) pickNhapValue($nhom, ['manhomhang', 'ma_nhom_hang', 'manhom', 'id'], '');
            $tenNhom = (string) pickNhapValue($nhom, ['tennhomhang', 'ten_nhom_hang', 'tennhom', 'name'], '');
            if ($maNhom !== '') {
                $nhomHangMap[$maNhom] = $tenNhom;
            }
        }
    } catch (Throwable $ignored) {
    }

    $hangHoaMap = [];
    $hangHoaGiaCoThueMap = [];
    $hangHoaNhomMap = [];
    try {
        $hangRows = $pdo->query("SELECT * FROM hanghoa")->fetchAll();
        foreach ($hangRows as $hh) {
            $maHang = (string) pickNhapValue($hh, ['mahang', 'ma_hang', 'id']);
            $tenHang = (string) pickNhapValue($hh, ['tenhang', 'ten_hang', 'name']);
            $maNhomHang = (string) pickNhapValue($hh, ['manhomhang', 'ma_nhom_hang', 'manhom'], '');
            if ($maHang === '') {
                continue;
            }

            $hangHoaMap[$maHang] = $tenHang;
            $hangHoaGiaCoThueMap[$maHang] = resolveGiaCoThueFromHangHoaRow($hh);
            $hangHoaNhomMap[$maHang] = $maNhomHang;
        }
    } catch (Throwable $ignored) {
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $action = trim((string) ($_POST['crud_action'] ?? ''));

            if ($action === 'add_import') {
                $maPhieu = trim((string) ($_POST['ma_phieu'] ?? ''));
                $kyHieu = trim((string) ($_POST['ky_hieu'] ?? ''));
                $nhanVienInput = trim((string) ($_POST['nhan_vien'] ?? ''));
                $nhaCungCapInput = trim((string) ($_POST['nha_cung_cap'] ?? ''));
                $ngayNhap = trim((string) ($_POST['ngay_nhap'] ?? ''));

                if ($maPhieu === '') {
                    $maPhieu = generateNextNhapId($pdo);
                }

                $maNv = isset($nhanVienMap[$nhanVienInput])
                    ? $nhanVienInput
                    : ($nhanVienNameMap[normalizeNhapText($nhanVienInput)] ?? '');
                $maNcc = isset($nhaCungCapMap[$nhaCungCapInput])
                    ? $nhaCungCapInput
                    : ($nhaCungCapNameMap[normalizeNhapText($nhaCungCapInput)] ?? '');

                if ($maPhieu === '' || $kyHieu === '' || $ngayNhap === '' || $maNv === '' || $maNcc === '') {
                    $crudError = 'Dữ liệu thêm phiếu nhập chưa hợp lệ hoặc không tìm thấy nhân viên/nhà cung cấp tương ứng.';
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO phieunhap (IdPhieuNhap, MaNV, MaNCC, KyHieu, NgayNhap) VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([$maPhieu, $maNv, $maNcc, $kyHieu, $ngayNhap . ' 00:00:00']);
                    $selectedMaPhieu = $maPhieu;
                    $crudMessage = 'Đã thêm phiếu nhập thành công.';
                }
            }

            if ($action === 'update_import') {
                $maPhieu = trim((string) ($_POST['ma_phieu'] ?? ''));
                $kyHieu = trim((string) ($_POST['ky_hieu'] ?? ''));
                $nhanVienInput = trim((string) ($_POST['nhan_vien'] ?? ''));
                $nhaCungCapInput = trim((string) ($_POST['nha_cung_cap'] ?? ''));
                $ngayNhap = trim((string) ($_POST['ngay_nhap'] ?? ''));

                $maNv = isset($nhanVienMap[$nhanVienInput])
                    ? $nhanVienInput
                    : ($nhanVienNameMap[normalizeNhapText($nhanVienInput)] ?? '');
                $maNcc = isset($nhaCungCapMap[$nhaCungCapInput])
                    ? $nhaCungCapInput
                    : ($nhaCungCapNameMap[normalizeNhapText($nhaCungCapInput)] ?? '');

                if ($maPhieu === '' || $kyHieu === '' || $ngayNhap === '' || $maNv === '' || $maNcc === '') {
                    $crudError = 'Dữ liệu cập nhật phiếu nhập chưa hợp lệ hoặc không tìm thấy nhân viên/nhà cung cấp tương ứng.';
                } else {
                    $stmt = $pdo->prepare(
                        "UPDATE phieunhap SET MaNV = ?, MaNCC = ?, KyHieu = ?, NgayNhap = ? WHERE IdPhieuNhap = ?"
                    );
                    $stmt->execute([$maNv, $maNcc, $kyHieu, $ngayNhap . ' 00:00:00', $maPhieu]);
                    $selectedMaPhieu = $maPhieu;
                    $crudMessage = 'Đã cập nhật phiếu nhập thành công.';
                }
            }

            if ($action === 'delete_import') {
                $maPhieu = trim((string) ($_POST['ma_phieu'] ?? ''));

                if ($maPhieu === '') {
                    $crudError = 'Không xác định được phiếu nhập để xóa.';
                } else {
                    $deleteDetails = $pdo->prepare("DELETE FROM chitietnhaphang WHERE IdPhieuNhap = ?");
                    $deleteDetails->execute([$maPhieu]);

                    $deleteImport = $pdo->prepare("DELETE FROM phieunhap WHERE IdPhieuNhap = ?");
                    $deleteImport->execute([$maPhieu]);

                    syncInventoryFromImportDetails($pdo, $hangHoaStockColumn);

                    if ($selectedMaPhieu === $maPhieu) {
                        $selectedMaPhieu = '';
                    }

                    $crudMessage = 'Đã xóa phiếu nhập thành công.';
                }
            }

            if ($action === 'add_import_item') {
                $maPhieu = trim((string) ($_POST['ma_phieu'] ?? ''));
                $maHang = trim((string) ($_POST['ma_hang'] ?? ''));
                $soLuong = (int) ($_POST['so_luong'] ?? 0);
                $chietKhau = (float) ($_POST['chiet_khau'] ?? 0);
                $giaNhap = (float) ($hangHoaGiaCoThueMap[$maHang] ?? 0);

                if ($maPhieu === '' || $maHang === '' || $soLuong <= 0 || $chietKhau < 0 || $chietKhau > 100) {
                    $crudError = 'Dữ liệu thêm mặt hàng nhập chưa hợp lệ.';
                } elseif ($giaNhap <= 0) {
                    $crudError = 'Không xác định được giá có thuế của mặt hàng đã chọn.';
                } else {
                    $existingStmt = $pdo->prepare(
                        "SELECT GiaNhap, SoLuongNhap, ChietKhau
                         FROM chitietnhaphang
                         WHERE IdPhieuNhap = ? AND MaHang = ?"
                    );
                    $existingStmt->execute([$maPhieu, $maHang]);
                    $existing = $existingStmt->fetch();

                    if ($existing) {
                        $newSoLuong = (int) ($existing['SoLuongNhap'] ?? 0) + $soLuong;
                        $thanhTien = $giaNhap * $newSoLuong;
                        $tongTienNhap = $thanhTien - ($thanhTien * ($chietKhau / 100));

                        $updateStmt = $pdo->prepare(
                            "UPDATE chitietnhaphang
                             SET GiaNhap = ?, SoLuongNhap = ?, ThanhTien = ?, ChietKhau = ?, TongTienNhap = ?
                             WHERE IdPhieuNhap = ? AND MaHang = ?"
                        );
                        $updateStmt->execute([$giaNhap, $newSoLuong, $thanhTien, $chietKhau, $tongTienNhap, $maPhieu, $maHang]);
                        $crudMessage = 'Mặt hàng đã tồn tại, hệ thống đã cộng dồn số lượng nhập.';
                    } else {
                        $thanhTien = $giaNhap * $soLuong;
                        $tongTienNhap = $thanhTien - ($thanhTien * ($chietKhau / 100));

                        $insertStmt = $pdo->prepare(
                            "INSERT INTO chitietnhaphang (IdPhieuNhap, MaHang, GiaNhap, SoLuongNhap, ThanhTien, ChietKhau, TongTienNhap)
                             VALUES (?, ?, ?, ?, ?, ?, ?)"
                        );
                        $insertStmt->execute([$maPhieu, $maHang, $giaNhap, $soLuong, $thanhTien, $chietKhau, $tongTienNhap]);
                        $crudMessage = 'Đã thêm mặt hàng vào phiếu nhập thành công.';
                    }

                    syncInventoryFromImportDetails($pdo, $hangHoaStockColumn);
                    $selectedMaPhieu = $maPhieu;
                }
            }

            if ($action === 'update_import_item') {
                $maPhieu = trim((string) ($_POST['ma_phieu'] ?? ''));
                $maHang = trim((string) ($_POST['ma_hang'] ?? ''));
                $soLuong = (int) ($_POST['so_luong'] ?? 0);
                $chietKhau = (float) ($_POST['chiet_khau'] ?? 0);
                $giaNhap = (float) ($hangHoaGiaCoThueMap[$maHang] ?? 0);

                if ($maPhieu === '' || $maHang === '') {
                    $crudError = 'Không xác định được chi tiết nhập để cập nhật.';
                } elseif ($soLuong <= 0 || $chietKhau < 0 || $chietKhau > 100) {
                    $crudError = 'Dữ liệu cập nhật chi tiết nhập hàng không hợp lệ.';
                } elseif ($giaNhap <= 0) {
                    $crudError = 'Không xác định được giá có thuế của mặt hàng đã chọn.';
                } else {
                    $thanhTien = $giaNhap * $soLuong;
                    $tongTienNhap = $thanhTien - ($thanhTien * ($chietKhau / 100));

                    $stmt = $pdo->prepare(
                        "UPDATE chitietnhaphang
                         SET GiaNhap = ?, SoLuongNhap = ?, ThanhTien = ?, ChietKhau = ?, TongTienNhap = ?
                         WHERE IdPhieuNhap = ? AND MaHang = ?"
                    );
                    $stmt->execute([$giaNhap, $soLuong, $thanhTien, $chietKhau, $tongTienNhap, $maPhieu, $maHang]);
                    syncInventoryFromImportDetails($pdo, $hangHoaStockColumn);
                    $selectedMaPhieu = $maPhieu;
                    $crudMessage = 'Đã cập nhật chi tiết nhập hàng thành công.';
                }
            }

            if ($action === 'delete_import_item') {
                $maPhieu = trim((string) ($_POST['ma_phieu'] ?? ''));
                $maHang = trim((string) ($_POST['ma_hang'] ?? ''));

                if ($maPhieu === '' || $maHang === '') {
                    $crudError = 'Không xác định được mặt hàng nhập để xóa.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM chitietnhaphang WHERE IdPhieuNhap = ? AND MaHang = ?");
                    $stmt->execute([$maPhieu, $maHang]);
                    syncInventoryFromImportDetails($pdo, $hangHoaStockColumn);
                    $selectedMaPhieu = $maPhieu;
                    $crudMessage = 'Đã xóa mặt hàng nhập thành công.';
                }
            }
        } catch (Throwable $postError) {
            $crudError = $postError->getMessage();
        }
    }

    $phieuRows = $pdo->query("SELECT * FROM phieunhap")->fetchAll();

    foreach ($phieuRows as $row) {
        $maPhieu = (string) pickNhapValue($row, ['idphieunhap', 'maphieunhap', 'maphieu', 'ma_phieu', 'id']);
        $kyHieu = (string) pickNhapValue($row, ['kyhieu', 'ky_hieu', 'kyhieuphieu'], '');

        $maNhanVien = (string) pickNhapValue($row, ['manv', 'manhanvien', 'ma_nhan_vien'], '');
        $nhanVien = (string) pickNhapValue($row, ['tennv', 'tennhanvien', 'ten_nhan_vien', 'nhanvien'], '');
        if ($nhanVien === '' && isset($nhanVienMap[$maNhanVien])) {
            $nhanVien = $nhanVienMap[$maNhanVien];
        }

        $maNcc = (string) pickNhapValue($row, ['mancc', 'ma_ncc', 'manhacungcap'], '');
        $nhaCungCap = (string) pickNhapValue($row, ['tenncc', 'ten_ncc', 'nhacungcap'], '');
        if ($nhaCungCap === '' && isset($nhaCungCapMap[$maNcc])) {
            $nhaCungCap = $nhaCungCapMap[$maNcc];
        }

        $ngayNhapRaw = (string) pickNhapValue($row, ['ngaynhap', 'ngay_nhap', 'ngaylap'], '');
        $ngayNhap = $ngayNhapRaw;
        if ($ngayNhapRaw !== '') {
            $timestamp = strtotime($ngayNhapRaw);
            if ($timestamp !== false) {
                $ngayNhap = date('d/m/Y', $timestamp);
            }
        }

        $phieuNhaps[] = [
            'MaPhieu' => $maPhieu,
            'MaNV' => $maNhanVien,
            'NhanVien' => $nhanVien,
            'KyHieu' => $kyHieu,
            'MaNCC' => $maNcc,
            'NhaCungCap' => $nhaCungCap,
            'NgayNhap' => $ngayNhap,
        ];
    }

    $nextImportId = generateNextNhapId($pdo);

    if ($selectedMaPhieu === '' && count($phieuNhaps) > 0) {
        $selectedMaPhieu = $phieuNhaps[0]['MaPhieu'];
    }

    $chiTietRows = $pdo->query("SELECT * FROM chitietnhaphang")->fetchAll();
    foreach ($chiTietRows as $row) {
        $maPhieuChiTiet = (string) pickNhapValue($row, ['idphieunhap', 'maphieunhap', 'maphieu', 'ma_phieu'], '');
        $maHang = (string) pickNhapValue($row, ['mahang', 'ma_hang', 'idhanghoa'], '');
        $tenHang = (string) pickNhapValue($row, ['tenhang', 'ten_hang'], '');
        if ($tenHang === '' && isset($hangHoaMap[$maHang])) {
            $tenHang = $hangHoaMap[$maHang];
        }

        $giaNhap = (float) pickNhapValue($row, ['gianhap', 'gia_nhap', 'dongia', 'don_gia', 'gia'], 0);
        $soLuong = (int) pickNhapValue($row, ['soluongnhap', 'so_luong_nhap', 'soluong', 'so_luong'], 0);
        $chietKhau = (float) pickNhapValue($row, ['chietkhau', 'chiet_khau'], 0);

        $thanhTienGocTong = $giaNhap * $soLuong;
        $soTienChietKhauTong = $thanhTienGocTong * ($chietKhau / 100);
        $tongMatHangTongCong += 1;
        $tongSoLuongTongCong += $soLuong;
        $tongChiPhiTongCong += ($thanhTienGocTong - $soTienChietKhauTong);

        if ($selectedMaPhieu !== '' && $maPhieuChiTiet !== $selectedMaPhieu) {
            continue;
        }

        $importItems[] = [
            'MaHang' => $maHang,
            'TenHang' => $tenHang,
            'GiaNhap' => $giaNhap,
            'SoLuong' => $soLuong,
            'ChietKhau' => $chietKhau,
        ];
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$tongMatHang = count($importItems);
$tongSoLuong = 0;
$tongChiPhi = 0;

foreach ($importItems as $item) {
    $tongSoLuong += $item['SoLuong'];

    $thanhTienGoc = $item['GiaNhap'] * $item['SoLuong'];
    $soTienChietKhau = $thanhTienGoc * ($item['ChietKhau'] / 100);
    $tongChiPhi += ($thanhTienGoc - $soTienChietKhau);
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Admin - Quản lý nhập hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --primary-blue: #0d6efd;
        --bg-light: #f8f9fa;
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
        color: #333;
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
        box-shadow: 0 4px 6px rgba(13, 110, 253, 0.3);
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

    .import-top-sticky {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        z-index: 1030;
        background: var(--bg-light);
        padding: 20px 30px 12px;
        border-bottom: none;
        box-shadow: none;
    }

    .import-top-sticky .alert {
        margin-bottom: 10px;
    }

    .import-content-offset {
        position: fixed;
        left: calc(var(--sidebar-width) + 18px);
        right: 18px;
        top: 340px;
        bottom: 12px;
        overflow: auto;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    body.modal-open #importContentOffset {
        overflow: visible;
    }

    /* --- HEADER & SEARCH --- */
    .search-input {
        border-radius: 20px;
        padding: 8px 20px;
        border: 1px solid #ced4da;
        width: 280px;
        font-size: 0.9rem;
    }

    /* --- STAT CARDS --- */
    .stat-card {
        border-radius: 8px;
        padding: 20px;
        border: none;
    }

    .bg-blue-light {
        background-color: #dbeafe;
    }

    .bg-green-light {
        background-color: #d1e7dd;
    }

    .bg-red-light {
        background-color: #f8d7da;
    }

    .stat-label {
        color: #6c757d;
        font-size: 0.9rem;
        font-weight: 500;
        margin-bottom: 5px;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
    }

    .text-dark-blue {
        color: #1e3a8a;
    }

    .text-success-custom {
        color: #198754;
    }

    .text-danger-custom {
        color: #dc3545;
    }

    .btn-add {
        background-color: var(--primary-blue);
        color: white;
        border-radius: 6px;
        padding: 10px 20px;
        border: none;
        font-weight: 500;
    }

    .btn-add:hover {
        background-color: #0b5ed7;
        color: white;
    }

    /* --- BẢNG CHUNG --- */
    .section-card {
        background: white;
        border-radius: 8px;
        padding: 14px 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        margin-bottom: 0;
        min-height: auto;
        display: flex;
        flex-direction: column;
        flex: 0 0 auto;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 15px;
    }

    .import-table-scroll {
        flex: 0 0 auto;
        min-height: auto;
        overflow: visible;
    }

    .table-plain {
        width: 100%;
        border-collapse: collapse;
    }

    .table-plain thead th {
        font-weight: 700;
        color: #333;
        padding: 12px 10px;
        border-bottom: 2px solid #eee;
        text-align: left;
        position: sticky;
        top: 0;
        z-index: 6;
        background: #fff;
    }

    .table-plain tbody td {
        padding: 12px 10px;
        vertical-align: middle;
        border-bottom: 1px solid #eee;
        color: #555;
    }

    .table-plain tbody tr {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .table-plain tbody tr:hover {
        background-color: #f8f9fa;
    }

    .table-plain tbody tr.selected {
        background-color: #ffffff;
        border-left: none;
        box-shadow: inset 4px 0 0 #0069d9;
    }

    .table-plain tbody tr.selected td:first-child {
        padding-left: 10px;
    }

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
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: min(700px, 92vw);
        max-height: 85vh;
        overflow-y: auto;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.18);
        border: 1px solid #e9ecef;
        z-index: 1055;
    }

    .detail-panel.show {
        display: block;
        animation: slideDown 0.25s ease-out;
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
        padding: 18px 20px;
        border-bottom: 1px solid #edf1f5;
    }

    .detail-header h5 {
        margin: 0;
        font-weight: 700;
        color: #344767;
    }

    .detail-content {
        padding: 20px;
        display: grid;
        grid-template-columns: repeat(2, minmax(220px, 1fr));
        gap: 14px;
    }

    .detail-field {
        display: flex;
        flex-direction: column;
    }

    .detail-field label {
        font-size: 0.82rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .detail-field input {
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 10px 12px;
        background: #f8f9fa;
        color: #344767;
    }

    .detail-field input:focus {
        outline: none;
        border-color: #667eea;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .detail-field input[readonly] {
        background: #e9ecef;
        color: #6c757d;
        cursor: not-allowed;
    }

    .detail-related-wrap {
        grid-column: 1 / -1;
    }

    .detail-related-wrap .table {
        margin-bottom: 0;
    }

    .detail-related-wrap .table thead th {
        font-size: 0.85rem;
        white-space: nowrap;
    }

    .detail-related-wrap .table tbody td {
        font-size: 0.9rem;
    }

    .detail-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        position: sticky;
        bottom: 0;
        padding: 14px 20px;
        border-top: 1px solid #edf1f5;
        background: #fff;
    }

    /* --- BẢNG CHI TIẾT HÀNG HÓA --- */
    .import-section {
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        margin-top: 0;
        overflow: hidden;
        min-height: auto;
        display: flex;
        flex-direction: column;
        flex: 0 0 auto;
    }

    .import-items-table-scroll {
        flex: 0 0 auto;
        min-height: auto;
        overflow: visible;
    }

    .info-header {
        background-color: white;
        /* Thay đổi thành màu trắng */
        color: #344767;
        padding: 12px 20px;
        font-weight: 600;
        font-size: 1.05rem;
        display: flex;
        align-items: center;
    }

    .ma-phieu-box {
        background: white;
        border: 1px solid #999;
        padding: 2px 10px;
        border-radius: 4px;
        margin-left: 10px;
        font-weight: bold;
        font-size: 0.95rem;
        color: #333;
    }

    .table-custom {
        margin-bottom: 0;
        width: 100%;
        border-collapse: collapse;
    }

    .table-custom thead th {
        background-color: white;
        /* Thay đổi thành màu trắng */
        color: #333;
        /* Thay đổi thành màu tối để hiển thị rõ trên nền trắng */
        font-weight: 700;
        padding: 12px 20px;
        border-bottom: 2px solid #eee;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 6;
    }

    .table-custom tbody td {
        padding: 15px 20px;
        vertical-align: middle;
        color: #344767;
        border-bottom: 1px solid #f0f2f5;
    }

    .table-custom tbody tr.import-item-row {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .table-custom tbody tr.import-item-row:hover {
        background-color: #f8f9fa;
    }

    .table-custom tbody tr.import-item-row.selected {
        background-color: #ffffff;
        box-shadow: inset 4px 0 0 #0069d9;
        border-left: none;
    }

    .table-custom tbody tr.import-item-row.selected td:first-child {
        padding-left: 20px;
    }

    .table-custom tbody tr.import-total-row {
        cursor: default;
    }

    @media (max-width: 768px) {
        .import-top-sticky {
            padding: 16px;
        }

        .import-content-offset {
            left: calc(var(--sidebar-width) + 16px);
            right: 16px;
            bottom: 16px;
            gap: 8px;
        }
    }
    </style>
    <link rel="stylesheet" href="admin-unified-ui.css?v=20260414-2">
</head>

<body>

    <div class="sidebar">
        <div class="brand-logo">
            <img src="../TrangUser/ack.png" alt="Logo" height="40" onerror="this.src='https://via.placeholder.com/40'">
            <h4 class="fw-bold ms-2 mb-0" style="color: #344767;">Admin</h4>
        </div>
        <nav>
            <a href="admin.php" class="nav-item"><i class="fas fa-chart-bar"></i> Tổng quan</a>
            <a href="admin-sanpham.php" class="nav-item"><i class="fas fa-box"></i> Sản phẩm</a>
            <a href="admin-nhomhang.php" class="nav-item"><i class="fas fa-folder"></i> Nhóm hàng</a>
            <a href="admin-nhaphang.php" class="nav-item active"><i class="fas fa-truck-loading"></i> Nhập hàng</a>
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
        <div class="product-top-sticky import-top-sticky">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-primary mb-0">Bảng điều khiển Admin</h4>
                <button class="btn btn-light rounded-circle border shadow-sm btn-sm"
                    style="width: 32px; height: 32px;"><i class="fas fa-times"></i></button>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Quản lý nhập hàng</h3>
                <input type="text" class="search-input" placeholder="Tìm kiếm mã/tên hàng...">
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

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card bg-blue-light">
                        <div class="stat-label">Tổng mặt hàng nhập (tổng cộng)</div>
                        <div class="stat-value text-dark-blue" id="summaryTongMatHang">
                            <?php echo $tongMatHangTongCong; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card bg-green-light">
                        <div class="stat-label">Tổng số lượng (tổng cộng)</div>
                        <div class="stat-value text-success-custom" id="summaryTongSoLuong">
                            <?php echo $tongSoLuongTongCong; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card bg-red-light">
                        <div class="stat-label">Tổng chi phí (tổng cộng)</div>
                        <div class="stat-value text-danger-custom" id="summaryTongChiPhi">
                            <?php echo number_format($tongChiPhiTongCong, 0, ',', '.'); ?> ₫</div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addImportModal"><i
                        class="fas fa-plus me-1"></i> Thêm phiếu nhập</button>
                <button class="btn btn-success fw-semibold" id="btnAddImportItem"><i class="fas fa-cart-plus me-1"></i>
                    Thêm hàng vào phiếu</button>
                <button class="btn btn-warning fw-semibold" id="btnEditImport" disabled><i class="fas fa-pen me-1"></i>
                    Sửa nhập hàng</button>
                <button class="btn btn-info fw-semibold text-white" id="btnViewImport" disabled><i
                        class="fas fa-eye me-1"></i>
                    Xem chi tiết</button>
                <button class="btn btn-danger fw-semibold" id="btnDeleteImport" disabled><i
                        class="fas fa-trash me-1"></i>
                    Xóa</button>
            </div>
        </div>

        <div id="importContentOffset" class="import-content-offset">

            <div class="section-card">
                <div class="section-title">Danh sách phiếu nhập</div>
                <div class="table-responsive import-table-scroll">
                    <table class="table-plain">
                        <thead>
                            <tr>
                                <th>Mã phiếu</th>
                                <th>Ký hiệu phiếu</th>
                                <th>Nhân viên</th>
                                <th>Nhà cung cấp</th>
                                <th>Ngày nhập</th>
                            </tr>
                        </thead>
                        <tbody id="importTableBody">
                            <?php if (count($phieuNhaps) === 0): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Chưa có phiếu nhập nào.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($phieuNhaps as $phieu): ?>
                            <?php $isSelected = $selectedMaPhieu !== '' && $selectedMaPhieu === $phieu['MaPhieu']; ?>
                            <tr class="import-row<?php echo $isSelected ? ' selected' : ''; ?>"
                                data-ma-phieu="<?php echo htmlspecialchars($phieu['MaPhieu']); ?>"
                                data-ma-nv="<?php echo htmlspecialchars($phieu['MaNV']); ?>"
                                data-nhan-vien="<?php echo htmlspecialchars($phieu['NhanVien']); ?>"
                                data-ky-hieu="<?php echo htmlspecialchars($phieu['KyHieu']); ?>"
                                data-ma-ncc="<?php echo htmlspecialchars($phieu['MaNCC']); ?>"
                                data-nha-cung-cap="<?php echo htmlspecialchars($phieu['NhaCungCap']); ?>"
                                data-ngay-nhap="<?php echo htmlspecialchars($phieu['NgayNhap']); ?>">
                                <td class="fw-bold text-primary"><?php echo htmlspecialchars($phieu['MaPhieu']); ?></td>
                                <td><?php echo htmlspecialchars($phieu['KyHieu']); ?></td>
                                <td><?php echo htmlspecialchars($phieu['NhanVien']); ?></td>
                                <td><?php echo htmlspecialchars($phieu['NhaCungCap']); ?></td>
                                <td><?php echo htmlspecialchars($phieu['NgayNhap']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal fade" id="addImportModal" tabindex="-1" aria-labelledby="addImportModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="addImportModalLabel">Thêm phiếu nhập</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="addImportForm" method="post">
                            <input type="hidden" name="crud_action" value="add_import">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="maPhieu" class="form-label">Mã phiếu</label>
                                        <input type="text" class="form-control" id="maPhieu" name="ma_phieu"
                                            value="<?php echo htmlspecialchars($nextImportId); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="kyHieuPhieu" class="form-label">Ký hiệu phiếu</label>
                                        <input type="text" class="form-control" id="kyHieuPhieu" name="ky_hieu"
                                            placeholder="VD: PN4" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nhanVienNhap" class="form-label">Nhân viên</label>
                                        <select class="form-select" id="nhanVienNhap" name="nhan_vien" required>
                                            <option value="">Chọn nhân viên</option>
                                            <?php foreach ($nhanVienMap as $maNvOption => $tenNvOption): ?>
                                            <option value="<?php echo htmlspecialchars($maNvOption); ?>">
                                                <?php echo htmlspecialchars($maNvOption . ' - ' . $tenNvOption); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nhaCungCapNhap" class="form-label">Nhà cung cấp</label>
                                        <select class="form-select" id="nhaCungCapNhap" name="nha_cung_cap" required>
                                            <option value="">Chọn nhà cung cấp</option>
                                            <?php foreach ($nhaCungCapMap as $maNccOption => $tenNccOption): ?>
                                            <option value="<?php echo htmlspecialchars($maNccOption); ?>">
                                                <?php echo htmlspecialchars($maNccOption . ' - ' . $tenNccOption); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label for="ngayNhap" class="form-label">Ngày nhập</label>
                                        <input type="date" class="form-control" id="ngayNhap" name="ngay_nhap" required>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" class="btn btn-primary">Lưu phiếu nhập</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="addImportItemModal" tabindex="-1" aria-labelledby="addImportItemModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="addImportItemModalLabel">Thêm hàng vào phiếu nhập</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="addImportItemForm" method="post">
                            <input type="hidden" name="crud_action" value="add_import_item">
                            <input type="hidden" id="addItemMaPhieu" name="ma_phieu">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="addItemMaPhieuView" class="form-label">Mã phiếu nhập</label>
                                        <input type="text" class="form-control" id="addItemMaPhieuView" readonly>
                                    </div>
                                    <div class="col-12">
                                        <label for="addItemNhomHang" class="form-label">Nhóm hàng</label>
                                        <select class="form-select" id="addItemNhomHang">
                                            <option value="">-- Tất cả nhóm hàng --</option>
                                            <?php foreach ($nhomHangMap as $maNhomOption => $tenNhomOption): ?>
                                            <option value="<?php echo htmlspecialchars($maNhomOption); ?>">
                                                <?php echo htmlspecialchars($maNhomOption . ' - ' . $tenNhomOption); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label for="addItemMaHang" class="form-label">Mặt hàng</label>
                                        <select class="form-select" id="addItemMaHang" name="ma_hang" required>
                                            <option value="">Chọn mặt hàng</option>
                                            <?php foreach ($hangHoaMap as $maHangOption => $tenHangOption): ?>
                                            <option value="<?php echo htmlspecialchars($maHangOption); ?>"
                                                data-ma-nhom-hang="<?php echo htmlspecialchars((string) ($hangHoaNhomMap[$maHangOption] ?? '')); ?>"
                                                data-gia-co-thue="<?php echo htmlspecialchars((string) ($hangHoaGiaCoThueMap[$maHangOption] ?? 0)); ?>">
                                                <?php echo htmlspecialchars($maHangOption . ' - ' . $tenHangOption); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="addItemGiaNhap" class="form-label">Giá nhập (₫)</label>
                                        <input type="number" class="form-control" id="addItemGiaNhap" name="gia_nhap"
                                            min="0" step="0.01" readonly required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="addItemSoLuong" class="form-label">Số lượng</label>
                                        <input type="number" class="form-control" id="addItemSoLuong" name="so_luong"
                                            min="1" step="1" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="addItemChietKhau" class="form-label">Chiết khấu (%)</label>
                                        <input type="number" class="form-control" id="addItemChietKhau"
                                            name="chiet_khau" min="0" max="100" step="0.01" value="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" class="btn btn-primary">Lưu mặt hàng</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="detailOverlay" class="detail-overlay" onclick="closeImportDetailPanel()"></div>

            <div id="importDetailPanel" class="detail-panel">
                <div class="detail-header">
                    <h5>Chi tiết phiếu nhập</h5>
                    <button type="button" class="btn-close" onclick="closeImportDetailPanel()"></button>
                </div>
                <div class="detail-content">
                    <div class="detail-field">
                        <label>Mã phiếu</label>
                        <input type="text" id="detailMaPhieu" readonly>
                    </div>
                    <div class="detail-field">
                        <label>Ký hiệu phiếu</label>
                        <input type="text" id="detailKyHieu">
                    </div>
                    <div class="detail-field">
                        <label>Nhân viên</label>
                        <select id="detailNhanVien" class="form-select">
                            <option value="">Chọn nhân viên</option>
                            <?php foreach ($nhanVienMap as $maNvOption => $tenNvOption): ?>
                            <option value="<?php echo htmlspecialchars($maNvOption); ?>">
                                <?php echo htmlspecialchars($maNvOption . ' - ' . $tenNvOption); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="detail-field">
                        <label>Nhà cung cấp</label>
                        <select id="detailNhaCungCap" class="form-select">
                            <option value="">Chọn nhà cung cấp</option>
                            <?php foreach ($nhaCungCapMap as $maNccOption => $tenNccOption): ?>
                            <option value="<?php echo htmlspecialchars($maNccOption); ?>">
                                <?php echo htmlspecialchars($maNccOption . ' - ' . $tenNccOption); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="detail-field" style="grid-column: 1 / -1;">
                        <label>Ngày nhập</label>
                        <input type="date" id="detailNgayNhap">
                    </div>
                    <div class="detail-related-wrap">
                        <label class="form-label fw-bold mb-2">Dữ liệu liên quan phiếu nhập</label>
                        <div class="table-responsive border rounded bg-white">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Mã hàng</th>
                                        <th>Tên hàng</th>
                                        <th>Giá nhập</th>
                                        <th>Số lượng</th>
                                        <th>Chiết khấu</th>
                                        <th>Tổng tiền</th>
                                    </tr>
                                </thead>
                                <tbody id="detailRelatedItemsBody">
                                    <tr>
                                        <td colspan="6" class="text-muted text-center py-3">Chưa có dữ liệu hàng hóa
                                            liên
                                            quan.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="detailRelatedSummary" class="small text-muted mt-2"></div>
                    </div>
                </div>
                <div class="detail-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeImportDetailPanel()">Đóng</button>
                    <button type="button" class="btn btn-primary" id="btnSaveImportDetail">Lưu thay đổi</button>
                </div>
            </div>

            <div id="importItemDetailPanel" class="detail-panel">
                <div class="detail-header">
                    <h5>Chi tiết hàng hóa nhập</h5>
                    <button type="button" class="btn-close" onclick="closeImportDetailPanel()"></button>
                </div>
                <div class="detail-content">
                    <div class="detail-field">
                        <label>Mã hàng</label>
                        <input type="text" id="detailItemMaHang" readonly>
                    </div>
                    <div class="detail-field">
                        <label>Tên hàng</label>
                        <input type="text" id="detailItemTenHang" readonly>
                    </div>
                    <div class="detail-field">
                        <label>Giá nhập (₫)</label>
                        <input type="number" id="detailItemGiaNhap" min="0" readonly>
                    </div>
                    <div class="detail-field">
                        <label>Số lượng</label>
                        <input type="number" id="detailItemSoLuong" min="0">
                    </div>
                    <div class="detail-field" style="grid-column: 1 / -1;">
                        <label>Chiết khấu (%)</label>
                        <input type="number" id="detailItemChietKhau" min="0" max="100">
                    </div>
                </div>
                <div class="detail-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeImportDetailPanel()">Đóng</button>
                    <button type="button" class="btn btn-primary" id="btnSaveImportItemDetail">Lưu thay đổi</button>
                </div>
            </div>

            <div class="modal fade" id="deleteImportModal" tabindex="-1" aria-labelledby="deleteImportModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteImportModalLabel">Xác nhận xóa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <span id="deleteImportMessage">Bạn có chắc chắn muốn xóa dữ liệu này?</span>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="button" class="btn btn-danger" id="btnConfirmDeleteImport">Xóa</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="import-section">
                <div class="info-header">
                    Thông tin hàng hóa <span class="ms-2 fw-normal fs-6">mã phiếu</span> <span
                        class="ma-phieu-box"><?php echo ($selectedMaPhieu !== '') ? htmlspecialchars($selectedMaPhieu) : '--'; ?></span>
                </div>
                <div class="table-responsive import-items-table-scroll">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Mã Hàng</th>
                                <th>Tên Hàng</th>
                                <th>Giá Nhập</th>
                                <th>Số Lượng</th>
                                <th>Chiết Khấu</th>
                                <th>Thành Tiền</th>
                                <th>Tổng Tiền Nhập</th>
                            </tr>
                        </thead>
                        <tbody id="importItemsTableBody">
                            <?php 
                        foreach($importItems as $item): 
                            $thanhTienGoc = $item['GiaNhap'] * $item['SoLuong'];
                            $soTienChietKhau = $thanhTienGoc * ($item['ChietKhau'] / 100);
                            $thanhTien = $thanhTienGoc - $soTienChietKhau;
                        ?>
                            <tr class="import-item-row" data-ma-hang="<?php echo htmlspecialchars($item['MaHang']); ?>"
                                data-ten-hang="<?php echo htmlspecialchars($item['TenHang']); ?>"
                                data-gia-nhap="<?php echo htmlspecialchars((string) $item['GiaNhap']); ?>"
                                data-so-luong="<?php echo htmlspecialchars((string) $item['SoLuong']); ?>"
                                data-chiet-khau="<?php echo htmlspecialchars((string) $item['ChietKhau']); ?>">
                                <td class="fw-bold text-primary"><?php echo $item['MaHang']; ?></td>
                                <td class="fw-bold"><?php echo $item['TenHang']; ?></td>
                                <td><?php echo number_format($item['GiaNhap'], 0, ',', '.'); ?> ₫</td>
                                <td><?php echo $item['SoLuong']; ?></td>
                                <td><?php echo $item['ChietKhau']; ?>%</td>
                                <td><?php echo number_format($thanhTien, 0, ',', '.'); ?> ₫</td>
                                <td class="fw-bold text-danger"><?php echo number_format($thanhTien, 0, ',', '.'); ?> ₫
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr id="importItemsSummaryRow" class="import-total-row" style="background-color: #f8f9fa;">
                                <td colspan="6" class="text-end fw-bold" style="font-size: 1.1rem;">Tổng cộng:
                                </td>
                                <td id="importItemsTotalValue" class="fw-bold text-danger" style="font-size: 1.2rem;">
                                    <?php echo number_format($tongChiPhi, 0, ',', '.'); ?> ₫</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="post" id="importEditForm" class="d-none">
                <input type="hidden" name="crud_action" value="update_import">
                <input type="hidden" name="ma_phieu" id="editImportMaPhieu">
                <input type="hidden" name="ky_hieu" id="editImportKyHieu">
                <input type="hidden" name="nhan_vien" id="editImportNhanVien">
                <input type="hidden" name="nha_cung_cap" id="editImportNhaCungCap">
                <input type="hidden" name="ngay_nhap" id="editImportNgayNhap">
            </form>

            <form method="post" id="importDeleteForm" class="d-none">
                <input type="hidden" name="crud_action" value="delete_import">
                <input type="hidden" name="ma_phieu" id="deleteImportMaPhieu">
            </form>

            <form method="post" id="importItemEditForm" class="d-none">
                <input type="hidden" name="crud_action" value="update_import_item">
                <input type="hidden" name="ma_phieu" id="editImportItemMaPhieu">
                <input type="hidden" name="ma_hang" id="editImportItemMaHang">
                <input type="hidden" name="gia_nhap" id="editImportItemGiaNhap">
                <input type="hidden" name="so_luong" id="editImportItemSoLuong">
                <input type="hidden" name="chiet_khau" id="editImportItemChietKhau">
            </form>

            <form method="post" id="importItemDeleteForm" class="d-none">
                <input type="hidden" name="crud_action" value="delete_import_item">
                <input type="hidden" name="ma_phieu" id="deleteImportItemMaPhieu">
                <input type="hidden" name="ma_hang" id="deleteImportItemMaHang">
            </form>

        </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="admin-search.js?v=20260414-2"></script>
    <script>
    let selectedImportRow = null;
    let selectedImportItemRow = null;
    let pendingDeleteImportRow = null;
    let pendingDeleteImportItemRow = null;
    let isImportDetailReadOnly = false;
    let isImportItemDetailReadOnly = false;
    const initialSelectedMaPhieu = <?php echo json_encode($selectedMaPhieu); ?>;

    const addImportModalEl = document.getElementById('addImportModal');
    if (addImportModalEl && addImportModalEl.parentElement !== document.body) {
        document.body.appendChild(addImportModalEl);
    }

    const addImportItemModalEl = document.getElementById('addImportItemModal');
    if (addImportItemModalEl && addImportItemModalEl.parentElement !== document.body) {
        document.body.appendChild(addImportItemModalEl);
    }

    const deleteImportModalEl = document.getElementById('deleteImportModal');
    if (deleteImportModalEl && deleteImportModalEl.parentElement !== document.body) {
        document.body.appendChild(deleteImportModalEl);
    }

    const detailOverlayEl = document.getElementById('detailOverlay');
    if (detailOverlayEl && detailOverlayEl.parentElement !== document.body) {
        document.body.appendChild(detailOverlayEl);
    }

    const importDetailPanelEl = document.getElementById('importDetailPanel');
    if (importDetailPanelEl && importDetailPanelEl.parentElement !== document.body) {
        document.body.appendChild(importDetailPanelEl);
    }

    const importItemDetailPanelEl = document.getElementById('importItemDetailPanel');
    if (importItemDetailPanelEl && importItemDetailPanelEl.parentElement !== document.body) {
        document.body.appendChild(importItemDetailPanelEl);
    }

    const importTableBody = document.getElementById('importTableBody');
    const importItemsTableBody = document.getElementById('importItemsTableBody');
    const selectedMaPhieuBox = document.querySelector('.ma-phieu-box');
    let displayedImportId = (initialSelectedMaPhieu || '').trim();

    function displayDateToInput(displayDate) {
        if (!displayDate || typeof displayDate !== 'string') {
            return '';
        }
        const match = displayDate.trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!match) {
            return '';
        }
        const [, dd, mm, yyyy] = match;
        return `${yyyy}-${mm}-${dd}`;
    }

    function inputDateToDisplay(inputDate) {
        if (!inputDate) {
            return '--/--/----';
        }
        const [yyyy, mm, dd] = inputDate.split('-');
        if (!yyyy || !mm || !dd) {
            return inputDate;
        }
        return `${dd}/${mm}/${yyyy}`;
    }

    function syncImportActionButtons() {
        const hasSelected = !!document.querySelector('.import-row.selected') || !!document.querySelector(
            '.import-item-row.selected');
        const hasImportSelected = !!document.querySelector('.import-row.selected');
        document.getElementById('btnEditImport').disabled = !hasSelected;
        document.getElementById('btnViewImport').disabled = !hasImportSelected;
        document.getElementById('btnDeleteImport').disabled = !hasSelected;

        const addItemBtn = document.getElementById('btnAddImportItem');
        if (addItemBtn) {
            addItemBtn.disabled = !getDisplayedImportId();
        }
    }

    function getDisplayedImportId() {
        if (displayedImportId) {
            return displayedImportId;
        }

        if (selectedMaPhieuBox) {
            const boxValue = (selectedMaPhieuBox.textContent || '').trim();
            if (boxValue && boxValue !== '--') {
                displayedImportId = boxValue;
                return displayedImportId;
            }
        }

        return '';
    }

    function getActiveImportId() {
        if (selectedImportRow) {
            return (selectedImportRow.getAttribute('data-ma-phieu') || '').trim();
        }

        if (selectedMaPhieuBox) {
            const boxValue = (selectedMaPhieuBox.textContent || '').trim();
            if (boxValue && boxValue !== '--') {
                return boxValue;
            }
        }

        return '';
    }

    function clearImportRowSelection() {
        document.querySelectorAll('.import-row').forEach(r => r.classList.remove('selected'));
        selectedImportRow = null;
    }

    function clearImportItemSelection() {
        document.querySelectorAll('.import-item-row').forEach(r => r.classList.remove('selected'));
        selectedImportItemRow = null;
    }

    function bindImportRowEvents(row) {
        row.addEventListener('click', function() {
            const maPhieu = (this.getAttribute('data-ma-phieu') || '').trim();
            if (!maPhieu) {
                return;
            }

            if (maPhieu !== getDisplayedImportId()) {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('maPhieu', maPhieu);
                window.location.href = currentUrl.toString();
                return;
            }

            clearImportRowSelection();
            clearImportItemSelection();
            this.classList.add('selected');
            selectedImportRow = this;
            syncImportActionButtons();
        });
    }

    document.querySelectorAll('.import-row').forEach(bindImportRowEvents);

    function bindImportItemRowEvents(row) {
        row.addEventListener('click', function() {
            clearImportItemSelection();
            clearImportRowSelection();
            this.classList.add('selected');
            selectedImportItemRow = this;
            syncImportActionButtons();
        });
    }

    document.querySelectorAll('.import-item-row').forEach(bindImportItemRowEvents);

    function setImportDetailReadOnly(readOnly) {
        isImportDetailReadOnly = !!readOnly;

        const fieldIds = ['detailNhanVien', 'detailKyHieu', 'detailNhaCungCap', 'detailNgayNhap'];
        fieldIds.forEach((fieldId) => {
            const field = document.getElementById(fieldId);
            if (!field) {
                return;
            }
            if (field.tagName === 'SELECT') {
                field.disabled = isImportDetailReadOnly;
            } else {
                field.readOnly = isImportDetailReadOnly;
            }
        });

        const saveBtn = document.getElementById('btnSaveImportDetail');
        if (saveBtn) {
            saveBtn.style.display = isImportDetailReadOnly ? 'none' : 'inline-block';
        }
    }

    function ensureSelectHasValue(selectId, value, label) {
        const select = document.getElementById(selectId);
        if (!select) {
            return;
        }

        const normalizedValue = (value || '').trim();
        if (normalizedValue === '') {
            select.value = '';
            return;
        }

        const hasOption = Array.from(select.options).some((option) => option.value === normalizedValue);
        if (!hasOption) {
            const fallbackText = label ? `${normalizedValue} - ${label}` : normalizedValue;
            select.appendChild(new Option(fallbackText, normalizedValue, true, true));
        }

        select.value = normalizedValue;
    }

    function showImportDetailPanel(row, readOnly = false) {
        document.getElementById('detailMaPhieu').value = row.getAttribute('data-ma-phieu') || '';
        const maNv = row.getAttribute('data-ma-nv') || '';
        const maNcc = row.getAttribute('data-ma-ncc') || '';
        ensureSelectHasValue('detailNhanVien', maNv, row.getAttribute('data-nhan-vien') || '');
        document.getElementById('detailKyHieu').value = row.getAttribute('data-ky-hieu') || '';
        ensureSelectHasValue('detailNhaCungCap', maNcc, row.getAttribute('data-nha-cung-cap') || '');
        document.getElementById('detailNgayNhap').value = displayDateToInput(row.getAttribute('data-ngay-nhap') || '');
        populateImportRelatedData();
        setImportDetailReadOnly(readOnly);

        document.getElementById('detailOverlay').classList.add('show');
        document.getElementById('importDetailPanel').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeImportDetailPanel() {
        document.getElementById('importDetailPanel').classList.remove('show');
        document.getElementById('importItemDetailPanel').classList.remove('show');
        document.getElementById('detailOverlay').classList.remove('show');
        document.body.style.overflow = '';
    }

    function setImportItemDetailReadOnly(readOnly) {
        isImportItemDetailReadOnly = !!readOnly;

        const fieldIds = ['detailItemSoLuong', 'detailItemChietKhau'];
        fieldIds.forEach((fieldId) => {
            const field = document.getElementById(fieldId);
            if (!field) {
                return;
            }
            field.readOnly = isImportItemDetailReadOnly;
        });

        const saveBtn = document.getElementById('btnSaveImportItemDetail');
        if (saveBtn) {
            saveBtn.style.display = isImportItemDetailReadOnly ? 'none' : 'inline-block';
        }
    }

    function showImportItemDetailPanel(row, readOnly = false) {
        document.getElementById('detailItemMaHang').value = row.getAttribute('data-ma-hang') || '';
        document.getElementById('detailItemTenHang').value = row.getAttribute('data-ten-hang') || '';
        document.getElementById('detailItemGiaNhap').value = row.getAttribute('data-gia-nhap') || '0';
        document.getElementById('detailItemSoLuong').value = row.getAttribute('data-so-luong') || '0';
        document.getElementById('detailItemChietKhau').value = row.getAttribute('data-chiet-khau') || '0';
        setImportItemDetailReadOnly(readOnly);

        document.getElementById('detailOverlay').classList.add('show');
        document.getElementById('importItemDetailPanel').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function formatCurrencyVnd(value) {
        const amount = Number(value) || 0;
        return `${amount.toLocaleString('vi-VN')} ₫`;
    }

    function formatPercent(value) {
        const number = Number(value) || 0;
        return `${number}%`;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function populateImportRelatedData() {
        const tbody = document.getElementById('detailRelatedItemsBody');
        const summary = document.getElementById('detailRelatedSummary');
        if (!tbody || !summary) {
            return;
        }

        const itemRows = Array.from(document.querySelectorAll('#importItemsTableBody .import-item-row'));
        if (itemRows.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="6" class="text-muted text-center py-3">Chưa có dữ liệu hàng hóa liên quan.</td></tr>';
            summary.textContent = 'Tổng mặt hàng: 0 • Tổng số lượng: 0 • Tổng tiền nhập: 0 ₫';
            return;
        }

        let totalQuantity = 0;
        let totalAmount = 0;

        const rowsHtml = itemRows.map((row) => {
            const maHang = row.getAttribute('data-ma-hang') || '';
            const tenHang = row.getAttribute('data-ten-hang') || '';
            const giaNhap = Number(row.getAttribute('data-gia-nhap') || 0);
            const soLuong = Number(row.getAttribute('data-so-luong') || 0);
            const chietKhau = Number(row.getAttribute('data-chiet-khau') || 0);
            const tongTien = giaNhap * soLuong * (1 - chietKhau / 100);

            totalQuantity += soLuong;
            totalAmount += tongTien;

            return `
                <tr>
                    <td>${escapeHtml(maHang)}</td>
                    <td>${escapeHtml(tenHang)}</td>
                    <td>${formatCurrencyVnd(giaNhap)}</td>
                    <td>${escapeHtml(String(soLuong))}</td>
                    <td>${formatPercent(chietKhau)}</td>
                    <td class="fw-semibold text-danger">${formatCurrencyVnd(tongTien)}</td>
                </tr>
            `;
        }).join('');

        tbody.innerHTML = rowsHtml;
        summary.textContent =
            `Tổng mặt hàng: ${itemRows.length} • Tổng số lượng: ${totalQuantity} • Tổng tiền nhập: ${formatCurrencyVnd(totalAmount)}`;
    }

    function syncAddImportItemPrice() {
        const select = document.getElementById('addItemMaHang');
        const priceInput = document.getElementById('addItemGiaNhap');
        if (!select || !priceInput) {
            return;
        }

        const selectedOption = select.options[select.selectedIndex];
        const rawPrice = selectedOption ? selectedOption.getAttribute('data-gia-co-thue') : null;
        const giaCoThue = Number(rawPrice || 0);
        priceInput.value = giaCoThue > 0 ? String(giaCoThue) : '';
    }

    function filterAddItemByGroup() {
        const groupSelect = document.getElementById('addItemNhomHang');
        const itemSelect = document.getElementById('addItemMaHang');
        const priceInput = document.getElementById('addItemGiaNhap');
        if (!groupSelect || !itemSelect) {
            return;
        }

        const selectedGroup = (groupSelect.value || '').trim();
        const options = Array.from(itemSelect.options);

        options.forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const itemGroup = (option.getAttribute('data-ma-nhom-hang') || '').trim();
            const isMatch = selectedGroup === '' || itemGroup === selectedGroup;
            option.hidden = !isMatch;
            option.disabled = !isMatch;
        });

        const currentValue = (itemSelect.value || '').trim();
        const selectedOption = currentValue ?
            options.find((option) => option.value === currentValue) :
            null;

        if (!selectedOption || selectedOption.disabled) {
            itemSelect.value = '';
            if (priceInput) {
                priceInput.value = '';
            }
        }
    }

    function refreshImportSummary() {
        const itemRows = Array.from(document.querySelectorAll('.import-item-row'));

        let totalItems = 0;
        let totalQuantity = 0;
        let totalCost = 0;

        itemRows.forEach(row => {
            const giaNhap = Number(row.getAttribute('data-gia-nhap')) || 0;
            const soLuong = Number(row.getAttribute('data-so-luong')) || 0;
            const chietKhau = Number(row.getAttribute('data-chiet-khau')) || 0;
            const thanhTien = giaNhap * soLuong * (1 - chietKhau / 100);

            totalItems += 1;
            totalQuantity += soLuong;
            totalCost += thanhTien;
        });

        const totalValueCell = document.getElementById('importItemsTotalValue');

        if (totalValueCell) totalValueCell.textContent = formatCurrencyVnd(totalCost);

        let emptyRow = importItemsTableBody.querySelector('tr.import-items-empty-row');
        if (totalItems === 0) {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.className = 'import-items-empty-row';
                emptyRow.innerHTML =
                    '<td colspan="7" class="text-center text-muted py-4">Chưa có hàng hóa trong phiếu này.</td>';
                const summaryRow = document.getElementById('importItemsSummaryRow');
                importItemsTableBody.insertBefore(emptyRow, summaryRow);
            }
        } else if (emptyRow) {
            emptyRow.remove();
        }
    }

    document.getElementById('btnEditImport').addEventListener('click', function() {
        if (selectedImportItemRow) {
            showImportItemDetailPanel(selectedImportItemRow, false);
            return;
        }

        if (selectedImportRow) {
            showImportDetailPanel(selectedImportRow, false);
            return;
        }

        alert('Vui lòng chọn phiếu nhập hoặc mặt hàng để sửa');
    });

    document.getElementById('btnViewImport').addEventListener('click', function() {
        if (selectedImportRow) {
            showImportDetailPanel(selectedImportRow, true);
            return;
        }

        alert('Vui lòng chọn phiếu nhập ở bảng phía trên để xem chi tiết');
    });

    document.getElementById('btnSaveImportDetail').addEventListener('click', function() {
        if (isImportDetailReadOnly) {
            return;
        }

        const selectedRow = selectedImportRow;
        if (!selectedRow) {
            alert('Không tìm thấy dòng phiếu nhập đang chọn.');
            return;
        }

        const maPhieu = document.getElementById('detailMaPhieu').value.trim();
        const nhanVien = document.getElementById('detailNhanVien').value.trim();
        const kyHieu = document.getElementById('detailKyHieu').value.trim();
        const nhaCungCap = document.getElementById('detailNhaCungCap').value.trim();
        const ngayNhap = document.getElementById('detailNgayNhap').value;

        if (!nhanVien || !kyHieu || !nhaCungCap) {
            alert('Vui lòng nhập đầy đủ thông tin phiếu nhập.');
            return;
        }

        document.getElementById('editImportMaPhieu').value = maPhieu;
        document.getElementById('editImportKyHieu').value = kyHieu;
        document.getElementById('editImportNhanVien').value = nhanVien;
        document.getElementById('editImportNhaCungCap').value = nhaCungCap;
        document.getElementById('editImportNgayNhap').value = ngayNhap;
        document.getElementById('importEditForm').submit();
    });

    document.getElementById('btnSaveImportItemDetail').addEventListener('click', function() {
        if (isImportItemDetailReadOnly) {
            return;
        }

        const selectedRow = selectedImportItemRow;
        if (!selectedRow) {
            alert('Không tìm thấy hàng hóa đang chọn.');
            return;
        }

        const maPhieu = getDisplayedImportId();
        const maHang = document.getElementById('detailItemMaHang').value.trim();
        const giaNhap = Number(document.getElementById('detailItemGiaNhap').value || 0);
        const soLuong = Number(document.getElementById('detailItemSoLuong').value || 0);
        const chietKhau = Number(document.getElementById('detailItemChietKhau').value || 0);

        if (!maPhieu) {
            alert('Vui lòng chọn phiếu nhập trước khi cập nhật hàng hóa.');
            return;
        }

        if (giaNhap < 0 || soLuong <= 0 || chietKhau < 0 || chietKhau > 100) {
            alert('Giá nhập, số lượng hoặc chiết khấu không hợp lệ.');
            return;
        }

        document.getElementById('editImportItemMaPhieu').value = maPhieu;
        document.getElementById('editImportItemMaHang').value = maHang;
        document.getElementById('editImportItemGiaNhap').value = String(giaNhap);
        document.getElementById('editImportItemSoLuong').value = String(soLuong);
        document.getElementById('editImportItemChietKhau').value = String(chietKhau);
        document.getElementById('importItemEditForm').submit();
    });

    document.getElementById('btnDeleteImport').addEventListener('click', function() {
        if (selectedImportItemRow) {
            pendingDeleteImportItemRow = selectedImportItemRow;
            pendingDeleteImportRow = null;
            const maHang = (selectedImportItemRow.getAttribute('data-ma-hang') || '').trim();
            document.getElementById('deleteImportMessage').innerHTML =
                `Bạn có chắc chắn muốn xóa mặt hàng <strong>${maHang || '(không xác định)'}</strong> khỏi phiếu nhập không?`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteImportModal')).show();
            return;
        }

        if (selectedImportRow) {
            pendingDeleteImportRow = selectedImportRow;
            pendingDeleteImportItemRow = null;
            const maPhieu = (selectedImportRow.getAttribute('data-ma-phieu') || '').trim();
            document.getElementById('deleteImportMessage').innerHTML =
                `Bạn có chắc chắn muốn xóa phiếu nhập <strong>${maPhieu || '(không xác định)'}</strong> không?`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteImportModal')).show();
            return;
        }

        alert('Vui lòng chọn phiếu nhập hoặc mặt hàng để xóa');
    });

    document.getElementById('btnConfirmDeleteImport').addEventListener('click', function() {
        if (pendingDeleteImportItemRow) {
            const maPhieu = getDisplayedImportId();
            const maHang = (pendingDeleteImportItemRow.getAttribute('data-ma-hang') || '').trim();
            if (!maPhieu || !maHang) {
                alert('Không xác định được thông tin mặt hàng cần xóa.');
                return;
            }

            document.getElementById('deleteImportItemMaPhieu').value = maPhieu;
            document.getElementById('deleteImportItemMaHang').value = maHang;
            document.getElementById('importItemDeleteForm').submit();
            return;
        }

        if (!pendingDeleteImportRow) {
            return;
        }

        const maPhieu = (pendingDeleteImportRow.getAttribute('data-ma-phieu') || '').trim();
        if (!maPhieu) {
            alert('Không xác định được phiếu nhập cần xóa.');
            return;
        }

        document.getElementById('deleteImportMaPhieu').value = maPhieu;
        document.getElementById('importDeleteForm').submit();
    });

    document.getElementById('deleteImportModal').addEventListener('hidden.bs.modal', function() {
        pendingDeleteImportRow = null;
        pendingDeleteImportItemRow = null;
    });

    document.getElementById('addImportForm').addEventListener('submit', function(e) {
        const maPhieu = document.getElementById('maPhieu').value.trim();
        const kyHieu = document.getElementById('kyHieuPhieu').value.trim();
        const nhanVien = document.getElementById('nhanVienNhap').value.trim();
        const nhaCungCap = document.getElementById('nhaCungCapNhap').value.trim();
        const ngayNhapInput = document.getElementById('ngayNhap').value;

        if (!maPhieu || !kyHieu || !nhanVien || !nhaCungCap || !ngayNhapInput) {
            alert('Vui lòng nhập đầy đủ thông tin phiếu nhập.');
            e.preventDefault();
            return;
        }
    });

    document.getElementById('btnAddImportItem').addEventListener('click', function() {
        const maPhieu = getDisplayedImportId();
        if (!maPhieu) {
            alert('Vui lòng chọn phiếu nhập trước khi thêm hàng hóa.');
            return;
        }

        document.getElementById('addItemMaPhieu').value = maPhieu;
        document.getElementById('addItemMaPhieuView').value = maPhieu;
        document.getElementById('addItemNhomHang').value = '';
        document.getElementById('addItemMaHang').value = '';
        document.getElementById('addItemGiaNhap').value = '';
        document.getElementById('addItemSoLuong').value = '1';
        document.getElementById('addItemChietKhau').value = '0';
        filterAddItemByGroup();
        syncAddImportItemPrice();

        bootstrap.Modal.getOrCreateInstance(document.getElementById('addImportItemModal')).show();
    });

    document.getElementById('addItemNhomHang').addEventListener('change', function() {
        filterAddItemByGroup();
        syncAddImportItemPrice();
    });

    document.getElementById('addItemMaHang').addEventListener('change', syncAddImportItemPrice);

    document.getElementById('addImportItemForm').addEventListener('submit', function(e) {
        const maPhieu = document.getElementById('addItemMaPhieu').value.trim();
        const maHang = document.getElementById('addItemMaHang').value.trim();
        const giaNhap = Number(document.getElementById('addItemGiaNhap').value || 0);
        const soLuong = Number(document.getElementById('addItemSoLuong').value || 0);
        const chietKhau = Number(document.getElementById('addItemChietKhau').value || 0);

        if (!maPhieu || !maHang) {
            alert('Vui lòng chọn đầy đủ phiếu nhập và mặt hàng.');
            e.preventDefault();
            return;
        }

        if (giaNhap <= 0 || soLuong <= 0 || chietKhau < 0 || chietKhau > 100) {
            alert('Giá nhập, số lượng hoặc chiết khấu không hợp lệ.');
            e.preventDefault();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImportDetailPanel();
        }
    });

    document.getElementById('addImportModal').addEventListener('show.bs.modal', function() {
        closeImportDetailPanel();
        const maPhieuInput = document.getElementById('maPhieu');
        if (maPhieuInput) {
            maPhieuInput.value = '<?php echo htmlspecialchars($nextImportId); ?>';
        }
    });

    if (initialSelectedMaPhieu) {
        const initialRow = Array.from(document.querySelectorAll('.import-row')).find((row) =>
            (row.getAttribute('data-ma-phieu') || '').trim() === initialSelectedMaPhieu
        );
        if (initialRow) {
            initialRow.classList.add('selected');
            selectedImportRow = initialRow;
        }
    }

    function syncFixedTopOffset() {
        const fixedTop = document.querySelector('.import-top-sticky');
        const contentOffset = document.getElementById('importContentOffset');
        if (!fixedTop || !contentOffset) {
            return;
        }

        const topHeight = Math.ceil(fixedTop.getBoundingClientRect().height);
        contentOffset.style.top = `${topHeight + 6}px`;
    }

    window.addEventListener('resize', syncFixedTopOffset);
    window.addEventListener('load', syncFixedTopOffset);
    syncFixedTopOffset();

    refreshImportSummary();
    syncImportActionButtons();
    </script>
</body>

</html>