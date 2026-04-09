<?php
require_once __DIR__ . '/catalog_data.php';

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

$reviews = [
    ['name' => 'Phạm Chi Hiếu', 'rating' => 5, 'content' => 'Sản phẩm ngon, đúng vị, giao hàng nhanh.'],
    ['name' => 'Nguyễn Thanh Kiệt', 'rating' => 5, 'content' => 'Đóng gói cẩn thận, đồ uống mát lạnh, giá hợp lý.'],
    ['name' => 'Lê Quốc Ân', 'rating' => 4, 'content' => 'Uống ổn, vị dễ chịu, sẽ ủng hộ tiếp.'],
    ['name' => 'Đinh Quốc Cường', 'rating' => 5, 'content' => 'Chất lượng tốt, phục vụ nhanh, rất hài lòng.']
];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết - <?php echo htmlspecialchars($product['name']); ?> | ACK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f5f5f5; font-family: 'Segoe UI', sans-serif; color: #333; }
        .top-header { background: white; padding: 10px 0; }
        .logo { font-size: 24px; font-weight: 800; color: #007bff; text-decoration: none; }
        .search-box { border-radius: 20px; background: #f0f2f5; border: none; padding-left: 20px; }
        .nav-blue { background-color: #0099ff; color: white; font-weight: 500; }
        .nav-blue a { color: white; text-decoration: none; padding: 10px 15px; display: inline-block; font-size: 14px; }
        .nav-blue a:hover { background: rgba(255,255,255,0.2); }
        .product-container { background: white; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); padding: 20px; margin-top: 20px; }
        .main-img { width: 100%; border-radius: 8px; object-fit: cover; }
        .product-title { font-size: 24px; font-weight: 700; margin-bottom: 10px; }
        .price-section { margin: 15px 0; }
        .current-price { color: #ee4d2d; font-size: 30px; font-weight: bold; }
        .old-price { text-decoration: line-through; color: #888; font-size: 16px; margin-left: 10px; }
        .discount-tag { background: #ffebeb; color: #ee4d2d; padding: 2px 5px; font-size: 12px; font-weight: bold; border-radius: 2px; }
        .shipping-box { border: 1px solid #eee; padding: 10px; border-radius: 5px; font-size: 13px; color: #555; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .btn-add-cart { background: #d6f0ff; color: #007bff; border: 1px solid #007bff; font-weight: 600; padding: 10px 20px; }
        .btn-buy-now { background: #007bff; color: white; font-weight: 600; padding: 10px 75px; border: none; }
        .review-header-box { background: #e8f2fc; border: 1px solid #f9ede5; padding: 30px; border-radius: 5px; display: flex; align-items: center; gap: 40px; margin-bottom: 20px; }
        .score-big { font-size: 36px; color: #007bff; font-weight: bold; }
        .filter-btn { border: 1px solid #ddd; background: white; padding: 5px 15px; margin-right: 5px; border-radius: 2px; color: #555; font-size: 14px; }
        .filter-btn.active { border-color: #007bff; color: #007bff; }
        .user-review { border-bottom: 1px solid #eee; padding: 20px 0; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; margin-right: 15px; }
        .review-content { color: #333; margin-top: 8px; font-size: 14px; line-height: 1.5; }
    </style>
</head>
<body>
<header class="top-header sticky-top shadow-sm">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-3 col-6">
                <a href="Trangdouong.php" class="logo"><span style="color:#003366">ACK</span></a>
            </div>
            <div class="col-md-6 d-none d-md-block">
                <div class="input-group">
                    <input type="text" class="form-control search-box" placeholder="Tìm kiếm sản phẩm...">
                    <button class="btn btn-light border-0" style="background:#f0f2f5"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <div class="col-md-3 col-6 text-end">
                <i class="fas fa-headset fs-5 me-3 text-secondary"></i>
                <i class="fas fa-globe fs-5 me-3 text-secondary"></i>
                <i class="fas fa-user-circle fs-4 text-warning"></i>
            </div>
        </div>
    </div>
</header>
<div class="nav-blue">
    <div class="container">
        <a href="Trangdouong.php">Sản phẩm</a>
        <a href="#">Tin tức</a>
        <a href="#">Tuyển dụng</a>
        <a href="#">Chuyển nhượng</a>
        <span class="float-end py-2 d-none d-md-inline"><i class="fas fa-bullhorn me-2"></i>Cơ hội cho nhà đầu tư</span>
    </div>
</div>
<div class="container">
    <div class="py-2">
        <a href="Trangdouong.php" class="text-decoration-none text-dark"><i class="fas fa-chevron-left"></i> Quay lại</a>
    </div>
    <div
        class="product-container"
        data-product-id="<?php echo $productDataId; ?>"
        data-product-name="<?php echo $productDataName; ?>"
        data-product-price="<?php echo $productDataPrice; ?>"
        data-product-old-price="<?php echo $productDataOldPrice; ?>"
        data-product-img="<?php echo $productDataImage; ?>"
    >
        <div class="row">
            <div class="col-md-5">
                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="main-img img-fluid">
            </div>
            <div class="col-md-7">
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                <div class="mb-2">
                    <span class="text-warning">
                        <?php echo number_format($product['rating'], 1); ?> <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i>
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
                    <a href="#" class="text-decoration-none text-secondary"><i class="far fa-heart me-1"></i> Thêm vào yêu thích</a>
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
            </div>
        </div>
    </div>
    <div class="product-container">
        <h4 class="fw-bold mb-3">Đánh giá (100)</h4>
        <div class="review-header-box d-flex flex-wrap">
            <div class="score-circle">
                <div class="score-big"><?php echo number_format($product['rating'], 1); ?></div>
                <div class="text-warning"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i></div>
            </div>
            <div class="filters">
                <button class="filter-btn active">Tất cả (100)</button>
                <button class="filter-btn">5 sao (80)</button>
                <button class="filter-btn">4 sao (10)</button>
                <button class="filter-btn">3 sao (10)</button>
                <button class="filter-btn">Có bình luận (50)</button>
            </div>
        </div>
        <div class="review-list">
            <?php foreach($reviews as $rv): ?>
            <div class="user-review">
                <div class="d-flex">
                    <img src="https://via.placeholder.com/40" class="avatar" alt="User">
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($rv['name']); ?> <span class="text-success" style="font-size:12px;"><i class="fas fa-check-circle"></i> Đã mua hàng</span></div>
                        <div class="text-warning" style="font-size: 12px;">
                            <?php for($i = 0; $i < $rv['rating']; $i++) echo '<i class="fas fa-star"></i>'; ?>
                        </div>
                        <p class="review-content"><?php echo htmlspecialchars($rv['content']); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="web-events.js"></script>
</body>
</html>
