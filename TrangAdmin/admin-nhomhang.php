<?php
require_once __DIR__ . '/../Login/admin_auth.php';

function pickCategoryValue(array $row, array $keys, $default = '') {
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

function pickExistingColumn(array $existingColumns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (in_array(strtolower($candidate), $existingColumns, true)) {
            return $candidate;
        }
    }

    return null;
}

function getCategoryImageColumnCandidates(): array {
    return [
        'img',
        'hinh',
        'logo',
        'image',
        'hinhanh',
        'hinh_anh',
        'duongdananh',
        'duong_dan_anh',
        'anh',
        'avatar',
    ];
}

function generateNextCategoryId(PDO $pdo): string {
    $rows = $pdo->query("SELECT MaNhomHang FROM nhomhang")->fetchAll(PDO::FETCH_COLUMN);
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

    return 'NH' . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
}

function ensureCategoryImageColumn(PDO $pdo): string {
    $columns = getExistingColumns($pdo, 'nhomhang', true);
    $imgCol = pickExistingColumn($columns, getCategoryImageColumnCandidates());

    if ($imgCol !== null) {
        return $imgCol;
    }

    $pdo->exec("ALTER TABLE nhomhang ADD COLUMN HinhAnh VARCHAR(255) NULL");
    $columns = getExistingColumns($pdo, 'nhomhang', true);

    $imgCol = pickExistingColumn($columns, getCategoryImageColumnCandidates());
    if ($imgCol === null) {
        throw new RuntimeException('Không thể tạo cột ảnh cho bảng nhomhang.');
    }

    return $imgCol;
}

function storeUploadedCategoryImage(array $uploadFile, string &$errorMessage = ''): ?string {
    $errorCode = (int) ($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errorMessage = 'Không thể tải ảnh nhóm hàng lên. Vui lòng thử lại.';
        return null;
    }

    $tmpFile = (string) ($uploadFile['tmp_name'] ?? '');
    $fileSize = (int) ($uploadFile['size'] ?? 0);
    if ($tmpFile === '' || $fileSize <= 0) {
        $errorMessage = 'Tệp ảnh nhóm hàng không hợp lệ.';
        return null;
    }

    if ($fileSize > 5 * 1024 * 1024) {
        $errorMessage = 'Ảnh nhóm hàng vượt quá 5MB.';
        return null;
    }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mimeType = $finfo ? (string) finfo_file($finfo, $tmpFile) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowedMimeMap[$mimeType])) {
        $errorMessage = 'Ảnh nhóm hàng chỉ hỗ trợ JPG, PNG, WEBP hoặc GIF.';
        return null;
    }

    $projectRoot = dirname(__DIR__);
    $uploadDir = $projectRoot . DIRECTORY_SEPARATOR . 'file anh' . DIRECTORY_SEPARATOR . 'categories';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    if (!is_dir($uploadDir)) {
        $errorMessage = 'Không thể tạo thư mục lưu ảnh nhóm hàng.';
        return null;
    }

    try {
        $random = bin2hex(random_bytes(6));
    } catch (Throwable $ignored) {
        $random = (string) mt_rand(100000, 999999);
    }

    $extension = $allowedMimeMap[$mimeType];
    $fileName = 'nh_' . date('Ymd_His') . '_' . $random . '.' . $extension;
    $targetAbsolute = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($tmpFile, $targetAbsolute)) {
        $errorMessage = 'Không thể lưu ảnh nhóm hàng.';
        return null;
    }

    return '../file anh/categories/' . $fileName;
}

function removeUploadedCategoryImageIfManaged(?string $imagePath): void {
    $imagePath = trim((string) $imagePath);
    if ($imagePath === '' || !str_starts_with($imagePath, '../file anh/categories/')) {
        return;
    }

    $projectRoot = dirname(__DIR__);
    $relativePath = ltrim(str_replace('../', '', $imagePath), '/\\');
    $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

$categories = [];
$dbError = '';
$crudMessage = '';
$crudError = '';
$nextCategoryId = '';

$dbHost = '127.0.0.1';
$dbName = 'qlhethongbanhangmini';
$dbUser = 'root';
$dbPass = '';

$categoryImageById = [
    'NH01' => 'TrangSale/douong.png',
    'NH02' => 'TrangSale/doanvat.png',
    'NH03' => 'TrangSale/banhngot.png',
    'NH04' => 'TrangSale/traicay.png',
    'NH05' => 'TrangSale/sua.png',
    'NH06' => 'TrangSale/mianlien.png',
    'NH07' => 'TrangSale/nuocngot.png',
    'NH08' => 'TrangSale/thitsong.png',
    'NH09' => 'TrangSale/Giadung.png',
    'NH10' => 'TrangSale/MyPham.png',
    'NH11' => 'TrangSale/Kem.png',
    'NH12' => 'TrangSale/raucu.png',
    'NH13' => 'TrangSale/dohop.png',
    'NH14' => 'TrangSale/thucannhanh.png',
    'NH15' => 'TrangSale/giavi.png',
    'NH16' => 'TrangSale/bia.png',
];

$categoryImageByName = [
    'đồ uống' => 'TrangSale/douong.png',
    'đồ ăn vặt' => 'TrangSale/doanvat.png',
    'bánh ngọt' => 'TrangSale/banhngot.png',
    'trái cây' => 'TrangSale/traicay.png',
    'sữa' => 'TrangSale/sua.png',
    'mì ăn liền' => 'TrangSale/mianlien.png',
    'nước ngọt' => 'TrangSale/nuocngot.png',
    'tươi sống' => 'TrangSale/thitsong.png',
    'gia dụng' => 'TrangSale/Giadung.png',
    'mỹ phẩm' => 'TrangSale/MyPham.png',
    'kem' => 'TrangSale/Kem.png',
    'rau củ' => 'TrangSale/raucu.png',
    'đồ hộp' => 'TrangSale/dohop.png',
    'thức ăn nhanh' => 'TrangSale/thucannhanh.png',
    'gia vị' => 'TrangSale/giavi.png',
    'bia' => 'TrangSale/bia.png',
];

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

    $categoryImageColumn = ensureCategoryImageColumn($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['crud_action']) ? trim((string) $_POST['crud_action']) : '';

        if ($action === 'add_category') {
            $id = trim((string) ($_POST['category_id'] ?? ''));
            $name = trim((string) ($_POST['category_name'] ?? ''));
            $uploadError = '';
            $uploadedImagePath = storeUploadedCategoryImage($_FILES['category_img_file'] ?? [], $uploadError);
            if ($uploadError !== '') {
                $crudError = $uploadError;
            }

            if ($id === '') {
                $id = generateNextCategoryId($pdo);
            }

            if ($crudError !== '') {
                // Đã có lỗi upload ảnh.
            } elseif ($id === '' || $name === '') {
                $crudError = 'Vui lòng nhập đầy đủ tên nhóm hàng.';
            } else {
                try {
                    $columns = getExistingColumns($pdo, 'nhomhang');
                    $idCol = pickExistingColumn($columns, ['manhomhang', 'ma_nhom_hang', 'manhom', 'id']);
                    $nameCol = pickExistingColumn($columns, ['tennhomhang', 'ten_nhom_hang', 'tennhom', 'name']);
                    $imgCol = pickExistingColumn($columns, getCategoryImageColumnCandidates()) ?? $categoryImageColumn;

                    if ($idCol === null || $nameCol === null) {
                        $crudError = 'Không tìm thấy cột bắt buộc để thêm nhóm hàng (mã nhóm/tên nhóm).';
                    } else {
                        $insertColumns = [$idCol, $nameCol];
                        $insertPlaceholders = [':id', ':name'];
                        $params = [
                            ':id' => $id,
                            ':name' => $name,
                        ];

                        if ($imgCol !== null) {
                            $insertColumns[] = $imgCol;
                            $insertPlaceholders[] = ':img';
                            $params[':img'] = $uploadedImagePath !== null ? $uploadedImagePath : null;
                        }

                        $sql = 'INSERT INTO nhomhang (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        $crudMessage = 'Đã thêm nhóm hàng thành công.';
                    }
                } catch (Throwable $insertError) {
                    $crudError = 'Không thể thêm nhóm hàng: ' . $insertError->getMessage();
                }
            }
        }

        if ($action === 'update_category') {
            $id = trim((string) ($_POST['category_id'] ?? ''));
            $name = trim((string) ($_POST['category_name'] ?? ''));
            $imgCu = trim((string) ($_POST['category_img_current'] ?? ''));
            $uploadError = '';
            $uploadedImagePath = storeUploadedCategoryImage($_FILES['category_img_file'] ?? [], $uploadError);
            if ($uploadError !== '') {
                $crudError = $uploadError;
            }
            $img = $uploadedImagePath !== null ? $uploadedImagePath : ($imgCu !== '' ? $imgCu : null);

            if ($crudError !== '') {
                // Đã có lỗi upload ảnh.
            } elseif ($id === '' || $name === '') {
                $crudError = 'Dữ liệu cập nhật không hợp lệ.';
            } else {
                try {
                    $columns = getExistingColumns($pdo, 'nhomhang');
                    $idCol = pickExistingColumn($columns, ['manhomhang', 'ma_nhom_hang', 'manhom', 'id']);
                    $nameCol = pickExistingColumn($columns, ['tennhomhang', 'ten_nhom_hang', 'tennhom', 'name']);
                    $imgCol = pickExistingColumn($columns, getCategoryImageColumnCandidates()) ?? $categoryImageColumn;

                    if ($idCol === null || $nameCol === null) {
                        $crudError = 'Không tìm thấy cột bắt buộc để cập nhật nhóm hàng.';
                    } else {
                        $setParts = ["{$nameCol} = :name"];
                        $params = [
                            ':id' => $id,
                            ':name' => $name,
                        ];

                        if ($imgCol !== null) {
                            $setParts[] = "{$imgCol} = :img";
                            $params[':img'] = $img;
                        }

                        $sql = 'UPDATE nhomhang SET ' . implode(', ', $setParts) . " WHERE {$idCol} = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        if ($uploadedImagePath !== null && $imgCu !== '' && $imgCu !== $uploadedImagePath) {
                            removeUploadedCategoryImageIfManaged($imgCu);
                        }

                        $crudMessage = 'Đã cập nhật nhóm hàng.';
                    }
                } catch (Throwable $updateError) {
                    $crudError = 'Không thể cập nhật nhóm hàng: ' . $updateError->getMessage();
                }
            }
        }

        if ($action === 'delete_category') {
            $id = trim((string) ($_POST['category_id'] ?? ''));
            if ($id === '') {
                $crudError = 'Không xác định được nhóm hàng để xóa.';
            } else {
                try {
                    $columns = getExistingColumns($pdo, 'nhomhang');
                    $idCol = pickExistingColumn($columns, ['manhomhang', 'ma_nhom_hang', 'manhom', 'id']);

                    if ($idCol === null) {
                        $crudError = 'Không tìm thấy cột mã nhóm hàng để xóa.';
                    } else {
                        $hangHoaColumns = getExistingColumns($pdo, 'hanghoa');
                        $hangHoaCategoryCol = pickExistingColumn($hangHoaColumns, ['manhomhang', 'ma_nhom_hang', 'manhom', 'nhomhang']);

                        if ($hangHoaCategoryCol !== null) {
                            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM hanghoa WHERE {$hangHoaCategoryCol} = :id");
                            $countStmt->execute([':id' => $id]);
                            $linkedProductCount = (int) $countStmt->fetchColumn();

                            if ($linkedProductCount > 0) {
                                $crudError = 'Không thể xóa nhóm hàng vì vẫn còn ' . $linkedProductCount . ' sản phẩm thuộc nhóm này. Vui lòng chuyển nhóm hoặc xóa sản phẩm trước.';
                            } else {
                                $stmt = $pdo->prepare("DELETE FROM nhomhang WHERE {$idCol} = :id");
                                $stmt->execute([':id' => $id]);
                                $crudMessage = 'Đã xóa nhóm hàng.';
                            }
                        } else {
                            $crudError = 'Không thể xác định cột mã nhóm hàng trong bảng sản phẩm để kiểm tra ràng buộc xóa.';
                        }
                    }
                } catch (Throwable $deleteError) {
                    $deleteMessage = (string) $deleteError->getMessage();
                    if (str_contains($deleteMessage, 'SQLSTATE[23000]') || str_contains($deleteMessage, '1451')) {
                        $crudError = 'Không thể xóa nhóm hàng vì vẫn còn sản phẩm liên kết. Vui lòng chuyển nhóm hoặc xóa sản phẩm trước.';
                    } else {
                        $crudError = 'Không thể xóa nhóm hàng: ' . $deleteMessage;
                    }
                }
            }
        }
    }

    $rows = $pdo->query("SELECT * FROM nhomhang")->fetchAll();
    foreach ($rows as $row) {
        $id = (string) pickCategoryValue($row, ['manhomhang', 'ma_nhom_hang', 'manhom', 'id']);
        $name = (string) pickCategoryValue($row, ['tennhomhang', 'ten_nhom_hang', 'tennhom', 'name']);
        $img = (string) pickCategoryValue($row, getCategoryImageColumnCandidates(), '');

        if ($img === '') {
            if (isset($categoryImageById[$id])) {
                $img = $categoryImageById[$id];
            } else {
                $normalizedName = function_exists('mb_strtolower') ? mb_strtolower(trim($name)) : strtolower(trim($name));
                if (isset($categoryImageByName[$normalizedName])) {
                    $img = $categoryImageByName[$normalizedName];
                }
            }

            if ($img === '') {
                $shortName = function_exists('mb_substr') ? trim(mb_substr($name, 0, 2)) : trim(substr($name, 0, 2));
                if ($shortName === '') {
                    $shortName = 'NH';
                }
                $img = '../TrangUser/ack.png';
            }
        }

        if ($img !== '' && !preg_match('/^(?:https?:)?\/\//i', $img) && !str_starts_with($img, '../') && !str_starts_with($img, '/')) {
            $img = '../' . ltrim($img, './');
        }

        $categories[] = [
            'img' => $img,
            'id' => $id,
            'name' => $name,
        ];
    }

    $nextCategoryId = generateNextCategoryId($pdo);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$totalCategories = count($categories);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Admin - Quản lí sản xuất</title>
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
        background-color: #ffffff;
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
        border-right: 1px solid #f0f2f5;
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
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }

    .product-top-sticky {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        z-index: 1030;
        background: #ffffff;
        padding: 20px 40px 12px;
        border-bottom: none;
        box-shadow: none;
    }

    .product-top-sticky .alert {
        margin-bottom: 10px;
    }

    .category-content-offset {
        position: fixed;
        left: calc(var(--sidebar-width) + 40px);
        right: 40px;
        top: 320px;
        bottom: 20px;
        overflow: hidden;
    }

    body.modal-open #categoryContentOffset {
        overflow: visible;
    }

    /* HEADER & SEARCH */
    .top-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .page-title {
        font-weight: 700;
        color: #000;
        font-size: 1.5rem;
        margin: 0;
    }

    .search-top {
        border: 1px solid #ccc;
        border-radius: 20px;
        padding: 6px 15px;
        width: 250px;
        outline: none;
        font-size: 0.9rem;
    }

    /* SUMMARY CARDS */
    .summary-cards {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .card-box {
        background-color: #cce3ff;
        border: 1px solid #a8cfff;
        border-radius: 12px;
        padding: 15px 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .card-title-text {
        color: #555;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .card-number {
        color: #222;
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
    }

    /* BUTTON THÊM */
    .btn-add-product {
        background-color: #2196f3;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 8px 20px;
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 30px;
    }

    .btn-add-product:hover {
        background-color: #1976d2;
        color: white;
    }

    /* TABLE CONTAINER */
    .table-container {
        background: white;
        border-radius: 12px;
        border: 1px solid #f0f0f0;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .table-header {
        background-color: #ffffff;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f0f0f0;
    }

    .table-header h5 {
        margin: 0;
        font-weight: 700;
        font-size: 1.1rem;
        color: #222;
    }

    .category-table-scroll {
        flex: 1 1 auto;
        overflow: auto;
        min-height: 220px;
    }

    /* TABLE STYLES */
    .table {
        margin-bottom: 0;
    }

    .table thead th {
        border-bottom: 1px solid #f0f0f0;
        padding: 15px 20px;
        font-weight: 700;
        font-size: 0.9rem;
        color: #333;
        position: sticky;
        top: 0;
        z-index: 6;
        background: #F8F9FA;
    }

    .table tbody td {
        padding: 12px 20px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.95rem;
        color: #444;
    }

    .table tbody tr:last-child td {
        border-bottom: none;
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
    }

    .category-img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid #ddd;
    }

    /* Row selection enhancement and Detail Panel */
    .table tbody tr.selected {
        background-color: #cce3ff;
        font-weight: 500;
        border-left: 4px solid #007bff;
        box-shadow: inset 0 0 8px rgba(0, 123, 255, 0.1);
    }

    .table tbody tr.selected td:first-child {
        padding-left: 16px;
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
        width: min(900px, 92vw);
        max-height: 85vh;
        overflow-y: auto;
        background: white;
        border-radius: 12px;
        border: 1px solid #e9ecef;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        padding: 25px;
        z-index: 1055;
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
        margin-bottom: 20px;
        padding-bottom: 15px;
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
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .detail-field {
        display: flex;
        flex-direction: column;
    }

    .detail-field label {
        font-weight: 600;
        color: #667eea;
        font-size: 0.85rem;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-field input,
    .detail-field textarea {
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 0.95rem;
        color: #444;
        background-color: #f8f9fa;
    }

    .detail-field input:focus,
    .detail-field textarea:focus {
        background-color: #fff;
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .detail-field-preview {
        text-align: center;
    }

    .detail-field-preview img {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: contain;
        border: 2px solid #e9ecef;
        background-color: #fff;
        padding: 5px;
    }

    .detail-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        position: sticky;
        bottom: 0;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #f0f2f5;
        background: #fff;
    }

    .detail-actions button {
        font-size: 0.9rem;
        font-weight: 500;
        padding: 8px 18px;
    }

    @media (max-width: 768px) {
        .product-top-sticky {
            padding: 20px 16px 10px;
        }

        .category-content-offset {
            left: calc(var(--sidebar-width) + 16px);
            right: 16px;
            bottom: 16px;
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
            <a href="admin-nhomhang.php" class="nav-item active"><i class="fas fa-folder"></i> Nhóm hàng</a>
            <a href="admin-nhaphang.php" class="nav-item"><i class="fas fa-truck-loading"></i> Nhập hàng</a>
            <a href="admin-nhacungcap.php" class="nav-item"><i class="fas fa-building"></i> Nhà cung cấp</a>
            <a href="admin-bophan.php" class="nav-item"><i class="fas fa-sitemap"></i> Bộ phận</a>
            <a href="admin-chucvu.php" class="nav-item"><i class="fas fa-user-tag"></i> Chức vụ</a>
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
                <button class="btn btn-light rounded-circle shadow-sm"><i class="fas fa-times"></i></button>
            </div>
            <div class="top-header">
                <h2 class="page-title">Quản lí nhóm hàng</h2>
                <input type="text" class="search-top" placeholder="Tìm kiếm">
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

            <div class="summary-cards">
                <div class="card-box">
                    <span class="card-title-text">Tổng nhóm hàng</span>
                    <p class="card-number"><?php echo $totalCategories; ?></p>
                </div>
                <div class="card-box">
                    <span class="card-title-text">Đang hiển thị</span>
                    <p class="card-number"><?php echo $totalCategories; ?></p>
                </div>
                <div class="card-box">
                    <span class="card-title-text">Chưa có dữ liệu</span>
                    <p class="card-number"><?php echo ($totalCategories === 0) ? 1 : 0; ?></p>
                </div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button class="btn btn-add-product mb-0" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-1"></i> Thêm nhóm hàng
                </button>
                <button class="btn btn-warning fw-semibold" id="btnEditCategory" disabled>
                    <i class="fas fa-pen me-1"></i> Sửa nhóm hàng chi
                </button>
                <button class="btn btn-info fw-semibold text-white" id="btnViewCategory" disabled>
                    <i class="fas fa-eye me-1"></i> Xem chi tiết
                </button>
                <button class="btn btn-danger fw-semibold" id="btnDeleteCategory" disabled>
                    <i class="fas fa-trash me-1"></i> Xóa
                </button>
            </div>
        </div>

        <div id="categoryContentOffset" class="category-content-offset">

            <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="addCategoryModalLabel">Thêm nhóm hàng</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="crud_action" value="add_category">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="categoryId" class="form-label">Mã nhóm hàng</label>
                                        <input type="text" class="form-control" id="categoryId" name="category_id"
                                            value="<?php echo htmlspecialchars($nextCategoryId); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="categoryName" class="form-label">Tên nhóm hàng</label>
                                        <input type="text" class="form-control" id="categoryName" name="category_name"
                                            placeholder="Nhập tên nhóm hàng" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="categoryImgFile" class="form-label">Hình nhóm hàng</label>
                                        <input type="file" class="form-control" id="categoryImgFile"
                                            name="category_img_file" accept="image/jpeg,image/png,image/webp,image/gif">
                                        <div class="form-text">Hỗ trợ JPG, PNG, WEBP, GIF. Tối đa 5MB.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" class="btn btn-primary">Lưu nhóm hàng</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="detailOverlay" class="detail-overlay" onclick="closeDetailPanel()"></div>

            <!-- Detail Panel -->
            <form id="detailPanel" class="detail-panel" method="post" enctype="multipart/form-data">
                <input type="hidden" name="crud_action" value="update_category">
                <div class="detail-header">
                    <h5>Chi tiết nhóm hàng</h5>
                    <button type="button" class="btn-close" onclick="closeDetailPanel()"></button>
                </div>
                <div class="detail-content">
                    <div class="detail-field-preview">
                        <p style="font-weight: 600; margin-bottom: 10px;">Ảnh</p>
                        <img id="detailImg" src="" alt="Ảnh" onerror="this.src='../TrangUser/ack.png'">
                    </div>
                    <div class="detail-field">
                        <label>Mã nhóm hàng</label>
                        <input type="text" id="detailId" name="category_id" readonly>
                    </div>
                    <div class="detail-field">
                        <label>Tên nhóm hàng</label>
                        <input type="text" id="detailName" name="category_name">
                    </div>
                    <div class="detail-field" style="grid-column: 1 / -1;">
                        <label>Đổi ảnh nhóm hàng</label>
                        <input type="hidden" id="detailImgCurrent" name="category_img_current">
                        <input type="file" class="form-control" id="detailImgFile" name="category_img_file"
                            accept="image/jpeg,image/png,image/webp,image/gif">
                        <div class="form-text">Để trống nếu muốn giữ ảnh hiện tại.</div>
                    </div>
                </div>
                <div class="detail-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeDetailPanel()">Đóng</button>
                    <button type="button" class="btn btn-primary" id="btnDetailSave">Lưu thay đổi</button>
                </div>
            </form>

            <div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteCategoryModalLabel">Xác nhận xóa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Bạn có chắc chắn muốn xóa nhóm hàng <strong id="deleteCategoryName"></strong> không?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="button" class="btn btn-danger" id="btnConfirmDeleteCategory">Xóa</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h5>Danh sách nhóm hàng</h5>
                    <a href="#" class="text-dark"><i class="fas fa-download"></i></a>
                </div>
                <div class="category-table-scroll">
                    <table class="table text-center">
                        <thead>
                            <tr>
                                <th class="text-center" width="15%">Nhóm hàng</th>
                                <th class="text-center" width="30%">Mã nhóm hàng</th>
                                <th class="text-center" width="55%">Tên nhóm hàng</th>
                            </tr>
                        </thead>
                        <tbody id="categoryTableBody">
                            <?php foreach($categories as $c): ?>
                            <tr class="category-row" data-id="<?php echo htmlspecialchars($c['id']); ?>"
                                data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                data-img="<?php echo htmlspecialchars($c['img']); ?>">
                                <td class="text-center">
                                    <img src="<?php echo $c['img']; ?>" alt="<?php echo $c['name']; ?>"
                                        class="category-img" onerror="this.src='../TrangUser/ack.png'">
                                </td>
                                <td class="text-center"><?php echo $c['id']; ?></td>
                                <td class="text-center"><?php echo $c['name']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
    <script>
    let selectedCategoryId = null;
    let pendingDeleteCategoryId = null;
    let isCategoryDetailReadOnly = false;

    const addCategoryModalEl = document.getElementById('addCategoryModal');
    if (addCategoryModalEl && addCategoryModalEl.parentElement !== document.body) {
        document.body.appendChild(addCategoryModalEl);
    }

    const deleteCategoryModalEl = document.getElementById('deleteCategoryModal');
    if (deleteCategoryModalEl && deleteCategoryModalEl.parentElement !== document.body) {
        document.body.appendChild(deleteCategoryModalEl);
    }

    const detailOverlayEl = document.getElementById('detailOverlay');
    if (detailOverlayEl && detailOverlayEl.parentElement !== document.body) {
        document.body.appendChild(detailOverlayEl);
    }

    const detailPanelEl = document.getElementById('detailPanel');
    if (detailPanelEl && detailPanelEl.parentElement !== document.body) {
        document.body.appendChild(detailPanelEl);
    }

    const btnEditCategory = document.getElementById('btnEditCategory');
    const btnViewCategory = document.getElementById('btnViewCategory');
    const btnDeleteCategory = document.getElementById('btnDeleteCategory');

    function updateCategoryActionButtons(enabled) {
        if (btnEditCategory) btnEditCategory.disabled = !enabled;
        if (btnViewCategory) btnViewCategory.disabled = !enabled;
        if (btnDeleteCategory) btnDeleteCategory.disabled = !enabled;
    }

    function selectCategoryRow(row) {
        if (!row) {
            selectedCategoryId = null;
            updateCategoryActionButtons(false);
            return;
        }

        document.querySelectorAll('.category-row').forEach(r => r.classList.remove('selected'));
        row.classList.add('selected');
        selectedCategoryId = row.getAttribute('data-id');
        updateCategoryActionButtons(!!selectedCategoryId);
    }

    function getSelectedCategoryRow() {
        if (selectedCategoryId) {
            const byId = Array.from(document.querySelectorAll('.category-row')).find(r =>
                r.getAttribute('data-id') === selectedCategoryId
            );
            if (byId) {
                return byId;
            }
        }

        const byClass = document.querySelector('.category-row.selected');
        if (byClass) {
            selectedCategoryId = byClass.getAttribute('data-id');
            return byClass;
        }

        return null;
    }

    const categoryTableBody = document.getElementById('categoryTableBody');
    if (categoryTableBody) {
        categoryTableBody.addEventListener('click', function(e) {
            const row = e.target.closest('.category-row');
            if (!row) {
                return;
            }
            selectCategoryRow(row);
        });
    }

    const categorySearchInput = document.querySelector('.search-top');

    function normalizeSearchText(value) {
        return (value || '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function filterCategoryRows() {
        const keyword = normalizeSearchText(categorySearchInput ? categorySearchInput.value : '');
        const rows = document.querySelectorAll('.category-row');
        let hasVisibleSelection = false;

        rows.forEach((row) => {
            const id = normalizeSearchText(row.getAttribute('data-id') || '');
            const name = normalizeSearchText(row.getAttribute('data-name') || '');
            const visible = keyword === '' || id.includes(keyword) || name.includes(keyword);

            row.style.display = visible ? '' : 'none';

            if (visible && selectedCategoryId && row.getAttribute('data-id') === selectedCategoryId) {
                hasVisibleSelection = true;
                row.classList.add('selected');
            } else if (!visible) {
                row.classList.remove('selected');
            }
        });

        if (!hasVisibleSelection) {
            selectedCategoryId = null;
            document.querySelectorAll('.category-row').forEach(r => r.classList.remove('selected'));
            updateCategoryActionButtons(false);
        }
    }

    if (categorySearchInput) {
        categorySearchInput.addEventListener('input', filterCategoryRows);
    }

    updateCategoryActionButtons(false);

    function setCategoryDetailReadOnly(readOnly) {
        isCategoryDetailReadOnly = !!readOnly;

        const nameField = document.getElementById('detailName');
        const imgField = document.getElementById('detailImgFile');
        if (nameField) {
            nameField.readOnly = isCategoryDetailReadOnly;
        }
        if (imgField) {
            imgField.disabled = isCategoryDetailReadOnly;
        }

        const saveBtn = document.getElementById('btnDetailSave');
        if (saveBtn) {
            saveBtn.style.display = isCategoryDetailReadOnly ? 'none' : 'inline-block';
        }
    }

    // Show detail panel
    function showDetailPanel(id, name, img, readOnly = false) {
        document.getElementById('detailId').value = id;
        document.getElementById('detailName').value = name;
        document.getElementById('detailImgCurrent').value = img || '';
        const detailImgFile = document.getElementById('detailImgFile');
        if (detailImgFile) {
            detailImgFile.value = '';
        }
        document.getElementById('detailImg').src = img;
        setCategoryDetailReadOnly(readOnly);
        document.getElementById('detailPanel').classList.add('show');
        document.getElementById('detailOverlay').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    // Close detail panel
    function closeDetailPanel() {
        document.getElementById('detailPanel').classList.remove('show');
        document.getElementById('detailOverlay').classList.remove('show');
        document.body.style.overflow = '';
    }

    // Edit button click
    document.getElementById('btnEditCategory').addEventListener('click', function() {
        const selectedRow = getSelectedCategoryRow();
        if (!selectedRow) {
            alert('Vui lòng chọn nhóm hàng để sửa');
            return;
        }

        showDetailPanel(
            selectedRow.getAttribute('data-id'),
            selectedRow.getAttribute('data-name'),
            selectedRow.getAttribute('data-img'),
            false
        );
    });

    document.getElementById('btnViewCategory').addEventListener('click', function() {
        const selectedRow = getSelectedCategoryRow();
        if (!selectedRow) {
            alert('Vui lòng chọn nhóm hàng để xem chi tiết');
            return;
        }

        showDetailPanel(
            selectedRow.getAttribute('data-id'),
            selectedRow.getAttribute('data-name'),
            selectedRow.getAttribute('data-img'),
            true
        );
    });

    // Detail Save button
    document.getElementById('btnDetailSave').addEventListener('click', function() {
        if (isCategoryDetailReadOnly) {
            return;
        }

        const name = document.getElementById('detailName').value.trim();

        if (!name) {
            alert('Tên nhóm hàng không được để trống');
            return;
        }

        const detailForm = document.getElementById('detailPanel');
        if (detailForm) {
            detailForm.submit();
        }
    });

    const detailImgFileInput = document.getElementById('detailImgFile');
    if (detailImgFileInput) {
        detailImgFileInput.addEventListener('change', function() {
            const [file] = detailImgFileInput.files || [];
            if (!file) {
                document.getElementById('detailImg').src = document.getElementById('detailImgCurrent')?.value ||
                    '../TrangUser/ack.png';
                return;
            }
            document.getElementById('detailImg').src = URL.createObjectURL(file);
        });
    }

    // Delete button click
    document.getElementById('btnDeleteCategory').addEventListener('click', function() {
        const selectedRow = getSelectedCategoryRow();
        if (!selectedRow) {
            alert('Vui lòng chọn nhóm hàng để xóa');
            return;
        }

        pendingDeleteCategoryId = selectedRow.getAttribute('data-id');
        const categoryName = selectedRow.getAttribute('data-name') || pendingDeleteCategoryId;
        document.getElementById('deleteCategoryName').textContent = categoryName;

        const deleteModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteCategoryModal'));
        deleteModal.show();
    });

    document.getElementById('btnConfirmDeleteCategory').addEventListener('click', function() {
        if (!pendingDeleteCategoryId) {
            return;
        }

        const deleteModalEl = document.getElementById('deleteCategoryModal');
        const deleteModal = bootstrap.Modal.getInstance(deleteModalEl);
        if (deleteModal) {
            deleteModal.hide();
        }

        deleteCategory(pendingDeleteCategoryId);
    });

    document.getElementById('deleteCategoryModal').addEventListener('hidden.bs.modal', function() {
        pendingDeleteCategoryId = null;
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDetailPanel();
        }
    });

    document.getElementById('addCategoryModal').addEventListener('show.bs.modal', function() {
        closeDetailPanel();
        const categoryIdInput = document.getElementById('categoryId');
        if (categoryIdInput) {
            categoryIdInput.value = '<?php echo htmlspecialchars($nextCategoryId); ?>';
        }
    });

    function deleteCategory(id) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
                <input type="hidden" name="crud_action" value="delete_category">
                <input type="hidden" name="category_id" value="${id}">
            `;
        document.body.appendChild(form);
        form.submit();
    }

    function syncFixedTopOffset() {
        const fixedTop = document.querySelector('.product-top-sticky');
        const contentOffset = document.getElementById('categoryContentOffset');
        if (!fixedTop || !contentOffset) {
            return;
        }

        const topHeight = Math.ceil(fixedTop.getBoundingClientRect().height);
        contentOffset.style.top = `${topHeight}px`;
    }

    window.addEventListener('resize', syncFixedTopOffset);
    window.addEventListener('load', syncFixedTopOffset);
    syncFixedTopOffset();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="admin-search.js?v=20260414-2"></script>
</body>

</html>