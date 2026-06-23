<?php
$pdo = new PDO(
    'mysql:host=webbanhang-mysql.mysql.database.azure.com;dbname=qlhethongbanhangmini;charset=utf8mb4',
    'webbanhang123',
    'thanhkiet1234ACK@'
);

$updates = [
    ['KH01', 'shopp7826@gmail.com'],
    ['KH02', '0023413371@student.dthu.edu.vn'],
    ['KH03', 'cuonghi535@gmail.com'],
];

foreach($updates as $update) {
    $pdo->prepare("UPDATE khachhang SET MaSoThue = ? WHERE MaKhachHang = ?")
        ->execute([$update[1], $update[0]]);
    echo "Cập nhật {$update[0]}: {$update[1]}\n";
}

echo "\n=== Kiểm tra sau cập nhật ===\n";
$customers = $pdo->query('SELECT * FROM khachhang LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
foreach($customers as $c) {
    echo $c['MaKhachHang'] . " - " . $c['MaSoThue'] . "\n";
}
?>
