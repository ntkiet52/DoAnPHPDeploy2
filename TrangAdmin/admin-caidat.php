<?php
require_once __DIR__ . '/../Login/admin_auth.php';

$dbError = '';
$crudMessage = '';
$crudError = '';

$dbHost = '127.0.0.1';
$dbName = 'qlhethongbanhangmini';
$dbUser = 'root';
$dbPass = '';

$settings = [
    'store_name' => 'ACK',
    'support_email' => 'support@shophub.com',
    'support_phone' => '0900000000',
    'store_address' => '123 Đường Nguyễn Huệ, Q.1, HCM',
    'payment_online' => 1,
    'payment_cod' => 1,
    'transaction_fee' => '2.5',
    'shipping_base_fee' => '30000',
    'shipping_fast_fee' => '50000',
    'shipping_express_2h' => 0,
    'security_2fa' => 0,
    'security_email_verify' => 0,
    'notify_new_order' => 1,
    'notify_low_stock' => 1,
    'notify_daily_report' => 0,
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

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS caidat_hethong (
            id TINYINT(1) NOT NULL,
            store_name VARCHAR(150) NOT NULL,
            support_email VARCHAR(150) NOT NULL,
            support_phone VARCHAR(30) NOT NULL,
            store_address TEXT NOT NULL,
            payment_online TINYINT(1) NOT NULL DEFAULT 1,
            payment_cod TINYINT(1) NOT NULL DEFAULT 1,
            transaction_fee DECIMAL(6,2) NOT NULL DEFAULT 2.50,
            shipping_base_fee INT(11) NOT NULL DEFAULT 30000,
            shipping_fast_fee INT(11) NOT NULL DEFAULT 50000,
            shipping_express_2h TINYINT(1) NOT NULL DEFAULT 0,
            security_2fa TINYINT(1) NOT NULL DEFAULT 0,
            security_email_verify TINYINT(1) NOT NULL DEFAULT 0,
            notify_new_order TINYINT(1) NOT NULL DEFAULT 1,
            notify_low_stock TINYINT(1) NOT NULL DEFAULT 1,
            notify_daily_report TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $posted = [
                'store_name' => trim((string) ($_POST['store_name'] ?? '')),
                'support_email' => trim((string) ($_POST['support_email'] ?? '')),
                'support_phone' => trim((string) ($_POST['support_phone'] ?? '')),
                'store_address' => trim((string) ($_POST['store_address'] ?? '')),
                'payment_online' => isset($_POST['payment_online']) ? 1 : 0,
                'payment_cod' => isset($_POST['payment_cod']) ? 1 : 0,
                'transaction_fee' => (float) str_replace(',', '.', (string) ($_POST['transaction_fee'] ?? '0')),
                'shipping_base_fee' => (int) ($_POST['shipping_base_fee'] ?? 0),
                'shipping_fast_fee' => (int) ($_POST['shipping_fast_fee'] ?? 0),
                'shipping_express_2h' => isset($_POST['shipping_express_2h']) ? 1 : 0,
                'security_2fa' => isset($_POST['security_2fa']) ? 1 : 0,
                'security_email_verify' => isset($_POST['security_email_verify']) ? 1 : 0,
                'notify_new_order' => isset($_POST['notify_new_order']) ? 1 : 0,
                'notify_low_stock' => isset($_POST['notify_low_stock']) ? 1 : 0,
                'notify_daily_report' => isset($_POST['notify_daily_report']) ? 1 : 0,
            ];

            if ($posted['store_name'] === '' || $posted['support_email'] === '') {
                $crudError = 'Vui lòng nhập tên cửa hàng và email hỗ trợ.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO caidat_hethong (
                        id, store_name, support_email, support_phone, store_address,
                        payment_online, payment_cod, transaction_fee,
                        shipping_base_fee, shipping_fast_fee, shipping_express_2h,
                        security_2fa, security_email_verify,
                        notify_new_order, notify_low_stock, notify_daily_report
                    ) VALUES (
                        1, ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?, ?, ?
                    )
                    ON DUPLICATE KEY UPDATE
                        store_name = VALUES(store_name),
                        support_email = VALUES(support_email),
                        support_phone = VALUES(support_phone),
                        store_address = VALUES(store_address),
                        payment_online = VALUES(payment_online),
                        payment_cod = VALUES(payment_cod),
                        transaction_fee = VALUES(transaction_fee),
                        shipping_base_fee = VALUES(shipping_base_fee),
                        shipping_fast_fee = VALUES(shipping_fast_fee),
                        shipping_express_2h = VALUES(shipping_express_2h),
                        security_2fa = VALUES(security_2fa),
                        security_email_verify = VALUES(security_email_verify),
                        notify_new_order = VALUES(notify_new_order),
                        notify_low_stock = VALUES(notify_low_stock),
                        notify_daily_report = VALUES(notify_daily_report)"
                );

                $stmt->execute([
                    $posted['store_name'],
                    $posted['support_email'],
                    $posted['support_phone'],
                    $posted['store_address'],
                    $posted['payment_online'],
                    $posted['payment_cod'],
                    $posted['transaction_fee'],
                    $posted['shipping_base_fee'],
                    $posted['shipping_fast_fee'],
                    $posted['shipping_express_2h'],
                    $posted['security_2fa'],
                    $posted['security_email_verify'],
                    $posted['notify_new_order'],
                    $posted['notify_low_stock'],
                    $posted['notify_daily_report'],
                ]);
                $crudMessage = 'Đã lưu cài đặt hệ thống thành công.';
            }
        } catch (Throwable $postError) {
            $crudError = $postError->getMessage();
        }
    }

    $row = $pdo->query("SELECT * FROM caidat_hethong WHERE id = 1 LIMIT 1")->fetch();
    if ($row) {
        $settings = array_merge($settings, [
            'store_name' => (string) ($row['store_name'] ?? $settings['store_name']),
            'support_email' => (string) ($row['support_email'] ?? $settings['support_email']),
            'support_phone' => (string) ($row['support_phone'] ?? $settings['support_phone']),
            'store_address' => (string) ($row['store_address'] ?? $settings['store_address']),
            'payment_online' => (int) ($row['payment_online'] ?? $settings['payment_online']),
            'payment_cod' => (int) ($row['payment_cod'] ?? $settings['payment_cod']),
            'transaction_fee' => (string) ($row['transaction_fee'] ?? $settings['transaction_fee']),
            'shipping_base_fee' => (string) ($row['shipping_base_fee'] ?? $settings['shipping_base_fee']),
            'shipping_fast_fee' => (string) ($row['shipping_fast_fee'] ?? $settings['shipping_fast_fee']),
            'shipping_express_2h' => (int) ($row['shipping_express_2h'] ?? $settings['shipping_express_2h']),
            'security_2fa' => (int) ($row['security_2fa'] ?? $settings['security_2fa']),
            'security_email_verify' => (int) ($row['security_email_verify'] ?? $settings['security_email_verify']),
            'notify_new_order' => (int) ($row['notify_new_order'] ?? $settings['notify_new_order']),
            'notify_low_stock' => (int) ($row['notify_low_stock'] ?? $settings['notify_low_stock']),
            'notify_daily_report' => (int) ($row['notify_daily_report'] ?? $settings['notify_daily_report']),
        ]);
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACK Admin - Cài đặt hệ thống</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --primary-blue: #007bff;
        --bg-light: #f5f7fa;
        --text-dark: #344767;
        --sidebar-width: 260px;
        --admin-layout-gap: 10px;
        --admin-content-inline-padding: 10px;
    }

    html,
    body {
        overflow-x: hidden;
    }

    body {
        font-family: 'Roboto', sans-serif;
        background-color: var(--bg-light);
        color: var(--text-dark);
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
        margin-left: calc(var(--sidebar-width) + 20px);
        padding: 20px 20px;
        width: calc(100% - (var(--sidebar-width) + 20px));
        max-width: calc(100% - (var(--sidebar-width) + 20px));
        box-sizing: border-box;
        overflow-x: hidden;
    }

    /* --- SETTINGS FORM --- */
    .settings-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
        border: none;
    }

    .section-title {
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
    }

    .section-icon {
        width: 35px;
        height: 35px;
        background: #e033ff;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin-right: 12px;
    }

    .form-label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #344767;
    }

    .form-control {
        border-radius: 10px;
        padding: 10px 15px;
        border: 1px solid #dee2e6;
        font-size: 0.9rem;
    }

    /* Custom Checkbox/Toggle look */
    .option-box {
        border: 1px solid #eee;
        border-radius: 10px;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .option-info h6 {
        margin: 0;
        font-size: 0.85rem;
        font-weight: 700;
    }

    .option-info p {
        margin: 0;
        font-size: 0.75rem;
        color: #67748e;
    }

    .btn-change-pass {
        background-color: #cfe2ff;
        color: #007bff;
        border: none;
        border-radius: 8px;
        width: 100%;
        padding: 10px;
        font-weight: 600;
    }

    .btn-save {
        background: #a370f7;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 12px 60px;
        font-weight: 600;
    }

    .btn-cancel {
        background: #828282;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 12px 60px;
        font-weight: 600;
    }

    .search-input {
        border-radius: 20px;
        padding: 5px 20px;
        border: 1px solid #ddd;
        width: 250px;
    }
    </style>
    <link rel="stylesheet" href="admin-unified-ui.css">
</head>

<body>

    <div class="sidebar">
        <div class="brand-logo">
            <img src="../TrangUser/ack.png" alt="Logo" height="40">
            <h4 class="fw-bold ms-2 mb-0" style="color: #344767;">Admin</h4>
        </div>
        <nav>
            <a href="admin.php" class="nav-item"><i class="fas fa-chart-bar"></i> Tổng quan</a>
            <a href="admin-sanpham.php" class="nav-item"><i class="fas fa-box"></i> Sản phẩm</a>
            <a href="admin-nhomhang.php" class="nav-item"><i class="fas fa-folder"></i> Nhóm hàng</a>
            <a href="admin-nhaphang.php" class="nav-item"><i class="fas fa-truck-loading"></i> Nhập hàng</a>
            <a href="admin-nhacungcap.php" class="nav-item"><i class="fas fa-building"></i> Nhà cung cấp</a>
            <a href="admin-bophan.php" class="nav-item"><i class="fas fa-sitemap"></i> Bộ phận</a>
            <a href="admin-donhang.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Đơn hàng</a>
            <a href="admin-nhanvien.php" class="nav-item"><i class="fas fa-user-tie"></i> Nhân viên</a>
            <a href="admin-khachhang.php" class="nav-item"><i class="fas fa-users"></i> Khách hàng</a>
            <a href="admin-voucher.php" class="nav-item"><i class="fas fa-ticket-alt"></i> Voucher</a>
            <a href="admin-caidat.php" class="nav-item active"><i class="fas fa-cog"></i> Cài đặt</a>
        </nav>
        <a href="../Login/logout.php" class="logout-btn">Đăng xuất</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-primary">Bảng điều khiển Admin</h4>
            <button class="btn btn-light rounded-circle shadow-sm"><i class="fas fa-times"></i></button>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">Cài đặt hệ thống</h3>
            <input type="text" class="search-input" placeholder="Tìm kiếm">
        </div>

        <?php if ($dbError !== ''): ?>
        <div class="alert alert-warning" role="alert">
            Không thể kết nối/lưu cài đặt: <?php echo htmlspecialchars($dbError); ?>
        </div>
        <?php endif; ?>

        <?php if ($crudError !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($crudError); ?>
        </div>
        <?php endif; ?>

        <?php if ($crudMessage !== ''): ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($crudMessage); ?>
        </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="settings-card">
                <div class="section-title">
                    <span class="section-icon"><i class="fas fa-store"></i></span> Thông tin cửa hàng
                </div>
                <div class="mb-3">
                    <label class="form-label">Tên cửa hàng</label>
                    <input type="text" class="form-control" name="store_name"
                        value="<?php echo htmlspecialchars((string) $settings['store_name']); ?>">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Email hỗ trợ</label>
                        <input type="email" class="form-control" name="support_email"
                            value="<?php echo htmlspecialchars((string) $settings['support_email']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số điện thoại hỗ trợ</label>
                        <input type="text" class="form-control" name="support_phone"
                            value="<?php echo htmlspecialchars((string) $settings['support_phone']); ?>">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Địa chỉ cửa hàng</label>
                    <textarea class="form-control" rows="3"
                        name="store_address"><?php echo htmlspecialchars((string) $settings['store_address']); ?></textarea>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="settings-card h-100">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Thanh toán</h6>
                        <div class="option-box">
                            <div class="option-info">
                                <h6>Thanh toán Online</h6>
                                <p>Hỗ trợ Visa, Mastercard, Zalopay</p>
                            </div>
                            <input type="checkbox" name="payment_online"
                                <?php echo ((int) $settings['payment_online'] === 1) ? 'checked' : ''; ?>>
                        </div>
                        <div class="option-box">
                            <div class="option-info">
                                <h6>Thanh toán COD</h6>
                                <p>Thanh toán khi nhận hàng</p>
                            </div>
                            <input type="checkbox" name="payment_cod"
                                <?php echo ((int) $settings['payment_cod'] === 1) ? 'checked' : ''; ?>>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Phí giao dịch (%)</label>
                            <input type="text" class="form-control" name="transaction_fee"
                                value="<?php echo htmlspecialchars((string) $settings['transaction_fee']); ?>">
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="settings-card h-100">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Vận chuyển</h6>
                        <div class="mb-3">
                            <label class="form-label">Phí vận chuyển cơ bản (đ)</label>
                            <input type="text" class="form-control" name="shipping_base_fee"
                                value="<?php echo htmlspecialchars((string) $settings['shipping_base_fee']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phí vận chuyển nhanh (đ)</label>
                            <input type="text" class="form-control" name="shipping_fast_fee"
                                value="<?php echo htmlspecialchars((string) $settings['shipping_fast_fee']); ?>">
                        </div>
                        <div class="option-box">
                            <div class="option-info">
                                <h6>Giao hàng nhanh 2h</h6>
                            </div>
                            <input type="checkbox" name="shipping_express_2h"
                                <?php echo ((int) $settings['shipping_express_2h'] === 1) ? 'checked' : ''; ?>>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="settings-card h-100">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Bảo mật</h6>
                        <div class="option-box">
                            <div class="option-info">
                                <h6>Xác thực 2 yếu tố</h6>
                            </div>
                            <input type="checkbox" name="security_2fa"
                                <?php echo ((int) $settings['security_2fa'] === 1) ? 'checked' : ''; ?>>
                        </div>
                        <div class="option-box mb-4">
                            <div class="option-info">
                                <h6>Yêu cầu xác nhận email</h6>
                            </div>
                            <input type="checkbox" name="security_email_verify"
                                <?php echo ((int) $settings['security_email_verify'] === 1) ? 'checked' : ''; ?>>
                        </div>
                        <button type="button" class="btn-change-pass">Đổi mật khẩu</button>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="settings-card h-100">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Thông báo</h6>
                        <div class="option-box">
                            <div class="option-info">
                                <h6>Cảnh báo đơn hàng mới</h6>
                            </div>
                            <input type="checkbox" name="notify_new_order"
                                <?php echo ((int) $settings['notify_new_order'] === 1) ? 'checked' : ''; ?>>
                        </div>
                        <div class="option-box">
                            <div class="option-info">
                                <h6>Cảnh báo tồn kho thấp</h6>
                            </div>
                            <input type="checkbox" name="notify_low_stock"
                                <?php echo ((int) $settings['notify_low_stock'] === 1) ? 'checked' : ''; ?>>
                        </div>
                        <div class="option-box">
                            <div class="option-info">
                                <h6>Báo cáo hằng ngày</h6>
                            </div>
                            <input type="checkbox" name="notify_daily_report"
                                <?php echo ((int) $settings['notify_daily_report'] === 1) ? 'checked' : ''; ?>>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4 mb-5">
                <button type="submit" class="btn-save me-3">Lưu cài đặt</button>
                <a href="admin-caidat.php" class="btn-cancel text-decoration-none">Hủy</a>
            </div>
        </form>
    </div>

    <script src="admin-search.js"></script>
</body>

</html>