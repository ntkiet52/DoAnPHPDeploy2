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
    <title>Nhượng quyền - ACK Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --primary-blue: #007bff;
        --bg-gray: #f5f7fb;
        --ack-purple: #5a60f0;
        --ack-deep: #2f2d79;
        --ack-orange: #ff6d00;
        --ack-yellow: #f7ad00;
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

    .franchise-wrap {
        max-width: 1100px;
        margin: 20px auto 40px;
        padding: 0 12px;
        background: transparent;
    }

    .franchise-head {
        background: #cfd6e8;
        border-top: 2px solid #5368ff;
        border-bottom: 1px solid #d7deee;
        padding: 24px 16px;
        border-radius: 16px;
        margin-bottom: 14px;
    }

    .franchise-title {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 16px;
        color: #3456b8;
        font-weight: 800;
        font-size: clamp(1.8rem, 2.8vw, 2.8rem);
        line-height: 1.1;
        margin: 0;
    }

    .franchise-title i {
        color: var(--ack-purple);
    }

    .franchise-tabs {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 14px;
    }

    .franchise-tab {
        min-height: 146px;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-weight: 700;
        font-size: clamp(1rem, 1.35vw, 1.45rem);
        line-height: 1.25;
        border-right: 1px solid rgba(255, 255, 255, 0.7);
    }

    .franchise-tab:last-child {
        border-right: none;
    }

    .franchise-tab.is-active {
        background: var(--ack-orange);
    }

    .franchise-tab.is-normal {
        background: var(--ack-yellow);
    }

    .franchise-block {
        min-height: 360px;
        display: flex;
        align-items: center;
        background-size: cover;
        background-position: center;
        position: relative;
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 14px;
    }

    .franchise-block::before {
        content: '';
        position: absolute;
        inset: 0;
    }

    .franchise-block.light::before {
        background: rgba(255, 255, 255, 0.82);
    }

    .franchise-block.dark::before {
        background: rgba(43, 40, 122, 0.86);
    }

    .franchise-content {
        position: relative;
        z-index: 2;
        width: 100%;
        margin: 0 auto;
        padding: 46px 34px;
    }

    .franchise-copy {
        max-width: 780px;
    }

    .franchise-copy.right {
        margin-left: auto;
    }

    .franchise-copy h2 {
        margin: 0 0 18px;
        color: var(--ack-purple);
        font-size: clamp(1.45rem, 2.2vw, 2.25rem);
        line-height: 1.18;
        font-weight: 800;
    }

    .franchise-block.dark .franchise-copy h2 {
        color: #fff;
    }

    .franchise-copy p {
        margin: 0;
        font-size: clamp(0.98rem, 1.05vw, 1.2rem);
        line-height: 1.65;
        color: #111;
    }

    .franchise-block.dark .franchise-copy p {
        color: #fff;
    }

    .franchise-more {
        display: inline-block;
        margin-top: 22px;
        color: #000;
        font-weight: 700;
        font-size: clamp(1.05rem, 1.15vw, 1.28rem);
        text-decoration: none;
    }

    .franchise-more:hover {
        text-decoration: underline;
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
        .franchise-tabs {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .franchise-tab {
            min-height: 108px;
            font-size: 1rem;
            border-right: 1px solid rgba(255, 255, 255, 0.7);
            border-bottom: 1px solid rgba(255, 255, 255, 0.7);
        }

        .franchise-tab:nth-child(2n) {
            border-right: none;
        }

        .franchise-tab:nth-last-child(-n+2) {
            border-bottom: none;
        }

        .franchise-content {
            padding: 26px 18px;
        }

        .franchise-copy,
        .franchise-copy.right {
            max-width: 100%;
            margin-left: 0;
        }

        .franchise-block {
            min-height: 310px;
        }

        .franchise-copy h2 {
            font-size: 1.5rem;
            margin-bottom: 12px;
        }

        .franchise-copy p {
            font-size: 0.98rem;
            line-height: 1.6;
        }

        .franchise-more {
            font-size: 1rem;
            margin-top: 14px;
        }

        .franchise-wrap {
            margin-top: 14px;
            margin-bottom: 26px;
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

    .ack-notice-wrap {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 3000;
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: min(420px, calc(100vw - 24px));
        pointer-events: none;
    }

    .ack-notice-item {
        pointer-events: auto;
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 14px;
        font-weight: 600;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        border: 1px solid;
        text-align: center;
    }

    .ack-notice-item.success {
        background: #dff7e6;
        color: #1a7f37;
        border-color: #b7ebc6;
    }

    .ack-notice-item.error {
        background: #fff1f1;
        color: #b42318;
        border-color: #ffd4d4;
    }

    .ack-loader-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.35);
        backdrop-filter: blur(2px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 4000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    .ack-loader-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .ack-loader-box {
        background: #ffffff;
        padding: 18px 22px;
        border-radius: 16px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.18);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .ack-loader-spinner {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 4px solid #e5e7eb;
        border-top-color: #4f46e5;
        animation: ack-spin 0.8s linear infinite;
    }

    .ack-loader-text {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
    }

    @keyframes ack-spin {
        to {
            transform: rotate(360deg);
        }
    }
    </style>
</head>

<body>
    <?php $newsletterStatus = trim((string) ($_GET['newsletter'] ?? '')); ?>
    <?php if ($newsletterStatus !== ''): ?>
    <div class="ack-notice-wrap">
        <?php if ($newsletterStatus === 'sent'): ?>
        <div class="ack-notice-item success">Đã gửi thông tin về email của bạn. Cảm ơn bạn!</div>
        <?php elseif ($newsletterStatus === 'invalid'): ?>
        <div class="ack-notice-item error">Email không hợp lệ. Vui lòng kiểm tra lại.</div>
        <?php else: ?>
        <div class="ack-notice-item error">Không gửi được. Vui lòng thử lại sau.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="ack-loader-overlay" id="ack-loader">
        <div class="ack-loader-box">
            <div class="ack-loader-spinner"></div>
            <div class="ack-loader-text">Đang gửi...</div>
        </div>
    </div>
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
                    <li class="nav-item"><a class="nav-link" href="tuyen-dung.php">Tuyển dụng</a></li>
                    <li class="nav-item"><a class="nav-link active" href="chuyen-nhuong.php">Chuyển nhượng</a></li>
                </ul>
                <div class="delivery-notice d-none d-md-block">
                    <i class="fas fa-truck-fast me-1"></i> Miễn phí giao hàng tại Đồng Tháp
                </div>
            </div>
        </div>
    </header>

    <main class="franchise-wrap">
        <section class="franchise-head">
            <h1 class="franchise-title">
                <i class="fas fa-store-alt"></i>
                <span>Nhượng quyền</span>
            </h1>
        </section>

        <section class="franchise-tabs" aria-label="Danh mục nhượng quyền">
            <div class="franchise-tab is-active">Thông tin<br>chung</div>
            <div class="franchise-tab is-normal">Cải tiến<br>Sáng tạo</div>
            <div class="franchise-tab is-normal">Nhượng quyền<br>thương hiệu</div>
            <div class="franchise-tab is-normal">Mô hình<br>nhượng quyền</div>
        </section>

        <section class="franchise-block light" style="background-image: url('../AnhTrangChu/sale.png');">
            <div class="franchise-content">
                <div class="franchise-copy">
                    <h2>Nhượng quyền là gì và tại sao nên nhượng quyền?</h2>
                    <p>Nhượng quyền là một hoạt động thương mại, theo đó bên nhượng quyền sẽ trao quyền và hỗ trợ bên
                        nhận nhượng quyền để bán hàng hóa, cung cấp dịch vụ theo nhãn hiệu, hệ thống hay là phương thức
                        được xác định bởi bên nhượng quyền trong một khoảng thời gian và địa điểm nhất định.</p>
                    <a class="franchise-more" href="#">Xem thêm</a>
                </div>
            </div>
        </section>

        <section class="franchise-block dark" style="background-image: url('../AnhTrangChu/drthanh.png');">
            <div class="franchise-content">
                <div class="franchise-copy">
                    <h2>Điều khoản chung của nhượng quyền</h2>
                    <p>Hình thức kinh doanh nhượng quyền: loại nhượng quyền này bao gồm không chỉ một sản phẩm, dịch vụ
                        và nhãn hiệu, mà còn là phương thức hoàn chỉnh để tự điều hành kinh doanh, ví dụ như kế hoạch
                        marketing và hướng dẫn vận hành.</p>
                    <a class="franchise-more" href="#">Xem thêm</a>
                </div>
            </div>
        </section>

        <section class="franchise-block light" style="background-image: url('../AnhTrangChu/muctet.png');">
            <div class="franchise-content">
                <div class="franchise-copy right">
                    <h2>Câu chuyện thành công của ACK</h2>
                    <p>Mỗi cửa hàng là một cầu nối cung cấp tất cả hàng hóa thiết yếu, dịch vụ tiện ích và đặc biệt là
                        địa điểm an toàn cho khách hàng dừng chân hoặc mua sắm không chỉ vào những khung giờ đơn thuần
                        mà tại ACK còn phục vụ khách hàng của mình 24/7.</p>
                    <a class="franchise-more" href="#">Xem thêm</a>
                </div>
            </div>
        </section>

        <section class="franchise-block dark" style="background-image: url('../AnhTrangChu/sale.png');">
            <div class="franchise-content">
                <div class="franchise-copy right">
                    <h2>Quảng cáo &amp; truyền thông Marketing</h2>
                    <p>Bằng phương thức quảng cáo và truyền thông, ACK ngày càng được biết đến và trở thành thương hiệu
                        cửa hàng tiện lợi được khách hàng ưa chuộng. Với tư cách là một người nhận nhượng quyền, bạn
                        thừa hưởng được tất cả những thành tựu của các chiến dịch quảng cáo và chiến lược Marketing độc
                        đáo của ACK.</p>
                    <a class="franchise-more" href="#">Xem thêm</a>
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
            <form class="newsletter-form" id="newsletter-form" method="post" action="newsletter-submit.php">
                <input type="email" name="email" class="newsletter-input" placeholder="Email của bạn..." required>
                <button type="submit" class="newsletter-btn">Đăng ký</button>
            </form>
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
    document.querySelectorAll('.ack-notice-item').forEach((node) => {
        window.setTimeout(() => {
            node.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
            node.style.opacity = '0';
            node.style.transform = 'translateY(-6px)';
            window.setTimeout(() => node.remove(), 220);
        }, 3200);
    });

    const createNoticeWrap = () => {
        const wrap = document.createElement('div');
        wrap.className = 'ack-notice-wrap';
        document.body.appendChild(wrap);
        return wrap;
    };

    const showToast = (message, isError = false) => {
        const wrap = document.querySelector('.ack-notice-wrap') || createNoticeWrap();
        const item = document.createElement('div');
        item.className = `ack-notice-item ${isError ? 'error' : 'success'}`;
        item.textContent = message;
        wrap.appendChild(item);

        window.setTimeout(() => {
            item.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
            item.style.opacity = '0';
            item.style.transform = 'translateY(-6px)';
            window.setTimeout(() => item.remove(), 220);
        }, 3200);
    };

    const newsletterForm = document.getElementById('newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const emailInput = newsletterForm.querySelector('input[name="email"]');
            const submitBtn = newsletterForm.querySelector('button[type="submit"]');
            if (!emailInput || !submitBtn) return;

            const email = emailInput.value.trim();
            if (!email) {
                showToast('Vui lòng nhập email.', true);
                return;
            }

            submitBtn.disabled = true;
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Đang gửi...';
            const loader = document.getElementById('ack-loader');
            if (loader) {
                loader.classList.add('active');
            }

            try {
                const response = await fetch(newsletterForm.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        email
                    })
                });

                const data = await response.json();
                if (data.success) {
                    showToast(data.message || 'Đã gửi thông tin về email của bạn. Cảm ơn bạn!');
                    emailInput.value = '';
                } else {
                    showToast(data.message || 'Không gửi được. Vui lòng thử lại sau.', true);
                }
            } catch (error) {
                showToast('Không gửi được. Vui lòng thử lại sau.', true);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                if (loader) {
                    loader.classList.remove('active');
                }
            }
        });
    }
    </script>
</body>

</html>