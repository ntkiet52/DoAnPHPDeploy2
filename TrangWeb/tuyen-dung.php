<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Tuyển dụng - ACK Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --primary-blue: #007bff;
        --bg-gray: #f5f7fb;
        --hero-blue: #2f66cc;
        --card-blue-1: #3e62d1;
        --card-blue-2: #22b1df;
    }

    body {
        font-family: 'Roboto', sans-serif;
        background: var(--bg-gray);
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
        background-color: var(--primary-blue);
        color: white;
        padding: 0;
    }

    .main-nav .nav-link {
        color: white;
        padding: 10px 20px;
        font-weight: 500;
    }

    .main-nav .nav-link:hover,
    .main-nav .nav-link.active {
        background-color: rgba(255, 255, 255, 0.2);
    }

    .delivery-notice {
        font-size: 0.9rem;
        font-style: italic;
    }

    .career-wrap {
        max-width: 1100px;
        margin: 20px auto 40px;
    }

    .career-hero {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 26px;
        margin-bottom: 16px;
        overflow: hidden;
    }

    .hero-title {
        font-size: clamp(1.7rem, 3.5vw, 2.4rem);
        font-weight: 800;
        line-height: 1.15;
        color: #111827;
        margin-bottom: 14px;
        text-transform: uppercase;
    }

    .hero-apply-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 34px;
        border-radius: 999px;
        padding: 0 18px;
        border: none;
        color: #fff;
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
    }

    .hero-stats {
        margin-top: 18px;
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .hero-stat-item strong {
        display: block;
        color: #0f172a;
        font-size: 1.6rem;
        line-height: 1;
    }

    .hero-stat-item span {
        color: #64748b;
        font-size: 0.82rem;
    }

    .hero-collage {
        position: relative;
        min-height: 250px;
        height: 100%;
    }

    .hero-main-photo,
    .hero-floating-photo {
        position: absolute;
        border-radius: 10px;
        border: 4px solid #fff;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.22);
        object-fit: cover;
    }

    .hero-main-photo {
        width: 250px;
        height: 160px;
        top: 30px;
        left: 20px;
        z-index: 2;
    }

    .hero-floating-photo.photo-a {
        width: 130px;
        height: 90px;
        top: -6px;
        left: 40px;
    }

    .hero-floating-photo.photo-b {
        width: 120px;
        height: 140px;
        top: 10px;
        right: 12px;
    }

    .hero-floating-photo.photo-c {
        width: 140px;
        height: 88px;
        bottom: 8px;
        left: 56px;
    }

    .hero-floating-photo.photo-d {
        width: 122px;
        height: 115px;
        bottom: 16px;
        right: 36px;
    }

    .star-deco {
        position: absolute;
        color: #38bdf8;
        font-size: 1.2rem;
        opacity: 0.95;
    }

    .star-deco.star-1 {
        top: 12px;
        right: 140px;
    }

    .star-deco.star-2 {
        bottom: 24px;
        right: 6px;
    }

    .career-section-card {
        background: #fff;
        border-radius: 16px;
        padding: 18px;
        margin-bottom: 16px;
        border: 1px solid #e5e7eb;
    }

    .section-title {
        text-align: center;
        color: var(--hero-blue);
        font-weight: 800;
        margin-bottom: 16px;
    }

    .opportunity-card {
        border-radius: 10px;
        color: #fff;
        padding: 16px;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .opportunity-card.store {
        background: linear-gradient(135deg, var(--card-blue-1) 0%, #2f58c6 100%);
    }

    .opportunity-card.office {
        background: linear-gradient(135deg, var(--card-blue-2) 0%, #0f94ca 100%);
    }

    .opportunity-card h4 {
        font-size: 1.8rem;
        text-transform: uppercase;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .opportunity-card p {
        margin: 0;
        line-height: 1.5;
        flex: 1;
    }

    .apply-small-btn {
        margin-top: auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        border-radius: 999px;
        padding: 0 16px;
        border: none;
        color: #2f66cc;
        font-weight: 700;
        background: #fff;
    }

    .life-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 10px;
    }

    .life-item {
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        min-height: 120px;
    }

    .life-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .life-item:nth-child(1) {
        grid-column: span 2;
    }

    .life-item:nth-child(2) {
        grid-column: span 2;
    }

    .life-item:nth-child(3) {
        grid-column: span 2;
    }

    .life-item:nth-child(4) {
        grid-column: span 4;
    }

    .life-item:nth-child(5) {
        grid-column: span 2;
    }

    .life-tag {
        position: absolute;
        top: 8px;
        left: 10px;
        color: #fff;
        font-size: 1.8rem;
        font-weight: 800;
        text-shadow: 0 3px 12px rgba(0, 0, 0, 0.45);
    }

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

    .bg-light.py-4,
    .feedback-section,
    .newsletter-section,
    .site-footer {
        width: 100%;
        position: relative;
        left: 0;
        right: 0;
        margin-left: 0;
        margin-right: 0;
    }

    .bg-light.py-4,
    .feedback-section {
        padding-left: 16px;
        padding-right: 16px;
    }

    .newsletter-section {
        background: linear-gradient(135deg, #5662f6 0%, #3943c8 100%);
        min-height: 340px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 32px 16px;
        color: #fff;
    }

    .newsletter-content {
        width: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 14px;
    }

    .newsletter-icon {
        font-size: 3.1rem;
        opacity: 0.95;
    }

    .newsletter-title {
        margin: 0;
        font-size: clamp(1.8rem, 3vw, 2.4rem);
        font-weight: 800;
    }

    .newsletter-subtitle {
        margin: 0;
        color: rgba(255, 255, 255, 0.78);
        font-size: clamp(1rem, 1.8vw, 1.35rem);
    }

    .newsletter-form {
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 14px;
        margin-top: 6px;
    }

    .newsletter-input {
        width: min(100%, 620px);
        min-height: 56px;
        border: none;
        outline: none;
        border-radius: 999px;
        padding: 0 24px;
        font-size: 1.15rem;
        color: #1f2937;
        background: #fff;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    }

    .newsletter-btn {
        min-height: 56px;
        border: none;
        border-radius: 999px;
        padding: 0 28px;
        font-weight: 700;
        font-size: 1.05rem;
        color: #fff;
        background: linear-gradient(135deg, #0b74e5 0%, #2351ff 100%);
        box-shadow: 0 10px 25px rgba(15, 87, 209, 0.35);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .newsletter-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 30px rgba(15, 87, 209, 0.45);
    }

    .site-footer {
        background-color: #1e2743;
        color: #eaecf0;
        padding: 56px 16px 22px;
    }

    .footer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 30px;
        align-items: start;
    }

    .site-footer h5 {
        color: #fff;
        font-weight: 700;
        margin-bottom: 14px;
        text-transform: uppercase;
        font-size: 1.05rem;
    }

    .site-footer a {
        color: #d0d5dd;
        text-decoration: none;
        display: block;
        margin-bottom: 10px;
        line-height: 1.55;
        transition: color 0.2s;
    }

    .site-footer a:hover {
        color: #fff;
    }

    .site-footer .social-icons a {
        display: inline-flex;
        width: 38px;
        height: 38px;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.12);
        text-align: center;
        border-radius: 50%;
        margin-right: 10px;
        color: #fff;
    }

    .site-footer .social-icons a:hover {
        background: var(--primary-blue);
    }

    .copyright-border {
        border-top: 1px solid #344054;
        margin-top: 36px;
        padding-top: 18px;
        color: #98a2b3;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }

    /* === Unified full-width newsletter + footer (global override) === */
    .bg-light.py-4,
    .feedback-section,
    .newsletter-section,
    footer {
        width: 100%;
        position: relative;
        left: 0;
        right: 0;
        margin-left: 0;
        margin-right: 0;
    }

    .newsletter-section {
        min-height: 340px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 32px 16px;
        background: linear-gradient(135deg, #5865f8 0%, #3844c7 100%);
        color: #fff;
    }

    .newsletter-section>.container {
        width: 100%;
        max-width: none !important;
        padding-left: 16px !important;
        padding-right: 16px !important;
    }

    .newsletter-section .row {
        width: 100%;
        justify-content: center;
    }

    .newsletter-section .col-md-8,
    .newsletter-section .col-lg-8 {
        width: 100%;
        max-width: 900px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 14px;
    }

    .newsletter-section .d-flex {
        width: 100%;
        max-width: 760px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
    }

    .newsletter-input,
    .newsletter-section input[type="email"] {
        width: 100% !important;
        flex: 1 1 auto;
        min-height: 56px;
        border: none;
        border-radius: 999px !important;
        padding: 0 22px;
        font-size: 1.06rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    }

    .newsletter-btn,
    .newsletter-section button {
        min-height: 56px;
        border: none;
        border-radius: 999px !important;
        padding: 0 28px;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, #0b74e5 0%, #1f55ff 100%);
        box-shadow: 0 10px 25px rgba(15, 87, 209, 0.35);
    }

    footer {
        background-color: #1e2743;
        color: #eaecf0;
        padding: 56px 16px 22px !important;
        margin-top: 0 !important;
    }

    footer>.container {
        width: 100%;
        max-width: none !important;
        padding-left: 16px !important;
        padding-right: 16px !important;
    }

    footer>.container>.row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 30px;
        margin: 0 !important;
    }

    footer>.container>.row>[class*="col"] {
        max-width: none !important;
        width: auto !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-bottom: 0 !important;
    }

    .copyright-border {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    @media (max-width: 991.98px) {
        .career-hero {
            padding: 16px;
        }

        .hero-collage {
            min-height: 220px;
            margin-top: 18px;
        }

        .hero-main-photo {
            width: 200px;
            height: 130px;
            left: 10px;
        }

        .hero-floating-photo.photo-a {
            width: 110px;
            height: 80px;
            left: 8px;
        }

        .hero-floating-photo.photo-b {
            width: 92px;
            height: 112px;
            right: 6px;
        }

        .hero-floating-photo.photo-c {
            width: 110px;
            height: 72px;
            left: 20px;
        }

        .hero-floating-photo.photo-d {
            width: 96px;
            height: 92px;
            right: 16px;
        }

        .opportunity-card h4 {
            font-size: 1.25rem;
        }

        .life-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .life-item,
        .life-item:nth-child(1),
        .life-item:nth-child(2),
        .life-item:nth-child(3),
        .life-item:nth-child(4),
        .life-item:nth-child(5) {
            grid-column: span 1;
            min-height: 130px;
        }

        .life-tag {
            font-size: 1.2rem;
        }

        .newsletter-form {
            flex-direction: column;
            width: 100%;
            max-width: 460px;
            gap: 12px;
        }

        .newsletter-input,
        .newsletter-btn {
            width: 100%;
        }
    }

    @media (min-width: 992px) {

        .bg-light.py-4,
        .feedback-section,
        .newsletter-section,
        footer {
            padding-left: 80px;
            padding-right: 80px;
        }

        .newsletter-section {
            min-height: 380px;
        }
    }

    @media (max-width: 768px) {
        .newsletter-section .d-flex {
            flex-direction: column;
            max-width: 460px;
            gap: 12px;
        }

        .newsletter-input,
        .newsletter-btn,
        .newsletter-section button {
            width: 100% !important;
        }

        .site-footer {
            padding-top: 46px;
            padding-bottom: 20px;
        }
    }

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
                    <a href="#" class="text-dark"><i class="fas fa-bell fa-lg"></i></a>
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
                    <li class="nav-item"><a class="nav-link" href="trangchu.php">Sản phẩm</a></li>
                    <li class="nav-item"><a class="nav-link" href="tin-tuc.php">Tin tức</a></li>
                    <li class="nav-item"><a class="nav-link active" href="tuyen-dung.php">Tuyển dụng</a></li>
                    <li class="nav-item"><a class="nav-link" href="chuyen-nhuong.php">Chuyển nhượng</a></li>
                </ul>
                <div class="delivery-notice d-none d-md-block">
                    <i class="fas fa-truck-fast me-1"></i> Miễn phí giao hàng tại Đồng Tháp
                </div>
            </div>
        </div>
    </header>

    <main class="career-wrap">
        <section class="career-hero">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title">Best Place To<br>Build Yourself</h1>
                    <button type="button" class="hero-apply-btn">Apply now</button>

                    <div class="hero-stats">
                        <div class="hero-stat-item">
                            <strong class="hero-stat-number" data-target="300" data-suffix=" +">1 +</strong>
                            <span>Cửa hàng</span>
                        </div>
                        <div class="hero-stat-item">
                            <strong class="hero-stat-number" data-target="3000" data-suffix=" +">1 +</strong>
                            <span>Nhân viên</span>
                        </div>
                        <div class="hero-stat-item">
                            <strong class="hero-stat-number" data-target="30000" data-suffix=" +">1 +</strong>
                            <span>Ứng viên/năm</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-collage">
                        <img class="hero-main-photo" src="../AnhTrangChu/sale.png" alt="Career Main">
                        <img class="hero-floating-photo photo-a" src="../AnhTrangChu/muctet.png" alt="Store">
                        <img class="hero-floating-photo photo-b" src="../AnhTrangChu/drthanh.png" alt="Office">
                        <img class="hero-floating-photo photo-c" src="../TrangUser/chtl.png" alt="Team">
                        <img class="hero-floating-photo photo-d" src="../TrangUser/ack.png" alt="Brand">
                        <i class="fas fa-star star-deco star-1"></i>
                        <i class="fas fa-star star-deco star-2"></i>
                    </div>
                </div>
            </div>
        </section>

        <section class="career-section-card">
            <h3 class="section-title">CƠ HỘI NGHỀ NGHIỆP TẠI ACK</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <article class="opportunity-card store">
                        <h4>Khối cửa hàng</h4>
                        <p>Với hệ thống hơn 300 cửa hàng, ACK Việt Nam đã phủ rộng khắp các tỉnh thành phía Nam: TP. Hồ
                            Chí Minh, Đồng Nai, Bình Dương, Bà Rịa - Vũng Tàu, Tiền Giang, Cần Thơ. ACK hứa hẹn là điểm
                            đến cơ hội nghề nghiệp hấp dẫn cho các bạn trẻ.</p>
                        <button type="button" class="apply-small-btn">Ứng tuyển ngay</button>
                    </article>
                </div>
                <div class="col-md-6">
                    <article class="opportunity-card office">
                        <h4>Khối văn phòng</h4>
                        <p>Với văn hóa 4F: Friendly - Fresh - Fun - Fair, ACK Việt Nam mong muốn đem lại không gian làm
                            việc tích cực với môi trường học hỏi và phát triển không giới hạn dành cho đội ngũ nhân sự
                            trẻ, năng động.</p>
                        <button type="button" class="apply-small-btn">Ứng tuyển ngay</button>
                    </article>
                </div>
            </div>
        </section>

        <section class="career-section-card">
            <h3 class="section-title">Life at ACK</h3>
            <div class="life-grid">
                <div class="life-item">
                    <img src="../TrangUser/messi.png" alt="Friendly">
                    <span class="life-tag">Friendly</span>
                </div>
                <div class="life-item">
                    <img src="../TrangUser/kiet.png" alt="Fresh">
                    <span class="life-tag">Fresh</span>
                </div>
                <div class="life-item">
                    <img src="../TrangUser/anlecho.png" alt="Fair">
                    <span class="life-tag">Fair</span>
                </div>
                <div class="life-item">
                    <img src="../TrangUser/chtl.png" alt="Fun">
                    <span class="life-tag">Fun</span>
                </div>
                <div class="life-item">
                    <img src="../AnhTrangChu/muctet.png" alt="Fair store">
                    <span class="life-tag">Fair</span>
                </div>
            </div>
        </section>

    </main>

    <div class="bg-light py-4">
        <div class="container">
            <div class="row text-center">
                <div class="col-3"><i class="fas fa-truck fa-2x text-primary mb-2"></i><br><b>Giao hàng nhanh</b></div>
                <div class="col-3"><i class="fas fa-shield-alt fa-2x text-primary mb-2"></i><br><b>An toàn bảo mật</b>
                </div>
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
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="feedback-text">"Sản phẩm rất tươi ngon, giao hàng đúng hẹn. Tết này sẽ ủng hộ tiếp!
                            Đóng gói rất cẩn thận."</p>
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
                        <div class="feedback-text">"Dịch vụ tuyệt vời. Mua hàng online mà được freeship lại còn được quà
                            tặng kèm."</div>
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
        <div class="newsletter-content">
            <i class="far fa-envelope newsletter-icon" aria-hidden="true"></i>
            <h3 class="newsletter-title">Đăng kí nhận khuyến mãi</h3>
            <p class="newsletter-subtitle">Nhận thông tin về các sản phẩm mới và các ưu đãi đặc biệt</p>
            <div class="newsletter-form">
                <input type="email" class="newsletter-input" placeholder="Email của bạn...">
                <button type="button" class="newsletter-btn">Đăng ký</button>
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div class="footer-grid">
            <div>
                <div class="d-flex align-items-center mb-3">
                    <img src="../TrangUser/ack.png" alt="ACK Logo"
                        style="height: 40px; filter: brightness(0) invert(1);"> <span
                        class="ms-2 fw-bold fs-4 text-white">ACK Mart</span>
                </div>
                <p class="text-muted">Nơi mua sắm tin cậy cho mọi nhà. Cam kết chất lượng, giá cả bình ổn và dịch vụ tận
                    tâm.</p>
                <div class="social-icons mt-3">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>

            <div>
                <h5>Về ACK</h5>
                <a href="#">Giới thiệu</a>
                <a href="tuyen-dung.php">Tuyển dụng</a>
                <a href="tin-tuc.php">Tin tức &amp; Sự kiện</a>
                <a href="#">Hệ thống cửa hàng</a>
            </div>

            <div>
                <h5>Hỗ trợ khách hàng</h5>
                <a href="#">Chính sách đổi trả</a>
                <a href="#">Chính sách giao hàng</a>
                <a href="#">Phương thức thanh toán</a>
                <a href="#">Câu hỏi thường gặp (FAQ)</a>
            </div>

            <div>
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

        <div class="copyright-border">
            <p class="m-0">© 2025 ACK Mart. Tất cả các quyền được bảo lưu.</p>
            <div>
                <img src="https://upload.wikimedia.org/wikipedia/commons/0/04/Visa.svg" height="20"
                    class="me-2 bg-white rounded p-1" alt="Visa">
                <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" height="20"
                    class="me-2 bg-white rounded p-1" alt="Mastercard">
                <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" height="20"
                    class="bg-white rounded p-1" alt="Paypal">
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="web-events.js?v=20260414-3"></script>
    <script>
    (function() {
        const counters = Array.from(document.querySelectorAll('.hero-stat-number[data-target]'));
        if (!counters.length) return;

        const runCounter = (el) => {
            if (!el || el.dataset.counted === '1') return;

            const target = Number.parseInt(String(el.dataset.target || '0'), 10);
            const suffix = String(el.dataset.suffix || '');
            const start = Number.parseInt(String(el.dataset.start || '1'), 10) || 1;
            const duration = 2000;

            if (!Number.isFinite(target) || target <= start) {
                el.textContent = `${target}${suffix}`;
                el.dataset.counted = '1';
                return;
            }

            const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);
            let startTime = null;

            const tick = (timestamp) => {
                if (startTime === null) startTime = timestamp;
                const elapsed = timestamp - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased = easeOutCubic(progress);
                const value = Math.floor(start + (target - start) * eased);
                el.textContent = `${value}${suffix}`;

                if (progress < 1) {
                    requestAnimationFrame(tick);
                    return;
                }

                el.textContent = `${target}${suffix}`;
                el.dataset.counted = '1';
            };

            requestAnimationFrame(tick);
        };

        if (!('IntersectionObserver' in window)) {
            counters.forEach(runCounter);
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                runCounter(entry.target);
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.35
        });

        counters.forEach((counter) => observer.observe(counter));
    })();
    </script>
</body>

</html>