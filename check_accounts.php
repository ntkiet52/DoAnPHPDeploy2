<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=qlhethongbanhangmini;charset=utf8mb4', 'root', '');

echo "=== Khách hàng và Email ===\n";
$stmt = $pdo->query('SELECT MaKhachHang, TenKhachHang, Email FROM khachhang');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['MaKhachHang'] . ': ' . $row['TenKhachHang'] . ' => ' . ($row['Email'] ?? 'NULL') . "\n";
}

echo "\n=== Tài khoản users ===\n";
$stmt = $pdo->query('SELECT id, name, email, provider, status FROM users ORDER BY id DESC');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['id'] . ': ' . $row['name'] . ' (' . $row['provider'] . ') => ' . $row['email'] . ' [' . $row['status'] . "]\n";
}
?>
