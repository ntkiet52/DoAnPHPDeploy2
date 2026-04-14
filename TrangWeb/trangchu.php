<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/catalog_data.php';

$isLoggedIn = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
$currentUserName = trim((string) ($_SESSION['user_name'] ?? 'Khách hàng'));
$currentUserEmail = trim((string) ($_SESSION['user_email'] ?? ''));
$currentUserRole = strtolower(trim((string) ($_SESSION['user_role'] ?? 'guest')));

$roleLabelMap = [
    'admin' => 'Quản trị viên',
    'khachhang' => 'Khách hàng',
    'user' => 'Người dùng',
    'guest' => 'Khách',
];

$currentUserRoleLabel = $roleLabelMap[$currentUserRole] ?? 'Người dùng';
$sessionEmailLower = strtolower(trim((string) ($_SESSION['user_email'] ?? '')));
$hasAdminAccessSession = !empty($_SESSION['is_admin']) && (!empty($_SESSION['admin_created_account']) || $sessionEmailLower === 'admin@gmail.com');
$isAdminUser = $isLoggedIn && $currentUserRole === 'admin' && $hasAdminAccessSession;

$homeThemeDefaults = [
    'pageBg' => '#f5f5f5',
    'sectionBg' => '#e0f2fe',
    'navBg' => '#007bff',
];

$homeTextDefaults = [
    'home_product_heading' => 'Sản phẩm',
    'section_soft_drink_title' => 'Nước ngọt tết sale 10%',
    'section_beer_title' => 'Bia tết sale 10%',
    'section_gift_title' => 'Đồ biếu tết sale 10%',
    'section_market_title' => 'Đi chợ ngày tết',
    'countdown_day_label' => 'ngày',
    'newsletter_title' => 'Đăng kí nhận khuyến mãi',
    'newsletter_subtitle' => 'Nhận thông tin về các sản phẩm mới và các ưu đãi đặc biệt',
    'newsletter_button' => 'Đăng ký',
];

$homeThemeConfig = $homeThemeDefaults;
$homeTextConfig = $homeTextDefaults;

try {
    $homeConfigPdo = new PDO(
        'mysql:host=127.0.0.1;dbname=qlhethongbanhangmini;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $homeConfigPdo->exec(
        "CREATE TABLE IF NOT EXISTS caidat_trangchu (
            id TINYINT(1) NOT NULL,
            theme_config LONGTEXT NULL,
            text_config LONGTEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    if (isset($_GET['admin_home_config_api'])) {
        header('Content-Type: application/json; charset=utf-8');

        if (!$isAdminUser) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'message' => 'Bạn không có quyền chỉnh sửa cấu hình trang chủ.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $rawBody = file_get_contents('php://input');
        $payload = json_decode((string) $rawBody, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $action = trim((string) ($payload['action'] ?? 'save'));

        if ($action === 'reset') {
            $themeToSave = $homeThemeDefaults;
            $textToSave = $homeTextDefaults;
        } else {
            $incomingTheme = is_array($payload['theme'] ?? null) ? $payload['theme'] : [];
            $incomingText = is_array($payload['text'] ?? null) ? $payload['text'] : [];

            $themeToSave = $homeThemeDefaults;
            foreach ($homeThemeDefaults as $key => $defaultValue) {
                if (isset($incomingTheme[$key])) {
                    $themeToSave[$key] = trim((string) $incomingTheme[$key]);
                }
            }

            $textToSave = $homeTextDefaults;
            foreach ($homeTextDefaults as $key => $defaultValue) {
                if (isset($incomingText[$key])) {
                    $textToSave[$key] = mb_substr(trim((string) $incomingText[$key]), 0, 200);
                }
            }
        }

        $upsert = $homeConfigPdo->prepare(
            "INSERT INTO caidat_trangchu (id, theme_config, text_config)
             VALUES (1, :theme_config, :text_config)
             ON DUPLICATE KEY UPDATE
                theme_config = VALUES(theme_config),
                text_config = VALUES(text_config)"
        );
        $upsert->execute([
            ':theme_config' => json_encode($themeToSave, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':text_config' => json_encode($textToSave, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        echo json_encode([
            'ok' => true,
            'theme' => $themeToSave,
            'text' => $textToSave,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $storedConfig = $homeConfigPdo->query('SELECT theme_config, text_config FROM caidat_trangchu WHERE id = 1 LIMIT 1')->fetch();
    if ($storedConfig) {
        $decodedTheme = json_decode((string) ($storedConfig['theme_config'] ?? ''), true);
        if (is_array($decodedTheme)) {
            foreach ($homeThemeDefaults as $key => $defaultValue) {
                if (isset($decodedTheme[$key])) {
                    $homeThemeConfig[$key] = (string) $decodedTheme[$key];
                }
            }
        }

        $decodedText = json_decode((string) ($storedConfig['text_config'] ?? ''), true);
        if (is_array($decodedText)) {
            foreach ($homeTextDefaults as $key => $defaultValue) {
                if (isset($decodedText[$key])) {
                    $homeTextConfig[$key] = (string) $decodedText[$key];
                }
            }
        }
    }
} catch (Throwable $ignored) {
    if (isset($_GET['admin_home_config_api'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Không thể lưu cài đặt trang chủ lúc này.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($currentUserName === '') {
    $currentUserName = 'Khách hàng';
}

$displayEmail = $currentUserEmail !== '' ? $currentUserEmail : 'Chưa cập nhật email';

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
        /* Màu nền xanh nhạt cho các block */
        --header-blue: #f8f9fa;
        --text-red: #dc3545;
        --page-bg-custom: #f5f5f5;
        --section-bg-custom: #e0f2fe;
        --main-nav-custom: #007bff;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        width: 100%;
        background-color: var(--page-bg-custom);
    }

    body {
        font-family: 'Roboto', sans-serif;
        background-color: var(--page-bg-custom);
        min-height: 100vh;
    }

    /* HEADER */
    .top-bar {
        background-color: #fff;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }

    .ack-logo {
        height: 40px;
        /* chỉnh 32–48px tùy bạn */
        width: auto;
        /* giữ đúng tỉ lệ */
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

    .user-menu-toggle {
        border: none;
        background: transparent;
        padding: 0;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .user-menu-toggle::after {
        display: none;
    }

    .user-avatar-chip {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #fff5d6;
        color: #f59f00;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #ffe8a1;
        font-size: 1.2rem;
    }

    .user-mini-info {
        line-height: 1.1;
        text-align: left;
    }

    .user-mini-name {
        max-width: 140px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 600;
        font-size: 0.86rem;
        color: #111827;
    }

    .user-mini-role {
        font-size: 0.72rem;
        color: #6b7280;
    }

    .user-dropdown-menu {
        min-width: 280px;
        border-radius: 12px;
        border: 1px solid #edf2f7;
        padding-top: 0;
        overflow: hidden;
    }

    .user-dropdown-head {
        background: #f8fbff;
        padding: 12px 14px;
        border-bottom: 1px solid #e9eef5;
    }

    .user-dropdown-head .name {
        font-weight: 700;
        color: #0f172a;
    }

    .user-dropdown-head .email {
        font-size: 0.82rem;
        color: #475569;
    }

    .user-dropdown-head .role {
        font-size: 0.75rem;
        color: #0b74e5;
        font-weight: 600;
    }

    .user-dropdown-menu .dropdown-item {
        padding: 10px 14px;
        font-size: 0.92rem;
    }

    .user-dropdown-menu .dropdown-item i {
        width: 18px;
    }

    .main-nav {
        background-color: var(--main-nav-custom);
        color: white;
        padding: 0;
    }

    .main-nav .nav-link {
        color: white;
        padding: 10px 20px;
        font-weight: 500;
    }

    .main-nav .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.2);
    }

    .delivery-notice {
        font-size: 0.9rem;
        font-style: italic;
    }

    /* SIDEBAR DANH MỤC */
    .sidebar-container {
        background: #fff;
        border-radius: 12px;
        padding: 15px;
        /* Hiệu ứng trượt theo khi cuộn chuột */
        position: sticky;
        top: 20px;
        z-index: 90;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        border: 1px solid #edf2f7;
    }

    .sidebar-title {
        font-weight: 700;
        margin-bottom: 15px;
        font-size: 1.1rem;
        padding-bottom: 10px;
        border-bottom: 2px solid #f1f1f1;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .cat-item {
        display: flex;
        align-items: center;
        padding: 10px;
        border-radius: 8px;
        color: #4a4a4a;
        text-decoration: none;
        transition: all 0.2s;
        margin-bottom: 2px;
    }

    .cat-item:hover {
        background-color: var(--light-bg-blue);
        color: var(--primary-blue);
        font-weight: 500;
        transform: translateX(5px);
    }

    .cat-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        /* Làm tròn icon như yêu cầu */
        object-fit: cover;
        margin-right: 12px;
        background: #fff;
        border: 1px solid #eee;
        padding: 2px;
    }

    /* BANNER */
    .main-banner {
        margin-top: 15px;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .sale-banner img {
        width: 100%;
        border-radius: 10px;
        margin: 15px 0;
    }

    /* SECTIONS & PRODUCT CARDS */
    .section-container {
        background-color: var(--section-bg-custom);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 30px;
        border: 1px solid #cce5ff;
    }

    .admin-home-toolbar {
        background: #fffbe8;
        border: 1px solid #ffe58f;
        border-radius: 12px;
        padding: 12px 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .admin-theme-controls {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .admin-theme-controls label {
        font-size: 0.82rem;
        color: #334155;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .admin-theme-controls input[type="color"] {
        width: 36px;
        height: 28px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 0;
        background: transparent;
        cursor: pointer;
    }

    .admin-text-editor {
        width: 100%;
        background: #fff;
        border: 1px dashed #f5c35c;
        border-radius: 10px;
        padding: 10px 12px;
    }

    .admin-text-editor summary {
        cursor: pointer;
        font-weight: 700;
        color: #92400e;
    }

    .admin-text-grid {
        margin-top: 10px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 10px;
    }

    .admin-text-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .admin-text-field label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
    }

    .admin-text-field input {
        border: 1px solid #d1d5db;
        border-radius: 8px;
        min-height: 34px;
        padding: 6px 10px;
        font-size: 0.9rem;
        color: #1f2937;
    }

    .admin-text-actions {
        margin-top: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .admin-text-hint {
        font-size: 0.78rem;
        color: #64748b;
    }

    .admin-edit-btn {
        position: absolute;
        top: 8px;
        left: 8px;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 1px solid #fff;
        background: #0b74e5;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        z-index: 2;
        box-shadow: 0 4px 8px rgba(11, 116, 229, 0.3);
    }

    .admin-edit-btn:hover {
        background: #095cb6;
        color: #fff;
    }

    .section-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-red);
        margin-right: 15px;
        margin-bottom: 0;
    }

    /* Countdown Timer */
    .countdown-timer {
        display: flex;
        gap: 5px;
        align-items: center;
        font-weight: bold;
    }

    .time-box {
        background: white;
        color: var(--text-red);
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid var(--text-red);
        min-width: 25px;
        text-align: center;
    }

    .time-label {
        color: var(--text-red);
        font-weight: bold;
    }

    /* Product Card */
    .product-card {
        background: white;
        border-radius: 10px;
        border: 1px solid #eee;
        padding: 10px;
        height: 100%;
        position: relative;
        transition: transform 0.2s;
        cursor: pointer;
    }

    .product-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .badge-sale {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #ffc107;
        font-weight: bold;
        font-size: 0.8rem;
        padding: 3px 8px;
        border-radius: 10px;
        z-index: 1;
    }

    .card-img-wrapper {
        position: relative;
        overflow: hidden;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .product-img {
        width: 100%;
        height: 200px;
        /* <--- Giảm số này xuống */
        object-fit: contain;
        /* Đổi thành 'contain' để ảnh ko bị cắt mất chữ, hoặc giữ 'cover' nếu muốn ảnh full khung */
        padding: 10px;
        /* Thêm chút đệm cho ảnh nó lọt thỏm vào trong, nhìn sang hơn */
    }

    .product-title {
        font-size: 0.95rem;
        font-weight: 500;
        height: 40px;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        margin-bottom: 5px;
    }

    .price-current {
        color: #d0021b;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .price-old {
        font-size: 0.85rem;
        text-decoration: line-through;
        color: #999;
        margin-left: 5px;
    }

    .btn-add {
        position: absolute;
        bottom: 10px;
        right: 10px;
        background: var(--primary-blue);
        color: white;
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-add:hover {
        background: #0056b3;
    }

    /* Footer Reuse */
    /* --- FEEDBACK SECTION --- */
    .feedback-section {
        background-color: #f9fafb;
        /* Nền xám rất nhạt */
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

    @media (min-width: 992px) {

        .bg-light.py-4,
        .feedback-section,
        .newsletter-section,
        .site-footer {
            padding-left: 80px;
            padding-right: 80px;
        }

        .newsletter-section {
            min-height: 380px;
        }
    }

    @media (max-width: 768px) {
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

        .site-footer {
            padding-top: 46px;
            padding-bottom: 20px;
        }
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

    /* AI Chatbot Widget */
    #chatbot-button {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border: none;
        color: white;
        font-size: 28px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 998;
        transition: all 0.3s ease;
    }

    #chatbot-button:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 18px rgba(0, 123, 255, 0.6);
    }

    #chatbot-button.active {
        bottom: 390px;
    }

    #chatbot-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 380px;
        height: 500px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 40px rgba(0, 0, 0, 0.16);
        display: none;
        flex-direction: column;
        z-index: 999;
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    #chatbot-widget.active {
        display: flex;
    }

    .chatbot-header {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        padding: 15px;
        border-radius: 15px 15px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f0f0f0;
    }

    .chatbot-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }

    .chatbot-close {
        background: none;
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chatbot-messages {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
        background: #f9f9f9;
    }

    .chatbot-message {
        margin-bottom: 10px;
        display: flex;
        animation: fadeIn .3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .chatbot-message.user {
        justify-content: flex-end;
    }

    .chatbot-message.ai {
        justify-content: flex-start;
    }

    .chatbot-bubble {
        max-width: 70%;
        padding: 10px 15px;
        border-radius: 10px;
        word-wrap: break-word;
        line-height: 1.4;
        font-size: 14px;
        white-space: pre-line;
    }

    .chatbot-message.user .chatbot-bubble {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        border-bottom-right-radius: 2px;
    }

    .chatbot-message.ai .chatbot-bubble {
        background: #e9ecef;
        color: #333;
        border-bottom-left-radius: 2px;
    }

    .chatbot-input-area {
        padding: 15px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        gap: 10px;
        background: white;
        border-radius: 0 0 15px 15px;
    }

    .chatbot-input-area input {
        flex: 1;
        border: 1px solid #ddd;
        border-radius: 20px;
        padding: 10px 15px;
        font-size: 14px;
        outline: none;
        transition: border-color .2s;
    }

    .chatbot-input-area input:focus {
        border-color: #007bff;
    }

    .chatbot-send-btn {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border: none;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        transition: transform .2s;
    }

    .chatbot-send-btn:hover {
        transform: scale(1.05);
    }

    .chatbot-send-btn:active {
        transform: scale(0.95);
    }

    @media (max-width: 480px) {
        #chatbot-widget {
            width: calc(100% - 10px);
            height: 60vh;
            bottom: 10px;
            right: 5px;
        }

        #chatbot-button {
            width: 50px;
            height: 50px;
            font-size: 24px;
        }
    }
    </style>
</head>

<body>

    <header class="sticky-top bg-white">
        <div class="top-bar">
            <div class="container d-flex align-items-center justify-content-between">
                <a href="#" class="d-flex align-items-center text-decoration-none me-3">
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

                    <div class="dropdown">
                        <button class="dropdown-toggle user-menu-toggle" type="button" id="userMenuDropdown"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="user-avatar-chip"><i class="fas fa-user"></i></span>
                            <span class="d-none d-md-block user-mini-info">
                                <span
                                    class="d-block user-mini-name"><?php echo htmlspecialchars($currentUserName); ?></span>
                                <span
                                    class="d-block user-mini-role"><?php echo htmlspecialchars($currentUserRoleLabel); ?></span>
                            </span>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu shadow"
                            aria-labelledby="userMenuDropdown">
                            <li class="user-dropdown-head">
                                <div class="name"><?php echo htmlspecialchars($currentUserName); ?></div>
                                <div class="email"><?php echo htmlspecialchars($displayEmail); ?></div>
                                <div class="role"><?php echo htmlspecialchars($currentUserRoleLabel); ?></div>
                            </li>

                            <?php if ($isLoggedIn): ?>
                            <li><a class="dropdown-item" href="tai-khoan.php?tab=info"><i
                                        class="fas fa-id-badge me-2 text-primary"></i>Thông tin tài khoản</a></li>
                            <li><a class="dropdown-item" href="tai-khoan.php?tab=manage"><i
                                        class="fas fa-briefcase me-2 text-primary"></i>Quản lý tài khoản</a></li>
                            <li><a class="dropdown-item" href="don-hang-cua-toi.php"><i
                                        class="fas fa-receipt me-2 text-primary"></i>Đơn hàng của tôi</a></li>
                            <li><a class="dropdown-item" href="tai-khoan.php?tab=settings"><i
                                        class="fas fa-gear me-2 text-primary"></i>Cài đặt tài khoản</a></li>
                            <li>
                                <hr class="dropdown-divider my-1">
                            </li>
                            <li><a class="dropdown-item text-danger" href="../Login/logout.php"><i
                                        class="fas fa-right-from-bracket me-2"></i>Đăng xuất</a></li>
                            <?php else: ?>
                            <li><a class="dropdown-item" href="../Login/Dangnhap.php"><i
                                        class="fas fa-right-to-bracket me-2 text-primary"></i>Đăng nhập</a></li>
                            <li><a class="dropdown-item" href="../Login/Dangnhap.php"><i
                                        class="fas fa-user-plus me-2 text-primary"></i>Tạo tài khoản mới</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-nav">
            <div class="container d-flex justify-content-between align-items-center">
                <ul class="nav">
                    <li class="nav-item"><a class="nav-link" href="#">Sản phẩm</a></li>
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

    <?php if ($isAdminUser): ?>
    <div class="container mt-3">
        <div class="admin-home-toolbar">
            <div class="fw-semibold text-dark">
                <i class="fas fa-shield-halved text-primary me-2"></i>Chế độ Admin trên trang chủ
            </div>
            <div class="admin-theme-controls">
                <a href="../TrangAdmin/admin.php" class="btn btn-sm btn-outline-primary" id="adminGoDashboardLink"><i
                        class="fas fa-gauge-high me-1"></i>Về Admin</a>
                <a href="../TrangAdmin/admin-sanpham.php?return_to=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/DoAnPHP/TrangWeb/trangchu.php?admin_mode=1'); ?>" class="btn btn-sm btn-primary" id="adminGoProductsLink"><i
                        class="fas fa-pen-to-square me-1"></i>Sửa sản phẩm</a>
                <label>BG trang <input type="color" id="adminPageBgPicker" value="#f5f5f5"></label>
                <label>BG khối <input type="color" id="adminSectionBgPicker" value="#e0f2fe"></label>
                <label>BG menu <input type="color" id="adminNavBgPicker" value="#007bff"></label>
                <button type="button" class="btn btn-sm btn-outline-dark" id="adminResetThemeBtn">Mặc định</button>
            </div>
            <details class="admin-text-editor">
                <summary><i class="fas fa-font me-1"></i>Chỉnh chữ nhanh trên trang chủ</summary>
                <div class="admin-text-grid" id="adminTextEditorFields"></div>
                <div class="admin-text-actions">
                    <button type="button" class="btn btn-sm btn-outline-dark" id="adminResetTextBtn">Khôi phục chữ gốc</button>
                    <span class="admin-text-hint">Gõ tới đâu cập nhật ngay tới đó.</span>
                </div>
            </details>
        </div>
    </div>
    <?php endif; ?>

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
                    <button class="carousel-control-prev" type="button" data-bs-target="#tetCarousel"
                        data-bs-slide="prev">
                        <span class="carousel-control-prev-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#tetCarousel"
                        data-bs-slide="next">
                        <span class="carousel-control-next-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0" data-admin-text-key="home_product_heading">Sản phẩm</h4>
                    <a href="#" class="text-primary text-decoration-none fw-bold">Xem tất cả <i
                            class="fas fa-chevron-right"></i></a>
                </div>

                <div class="sale-banner mb-4">
                    <img src="../anhtrangchu/sale.png">
                </div>

                <div class="section-container">
                    <div class="section-header">
                        <h5 class="section-title" data-admin-text-key="section_soft_drink_title">Nước ngọt tết sale 10%</h5>
                        <div class="countdown-timer" id="timer1">
                            <div class="time-box days">11</div> <span class="time-label" data-admin-text-key="countdown_day_label">ngày</span>
                            <div class="time-box hours">06</div> <span class="time-label">:</span>
                            <div class="time-box minutes">50</div> <span class="time-label">:</span>
                            <div class="time-box seconds">16</div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <?php foreach($soft_drinks as $prod): ?>
                        <div class="col-6 col-md-3">
                            <div class="product-card" role="link" tabindex="0"
                                onclick='window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>'
                                onkeypress='if(event.key === "Enter" || event.key === " "){ event.preventDefault(); window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>; }'>
                                <span class="badge-sale">-10%</span>
                                <div class="card-img-wrapper">
                                    <img src="<?php echo $prod['img']; ?>" class="product-img" alt="Product">
                                </div>
                                <div class="product-title"><?php echo $prod['name']; ?></div>
                                <div class="d-flex align-items-center">
                                    <span class="price-current"><?php echo $prod['price']; ?></span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="price-old"><?php echo $prod['old']; ?></span>
                                    <button class="btn-add" type="button" onclick="event.stopPropagation();"><i
                                            class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section-container">
                    <div class="section-header">
                        <h5 class="section-title" data-admin-text-key="section_beer_title">Bia tết sale 10%</h5>
                        <div class="countdown-timer" id="timer2">
                            <div class="time-box days">11</div> <span class="time-label" data-admin-text-key="countdown_day_label">ngày</span>
                            <div class="time-box hours">06</div> <span class="time-label">:</span>
                            <div class="time-box minutes">50</div> <span class="time-label">:</span>
                            <div class="time-box seconds">16</div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <?php foreach($beers as $prod): ?>
                        <div class="col-6 col-md-3">
                            <div class="product-card" role="link" tabindex="0"
                                onclick='window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>'
                                onkeypress='if(event.key === "Enter" || event.key === " "){ event.preventDefault(); window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>; }'>
                                <span class="badge-sale">-10%</span>
                                <div class="card-img-wrapper">
                                    <img src="<?php echo $prod['img']; ?>" class="product-img" alt="Product">
                                </div>
                                <div class="product-title"><?php echo $prod['name']; ?></div>
                                <div><span class="price-current"><?php echo $prod['price']; ?></span></div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="price-old"><?php echo $prod['old']; ?></span>
                                    <button class="btn-add" type="button" onclick="event.stopPropagation();"><i
                                            class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section-container">
                    <div class="section-header">
                        <h5 class="section-title text-primary" data-admin-text-key="section_gift_title">Đồ biếu tết sale 10%</h5>
                    </div>
                    <div class="row g-3">
                        <?php foreach($gifts as $prod): ?>
                        <div class="col-6 col-md-3">
                            <div class="product-card" role="link" tabindex="0"
                                onclick='window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>'
                                onkeypress='if(event.key === "Enter" || event.key === " "){ event.preventDefault(); window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>; }'>
                                <span class="badge-sale">-10%</span>
                                <div class="card-img-wrapper"><img src="<?php echo $prod['img']; ?>" class="product-img"
                                        alt="Product"></div>
                                <div class="product-title"><?php echo $prod['name']; ?></div>
                                <div><span class="price-current"><?php echo $prod['price']; ?></span></div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="price-old"><?php echo $prod['old']; ?></span>
                                    <button class="btn-add" type="button" onclick="event.stopPropagation();"><i
                                            class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section-container">
                    <div class="section-header">
                        <h5 class="section-title" data-admin-text-key="section_market_title">Đi chợ ngày tết</h5>
                    </div>
                    <div class="row g-3">
                        <?php foreach($fresh_foods as $prod): ?>
                        <div class="col-6 col-md-3">
                            <div class="product-card" role="link" tabindex="0"
                                onclick='window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>'
                                onkeypress='if(event.key === "Enter" || event.key === " "){ event.preventDefault(); window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>; }'>
                                <span class="badge-sale">-15%</span>
                                <div class="card-img-wrapper"><img src="<?php echo $prod['img']; ?>" class="product-img"
                                        alt="Product"></div>
                                <div class="product-title"><?php echo $prod['name']; ?></div>
                                <div><span class="price-current"><?php echo $prod['price']; ?></span></div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="price-old"><?php echo $prod['old']; ?></span>
                                    <button class="btn-add" type="button" onclick="event.stopPropagation();"><i
                                            class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section-container">
                    <div class="section-header">
                        <h5 class="section-title" data-admin-text-key="section_beer_title">Bia tết sale 10%</h5>
                        <div class="countdown-timer" id="timer2">
                            <div class="time-box days">11</div> <span class="time-label" data-admin-text-key="countdown_day_label">ngày</span>
                            <div class="time-box hours">06</div> <span class="time-label">:</span>
                            <div class="time-box minutes">50</div> <span class="time-label">:</span>
                            <div class="time-box seconds">16</div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <?php foreach($beers as $prod): ?>
                        <div class="col-6 col-md-3">
                            <div class="product-card" role="link" tabindex="0"
                                onclick='window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>'
                                onkeypress='if(event.key === "Enter" || event.key === " "){ event.preventDefault(); window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>; }'>
                                <span class="badge-sale">-10%</span>
                                <div class="card-img-wrapper">
                                    <img src="<?php echo $prod['img']; ?>" class="product-img" alt="Product">
                                </div>
                                <div class="product-title"><?php echo $prod['name']; ?></div>
                                <div><span class="price-current"><?php echo $prod['price']; ?></span></div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="price-old"><?php echo $prod['old']; ?></span>
                                    <button class="btn-add" type="button" onclick="event.stopPropagation();"><i
                                            class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section-container">
                    <div class="section-header">
                        <h5 class="section-title text-primary" data-admin-text-key="section_gift_title">Đồ biếu tết sale 10%</h5>
                    </div>
                    <div class="row g-3">
                        <?php foreach($gifts as $prod): ?>
                        <div class="col-6 col-md-3">
                            <div class="product-card" role="link" tabindex="0"
                                onclick='window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>'
                                onkeypress='if(event.key === "Enter" || event.key === " "){ event.preventDefault(); window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>; }'>
                                <span class="badge-sale">-10%</span>
                                <div class="card-img-wrapper">
                                    <img src="<?php echo $prod['img']; ?>" class="product-img" alt="Product">
                                </div>
                                <div class="product-title"><?php echo $prod['name']; ?></div>
                                <div><span class="price-current"><?php echo $prod['price']; ?></span></div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="price-old"><?php echo $prod['old']; ?></span>
                                    <button class="btn-add" type="button" onclick="event.stopPropagation();"><i
                                            class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section-container">
                    <div class="section-header">
                        <h5 class="section-title" data-admin-text-key="section_market_title">Đi chợ ngày tết</h5>
                    </div>
                    <div class="row g-3">
                        <?php foreach($fresh_foods as $prod): ?>
                        <div class="col-6 col-md-3">
                            <div class="product-card" role="link" tabindex="0"
                                onclick='window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>'
                                onkeypress='if(event.key === "Enter" || event.key === " "){ event.preventDefault(); window.location.href=<?php echo json_encode($prod["link"] ?? "#"); ?>; }'>
                                <span class="badge-sale">-15%</span>
                                <div class="card-img-wrapper">
                                    <img src="<?php echo $prod['img']; ?>" class="product-img" alt="Product">
                                </div>
                                <div class="product-title"><?php echo $prod['name']; ?></div>
                                <div><span class="price-current"><?php echo $prod['price']; ?></span></div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="price-old"><?php echo $prod['old']; ?></span>
                                    <button class="btn-add" type="button" onclick="event.stopPropagation();"><i
                                            class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

        </div>
    </div>
    </div>

    <div class="bg-light py-4">
        <div class="container">
            <div class="row text-center">
                <div class="col-3"><i class="fas fa-truck fa-2x text-primary mb-2"></i><br><b>Giao hàng
                        nhanh</b></div>
                <div class="col-3"><i class="fas fa-shield-alt fa-2x text-primary mb-2"></i><br><b>An toàn bảo
                        mật</b></div>
                <div class="col-3"><i class="fas fa-headset fa-2x text-primary mb-2"></i><br><b>Hỗ trợ 24/7</b>
                </div>
                <div class="col-3"><i class="fas fa-undo fa-2x text-primary mb-2"></i><br><b>Đổi trả nhanh</b>
                </div>
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
                        <p class="feedback-text">"Sản phẩm rất tươi ngon, giao hàng đúng hẹn. Tết này sẽ ủng hộ
                            tiếp! Đóng gói rất cẩn thận."</p>
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
                        <p class="feedback-text">"Giá cả cạnh tranh, nhân viên tư vấn nhiệt tình. Sẽ giới thiệu
                            cho bạn bè mua sắm tại đây."</p>
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
                        <div class="feedback-text">"Dịch vụ tuyệt vời. Mua hàng online mà được freeship lại còn
                            được quà tặng kèm."</div>
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
            <h3 class="newsletter-title" data-admin-text-key="newsletter_title">Đăng kí nhận khuyến mãi</h3>
            <p class="newsletter-subtitle" data-admin-text-key="newsletter_subtitle">Nhận thông tin về các sản phẩm mới và các ưu đãi đặc biệt</p>
            <div class="newsletter-form">
                <input type="email" class="newsletter-input" placeholder="Email của bạn...">
                <button type="button" class="newsletter-btn" data-admin-text-key="newsletter_button">Đăng ký</button>
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
                <a href="#">Tuyển dụng</a>
                <a href="#">Tin tức &amp; Sự kiện</a>
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

    <button id="chatbot-button" title="Hỏi AI tư vấn">
        <i class="fas fa-robot"></i>
    </button>

    <div id="chatbot-widget">
        <div class="chatbot-header">
            <h3>🤖 AI Tư Vấn</h3>
            <button class="chatbot-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="chatbot-messages">
            <div class="chatbot-message ai">
                <div class="chatbot-bubble">👋 Xin chào! Tôi có thể giúp bạn chọn sản phẩm nào không? Hỏi tôi về nước ngọt, trái cây, sữa, v.v...</div>
            </div>
        </div>
        <div class="chatbot-input-area">
            <input type="text" placeholder="Nhập câu hỏi..." autocomplete="off">
            <button class="chatbot-send-btn" title="Gửi"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="web-events.js?v=20260414-3"></script>
    <script>
    const isAdminUser = <?php echo $isAdminUser ? 'true' : 'false'; ?>;
    const adminConfigApiUrl = 'trangchu.php?admin_home_config_api=1';
    const adminInitialThemeConfig = <?php echo json_encode($homeThemeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const adminInitialTextConfig = <?php echo json_encode($homeTextConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const adminCurrentThemeConfig = {
        ...adminInitialThemeConfig
    };
    const adminCurrentTextConfig = {
        ...adminInitialTextConfig
    };
    let adminSaveDebounceTimer = null;

    const adminEditableTextMeta = {
        home_product_heading: {
            label: 'Tiêu đề khối sản phẩm',
            defaultValue: 'Sản phẩm'
        },
        section_soft_drink_title: {
            label: 'Tiêu đề khối nước ngọt',
            defaultValue: 'Nước ngọt tết sale 10%'
        },
        section_beer_title: {
            label: 'Tiêu đề khối bia',
            defaultValue: 'Bia tết sale 10%'
        },
        section_gift_title: {
            label: 'Tiêu đề khối đồ biếu',
            defaultValue: 'Đồ biếu tết sale 10%'
        },
        section_market_title: {
            label: 'Tiêu đề khối đi chợ',
            defaultValue: 'Đi chợ ngày tết'
        },
        countdown_day_label: {
            label: 'Nhãn đếm ngược (ngày)',
            defaultValue: 'ngày'
        },
        newsletter_title: {
            label: 'Tiêu đề đăng ký email',
            defaultValue: 'Đăng kí nhận khuyến mãi'
        },
        newsletter_subtitle: {
            label: 'Mô tả đăng ký email',
            defaultValue: 'Nhận thông tin về các sản phẩm mới và các ưu đãi đặc biệt'
        },
        newsletter_button: {
            label: 'Nút đăng ký email',
            defaultValue: 'Đăng ký'
        }
    };

    function adminApplyTheme(themeConfig) {
        if (!themeConfig || typeof themeConfig !== 'object') {
            return;
        }

        const root = document.documentElement;
        if (themeConfig.pageBg) {
            root.style.setProperty('--page-bg-custom', themeConfig.pageBg);
        }
        if (themeConfig.sectionBg) {
            root.style.setProperty('--section-bg-custom', themeConfig.sectionBg);
        }
        if (themeConfig.navBg) {
            root.style.setProperty('--main-nav-custom', themeConfig.navBg);
        }
    }

    function adminQueueSaveConfig() {
        if (!isAdminUser) {
            return;
        }

        if (adminSaveDebounceTimer) {
            clearTimeout(adminSaveDebounceTimer);
        }

        adminSaveDebounceTimer = setTimeout(() => {
            fetch(adminConfigApiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'save',
                        theme: adminCurrentThemeConfig,
                        text: adminCurrentTextConfig,
                    })
                })
                .catch(() => {
                    // Không khóa UI nếu API lỗi.
                });
        }, 250);
    }

    function adminGetDefaultThemeConfig() {
        return {
            pageBg: '#f5f5f5',
            sectionBg: '#e0f2fe',
            navBg: '#007bff'
        };
    }

    function adminGetDefaultTextConfig() {
        const defaults = {
            ...adminInitialTextConfig
        };
        Object.keys(adminEditableTextMeta).forEach((key) => {
            if (!Object.prototype.hasOwnProperty.call(defaults, key)) {
                defaults[key] = adminEditableTextMeta[key].defaultValue || '';
            }
        });
        return defaults;
    }

    function adminApplyTextConfig(textConfig) {
        if (!textConfig || typeof textConfig !== 'object') {
            return;
        }

        document.querySelectorAll('[data-admin-text-key]').forEach((el) => {
            const key = el.getAttribute('data-admin-text-key');
            if (!key || !Object.prototype.hasOwnProperty.call(textConfig, key)) {
                return;
            }
            el.textContent = textConfig[key] ?? '';
        });
    }

    function initAdminTextControls() {
        const defaults = adminGetDefaultTextConfig();
        const currentConfig = {
            ...defaults,
            ...adminCurrentTextConfig
        };

        adminApplyTextConfig(currentConfig);

        if (!isAdminUser) {
            return;
        }

        const fieldWrap = document.getElementById('adminTextEditorFields');
        const resetTextBtn = document.getElementById('adminResetTextBtn');
        if (!fieldWrap || !resetTextBtn) {
            return;
        }

        const fragment = document.createDocumentFragment();
        Object.keys(adminEditableTextMeta).forEach((key) => {
            const item = adminEditableTextMeta[key];
            const field = document.createElement('div');
            field.className = 'admin-text-field';

            const label = document.createElement('label');
            label.setAttribute('for', `admin-text-input-${key}`);
            label.textContent = item.label;

            const input = document.createElement('input');
            input.type = 'text';
            input.id = `admin-text-input-${key}`;
            input.value = currentConfig[key] ?? item.defaultValue ?? '';
            input.maxLength = 120;
            input.setAttribute('data-admin-text-input', key);

            input.addEventListener('input', () => {
                currentConfig[key] = input.value;
                adminCurrentTextConfig[key] = input.value;
                adminApplyTextConfig(currentConfig);
                adminQueueSaveConfig();
            });

            field.appendChild(label);
            field.appendChild(input);
            fragment.appendChild(field);
        });

        fieldWrap.innerHTML = '';
        fieldWrap.appendChild(fragment);

        resetTextBtn.addEventListener('click', () => {
            const defaultConfig = adminGetDefaultTextConfig();
            Object.keys(defaultConfig).forEach((key) => {
                currentConfig[key] = defaultConfig[key];
                adminCurrentTextConfig[key] = defaultConfig[key];
                const input = document.querySelector(`[data-admin-text-input="${key}"]`);
                if (input) {
                    input.value = defaultConfig[key];
                }
            });
            adminApplyTextConfig(defaultConfig);
            adminQueueSaveConfig();
        });
    }

    function attachAdminEditButtons() {
        if (!isAdminUser) {
            return;
        }

        document.querySelectorAll('.product-card').forEach((card) => {
            if (card.querySelector('.admin-edit-btn')) {
                return;
            }

            const onclickContent = card.getAttribute('onclick') || '';
            const match = onclickContent.match(/id=([^"'&\s]+)/);
            if (!match || !match[1]) {
                return;
            }

            const productId = decodeURIComponent(match[1]);
            const returnTo = encodeURIComponent(window.location.pathname + window.location.search);
            const editLink = document.createElement('a');
            editLink.className = 'admin-edit-btn';
            editLink.href = `../TrangAdmin/admin-sanpham.php?edit=${encodeURIComponent(productId)}&return_to=${returnTo}`;
            editLink.title = 'Sửa sản phẩm này';
            editLink.innerHTML = '<i class="fas fa-pen"></i>';
            editLink.addEventListener('click', function(event) {
                event.stopPropagation();
            });

            card.appendChild(editLink);
        });
    }

    function initAdminThemeControls() {
        adminApplyTheme(adminCurrentThemeConfig);

        if (!isAdminUser) {
            return;
        }

        const pageBgPicker = document.getElementById('adminPageBgPicker');
        const sectionBgPicker = document.getElementById('adminSectionBgPicker');
        const navBgPicker = document.getElementById('adminNavBgPicker');
        const resetBtn = document.getElementById('adminResetThemeBtn');

        if (!pageBgPicker || !sectionBgPicker || !navBgPicker || !resetBtn) {
            return;
        }

        const defaultTheme = adminGetDefaultThemeConfig();
        const currentTheme = {
            ...defaultTheme,
            ...adminCurrentThemeConfig,
        };

        pageBgPicker.value = currentTheme.pageBg;
        sectionBgPicker.value = currentTheme.sectionBg;
        navBgPicker.value = currentTheme.navBg;

        const onThemeChange = () => {
            const theme = {
                pageBg: pageBgPicker.value,
                sectionBg: sectionBgPicker.value,
                navBg: navBgPicker.value,
            };
            adminApplyTheme(theme);
            adminCurrentThemeConfig.pageBg = theme.pageBg;
            adminCurrentThemeConfig.sectionBg = theme.sectionBg;
            adminCurrentThemeConfig.navBg = theme.navBg;
            adminQueueSaveConfig();
        };

        pageBgPicker.addEventListener('input', onThemeChange);
        sectionBgPicker.addEventListener('input', onThemeChange);
        navBgPicker.addEventListener('input', onThemeChange);

        resetBtn.addEventListener('click', () => {
            adminApplyTheme(defaultTheme);
            adminCurrentThemeConfig.pageBg = defaultTheme.pageBg;
            adminCurrentThemeConfig.sectionBg = defaultTheme.sectionBg;
            adminCurrentThemeConfig.navBg = defaultTheme.navBg;
            pageBgPicker.value = defaultTheme.pageBg;
            sectionBgPicker.value = defaultTheme.sectionBg;
            navBgPicker.value = defaultTheme.navBg;
            adminQueueSaveConfig();
        });
    }

    function bindAdminQuickLinks() {
        if (!isAdminUser) {
            return;
        }

        const goDashboardLink = document.getElementById('adminGoDashboardLink');
        const goProductsLink = document.getElementById('adminGoProductsLink');

        if (goDashboardLink) {
            goDashboardLink.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                window.location.assign('../TrangAdmin/admin.php');
            });
        }

        if (goProductsLink) {
            goProductsLink.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const returnTo = encodeURIComponent(window.location.pathname + window.location.search);
                window.location.assign(`../TrangAdmin/admin-sanpham.php?return_to=${returnTo}`);
            });
        }
    }

    // JS tạo hiệu ứng đếm ngược thời gian giả lập
    function startTimer(duration, displayElement) {
        let timer = duration,
            days, hours, minutes, seconds;
        setInterval(function() {
            days = Math.floor(timer / (24 * 60 * 60));
            hours = Math.floor((timer % (24 * 60 * 60)) / (60 * 60));
            minutes = Math.floor((timer % (60 * 60)) / 60);
            seconds = Math.floor(timer % 60);

            // Cập nhật DOM
            const el = document.getElementById(displayElement);
            if (el) {
                el.querySelector('.days').textContent = days < 10 ? "0" + days : days;
                el.querySelector('.hours').textContent = hours < 10 ? "0" + hours : hours;
                el.querySelector('.minutes').textContent = minutes < 10 ? "0" + minutes : minutes;
                el.querySelector('.seconds').textContent = seconds < 10 ? "0" + seconds : seconds;
            }

            if (--timer < 0) {
                timer = duration; // Reset lại khi hết giờ
            }
        }, 1000);
    }

    // Chạy timer: 11 ngày, 6 giờ, 50 phút = 975000 giây (ví dụ)
    window.onload = function() {
        attachAdminEditButtons();
        initAdminThemeControls();
        initAdminTextControls();
        bindAdminQuickLinks();
        startTimer(975000, 'timer1');
        startTimer(975010, 'timer2');
    };
    </script>
    <script>
    (function() {
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text || '').replace(/[&<>"']/g, (m) => map[m]);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const chatbotButton = document.getElementById('chatbot-button');
            const chatbotWidget = document.getElementById('chatbot-widget');
            const chatbotClose = document.querySelector('.chatbot-close');
            const chatbotMessages = document.querySelector('.chatbot-messages');
            const chatbotInput = document.querySelector('.chatbot-input-area input');
            const chatbotSendBtn = document.querySelector('.chatbot-send-btn');

            if (window.__ackChatbotManagedByGlobal || (chatbotWidget && chatbotWidget.dataset && chatbotWidget.dataset.ackBound === '1')) {
                return;
            }

            if (!chatbotButton || !chatbotWidget || !chatbotClose || !chatbotMessages || !chatbotInput || !chatbotSendBtn) {
                return;
            }

            chatbotButton.addEventListener('click', function() {
                chatbotWidget.classList.toggle('active');
                chatbotButton.classList.toggle('active');
                if (chatbotWidget.classList.contains('active')) {
                    chatbotInput.focus();
                }
            });

            chatbotClose.addEventListener('click', function() {
                chatbotWidget.classList.remove('active');
                chatbotButton.classList.remove('active');
            });

            function sendChatMessage() {
                const message = chatbotInput.value.trim();
                if (!message) return;

                const userDiv = document.createElement('div');
                userDiv.className = 'chatbot-message user';
                userDiv.innerHTML = '<div class="chatbot-bubble">' + escapeHtml(message) + '</div>';
                chatbotMessages.appendChild(userDiv);
                chatbotInput.value = '';

                const typingDiv = document.createElement('div');
                typingDiv.className = 'chatbot-message ai';
                typingDiv.id = 'typing-indicator';
                typingDiv.innerHTML = '<div class="chatbot-bubble">Đang suy nghĩ...</div>';
                chatbotMessages.appendChild(typingDiv);
                chatbotMessages.scrollTop = chatbotMessages.scrollHeight;

                fetch('ai-advisor.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'message=' + encodeURIComponent(message)
                    })
                    .then((response) => response.json())
                    .then((data) => {
                        const typingEl = document.getElementById('typing-indicator');
                        if (typingEl) typingEl.remove();

                        const aiDiv = document.createElement('div');
                        aiDiv.className = 'chatbot-message ai';
                        aiDiv.innerHTML = '<div class="chatbot-bubble">' + escapeHtml(data.reply || '') + '</div>';
                        chatbotMessages.appendChild(aiDiv);

                        if (data.type === 'products' && Array.isArray(data.products) && data.products.length > 0) {
                            if (!document.getElementById('chatbot-products-style')) {
                                const style = document.createElement('style');
                                style.id = 'chatbot-products-style';
                                style.textContent = `
                                    .chatbot-products {
                                        display: grid;
                                        grid-template-columns: repeat(4, minmax(0, 1fr));
                                        gap: 10px;
                                        margin: 10px 0 2px;
                                    }
                                    .chatbot-product-item {
                                        background: white;
                                        border: 2px solid #0d6efd;
                                        border-radius: 12px;
                                        padding: 0;
                                        overflow: hidden;
                                        text-align: left;
                                        cursor: pointer;
                                        transition: all 0.2s;
                                        text-decoration: none !important;
                                        color: #333 !important;
                                        display: block;
                                    }
                                    .chatbot-product-item:hover {
                                        border-color: #007bff;
                                        background: #ffffff;
                                        transform: translateY(-2px);
                                        box-shadow: 0 6px 16px rgba(0,123,255,0.24);
                                    }
                                    .chatbot-product-img {
                                        width: 100%;
                                        aspect-ratio: 1 / 1;
                                        height: auto;
                                        object-fit: cover;
                                        display: block;
                                        background: #fff;
                                    }
                                    .chatbot-product-body {
                                        padding: 8px 10px 10px;
                                    }
                                    .chatbot-product-name {
                                        font-size: 13px;
                                        font-weight: 600;
                                        margin-bottom: 4px;
                                        line-height: 1.3;
                                    }
                                    .chatbot-product-price {
                                        font-size: 14px;
                                        color: #dc3545;
                                        font-weight: bold;
                                    }
                                    .chatbot-view-more {
                                        grid-column: 1 / -1;
                                        margin-top: 8px;
                                        text-align: center;
                                        padding-top: 8px;
                                        border-top: 1px solid #f0f0f0;
                                    }
                                    .chatbot-view-more a {
                                        text-decoration: none !important;
                                    }
                                    .chatbot-view-more-btn {
                                        background: none;
                                        border: none;
                                        color: #007bff;
                                        cursor: pointer;
                                        font-size: 12px;
                                        font-weight: 500;
                                        padding: 3px 8px;
                                        transition: all 0.2s;
                                        display: inline-block;
                                    }
                                    .chatbot-view-more-btn:hover {
                                        color: #0056b3;
                                        text-decoration: underline;
                                    }

                                `;
                                document.head.appendChild(style);
                            }

                            const productsDiv = document.createElement('div');
                            productsDiv.className = 'chatbot-message ai';

                            let productsHTML = '<div class="chatbot-products">';
                            data.products.forEach((product) => {
                                const productUrl = product.link || '#';
                                productsHTML += `
                                    <a href="${escapeHtml(productUrl)}" class="chatbot-product-item" title="${escapeHtml(product.name || '')}">
                                        <img src="${escapeHtml(product.img || '')}" alt="${escapeHtml(product.name || '')}" class="chatbot-product-img">
                                        <div class="chatbot-product-body">
                                            <div class="chatbot-product-name">${escapeHtml(product.name || '')}</div>
                                            <div class="chatbot-product-price">${escapeHtml(product.price || '')}</div>
                                        </div>
                                    </a>
                                `;
                            });
                            productsHTML += '</div>';

                            if (data.hasMore) {
                                const categoryNameMap = {
                                    'nuocngot': 'Trangnuocngot.php',
                                    'douong': 'Trangdouong.php',
                                    'anvat': 'Tranganvat.php',
                                    'thucannhanh': 'Trangthucannhanh.php',
                                    'traicay': 'Trangtraicay.php',
                                    'raucu': 'Trangraucu.php',
                                    'sua': 'Trangsua.php',
                                    'banhngot': 'Trangbanhngot.php',
                                    'giadung': 'Tranggiadung.php',
                                    'mypham': 'Trangmypham.php',
                                    'kem': 'Trangkem.php',
                                    'mianlien': 'Trangmianlien.php',
                                    'tuoisong': 'Trangtuoisong.php',
                                    'dohop': 'Trangdohop.php',
                                    'giavi': 'Tranggiavi.php',
                                    'bia': 'Trangbia.php'
                                };
                                const categoryLink = categoryNameMap[data.categorySlug] || '#';
                                const remaining = Math.max(0, Number(data.totalCount || 0) - Number(data.displayedCount || 0));
                                productsHTML += `
                                    <div class="chatbot-view-more">
                                        <a href="${categoryLink}">
                                            <button class="chatbot-view-more-btn">Xem thêm ${remaining} sản phẩm →</button>
                                        </a>
                                    </div>
                                `;
                            }

                            productsDiv.innerHTML = productsHTML;
                            chatbotMessages.appendChild(productsDiv);
                        }

                        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
                    })
                    .catch(() => {
                        const typingEl = document.getElementById('typing-indicator');
                        if (typingEl) typingEl.remove();

                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'chatbot-message ai';
                        errorDiv.innerHTML = '<div class="chatbot-bubble">Xin lỗi, tôi gặp lỗi. Vui lòng thử lại!</div>';
                        chatbotMessages.appendChild(errorDiv);
                    });
            }

            chatbotSendBtn.addEventListener('click', sendChatMessage);
            chatbotInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendChatMessage();
                }
            });
        });
    })();
    </script>
</body>

</html>