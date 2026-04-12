<?php
require_once __DIR__ . '/catalog_data.php';

// Dữ liệu danh mục hiển thị theo layout trang chủ
$categories = [
    ['name' => 'Thức ăn', 'icon' => 'fa-hamburger', 'desc' => 'Ăn uống đa dạng'],
    ['name' => 'Sức khỏe', 'icon' => 'fa-heartbeat', 'desc' => 'Chăm sóc toàn diện'],
    ['name' => 'Dụng cụ', 'icon' => 'fa-tools', 'desc' => 'Đồ dùng đa năng'],
    ['name' => 'Quà tặng', 'icon' => 'fa-gift', 'desc' => 'Ý tưởng quà tặng'],
];

$products = loadFeaturedProductsFromDb(8);

$feedbacks = [
    ['name' => 'Đinh Quốc Cường', 'comment' => 'Sản phẩm chất lượng, giao hàng nhanh. Rất hài lòng.', 'avatar' => '../TrangUser/messi.png'],
    ['name' => 'Nguyễn Thanh Kiệt', 'comment' => 'Dịch vụ tốt, nhân viên thân thiện. Sẽ quay lại.', 'avatar' => '../TrangUser/kiet.png'],
    ['name' => 'Lê Quốc An', 'comment' => 'Giá cả hợp lý so với thị trường.', 'avatar' => '../TrangUser/anlecho.png'],
];
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Store - Trang chủ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --primary-blue: #0099ff;
        --light-blue: #e6f4ff;
        --dark-blue: #0d6efd;
        --footer-bg: #1a1a2e;
    }

    body {
        font-family: 'Roboto', sans-serif;
        background-color: #f8f9fa;
    }

    /* Navbar */
    .navbar-brand img {
        height: 40px;
    }

    .nav-link {
        font-weight: 500;
        color: #333;
    }

    .nav-link:hover {
        color: var(--primary-blue);
    }

    /* Hero Section */
    .hero-section {
        background: linear-gradient(135deg, #007bff 0%, #00c6ff 100%);
        color: white;
        padding: 80px 0;
        border-bottom-left-radius: 50px;
        border-bottom-right-radius: 50px;
        position: relative;
        overflow: hidden;
    }

    .hero-img-container {
        background: white;
        padding: 20px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .btn-hero-white {
        background: white;
        color: var(--primary-blue);
        font-weight: bold;
        border-radius: 30px;
        padding: 10px 30px;
    }

    .btn-hero-outline {
        border: 2px solid white;
        color: white;
        font-weight: bold;
        border-radius: 30px;
        padding: 10px 30px;
    }

    /* Categories */
    .category-card {
        background-color: var(--dark-blue);
        color: white;
        border-radius: 15px;
        padding: 20px;
        text-align: left;
        transition: transform 0.3s;
        height: 100%;
    }

    .category-card:hover {
        transform: translateY(-5px);
    }

    .category-icon {
        font-size: 2rem;
        margin-bottom: 10px;
    }

    /* Products */
    .product-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        transition: 0.3s;
    }

    .product-card:hover {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .badge-discount {
        position: absolute;
        top: 10px;
        right: 10px;
        background: var(--dark-blue);
        color: white;
        padding: 5px 10px;
        border-radius: 10px;
    }

    .product-img {
        border-radius: 15px 15px 0 0;
        object-fit: cover;
        height: 180px;
        width: 100%;
    }

    .stars {
        color: #ffc107;
        font-size: 0.8rem;
    }

    .price-new {
        color: var(--dark-blue);
        font-weight: bold;
        font-size: 1.1rem;
    }

    .price-old {
        text-decoration: line-through;
        color: #999;
        font-size: 0.9rem;
    }

    .btn-add-cart {
        background: #4a6cf7;
        color: white;
        border-radius: 20px;
        width: 100%;
        font-weight: 500;
    }

    /* Banners */
    .promo-banner {
        border-radius: 20px;
        padding: 30px;
        position: relative;
        overflow: hidden;
        color: #333;
    }

    .bg-purple-light {
        background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%);
    }

    .bg-blue-light {
        background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);
    }

    /* Features */
    .feature-box {
        text-align: center;
        padding: 20px;
    }

    .feature-icon-circle {
        background: #e9ecef;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        color: var(--primary-blue);
        font-size: 1.5rem;
    }

    /* Testimonials */
    .testimonial-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        height: 100%;
    }

    /* Feedback Section */
    .feedback-section {
        background-color: #f9fafb;
        padding: 60px 0;
    }

    .feedback-card {
        background: #fff;
        border: 1px solid #eaecf0;
        border-radius: 12px;
        padding: 24px;
        height: 100%;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s;
    }

    .feedback-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .star-rating {
        color: #fdb022;
        margin-bottom: 15px;
        font-size: 14px;
    }

    .feedback-text {
        font-size: 0.95rem;
        color: #101828;
        font-weight: 500;
        line-height: 1.5;
        margin-bottom: 20px;
        font-style: italic;
    }

    .user-info img {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 12px;
    }

    .user-name {
        font-weight: 700;
        color: #101828;
        font-size: 0.9rem;
        margin-bottom: 2px;
    }

    .user-time {
        font-size: 0.8rem;
        color: #667085;
    }

    /* Newsletter */
    .newsletter-section {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        padding: 60px 0;
        width: 100%;
        margin-left: calc(-50vw + 50%);
        margin-right: calc(-50vw + 50%);
        padding-left: calc(50vw - 50%);
        padding-right: calc(50vw - 50%);
    }

    .newsletter-section .container {
        max-width: 100%;
        padding-left: 15px !important;
        padding-right: 15px !important;
    }

    .newsletter-input {
        border-radius: 30px 0 0 30px;
        border: none;
        padding: 12px 20px;
    }

    .newsletter-btn {
        border-radius: 0 30px 30px 0;
        background: white;
        color: var(--primary-blue);
        font-weight: bold;
        border: none;
        padding: 0 25px;
    }

    /* Footer */
    footer {
        background-color: #1d2939;
        color: #eaecf0;
        padding-top: 60px;
        font-size: 0.9rem;
        margin-top: 0 !important;
        width: 100%;
        margin-left: calc(-50vw + 50%);
        margin-right: calc(-50vw + 50%);
        padding-left: calc(50vw - 50%);
        padding-right: calc(50vw - 50%);
    }

    footer .container {
        max-width: 100%;
        padding-left: 15px !important;
        padding-right: 15px !important;
    }

    footer h5 {
        color: #fff;
        font-weight: 700;
        margin-bottom: 20px;
        text-transform: uppercase;
        font-size: 1rem;
    }

    footer a {
        color: #d0d5dd;
        text-decoration: none;
        display: block;
        margin-bottom: 12px;
        transition: color 0.2s;
    }

    footer a:hover {
        color: #fff;
        padding-left: 5px;
    }

    footer .social-icons a {
        display: inline-block;
        width: 36px;
        height: 36px;
        line-height: 36px;
        background: rgba(255, 255, 255, 0.1);
        text-align: center;
        border-radius: 50%;
        margin-right: 10px;
        color: #fff;
    }

    footer .social-icons a:hover {
        background: var(--primary-blue);
    }

    .copyright-border {
        border-top: 1px solid #344054;
        margin-top: 40px;
        padding: 20px 0;
        color: #98a2b3;
    }

    html {
        scroll-behavior: smooth;
    }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="#">
                <img src="../TrangUser/ack.png" alt="Logo"> </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link active" href="../Login/Dangnhap.php">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Sản phẩm</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Giới thiệu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Liên hệ</a></li>
                </ul>
            </div>
            <div class="d-flex gap-3">
                <a href="#" class="text-dark"><i class="fas fa-user fa-lg"></i></a>
                <a href="giohang.php" class="text-dark position-relative">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                    <span data-cart-count
                        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">0</span>
                </a>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 mb-4 mb-md-0">
                    <h1 class="display-4 fw-bold mb-3">Bắt đầu <br> trải nghiệm</h1>
                    <p class="mb-4 opacity-75">Cung cấp đa dạng hàng hóa chất lượng, nguồn gốc rõ ràng, đáp ứng mọi nhu
                        cầu mua sắm của bạn.</p>
                    <div class="d-flex gap-3">
                        <a href="../Login/Dangnhap.php" class="btn btn-hero-white">Mua ngay <i
                                class="fas fa-arrow-right ms-2"></i></a>
                        <a href="../Login/Dangnhap.php" class="btn btn-hero-outline">Tìm hiểu thêm</a>
                    </div>
                </div>
                <div class="col-md-6 text-center">
                    <div class="hero-img-container d-inline-block">
                        <img src="../TrangUser/chtl.png" class="img-fluid rounded" alt="Hero Image">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="text-center mb-5">
            <h2 class="text-primary fw-bold">Danh mục sản phẩm</h2>
            <p class="text-muted">Chọn từ các danh mục yêu thích của bạn</p>
        </div>
        <div class="row g-4">
            <?php foreach($categories as $cat): ?>
            <div class="col-md-3 col-6">
                <div class="category-card p-4">
                    <i class="fas <?php echo $cat['icon']; ?> category-icon"></i>
                    <h5 class="fw-bold"><?php echo $cat['name']; ?></h5>
                    <small class="opacity-75"><?php echo $cat['desc']; ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="product-section" class="bg-light py-5">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="text-primary fw-bold mb-0">Danh mục sản phẩm</h3>
                <a href="#" class="btn btn-light rounded-pill">Xem tất cả</a>
            </div>
            <div class="row g-4">
                <?php foreach($products as $prod): ?>
                <div class="col-md-3 col-sm-6">
                    <div class="card product-card h-100"
                        data-product-id="<?php echo htmlspecialchars((string)($prod['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-product-name="<?php echo htmlspecialchars((string)$prod['name'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-product-price="<?php echo htmlspecialchars((string)($prod['price_raw'] ?? $prod['price']), ENT_QUOTES, 'UTF-8'); ?>"
                        data-product-old-price="<?php echo htmlspecialchars((string)$prod['old_price'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-product-img="<?php echo htmlspecialchars((string)$prod['image'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="badge-discount"><?php echo $prod['discount']; ?></span>
                        <a href="<?php echo htmlspecialchars((string)($prod['link'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>"
                            class="text-decoration-none">
                            <img src="<?php echo $prod['image']; ?>" class="card-img-top product-img"
                                alt="<?php echo $prod['name']; ?>">
                        </a>
                        <div class="card-body">
                            <h6 class="card-title product-title fw-bold"><?php echo $prod['name']; ?></h6>
                            <div class="stars mb-2">
                                <?php for($i=0; $i<$prod['rating']; $i++) echo '<i class="fas fa-star"></i>'; ?>
                                <span class="text-muted small">(<?php echo rand(10,50); ?>)</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="price-new"><?php echo $prod['price']; ?></span>
                                <span class="price-old"><?php echo $prod['old_price']; ?></span>
                            </div>
                            <button class="btn btn-add-cart btn-sm"><i class="fas fa-shopping-cart me-1"></i> Thêm vào
                                giỏ</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="promo-banner bg-purple-light h-100 d-flex flex-column justify-content-center">
                    <span class="badge bg-primary w-25 mb-2">Flash Sale</span>
                    <h3 class="fw-bold">Giảm giá lên đến <span class="text-primary">30%</span></h3>
                    <p>Cơ hội mua sắm giá siêu hời ngay hôm nay</p>
                    <a href="#" class="btn btn-primary btn-sm w-25 rounded-pill">Mua ngay</a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="promo-banner bg-blue-light h-100 d-flex flex-column justify-content-center">
                    <span class="badge bg-primary w-25 mb-2">Mới</span>
                    <h3 class="fw-bold">Sản phẩm <span class="text-primary">mới nhất</span></h3>
                    <p>Khám phá bộ sưu tập mới về cực chất</p>
                    <a href="#" class="btn btn-light btn-sm w-25 rounded-pill">Xem ngay</a>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-6 col-md-3 feature-box">
                    <div class="feature-icon-circle"><i class="fas fa-truck"></i></div>
                    <h6 class="fw-bold">Giao hàng nhanh</h6>
                    <small class="text-muted">Miễn phí giao hàng cho đơn từ 500k</small>
                </div>
                <div class="col-6 col-md-3 feature-box">
                    <div class="feature-icon-circle"><i class="fas fa-shield-alt"></i></div>
                    <h6 class="fw-bold">Bảo mật thanh toán</h6>
                    <small class="text-muted">Đảm bảo an toàn mọi giao dịch</small>
                </div>
                <div class="col-6 col-md-3 feature-box">
                    <div class="feature-icon-circle"><i class="fas fa-headset"></i></div>
                    <h6 class="fw-bold">Giao hàng nhanh</h6> <small class="text-muted">Hỗ trợ khách hàng 24/7</small>
                </div>
                <div class="col-6 col-md-3 feature-box">
                    <div class="feature-icon-circle"><i class="fas fa-sync"></i></div>
                    <h6 class="fw-bold">Đổi trả nhanh</h6>
                    <small class="text-muted">Đổi trả trong ngày nếu lỗi</small>
                </div>
            </div>
        </div>
    </section>

    <section class="feedback-section">
        <div class="container">
            <div class="text-center mb-5">
                <h3 class="fw-bold">Cảm nhận của khách hàng</h3>
                <p class="text-muted">Hàng nghàn khách hàng hài lòng về chung tôi</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feedback-card">
                        <div class="star-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="feedback-text">"Sản phẩm rất tươi ngon, giao hàng đúng hẹn. Tết này sẽ ủng hộ tiếp!
                            Đóng gói rất c᪭n thận."</p>
                        <div class="user-info d-flex align-items-center">
                            <img src="../TrangUser/messi.png" alt="User">
                            <div>
                                <div class="user-name">Dinha Quốc Cường</div>
                                <div class="user-time">15 phút trước</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feedback-card">
                        <div class="star-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="feedback-text">"Giá cả cạnh tranh, nhân viên tư vấn nhiệt tình. Sẽ giới thiệu cho bạn
                            bè mua sắm tại đây."</p>
                        <div class="user-info d-flex align-items-center">
                            <img src="../TrangUser/kiet.png" alt="User">
                            <div>
                                <div class="user-name">Nguyễn Thanh Kiệt</div>
                                <div class="user-time">1 giờ trước</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feedback-card">
                        <div class="star-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                        </div>
                        <p class="feedback-text">"Dịch vụ tuyệt vời. Mua hàng online mà được freeship lại còn được quà
                            tặng kèm."</p>
                        <div class="user-info d-flex align-items-center">
                            <img src="../TrangUser/anlecho.png" alt="User">
                            <div>
                                <div class="user-name">Lê Quốc An</div>
                                <div class="user-time">2 giờ trước</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="newsletter-section text-center">
        <div class="container">
            <i class="far fa-envelope fa-3x mb-3"></i>
            <h3 class="fw-bold">Đăng kí nhận khuyến mãi</h3>
            <p class="mb-4">Nhận thông tin về các sản phẩm mới và ưu đãi đặc biệt</p>
            <form class="d-flex justify-content-center w-md-50 mx-auto">
                <input type="email" class="form-control newsletter-input w-50" placeholder="Nhập email của bạn...">
                <button type="submit" class="newsletter-btn">Đăng ký</button>
            </form>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <img src="../TrangUser/ack.png" alt="ACK Logo"
                            style="height: 40px; filter: brightness(0) invert(1);"> <span
                            class="ms-2 fw-bold fs-4 text-white">ACK Mart</span>
                    </div>
                    <p class="text-muted">Nơi mua sắm tin cậy cho mọi nhà. Cam kết chất lượng, giá cả bình ổn và dịch vụ
                        tận tâm.</p>
                    <div class="social-icons mt-3">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                        <a href="#"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>

                <div class="col-6 col-md-2 mb-4">
                    <h5>Về ACK</h5>
                    <a href="#">Giới thiệu</a>
                    <a href="#">Tuyển dụng</a>
                    <a href="#">Tin tức & Sự kiện</a>
                    <a href="#">Hệ thống cửa hàng</a>
                </div>

                <div class="col-6 col-md-3 mb-4">
                    <h5>Hỗ trợ khách hàng</h5>
                    <a href="#">Chính sách đổi trả</a>
                    <a href="#">Chính sách giao hàng</a>
                    <a href="#">Phương thức thanh toán</a>
                    <a href="#">Câu hỏi thường gặp (FAQ)</a>
                </div>

                <div class="col-md-3 mb-4">
                    <h5>Liên hệ</h5>
                    <div class="d-flex mb-2 text-muted">
                        <i class="fas fa-map-marker-alt mt-1 me-2"></i>
                        <span>Phường 1, TP. Cao Lãnh, Đồng Tháp</span>
                    </div>
                    <div class="d-flex mb-2 text-muted">
                        <i class="fas fa-envelope mt-1 me-2"></i>
                        <span>hotro@ackmart.vn</span>
                    </div>
                    <div class="d-flex mb-2 text-muted">
                        <i class="fas fa-phone-alt mt-1 me-2"></i>
                        <span class="text-white fw-bold">1900 6789</span>
                    </div>
                </div>
            </div>

            <div class="copyright-border d-flex flex-column flex-md-row justify-content-between align-items-center">
                <p class="m-0">© 2025 ACK Mart. Tất cả các quyền được bảo lưu.</p>
                <div class="mt-2 mt-md-0">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/0/04/Visa.svg" height="20"
                        class="me-2 bg-white rounded p-1" alt="Visa">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" height="20"
                        class="me-2 bg-white rounded p-1" alt="Mastercard">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" height="20"
                        class="bg-white rounded p-1" alt="Paypal">
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="web-events.js?v=20260412-2"></script>
</body>

</html>