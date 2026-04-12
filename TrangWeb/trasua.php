<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết - Trà Sữa Truyền Thống | ACK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
    /* --- GLOBAL STYLES --- */
    body {
        background-color: #f5f5f5;
        font-family: 'Segoe UI', sans-serif;
        color: #333;
    }

    /* HEADER */
    .top-header {
        background: white;
        padding: 10px 0;
    }

    .logo {
        font-size: 24px;
        font-weight: 800;
        color: #007bff;
        text-decoration: none;
    }

    .search-box {
        border-radius: 20px;
        background: #f0f2f5;
        border: none;
        padding-left: 20px;
    }

    .nav-blue {
        background-color: #0099ff;
        color: white;
        font-weight: 500;
    }

    .nav-blue a {
        color: white;
        text-decoration: none;
        padding: 10px 15px;
        display: inline-block;
        font-size: 14px;
    }

    .nav-blue a:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    /* PRODUCT MAIN INFO */
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

    .rating-stars {
        color: #ffc107;
        font-size: 14px;
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

    .btn-add-cart:hover {
        background: #cceeff;
    }

    .btn-buy-now {
        background: #007bff;
        color: white;
        font-weight: 600;
        padding: 10px 75px;
        border: none;
    }

    .btn-buy-now:hover {
        background: #0056b3;
        color: white;
    }

    /* REVIEWS SECTION */
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

    .score-circle {
        text-align: center;
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

    .verified-tag {
        color: #00bfa5;
        font-size: 12px;
        margin-left: 5px;
    }

    .review-content {
        color: #333;
        margin-top: 8px;
        font-size: 14px;
        line-height: 1.5;
    }

    /* FOOTER & EXTRAS (Reused styles for consistency) */
    .feature-icon-circle {
        width: 50px;
        height: 50px;
        background: #e7f1ff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        color: #007bff;
        font-size: 20px;
    }

    .newsletter-section {
        background: linear-gradient(135deg, #0099ff, #00c6ff);
        color: white;
        padding: 40px 0;
        margin-top: 40px;
    }

    footer {
        background: #222;
        color: #aaa;
        padding: 40px 0;
        font-size: 14px;
    }
    </style>
</head>

<body>

    <?php
        require_once __DIR__ . '/catalog_data.php';

        $product = null;
        $productId = trim((string)($_GET['id'] ?? ''));

        if ($productId !== '') {
            $product = loadProductDetailById($productId);
        }

        if ($product === null) {
            $catalog = catalogFetchProducts();

            $candidate = null;
            foreach ($catalog as $item) {
                if (($item['slug'] ?? '') === 'douong') {
                    $candidate = $item;
                    break;
                }
            }

            if ($candidate === null && count($catalog) > 0) {
                $candidate = $catalog[0];
            }

            if (is_array($candidate) && !empty($candidate['id'])) {
                $product = loadProductDetailById((string)$candidate['id']);
            }
        }

        if ($product === null) {
            $product = [
                'id' => 'fallback',
                'name' => 'Trà sữa truyền thống',
                'price' => 25000,
                'old_price' => 40000,
                'sold' => '10.0k',
                'rating' => 4.0,
                'image' => 'trasua.png',
                'desc_list' => [
                    'Tên sản phẩm: Trà sữa truyền thống',
                    'Loại: Đồ uống pha sẵn dạng ly',
                    'Hương vị: Trà sữa truyền thống đậm vị',
                    'Đặc điểm: Vị ngọt béo hài hòa, thơm mùi trà sữa béo nhẹ, dễ uống. Có thể thêm trân châu hoặc uống nguyên vị.'
                ]
            ];
        }

        $reviews = [
            ['name' => 'Phạm Chi Hiếu', 'rating' => 5, 'content' => 'Ly trà sữa truyền thống có màu nâu nhạt đẹp mắt, mùi trà thơm dịu và sữa béo nhẹ. Uống vào cảm nhận ngay vị ngọt vừa.'],
            ['name' => 'Nguyễn Thanh Kiệt', 'rating' => 5, 'content' => 'Ly trà sữa truyền thống có lớp sữa mịn và màu trà hấp dẫn. Khi uống cảm nhận rõ vị trà thơm cùng độ béo nhẹ của sữa. Vị ngọt không gắt.'],
            ['name' => 'Lê Quốc Ân', 'rating' => 4, 'content' => 'Ly trà sữa truyền thống đang phủ khá đậm, mùi trà và vị sữa hòa quyện. Nhấp môi người ta cảm nhận được vị trà thơm.'],
            ['name' => 'Đinh Quốc Cường', 'rating' => 5, 'content' => 'Ly trà sữa truyền thống có trân châu mềm dai, nước trà sữa thơm và mát. Uống vào cảm nhận ngay vị ngọt dịu và mùi trà đặc trưng.']
        ];
    ?>

    <header class="top-header sticky-top shadow-sm">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3 col-6">
                    <a href="#" class="logo"><span style="color:#003366">ACK</span> <img
                            src="https://via.placeholder.com/20/FF0000/FFFFFF?text=." style="width:10px; height:10px;"
                            alt=""></a>
                </div>
                <div class="col-md-6 d-none d-md-block">
                    <div class="input-group">
                        <input type="text" class="form-control search-box" placeholder="Tìm kiếm sản phẩm...">
                        <button class="btn btn-light border-0" style="background:#f0f2f5"><i
                                class="fas fa-search"></i></button>
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
            <a href="#">Sản phẩm</a>
            <a href="#">Tin tức</a>
            <a href="#">Tuyển dụng</a>
            <a href="#">Chuyển nhượng</a>
            <span class="float-end py-2 d-none d-md-inline"><i class="fas fa-bullhorn me-2"></i>Cơ hội cho nhà đầu
                tư</span>
        </div>
    </div>

    <div class="container">
        <div class="py-2">
            <a href="Trangdouong.php" class="text-decoration-none text-dark"><i class="fas fa-chevron-left"></i> Quay
                lại</a>
        </div>

        <div class="product-container"
            data-product-id="<?php echo htmlspecialchars((string)($product['id'] ?? 'trasua'), ENT_QUOTES, 'UTF-8'); ?>"
            data-product-name="<?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8'); ?>"
            data-product-price="<?php echo (int)($product['price'] ?? 0); ?>"
            data-product-old-price="<?php echo (int)($product['old_price'] ?? 0); ?>"
            data-product-img="<?php echo htmlspecialchars((string)($product['image'] ?? 'trasua.png'), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="row">
                <div class="col-md-5">
                    <img src="<?php echo htmlspecialchars((string)($product['image'] ?? 'trasua.png')); ?>"
                        alt="<?php echo htmlspecialchars($product['name']); ?>" class="main-img img-fluid">
                </div>

                <div class="col-md-7">
                    <h1 class="product-title"><?php echo $product['name']; ?></h1>

                    <div class="mb-2">
                        <span class="text-warning">
                            4.0 <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="far fa-star"></i>
                        </span>
                        <span class="text-secondary mx-2">|</span>
                        <span><?php echo $product['sold']; ?> đánh giá</span>
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
                        <a href="#" class="text-decoration-none text-secondary"><i class="far fa-heart me-1"></i> Thêm
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
                            <li class="mb-1">• <?php echo $desc; ?></li>
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
                    <div class="score-big"><?php echo $product['rating']; ?></div>
                    <div class="text-warning">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                            class="fas fa-star"></i><i class="far fa-star"></i>
                    </div>
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
                            <div class="fw-bold"><?php echo $rv['name']; ?> <span class="verified-tag"><i
                                        class="fas fa-check-circle"></i> Đã mua hàng</span></div>
                            <div class="text-warning" style="font-size: 12px;">
                                <?php for($i=0; $i<$rv['rating']; $i++) echo '<i class="fas fa-star"></i>'; ?>
                            </div>
                            <p class="review-content"><?php echo $rv['content']; ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <nav aria-label="Page navigation" class="mt-4 d-flex justify-content-end">
                    <ul class="pagination pagination-sm">
                        <li class="page-item"><a class="page-link text-dark" href="#"><i
                                    class="fas fa-chevron-left"></i></a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link text-dark" href="#">2</a></li>
                        <li class="page-item"><a class="page-link text-dark" href="#">3</a></li>
                        <li class="page-item"><a class="page-link text-dark" href="#">...</a></li>
                        <li class="page-item"><a class="page-link text-dark" href="#"><i
                                    class="fas fa-chevron-right"></i></a></li>
                    </ul>
                </nav>
            </div>
        </div>

        <div class="row text-center py-5">
            <div class="col-6 col-md-3">
                <div class="feature-icon-circle"><i class="fas fa-truck"></i></div>
                <h6 class="fw-bold">Giao hàng nhanh</h6>
                <small class="text-muted">Đóng gói quy chuẩn</small>
            </div>
            <div class="col-6 col-md-3">
                <div class="feature-icon-circle"><i class="fas fa-shield-alt"></i></div>
                <h6 class="fw-bold">An toàn thanh toán</h6>
                <small class="text-muted">Bảo mật tuyệt đối</small>
            </div>
            <div class="col-6 col-md-3">
                <div class="feature-icon-circle"><i class="fas fa-headset"></i></div>
                <h6 class="fw-bold">Giao hàng nhanh</h6>
                <small class="text-muted">Hỗ trợ 24/7</small>
            </div>
            <div class="col-6 col-md-3">
                <div class="feature-icon-circle"><i class="fas fa-sync"></i></div>
                <h6 class="fw-bold">Đổi trả nhanh</h6>
                <small class="text-muted">Thủ tục đơn giản</small>
            </div>
        </div>

        <h3 class="text-center fw-bold mt-2 mb-4">Cảm nhận của khách hàng</h3>
        <p class="text-center text-muted mb-5">Hàng ngàn khách hàng hài lòng với chúng tôi</p>

        <div class="row mb-5">
            <div class="col-md-4 mb-3">
                <div class="card p-3 border-0 shadow-sm h-100">
                    <div class="text-warning mb-2">★★★★★</div>
                    <p class="small text-muted">"Sản phẩm chất lượng, giao hàng nhanh. Tôi rất hài lòng về dịch vụ."</p>
                    <div class="d-flex align-items-center mt-auto">
                        <img src="https://via.placeholder.com/30" class="rounded-circle me-2">
                        <span class="fw-bold small">Trần Quốc Cường</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card p-3 border-0 shadow-sm h-100">
                    <div class="text-warning mb-2">★★★★★</div>
                    <p class="small text-muted">"Giá cạnh tranh, sản phẩm đa dạng. Tôi sẽ mua lại."</p>
                    <div class="d-flex align-items-center mt-auto">
                        <img src="https://via.placeholder.com/30" class="rounded-circle me-2">
                        <span class="fw-bold small">Nguyễn Thanh Kết</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card p-3 border-0 shadow-sm h-100">
                    <div class="text-warning mb-2">★★★★☆</div>
                    <p class="small text-muted">"Tư vấn và cách dùng rất hữu ích. Hy vọng có thêm nhiều ưu đãi."</p>
                    <div class="d-flex align-items-center mt-auto">
                        <img src="https://via.placeholder.com/30" class="rounded-circle me-2">
                        <span class="fw-bold small">Lê Quân An</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="newsletter-section text-center">
        <i class="far fa-envelope fa-3x mb-3"></i>
        <h3 class="fw-bold">Đăng ký nhận khuyến mãi</h3>
        <p>Nhận thông tin về các sản phẩm mới và ưu đãi đặc biệt</p>
        <div class="row justify-content-center mt-4">
            <div class="col-md-5">
                <div class="input-group">
                    <input type="email" class="form-control rounded-start-pill border-0 ps-4"
                        placeholder="Nhập email của bạn...">
                    <button class="btn btn-light rounded-end-pill fw-bold text-primary px-4">Đăng ký</button>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <h5 class="text-white fw-bold mb-3">ACK</h5>
                    <p>Mua sắm thông minh, tiết kiệm tối đa.</p>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="text-white fw-bold mb-3">ACK</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-decoration-none text-secondary">Giới thiệu</a></li>
                        <li><a href="#" class="text-decoration-none text-secondary">Tuyển dụng</a></li>
                        <li><a href="#" class="text-decoration-none text-secondary">Tin tức</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="text-white fw-bold mb-3">ACK</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-decoration-none text-secondary">Liên hệ</a></li>
                        <li><a href="#" class="text-decoration-none text-secondary">FAQ</a></li>
                        <li><a href="#" class="text-decoration-none text-secondary">Chính sách</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="text-white fw-bold mb-3">ACK</h5>
                    <p>Tp. Cao Lãnh</p>
                    <p>@ duong@gmail.com</p>
                </div>
            </div>
            <hr class="border-secondary">
            <div class="text-center small">© 2024 ACK. Tất cả quyền được bảo lưu.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="web-events.js?v=20260412-3"></script>
</body>

</html>