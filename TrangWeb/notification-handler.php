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
    $notificationId = trim((string) ($_POST['notification_id'] ?? $_GET['notification_id'] ?? ''));
    if ($notificationId === '') {
        respondNotify(false, 'Thiếu notification_id.', [], 400);
    }

    if (!in_array($notificationId, $readIds, true)) {
        $readIds[] = $notificationId;
        $_SESSION['notification_read_ids'] = $readIds;
    }

    respondNotify(true, 'Đã đánh dấu đã đọc thông báo.', [
        'notification_id' => $notificationId,
    ]);
}

if ($action === 'mark_seen') {
    $_SESSION['notification_seen_at'] = date('Y-m-d H:i:s');
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
        'mysql:host=127.0.0.1;dbname=qlhethongbanhangmini;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
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

    $items = [];

    // 1) Phản hồi vào bình luận của tôi (khách/admin đều tính)
    $replyStmt = $pdo->prepare(
        'SELECT child.Id,
                child.MaHang,
                child.TenNguoiDung,
                child.NoiDung,
                child.VaiTroNguoiDung,
                child.NgayTao,
                parent.Id AS ParentCommentId,
                parent.TenNguoiDung AS ParentAuthor,
                hh.TenHang
         FROM hanghoa_binhluan child
         INNER JOIN hanghoa_binhluan parent ON parent.Id = child.ParentId
         LEFT JOIN hanghoa hh ON hh.MaHang = child.MaHang
         WHERE (
                parent.NguoiDungId = :uid
                OR (parent.NguoiDungId IS NULL AND LOWER(parent.TenNguoiDung) = LOWER(:uname))
               )
           AND (child.NguoiDungId IS NULL OR child.NguoiDungId <> :uid2)
         ORDER BY child.NgayTao DESC
         LIMIT 40'
    );

    $replyStmt->execute([
        ':uid' => $currentUserId,
        ':uid2' => $currentUserId,
        ':uname' => $currentUserName,
    ]);

    foreach ($replyStmt->fetchAll() as $row) {
        $senderRole = strtolower(trim((string) ($row['VaiTroNguoiDung'] ?? 'user')));
        $senderLabel = $senderRole === 'admin' ? 'Admin' : 'Khách hàng';

        $items[] = [
            'id' => 'reply_' . (int) ($row['Id'] ?? 0),
            'type' => 'reply',
            'title' => $senderLabel . ' đã phản hồi bình luận của bạn',
            'content' => trim((string) ($row['NoiDung'] ?? '')),
            'sender_name' => (string) ($row['TenNguoiDung'] ?? 'Người dùng'),
            'sender_role' => $senderRole,
            'product_id' => (string) ($row['MaHang'] ?? ''),
            'product_name' => (string) ($row['TenHang'] ?? ''),
            'url' => 'drink-detail.php?id=' . rawurlencode((string) ($row['MaHang'] ?? '')) . '#reviews',
            'created_at' => (string) ($row['NgayTao'] ?? ''),
        ];
    }

    // 2) Riêng admin: nhận thông báo khi khách hàng gửi phản hồi mới
    if ($currentRole === 'admin') {
        $adminStmt = $pdo->query(
            "SELECT bl.Id,
                    bl.MaHang,
                    bl.TenNguoiDung,
                    bl.NoiDung,
                    bl.VaiTroNguoiDung,
                    bl.ParentId,
                    bl.NgayTao,
                    hh.TenHang
             FROM hanghoa_binhluan bl
             LEFT JOIN hanghoa hh ON hh.MaHang = bl.MaHang
             WHERE (bl.VaiTroNguoiDung IS NULL OR LOWER(bl.VaiTroNguoiDung) <> 'admin')
             ORDER BY bl.NgayTao DESC
             LIMIT 40"
        );

        foreach ($adminStmt->fetchAll() as $row) {
            $isReply = (int) ($row['ParentId'] ?? 0) > 0;
            $items[] = [
                'id' => 'admin_feed_' . (int) ($row['Id'] ?? 0),
                'type' => $isReply ? 'customer-reply' : 'customer-feedback',
                'title' => $isReply
                    ? 'Khách hàng vừa phản hồi trong luồng bình luận'
                    : 'Khách hàng vừa gửi bình luận mới',
                'content' => trim((string) ($row['NoiDung'] ?? '')),
                'sender_name' => (string) ($row['TenNguoiDung'] ?? 'Khách hàng'),
                'sender_role' => 'customer',
                'product_id' => (string) ($row['MaHang'] ?? ''),
                'product_name' => (string) ($row['TenHang'] ?? ''),
                'url' => 'drink-detail.php?id=' . rawurlencode((string) ($row['MaHang'] ?? '')) . '#reviews',
                'created_at' => (string) ($row['NgayTao'] ?? ''),
            ];
        }
    }

    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    $items = array_slice($items, 0, 30);

    $visibleIds = array_values(array_filter(array_map(static function (array $item): string {
        return trim((string) ($item['id'] ?? ''));
    }, $items), static function (string $value): bool {
        return $value !== '';
    }));

    if (count($visibleIds) > 0) {
        $readIds = array_values(array_filter($readIds, static function (string $id) use ($visibleIds): bool {
            return in_array($id, $visibleIds, true);
        }));
        $_SESSION['notification_read_ids'] = $readIds;
    }

    $seenAt = trim((string) ($_SESSION['notification_seen_at'] ?? ''));
    $seenTs = $seenAt !== '' ? strtotime($seenAt) : 0;

    $unseenCount = 0;
    foreach ($items as $index => $item) {
        $itemId = trim((string) ($item['id'] ?? ''));
        $createdTs = strtotime((string) ($item['created_at'] ?? '')) ?: 0;
        $isReadByTime = $seenTs > 0 && $createdTs <= $seenTs;
        $isReadById = $itemId !== '' && in_array($itemId, $readIds, true);
        $isRead = $isReadByTime || $isReadById;

        $items[$index]['is_read'] = $isRead;

        if (!$isRead) {
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