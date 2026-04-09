<?php
// Kết nối database
require_once '../Login/connect.php';

// Khởi tạo session để lấy ID khách hàng (hoặc dùng ID mặc định cho demo)
session_start();

if (isset($_SESSION['ma_khach_hang']) && trim((string) $_SESSION['ma_khach_hang']) !== '') {
    $ma_khach_hang = trim((string) $_SESSION['ma_khach_hang']);
} else {
    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    if ($user_id > 0) {
        $ma_khach_hang = 'KH' . str_pad((string) $user_id, 3, '0', STR_PAD_LEFT);
    } else {
        if (!isset($_SESSION['guest_cart_code']) || trim((string) $_SESSION['guest_cart_code']) === '') {
            $_SESSION['guest_cart_code'] = 'GUEST_' . strtoupper(substr(sha1((string) session_id()), 0, 12));
        }
        $ma_khach_hang = trim((string) $_SESSION['guest_cart_code']);
    }
}

// Lấy hoặc tạo giỏ hàng cho khách hàng
$sql_check_cart = "SELECT id_gio_hang FROM gio_hang WHERE ma_khach_hang = '$ma_khach_hang' AND trang_thai = 'active' ORDER BY id_gio_hang DESC LIMIT 1";
$result_check = $conn->query($sql_check_cart);

if ($result_check->num_rows == 0) {
    // Tạo giỏ hàng mới
    $sql_create_cart = "INSERT INTO gio_hang (ma_khach_hang, trang_thai) VALUES ('$ma_khach_hang', 'active')";
    $conn->query($sql_create_cart);
    $id_gio_hang = $conn->insert_id;
} else {
    $row = $result_check->fetch_assoc();
    $id_gio_hang = $row['id_gio_hang'];
}

// Lấy danh sách sản phẩm trong giỏ hàng
$sql_cart_items = "SELECT * FROM gio_hang_chi_tiet WHERE id_gio_hang = $id_gio_hang ORDER BY thoi_gian_them DESC";
$result_cart = $conn->query($sql_cart_items);

$cart_items = [];
if ($result_cart->num_rows > 0) {
    while ($row = $result_cart->fetch_assoc()) {
        $cart_items[] = [
            'id' => $row['ma_san_pham'],
            'chi_tiet_id' => $row['id_chi_tiet'],
            'name' => $row['ten_san_pham'],
            'desc' => $row['mo_ta'],
            'price' => $row['gia_ban'],
            'old_price' => $row['gia_goc'],
            'qty' => $row['so_luong'],
            'img' => $row['hinh_anh'],
            'shop_voucher' => $row['voucher_cua_shop'],
            'ship_msg' => $row['thong_tin_giao_hang']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng - ACK Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --primary-blue: #0b74e5;
        --bg-gray: #f5f5fa;
        --text-black: #38383d;
        --border-color: #ddd;
        --voucher-red: #ff424e;
        --ship-green: #00ab56;
        /* Màu xanh lá của icon xe tải */
    }

    body {
        font-family: 'Roboto', sans-serif;
        background-color: var(--bg-gray);
        color: var(--text-black);
        font-size: 14px;
    }

    /* --- HEADER --- */
    header {
        background: #fff;
        padding: 15px 0;
        border-bottom: 1px solid #ececec;
        margin-bottom: 20px;
    }

    .logo-img {
        height: 40px;
    }

    .search-wrapper {
        position: relative;
        max-width: 600px;
        width: 100%;
        margin: 0 20px;
    }

    .search-input {
        border-radius: 4px;
        border: 1px solid #ddd;
        padding-left: 15px;
        height: 40px;
        width: 100%;
        background: #f8f8f8;
    }

    .search-icon {
        position: absolute;
        right: 15px;
        top: 10px;
        color: #999;
    }

    /* --- CART CARD STYLING (Quan trọng) --- */
    .cart-container {
        max-width: 1100px;
        margin: 0 auto;
        padding-bottom: 50px;
    }

    .cart-item-card {
        background: #fff;
        border-radius: 4px;
        margin-bottom: 12px;
        border: 1px solid #e5e5e5;
        /* Không dùng padding chung để dễ kẻ đường line full width */
    }

    /* TẦNG 1: Thông tin sản phẩm */
    .product-main-info {
        padding: 20px;
        display: flex;
        align-items: flex-start;
    }

    .custom-checkbox {
        width: 18px;
        height: 18px;
        margin-right: 15px;
        margin-top: 40px;
        cursor: pointer;
    }

    /* Ảnh sản phẩm - BỰ HƠN & CÓ VIỀN XANH KHI CHỌN */
    .img-wrapper {
        width: 130px;
        /* Tăng kích thước ảnh theo yêu cầu */
        height: 130px;
        margin-right: 20px;
        border: 1px solid #e5e5e5;
        /* Viền mờ mặc định */
        border-radius: 4px;
        padding: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Giả lập trạng thái được chọn có viền xanh như ảnh mẫu Tôm */
    .cart-item-card:hover .img-wrapper,
    .cart-item-card.active .img-wrapper {
        border: 1px solid var(--primary-blue);
    }

    .item-img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .item-info {
        flex: 1;
        padding-top: 5px;
    }

    .shop-badge {
        background: var(--primary-blue);
        color: #fff;
        font-size: 10px;
        padding: 1px 4px;
        border-radius: 2px;
        margin-right: 5px;
        font-weight: bold;
    }

    .product-name {
        font-size: 15px;
        color: #333;
        font-weight: 500;
        line-height: 1.4;
        margin-bottom: 5px;
        text-decoration: none;
        display: block;
    }

    .product-desc {
        font-size: 13px;
        color: #808089;
        margin-bottom: 10px;
    }

    .action-links {
        font-size: 13px;
        color: #0b74e5;
        cursor: pointer;
    }

    .action-links span:hover {
        text-decoration: underline;
    }

    .cursor-pointer {
        cursor: pointer;
    }

    .price-col {
        text-align: right;
        min-width: 120px;
        margin-left: 20px;
    }

    .price-current {
        font-size: 16px;
        font-weight: 700;
        color: #333;
        display: block;
    }

    .price-old {
        font-size: 13px;
        color: #999;
        text-decoration: line-through;
        display: block;
        margin-top: 2px;
    }

    /* Bộ đếm số lượng */
    .qty-control {
        display: flex;
        align-items: center;
        border: 1px solid #ddd;
        border-radius: 3px;
        margin-left: 30px;
        height: 32px;
    }

    .qty-btn {
        width: 28px;
        height: 100%;
        border: none;
        background: #fff;
        cursor: pointer;
        color: #787878;
        font-size: 16px;
    }

    .qty-input {
        width: 40px;
        height: 100%;
        text-align: center;
        border: none;
        border-left: 1px solid #ddd;
        border-right: 1px solid #ddd;
        outline: none;
        font-size: 14px;
    }

    .total-price-col {
        font-weight: 700;
        color: #333;
        width: 120px;
        text-align: right;
        font-size: 16px;
        margin-left: 20px;
    }

    /* --- 2 DÒNG VOUCHER Ở DƯỚI (YÊU CẦU MỚI) --- */
    .voucher-row {
        padding: 12px 20px;
        border-top: 1px solid #f0f0f0;
        /* Đường kẻ ngang */
        display: flex;
        align-items: center;
        font-size: 13px;
    }

    /* Dòng 1: Shop Voucher (Icon đỏ) */
    .icon-ticket {
        color: var(--voucher-red);
        margin-right: 10px;
        font-size: 16px;
    }

    .voucher-link {
        margin-left: 15px;
        color: #0b74e5;
        text-decoration: none;
        cursor: pointer;
    }

    /* Dòng 2: Ship Voucher (Icon xe tải xanh) */
    .icon-truck {
        color: var(--ship-green);
        margin-right: 8px;
        font-size: 16px;
        transform: scaleX(-1);
        /* Lật icon xe tải cho giống mẫu */
    }

    .ship-text {
        color: #333;
    }

    .learn-more {
        color: #0b74e5;
        text-decoration: none;
        margin-left: 5px;
        cursor: pointer;
    }

    /* --- CHECKOUT BAR (Sticky bottom hoặc cuối trang) --- */
    .checkout-section {
        background: #fff;
        padding: 20px;
        border-radius: 4px;
        margin-top: 20px;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .total-label {
        font-size: 16px;
        font-weight: 500;
    }

    .total-amount {
        font-size: 20px;
        color: var(--voucher-red);
        font-weight: 700;
        text-align: right;
    }

    .vat-text {
        font-size: 12px;
        color: #999;
        text-align: right;
        margin-bottom: 15px;
    }

    .btn-checkout-big {
        width: 100%;
        background: #0b74e5;
        /* Màu xanh chuẩn Tiki/Shopee mới */
        color: white;
        font-size: 18px;
        font-weight: 500;
        text-transform: uppercase;
        padding: 12px;
        border: none;
        border-radius: 4px;
        transition: opacity 0.3s;
    }

    .btn-checkout-big:hover {
        opacity: 0.9;
    }

    .btn-delete-selected {
        border: 1px solid #ffccd2;
        color: #d32f2f;
        background: #fff;
        border-radius: 20px;
        padding: 6px 14px;
        font-size: 13px;
        font-weight: 600;
    }

    .btn-delete-selected:hover {
        background: #fff5f5;
    }

    .voucher-apply-box {
        background: #f8fbff;
        border: 1px dashed #c9dcff;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 14px;
    }

    .voucher-feedback {
        font-size: 13px;
        margin-top: 8px;
        min-height: 18px;
    }

    .voucher-feedback.success {
        color: #0f8f45;
    }

    .voucher-feedback.error {
        color: #d32f2f;
    }

    .voucher-feedback.info {
        color: #0b74e5;
    }

    .voucher-quick-list {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .voucher-quick-item {
        border: 1px solid #d3e3ff;
        border-radius: 14px;
        padding: 4px 10px;
        font-size: 12px;
        color: #0b74e5;
        background: #fff;
        cursor: pointer;
    }

    .voucher-quick-item:hover {
        background: #edf4ff;
    }

    #clearVoucherBtn {
        display: none;
    }

    </style>
</head>

<body>

    <header>
        <div class="container d-flex align-items-center justify-content-between">
            <a href="trangchu.php" class="text-decoration-none d-flex align-items-center">
                <img src="../TrangUser/ack.png" alt="ACK Mart" class="logo-img">
                <span class="ms-3 fs-5 fw-bold text-primary border-start ps-3"
                    style="border-color: #ddd !important;">Giỏ Hàng</span>
            </a>

            <div class="search-wrapper d-none d-md-block">
                <input type="text" class="search-input"
                    placeholder="Tìm sản phẩm, danh mục hay thương hiệu mong muốn...">
                <i class="fas fa-search search-icon"></i>
            </div>

            <div class="d-flex gap-4 text-secondary align-items-center">
                <a href="trangchu.php" class="text-decoration-none text-dark">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-home fs-5 me-1"></i>
                        <span class="d-none d-lg-block">Trang chủ</span>
                    </div>
                </a>
                <!-- <div class="d-flex align-items-center"><i class="fas fa-crown fs-5 me-1"></i> <span class="d-none d-lg-block">Astra</span></div> -->
                <div class="d-flex align-items-center text-primary"><i class="fas fa-user-circle fs-3"></i></div>
            </div>
        </div>
    </header>

    <div class="cart-container container">

        <div class="bg-white p-3 rounded mb-3 d-flex align-items-center border shadow-sm" style="font-size: 14px;">
            <input type="checkbox" class="custom-checkbox mt-0 me-3" id="checkAllTop" checked
                onchange="toggleAll(this)">
            <label for="checkAllTop" class="cursor-pointer">Tất cả (<span id="count-items-head" class="fw-bold">0</span>
                sản phẩm)</label>
            <span class="ms-auto text-secondary">Đơn giá</span>
            <span class="mx-5 text-secondary d-none d-md-block">Số lượng</span>
            <span class="me-5 text-secondary d-none d-md-block">Thành tiền</span>
        </div>

        <div id="cart-list">
            <?php foreach($cart_items as $item): ?>
            <div class="cart-item-card" id="item-<?php echo (int)$item['chi_tiet_id']; ?>"
                data-product-id="<?php echo htmlspecialchars((string)$item['id'], ENT_QUOTES); ?>"
                data-product-name="<?php echo htmlspecialchars((string)$item['name'], ENT_QUOTES); ?>">

                <div class="product-main-info">
                    <input type="checkbox" class="custom-checkbox item-checkbox" checked
                        data-price="<?php echo (float)$item['price']; ?>"
                        data-id="<?php echo (int)$item['chi_tiet_id']; ?>"
                        data-product-id="<?php echo htmlspecialchars((string)$item['id'], ENT_QUOTES); ?>"
                        data-product-name="<?php echo htmlspecialchars((string)$item['name'], ENT_QUOTES); ?>"
                        onchange="calculateTotal()">

                    <div class="img-wrapper">
                        <img src="<?php echo $item['img']; ?>" class="item-img" alt="Sản phẩm"
                            onerror="this.src='https://via.placeholder.com/150'">
                    </div>

                    <div class="item-info">
                        <a href="#" class="product-name">
                            <?php if($item['id'] == 1) echo '<span class="shop-badge">OFFICIAL</span>'; ?>
                            <?php echo $item['name']; ?>
                        </a>
                        <div class="product-desc"><?php echo $item['desc']; ?></div>
                        <div class="action-links">
                            <i class="far fa-heart me-1"></i> <span>Thêm Vào Yêu Thích</span>
                            <span class="text-secondary mx-2">|</span>
                            <span class="text-danger cursor-pointer"
                                onclick="deleteFromCart(<?php echo (int)$item['chi_tiet_id']; ?>, this)">Xóa</span>
                        </div>
                    </div>

                    <div class="price-col">
                        <span class="price-current"><?php echo number_format($item['price'], 0, ',', '.'); ?>₫</span>
                        <?php if($item['old_price'] > $item['price']): ?>
                        <span class="price-old"><?php echo number_format($item['old_price'], 0, ',', '.'); ?>₫</span>
                        <?php endif; ?>
                    </div>

                    <div class="qty-control">
                        <button class="qty-btn"
                            onclick="updateQty(<?php echo (int)$item['chi_tiet_id']; ?>, -1)">-</button>
                        <input type="number" class="qty-input" id="qty-<?php echo (int)$item['chi_tiet_id']; ?>"
                            value="<?php echo $item['qty']; ?>" readonly>
                        <button class="qty-btn"
                            onclick="updateQty(<?php echo (int)$item['chi_tiet_id']; ?>, 1)">+</button>
                    </div>

                    <div class="total-price-col d-none d-md-block">
                        <span
                            id="total-<?php echo (int)$item['chi_tiet_id']; ?>"><?php echo number_format($item['price'] * $item['qty'], 0, ',', '.'); ?>₫</span>
                    </div>
                </div>

                <div class="voucher-row">
                    <i class="fas fa-ticket-alt icon-ticket"></i>
                    <span class="text-dark">
                        <?php echo isset($item['shop_voucher']) ? $item['shop_voucher'] : 'Xem tất cả Voucher'; ?>
                    </span>
                    <a href="#" class="voucher-link">Xem thêm Voucher</a>
                </div>

                <div class="voucher-row">
                    <i class="fas fa-truck-fast icon-truck"></i>
                    <span class="ship-text">Giảm 100.000đ phí vận chuyển cho đơn tối thiểu 0đ</span>
                    <a href="#" class="learn-more">Tìm hiểu thêm</a>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <div class="checkout-section">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div class="fw-bold">Hóa Đơn Của Bạn</div>
                <div class="text-primary cursor-pointer"><i class="fas fa-ticket-alt text-danger me-1"></i> Chọn hoặc
                    nhập mã</div>
            </div>

            <div class="voucher-apply-box">
                <div class="mb-2">
                    <select class="form-select" id="voucherSelect">
                        <option value="">-- Chọn voucher có sẵn --</option>
                    </select>
                </div>
                <div class="input-group">
                    <input type="text" class="form-control" id="voucherCodeInput"
                        placeholder="Nhập mã voucher (VD: SALE10)">
                    <button class="btn btn-primary" type="button" id="applyVoucherBtn">Áp dụng</button>
                    <button class="btn btn-outline-secondary" type="button" id="clearVoucherBtn">Bỏ mã</button>
                </div>
                <div class="voucher-feedback info" id="voucherFeedback">Chưa áp dụng voucher.</div>
                <div class="voucher-quick-list" id="voucherQuickList"></div>
            </div>

            <div class="row">
                <div class="col-md-7 d-none d-md-block">
                    <div class="small text-muted mt-2">
                        <i class="fas fa-info-circle me-1"></i> Phí vận chuyển sẽ được tính ở trang thanh toán.<br>
                        <i class="fas fa-check-circle me-1"></i> Bạn cũng có thể nhập mã giảm giá ở bước tiếp theo.
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="summary-row">
                        <span class="text-secondary">Tạm tính:</span>
                        <span class="fw-bold" id="sub-total">0₫</span>
                    </div>
                    <div class="summary-row">
                        <span class="text-secondary">Giảm giá:</span>
                        <span class="text-success fw-bold" id="discount-amount">0₫</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="total-label">Tổng cộng:</span>
                        <span class="total-amount" id="final-price">0₫</span>
                    </div>
                    <div class="vat-text">(Đã bao gồm VAT nếu có)</div>
                </div>
            </div>

            <button class="btn-checkout-big mt-3">Tiến hành đặt hàng</button>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="cart-events.js"></script>
</body>

</html>