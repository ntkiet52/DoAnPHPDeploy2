<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/catalog_data.php';

$q = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 8);
$limit = max(1, min($limit, 30));

try {
    $products = catalogFetchProducts();

    if ($q !== '') {
        $needle = mb_strtolower($q, 'UTF-8');
        $products = array_values(array_filter($products, static function (array $item) use ($needle): bool {
            $name = mb_strtolower((string)($item['name'] ?? ''), 'UTF-8');
            return $name !== '' && mb_strpos($name, $needle) !== false;
        }));
    }

    $results = array_slice(array_map(static function (array $item): array {
        return [
            'id' => (string)($item['id'] ?? ''),
            'name' => (string)($item['name'] ?? ''),
            'price' => (string)($item['price'] ?? ''),
            'img' => (string)($item['img'] ?? ''),
            'link' => (string)($item['link'] ?? ''),
        ];
    }, $products), 0, $limit);

    echo json_encode([
        'ok' => true,
        'query' => $q,
        'total' => count($products),
        'results' => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Không thể tìm kiếm sản phẩm lúc này.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
