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

function catalogCategoryNameSlugMap(): array
{
    $map = [];

    foreach (catalogCategoryItems() as $item) {
        $name = (string) ($item['name'] ?? '');
        $slug = (string) ($item['slug'] ?? '');

        if ($name === '' || $slug === '') {
            continue;
        }

        $map[catalogNormalizeText($name)] = $slug;
    }

    return $map;
}

function catalogCategoryAliasSlugMap(): array
{
    return [
        // Đồ uống
        'douong' => 'douong',
        'thucuong' => 'douong',
        'douuong' => 'douong',

        // Đồ ăn vặt
        'anvat' => 'anvat',
        'doanvat' => 'anvat',
        'snackanvat' => 'anvat',
        'snack' => 'anvat',

        // Bánh ngọt
        'banhngot' => 'banhngot',

        // Trái cây
        'traicay' => 'traicay',

        // Sữa
        'sua' => 'sua',

        // Mì ăn liền
        'mianlien' => 'mianlien',
        'mienlien' => 'mianlien',
        'migoi' => 'mianlien',

        // Nước ngọt
        'nuocngot' => 'nuocngot',

        // Tươi sống
        'tuoisong' => 'tuoisong',
        'thitca' => 'tuoisong',
        'thithai' => 'tuoisong',

        // Gia dụng
        'giadung' => 'giadung',

        // Mỹ phẩm
        'mypham' => 'mypham',

        // Kem
        'kem' => 'kem',

        // Rau củ
        'raucu' => 'raucu',

        // Đồ hộp
        'dohop' => 'dohop',

        // Thức ăn nhanh
        'thucannhanh' => 'thucannhanh',
        'doannhanh' => 'thucannhanh',

        // Gia vị
        'giavi' => 'giavi',

        // Bia
        'bia' => 'bia',
        'douongcocon' => 'bia',
    ];
}

function catalogCategoryCodeSlugMap(): array
{
    return [
        'NH01' => 'douong',
        'NH02' => 'anvat',
        'NH03' => 'banhngot',
        'NH04' => 'traicay',
        'NH05' => 'sua',
        'NH06' => 'mianlien',
        'NH07' => 'nuocngot',
        'NH08' => 'giadung',
        'NH09' => 'raucu',
        'NH10' => 'tuoisong',
        'NH11' => 'kem',
        'NH14' => 'thucannhanh',
        'NH15' => 'mypham',
        'NH16' => 'bia',
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

    $nameMap = catalogCategoryNameSlugMap();
    if ($name !== '' && isset($nameMap[$name])) {
        return $nameMap[$name];
    }

    $aliasMap = catalogCategoryAliasSlugMap();
    if ($name !== '' && isset($aliasMap[$name])) {
        return $aliasMap[$name];
    }

    $codeMap = catalogCategoryCodeSlugMap();
    if ($code !== '' && isset($codeMap[$code])) {
        return $codeMap[$code];
    }

    return 'other';
}

function catalogNormalizeImagePath(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '../TrangUser/ack.png';
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path) ?? $path;

    if (preg_match('#^(https?:)?//#i', $path) === 1) {
        return $path;
    }

    return $path;
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
            $image = (string) catalogPickValue($row, ['hinhanh', 'hinh_anh', 'image', 'img'], '');
            $donGia = (float) catalogPickValue($row, ['dongia', 'don_gia', 'giaban', 'gia'], 0);
            $vatRaw = (string) catalogPickValue($row, ['vat', 'thuevat', 'thue_vat'], '0');
            $vatPercent = (float) str_replace('%', '', str_replace(',', '.', $vatRaw));
            $computedPrice = $donGia > 0 ? $donGia * (1 + ($vatPercent / 100)) : 0;
            $storedTaxedPrice = (float) catalogPickValue($row, ['sotiencothue', 'so_tien_co_thue', 'giacothue', 'gia_co_thue'], 0);
            $price = $computedPrice > 0 ? $computedPrice : $storedTaxedPrice;
            $vat = (float) str_replace('%', '', (string) catalogPickValue($row, ['vat', 'thuevat', 'thue_vat'], 0));
            $stock = catalogResolveStock($row);

            if ($id === '' || $name === '') {
                continue;
            }

            if ($price <= 0) {
                continue;
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
                'img' => catalogNormalizeImagePath($image),
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

function catalogResolveCurrentPage(): int
{
    $raw = $_GET['page'] ?? 1;
    $page = (int) $raw;

    return $page > 0 ? $page : 1;
}

function catalogBuildPageUrl(int $targetPage): string
{
    $targetPage = max(1, $targetPage);
    $query = $_GET;
    $query['page'] = $targetPage;

    return '?' . http_build_query($query);
}

function catalogRenderPagination(array $pagination): string
{
    $totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
    $currentPage = max(1, min((int) ($pagination['current_page'] ?? 1), $totalPages));

    if ($totalPages <= 1) {
        return '';
    }

    $html = '<div class="pagination-container">';

    if ($currentPage > 1) {
        $html .= '<a href="' . htmlspecialchars(catalogBuildPageUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') . '" class="page-link-custom page-arrow"><i class="fas fa-chevron-left"></i></a>';
    } else {
        $html .= '<span class="page-link-custom page-arrow" style="opacity:.45;pointer-events:none;"><i class="fas fa-chevron-left"></i></span>';
    }

    $pagesToRender = [];

    if ($totalPages <= 7) {
        for ($page = 1; $page <= $totalPages; $page++) {
            $pagesToRender[] = $page;
        }
    } else {
        if ($currentPage <= 3) {
            $pagesToRender = [1, 2, 3, 4, '...', $totalPages];
        } elseif ($currentPage >= $totalPages - 2) {
            $pagesToRender = [1, '...', $totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages];
        } else {
            $pagesToRender = [1, '...', $currentPage - 1, $currentPage, $currentPage + 1, '...', $totalPages];
        }

        $pagesToRender = array_values(array_filter($pagesToRender, static function ($token) use ($totalPages) {
            if ($token === '...') {
                return true;
            }

            return is_int($token) && $token >= 1 && $token <= $totalPages;
        }));

        $deduped = [];
        $lastToken = null;
        foreach ($pagesToRender as $token) {
            if ($token === '...' && $lastToken === '...') {
                continue;
            }
            $deduped[] = $token;
            $lastToken = $token;
        }
        $pagesToRender = $deduped;
    }

    foreach ($pagesToRender as $token) {
        if ($token === '...') {
            $html .= '<span class="page-link-custom" style="pointer-events:none;opacity:.7;">...</span>';
            continue;
        }

        $page = (int) $token;
        $activeClass = $page === $currentPage ? ' active' : '';
        $html .= '<a href="' . htmlspecialchars(catalogBuildPageUrl($page), ENT_QUOTES, 'UTF-8') . '" class="page-link-custom' . $activeClass . '">' . $page . '</a>';
    }

    if ($currentPage < $totalPages) {
        $html .= '<a href="' . htmlspecialchars(catalogBuildPageUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') . '" class="page-link-custom page-arrow"><i class="fas fa-chevron-right"></i></a>';
    } else {
        $html .= '<span class="page-link-custom page-arrow" style="opacity:.45;pointer-events:none;"><i class="fas fa-chevron-right"></i></span>';
    }

    $html .= '</div>';

    return $html;
}

function loadCatalogDataForPage(string $fileName): array
{
    $pageSlug = catalogResolvePageSlug($fileName);
    $categories = catalogCategoryItems();
    $products = catalogFetchProducts();

    $filtered = array_values($products);
    if ($pageSlug !== 'home') {
        $filtered = array_values(array_filter($products, static function (array $item) use ($pageSlug): bool {
            return ($item['slug'] ?? '') === $pageSlug;
        }));
    }

    $perPage = 20;
    $totalItems = count($filtered);
    $totalPages = max(1, (int) ceil($totalItems / $perPage));
    $currentPage = min(catalogResolveCurrentPage(), $totalPages);
    $offset = ($currentPage - 1) * $perPage;
    $pagedProducts = array_slice($filtered, $offset, $perPage);

    $split = catalogSplitRows($pagedProducts);

    return array_merge($split, [
        'categories' => $categories,
        'all_products' => $pagedProducts,
        'all_products_total' => $totalItems,
        'pagination' => [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'per_page' => $perPage,
        ],
        'page_slug' => $pageSlug,
    ]);
}

function loadFeaturedProductsFromDb(int $limit = 8): array
{
    $products = catalogFetchProducts();

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

function catalogDescriptionCategoryMeta(string $slug): array
{
    $slug = trim(strtolower($slug));

    $map = [
        'douong' => [
            'group' => 'Đồ uống',
            'origin' => 'Việt Nam',
            'ingredients' => 'Nước tinh khiết, đường/mật ngọt, hương liệu thực phẩm và phụ gia trong ngưỡng cho phép.',
            'material' => 'Chai/lon thực phẩm (PET hoặc nhôm) đạt chuẩn tiếp xúc thực phẩm.',
            'benefit' => 'Giải khát nhanh, bổ sung năng lượng tức thời, tiện mang theo.',
            'uses' => 'Dùng trực tiếp sau khi mở nắp, ngon hơn khi uống lạnh từ 4–8°C.',
            'storage' => 'Bảo quản nơi khô ráo, tránh ánh nắng trực tiếp; nên dùng ngay trong ngày sau khi mở.',
            'note' => 'Không dùng nếu sản phẩm có dấu hiệu biến dạng bao bì hoặc đổi mùi vị.',
        ],
        'anvat' => [
            'group' => 'Đồ ăn vặt',
            'origin' => 'Việt Nam',
            'ingredients' => 'Tinh bột ngũ cốc/khoai, dầu thực vật, gia vị và phụ gia thực phẩm theo tiêu chuẩn.',
            'material' => 'Bao bì màng ghép thực phẩm, chống ẩm giúp giữ độ giòn.',
            'benefit' => 'Ăn nhẹ tiện lợi, bổ sung năng lượng nhanh giữa buổi.',
            'uses' => 'Dùng trực tiếp, phù hợp ăn nhẹ giữa buổi hoặc dùng kèm các loại nước giải khát.',
            'storage' => 'Đậy kín sau khi mở gói để giữ độ giòn, tránh nơi ẩm và nhiệt độ cao.',
            'note' => 'Nên sử dụng trong thời gian ngắn sau khi mở để đảm bảo hương vị tốt nhất.',
        ],
        'banhngot' => [
            'group' => 'Bánh ngọt',
            'origin' => 'Việt Nam',
            'ingredients' => 'Bột mì, đường, trứng/sữa, chất béo thực vật và hương tự nhiên tùy loại bánh.',
            'material' => 'Đóng gói khay/hộp hoặc túi thực phẩm, đảm bảo vệ sinh.',
            'benefit' => 'Dùng cho bữa phụ, tráng miệng, tiệc nhẹ và đãi khách.',
            'uses' => 'Dùng trực tiếp, thích hợp cho bữa phụ hoặc tiệc nhẹ.',
            'storage' => 'Bảo quản nơi thoáng mát; với sản phẩm tươi nên dùng trong ngày và bảo quản mát.',
            'note' => 'Tránh để gần nguồn nhiệt hoặc ánh nắng trực tiếp để không ảnh hưởng chất lượng.',
        ],
        'bia' => [
            'group' => 'Đồ uống có cồn',
            'origin' => 'Việt Nam',
            'ingredients' => 'Nước, malt đại mạch, hoa bia, men bia; có thể thêm hương trái cây tùy dòng.',
            'material' => 'Lon nhôm/chai thủy tinh đạt chuẩn thực phẩm.',
            'benefit' => 'Thưởng thức trong các dịp gặp gỡ, tiệc tùng với khẩu vị đặc trưng.',
            'uses' => 'Dùng trực tiếp, ngon hơn khi ướp lạnh; sử dụng có trách nhiệm.',
            'storage' => 'Bảo quản nơi khô ráo, thoáng mát, tránh rung lắc mạnh và ánh nắng.',
            'note' => 'Không sử dụng cho người dưới 18 tuổi, phụ nữ mang thai và người lái xe.',
        ],
        'traicay' => [
            'group' => 'Trái cây',
            'origin' => 'Việt Nam',
            'ingredients' => '100% trái cây tươi theo mùa, không pha trộn công nghiệp.',
            'material' => 'Đóng trong túi/lưới/hộp thực phẩm tùy loại trái cây.',
            'benefit' => 'Bổ sung vitamin, khoáng chất và chất xơ tự nhiên cho cơ thể.',
            'uses' => 'Rửa sạch trước khi dùng; có thể ăn trực tiếp hoặc chế biến thành món tráng miệng.',
            'storage' => 'Bảo quản ngăn mát tủ lạnh để giữ độ tươi; dùng sớm sau khi mua.',
            'note' => 'Sản phẩm tự nhiên có thể chênh lệch nhẹ về màu sắc/kích thước theo mùa vụ.',
        ],
        'sua' => [
            'group' => 'Sữa và chế phẩm',
            'origin' => 'Việt Nam',
            'ingredients' => 'Sữa và các vi chất bổ sung (canxi, vitamin) tùy dòng sản phẩm.',
            'material' => 'Hộp giấy/chai nhựa đạt chuẩn an toàn thực phẩm.',
            'benefit' => 'Hỗ trợ bổ sung dinh dưỡng, phù hợp cho trẻ em và người trưởng thành.',
            'uses' => 'Lắc nhẹ trước khi dùng; phù hợp dùng trong bữa phụ hoặc bổ sung dinh dưỡng hằng ngày.',
            'storage' => 'Bảo quản nơi khô mát; sản phẩm thanh trùng cần giữ lạnh theo hướng dẫn.',
            'note' => 'Kiểm tra kỹ hạn sử dụng trước khi dùng.',
        ],
        'mianlien' => [
            'group' => 'Mì ăn liền',
            'origin' => 'Việt Nam',
            'ingredients' => 'Sợi mì từ bột mì/tinh bột, gói gia vị, dầu và rau sấy tùy sản phẩm.',
            'material' => 'Gói màng ghép hoặc ly giấy thực phẩm.',
            'benefit' => 'Tiết kiệm thời gian chế biến, tiện lợi cho bữa ăn nhanh.',
            'uses' => 'Chế biến theo hướng dẫn trên bao bì, có thể kết hợp rau/trứng/thịt để tăng dinh dưỡng.',
            'storage' => 'Bảo quản nơi khô ráo, tránh nơi ẩm thấp và côn trùng.',
            'note' => 'Nên dùng lượng gia vị phù hợp khẩu vị cá nhân.',
        ],
        'nuocngot' => [
            'group' => 'Nước ngọt',
            'origin' => 'Việt Nam',
            'ingredients' => 'Nước, đường/chất tạo ngọt, hương liệu và CO2 (đối với nước có gas).',
            'material' => 'Chai nhựa/lon nhôm đạt tiêu chuẩn thực phẩm.',
            'benefit' => 'Giải khát tức thì, thích hợp dùng trong các buổi họp mặt.',
            'uses' => 'Uống trực tiếp, ngon hơn khi dùng lạnh.',
            'storage' => 'Để nơi khô mát; nên dùng ngay sau khi mở nắp để giữ gas và hương vị.',
            'note' => 'Sử dụng lượng phù hợp trong chế độ ăn uống cân bằng.',
        ],
        'tuoisong' => [
            'group' => 'Thực phẩm tươi sống',
            'origin' => 'Việt Nam',
            'ingredients' => 'Nguyên liệu tươi sống tự nhiên theo từng loại thịt/cá/hải sản.',
            'material' => 'Đóng khay/hút chân không hoặc túi thực phẩm chuyên dụng.',
            'benefit' => 'Cung cấp đạm, khoáng chất và dưỡng chất thiết yếu cho bữa ăn hằng ngày.',
            'uses' => 'Sơ chế sạch và nấu chín kỹ trước khi sử dụng.',
            'storage' => 'Bảo quản lạnh từ 0–4°C hoặc cấp đông tùy loại sản phẩm.',
            'note' => 'Nên chế biến sớm để đảm bảo độ tươi ngon và an toàn thực phẩm.',
        ],
        'giadung' => [
            'group' => 'Gia dụng',
            'origin' => 'Việt Nam',
            'ingredients' => 'Thành phần/chất cấu tạo tùy sản phẩm (nhựa PP/PE, inox, hợp kim, chất tẩy rửa...).',
            'material' => 'Vật liệu gia dụng bền, phù hợp mục đích sử dụng hằng ngày.',
            'benefit' => 'Hỗ trợ công việc nhà bếp và chăm sóc nhà cửa hiệu quả hơn.',
            'uses' => 'Sử dụng theo mục đích in trên bao bì, đọc kỹ hướng dẫn trước khi dùng.',
            'storage' => 'Bảo quản nơi khô thoáng, tránh xa tầm tay trẻ em nếu là hóa phẩm.',
            'note' => 'Không dùng sản phẩm khi phát hiện nứt vỡ, rò rỉ hoặc hư hỏng.',
        ],
        'mypham' => [
            'group' => 'Mỹ phẩm',
            'origin' => 'Việt Nam',
            'ingredients' => 'Hoạt chất chăm sóc da và tá dược mỹ phẩm theo công bố của nhà sản xuất.',
            'material' => 'Tuýp/chai/lọ mỹ phẩm chuyên dụng, bảo quản kín khí.',
            'benefit' => 'Hỗ trợ làm sạch, dưỡng ẩm, bảo vệ và cải thiện bề mặt da.',
            'uses' => 'Dùng theo hướng dẫn của từng dòng sản phẩm và phù hợp loại da.',
            'storage' => 'Đậy kín sau khi dùng, bảo quản nơi thoáng mát, tránh ánh nắng trực tiếp.',
            'note' => 'Nên thử trên vùng da nhỏ trước khi dùng toàn mặt/toàn thân.',
        ],
        'kem' => [
            'group' => 'Kem',
            'origin' => 'Việt Nam',
            'ingredients' => 'Sữa, đường, chất béo thực vật/sữa và hương vị tự nhiên hoặc tổng hợp.',
            'material' => 'Bao bì ly/gói/hộp kem chịu lạnh.',
            'benefit' => 'Món tráng miệng mát lạnh, phù hợp giải nhiệt và thưởng thức nhanh.',
            'uses' => 'Dùng trực tiếp sau khi mở, giữ lạnh liên tục để đảm bảo chất lượng.',
            'storage' => 'Bảo quản ngăn đông ở nhiệt độ khuyến nghị của nhà sản xuất.',
            'note' => 'Không cấp đông lại sản phẩm đã tan chảy hoàn toàn.',
        ],
        'raucu' => [
            'group' => 'Rau củ',
            'origin' => 'Việt Nam',
            'ingredients' => 'Rau củ tươi tự nhiên, không pha trộn phụ gia chế biến.',
            'material' => 'Đóng trong túi/lưới/hộp thực phẩm thông thoáng.',
            'benefit' => 'Bổ sung vitamin, khoáng chất, chất xơ giúp cân bằng dinh dưỡng.',
            'uses' => 'Rửa sạch trước khi dùng, có thể nấu chín hoặc dùng theo nhu cầu chế biến.',
            'storage' => 'Bảo quản ngăn mát, tách riêng với thực phẩm sống để hạn chế lây nhiễm chéo.',
            'note' => 'Nên sử dụng trong thời gian ngắn để giữ độ giòn và dinh dưỡng.',
        ],
        'dohop' => [
            'group' => 'Đồ hộp',
            'origin' => 'Việt Nam',
            'ingredients' => 'Thực phẩm chế biến sẵn cùng gia vị và nước sốt theo từng dòng hộp.',
            'material' => 'Lon/hộp kim loại đạt chuẩn bảo quản thực phẩm dài ngày.',
            'benefit' => 'Tiện lợi, bảo quản lâu, phù hợp dự trữ tại gia đình.',
            'uses' => 'Dùng trực tiếp hoặc hâm nóng tùy sản phẩm.',
            'storage' => 'Bảo quản nơi khô mát; sau khi mở hộp, chuyển sang dụng cụ phù hợp và giữ lạnh.',
            'note' => 'Không sử dụng nếu hộp phồng, móp mạnh hoặc rỉ sét.',
        ],
        'thucannhanh' => [
            'group' => 'Thức ăn nhanh',
            'origin' => 'Việt Nam',
            'ingredients' => 'Tinh bột, đạm, rau củ và gia vị phối trộn theo công thức từng món.',
            'material' => 'Đóng gói giấy/túi/hộp thực phẩm tiện lợi.',
            'benefit' => 'Tiện dùng, tiết kiệm thời gian chuẩn bị bữa ăn.',
            'uses' => 'Dùng trực tiếp hoặc làm nóng trước khi dùng tùy loại sản phẩm.',
            'storage' => 'Giữ kín bao bì, bảo quản theo hướng dẫn trên nhãn.',
            'note' => 'Nên kết hợp rau xanh và trái cây để cân bằng bữa ăn.',
        ],
        'giavi' => [
            'group' => 'Gia vị',
            'origin' => 'Việt Nam',
            'ingredients' => 'Muối/đường/thảo mộc hoặc hỗn hợp gia vị tùy sản phẩm.',
            'material' => 'Lọ/chai/túi đựng gia vị chuyên dụng, kín ẩm.',
            'benefit' => 'Tăng hương vị món ăn, hỗ trợ chế biến nhanh và ổn định khẩu vị.',
            'uses' => 'Nêm nếm theo khẩu vị, dùng trong chế biến hoặc ướp thực phẩm.',
            'storage' => 'Đậy kín sau khi dùng, tránh ẩm và ánh nắng trực tiếp.',
            'note' => 'Sử dụng định lượng phù hợp để đảm bảo hương vị và sức khỏe.',
        ],
    ];

    return $map[$slug] ?? [
        'group' => 'Sản phẩm tiêu dùng',
        'origin' => 'Việt Nam',
        'ingredients' => 'Thành phần theo công bố trên nhãn của nhà sản xuất.',
        'material' => 'Bao bì đạt tiêu chuẩn lưu trữ và tiếp xúc sản phẩm.',
        'benefit' => 'Phục vụ nhu cầu sử dụng hằng ngày một cách tiện lợi.',
        'uses' => 'Sử dụng theo hướng dẫn trên bao bì sản phẩm.',
        'storage' => 'Bảo quản nơi khô ráo, thoáng mát.',
        'note' => 'Vui lòng kiểm tra bao bì và hạn sử dụng trước khi dùng.',
    ];
}

function catalogBuildDetailedDescriptionList(array $item): array
{
    $name = (string) ($item['name'] ?? 'Sản phẩm');
    $id = (string) ($item['id'] ?? '');
    $unit = (string) ($item['unit'] ?? '');
    $groupName = (string) ($item['group_name'] ?? 'Chưa phân loại');
    $slug = (string) ($item['slug'] ?? 'other');
    $meta = catalogDescriptionCategoryMeta($slug);
    $unitText = $unit !== '' ? $unit : 'Theo tiêu chuẩn nhà sản xuất';

    return [
        'Tên sản phẩm: ' . $name,
        'Mã tham chiếu: ' . ($id !== '' ? $id : 'Đang cập nhật'),
        'Phân loại: ' . $groupName . ' (' . ($meta['group'] ?? 'Sản phẩm tiêu dùng') . ')',
        'Quy cách: ' . $unitText,
        'Thành phần chính: ' . ($meta['ingredients'] ?? 'Thành phần theo công bố trên nhãn của nhà sản xuất.'),
        'Chất liệu/bao bì: ' . ($meta['material'] ?? 'Bao bì đạt tiêu chuẩn lưu trữ và tiếp xúc sản phẩm.'),
        'Công dụng nổi bật: ' . ($meta['benefit'] ?? 'Phục vụ nhu cầu sử dụng hằng ngày một cách tiện lợi.'),
        'Xuất xứ tham khảo: ' . ($meta['origin'] ?? 'Việt Nam') . '.',
        'Hướng dẫn sử dụng: ' . ($meta['uses'] ?? 'Sử dụng theo hướng dẫn trên bao bì sản phẩm.'),
        'Bảo quản: ' . ($meta['storage'] ?? 'Bảo quản nơi khô ráo, thoáng mát.'),
        'Lưu ý: ' . ($meta['note'] ?? 'Vui lòng kiểm tra bao bì và hạn sử dụng trước khi dùng.'),
    ];
}

function catalogFetchDetailFromView(string $productId): ?array
{
    static $pdo = null;
    static $viewChecked = false;
    static $viewAvailable = false;

    $productId = trim($productId);
    if ($productId === '') {
        return null;
    }

    try {
        if (!$pdo instanceof PDO) {
            $pdo = new PDO(
                'mysql:host=127.0.0.1;dbname=qlhethongbanhangmini;charset=utf8mb4',
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }

        if (!$viewChecked) {
            $viewChecked = true;
            $existsStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_qlhethongbanhangmini = 'vw_product_detail_page'");
            $viewAvailable = (bool) $existsStmt->fetchColumn();
        }

        if (!$viewAvailable) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, name, unit, price_before_tax, price, vat, image, group_code, group_name, detail_description
             FROM vw_product_detail_page
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch();

        if (!is_array($row) || empty($row)) {
            return null;
        }

        return [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'unit' => (string) ($row['unit'] ?? ''),
            'price_raw' => (int) round((float) ($row['price'] ?? 0)),
            'old_price_raw' => (int) round(((float) ($row['price'] ?? 0)) * 1.18),
            'vat' => (float) str_replace('%', '', (string) ($row['vat'] ?? 0)),
            'img' => catalogNormalizeImagePath((string) ($row['image'] ?? '')),
            'group_code' => (string) ($row['group_code'] ?? ''),
            'group_name' => (string) ($row['group_name'] ?? ''),
            'detail_description' => trim((string) ($row['detail_description'] ?? '')),
            'sold' => '1.2k',
            'rating' => 4.5,
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function catalogFetchDetailedDescriptionText(string $productId): string
{
    static $pdo = null;
    static $tableChecked = false;
    static $tableAvailable = false;

    $productId = trim($productId);
    if ($productId === '') {
        return '';
    }

    try {
        if (!$pdo instanceof PDO) {
            $pdo = new PDO(
                'mysql:host=127.0.0.1;dbname=qlhethongbanhangmini;charset=utf8mb4',
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }

        if (!$tableChecked) {
            $tableChecked = true;
            $existsStmt = $pdo->query("SHOW TABLES LIKE 'hanghoa_mota_chitiet'");
            $tableAvailable = (bool) $existsStmt->fetchColumn();
        }

        if (!$tableAvailable) {
            return '';
        }

        $stmt = $pdo->prepare('SELECT MoTaChiTiet FROM hanghoa_mota_chitiet WHERE MaHang = :id LIMIT 1');
        $stmt->execute([':id' => $productId]);
        $text = trim((string) ($stmt->fetchColumn() ?: ''));

        return $text;
    } catch (Throwable $e) {
        return '';
    }
}

function catalogBuildDescriptionListFromText(string $text, array $fallback): array
{
    $text = trim($text);
    if ($text === '') {
        return $fallback;
    }

    $parts = preg_split('/\r\n|\r|\n|\.(\s+|$)/u', $text) ?: [];
    $bullets = [];

    foreach ($parts as $part) {
        $line = trim((string) $part);
        if ($line !== '') {
            $normalized = mb_strtolower($line, 'UTF-8');
            if (
                str_starts_with($normalized, 'đơn vị tính:') ||
                str_starts_with($normalized, 'thuế vat áp dụng:') ||
                str_starts_with($normalized, 'tình trạng tồn kho:')
            ) {
                continue;
            }
            $bullets[] = rtrim($line, '.');
        }
    }

    return !empty($bullets) ? $bullets : $fallback;
}

function loadProductDetailById(string $productId): ?array
{
    $productId = trim($productId);
    if ($productId === '') {
        return null;
    }

    $viewData = catalogFetchDetailFromView($productId);
    if (is_array($viewData)) {
        $slug = catalogInferSlug((string) ($viewData['group_name'] ?? ''), (string) ($viewData['group_code'] ?? ''));
        $viewData['slug'] = $slug;
        $fallbackDescList = catalogBuildDetailedDescriptionList($viewData);
        $descList = catalogBuildDescriptionListFromText((string) ($viewData['detail_description'] ?? ''), $fallbackDescList);

        return [
            'id' => (string) ($viewData['id'] ?? ''),
            'name' => (string) ($viewData['name'] ?? ''),
            'price' => (int) ($viewData['price_raw'] ?? 0),
            'old_price' => (int) ($viewData['old_price_raw'] ?? 0),
            'sold' => (string) ($viewData['sold'] ?? '1.2k'),
            'rating' => (float) ($viewData['rating'] ?? 4.5),
            'image' => (string) ($viewData['img'] ?? '../TrangUser/ack.png'),
            'desc_list' => $descList,
        ];
    }

    foreach (catalogFetchProducts() as $item) {
        if (strcasecmp((string) ($item['id'] ?? ''), $productId) === 0) {
            $rating = ($item['slug'] ?? '') === 'mypham' ? 4.2 : 4.5;
            $fallbackDescList = catalogBuildDetailedDescriptionList($item);
            $dbDescription = catalogFetchDetailedDescriptionText((string) ($item['id'] ?? ''));
            $descriptionList = catalogBuildDescriptionListFromText($dbDescription, $fallbackDescList);

            return [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'price' => (int) ($item['price_raw'] ?? 0),
                'old_price' => (int) ($item['old_price_raw'] ?? 0),
                'sold' => '1.2k',
                'rating' => $rating,
                'image' => (string) ($item['img'] ?? '../TrangUser/ack.png'),
                'desc_list' => $descriptionList,
            ];
        }
    }

    return null;
}