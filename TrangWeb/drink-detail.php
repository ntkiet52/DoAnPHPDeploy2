<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/catalog_data.php';

$isAdminUser = isset($_SESSION['user_role']) && strtolower((string) $_SESSION['user_role']) === 'admin';
$adminEditError = '';
$adminEditSuccess = '';

function detailPageGetPdo(): PDO
{
    return new PDO(
        'mysql:host=webbanhang-mysql.mysql.database.azure.com;dbname=qlhethongbanhangmini;charset=utf8mb4',
        'webbanhang123',
        'thanhkiet1234ACK@',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]
    );
}

function detailEnsureCommentSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS hanghoa_binhluan (
            Id INT NOT NULL AUTO_INCREMENT,
            MaHang VARCHAR(10) NOT NULL,
            ParentId INT NULL,
            TenNguoiDung VARCHAR(120) NOT NULL,
            NoiDung TEXT NOT NULL,
            HinhAnh VARCHAR(255) NULL,
            SoSao TINYINT NOT NULL DEFAULT 0,
            NgayTao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (Id),
            KEY idx_hanghoa_binhluan_mahang (MaHang),
            KEY idx_hanghoa_binhluan_parent (ParentId),
            CONSTRAINT fk_hanghoa_binhluan_mahang FOREIGN KEY (MaHang)
                REFERENCES hanghoa (MaHang) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    try {
        $pdo->exec("ALTER TABLE hanghoa_binhluan ADD COLUMN IF NOT EXISTS ParentId INT NULL AFTER MaHang");
    } catch (Throwable $e) {
        // MariaDB cũ có thể không hỗ trợ IF NOT EXISTS, bỏ qua để không chặn trang.
    }

    try {
        $pdo->exec("ALTER TABLE hanghoa_binhluan ADD COLUMN IF NOT EXISTS HinhAnh VARCHAR(255) NULL AFTER NoiDung");
    } catch (Throwable $e) {
        // MariaDB cũ có thể không hỗ trợ IF NOT EXISTS, bỏ qua để không chặn trang.
    }

    try {
        $pdo->exec("ALTER TABLE hanghoa_binhluan ADD COLUMN IF NOT EXISTS NguoiDungId INT NULL AFTER SoSao");
    } catch (Throwable $e) {
        // MariaDB cũ có thể không hỗ trợ IF NOT EXISTS, bỏ qua để không chặn trang.
    }

    try {
        $pdo->exec("ALTER TABLE hanghoa_binhluan ADD COLUMN IF NOT EXISTS VaiTroNguoiDung VARCHAR(30) NULL AFTER NguoiDungId");
    } catch (Throwable $e) {
        // MariaDB cũ có thể không hỗ trợ IF NOT EXISTS, bỏ qua để không chặn trang.
    }
}

function detailHandleCommentImageUpload(array $file): array
{
    if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => '', 'error' => ''];
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => '', 'error' => 'Tải ảnh lên thất bại, vui lòng thử lại.'];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['path' => '', 'error' => 'Tệp ảnh không hợp lệ.'];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > (5 * 1024 * 1024)) {
        return ['path' => '', 'error' => 'Ảnh tối đa 5MB.'];
    }

    $imgInfo = @getimagesize($tmpPath);
    if ($imgInfo === false) {
        return ['path' => '', 'error' => 'Tệp tải lên không phải ảnh hợp lệ.'];
    }

    $mime = strtolower((string)($imgInfo['mime'] ?? ''));
    $allowedMimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowedMimeToExt[$mime])) {
        return ['path' => '', 'error' => 'Chỉ hỗ trợ ảnh JPG, PNG, WEBP hoặc GIF.'];
    }

    $uploadDir = __DIR__ . '/uploads/comment-images';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['path' => '', 'error' => 'Không thể tạo thư mục lưu ảnh bình luận.'];
    }

    $ext = $allowedMimeToExt[$mime];
    $fileName = 'cmt_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $targetPath = $uploadDir . '/' . $fileName;

    if (!@move_uploaded_file($tmpPath, $targetPath)) {
        return ['path' => '', 'error' => 'Không thể lưu ảnh bình luận.'];
    }

    return ['path' => 'uploads/comment-images/' . $fileName, 'error' => ''];
}

if ($isAdminUser && $_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['admin_action'] ?? '') === 'update_detail_product') {
    $editProductId = trim((string) ($_POST['product_id'] ?? ''));
    $editName = trim((string) ($_POST['ten_hang'] ?? ''));
    $editDvt = trim((string) ($_POST['dvt'] ?? ''));
    $editImage = trim((string) ($_POST['hinh_anh'] ?? ''));
    $editVat = (float) ($_POST['vat'] ?? 0);
    $editPrice = (float) ($_POST['don_gia'] ?? 0);
    $editDescription = trim((string) ($_POST['mo_ta_chi_tiet'] ?? ''));

    if ($editProductId === '' || $editName === '' || $editDvt === '' || $editPrice <= 0) {
        $adminEditError = 'Vui lòng nhập đầy đủ thông tin hợp lệ (tên, đơn vị tính, đơn giá).';
    } else {
        try {
            $pdo = detailPageGetPdo();

            $soTienCoThue = $editPrice * (1 + ($editVat / 100));

            $stmt = $pdo->prepare(
                'UPDATE hanghoa
                 SET TenHang = :tenhang,
                     DVT = :dvt,
                     DonGia = :dongia,
                     VAT = :vat,
                     SoTienCoThue = :sotiencothue,
                     HinhAnh = :hinhanh
                 WHERE MaHang = :mahang'
            );

            $stmt->execute([
                ':tenhang' => $editName,
                ':dvt' => $editDvt,
                ':dongia' => $editPrice,
                ':vat' => (string) $editVat,
                ':sotiencothue' => $soTienCoThue,
                ':hinhanh' => $editImage,
                ':mahang' => $editProductId,
            ]);

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS hanghoa_mota_chitiet (
                    MaHang VARCHAR(10) NOT NULL,
                    MoTaChiTiet TEXT NOT NULL,
                    NgayCapNhat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (MaHang),
                    CONSTRAINT fk_hanghoa_mota_chitiet_mahang FOREIGN KEY (MaHang)
                        REFERENCES hanghoa (MaHang) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );

            if ($editDescription !== '') {
                $descStmt = $pdo->prepare(
                    'INSERT INTO hanghoa_mota_chitiet (MaHang, MoTaChiTiet)
                     VALUES (:mahang, :mota)
                     ON DUPLICATE KEY UPDATE MoTaChiTiet = VALUES(MoTaChiTiet), NgayCapNhat = CURRENT_TIMESTAMP'
                );
                $descStmt->execute([
                    ':mahang' => $editProductId,
                    ':mota' => $editDescription,
                ]);
            }

            header('Location: drink-detail.php?id=' . urlencode($editProductId) . '&admin_saved=1');
            exit;
        } catch (Throwable $e) {
            $adminEditError = 'Không thể lưu thông tin sản phẩm: ' . $e->getMessage();
        }
    }
}

if ($isAdminUser && (string)($_GET['admin_saved'] ?? '') === '1') {
    $adminEditSuccess = 'Đã cập nhật thông tin sản phẩm thành công.';
}

$products = [
    'trasua' => [
        'name' => 'Trà sữa truyền thống',
        'price' => 25000,
        'old_price' => 40000,
        'sold' => '10.0k',
        'rating' => 4.0,
        'image' => '../SPdouong/trasua.png',
        'desc_list' => [
            'Tên sản phẩm: Trà sữa truyền thống',
            'Loại: Đồ uống pha sẵn dạng ly',
            'Hương vị: Trà sữa truyền thống đậm vị',
            'Đặc điểm: Vị ngọt béo hài hòa, thơm mùi trà sữa, dễ uống.'
        ]
    ],
    'thaixanh' => [
        'name' => 'Trà sữa thái xanh',
        'price' => 20000,
        'old_price' => 30000,
        'sold' => '7.5k',
        'rating' => 4.2,
        'image' => '../SPdouong/thaixanh.png',
        'desc_list' => [
            'Tên sản phẩm: Trà sữa thái xanh',
            'Loại: Đồ uống pha sẵn dạng ly',
            'Hương vị: Trà xanh thơm mát, béo nhẹ',
            'Đặc điểm: Màu xanh dịu, vị thanh, phù hợp uống lạnh.'
        ]
    ],
    'socola' => [
        'name' => 'Trà sữa Socola',
        'price' => 25000,
        'old_price' => 35000,
        'sold' => '5.1k',
        'rating' => 4.1,
        'image' => '../SPdouong/socola.png',
        'desc_list' => [
            'Tên sản phẩm: Trà sữa Socola',
            'Loại: Đồ uống pha sẵn dạng ly',
            'Hương vị: Socola thơm đậm, hậu vị béo',
            'Đặc điểm: Dễ uống, phù hợp khách thích vị ngọt đậm.'
        ]
    ],
    'suatuoi' => [
        'name' => 'Sữa tươi trân châu đường đen',
        'price' => 20000,
        'old_price' => 30000,
        'sold' => '8.0k',
        'rating' => 4.3,
        'image' => '../SPdouong/suatuoi.png',
        'desc_list' => [
            'Tên sản phẩm: Sữa tươi trân châu đường đen',
            'Loại: Đồ uống pha sẵn dạng ly',
            'Hương vị: Sữa tươi ngậy, đường đen thơm',
            'Đặc điểm: Trân châu dai mềm, vị ngọt dịu.'
        ]
    ],
    'tratac' => [
        'name' => 'Trà tắc thái xanh',
        'price' => 8000,
        'old_price' => 12000,
        'sold' => '6.2k',
        'rating' => 4.0,
        'image' => '../SPdouong/Tratac.png',
        'desc_list' => [
            'Tên sản phẩm: Trà tắc thái xanh',
            'Loại: Đồ uống giải khát',
            'Hương vị: Chua ngọt nhẹ, mùi trà dịu',
            'Đặc điểm: Mát lạnh, phù hợp ngày nóng.'
        ]
    ],
    'tra' => [
        'name' => 'Trà tắc',
        'price' => 5000,
        'old_price' => 8000,
        'sold' => '9.3k',
        'rating' => 4.0,
        'image' => '../SPdouong/Tra.png',
        'desc_list' => [
            'Tên sản phẩm: Trà tắc',
            'Loại: Đồ uống giải khát',
            'Hương vị: Chua thanh, ngọt nhẹ',
            'Đặc điểm: Dễ uống, giá bình dân.'
        ]
    ],
    'capheden' => [
        'name' => 'Cà phê đen',
        'price' => 7000,
        'old_price' => 10000,
        'sold' => '4.5k',
        'rating' => 4.1,
        'image' => '../SPdouong/Capheden.png',
        'desc_list' => [
            'Tên sản phẩm: Cà phê đen',
            'Loại: Cà phê pha sẵn',
            'Hương vị: Đậm đà, thơm mạnh',
            'Đặc điểm: Tỉnh táo nhanh, phù hợp buổi sáng.'
        ]
    ],
    'caphesua' => [
        'name' => 'Cà phê sữa',
        'price' => 10000,
        'old_price' => 15000,
        'sold' => '6.9k',
        'rating' => 4.2,
        'image' => '../SPdouong/Caphesua.png',
        'desc_list' => [
            'Tên sản phẩm: Cà phê sữa',
            'Loại: Cà phê pha sẵn',
            'Hương vị: Cân bằng đắng và ngọt',
            'Đặc điểm: Dễ uống, thơm mùi cà phê rang.'
        ]
    ],
    'rauma' => [
        'name' => 'Rau má',
        'price' => 10000,
        'old_price' => 14000,
        'sold' => '3.2k',
        'rating' => 4.0,
        'image' => '../SPdouong/rauma.png',
        'desc_list' => [
            'Tên sản phẩm: Rau má',
            'Loại: Nước ép giải nhiệt',
            'Hương vị: Thanh mát, dịu nhẹ',
            'Đặc điểm: Uống lạnh ngon hơn.'
        ]
    ],
    'matcha' => [
        'name' => 'Matchaaa',
        'price' => 19000,
        'old_price' => 25000,
        'sold' => '2.8k',
        'rating' => 4.1,
        'image' => '../SPdouong/matcha.png',
        'desc_list' => [
            'Tên sản phẩm: Matchaaa',
            'Loại: Đồ uống pha sẵn',
            'Hương vị: Matcha thơm, ngậy nhẹ',
            'Đặc điểm: Màu sắc bắt mắt, vị dễ chịu.'
        ]
    ],
    'aquafina' => [
        'name' => 'Nước suối Aquafina',
        'price' => 5000,
        'old_price' => 7000,
        'sold' => '12.0k',
        'rating' => 4.4,
        'image' => '../SPdouong/Aquafina.png',
        'desc_list' => [
            'Tên sản phẩm: Nước suối Aquafina',
            'Loại: Nước uống đóng chai',
            'Hương vị: Tinh khiết',
            'Đặc điểm: Tiện lợi, dùng hằng ngày.'
        ]
    ],
    'boss' => [
        'name' => 'Lon cà phê sữa',
        'price' => 10000,
        'old_price' => 14000,
        'sold' => '5.0k',
        'rating' => 4.0,
        'image' => '../SPdouong/Boss.png',
        'desc_list' => [
            'Tên sản phẩm: Lon cà phê sữa',
            'Loại: Cà phê lon',
            'Hương vị: Ngọt nhẹ, thơm cà phê',
            'Đặc điểm: Tiện lợi mang đi.'
        ]
    ],
    'c2' => [
        'name' => 'Chai C2',
        'price' => 10000,
        'old_price' => 12000,
        'sold' => '7.1k',
        'rating' => 4.1,
        'image' => '../SPdouong/C2.png',
        'desc_list' => [
            'Tên sản phẩm: Chai C2',
            'Loại: Trà đóng chai',
            'Hương vị: Trà ngọt thanh',
            'Đặc điểm: Uống lạnh rất ngon.'
        ]
    ],
    'cam' => [
        'name' => 'Nước cam',
        'price' => 10000,
        'old_price' => 15000,
        'sold' => '3.7k',
        'rating' => 4.0,
        'image' => '../SPdouong/Cam.png',
        'desc_list' => [
            'Tên sản phẩm: Nước cam',
            'Loại: Nước trái cây',
            'Hương vị: Chua ngọt tự nhiên',
            'Đặc điểm: Bổ sung vitamin C.'
        ]
    ],
    'traicay' => [
        'name' => 'Nước trái cây',
        'price' => 15000,
        'old_price' => 20000,
        'sold' => '2.9k',
        'rating' => 4.0,
        'image' => '../SPdouong/Traicay.png',
        'desc_list' => [
            'Tên sản phẩm: Nước trái cây',
            'Loại: Nước ép tổng hợp',
            'Hương vị: Ngọt thanh, dễ uống',
            'Đặc điểm: Thơm mùi trái cây tự nhiên.'
        ]
    ],
    'lavie' => [
        'name' => 'Nước suối Lavie',
        'price' => 5000,
        'old_price' => 7000,
        'sold' => '10.4k',
        'rating' => 4.3,
        'image' => '../SPdouong/Lavie.png',
        'desc_list' => [
            'Tên sản phẩm: Nước suối Lavie',
            'Loại: Nước uống đóng chai',
            'Hương vị: Tinh khiết',
            'Đặc điểm: Tiện lợi khi di chuyển.'
        ]
    ],
    'olong' => [
        'name' => 'Trà ô lông',
        'price' => 10000,
        'old_price' => 14000,
        'sold' => '4.3k',
        'rating' => 4.0,
        'image' => '../SPdouong/Olong.png',
        'desc_list' => [
            'Tên sản phẩm: Trà ô lông',
            'Loại: Trà đóng chai',
            'Hương vị: Trà thơm dịu',
            'Đặc điểm: Hậu vị thanh, ít ngọt.'
        ]
    ],
    'chaits' => [
        'name' => 'Trà sữa',
        'price' => 10000,
        'old_price' => 13000,
        'sold' => '3.5k',
        'rating' => 4.0,
        'image' => '../SPdouong/ChaiTS.png',
        'desc_list' => [
            'Tên sản phẩm: Trà sữa',
            'Loại: Trà sữa đóng chai',
            'Hương vị: Béo nhẹ, ngọt vừa',
            'Đặc điểm: Tiện lợi, dùng liền.'
        ]
    ],
    'boncha' => [
        'name' => 'BonChaa',
        'price' => 10000,
        'old_price' => 14000,
        'sold' => '2.1k',
        'rating' => 3.9,
        'image' => '../SPdouong/Boncha.png',
        'desc_list' => [
            'Tên sản phẩm: BonChaa',
            'Loại: Đồ uống đóng chai',
            'Hương vị: Ngọt dịu, thơm nhẹ',
            'Đặc điểm: Phù hợp uống lạnh.'
        ]
    ],
    'traxanh' => [
        'name' => 'Trà xanh',
        'price' => 5000,
        'old_price' => 8000,
        'sold' => '8.8k',
        'rating' => 4.1,
        'image' => '../SPdouong/Traxanh.png',
        'desc_list' => [
            'Tên sản phẩm: Trà xanh',
            'Loại: Trà đóng chai',
            'Hương vị: Thanh mát, dịu cổ',
            'Đặc điểm: Giá hợp lý, dễ mua.'
        ]
    ],
];

$products = [];

$productId = trim((string)($_GET['id'] ?? ''));
$sku = trim((string)($_GET['sku'] ?? 'trasua'));

$product = null;
if ($productId !== '') {
    $product = loadProductDetailById($productId);
}

if ($product === null) {
    if (!isset($products[$sku])) {
        $sku = 'trasua';
    }
    $product = $products[$sku] ?? null;
}

if ($product === null) {
    $fallbackCatalog = catalogFetchProducts();
    if (!empty($fallbackCatalog)) {
        $firstId = (string)($fallbackCatalog[0]['id'] ?? '');
        if ($firstId !== '') {
            $product = loadProductDetailById($firstId);
        }
    }
}

if ($product === null) {
    $product = [
        'id' => 'fallback',
        'name' => 'Sản phẩm đang cập nhật',
        'price' => 0,
        'old_price' => 0,
        'sold' => '0',
        'rating' => 4.0,
        'image' => '../TrangUser/ack.png',
        'desc_list' => ['Thông tin sản phẩm đang được cập nhật từ hệ thống.'],
    ];
}

$productDataId = htmlspecialchars((string)($product['id'] ?? ($productId !== '' ? $productId : $sku)), ENT_QUOTES, 'UTF-8');
$productDataName = htmlspecialchars((string)($product['name'] ?? 'Sản phẩm'), ENT_QUOTES, 'UTF-8');
$productDataImage = htmlspecialchars((string)($product['image'] ?? '../TrangUser/ack.png'), ENT_QUOTES, 'UTF-8');
$productDataPrice = (int)($product['price'] ?? 0);
$productDataOldPrice = (int)($product['old_price'] ?? 0);
$adminDescriptionText = '';
$adminFormUnit = 'Cai';
$adminFormVat = 10;
$adminFormDonGia = max(0, (int) round(((float)($product['price'] ?? 0)) / 1.1));

if (function_exists('catalogFetchDetailFromView')) {
    $detailViewData = catalogFetchDetailFromView((string) ($product['id'] ?? ''));
    if (is_array($detailViewData)) {
        $adminFormUnit = trim((string) ($detailViewData['unit'] ?? $adminFormUnit)) !== ''
            ? (string) ($detailViewData['unit'] ?? $adminFormUnit)
            : $adminFormUnit;
        $adminFormVat = (float) ($detailViewData['vat'] ?? $adminFormVat);
        $vatRate = max(0.0, $adminFormVat) / 100;
        $divisor = 1 + $vatRate;
        if ($divisor <= 0) {
            $divisor = 1;
        }
        $adminFormDonGia = (int) round((float) ($detailViewData['price_raw'] ?? $adminFormDonGia) / $divisor);
    }
}

if (function_exists('catalogFetchDetailedDescriptionText')) {
    $adminDescriptionText = trim((string) catalogFetchDetailedDescriptionText((string) ($product['id'] ?? '')));
}
if ($adminDescriptionText === '' && !empty($product['desc_list']) && is_array($product['desc_list'])) {
    $adminDescriptionText = implode("\n", array_map(static function ($line) {
        return trim((string) $line);
    }, $product['desc_list']));
}

$productListForLink = catalogFetchProducts();
$currentCategoryPageLink = 'trangchu.php';

if ($productId !== '') {
    foreach ($productListForLink as $item) {
        if (strcasecmp((string)($item['id'] ?? ''), $productId) !== 0) {
            continue;
        }

        $currentSlug = (string)($item['slug'] ?? '');
        foreach (catalogCategoryItems() as $cat) {
            if ((string)($cat['slug'] ?? '') === $currentSlug) {
                $currentCategoryPageLink = (string)($cat['link'] ?? 'trangchu.php');
                break;
            }
        }
        break;
    }
}

$commentError = '';
$commentSuccess = '';
$reviews = [];

$isLoggedInUser = isset($_SESSION['user_id']) && (int)($_SESSION['user_id']) > 0;
$currentUserName = trim((string)($_SESSION['user_name'] ?? ''));
$currentUserId = $isLoggedInUser ? (int)($_SESSION['user_id'] ?? 0) : null;
$currentUserRole = strtolower(trim((string)($_SESSION['user_role'] ?? 'guest')));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['comment_action'] ?? '') === 'submit_product_comment') {
    $commentProductId = trim((string)($_POST['product_id'] ?? ''));
    $commentContent = trim((string)($_POST['comment_content'] ?? ''));
    $parentCommentId = (int)($_POST['parent_comment_id'] ?? 0);
    $parentCommentId = $parentCommentId > 0 ? $parentCommentId : null;
    $commentRating = (int)($_POST['comment_rating'] ?? 5);
    $commentRating = max(1, min(5, $commentRating));

    $postedGuestName = trim((string)($_POST['guest_name'] ?? ''));
    $commentUserName = $currentUserName !== '' ? $currentUserName : ($postedGuestName !== '' ? $postedGuestName : 'Khách hàng');

    // Lưu tên khách vãng lai vào session để nhận thông báo sau
    if (!$isLoggedInUser && $postedGuestName !== '' && ($currentUserName === '' || $currentUserName === 'Khách hàng')) {
        $_SESSION['user_name'] = $postedGuestName;
    }

    $isReply = $parentCommentId !== null;
    if ($isReply) {
        $commentRating = 0;
    }

    $uploadedImagePath = '';
    $uploadResult = detailHandleCommentImageUpload($_FILES['comment_image'] ?? []);
    if (($uploadResult['error'] ?? '') !== '') {
        $commentError = (string)$uploadResult['error'];
    } else {
        $uploadedImagePath = (string)($uploadResult['path'] ?? '');
    }

    if ($commentError === '' && $commentProductId === '') {
        $commentError = 'Không xác định được sản phẩm để bình luận.';
    }

    if ($commentError === '' && $commentContent === '' && $uploadedImagePath === '') {
        $commentError = 'Vui lòng nhập nội dung hoặc chọn ảnh trước khi gửi.';
    } else {
        if ($commentError === '') {
            try {
            $pdo = detailPageGetPdo();
            detailEnsureCommentSchema($pdo);

            $insertStmt = $pdo->prepare(
                'INSERT INTO hanghoa_binhluan (MaHang, ParentId, TenNguoiDung, NoiDung, HinhAnh, SoSao, NguoiDungId, VaiTroNguoiDung)
                 VALUES (:mahang, :parentid, :ten, :noidung, :hinhanh, :sosao, :nguoidungid, :vaitro)'
            );

            $insertStmt->execute([
                ':mahang' => $commentProductId,
                ':parentid' => $parentCommentId,
                ':ten' => mb_substr($commentUserName, 0, 120, 'UTF-8'),
                ':noidung' => $commentContent,
                ':hinhanh' => $uploadedImagePath !== '' ? $uploadedImagePath : null,
                ':sosao' => $commentRating,
                ':nguoidungid' => $currentUserId,
                ':vaitro' => $currentUserRole,
            ]);

            // Notify admin and others of new comment/reply
            if ($parentCommentId === null || $parentCommentId <= 0) {
                // This is a new main comment - notify admin
                try {
                    $adminIds = $pdo->query("SELECT Id FROM users WHERE LOWER(role) = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
                    
                    $productNameStmt = $pdo->prepare('SELECT TenHang FROM hanghoa WHERE MaHang = :id LIMIT 1');
                    $productNameStmt->execute([':id' => $commentProductId]);
                    $productNameRow = $productNameStmt->fetch();
                    $productName = (string)($productNameRow['TenHang'] ?? $commentProductId);
                    
                    foreach ($adminIds as $adminId) {
                        $adminNotifyStmt = $pdo->prepare(
                            'INSERT INTO thongbao (NguoiDungId, LoaiThongBao, TieuDe, NoiDung, MaHang, TenHang, URL)
                             VALUES (:adminid, :loai, :tieude, :noidung, :mahang, :tenhang, :url)'
                        );
                        
                        $adminNotifyStmt->execute([
                            ':adminid' => $adminId,
                            ':loai' => 'customer_comment',
                            ':tieude' => 'Khách hàng vừa bình luận về sản phẩm',
                            ':noidung' => mb_substr($commentContent, 0, 500, 'UTF-8'),
                            ':mahang' => $commentProductId,
                            ':tenhang' => $productName,
                            ':url' => 'drink-detail.php?id=' . urlencode($commentProductId) . '#reviews',
                        ]);
                    }
                } catch (Throwable $e) {
                    // Admin notification not critical
                }
            }

            // Trigger notification if this is a reply
            if ($parentCommentId > 0) {
                try {
                    // Ensure notifications table exists
                    $pdo->exec(
                        "CREATE TABLE IF NOT EXISTS thongbao (
                            Id INT NOT NULL AUTO_INCREMENT,
                            NguoiDungId INT,
                            TenNguoiDung VARCHAR(120),
                            LoaiThongBao VARCHAR(30) NOT NULL,
                            TieuDe VARCHAR(255) NOT NULL,
                            NoiDung TEXT,
                            MaHang VARCHAR(10),
                            TenHang VARCHAR(255),
                            IdBinhLuan INT,
                            IdPhanHoi INT,
                            URL TEXT,
                            DaDoc TINYINT NOT NULL DEFAULT 0,
                            NgayTao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            NgayDoc TIMESTAMP NULL,
                            PRIMARY KEY (Id),
                            KEY idx_thongbao_user (NguoiDungId),
                            KEY idx_thongbao_tendung (TenNguoiDung),
                            KEY idx_thongbao_loai (LoaiThongBao),
                            KEY idx_thongbao_dadoc (DaDoc),
                            KEY idx_thongbao_ngaytao (NgayTao)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
                    );
                    
                    // Get product name
                    $productNameStmt = $pdo->prepare('SELECT TenHang FROM hanghoa WHERE MaHang = :id LIMIT 1');
                    $productNameStmt->execute([':id' => $commentProductId]);
                    $productNameRow = $productNameStmt->fetch();
                    $productName = (string)($productNameRow['TenHang'] ?? $commentProductId);
                    
                    // Notify parent comment author
                    $parentStmt = $pdo->prepare(
                        'SELECT TenNguoiDung, NguoiDungId FROM hanghoa_binhluan WHERE Id = :id LIMIT 1'
                    );
                    $parentStmt->execute([':id' => $parentCommentId]);
                    $parentRow = $parentStmt->fetch();
                    
                    if ($parentRow) {
                        $parentUserId = (int)($parentRow['NguoiDungId'] ?? 0);
                        $parentUserName = (string)($parentRow['TenNguoiDung'] ?? '');
                        
                        // Determine if this is a self-reply
                        $isSelfReply = false;
                        if ($parentUserId > 0 && $currentUserId > 0 && $parentUserId === $currentUserId) {
                            $isSelfReply = true;
                        } elseif ($parentUserId === 0 && $currentUserId === 0 && strtolower($parentUserName) === strtolower($commentUserName)) {
                            $isSelfReply = true;
                        }
                        
                        if (!$isSelfReply) {
                            // Send notification to parent comment author
                            if ($parentUserId > 0) {
                                // Parent has registered account
                                $notifyInsertStmt = $pdo->prepare(
                                    'INSERT INTO thongbao (NguoiDungId, LoaiThongBao, TieuDe, NoiDung, MaHang, TenHang, IdPhanHoi, URL)
                                     VALUES (:uid, :loai, :tieude, :noidung, :mahang, :tenhang, :idphanhoi, :url)'
                                );
                                
                                $notifyInsertStmt->execute([
                                    ':uid' => $parentUserId,
                                    ':loai' => 'reply',
                                    ':tieude' => 'Có phản hồi mới trên bình luận của bạn',
                                    ':noidung' => mb_substr($commentContent, 0, 500, 'UTF-8'),
                                    ':mahang' => $commentProductId,
                                    ':tenhang' => $productName,
                                    ':idphanhoi' => $parentCommentId,
                                    ':url' => 'drink-detail.php?id=' . urlencode($commentProductId) . '#reviews',
                                ]);
                            } else {
                                // Parent is guest user, notify by name
                                $notifyInsertStmt = $pdo->prepare(
                                    'INSERT INTO thongbao (TenNguoiDung, LoaiThongBao, TieuDe, NoiDung, MaHang, TenHang, IdPhanHoi, URL)
                                     VALUES (:uname, :loai, :tieude, :noidung, :mahang, :tenhang, :idphanhoi, :url)'
                                );
                                
                                $notifyInsertStmt->execute([
                                    ':uname' => $parentUserName,
                                    ':loai' => 'reply',
                                    ':tieude' => 'Có phản hồi mới trên bình luận của bạn',
                                    ':noidung' => mb_substr($commentContent, 0, 500, 'UTF-8'),
                                    ':mahang' => $commentProductId,
                                    ':tenhang' => $productName,
                                    ':idphanhoi' => $parentCommentId,
                                    ':url' => 'drink-detail.php?id=' . urlencode($commentProductId) . '#reviews',
                                ]);
                            }
                        }
                    }
                    
                    // Also notify admin of new customer reply/comment
                    $adminNotifyStmt = $pdo->prepare(
                        'INSERT INTO thongbao (NguoiDungId, LoaiThongBao, TieuDe, NoiDung, MaHang, TenHang, IdPhanHoi, URL)
                         VALUES (SELECT Id FROM users WHERE LOWER(role) = "admin" LIMIT 1, :loai, :tieude, :noidung, :mahang, :tenhang, :idphanhoi, :url)'
                    );
                    
                    try {
                        $adminIds = $pdo->query("SELECT Id FROM users WHERE LOWER(role) = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($adminIds as $adminId) {
                            $adminNotifyStmt = $pdo->prepare(
                                'INSERT INTO thongbao (NguoiDungId, LoaiThongBao, TieuDe, NoiDung, MaHang, TenHang, IdPhanHoi, URL)
                                 VALUES (:adminid, :loai, :tieude, :noidung, :mahang, :tenhang, :idphanhoi, :url)'
                            );
                            
                            $adminNotifyStmt->execute([
                                ':adminid' => $adminId,
                                ':loai' => 'customer_reply',
                                ':tieude' => 'Khách hàng vừa có phản hồi mới',
                                ':noidung' => mb_substr($commentContent, 0, 500, 'UTF-8'),
                                ':mahang' => $commentProductId,
                                ':tenhang' => $productName,
                                ':idphanhoi' => $parentCommentId,
                                ':url' => 'drink-detail.php?id=' . urlencode($commentProductId) . '#reviews',
                            ]);
                        }
                    } catch (Throwable $e) {
                        // Admin notification not critical
                    }
                } catch (Throwable $e) {
                    // Ignore notification errors
                }
            }
            
            header('Location: drink-detail.php?id=' . urlencode($commentProductId) . '&comment_saved=1#reviews');
            exit;
            } catch (Throwable $e) {
                $commentError = 'Không thể gửi bình luận lúc này: ' . $e->getMessage();
            }
        }
    }
}

if ((string)($_GET['comment_saved'] ?? '') === '1') {
    $commentSuccess = 'Đã gửi bình luận thành công. Khách hàng khác có thể thấy bình luận này.';
}

try {
    $pdo = detailPageGetPdo();
    detailEnsureCommentSchema($pdo);

    $commentStmt = $pdo->prepare(
        'SELECT Id, ParentId, TenNguoiDung, NoiDung, HinhAnh, SoSao, NgayTao, NguoiDungId, VaiTroNguoiDung
         FROM hanghoa_binhluan
         WHERE MaHang = :mahang
         ORDER BY NgayTao DESC, Id DESC'
    );

    $commentStmt->execute([
        ':mahang' => (string)($product['id'] ?? ''),
    ]);

    foreach ($commentStmt->fetchAll() as $row) {
        $reviews[] = [
            'id' => (int)($row['Id'] ?? 0),
            'parent_id' => isset($row['ParentId']) ? (int)$row['ParentId'] : null,
            'name' => (string)($row['TenNguoiDung'] ?? 'Khách hàng'),
            'rating' => max(0, min(5, (int)($row['SoSao'] ?? 0))),
            'content' => (string)($row['NoiDung'] ?? ''),
            'image' => trim((string)($row['HinhAnh'] ?? '')),
            'user_id' => (int)($row['NguoiDungId'] ?? 0),
            'user_role' => (string)($row['VaiTroNguoiDung'] ?? ''),
            'created_at' => (string)($row['NgayTao'] ?? ''),
        ];
    }
} catch (Throwable $e) {
    if ($commentError === '') {
        $commentError = 'Không thể tải danh sách bình luận: ' . $e->getMessage();
    }
}

$reviewCount = count($reviews);
$avgRating = (float)($product['rating'] ?? 4.0);
if ($reviewCount > 0) {
    $sumRating = 0;
    $ratingCount = 0;
    foreach ($reviews as $rv) {
        $rate = (int)($rv['rating'] ?? 0);
        if ($rate > 0) {
            $sumRating += $rate;
            $ratingCount++;
        }
    }
    if ($ratingCount > 0) {
        $avgRating = round($sumRating / $ratingCount, 1);
    }
}

$reviewMapById = [];
$rootReviews = [];
$replyMap = [];

foreach ($reviews as $rv) {
    $reviewMapById[(int)($rv['id'] ?? 0)] = $rv;
}

foreach ($reviews as $rv) {
    $parentId = (int)($rv['parent_id'] ?? 0);
    $currentId = (int)($rv['id'] ?? 0);
    if ($parentId > 0 && isset($reviewMapById[$parentId]) && $parentId !== $currentId) {
        if (!isset($replyMap[$parentId])) {
            $replyMap[$parentId] = [];
        }
        $replyMap[$parentId][] = $rv;
    } else {
        $rootReviews[] = $rv;
    }
}

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết - <?php echo htmlspecialchars($product['name']); ?> | ACK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
    body {
        background-color: #f5f5f5;
        font-family: 'Roboto', sans-serif;
        color: #333;
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
        padding: 10px 15px;
        font-weight: 500;
    }

    .main-nav .nav-link:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .delivery-notice {
        font-size: 0.9rem;
        font-style: italic;
    }

    .product-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        padding: 20px;
        margin-top: 20px;
    }

    .main-img {
        width: 100%;
        border-radius: 8px;
        object-fit: cover;
    }

    .product-title {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .price-section {
        margin: 15px 0;
    }

    .current-price {
        color: #ee4d2d;
        font-size: 30px;
        font-weight: bold;
    }

    .old-price {
        text-decoration: line-through;
        color: #888;
        font-size: 16px;
        margin-left: 10px;
    }

    .discount-tag {
        background: #ffebeb;
        color: #ee4d2d;
        padding: 2px 5px;
        font-size: 12px;
        font-weight: bold;
        border-radius: 2px;
    }

    .shipping-box {
        border: 1px solid #eee;
        padding: 10px;
        border-radius: 5px;
        font-size: 13px;
        color: #555;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-add-cart {
        background: #d6f0ff;
        color: #007bff;
        border: 1px solid #007bff;
        font-weight: 600;
        padding: 10px 20px;
    }

    .btn-buy-now {
        background: #007bff;
        color: white;
        font-weight: 600;
        padding: 10px 75px;
        border: none;
    }

    .review-header-box {
        background: #e8f2fc;
        border: 1px solid #f9ede5;
        padding: 30px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        gap: 40px;
        margin-bottom: 20px;
    }

    .score-big {
        font-size: 36px;
        color: #007bff;
        font-weight: bold;
    }

    .filter-btn {
        border: 1px solid #ddd;
        background: white;
        padding: 5px 15px;
        margin-right: 5px;
        border-radius: 2px;
        color: #555;
        font-size: 14px;
    }

    .filter-btn.active {
        border-color: #007bff;
        color: #007bff;
    }

    .user-review {
        border-bottom: 1px solid #eee;
        padding: 20px 0;
    }

    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 15px;
    }

    .review-content {
        color: #333;
        margin-top: 8px;
        font-size: 14px;
        line-height: 1.5;
    }

    .comment-image {
        max-width: 220px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        margin-top: 8px;
        display: block;
    }

    .reply-list {
        margin-top: 12px;
        margin-left: 52px;
        padding-left: 14px;
        border-left: 2px solid #e5e7eb;
    }

    .reply-item {
        padding-top: 10px;
        padding-bottom: 10px;
        border-bottom: 1px dashed #ececec;
    }

    .reply-form-wrap {
        display: none;
        margin-top: 12px;
        margin-left: 52px;
        padding: 10px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fafafa;
    }

    .reply-form-wrap.show {
        display: block;
    }

    .admin-edit-wrap {
        background: #fffbe8;
        border: 1px solid #ffe58f;
        border-radius: 10px;
        padding: 14px;
        margin-top: 16px;
    }

    .admin-edit-wrap .form-label {
        font-weight: 600;
        font-size: 0.85rem;
    }

    .comment-form-card {
        background: linear-gradient(180deg, #f8fbff 0%, #f1f6ff 100%);
        border: 1px solid #dbeafe;
        border-radius: 14px;
        padding: 16px;
    }

    .comment-form-card .form-label {
        font-size: 0.86rem;
        font-weight: 600;
        color: #334155;
        margin-bottom: 6px;
    }

    .comment-form-card .form-control,
    .comment-form-card .form-select {
        border-radius: 10px;
        border-color: #dbe3ef;
        min-height: 40px;
    }

    .comment-form-card .form-control:focus,
    .comment-form-card .form-select:focus {
        border-color: #60a5fa;
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.18);
    }

    .pretty-file-input {
        width: 100%;
        display: block;
        border: 1px solid #dbe3ef;
        border-radius: 10px;
        background: #fff;
        color: #0f172a;
        padding: 6px;
        font-size: 0.9rem;
    }

    .pretty-file-input::file-selector-button {
        border: none;
        border-radius: 8px;
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        color: #fff;
        padding: 8px 12px;
        margin-right: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: opacity .2s ease;
    }

    .pretty-file-input:hover::file-selector-button {
        opacity: .92;
    }

    .pretty-file-input::-webkit-file-upload-button {
        border: none;
        border-radius: 8px;
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        color: #fff;
        padding: 8px 12px;
        margin-right: 10px;
        font-weight: 600;
        cursor: pointer;
    }

    .file-input-hint {
        display: inline-block;
        margin-top: 6px;
        font-size: 0.8rem;
        color: #64748b;
    }

    .favorite-product-toggle {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        color: #6c757d;
    }

    .favorite-product-toggle .favorite-heart-icon {
        transition: color 0.25s ease, transform 0.2s ease;
    }

    .favorite-product-toggle.is-favorited .favorite-heart-icon {
        color: #ef4444 !important;
        transform: scale(1.08);
    }

    .favorite-heart-active {
        color: #ef4444 !important;
    }
    </style>
</head>

<body>
    <header class="sticky-top bg-white">
        <div class="top-bar">
            <div class="container d-flex align-items-center justify-content-between">
                <a href="<?php echo htmlspecialchars($currentCategoryPageLink); ?>"
                    class="d-flex align-items-center text-decoration-none me-3">
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
                    <li class="nav-item"><a class="nav-link"
                            href="<?php echo htmlspecialchars($currentCategoryPageLink); ?>">Sản phẩm</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Tin tức</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Tuyển dụng</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Chuyển nhượng</a></li>
                </ul>
                <div class="delivery-notice d-none d-md-block">
                    <i class="fas fa-bullhorn me-1"></i> Cơ hội cho nhà đầu tư
                </div>
            </div>
        </div>
    </header>
    <div class="container">
        <div class="product-container" data-product-id="<?php echo $productDataId; ?>"
            data-product-name="<?php echo $productDataName; ?>" data-product-price="<?php echo $productDataPrice; ?>"
            data-product-old-price="<?php echo $productDataOldPrice; ?>"
            data-product-img="<?php echo $productDataImage; ?>">
            <div class="row">
                <div class="col-md-5">
                    <img src="<?php echo htmlspecialchars($product['image']); ?>"
                        alt="<?php echo htmlspecialchars($product['name']); ?>" class="main-img img-fluid">
                </div>
                <div class="col-md-7">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                    <?php if ($isAdminUser): ?>
                    <div class="mb-3 d-flex gap-2 flex-wrap">
                        <a href="../TrangAdmin/admin-sanpham.php?edit=<?php echo urlencode((string)($product['id'] ?? '')); ?>"
                            class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-pen-to-square me-1"></i>Sửa ở trang Admin
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="mb-2">
                        <span class="text-warning">
                            <?php echo number_format($product['rating'], 1); ?> <i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="far fa-star"></i>
                        </span>
                        <span class="text-secondary mx-2">|</span>
                        <span><?php echo htmlspecialchars($product['sold']); ?> đánh giá</span>
                    </div>
                    <div class="price-section bg-light p-3 rounded">
                        <span class="current-price"><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</span>
                        <span class="old-price"><?php echo number_format($product['old_price'], 0, ',', '.'); ?>đ</span>
                        <span class="discount-tag">-40%</span>
                    </div>
                    <div class="shipping-box">
                        <i class="fas fa-truck text-success fs-5"></i>
                        <div>
                            <strong>Vận chuyển:</strong> Giao hàng trong 2 giờ. Freeship 0đ<br>
                            <small class="text-muted">Nhận hàng trong vòng 2 giờ (nội thành)</small>
                        </div>
                    </div>
                    <div class="mb-4">
                        <a href="#" class="text-decoration-none text-secondary favorite-product-toggle" role="button"
                            aria-pressed="false">
                            <i class="far fa-heart me-1 favorite-heart-icon"></i> Thêm
                            vào yêu thích</a>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-add-cart"><i class="fas fa-cart-plus me-2"></i>Thêm vào giỏ hàng</button>
                        <button class="btn btn-buy-now">Mua ngay</button>
                    </div>
                    <div class="mt-4">
                        <h5 class="fw-bold">Mô tả</h5>
                        <ul class="list-unstyled text-secondary" style="font-size: 14px;">
                            <?php foreach($product['desc_list'] as $desc): ?>
                            <li class="mb-1">• <?php echo htmlspecialchars($desc); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if ($isAdminUser): ?>
                    <div class="admin-edit-wrap">
                        <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-user-shield me-2"></i>Chỉnh thông tin
                            sản phẩm (Admin)</h6>

                        <?php if ($adminEditSuccess !== ''): ?>
                        <div class="alert alert-success py-2 mb-3"><?php echo htmlspecialchars($adminEditSuccess); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($adminEditError !== ''): ?>
                        <div class="alert alert-danger py-2 mb-3"><?php echo htmlspecialchars($adminEditError); ?></div>
                        <?php endif; ?>

                        <form method="post">
                            <input type="hidden" name="admin_action" value="update_detail_product">
                            <input type="hidden" name="product_id"
                                value="<?php echo htmlspecialchars((string)($product['id'] ?? '')); ?>">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Tên hàng</label>
                                    <input type="text" class="form-control" name="ten_hang"
                                        value="<?php echo htmlspecialchars((string)($product['name'] ?? '')); ?>"
                                        required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Đơn vị tính</label>
                                    <input type="text" class="form-control" name="dvt"
                                        value="<?php echo htmlspecialchars((string)$adminFormUnit); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">VAT (%)</label>
                                    <input type="number" min="0" max="100" step="0.01" class="form-control" name="vat"
                                        value="<?php echo htmlspecialchars((string)$adminFormVat); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Đơn giá (trước thuế)</label>
                                    <input type="number" min="0" step="1" class="form-control" name="don_gia"
                                        value="<?php echo max(0, (int)$adminFormDonGia); ?>" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Link ảnh</label>
                                    <input type="text" class="form-control" name="hinh_anh"
                                        value="<?php echo htmlspecialchars((string)($product['image'] ?? '')); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Mô tả chi tiết (mỗi dòng 1 ý)</label>
                                    <textarea class="form-control" name="mo_ta_chi_tiet"
                                        rows="5"><?php echo htmlspecialchars($adminDescriptionText); ?></textarea>
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-floppy-disk me-2"></i>Lưu thay đổi
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="product-container" id="reviews">
            <h4 class="fw-bold mb-3">Đánh giá (<?php echo $reviewCount; ?>)</h4>
            <div class="review-header-box d-flex flex-wrap">
                <div class="score-circle">
                    <div class="score-big"><?php echo number_format($avgRating, 1); ?></div>
                    <div class="text-warning"><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                            class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i></div>
                </div>
                <div class="filters">
                    <button class="filter-btn active">Tất cả (<?php echo $reviewCount; ?>)</button>
                    <button class="filter-btn">5 sao</button>
                    <button class="filter-btn">4 sao</button>
                    <button class="filter-btn">3 sao</button>
                    <button class="filter-btn">Có bình luận</button>
                </div>
            </div>

            <div class="mb-4 comment-form-card">
                <h6 class="fw-bold mb-3">Viết bình luận của bạn</h6>
                <?php if ($commentSuccess !== ''): ?>
                <div class="alert alert-success py-2 mb-3"><?php echo htmlspecialchars($commentSuccess); ?></div>
                <?php endif; ?>
                <?php if ($commentError !== ''): ?>
                <div class="alert alert-danger py-2 mb-3"><?php echo htmlspecialchars($commentError); ?></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="comment_action" value="submit_product_comment">
                    <input type="hidden" name="product_id"
                        value="<?php echo htmlspecialchars((string)($product['id'] ?? '')); ?>">
                    <div class="row g-2">
                        <?php if (!$isLoggedInUser): ?>
                        <div class="col-md-4">
                            <label class="form-label">Tên của bạn</label>
                            <input type="text" class="form-control" name="guest_name" maxlength="120"
                                placeholder="Nhập tên hiển thị">
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <label class="form-label">Đánh giá</label>
                            <select class="form-select" name="comment_rating">
                                <option value="5">5 sao</option>
                                <option value="4">4 sao</option>
                                <option value="3">3 sao</option>
                                <option value="2">2 sao</option>
                                <option value="1">1 sao</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nội dung bình luận</label>
                            <textarea class="form-control" name="comment_content" rows="3"
                                placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm..." required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ảnh đính kèm (tùy chọn)</label>
                            <input type="file" class="pretty-file-input" name="comment_image"
                                accept="image/jpeg,image/png,image/webp,image/gif">
                            <small class="file-input-hint">Hỗ trợ JPG/PNG/WEBP/GIF, tối đa 5MB.</small>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Gửi bình luận
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="review-list">
                <?php if ($reviewCount === 0): ?>
                <div class="text-muted py-3">Chưa có bình luận nào cho sản phẩm này. Hãy là người đầu tiên bình luận!
                </div>
                <?php endif; ?>
                <?php foreach($rootReviews as $rv): ?>
                <div class="user-review">
                    <div class="d-flex">
                        <img src="../TrangUser/ack.png" class="avatar" alt="User">
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($rv['name']); ?> <span class="text-success"
                                    style="font-size:12px;"><i class="fas fa-check-circle"></i> Đã mua hàng</span></div>
                            <?php if ((int)($rv['rating'] ?? 0) > 0): ?>
                            <div class="text-warning" style="font-size: 12px;">
                                <?php for($i = 0; $i < (int)$rv['rating']; $i++) echo '<i class="fas fa-star"></i>'; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (trim((string)($rv['content'] ?? '')) !== ''): ?>
                            <p class="review-content"><?php echo nl2br(htmlspecialchars($rv['content'])); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($rv['image'])): ?>
                            <img src="<?php echo htmlspecialchars((string)$rv['image']); ?>" class="comment-image"
                                alt="Ảnh bình luận">
                            <?php endif; ?>
                            <?php if (!empty($rv['created_at'])): ?>
                            <div class="text-muted" style="font-size:12px;">Gửi lúc:
                                <?php echo htmlspecialchars((string)$rv['created_at']); ?></div>
                            <?php endif; ?>
                            <button type="button"
                                class="btn btn-sm btn-link p-0 mt-1 text-decoration-none reply-toggle-btn"
                                data-reply-target="reply-form-<?php echo (int)$rv['id']; ?>">Phản hồi</button>
                        </div>
                    </div>

                    <div class="reply-form-wrap" id="reply-form-<?php echo (int)$rv['id']; ?>">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="comment_action" value="submit_product_comment">
                            <input type="hidden" name="product_id"
                                value="<?php echo htmlspecialchars((string)($product['id'] ?? '')); ?>">
                            <input type="hidden" name="parent_comment_id" value="<?php echo (int)$rv['id']; ?>">
                            <div class="row g-2">
                                <?php if (!$isLoggedInUser): ?>
                                <div class="col-md-5">
                                    <input type="text" class="form-control form-control-sm" name="guest_name"
                                        maxlength="120" placeholder="Tên của bạn">
                                </div>
                                <?php endif; ?>
                                <div class="col-12">
                                    <textarea class="form-control form-control-sm" name="comment_content" rows="2"
                                        placeholder="Viết phản hồi..."></textarea>
                                </div>
                                <div class="col-md-7">
                                    <input type="file" class="pretty-file-input" name="comment_image"
                                        accept="image/jpeg,image/png,image/webp,image/gif">
                                </div>
                                <div class="col-md-5 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-sm btn-primary">Gửi phản hồi</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <?php $childReplies = $replyMap[(int)$rv['id']] ?? []; ?>
                    <?php if (!empty($childReplies)): ?>
                    <div class="reply-list">
                        <?php foreach ($childReplies as $reply): ?>
                        <div class="reply-item">
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$reply['name']); ?></div>
                            <?php if (trim((string)($reply['content'] ?? '')) !== ''): ?>
                            <div class="review-content mb-1">
                                <?php echo nl2br(htmlspecialchars((string)$reply['content'])); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($reply['image'])): ?>
                            <img src="<?php echo htmlspecialchars((string)$reply['image']); ?>" class="comment-image"
                                alt="Ảnh phản hồi">
                            <?php endif; ?>
                            <?php if (!empty($reply['created_at'])): ?>
                            <div class="text-muted" style="font-size:12px;">Phản hồi lúc:
                                <?php echo htmlspecialchars((string)$reply['created_at']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelectorAll('.reply-toggle-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-reply-target');
            const formWrap = targetId ? document.getElementById(targetId) : null;
            if (!formWrap) return;

            formWrap.classList.toggle('show');
            if (formWrap.classList.contains('show')) {
                const textarea = formWrap.querySelector('textarea[name="comment_content"]');
                if (textarea) {
                    textarea.focus();
                }
            }
        });
    });

    (function() {
        const FAVORITES_KEY_PREFIX = 'ack_favorites_v1_';
        const USER_SESSION_ENDPOINT = 'user-session.php';
        const productWrap = document.querySelector('.product-container[data-product-id]');
        const favoriteBtn = document.querySelector('.favorite-product-toggle');
        const heartIcon = favoriteBtn ? favoriteBtn.querySelector('.favorite-heart-icon') : null;

        if (!productWrap || !favoriteBtn || !heartIcon) {
            return;
        }

        const productId = String(productWrap.getAttribute('data-product-id') || '').trim().toLowerCase();
        if (!productId) {
            return;
        }

        let cachedStorageKey = `${FAVORITES_KEY_PREFIX}guest`;
        let storageKeyResolved = false;

        const resolveCurrentStorageKey = async () => {
            if (storageKeyResolved) {
                return cachedStorageKey;
            }

            try {
                const res = await fetch(USER_SESSION_ENDPOINT, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: 'no-store'
                });
                const data = await res.json().catch(() => ({}));

                if (data && data.is_logged_in) {
                    const userId = Number.parseInt(String(data.user_id || 0), 10) || 0;
                    if (userId > 0) {
                        cachedStorageKey = `${FAVORITES_KEY_PREFIX}uid_${userId}`;
                    } else {
                        const email = String(data.email || '').trim().toLowerCase();
                        cachedStorageKey = email ? `${FAVORITES_KEY_PREFIX}mail_${email}` :
                            `${FAVORITES_KEY_PREFIX}guest`;
                    }
                }
            } catch (_) {
                cachedStorageKey = `${FAVORITES_KEY_PREFIX}guest`;
            }

            storageKeyResolved = true;
            return cachedStorageKey;
        };

        const collectFavoritedProductIds = async () => {
            const ids = new Set();

            try {
                const storageKey = await resolveCurrentStorageKey();
                const raw = localStorage.getItem(storageKey);
                const parsed = raw ? JSON.parse(raw) : [];
                if (Array.isArray(parsed)) {
                    parsed.forEach((item) => {
                        const id = String(item && item.id ? item.id : '').trim().toLowerCase();
                        if (id) {
                            ids.add(id);
                        }
                    });
                }
            } catch (_) {
                // ignore parse/storage errors
            }

            return ids;
        };

        const setFavoritedUI = (isFavorited) => {
            favoriteBtn.classList.toggle('is-favorited', isFavorited);
            favoriteBtn.setAttribute('aria-pressed', isFavorited ? 'true' : 'false');

            heartIcon.classList.toggle('far', !isFavorited);
            heartIcon.classList.toggle('fas', isFavorited);
            heartIcon.classList.toggle('fa-regular', !isFavorited);
            heartIcon.classList.toggle('fa-solid', isFavorited);
            heartIcon.classList.toggle('favorite-heart-active', isFavorited);
            if (isFavorited) {
                heartIcon.style.setProperty('color', '#ef4444', 'important');
            } else {
                heartIcon.style.removeProperty('color');
            }
        };

        const syncHeartStateFromStorage = async () => {
            const ids = await collectFavoritedProductIds();
            setFavoritedUI(ids.has(productId));
        };

        void syncHeartStateFromStorage();

        // web-events.js handles "add favorite" at capture phase and writes localStorage.
        // Re-sync UI right after that click flow completes.
        window.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) return;
            if (!target.closest('.favorite-product-toggle, .favorite-product-toggle *')) return;

            window.setTimeout(() => {
                void syncHeartStateFromStorage();
            }, 140);
        }, true);

        window.addEventListener('storage', (event) => {
            const key = String(event.key || '');
            if (!key.startsWith(FAVORITES_KEY_PREFIX)) return;
            void syncHeartStateFromStorage();
        });
    })();
    </script>
    <script src="web-events.js?v=20260414-3"></script>
</body>

</html>