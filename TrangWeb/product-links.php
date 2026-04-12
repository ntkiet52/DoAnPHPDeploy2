<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/catalog_data.php';

try {
    $products = catalogFetchProducts();
    $payload = array_map(static function (array $item): array {
        return [
            'id' => (string) ($item['id'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'img' => (string) ($item['img'] ?? ''),
            'link' => (string) ($item['link'] ?? ''),
        ];
    }, $products);

    echo json_encode([
        'ok' => true,
        'products' => $payload,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Không thể tải danh sách link sản phẩm.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
