<?php
require_once '../Login/connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function respondCart(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function currentCustomerCode(): string
{
    if (isset($_SESSION['ma_khach_hang']) && trim((string) $_SESSION['ma_khach_hang']) !== '') {
        return trim((string) $_SESSION['ma_khach_hang']);
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        return 'KH' . str_pad((string) $userId, 3, '0', STR_PAD_LEFT);
    }

    if (!isset($_SESSION['guest_cart_code']) || trim((string) $_SESSION['guest_cart_code']) === '') {
        $_SESSION['guest_cart_code'] = 'GUEST_' . strtoupper(substr(sha1((string) session_id()), 0, 12));
    }

    return trim((string) $_SESSION['guest_cart_code']);
}

function getOrCreateCart(mysqli $conn, string $maKhachHang): int
{
    $findStmt = $conn->prepare('SELECT id_gio_hang FROM gio_hang WHERE ma_khach_hang = ? AND trang_thai = ? ORDER BY id_gio_hang DESC LIMIT 1');
    $active = 'active';
    $findStmt->bind_param('ss', $maKhachHang, $active);
    $findStmt->execute();
    $res = $findStmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $findStmt->close();

    if (is_array($row) && isset($row['id_gio_hang'])) {
        return (int) $row['id_gio_hang'];
    }

    $insertStmt = $conn->prepare('INSERT INTO gio_hang (ma_khach_hang, trang_thai) VALUES (?, ?)');
    $insertStmt->bind_param('ss', $maKhachHang, $active);
    $insertStmt->execute();
    $newId = (int) $conn->insert_id;
    $insertStmt->close();

    return $newId;
}

function getActiveCartId(mysqli $conn, string $maKhachHang): ?int
{
    $stmt = $conn->prepare('SELECT id_gio_hang FROM gio_hang WHERE ma_khach_hang = ? AND trang_thai = ? ORDER BY id_gio_hang DESC LIMIT 1');
    $active = 'active';
    $stmt->bind_param('ss', $maKhachHang, $active);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row) || !isset($row['id_gio_hang'])) {
        return null;
    }

    return (int) $row['id_gio_hang'];
}

function getExistingColumnsCached(mysqli $conn, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '') {
        return [];
    }

    $rows = [];
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (is_array($row) && isset($row['Field'])) {
                $rows[] = strtolower((string) $row['Field']);
            }
        }
        $res->free();
    }

    $cache[$table] = $rows;
    return $rows;
}

function pickExistingColumnName(array $existingColumns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array(strtolower($candidate), $existingColumns, true)) {
            return $candidate;
        }
    }

    return null;
}

function pickStockValueFromRow(array $row): ?int
{
    $lowerRow = array_change_key_case($row, CASE_LOWER);
    $stockCandidates = ['soluongton', 'so_luong_ton', 'tonkho', 'so_luong_ton_kho'];

    foreach ($stockCandidates as $candidate) {
        if (array_key_exists($candidate, $lowerRow)) {
            return max(0, (int) $lowerRow[$candidate]);
        }
    }

    return null;
}

function resolveProductCodeForCart(mysqli $conn, string $maSanPham, string $tenSanPham): ?string
{
    $hangColumns = getExistingColumnsCached($conn, 'hanghoa');
    $idCol = pickExistingColumnName($hangColumns, ['MaHang', 'ma_hang', 'idhanghoa', 'id']);
    $nameCol = pickExistingColumnName($hangColumns, ['TenHang', 'ten_hang', 'tensp', 'tensanpham', 'name']);

    if ($idCol === null) {
        return null;
    }

    $maSanPham = trim($maSanPham);
    if ($maSanPham !== '') {
        $findByIdStmt = $conn->prepare("SELECT `{$idCol}` AS ma_hang FROM hanghoa WHERE `{$idCol}` = ? LIMIT 1");
        $findByIdStmt->bind_param('s', $maSanPham);
        $findByIdStmt->execute();
        $res = $findByIdStmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $findByIdStmt->close();

        if (is_array($row) && trim((string) ($row['ma_hang'] ?? '')) !== '') {
            return trim((string) $row['ma_hang']);
        }
    }

    if ($nameCol === null) {
        return null;
    }

    $tenSanPham = trim($tenSanPham);
    if ($tenSanPham === '') {
        return null;
    }

    $findByNameStmt = $conn->prepare("SELECT `{$idCol}` AS ma_hang FROM hanghoa WHERE LOWER(`{$nameCol}`) = LOWER(?) LIMIT 1");
    $findByNameStmt->bind_param('s', $tenSanPham);
    $findByNameStmt->execute();
    $nameRes = $findByNameStmt->get_result();
    $nameRow = $nameRes ? $nameRes->fetch_assoc() : null;
    $findByNameStmt->close();

    if (is_array($nameRow) && trim((string) ($nameRow['ma_hang'] ?? '')) !== '') {
        return trim((string) $nameRow['ma_hang']);
    }

    $likeKeyword = '%' . $tenSanPham . '%';
    $findByLikeStmt = $conn->prepare("SELECT `{$idCol}` AS ma_hang FROM hanghoa WHERE LOWER(`{$nameCol}`) LIKE LOWER(?) LIMIT 1");
    $findByLikeStmt->bind_param('s', $likeKeyword);
    $findByLikeStmt->execute();
    $likeRes = $findByLikeStmt->get_result();
    $likeRow = $likeRes ? $likeRes->fetch_assoc() : null;
    $findByLikeStmt->close();

    if (is_array($likeRow) && trim((string) ($likeRow['ma_hang'] ?? '')) !== '') {
        return trim((string) $likeRow['ma_hang']);
    }

    return null;
}

function getAvailableStockByProduct(mysqli $conn, string $maSanPham): int
{
    $maSanPham = trim($maSanPham);
    if ($maSanPham === '') {
        return 0;
    }

    $hhColumns = getExistingColumnsCached($conn, 'hanghoa');
    $stockCol = pickExistingColumnName($hhColumns, ['SoLuongTon', 'so_luong_ton', 'TonKho', 'ton_kho']);

    $selectStockSql = $stockCol !== null ? "hh.`{$stockCol}` AS so_luong_ton, " : '';
    $sql = "SELECT {$selectStockSql}
                   COALESCE((SELECT SUM(SoLuongNhap) FROM chitietnhaphang WHERE MaHang = ?), 0) AS tong_nhap,
                   COALESCE((
                       SELECT SUM(ctx.SoLuongPX)
                       FROM chitietphieuxuat ctx
                       LEFT JOIN phieuxuat px ON px.IdPhieuXuat = ctx.IdPhieuXuat
                       WHERE ctx.MaHang = ?
                         AND (
                             px.KyHieuPX IS NULL
                             OR (
                                 LOWER(px.KyHieuPX) NOT LIKE '%hủy%'
                                 AND LOWER(px.KyHieuPX) NOT LIKE '%huy%'
                                 AND LOWER(px.KyHieuPX) NOT LIKE '%cancel%'
                             )
                         )
                   ), 0) AS tong_xuat
            FROM hanghoa hh
            WHERE hh.MaHang = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $maSanPham, $maSanPham, $maSanPham);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $tongNhap = (int) ($row['tong_nhap'] ?? 0);
    $tongXuat = (int) ($row['tong_xuat'] ?? 0);
    $stockFromFlow = max(0, $tongNhap - $tongXuat);

    if ($stockFromFlow > 0) {
        return $stockFromFlow;
    }

    if (is_array($row)) {
        $stockFromTable = pickStockValueFromRow($row);
        if ($stockFromTable !== null) {
            return max(0, $stockFromTable);
        }
    }

    return $stockFromFlow;
}

function getStockMapByProducts(mysqli $conn, array $productIds): array
{
    $ids = array_values(array_filter(array_map(static function ($value): string {
        return trim((string) $value);
    }, $productIds), static function (string $value): bool {
        return $value !== '';
    }));

    if (count($ids) === 0) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $hhColumns = getExistingColumnsCached($conn, 'hanghoa');
    $stockCol = pickExistingColumnName($hhColumns, ['SoLuongTon', 'so_luong_ton', 'TonKho', 'ton_kho']);

    $selectStockSql = $stockCol !== null ? ", hh.`{$stockCol}` AS so_luong_ton" : '';
    $sql = "SELECT hh.MaHang AS ma_hang,
                   COALESCE(nhap.TongNhap, 0) AS tong_nhap,
                   COALESCE(xuat.TongXuat, 0) AS tong_xuat
                   {$selectStockSql}
            FROM hanghoa hh
            LEFT JOIN (
                SELECT MaHang, COALESCE(SUM(SoLuongNhap), 0) AS TongNhap
                FROM chitietnhaphang
                GROUP BY MaHang
            ) nhap ON nhap.MaHang = hh.MaHang
            LEFT JOIN (
                SELECT ctx.MaHang, COALESCE(SUM(ctx.SoLuongPX), 0) AS TongXuat
                FROM chitietphieuxuat ctx
                LEFT JOIN phieuxuat px ON px.IdPhieuXuat = ctx.IdPhieuXuat
                WHERE px.KyHieuPX IS NULL
                   OR (
                       LOWER(px.KyHieuPX) NOT LIKE '%hủy%'
                       AND LOWER(px.KyHieuPX) NOT LIKE '%huy%'
                       AND LOWER(px.KyHieuPX) NOT LIKE '%cancel%'
                   )
                GROUP BY ctx.MaHang
            ) xuat ON xuat.MaHang = hh.MaHang
            WHERE hh.MaHang IN ({$placeholders})";

    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();

    $map = [];
    while ($row = $res ? $res->fetch_assoc() : null) {
        if (!is_array($row)) {
            break;
        }

        $key = trim((string) ($row['ma_hang'] ?? ''));
        if ($key === '') {
            continue;
        }

        $stockFromFlow = max(0, (int) ($row['tong_nhap'] ?? 0) - (int) ($row['tong_xuat'] ?? 0));
        if ($stockFromFlow > 0) {
            $map[$key] = $stockFromFlow;
            continue;
        }

        $stockFromTable = pickStockValueFromRow($row);
        if ($stockFromTable !== null) {
            $map[$key] = max(0, $stockFromTable);
            continue;
        }

        $map[$key] = $stockFromFlow;
    }

    $stmt->close();

    return $map;
}

function formatVoucherDiscount(array $voucher): string
{
    $type = strtolower((string) ($voucher['kieu_giam'] ?? 'fixed'));
    $value = (float) ($voucher['gia_tri_giam'] ?? 0);
    if ($type === 'percent') {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
    }

    return number_format((int) round($value), 0, ',', '.') . 'đ';
}

function currentVoucherUserKey(): string
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        return 'UID_' . $userId;
    }

    return 'CUST_' . currentCustomerCode();
}

function ensureVoucherUsageTable(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS voucher_nguoi_dung_da_dung (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_key VARCHAR(100) NOT NULL,
                id_voucher INT NOT NULL,
                ma_voucher VARCHAR(100) NOT NULL,
                ma_don_hang VARCHAR(100) DEFAULT NULL,
                thoi_gian_su_dung DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_voucher (user_key, id_voucher),
                INDEX idx_user_key (user_key),
                INDEX idx_id_voucher (id_voucher)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql);
}

$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
$maKhachHang = currentCustomerCode();
$voucherUserKey = currentVoucherUserKey();

if ($action === '') {
    respondCart(false, 'Thiếu action.', [], 400);
}

try {
    switch ($action) {
        case 'add_to_cart': {
            $idGioHang = getOrCreateCart($conn, $maKhachHang);

            $maSanPham = trim((string) ($_POST['ma_san_pham'] ?? ''));
            $tenSanPham = trim((string) ($_POST['ten_san_pham'] ?? ''));
            $moTa = trim((string) ($_POST['mo_ta'] ?? ''));
            $giaBan = max(0, (float) ($_POST['gia_ban'] ?? 0));
            $giaGoc = max($giaBan, (float) ($_POST['gia_goc'] ?? $giaBan));
            $soLuong = max(1, (int) ($_POST['so_luong'] ?? 1));
            $hinhAnh = trim((string) ($_POST['hinh_anh'] ?? '../TrangUser/ack.png'));
            $voucherShop = trim((string) ($_POST['voucher'] ?? ''));
            $thongTinGiao = trim((string) ($_POST['thong_tin_giao'] ?? 'Giao nhanh trong ngày'));

            if ($maSanPham === '' || $tenSanPham === '') {
                respondCart(false, 'Thiếu mã hoặc tên sản phẩm.', [], 400);
            }

            $resolvedProductCode = resolveProductCodeForCart($conn, $maSanPham, $tenSanPham);
            if ($resolvedProductCode === null || $resolvedProductCode === '') {
                respondCart(false, 'Không tìm thấy sản phẩm trong kho để thêm vào giỏ hàng.', [], 404);
            }
            $maSanPham = $resolvedProductCode;

            $availableStock = getAvailableStockByProduct($conn, $maSanPham);
            if ($availableStock <= 0) {
                respondCart(false, 'Sản phẩm đã hết hàng. Vui lòng quay lại sau hoặc chờ admin nhập thêm.', [
                    'ma_san_pham' => $maSanPham,
                    'available_stock' => 0,
                ], 400);
            }

            $checkStmt = $conn->prepare('SELECT id_chi_tiet, so_luong FROM gio_hang_chi_tiet WHERE id_gio_hang = ? AND ma_san_pham = ? LIMIT 1');
            $checkStmt->bind_param('is', $idGioHang, $maSanPham);
            $checkStmt->execute();
            $res = $checkStmt->get_result();
            $existing = $res ? $res->fetch_assoc() : null;
            $checkStmt->close();

            if (is_array($existing) && isset($existing['id_chi_tiet'])) {
                $idChiTiet = (int) $existing['id_chi_tiet'];
                $newQty = (int) ($existing['so_luong'] ?? 0) + $soLuong;

                if ($newQty > $availableStock) {
                    respondCart(false, 'Số lượng vượt quá tồn kho. Hiện chỉ còn ' . $availableStock . ' sản phẩm.', [
                        'ma_san_pham' => $maSanPham,
                        'available_stock' => $availableStock,
                    ], 400);
                }

                $updateStmt = $conn->prepare('UPDATE gio_hang_chi_tiet SET so_luong = ?, ten_san_pham = ?, mo_ta = ?, gia_ban = ?, gia_goc = ?, hinh_anh = ?, voucher_cua_shop = ?, thong_tin_giao_hang = ? WHERE id_chi_tiet = ?');
                $updateStmt->bind_param('issddsssi', $newQty, $tenSanPham, $moTa, $giaBan, $giaGoc, $hinhAnh, $voucherShop, $thongTinGiao, $idChiTiet);
                $updateStmt->execute();
                $updateStmt->close();

                respondCart(true, 'Đã cập nhật số lượng trong giỏ.', [
                    'id_gio_hang' => $idGioHang,
                    'id_chi_tiet' => $idChiTiet,
                    'so_luong' => $newQty,
                    'available_stock' => $availableStock,
                ]);
            }

            if ($soLuong > $availableStock) {
                respondCart(false, 'Số lượng vượt quá tồn kho. Hiện chỉ còn ' . $availableStock . ' sản phẩm.', [
                    'ma_san_pham' => $maSanPham,
                    'available_stock' => $availableStock,
                ], 400);
            }

            $insertStmt = $conn->prepare('INSERT INTO gio_hang_chi_tiet (id_gio_hang, ma_san_pham, ten_san_pham, mo_ta, gia_ban, gia_goc, so_luong, hinh_anh, voucher_cua_shop, thong_tin_giao_hang) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insertStmt->bind_param('isssddisss', $idGioHang, $maSanPham, $tenSanPham, $moTa, $giaBan, $giaGoc, $soLuong, $hinhAnh, $voucherShop, $thongTinGiao);
            $insertStmt->execute();
            $newDetailId = (int) $conn->insert_id;
            $insertStmt->close();

            respondCart(true, 'Đã thêm sản phẩm vào giỏ.', [
                'id_gio_hang' => $idGioHang,
                'id_chi_tiet' => $newDetailId,
                'so_luong' => $soLuong,
                'available_stock' => $availableStock,
            ]);
        }

        case 'remove_item': {
            $idChiTiet = max(0, (int) ($_POST['id_chi_tiet'] ?? 0));
            if ($idChiTiet <= 0) {
                respondCart(false, 'Thiếu id_chi_tiet.', [], 400);
            }

            $stmt = $conn->prepare('DELETE gct FROM gio_hang_chi_tiet gct INNER JOIN gio_hang gh ON gh.id_gio_hang = gct.id_gio_hang WHERE gct.id_chi_tiet = ? AND gh.ma_khach_hang = ? AND gh.trang_thai = ?');
            $active = 'active';
            $stmt->bind_param('iss', $idChiTiet, $maKhachHang, $active);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            respondCart($affected > 0, $affected > 0 ? 'Xóa sản phẩm thành công.' : 'Không tìm thấy sản phẩm trong giỏ.');
        }

        case 'remove_item_by_product': {
            $maSanPham = trim((string) ($_POST['ma_san_pham'] ?? ''));
            if ($maSanPham === '') {
                respondCart(false, 'Thiếu mã sản phẩm.', [], 400);
            }

            $stmt = $conn->prepare('DELETE gct FROM gio_hang_chi_tiet gct INNER JOIN gio_hang gh ON gh.id_gio_hang = gct.id_gio_hang WHERE gct.ma_san_pham = ? AND gh.ma_khach_hang = ? AND gh.trang_thai = ?');
            $active = 'active';
            $stmt->bind_param('sss', $maSanPham, $maKhachHang, $active);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            respondCart($affected > 0, $affected > 0 ? 'Xóa sản phẩm thành công.' : 'Không tìm thấy sản phẩm trong giỏ.');
        }

        case 'update_quantity': {
            $idChiTiet = max(0, (int) ($_POST['id_chi_tiet'] ?? 0));
            $soLuong = (int) ($_POST['so_luong'] ?? 0);
            if ($idChiTiet <= 0) {
                respondCart(false, 'Thiếu id_chi_tiet.', [], 400);
            }

            if ($soLuong <= 0) {
                $_POST['id_chi_tiet'] = (string) $idChiTiet;
                $_POST['action'] = 'remove_item';
                $action = 'remove_item';
                // Continue with remove action logic by recursion-like handling
                $stmt = $conn->prepare('DELETE gct FROM gio_hang_chi_tiet gct INNER JOIN gio_hang gh ON gh.id_gio_hang = gct.id_gio_hang WHERE gct.id_chi_tiet = ? AND gh.ma_khach_hang = ? AND gh.trang_thai = ?');
                $active = 'active';
                $stmt->bind_param('iss', $idChiTiet, $maKhachHang, $active);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                respondCart($affected > 0, $affected > 0 ? 'Đã xóa sản phẩm.' : 'Không tìm thấy sản phẩm.');
            }

            $detailStmt = $conn->prepare('SELECT gct.ma_san_pham FROM gio_hang_chi_tiet gct INNER JOIN gio_hang gh ON gh.id_gio_hang = gct.id_gio_hang WHERE gct.id_chi_tiet = ? AND gh.ma_khach_hang = ? AND gh.trang_thai = ? LIMIT 1');
            $active = 'active';
            $detailStmt->bind_param('iss', $idChiTiet, $maKhachHang, $active);
            $detailStmt->execute();
            $detailRes = $detailStmt->get_result();
            $detailRow = $detailRes ? $detailRes->fetch_assoc() : null;
            $detailStmt->close();

            if (!is_array($detailRow) || trim((string) ($detailRow['ma_san_pham'] ?? '')) === '') {
                respondCart(false, 'Không tìm thấy sản phẩm trong giỏ.', [], 404);
            }

            $maSanPham = trim((string) $detailRow['ma_san_pham']);
            $availableStock = getAvailableStockByProduct($conn, $maSanPham);
            if ($soLuong > $availableStock) {
                respondCart(false, 'Số lượng vượt quá tồn kho. Hiện chỉ còn ' . $availableStock . ' sản phẩm.', [
                    'ma_san_pham' => $maSanPham,
                    'available_stock' => $availableStock,
                ], 400);
            }

            $stmt = $conn->prepare('UPDATE gio_hang_chi_tiet gct INNER JOIN gio_hang gh ON gh.id_gio_hang = gct.id_gio_hang SET gct.so_luong = ? WHERE gct.id_chi_tiet = ? AND gh.ma_khach_hang = ? AND gh.trang_thai = ?');
            $stmt->bind_param('iiss', $soLuong, $idChiTiet, $maKhachHang, $active);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            respondCart($affected >= 0, 'Cập nhật số lượng thành công.', [
                'id_chi_tiet' => $idChiTiet,
                'so_luong' => $soLuong,
                'available_stock' => $availableStock,
            ]);
        }

        case 'get_stock_map': {
            $rawIds = $_POST['ids'] ?? [];
            if (is_string($rawIds)) {
                $rawIds = explode(',', $rawIds);
            }

            if (!is_array($rawIds)) {
                $rawIds = [];
            }

            $stockMap = getStockMapByProducts($conn, $rawIds);
            respondCart(true, 'Lấy tồn kho thành công.', [
                'stock_map' => $stockMap,
            ]);
        }

        case 'remove_selected': {
            $rawIds = $_POST['ids'] ?? [];
            if (is_string($rawIds)) {
                $rawIds = explode(',', $rawIds);
            }

            if (!is_array($rawIds) || count($rawIds) === 0) {
                respondCart(false, 'Không có sản phẩm được chọn.', [], 400);
            }

            $ids = array_values(array_filter(array_map(static fn($v) => (int) $v, $rawIds), static fn($v) => $v > 0));
            if (count($ids) === 0) {
                respondCart(false, 'Danh sách sản phẩm không hợp lệ.', [], 400);
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids)) . 'ss';
            $active = 'active';
            $params = array_merge($ids, [$maKhachHang, $active]);

            $query = "DELETE gct FROM gio_hang_chi_tiet gct
                      INNER JOIN gio_hang gh ON gh.id_gio_hang = gct.id_gio_hang
                      WHERE gct.id_chi_tiet IN ({$placeholders})
                      AND gh.ma_khach_hang = ?
                      AND gh.trang_thai = ?";

            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            respondCart(true, 'Đã xóa sản phẩm đã chọn.', [
                'deleted_count' => $affected,
            ]);
        }

        case 'clear_cart': {
            $activeCartId = getActiveCartId($conn, $maKhachHang);
            if ($activeCartId === null) {
                respondCart(true, 'Giỏ hàng đã trống.', [
                    'id_gio_hang' => null,
                    'deleted_count' => 0,
                ]);
            }

            $deleteStmt = $conn->prepare('DELETE FROM gio_hang_chi_tiet WHERE id_gio_hang = ?');
            $deleteStmt->bind_param('i', $activeCartId);
            $deleteStmt->execute();
            $affected = $deleteStmt->affected_rows;
            $deleteStmt->close();

            respondCart(true, 'Đã xóa toàn bộ giỏ hàng.', [
                'id_gio_hang' => $activeCartId,
                'deleted_count' => $affected,
            ]);
        }

        case 'get_cart_count': {
            $activeCartId = getActiveCartId($conn, $maKhachHang);
            if ($activeCartId === null) {
                respondCart(true, 'Lấy số lượng giỏ hàng thành công.', [
                    'cart_count' => 0,
                ]);
            }

            $countStmt = $conn->prepare('SELECT COALESCE(SUM(so_luong), 0) AS total_qty FROM gio_hang_chi_tiet WHERE id_gio_hang = ?');
            $countStmt->bind_param('i', $activeCartId);
            $countStmt->execute();
            $countRes = $countStmt->get_result();
            $countRow = $countRes ? $countRes->fetch_assoc() : null;
            $countStmt->close();

            respondCart(true, 'Lấy số lượng giỏ hàng thành công.', [
                'cart_count' => max(0, (int) ($countRow['total_qty'] ?? 0)),
            ]);
        }

        case 'apply_voucher': {
            ensureVoucherUsageTable($conn);

            $maVoucher = strtoupper(trim((string) ($_POST['ma_voucher'] ?? '')));
            if ($maVoucher === '') {
                respondCart(false, 'Vui lòng nhập mã voucher.', [], 400);
            }

            $query = 'SELECT id_voucher, ma_voucher, ten_voucher, mo_ta, kieu_giam, gia_tri_giam, so_luong_toi_da, so_luong_da_su_dung, tien_toi_thieu, ngay_bat_dau, ngay_ket_thuc, trang_thai
                      FROM voucher
                      WHERE UPPER(ma_voucher) = ?
                      AND trang_thai = ?
                      AND NOW() BETWEEN ngay_bat_dau AND ngay_ket_thuc
                      LIMIT 1';

            $stmt = $conn->prepare($query);
            $active = 'active';
            $stmt->bind_param('ss', $maVoucher, $active);
            $stmt->execute();
            $res = $stmt->get_result();
            $voucher = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!is_array($voucher)) {
                respondCart(false, 'Mã voucher không hợp lệ hoặc đã hết hạn.', [], 404);
            }

            $maxQty = (int) ($voucher['so_luong_toi_da'] ?? 0);
            $usedQty = (int) ($voucher['so_luong_da_su_dung'] ?? 0);
            if ($maxQty > 0 && $usedQty >= $maxQty) {
                respondCart(false, 'Voucher đã hết lượt sử dụng.', [], 400);
            }

            $voucherId = (int) ($voucher['id_voucher'] ?? 0);
            if ($voucherId > 0) {
                $usedStmt = $conn->prepare('SELECT id FROM voucher_nguoi_dung_da_dung WHERE user_key = ? AND id_voucher = ? LIMIT 1');
                $usedStmt->bind_param('si', $voucherUserKey, $voucherId);
                $usedStmt->execute();
                $usedRes = $usedStmt->get_result();
                $alreadyUsed = $usedRes && $usedRes->num_rows > 0;
                $usedStmt->close();

                if ($alreadyUsed) {
                    respondCart(false, 'Voucher này bạn đã sử dụng rồi.', [], 400);
                }
            }

            respondCart(true, 'Áp dụng voucher thành công.', [
                'voucher' => [
                    'id' => (int) $voucher['id_voucher'],
                    'code' => (string) $voucher['ma_voucher'],
                    'name' => (string) ($voucher['ten_voucher'] ?? ''),
                    'description' => (string) ($voucher['mo_ta'] ?? ''),
                    'discount_type' => (string) ($voucher['kieu_giam'] ?? 'fixed'),
                    'discount_value' => (float) ($voucher['gia_tri_giam'] ?? 0),
                    'discount_text' => formatVoucherDiscount($voucher),
                    'min_order_value' => (float) ($voucher['tien_toi_thieu'] ?? 0),
                    'start_at' => (string) ($voucher['ngay_bat_dau'] ?? ''),
                    'end_at' => (string) ($voucher['ngay_ket_thuc'] ?? ''),
                ],
            ]);
        }

        case 'get_available_vouchers': {
            ensureVoucherUsageTable($conn);

            $query = 'SELECT id_voucher, ma_voucher, ten_voucher, mo_ta, kieu_giam, gia_tri_giam, so_luong_toi_da, so_luong_da_su_dung, tien_toi_thieu, ngay_bat_dau, ngay_ket_thuc
                      FROM voucher
                      WHERE trang_thai = ?
                      AND NOW() BETWEEN ngay_bat_dau AND ngay_ket_thuc
                      ORDER BY ngay_ket_thuc ASC, id_voucher DESC
                      LIMIT 20';

            $stmt = $conn->prepare($query);
            $active = 'active';
            $stmt->bind_param('s', $active);
            $stmt->execute();
            $res = $stmt->get_result();

            $vouchers = [];
            while ($row = $res ? $res->fetch_assoc() : null) {
                if (!is_array($row)) {
                    break;
                }

                $maxQty = (int) ($row['so_luong_toi_da'] ?? 0);
                $usedQty = (int) ($row['so_luong_da_su_dung'] ?? 0);
                if ($maxQty > 0 && $usedQty >= $maxQty) {
                    continue;
                }

                $voucherId = (int) ($row['id_voucher'] ?? 0);
                if ($voucherId > 0) {
                    $usedStmt = $conn->prepare('SELECT id FROM voucher_nguoi_dung_da_dung WHERE user_key = ? AND id_voucher = ? LIMIT 1');
                    $usedStmt->bind_param('si', $voucherUserKey, $voucherId);
                    $usedStmt->execute();
                    $usedRes = $usedStmt->get_result();
                    $alreadyUsed = $usedRes && $usedRes->num_rows > 0;
                    $usedStmt->close();

                    if ($alreadyUsed) {
                        continue;
                    }
                }

                $discountText = formatVoucherDiscount($row);
                $minOrder = (float) ($row['tien_toi_thieu'] ?? 0);

                $vouchers[] = [
                    'id' => (int) $row['id_voucher'],
                    'ma_voucher' => (string) $row['ma_voucher'],
                    'ten_voucher' => (string) ($row['ten_voucher'] ?? ''),
                    'mo_ta' => (string) ($row['mo_ta'] ?? ''),
                    'kieu_giam' => (string) ($row['kieu_giam'] ?? 'fixed'),
                    'gia_tri_giam' => (float) ($row['gia_tri_giam'] ?? 0),
                    'discount_text' => $discountText,
                    'tien_toi_thieu' => $minOrder,
                    'label' => (string) $row['ma_voucher'] . ' - ' . $discountText . ' (Tối thiểu ' . number_format((int) round($minOrder), 0, ',', '.') . 'đ)',
                    'ngay_ket_thuc' => (string) ($row['ngay_ket_thuc'] ?? ''),
                ];
            }

            $stmt->close();

            respondCart(true, 'Lấy danh sách voucher thành công.', [
                'vouchers' => $vouchers,
            ]);
        }

        default:
            respondCart(false, 'Action không hợp lệ.', [], 400);
    }
} catch (Throwable $e) {
    respondCart(false, 'Lỗi xử lý giỏ hàng: ' . $e->getMessage(), [], 500);
} finally {
    $conn->close();
}
?>
