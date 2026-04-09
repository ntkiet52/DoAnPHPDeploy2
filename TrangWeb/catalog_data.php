<?php

declare(strict_types=1);

function catalogPickValue(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row)) {
            return $row[$key];
        }
    }

    $lowerRow = array_change_key_case($row, CASE_LOWER);
    foreach ($keys as $key) {
        $lowerKey = strtolower($key);
        if (array_key_exists($lowerKey, $lowerRow)) {
            return $lowerRow[$lowerKey];
        }
    }

    return $default;
}

function catalogNormalizeText(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($converted) && $converted !== '') {
        $value = $converted;
    }

    $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    return $value;
}

function catalogCategoryItems(): array
{
    return [
        ['slug' => 'douong', 'name' => 'Đồ uống', 'img' => '../TrangSale/douong.png', 'link' => 'Trangdouong.php'],
        ['slug' => 'anvat', 'name' => 'Đồ ăn vặt', 'img' => '../TrangSale/doanvat.png', 'link' => 'Tranganvat.php'],
        ['slug' => 'banhngot', 'name' => 'Bánh ngọt', 'img' => '../TrangSale/banhngot.png', 'link' => 'Trangbanhngot.php'],
        ['slug' => 'traicay', 'name' => 'Trái cây', 'img' => '../TrangSale/traicay.png', 'link' => 'Trangtraicay.php'],
        ['slug' => 'sua', 'name' => 'Sữa', 'img' => '../TrangSale/sua.png', 'link' => 'Trangsua.php'],
        ['slug' => 'mianlien', 'name' => 'Mì ăn liền', 'img' => '../TrangSale/mianlien.png', 'link' => 'Trangmianlien.php'],
        ['slug' => 'nuocngot', 'name' => 'Nước ngọt', 'img' => '../TrangSale/nuocngot.png', 'link' => 'Trangnuocngot.php'],
        ['slug' => 'tuoisong', 'name' => 'Tươi sống', 'img' => '../TrangSale/thitsong.png', 'link' => 'Trangtuoisong.php'],
        ['slug' => 'giadung', 'name' => 'Gia dụng', 'img' => '../TrangSale/Giadung.png', 'link' => 'Tranggiadung.php'],
        ['slug' => 'mypham', 'name' => 'Mỹ phẩm', 'img' => '../TrangSale/MyPham.png', 'link' => 'Trangmypham.php'],
        ['slug' => 'kem', 'name' => 'Kem', 'img' => '../TrangSale/Kem.png', 'link' => 'Trangkem.php'],
        ['slug' => 'raucu', 'name' => 'Rau củ', 'img' => '../TrangSale/raucu.png', 'link' => 'Trangraucu.php'],
        ['slug' => 'dohop', 'name' => 'Đồ hộp', 'img' => '../TrangSale/dohop.png', 'link' => 'Trangdohop.php'],
        ['slug' => 'thucannhanh', 'name' => 'Thức ăn nhanh', 'img' => '../TrangSale/thucannhanh.png', 'link' => 'Trangthucannhanh.php'],
        ['slug' => 'giavi', 'name' => 'Gia vị', 'img' => '../TrangSale/giavi.png', 'link' => 'Tranggiavi.php'],
        ['slug' => 'bia', 'name' => 'Bia', 'img' => '../TrangSale/bia.png', 'link' => 'Trangbia.php'],
    ];
}

function catalogPageSlugMap(): array
{
    return [
        'douong.php' => 'douong',
        'trangdouong.php' => 'douong',
        'tranganvat.php' => 'anvat',
        'trangbanhngot.php' => 'banhngot',
        'trangbia.php' => 'bia',
        'trangdohop.php' => 'dohop',
        'tranggiadung.php' => 'giadung',
        'tranggiavi.php' => 'giavi',
        'trangkem.php' => 'kem',
        'trangmianlien.php' => 'mianlien',
        'trangmypham.php' => 'mypham',
        'trangnuocngot.php' => 'nuocngot',
        'trangraucu.php' => 'raucu',
        'trangsua.php' => 'sua',
        'trangthucannhanh.php' => 'thucannhanh',
        'trangtraicay.php' => 'traicay',
        'trangtuoisong.php' => 'tuoisong',
        'trangchu.php' => 'home',
    ];
}

function catalogResolvePageSlug(string $fileName): string
{
    $map = catalogPageSlugMap();
    $key = strtolower($fileName);

    return $map[$key] ?? 'home';
}

function catalogInferSlug(string $groupName, string $groupCode = ''): string
{
    $name = catalogNormalizeText($groupName);
    $code = strtoupper(trim($groupCode));

    $codeMap = [
        'NH01' => 'douong',
        'NH02' => 'anvat',
        'NH03' => 'banhngot',
        'NH04' => 'giadung',
        'NH05' => 'mypham',
    ];

    if ($code !== '' && isset($codeMap[$code])) {
        return $codeMap[$code];
    }

    $keywordMap = [
        'douong' => ['douong', 'nuoc', 'trasua', 'caf', 'coffee', 'tra'],
        'anvat' => ['anvat', 'snack', 'banhkeo'],
        'banhngot' => ['banhngot', 'banh', 'ngot'],
        'bia' => ['bia', 'beer'],
        'dohop' => ['dohop', 'hop'],
        'giadung' => ['giadung', 'dungcu', 'vpp'],
        'giavi' => ['giavi', 'gia', 'vi'],
        'kem' => ['kem', 'icecream'],
        'mianlien' => ['mianlien', 'mi', 'migoi'],
        'mypham' => ['mypham', 'cosmetic', 'skincare'],
        'nuocngot' => ['nuocngot', 'ngot'],
        'raucu' => ['raucu', 'rau', 'cu'],
        'sua' => ['sua', 'milk'],
        'thucannhanh' => ['thucannhanh', 'fastfood'],
        'traicay' => ['traicay', 'fruit'],
        'tuoisong' => ['tuoisong', 'haisan', 'thit', 'ca', 'tom'],
    ];

    foreach ($keywordMap as $slug => $keywords) {
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($name, $keyword)) {
                return $slug;
            }
        }
    }

    return 'other';
}

function catalogGuessImage(string $slug, string $name): string
{
    $normalizedName = catalogNormalizeText($name);

    if ($slug === 'mypham' || str_contains($normalizedName, 'cerave')) {
        return 'cerave.png';
    }

    if ($slug === 'tuoisong' || str_contains($normalizedName, 'tom') || str_contains($normalizedName, 'shrimp')) {
        return 'tom.png';
    }

    if (in_array($slug, ['douong', 'nuocngot', 'bia'], true)) {
        return '../SPdouong/trasua.png';
    }

    return '../TrangUser/ack.png';
}

function catalogResolveStock(array $row): int
{
    $lowerRow = array_change_key_case($row, CASE_LOWER);
    $stockFieldCandidates = ['soluongton', 'so_luong_ton', 'tonkho', 'soluong'];

    foreach ($stockFieldCandidates as $candidate) {
        if (array_key_exists($candidate, $lowerRow)) {
            return max(0, (int) catalogPickValue($row, ['soluongton', 'so_luong_ton', 'tonkho', 'soluong'], 0));
        }
    }

    $tongNhap = (int) catalogPickValue($row, ['tongnhap', 'tong_nhap'], 0);
    $tongXuat = (int) catalogPickValue($row, ['tongxuat', 'tong_xuat'], 0);

    return max(0, $tongNhap - $tongXuat);
}

function catalogFetchProducts(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];

    $dbHost = '127.0.0.1';
    $dbName = 'qlhethongbanhangmini';
    $dbUser = 'root';
    $dbPass = '';

    try {
        $pdo = new PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $rows = $pdo->query(
            'SELECT hh.*, nh.MaNhomHang AS NhomHangMa, nh.TenNhomHang AS NhomHangTen,
                    COALESCE(nhap.TongNhap, 0) AS TongNhap,
                    COALESCE(xuat.TongXuat, 0) AS TongXuat
             FROM hanghoa hh
             LEFT JOIN nhomhang nh ON nh.MaNhomHang = hh.MaNhomHang
             LEFT JOIN (
                 SELECT MaHang, COALESCE(SUM(SoLuongNhap), 0) AS TongNhap
                 FROM chitietnhaphang
                 GROUP BY MaHang
             ) nhap ON nhap.MaHang = hh.MaHang
             LEFT JOIN (
                 SELECT MaHang, COALESCE(SUM(SoLuongPX), 0) AS TongXuat
                 FROM chitietphieuxuat
                 GROUP BY MaHang
             ) xuat ON xuat.MaHang = hh.MaHang'
        )->fetchAll();

        foreach ($rows as $row) {
            $id = (string) catalogPickValue($row, ['mahang', 'ma_hang', 'idhanghoa', 'id'], '');
            $name = (string) catalogPickValue($row, ['tenhang', 'ten_hang', 'tensp', 'tensanpham', 'name'], '');
            $groupName = (string) catalogPickValue($row, ['tennhomhang', 'ten_nhom_hang', 'nhomhangten', 'NhomHangTen'], '');
            $groupCode = (string) catalogPickValue($row, ['manhomhang', 'ma_nhom_hang', 'nhomhangma', 'NhomHangMa'], '');
            $unit = (string) catalogPickValue($row, ['dvt', 'donvitinh', 'don_vi_tinh'], '');
            $price = (float) catalogPickValue($row, ['sotiencothue', 'so_tien_co_thue', 'giacothue', 'gia_co_thue'], 0);
            if ($price <= 0) {
                $donGia = (float) catalogPickValue($row, ['dongia', 'don_gia', 'giaban', 'gia'], 0);
                $vatRaw = (string) catalogPickValue($row, ['vat', 'thuevat', 'thue_vat'], '0');
                $vatPercent = (float) str_replace('%', '', str_replace(',', '.', $vatRaw));
                $price = $donGia > 0 ? $donGia * (1 + ($vatPercent / 100)) : 0;
            }
            $vat = (float) str_replace('%', '', (string) catalogPickValue($row, ['vat', 'thuevat', 'thue_vat'], 0));
            $stock = catalogResolveStock($row);

            if ($id === '' || $name === '') {
                continue;
            }

            if ($price <= 0) {
                $price = 10000;
            }

            $slug = catalogInferSlug($groupName, $groupCode);
            $oldPrice = (int) round($price * 1.18);

            $cache[] = [
                'id' => $id,
                'slug' => $slug,
                'name' => $name,
                'unit' => $unit,
                'group_name' => $groupName,
                'group_code' => $groupCode,
                'price_raw' => (int) round($price),
                'old_price_raw' => $oldPrice,
                'price' => number_format((int) round($price), 0, ',', '.') . ' ₫',
                'old' => number_format($oldPrice, 0, ',', '.') . ' ₫',
                'discount' => '-10%',
                'vat' => $vat,
                'stock' => $stock,
                'img' => catalogGuessImage($slug, $name),
                'link' => 'drink-detail.php?id=' . urlencode($id),
            ];
        }
    } catch (Throwable $e) {
        // keep fallback empty array; pages will render safely
    }

    return $cache;
}

function catalogSplitRows(array $products): array
{
    return [
        'soft_drinks' => array_slice($products, 0, 4),
        'beers' => array_slice($products, 4, 4),
        'gifts' => array_slice($products, 8, 4),
        'fresh_foods' => array_slice($products, 12, 4),
        'household' => array_slice($products, 16, 4),
    ];
}

function catalogFallbackProducts(): array
{
    return [
        [
            'id' => 'SPF01',
            'slug' => 'douong',
            'name' => 'Trà sữa truyền thống',
            'price_raw' => 20000,
            'old_price_raw' => 25000,
            'price' => '20.000 ₫',
            'old' => '25.000 ₫',
            'discount' => '-10%',
            'stock' => 10,
            'img' => '../SPdouong/trasua.png',
            'link' => 'drink-detail.php?sku=trasua',
        ],
        [
            'id' => 'SPF02',
            'slug' => 'mypham',
            'name' => 'Sữa rửa mặt CeraVe',
            'price_raw' => 390000,
            'old_price_raw' => 450000,
            'price' => '390.000 ₫',
            'old' => '450.000 ₫',
            'discount' => '-10%',
            'stock' => 8,
            'img' => 'cerave.png',
            'link' => 'drink-detail.php?sku=trasua',
        ],
        [
            'id' => 'SPF03',
            'slug' => 'tuoisong',
            'name' => 'Tôm thẻ tươi sống',
            'price_raw' => 180000,
            'old_price_raw' => 220000,
            'price' => '180.000 ₫',
            'old' => '220.000 ₫',
            'discount' => '-10%',
            'stock' => 12,
            'img' => 'tom.png',
            'link' => 'drink-detail.php?sku=trasua',
        ],
    ];
}

function loadCatalogDataForPage(string $fileName): array
{
    $pageSlug = catalogResolvePageSlug($fileName);
    $categories = catalogCategoryItems();
    $products = catalogFetchProducts();

    if (count($products) === 0) {
        $products = catalogFallbackProducts();
    }

    $availableProducts = array_values(array_filter($products, static function (array $item): bool {
        return (int) ($item['stock'] ?? 0) > 0;
    }));

    $filtered = $availableProducts;
    if ($pageSlug !== 'home') {
        $filtered = array_values(array_filter($availableProducts, static function (array $item) use ($pageSlug): bool {
            return ($item['slug'] ?? '') === $pageSlug;
        }));
    }

    $split = catalogSplitRows($filtered);

    return array_merge($split, [
        'categories' => $categories,
        'all_products' => $filtered,
        'page_slug' => $pageSlug,
    ]);
}

function loadFeaturedProductsFromDb(int $limit = 8): array
{
    $products = catalogFetchProducts();
    if (count($products) === 0) {
        $products = catalogFallbackProducts();
    }

    $products = array_values(array_filter($products, static function (array $item): bool {
        return (int) ($item['stock'] ?? 0) > 0;
    }));

    $products = array_slice($products, 0, max(1, $limit));

    return array_map(static function (array $item): array {
        $rating = 5;
        if (($item['slug'] ?? '') === 'mypham') {
            $rating = 4;
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'price' => (string) ($item['price'] ?? '0 ₫'),
            'old_price' => (string) ($item['old'] ?? '0 ₫'),
            'discount' => (string) ($item['discount'] ?? '-10%'),
            'image' => (string) ($item['img'] ?? '../TrangUser/ack.png'),
            'rating' => $rating,
            'link' => (string) ($item['link'] ?? '#'),
            'price_raw' => (int) ($item['price_raw'] ?? 0),
        ];
    }, $products);
}

function loadProductDetailById(string $productId): ?array
{
    $productId = trim($productId);
    if ($productId === '') {
        return null;
    }

    foreach (catalogFetchProducts() as $item) {
        if (strcasecmp((string) ($item['id'] ?? ''), $productId) === 0) {
            $rating = ($item['slug'] ?? '') === 'mypham' ? 4.2 : 4.5;
            return [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'price' => (int) ($item['price_raw'] ?? 0),
                'old_price' => (int) ($item['old_price_raw'] ?? 0),
                'sold' => '1.2k',
                'rating' => $rating,
                'image' => (string) ($item['img'] ?? '../TrangUser/ack.png'),
                'desc_list' => [
                    'Mã sản phẩm: ' . (string) ($item['id'] ?? ''),
                    'Tên sản phẩm: ' . (string) ($item['name'] ?? ''),
                    'Nhóm hàng: ' . (string) ($item['group_name'] ?? 'Chưa phân loại'),
                    'Đơn vị: ' . (string) (($item['unit'] ?? '') !== '' ? $item['unit'] : 'Đang cập nhật'),
                    'Số lượng tồn: ' . (string) ($item['stock'] ?? 0),
                ],
            ];
        }
    }

    return null;
}