<?php
require_once __DIR__ . '/catalog_data.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function buildDiverseFeaturedProducts(int $limit = 12, int $maxPerSlug = 2): array
{
    $limit = max(1, $limit);
    $maxPerSlug = max(1, $maxPerSlug);

    $allProducts = catalogFetchProducts();
    if (empty($allProducts)) {
        return [];
    }

    $inStock = [];
    $outOfStock = [];

    foreach ($allProducts as $item) {
        $stock = (int) ($item['stock'] ?? 0);
        if ($stock > 0) {
            $inStock[] = $item;
            continue;
        }

        $outOfStock[] = $item;
    }

    $pool = array_merge($inStock, $outOfStock);
    $picked = [];
    $pickedIds = [];
    $slugCounter = [];

    foreach ($pool as $item) {
        $id = trim((string) ($item['id'] ?? ''));
        if ($id === '' || isset($pickedIds[$id])) {
            continue;
        }

        $slug = trim((string) ($item['slug'] ?? 'other'));
        if ($slug === '') {
            $slug = 'other';
        }

        $slugCounter[$slug] = (int) ($slugCounter[$slug] ?? 0);
        if ($slugCounter[$slug] >= $maxPerSlug) {
            continue;
        }

        $picked[] = $item;
        $pickedIds[$id] = true;
        $slugCounter[$slug]++;

        if (count($picked) >= $limit) {
            break;
        }
    }

    if (count($picked) < $limit) {
        foreach ($pool as $item) {
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '' || isset($pickedIds[$id])) {
                continue;
            }

            $picked[] = $item;
            $pickedIds[$id] = true;

            if (count($picked) >= $limit) {
                break;
            }
        }
    }

    return array_map(static function (array $item): array {
        $slug = (string) ($item['slug'] ?? '');
        $rating = 5;
        if ($slug === 'mypham') {
            $rating = 4;
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'name' => (string) ($item['name'] ?? 'Sản phẩm'),
            'price' => (string) ($item['price'] ?? '0 ₫'),
            'old_price' => (string) ($item['old'] ?? '0 ₫'),
            'discount' => (string) ($item['discount'] ?? '-10%'),
            'image' => (string) ($item['img'] ?? '../TrangUser/ack.png'),
            'rating' => $rating,
            'link' => (string) ($item['link'] ?? '#'),
            'price_raw' => (int) ($item['price_raw'] ?? 0),
        ];
    }, $picked);
}

$categories = [
    ['name' => 'Thức ăn nhanh', 'icon' => 'fa-burger', 'desc' => 'Nhiều món tiện lợi mỗi ngày', 'link' => 'Trangthucannhanh.php'],
    ['name' => 'Đồ uống', 'icon' => 'fa-mug-hot', 'desc' => 'Giải khát và bổ sung năng lượng', 'link' => 'Trangdouong.php'],
    ['name' => 'Gia dụng', 'icon' => 'fa-house', 'desc' => 'Vật dụng cần thiết cho gia đình', 'link' => 'Tranggiadung.php'],
    ['name' => 'Mỹ phẩm', 'icon' => 'fa-spa', 'desc' => 'Chăm sóc cá nhân an toàn, tiện lợi', 'link' => 'Trangmypham.php'],
];

$products = buildDiverseFeaturedProducts(12, 2);

$feedbacks = [
    ['name' => 'Đinh Quốc Cường', 'comment' => 'Sản phẩm chất lượng, giao hàng nhanh. Rất hài lòng.', 'avatar' => '../TrangUser/messi.png', 'time' => '15 phút trước'],
    ['name' => 'Nguyễn Thanh Kiệt', 'comment' => 'Nhân viên tư vấn nhiệt tình, đặt hàng rất dễ.', 'avatar' => '../TrangUser/kiet.png', 'time' => '1 giờ trước'],
    ['name' => 'Lê Quốc An', 'comment' => 'Giá hợp lý và nhiều ưu đãi, sẽ quay lại mua tiếp.', 'avatar' => '../TrangUser/anlecho.png', 'time' => '2 giờ trước'],
];
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Mart - Chào mừng bạn</title>
    <meta name="description"
        content="ACK Mart - Trang mua sắm đầu tiên trước đăng nhập: xem danh mục, sản phẩm nổi bật và ưu đãi mới nhất.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;800&display=swap" rel="stylesheet">

    <style>
    :root {
        --brand-700: #0b66ff;
        --brand-600: #2e7dff;
        --brand-100: #eaf2ff;
        --text-900: #101828;
        --text-600: #475467;
        --line: #e4e7ec;
        --success: #16a34a;
        --surface: #ffffff;
        --bg-soft: #f8faff;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        font-family: 'Roboto', sans-serif;
        background: var(--bg-soft);
        color: var(--text-900);
    }

    .navbar {
        border-bottom: 1px solid #f0f2f5;
        backdrop-filter: saturate(130%) blur(8px);
    }

    .navbar-brand img {
        height: 42px;
    }

    .nav-link {
        font-weight: 500;
        color: #344054;
    }

    .nav-link:hover,
    .nav-link.active {
        color: var(--brand-700);
    }

    .btn-pill {
        border-radius: 999px;
        font-weight: 600;
        padding: 10px 18px;
    }

    .hero {
        position: relative;
        overflow: hidden;
        background: linear-gradient(120deg, #0b66ff 0%, #37a3ff 100%);
        color: #fff;
        padding: 86px 0 72px;
        border-bottom-left-radius: 30px;
        border-bottom-right-radius: 30px;
    }

    .hero::before,
    .hero::after {
        content: "";
        position: absolute;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        pointer-events: none;
    }

    .hero::before {
        width: 420px;
        height: 420px;
        right: -120px;
        top: -160px;
    }

    .hero::after {
        width: 280px;
        height: 280px;
        left: -80px;
        bottom: -120px;
    }

    .hero h1 {
        font-size: clamp(2rem, 4vw, 3.35rem);
        font-weight: 800;
        line-height: 1.18;
        margin-bottom: 12px;
    }

    .hero p {
        color: rgba(255, 255, 255, 0.92);
        font-size: 1.05rem;
    }

    .hero-card {
        background: #fff;
        border-radius: 20px;
        padding: 10px;
        width: 700px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.16);
        max-width: 1000px;
        margin-inline: auto;
        position: relative;
        z-index: 1;
    }

    .hero-card img {
        border-radius: 14px;
    }

    .hero-kpis {
        margin-top: 22px;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .hero-kpi {
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 14px;
        text-align: center;
        padding: 10px 8px;
    }

    .hero-kpi .num {
        display: block;
        font-weight: 800;
        font-size: 1.1rem;
    }

    .hero-kpi .label {
        display: block;
        font-size: 0.84rem;
        opacity: 0.94;
    }

    .section-title {
        font-weight: 800;
        margin-bottom: 8px;
    }

    .section-sub {
        color: var(--text-600);
        margin-bottom: 0;
    }

    .category-link {
        text-decoration: none;
        display: block;
        height: 100%;
    }

    .category-card {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 20px;
        height: 100%;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .category-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px rgba(16, 24, 40, 0.08);
        border-color: #d0d5dd;
    }

    .category-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: var(--brand-100);
        color: var(--brand-700);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        font-size: 1.25rem;
    }

    .product-card {
        border: 1px solid var(--line);
        border-radius: 16px;
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .product-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 30px rgba(16, 24, 40, 0.12);
    }

    .badge-discount {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 2;
        background: #1d4ed8;
        color: #fff;
        border-radius: 999px;
        font-size: 0.78rem;
        padding: 6px 10px;
        font-weight: 700;
    }

    .product-img {
        width: 100%;
        height: 185px;
        object-fit: cover;
    }

    .price-new {
        color: #0b66ff;
        font-weight: 800;
        font-size: 1.05rem;
    }

    .price-old {
        text-decoration: line-through;
        color: #98a2b3;
        font-size: 0.88rem;
    }

    .stars {
        color: #f59e0b;
        font-size: 0.82rem;
    }

    .btn-view-more {
        min-width: 220px;
    }

    .benefit-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 18px;
        height: 100%;
        text-align: center;
    }

    .benefit-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #f2f7ff;
        color: var(--brand-700);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-bottom: 10px;
    }

    .feedback-section {
        background: #f5f8ff;
        border-top: 1px solid #edf2ff;
        border-bottom: 1px solid #edf2ff;
    }

    .feedback-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 20px;
        height: 100%;
    }

    .feedback-text {
        color: #344054;
        font-style: italic;
        min-height: 72px;
    }

    .user-info img {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 10px;
        border: 1px solid #eaecf0;
    }

    .newsletter {
        background: linear-gradient(130deg, #0b66ff 0%, #0058d6 100%);
        color: #fff;
        border-radius: 20px;
        padding: 34px;
    }

    .newsletter .form-control {
        border-radius: 999px;
        border: none;
        min-height: 46px;
    }

    .newsletter .btn {
        border-radius: 999px;
        font-weight: 700;
        min-height: 46px;
    }

    footer {
        background: #111827;
        color: #d0d5dd;
        margin-top: 50px;
        padding-top: 48px;
    }

    footer h5 {
        color: #fff;
        font-weight: 700;
        margin-bottom: 14px;
        font-size: 1rem;
    }

    footer a {
        color: #d0d5dd;
        text-decoration: none;
        display: inline-block;
        margin-bottom: 10px;
    }

    footer a:hover {
        color: #fff;
    }

    .social-icons a {
        width: 36px;
        height: 36px;
        line-height: 36px;
        text-align: center;
        border-radius: 50%;
        margin-right: 8px;
        background: rgba(255, 255, 255, 0.1);
    }

    .copyright-border {
        border-top: 1px solid rgba(255, 255, 255, 0.16);
        margin-top: 26px;
        padding: 18px 0;
        font-size: 0.9rem;
        color: #98a2b3;
    }

    @media (max-width: 767.98px) {
        .hero {
            padding: 70px 0 56px;
            border-bottom-left-radius: 22px;
            border-bottom-right-radius: 22px;
        }

        .newsletter {
            padding: 22px;
        }
    }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="#top" aria-label="ACK Mart">
                <img src="../TrangUser/ack.png" alt="ACK Mart logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Mở menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link active" href="#top">Trang đầu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#danhmuc">Danh mục</a></li>
                    <li class="nav-item"><a class="nav-link" href="#sanpham">Sản phẩm nổi bật</a></li>
                    <li class="nav-item"><a class="nav-link" href="#lydo">Lý do chọn ACK</a></li>
                    <li class="nav-item"><a class="nav-link" href="#phanhoi">Phản hồi</a></li>
                </ul>

                <div class="d-flex gap-2 align-items-center">
                    <a href="../Login/Dangnhap.php" class="btn btn-outline-primary btn-pill">Đăng nhập</a>
                    <a href="../Login/register.php" class="btn btn-primary btn-pill">Đăng ký</a>
                </div>
            </div>
        </div>
    </nav>

    <header class="hero" id="top">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <span class="badge rounded-pill bg-light text-primary mb-3 px-3 py-2">ACK Mart • Mua sắm tiện
                        lợi</span>
                    <h1>Trang mua sắm online số 1 Việt Nam</h1>
                    <p class="mb-4">Khám phá danh mục đa dạng, xem sản phẩm nổi bật và ưu đãi mới. Khi sẵn sàng, đăng
                        nhập để đặt hàng chỉ trong vài bước.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="#sanpham" class="btn btn-light btn-pill">Khám phá sản phẩm <i
                                class="fas fa-arrow-right ms-1"></i></a>
                        <a href="#sanpham" class="btn btn-outline-light btn-pill">Xem sản phẩm nổi bật</a>
                    </div>

                    <div class="hero-kpis">
                        <div class="hero-kpi">
                            <span class="num">1.000+</span>
                            <span class="label">Sản phẩm</span>
                        </div>
                        <div class="hero-kpi">
                            <span class="num">24/7</span>
                            <span class="label">Hỗ trợ</span>
                        </div>
                        <div class="hero-kpi">
                            <span class="num">99%</span>
                            <span class="label">Khách hài lòng</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="hero-card">
                        <img src="../AnhTrangTD/Ro.png" class="img-fluid" alt="Không gian mua sắm ACK Mart">
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="container py-5" id="danhmuc">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-2">
            <div>
                <h2 class="section-title">Danh mục nổi bật</h2>
                <p class="section-sub">Xem nhanh các nhóm hàng được quan tâm nhiều nhất.</p>
            </div>
        </div>

        <div class="row g-3 g-md-4">
            <?php foreach ($categories as $cat): ?>
            <div class="col-sm-6 col-lg-3">
                <a class="category-link" href="<?php echo e((string) ($cat['link'] ?? '#')); ?>">
                    <article class="category-card">
                        <span class="category-icon"><i
                                class="fas <?php echo e((string) ($cat['icon'] ?? 'fa-box')); ?>"></i></span>
                        <h5 class="fw-bold mb-1 text-dark"><?php echo e((string) ($cat['name'] ?? 'Danh mục')); ?></h5>
                        <p class="text-muted mb-0 small"><?php echo e((string) ($cat['desc'] ?? '')); ?></p>
                    </article>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="container pb-5" id="sanpham">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-2">
            <div>
                <h2 class="section-title">Sản phẩm nổi bật</h2>
                <p class="section-sub">Xem trước sản phẩm đang bán chạy. Đăng nhập để thêm vào giỏ hàng.</p>
            </div>
        </div>

        <div class="row g-4">
            <?php if (!empty($products)): ?>
            <?php foreach ($products as $prod): ?>
            <?php
                    $prodId = (string) ($prod['id'] ?? '');
                    $prodName = (string) ($prod['name'] ?? 'Sản phẩm');
                    $prodPrice = (string) ($prod['price'] ?? '0 ₫');
                    $prodOldPrice = (string) ($prod['old_price'] ?? '');
                    $prodPriceRaw = (string) ($prod['price_raw'] ?? '0');
                    $prodImage = (string) ($prod['image'] ?? '../TrangUser/ack.png');
                    $prodDiscount = (string) ($prod['discount'] ?? '-10%');
                    $prodLink = (string) ($prod['link'] ?? '#');
                    $prodRating = (int) ($prod['rating'] ?? 5);
                    $prodRating = max(1, min(5, $prodRating));
                    ?>
            <div class="col-sm-6 col-lg-3">
                <article class="card product-card h-100" data-product-id="<?php echo e($prodId); ?>"
                    data-product-name="<?php echo e($prodName); ?>" data-product-price="<?php echo e($prodPriceRaw); ?>"
                    data-product-old-price="<?php echo e($prodOldPrice); ?>"
                    data-product-img="<?php echo e($prodImage); ?>">
                    <span class="badge-discount"><?php echo e($prodDiscount); ?></span>
                    <a href="<?php echo e($prodLink); ?>" class="text-decoration-none">
                        <img src="<?php echo e($prodImage); ?>" class="product-img" alt="<?php echo e($prodName); ?>">
                    </a>
                    <div class="card-body d-flex flex-column">
                        <h6 class="product-title fw-bold mb-2"><?php echo e($prodName); ?></h6>
                        <div class="stars mb-2" aria-label="Đánh giá sản phẩm">
                            <?php for ($i = 0; $i < $prodRating; $i++): ?>
                            <i class="fas fa-star"></i>
                            <?php endfor; ?>
                            <span class="text-muted small ms-1">(nổi bật)</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3 mt-auto">
                            <span class="price-new"><?php echo e($prodPrice); ?></span>
                            <span class="price-old"><?php echo e($prodOldPrice); ?></span>
                        </div>

                        <a href="<?php echo e($prodLink); ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-eye me-1"></i> Xem chi tiết
                        </a>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="col-12">
                <div class="alert alert-light border text-center py-4">
                    Chưa có dữ liệu sản phẩm nổi bật. Vui lòng xem danh mục khác.
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-4">
            <a href="../Login/Dangnhap.php" class="btn btn-outline-primary btn-pill btn-view-more">
                <i class="fas fa-plus me-1"></i> Xem thêm sản phẩm
            </a>
        </div>
    </section>

    <section class="container py-5" id="lydo">
        <div class="text-center mb-4">
            <h2 class="section-title">Vì sao nên chọn ACK Mart?</h2>
            <p class="section-sub">Nhanh chóng, minh bạch và thân thiện ở mọi bước mua sắm.</p>
        </div>

        <div class="row g-3 g-md-4">
            <div class="col-6 col-md-3">
                <div class="benefit-card">
                    <span class="benefit-icon"><i class="fas fa-truck-fast"></i></span>
                    <h6 class="fw-bold mb-1">Giao nhanh</h6>
                    <p class="small text-muted mb-0">Ưu tiên đơn nội thành trong ngày.</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="benefit-card">
                    <span class="benefit-icon"><i class="fas fa-shield-heart"></i></span>
                    <h6 class="fw-bold mb-1">An toàn</h6>
                    <p class="small text-muted mb-0">Sản phẩm rõ nguồn gốc và thông tin.</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="benefit-card">
                    <span class="benefit-icon"><i class="fas fa-headset"></i></span>
                    <h6 class="fw-bold mb-1">Hỗ trợ 24/7</h6>
                    <p class="small text-muted mb-0">Luôn có đội ngũ sẵn sàng hỗ trợ bạn.</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="benefit-card">
                    <span class="benefit-icon"><i class="fas fa-rotate-left"></i></span>
                    <h6 class="fw-bold mb-1">Đổi trả rõ ràng</h6>
                    <p class="small text-muted mb-0">Xử lý nhanh với sản phẩm lỗi/hư hỏng.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="feedback-section py-5" id="phanhoi">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="section-title">Khách hàng nói gì?</h2>
                <p class="section-sub">Những phản hồi thực tế từ người dùng đã mua tại ACK Mart.</p>
            </div>

            <div class="row g-4">
                <?php foreach ($feedbacks as $item): ?>
                <div class="col-md-4">
                    <article class="feedback-card">
                        <div class="stars mb-2" aria-hidden="true">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="feedback-text">“<?php echo e((string) ($item['comment'] ?? '')); ?>”</p>
                        <div class="user-info d-flex align-items-center mt-3">
                            <img src="<?php echo e((string) ($item['avatar'] ?? '../TrangUser/ack.png')); ?>"
                                alt="<?php echo e((string) ($item['name'] ?? 'Khách hàng')); ?>">
                            <div>
                                <div class="fw-bold"><?php echo e((string) ($item['name'] ?? 'Khách hàng')); ?></div>
                                <small
                                    class="text-muted"><?php echo e((string) ($item['time'] ?? 'Vừa xong')); ?></small>
                            </div>
                        </div>
                    </article>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="newsletter">
            <div class="row g-3 align-items-center">
                <div class="col-lg-7">
                    <h3 class="fw-bold mb-1">Nhận ưu đãi sớm từ ACK Mart</h3>
                    <p class="mb-0 opacity-75">Đăng nhập hoặc tạo tài khoản để lưu voucher và theo dõi đơn hàng.</p>
                </div>
                <div class="col-lg-5">
                    <div class="d-grid gap-2 d-sm-flex justify-content-lg-end">
                        <a href="../Login/Dangnhap.php" class="btn btn-light px-4">Đăng nhập</a>
                        <a href="../Login/register.php" class="btn btn-outline-light px-4">Tạo tài khoản</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-2">
                        <img src="../TrangUser/ack.png" alt="ACK logo"
                            style="height: 38px; filter: brightness(0) invert(1);">
                        <span class="ms-2 fw-bold fs-5 text-white">ACK Mart</span>
                    </div>
                    <p class="mb-2">Nơi mua sắm đáng tin cậy cho gia đình Việt: giá hợp lý, thông tin rõ ràng và hỗ trợ
                        nhanh chóng.</p>
                    <div class="social-icons mt-3">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="Youtube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <h5>Khám phá</h5>
                    <a href="#danhmuc">Danh mục</a><br>
                    <a href="#sanpham">Sản phẩm nổi bật</a><br>
                    <a href="tin-tuc.php">Tin tức</a>
                </div>

                <div class="col-6 col-md-3">
                    <h5>Hỗ trợ</h5>
                    <a href="privacy-policy.php">Chính sách bảo mật</a><br>
                    <a href="tuyen-dung.php">Tuyển dụng</a><br>
                    <a href="locations.php">Hệ thống cửa hàng</a>
                </div>

                <div class="col-md-3">
                    <h5>Liên hệ</h5>
                    <div class="mb-2"><i class="fas fa-location-dot me-2"></i>TP. Cao Lãnh, Đồng Tháp</div>
                    <div class="mb-2"><i class="fas fa-envelope me-2"></i>hotro@ackmart.vn</div>
                    <div><i class="fas fa-phone me-2"></i><strong>1900 6789</strong></div>
                </div>
            </div>

            <div class="copyright-border d-flex flex-column flex-md-row justify-content-between align-items-center">
                <p class="m-0">© 2026 ACK Mart. Tất cả quyền được bảo lưu.</p>
                <div class="mt-2 mt-md-0 d-flex align-items-center gap-2">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/0/04/Visa.svg" height="22"
                        class="bg-white rounded p-1" alt="Visa">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" height="22"
                        class="bg-white rounded p-1" alt="Mastercard">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" height="22"
                        class="bg-white rounded p-1" alt="PayPal">
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="web-events.js?v=20260414-3"></script>
</body>

</html>