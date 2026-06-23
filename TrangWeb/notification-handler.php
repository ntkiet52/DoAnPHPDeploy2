<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function respondNotify(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? 'list'));
$isLoggedIn = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentUserName = trim((string) ($_SESSION['user_name'] ?? ''));
$currentRole = strtolower(trim((string) ($_SESSION['user_role'] ?? 'guest')));

// DEBUG: Log user info
error_log("DEBUG NOTIFY - UserId: {$currentUserId}, UserName: {$currentUserName}, Role: {$currentRole}, Action: {$action}");

$readIdsRaw = $_SESSION['notification_read_ids'] ?? [];
if (!is_array($readIdsRaw)) {
    $readIdsRaw = [];
}
$readIds = array_values(array_unique(array_filter(array_map(static function ($value): string {
    return trim((string) $value);
}, $readIdsRaw), static function (string $value): bool {
    return $value !== '';
})));

if ($action === 'mark_one') {
    $notificationId = (int) ($_POST['notification_id'] ?? $_GET['notification_id'] ?? 0);
    if ($notificationId <= 0) {
        respondNotify(false, 'Thiếu notification_id hợp lệ.', [], 400);
    }

    try {
        $pdo = new PDO(
                'mysql:host=127.0.0.1;dbname=qlhethongbanhangmini;charset=utf8mb4',
                'root',
                '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ]
        );

        $markStmt = $pdo->prepare(
            'UPDATE thongbao SET DaDoc = 1, NgayDoc = CURRENT_TIMESTAMP WHERE Id = :id'
        );
        $markStmt->execute([':id' => $notificationId]);

        respondNotify(true, 'Đã đánh dấu đã đọc thông báo.', [
            'notification_id' => $notificationId,
        ]);
    } catch (Throwable $e) {
        respondNotify(false, 'Không thể cập nhật thông báo: ' . $e->getMessage(), [], 500);
    }
}

if ($action === 'mark_seen') {
    respondNotify(true, 'Đã đánh dấu đã xem.', [
        'unseen_count' => 0,
    ]);
}

if (!$isLoggedIn) {
    respondNotify(true, 'Người dùng chưa đăng nhập.', [
        'is_logged_in' => false,
        'notifications' => [],
        'total_count' => 0,
        'unseen_count' => 0,
    ]);
}

try {
    $pdo = new PDO(
        'mysql:host=webbanhang-mysql.mysql.database.azure.com;dbname=qlhethongbanhangmini;charset=utf8mb4',
        'webbanhang123',
        'thanhkiet1234ACK@',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS hanghoa_binhluan (
            Id INT NOT NULL AUTO_INCREMENT,
            MaHang VARCHAR(10) NOT NULL,
            ParentId INT NULL,
            TenNguoiDung VARCHAR(120) NOT NULL,
            NoiDung TEXT NOT NULL,
            HinhAnh VARCHAR(255) NULL,
            SoSao TINYINT NOT NULL DEFAULT 0,
            NguoiDungId INT NULL,
            VaiTroNguoiDung VARCHAR(30) NULL,
            NgayTao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (Id),
            KEY idx_hanghoa_binhluan_mahang (MaHang),
            KEY idx_hanghoa_binhluan_parent (ParentId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    try {
        $pdo->exec("ALTER TABLE hanghoa_binhluan ADD COLUMN IF NOT EXISTS NguoiDungId INT NULL AFTER SoSao");
    } catch (Throwable $e) {
        // ignore for older MariaDB
    }

    try {
        $pdo->exec("ALTER TABLE hanghoa_binhluan ADD COLUMN IF NOT EXISTS VaiTroNguoiDung VARCHAR(30) NULL AFTER NguoiDungId");
    } catch (Throwable $e) {
        // ignore for older MariaDB
    }

    // Tạo bảng thông báo để lưu trữ lâu dài
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS thongbao (
            Id INT NOT NULL AUTO_INCREMENT,
            NguoiDungId INT,
            TenNguoiDung VARCHAR(120),
            LoaiThongBao VARCHAR(30) NOT NULL,
            TieuDe VARCHAR(255) NOT NULL,
            NoiDung TEXT,
            MaHang VARCHAR(10),
            TenHang VARCHAR(255),
            IdBinhLuan INT,
            IdPhanHoi INT,
            URL TEXT,
            DaDoc TINYINT NOT NULL DEFAULT 0,
            NgayTao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            NgayDoc TIMESTAMP NULL,
            PRIMARY KEY (Id),
            KEY idx_thongbao_user (NguoiDungId),
            KEY idx_thongbao_tendung (TenNguoiDung),
            KEY idx_thongbao_loai (LoaiThongBao),
            KEY idx_thongbao_dadoc (DaDoc),
            KEY idx_thongbao_ngaytao (NgayTao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $items = [];

    // Lấy thông báo từ database
    if ($currentUserId > 0) {
        // User đã login - lấy theo NguoiDungId
        $notifySql = 'SELECT Id, LoaiThongBao, TieuDe, NoiDung, MaHang, TenHang, URL, DaDoc, NgayTao
                      FROM thongbao
                      WHERE NguoiDungId = :uid
                      ORDER BY DaDoc ASC, NgayTao DESC
                      LIMIT 50';
        
        $notifyParams = [':uid' => $currentUserId];
    } elseif ($currentUserName !== '') {
        // User là guest - lấy theo TenNguoiDung
        $notifySql = 'SELECT Id, LoaiThongBao, TieuDe, NoiDung, MaHang, TenHang, URL, DaDoc, NgayTao
                      FROM thongbao
                      WHERE LOWER(TenNguoiDung) = LOWER(:uname)
                      ORDER BY DaDoc ASC, NgayTao DESC
                      LIMIT 50';
        
        $notifyParams = [':uname' => $currentUserName];
    } else {
        // Chưa login và không có tên - không có thông báo
        respondNotify(true, 'Lấy thông báo thành công.', [
            'is_logged_in' => false,
            'notifications' => [],
            'total_count' => 0,
            'unseen_count' => 0,
        ]);
    }
    
    $notifyStmt = $pdo->prepare($notifySql);
    $notifyStmt->execute($notifyParams);
    
    foreach ($notifyStmt->fetchAll() as $row) {
        $items[] = [
            'id' => (int)($row['Id'] ?? 0),
            'type' => (string)($row['LoaiThongBao'] ?? 'other'),
            'title' => (string)($row['TieuDe'] ?? ''),
            'content' => (string)($row['NoiDung'] ?? ''),
            'product_id' => (string)($row['MaHang'] ?? ''),
            'product_name' => (string)($row['TenHang'] ?? ''),
            'url' => (string)($row['URL'] ?? ''),
            'created_at' => (string)($row['NgayTao'] ?? ''),
            'is_read' => (int)($row['DaDoc'] ?? 0) === 1,
        ];
    }

    // Tính số thông báo chưa đọc
    $unseenCount = 0;
    foreach ($items as $item) {
        if (!($item['is_read'] ?? false)) {
            $unseenCount++;
        }
    }

    respondNotify(true, 'Lấy thông báo thành công.', [
        'is_logged_in' => true,
        'notifications' => $items,
        'total_count' => count($items),
        'unseen_count' => $unseenCount,
    ]);
} catch (Throwable $e) {
    respondNotify(false, 'Không thể lấy thông báo: ' . $e->getMessage(), [
        'is_logged_in' => $isLoggedIn,
        'notifications' => [],
        'total_count' => 0,
        'unseen_count' => 0,
    ], 500);
}