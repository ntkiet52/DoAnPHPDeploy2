<?php
$host = "localhost";
$user = "root";
$pass = ""; // Nếu bạn có mật khẩu MySQL thì điền vào đây
$db = "qlhethongbanhangmini";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
?>