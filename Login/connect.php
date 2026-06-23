<?php
$host = "webbanhang-mysql.mysql.database.azure.com";
$user = "webbanhang123";
$pass = "thanhkiet1234ACK@";
$db   = "qlhethongbanhangmini";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
mysqli_real_connect($conn, $host, $user, $pass, $db, 3306, NULL, MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'msg' => $conn->connect_error]));
}

$conn->set_charset("utf8mb4");
?>