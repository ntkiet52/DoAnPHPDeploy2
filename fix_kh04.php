<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=qlhethongbanhangmini;charset=utf8mb4', 'root', '');

// Cập nhật Email cho KH04
$pdo->prepare("UPDATE khachhang SET Email = ? WHERE MaKhachHang = ?")->execute(['webphp27@gmail.com', 'KH04']);
echo "✓ Cập nhật Email cho KH04: webphp27@gmail.com\n";

// Verify
$row = $pdo->prepare("SELECT MaKhachHang, Email FROM khachhang WHERE MaKhachHang = ?");
$row->execute(['KH04']);
$result = $row->fetch();
echo "Kiểm tra: KH04 Email = " . $result['Email'] . "\n";

echo "\n=== Khách hàng và Email sau cập nhật ===\n";
$stmt = $pdo->query('SELECT MaKhachHang, TenKhachHang, Email FROM khachhang');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['MaKhachHang'] . ': ' . $row['TenKhachHang'] . ' => ' . ($row['Email'] ?? 'NULL') . "\n";
}
?>
