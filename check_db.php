<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=qlhethongbanhangmini;charset=utf8mb4', 'root', '');

echo "=== Cột trong bảng khachhang ===\n";
$columns = $pdo->query('SHOW COLUMNS FROM khachhang')->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== Cột trong bảng users ===\n";
$columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== Sample data từ khachhang ===\n";
$customers = $pdo->query('SELECT * FROM khachhang LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
foreach($customers as $c) {
    echo json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== Dữ liệu trong bảng users ===\n";
$users = $pdo->query('SELECT id, name, email, status FROM users LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
foreach($users as $u) {
    echo json_encode($u, JSON_UNESCAPED_UNICODE) . "\n";
}
?>
