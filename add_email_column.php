<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=qlhethongbanhangmini;charset=utf8mb4', 'root', '');

echo "=== Thêm cột Email vào bảng khachhang ===\n";

try {
    // Kiểm tra xem cột Email đã tồn tại chưa
    $result = $pdo->query("SHOW COLUMNS FROM khachhang LIKE 'Email'");
    if ($result->rowCount() > 0) {
        echo "Cột Email đã tồn tại!\n";
    } else {
        // Thêm cột Email
        $pdo->exec("ALTER TABLE khachhang ADD COLUMN Email VARCHAR(150) NULL AFTER MaSoThue");
        echo "✓ Đã thêm cột Email\n";
        
        // Cập nhật dữ liệu email
        $pdo->prepare("UPDATE khachhang SET Email = ? WHERE MaKhachHang = ?")->execute(['shopp7826@gmail.com', 'KH01']);
        $pdo->prepare("UPDATE khachhang SET Email = ? WHERE MaKhachHang = ?")->execute(['0023413371@student.dthu.edu.vn', 'KH02']);
        $pdo->prepare("UPDATE khachhang SET Email = ? WHERE MaKhachHang = ?")->execute(['cuonghi535@gmail.com', 'KH03']);
        echo "✓ Đã cập nhật email cho khách hàng\n";
    }
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
}

echo "\n=== Kiểm tra cấu trúc bảng sau cập nhật ===\n";
$columns = $pdo->query('SHOW COLUMNS FROM khachhang')->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== Dữ liệu khách hàng ===\n";
$customers = $pdo->query('SELECT MaKhachHang, TenKhachHang, MaSoThue, Email FROM khachhang LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
foreach($customers as $c) {
    echo json_encode($c, JSON_UNESCAPED_UNICODE) . "\n";
}
?>
