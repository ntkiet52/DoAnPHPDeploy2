<?php
/**
 * PRODUCT HELPER FUNCTIONS
 * Hỗ trợ tải sản phẩm từ database cho tất cả các trang
 */

if (!isset($conn)) {
    require_once __DIR__ . '/../Login/connect.php';
}

/**
 * Lấy danh sách sản phẩm
 * @param $limit số lượng sản phẩm (default 20)
 * @return array danh sách sản phẩm
 */
function getProducts($limit = 20) {
    global $conn;
    
    $products = [];
    $sql = "SELECT MaHang as id, TenHang as name, DonGia as price, SoTienCoThue as old_price, HinhAnh as img 
            FROM hanghoa LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'price' => (int)$row['price'],
            'old_price' => (int)$row['old_price'],
            'img' => $row['img'],
            'discount' => ''
        ];
    }
    
    $stmt->close();
    return $products;
}

/**
 * Format giá tiền theo VND
 * @param int $price giá tiền
 * @return string giá định dạng
 */
function formatPrice($price) {
    return number_format($price, 0, ',', '.') . '₫';
}

/**
 * Danh mục sản phẩm
 * @return array danh sách danh mục
 */
function getCategories() {
    return [
        ['name' => 'Đồ uống', 'img' => '../TrangSale/douong.png','link' => 'Trangdouong.php'],
        ['name' => 'Đồ ăn vặt', 'img' => '../TrangSale/doanvat.png','link' => 'Tranganvat.php'],
        ['name' => 'Bánh ngọt', 'img' => '../TrangSale/banhngot.png','link' => 'Trangbanhngot.php'],
        ['name' => 'Trái cây', 'img' => '../TrangSale/traicay.png','link' => 'Trangtraicay.php'],
        ['name' => 'Sữa', 'img' => '../TrangSale/sua.png','link' => 'Trangsua.php'],
        ['name' => 'Mì ăn liền', 'img' => '../TrangSale/mianlien.png','link' => 'Trangmianlien.php'],
        ['name' => 'Nước ngọt', 'img' => '../TrangSale/nuocngot.png','link' => 'Trangnuocngot.php'],
        ['name' => 'Tươi sống', 'img' => '../TrangSale/thitsong.png','link' => 'Trangtuoisong.php'],
        ['name' => 'Gia dụng', 'img' => '../TrangSale/Giadung.png','link' => 'Tranggiadung.php'],
        ['name' => 'Mỹ phẩm', 'img' => '../TrangSale/MyPham.png','link' => 'Trangmypham.php'],
        ['name' => 'Kem', 'img' => '../TrangSale/Kem.png','link' => 'Trangkem.php'],
        ['name' => 'Rau củ', 'img' => '../TrangSale/raucu.png','link' => 'Trangraucu.php'],
        ['name' => 'Đồ hộp', 'img' => '../TrangSale/dohop.png','link' => 'Trangdohop.php'],
        ['name' => 'Thức ăn nhanh', 'img' => '../TrangSale/thucannhanh.png','link' => 'Trangthucannhanh.php'],
        ['name' => 'Gia vị', 'img' => '../TrangSale/giavi.png','link' => 'Tranggiavi.php'],
        ['name' => 'Bia', 'img' => '../TrangSale/bia.png','link' => 'Trangbia.php'],
    ];
}
?>
