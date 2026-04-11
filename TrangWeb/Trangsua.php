<?php
require_once __DIR__ . '/catalog_data.php';

$__catalogData = loadCatalogDataForPage(basename(__FILE__));
$soft_drinks = $__catalogData['soft_drinks'];
$beers = $__catalogData['beers'];
$gifts = $__catalogData['gifts'];
$fresh_foods = $__catalogData['fresh_foods'];
$household = $__catalogData['household'];
$categories = $__catalogData['categories'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Mart - Sắm Tết Thả Ga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #007bff;
            --light-bg-blue: #e0f2fe; 
            --header-blue: #f8f9fa;
            --text-red: #dc3545;
        }
        body { font-family: 'Roboto', sans-serif; background-color: #f5f5f5; }

        /* HEADER */
        .top-bar { background-color: #fff; padding: 10px 0; border-bottom: 1px solid #eee; }
        .ack-logo { height: 40px; width: auto; object-fit: contain; }

        .search-box { position: relative; width: 100%; }
        .search-box input { border-radius: 20px; padding-right: 40px; background: #f1f1f1; border: none; }
        .search-box i { position: absolute; right: 15px; top: 10px; color: #666; }
        .location-select { border-radius: 20px; background: #eee; padding: 5px 15px; font-size: 0.9rem; border: none; }
        
        .main-nav { background-color: var(--primary-blue); color: white; padding: 0; }
        .main-nav .nav-link { color: white; padding: 10px 20px; font-weight: 500; }
        .main-nav .nav-link:hover { background-color: rgba(255,255,255,0.2); }
        .delivery-notice { font-size: 0.9rem; font-style: italic; }
        
        /* SIDEBAR DANH MỤC */
        .sidebar-container {
            background: #fff; border-radius: 12px; padding: 15px;
            position: sticky; top: 20px; z-index: 90;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #edf2f7;
        }
        .sidebar-title { font-weight: 700; margin-bottom: 15px; font-size: 1.1rem; padding-bottom: 10px; border-bottom: 2px solid #f1f1f1; display: flex; align-items: center; gap: 10px; }
        .cat-item { display: flex; align-items: center; padding: 10px; border-radius: 8px; color: #4a4a4a; text-decoration: none; transition: all 0.2s; margin-bottom: 2px; }
        .cat-item:hover { background-color: var(--light-bg-blue); color: var(--primary-blue); font-weight: 500; transform: translateX(5px); }
        .cat-icon { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 12px; background: #fff; border: 1px solid #eee; padding: 2px; }

        /* --- THANH TRƯỢT DANH MỤC (KÉO ĐƯỢC) --- */
        .category-scroll-wrapper {
            background: #fff; 
            border-radius: 12px; 
            padding: 20px 0; 
            margin-bottom: 25px;
        }
        .category-scroll-inner {
            display: flex;
            overflow-x: auto; 
            gap: 20px;
            padding: 0 15px;
            scroll-behavior: auto; /* Để auto để JS kéo mượt hơn */
            -ms-overflow-style: none;  
            scrollbar-width: none;
            
            /* [Quan trọng] Con trỏ chuột hình bàn tay để biết là kéo được */
            cursor: grab; 
            
            /* Chặn bôi đen text khi kéo */
            user-select: none; 
            -webkit-user-select: none;
        }
        .category-scroll-inner.active {
            cursor: grabbing; /* Đổi icon thành bàn tay nắm khi đang kéo */
            cursor: -webkit-grabbing;
        }
        .category-scroll-inner::-webkit-scrollbar { display: none; }
        
        .cat-scroll-item {
            flex: 0 0 auto; width: 85px;
            text-align: center; text-decoration: none; color: #333;
            transition: transform 0.2s;
            display: flex; flex-direction: column; align-items: center;
        }
        .cat-scroll-item:hover { transform: translateY(-5px); color: var(--primary-blue); }
        .cat-scroll-img {
            width: 60px; height: 60px;
            border-radius: 50%; background: #fff; object-fit: contain;
            padding: 5px; margin-bottom: 8px; border: 1px solid #eee;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            /* Chặn sự kiện chuột lên ảnh để kéo div cha dễ dàng */
            pointer-events: none; 
        }
        .cat-scroll-name { font-size: 0.8rem; font-weight: 600; line-height: 1.2; text-align: center; }

        /* BANNER */
        .main-banner { margin-top: 15px; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        /* PRODUCT CARD */
        .product-card {
            background: white; border-radius: 10px; border: 1px solid #eee;
            padding: 10px; height: 100%; position: relative; transition: transform 0.2s;
        }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .badge-sale { position: absolute; top: 10px; right: 10px; background: #ffc107; font-weight: bold; font-size: 0.8rem; padding: 3px 8px; border-radius: 10px; z-index: 1; }
        .card-img-wrapper { position: relative; overflow: hidden; border-radius: 8px; margin-bottom: 10px; }
        .product-img { width: 100%; height: 200px; object-fit: contain; padding: 10px; }
        .product-title { font-size: 0.95rem; font-weight: 500; height: 40px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; margin-bottom: 5px; }
        .price-current { color: #d0021b; font-weight: 700; font-size: 1.1rem; }
        .price-old { font-size: 0.85rem; text-decoration: line-through; color: #999; margin-left: 5px; }
        .btn-add {
            position: absolute; bottom: 10px; right: 10px;
            background: var(--primary-blue); color: white;
            border: none; width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-add:hover { background: #0056b3; }

        /* PHÂN TRANG */
        .pagination-container {
            display: flex; justify-content: center; align-items: center;
            gap: 10px; margin-top: 40px; margin-bottom: 20px;
        }
        .page-link-custom {
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            background: white; color: #333; border-radius: 8px;
            text-decoration: none; font-weight: 500; border: 1px solid #eee; transition: all 0.2s;
        }
        .page-link-custom:hover { background: #f0f0f0; }
        .page-link-custom.active { background: #e0e0e0; font-weight: bold; border: none; }
        .page-arrow { font-size: 1.2rem; font-weight: bold; color: #333; }

        /* FOOTER & SECTIONS */
        .feedback-section { background-color: #f9fafb; padding: 60px 0; }
        .feedback-card { background: #fff; border: 1px solid #eaecf0; border-radius: 12px; padding: 24px; height: 100%; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.2s; }
        .feedback-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .star-rating { color: #fdb022; margin-bottom: 15px; font-size: 14px; }
        .feedback-text { font-size: 0.95rem; color: #101828; font-weight: 500; line-height: 1.5; margin-bottom: 20px; font-style: italic; }
        .user-info img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; margin-right: 12px; }
        .user-name { font-weight: 700; color: #101828; font-size: 0.9rem; margin-bottom: 2px; }
        .user-time { font-size: 0.8rem; color: #667085; }

        .newsletter-section { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); padding: 60px 0; color: white; text-align: center; width: 100%; margin-left: calc(-50vw + 50%); margin-right: calc(-50vw + 50%); padding-left: calc(50vw - 50%); padding-right: calc(50vw - 50%); }
        .newsletter-section .container { max-width: 100%; padding-left: 15px !important; padding-right: 15px !important; }
        .newsletter-icon { font-size: 3rem; margin-bottom: 15px; opacity: 0.9; }
        .newsletter-input { border-radius: 30px 0 0 30px; border: none; padding: 12px 20px; height: 50px; }
        .newsletter-btn { border-radius: 0 30px 30px 0; background: #fff; color: #007bff; font-weight: bold; border: none; padding: 0 30px; height: 50px; }
        .newsletter-btn:hover { background: #f0f0f0; }

        footer { background-color: #1d2939; color: #eaecf0; padding-top: 60px; font-size: 0.9rem; margin-top: 0 !important; width: 100%; margin-left: calc(-50vw + 50%); margin-right: calc(-50vw + 50%); padding-left: calc(50vw - 50%); padding-right: calc(50vw - 50%); }
        footer .container { max-width: 100%; padding-left: 15px !important; padding-right: 15px !important; }
        footer h5 { color: #fff; font-weight: 700; margin-bottom: 20px; text-transform: uppercase; font-size: 1rem; }
        footer a { color: #d0d5dd; text-decoration: none; display: block; margin-bottom: 12px; transition: color 0.2s; }
        footer a:hover { color: #fff; padding-left: 5px; }
        footer .social-icons a { display: inline-block; width: 36px; height: 36px; line-height: 36px; background: rgba(255,255,255,0.1); text-align: center; border-radius: 50%; margin-right: 10px; color: #fff; }
        footer .social-icons a:hover { background: var(--primary-blue); }
        .copyright-border { border-top: 1px solid #344054; margin-top: 40px; padding: 20px 0; color: #98a2b3; }
    /* === Unified full-width newsletter + footer (global override) === */
    .bg-light.py-4, .feedback-section, .newsletter-section, footer { width: 100%; position: relative; left: 0; right: 0; margin-left: 0; margin-right: 0; }
    .newsletter-section { min-height: 340px; display: flex; align-items: center; justify-content: center; text-align: center; padding: 32px 16px; background: linear-gradient(135deg, #5865f8 0%, #3844c7 100%); color: #fff; }
    .newsletter-section > .container { width: 100%; max-width: none !important; padding-left: 16px !important; padding-right: 16px !important; }
    .newsletter-section .row { width: 100%; justify-content: center; }
    .newsletter-section .col-md-8, .newsletter-section .col-lg-8 { width: 100%; max-width: 900px; display: flex; flex-direction: column; align-items: center; gap: 14px; }
    .newsletter-section .d-flex { width: 100%; max-width: 760px; display: flex; align-items: center; justify-content: center; gap: 14px; }
    .newsletter-input, .newsletter-section input[type="email"] { width: 100% !important; flex: 1 1 auto; min-height: 56px; border: none; border-radius: 999px !important; padding: 0 22px; font-size: 1.06rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12); }
    .newsletter-btn, .newsletter-section button { min-height: 56px; border: none; border-radius: 999px !important; padding: 0 28px; font-weight: 700; color: #fff; background: linear-gradient(135deg, #0b74e5 0%, #1f55ff 100%); box-shadow: 0 10px 25px rgba(15, 87, 209, 0.35); }
    footer { background-color: #1e2743; color: #eaecf0; padding: 56px 16px 22px !important; margin-top: 0 !important; }
    footer > .container { width: 100%; max-width: none !important; padding-left: 16px !important; padding-right: 16px !important; }
    footer > .container > .row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 30px; margin: 0 !important; }
    footer > .container > .row > [class*="col"] { max-width: none !important; width: auto !important; padding-left: 0 !important; padding-right: 0 !important; margin-bottom: 0 !important; }
    .copyright-border { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    @media (min-width: 992px) { .bg-light.py-4, .feedback-section, .newsletter-section, footer { padding-left: 80px; padding-right: 80px; } .newsletter-section { min-height: 380px; } }
    @media (max-width: 768px) { .newsletter-section .d-flex { flex-direction: column; max-width: 460px; gap: 12px; } .newsletter-input, .newsletter-btn, .newsletter-section button { width: 100% !important; } }
    footer .text-muted,
    footer .text-muted *,
    footer p,
    footer span {
        color: #dbe2ff !important;
        opacity: 1 !important;
    }

    footer h5,
    footer .fw-bold,
    footer .text-white {
        color: #ffffff !important;
    }

    .newsletter-btn,
    .newsletter-section button {
        white-space: nowrap;
        min-width: 132px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    </style>
</head>
<body>

    <header class="sticky-top bg-white">
        <div class="top-bar">
            <div class="container d-flex align-items-center justify-content-between">
                <a href="trangchu.php" class="d-flex align-items-center text-decoration-none me-3">
                    <img src="../TrangUser/ack.png" alt="ACK Logo" class="ack-logo">
                </a>

                <div class="d-none d-md-block me-3">
                    <button class="location-select">
                        <i class="fas fa-map-marker-alt text-danger me-1"></i> Đồng Tháp <i class="fas fa-caret-down ms-1"></i>
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
                    <a href="#" class="text-dark"><i class="fas fa-bell fa-lg"></i></a>
                    <a href="giohang.php" class="text-dark"><i class="fas fa-shopping-basket"></i></i></a>
                    <a href="#" class="text-warning"><i class="fas fa-user-circle fa-2x"></i></a>
                </div>
            </div>
        </div>

        <div class="main-nav">
            <div class="container d-flex justify-content-between align-items-center">
                <ul class="nav">
                    <li class="nav-item"><a class="nav-link" href="trangchu.php">Sản phẩm</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Tin tức</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Tuyển dụng</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Chuyển nhượng</a></li>
                </ul>
                <div class="delivery-notice d-none d-md-block">
                    <i class="fas fa-truck-fast me-1"></i> Miễn phí giao hàng tại Đồng Tháp
                </div>
            </div>
        </div>
    </header>

    <div class="container pb-5 mt-4">
    <div class="row">
        
        <div class="col-lg-3 d-none d-lg-block">
            <div class="sidebar-container">
                <div class="sidebar-title">
                    <i class="fas fa-bars text-primary"></i> Danh mục
                </div>
                <div class="d-flex flex-column">
                    <?php foreach($categories as $cat): ?>
                    <a href="<?php echo $cat['link'] ?? '#'; ?>" class="cat-item">
                        <img src="<?php echo $cat['img']; ?>" class="cat-icon" alt="icon">
                        <span><?php echo $cat['name']; ?></span>
                        <i class="fas fa-chevron-right ms-auto" style="font-size: 0.7rem; opacity: 0.5;"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            
            <div id="tetCarousel" class="carousel slide main-banner mb-4" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="../anhtrangchu/muctet.png" class="d-block w-100" alt="Tet Banner 1">
                    </div>
                    <div class="carousel-item">
                        <img src="../anhtrangchu/drthanh.png" class="d-block w-100" alt="Tet Banner 2">
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#tetCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#tetCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                </button>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold mb-0">Sản phẩm</h4>
                <a href="#" class="text-primary text-decoration-none fw-bold">Xem tất cả <i class="fas fa-chevron-right"></i></a>
            </div>
            
            <div class="category-scroll-wrapper">
                <div class="category-scroll-inner">
                    <?php foreach($categories as $cat): ?>
                    <a href="<?php echo $cat['link'] ?? '#'; ?>" class="cat-scroll-item" draggable="false">
                        <img src="<?php echo $cat['img']; ?>" class="cat-scroll-img" alt="<?php echo $cat['name']; ?>">
                        <span class="cat-scroll-name"><?php echo $cat['name']; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <?php foreach($soft_drinks as $prod): ?>
                <div class="col-6 col-md-3">
                    <div class="product-card">
                        <a href="<?php echo $prod['link'] ?? '#'; ?>" class="text-decoration-none text-dark">
                            
                            <?php if(!empty($prod['discount'])): ?>
                                <span class="badge-sale"><?php echo $prod['discount']; ?></span>
                            <?php endif; ?>

                            <div class="card-img-wrapper">
                                <img src="<?php echo $prod['img']; ?>" class="product-img" alt="Product">
                            </div>
                            
                            <div class="product-title"><?php echo $prod['name']; ?></div>
                            
                            <div class="d-flex align-items-center">
                                <span class="price-current"><?php echo $prod['price']; ?></span>
                            </div>
                            
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="price-old"><?php echo $prod['old']; ?></span>
                                <button class="btn-add" type="button"><i class="fas fa-plus"></i></button>
                            </div>

                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mb-3">
                <?php foreach($beers as $prod): ?>
                <div class="col-6 col-md-3">
                    <div class="product-card">
                   
                        <div class="card-img-wrapper">
                            <img src="<?php echo $prod['img']; ?>" class="product-img" alt="Product">
                        </div>
                        <div class="product-title"><?php echo $prod['name']; ?></div>
                        <div><span class="price-current"><?php echo $prod['price']; ?></span></div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="price-old"><?php echo $prod['old']; ?></span>
                            <button class="btn-add"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mb-3">
                <?php foreach($gifts as $prod): ?>
                <div class="col-6 col-md-3">
                    <div class="product-card">
                       
                        <div class="card-img-wrapper"><img src="<?php echo $prod['img']; ?>" class="product-img" alt="Product"></div>
                        <div class="product-title"><?php echo $prod['name']; ?></div>
                        <div><span class="price-current"><?php echo $prod['price']; ?></span></div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="price-old"><?php echo $prod['old']; ?></span>
                            <button class="btn-add"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mb-3">
                <?php foreach($fresh_foods as $prod): ?>
                <div class="col-6 col-md-3">
                    <div class="product-card">
                       
                        <div class="card-img-wrapper"><img src="<?php echo $prod['img']; ?>" class="product-img" alt="Product"></div>
                        <div class="product-title"><?php echo $prod['name']; ?></div>
                        <div><span class="price-current"><?php echo $prod['price']; ?></span></div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="price-old"><?php echo $prod['old']; ?></span>
                            <button class="btn-add"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mb-3">
                <?php foreach($household as $prod): ?>
                <div class="col-6 col-md-3">
                    <div class="product-card">
                        
                        <div class="card-img-wrapper"><img src="<?php echo $prod['img']; ?>" class="product-img" alt="Product"></div>
                        <div class="product-title"><?php echo $prod['name']; ?></div>
                        <div><span class="price-current"><?php echo $prod['price']; ?></span></div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="price-old"><?php echo $prod['old']; ?></span>
                            <button class="btn-add"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php echo catalogRenderPagination($__catalogData['pagination'] ?? []); ?>

        </div>
    </div>
    </div>

    <div class="bg-light py-4">
        <div class="container">
            <div class="row text-center">
                <div class="col-3"><i class="fas fa-truck fa-2x text-primary mb-2"></i><br><b>Giao hàng nhanh</b></div>
                <div class="col-3"><i class="fas fa-shield-alt fa-2x text-primary mb-2"></i><br><b>An toàn bảo mật</b></div>
                <div class="col-3"><i class="fas fa-headset fa-2x text-primary mb-2"></i><br><b>Hỗ trợ 24/7</b></div>
                <div class="col-3"><i class="fas fa-undo fa-2x text-primary mb-2"></i><br><b>Đổi trả nhanh</b></div>
            </div>
        </div>
    </div>

    <section class="feedback-section">
        <div class="container">
            <div class="text-center mb-5">
                <h3 class="fw-bold">Cảm nhận của khách hàng</h3>
                <p class="text-muted">Hàng ngàn khách hàng hài lòng về chúng tôi</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feedback-card">
                        <div class="star-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="feedback-text">"Sản phẩm rất tươi ngon, giao hàng đúng hẹn. Tết này sẽ ủng hộ tiếp! Đóng gói rất cẩn thận."</p>
                        <div class="user-info d-flex align-items-center">
                            <img src="../TrangUser/messi.png" alt="User">
                            <div>
                                <div class="user-name">Đinh Quốc Cường</div>
                                <div class="user-time">15 phút trước</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feedback-card">
                        <div class="star-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="feedback-text">"Giá cả cạnh tranh, nhân viên tư vấn nhiệt tình. Sẽ giới thiệu cho bạn bè mua sắm tại đây."</p>
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
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                        </div>
                        <p class="feedback-text">"Dịch vụ tuyệt vời. Mua hàng online mà được freeship lại còn được quà tặng kèm."</p>
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

    <section class="newsletter-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <i class="far fa-envelope newsletter-icon"></i>
                    <h3 class="fw-bold">Đăng kí nhận khuyến mãi</h3>
                    <p class="mb-4 text-white-50">Nhận thông tin về các sản phẩm mới và các ưu đãi đặc biệt</p>
                    <div class="d-flex justify-content-center">
                        <input type="email" class="form-control newsletter-input w-50" placeholder="Email của bạn...">
                        <button class="newsletter-btn">Đăng ký</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <img src="../TrangUser/ack.png" alt="ACK Logo" style="height: 40px; filter: brightness(0) invert(1);"> <span class="ms-2 fw-bold fs-4 text-white">ACK Mart</span>
                    </div>
                    <p class="text-muted">Nơi mua sắm tin cậy cho mọi nhà. Cam kết chất lượng, giá cả bình ổn và dịch vụ tận tâm.</p>
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
                    <img src="https://upload.wikimedia.org/wikipedia/commons/0/04/Visa.svg" height="20" class="me-2 bg-white rounded p-1" alt="Visa">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" height="20" class="me-2 bg-white rounded p-1" alt="Mastercard">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" height="20" class="bg-white rounded p-1" alt="Paypal">
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="web-events.js"></script>
    
    <script>
        const slider = document.querySelector('.category-scroll-inner');
        let isDown = false;
        let startX;
        let scrollLeft;

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            slider.classList.add('active'); // Thêm class để đổi con trỏ chuột
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });

        slider.addEventListener('mouseleave', () => {
            isDown = false;
            slider.classList.remove('active');
        });

        slider.addEventListener('mouseup', () => {
            isDown = false;
            slider.classList.remove('active');
        });

        slider.addEventListener('mousemove', (e) => {
            if(!isDown) return;
            e.preventDefault(); // Chặn việc bôi đen text
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 2; // Nhân 2 để kéo nhanh hơn một chút
            slider.scrollLeft = scrollLeft - walk;
        });
    </script>
</body>
</html>