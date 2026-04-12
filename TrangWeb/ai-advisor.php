<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

require_once __DIR__ . '/catalog_data.php';

$userMessage = trim($_POST['message'] ?? '');

if (empty($userMessage)) {
    echo json_encode(['reply' => 'Vui lòng nhập câu hỏi của bạn!'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kiểm tra nếu là lời chào
$greetingKeywords = ['chào', 'xin chào', 'hello', 'hi', 'hey', 'halo', 'tình yêu', 'em là ai', 'bạn là ai', 'tên em'];
$messageLower = mb_strtolower($userMessage, 'UTF-8');
foreach ($greetingKeywords as $greeting) {
    if (strpos($messageLower, mb_strtolower($greeting, 'UTF-8')) !== false) {
        $greetings = [
            "👋 Xin chào! Tôi là AI tư vấn sản phẩm của ACK Mart. Mình có thể giúp bạn tìm sản phẩm yêu thích bạn không? Hãy nói với tôi bạn muốn tìm gì!",
            "😊 Chào bạn! Tôi rất vui được gặp bạn. Hôm nay bạn muốn mua gì? Tôi có thể gợi ý sản phẩm cho bạn!",
            "👋 Xin chào bạn! Đây là AI tư vấn, tôi sẵn sàng giúp bạn chọn sản phẩm tốt nhất. Bạn cần gì không?",
            "🤖 Hi bạn! Mình là chatbot tư vấn sản phẩm. Nói cho mình biết bạn muốn tìm gì, mình sẽ gợi ý cho bạn!",
        ];
        echo json_encode(['type' => 'text', 'reply' => $greetings[array_rand($greetings)]], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Ánh xạ từ khóa tới danh mục sản phẩm
$categoryMap = [
    'nuocngot' => ['nước ngọt', 'nước uống', 'cocacola', 'coca', 'sprite', 'fanta', 'pepsi'],
    'douong' => ['trà sữa', 'tra sua', 'bobatea', 'chè sữa', 'thức uống'],
    'anvat' => ['đồ ăn vặt', 'ăn vặt', 'snack', 'an vat'],
    'thucannhanh' => ['đồ ăn nhanh', 'gà rán', 'hamburger', 'fastfood', 'ga ran'],
    'traicay' => ['trái cây', 'táo', 'cam', 'chuối', 'dâu', 'nho', 'xoài'],
    'raucu' => ['rau sạch', 'rau cu', 'rau cải', 'cà rốt', 'cà chua', 'bắp cải'],
    'sua' => ['sữa tươi', 'sữa chua', 'sữa', 'sua', 'yogurt', 'vinamilk', 'milo'],
    'banhngot' => ['bánh ngọt', 'bánh', 'bánh chưng', 'bánh quy', 'bánh kem'],
    'giadung' => ['đồ gia dụng', 'khăn giấy', 'khan giay', 'nấu ăn', 'dụng cụ bếp', 'chảo', 'nồi', 'bát', 'đũa', 'muỗng'],
    'mypham' => ['mỹ phẩm', 'kem dưỡng', 'mặt nạ', 'toner', 'skincare'],
    'kem' => ['kem lạnh', 'kem', 'ice cream', 'icecream', 'matcha'],
    'mianlien' => ['mỳ ăn liền', 'mỳ', 'bánh mì', 'ba mì'],
    'tuoisong' => ['tươi sống', 'thịt sống', 'hải sản', 'tôm', 'cá'],
    'dohop' => ['đồ hộp', 'hộp cơm', 'thực phẩm đóng hộp'],
    'giavi' => ['gia vị', 'gia vi', 'muối', 'tiêu', 'tương', 'mắm'],
    'bia' => ['bia', 'beer', 'rượu'],
];

// Từ khóa cụ thể sản phẩm (để lọc thêm trong danh mục)
$specificProductKeywords = [
    'táo' => 'táo',
    'cam' => 'cam',
    'chuối' => 'chuối',
    'dâu' => 'dâu',
    'nho' => 'nho',
    'xoài' => 'xoài',
    'milo' => 'milo',
    'vinamilk' => 'vinamilk',
    'cà rốt' => 'cà rốt',
    'cà chua' => 'cà chua',
    'sprite' => 'sprite',
    'coca' => 'coca',
    'fanta' => 'fanta',
];

// Hàm tìm category slug từ tin nhắn
function findCategorySlug($message, $categoryMap) {
    $messageLower = mb_strtolower($message, 'UTF-8');
    $bestMatch = null;
    $bestLength = 0;
    
    foreach ($categoryMap as $categorySlug => $keywords) {
        foreach ($keywords as $keyword) {
            $keywordLower = mb_strtolower($keyword, 'UTF-8');
            if (strpos($messageLower, $keywordLower) !== false) {
                if (strlen($keywordLower) > $bestLength) {
                    $bestMatch = $categorySlug;
                    $bestLength = strlen($keywordLower);
                }
            }
        }
    }
    
    return $bestMatch;
}

// Hàm tìm sản phẩm cụ thể từ tin nhắn
function findSpecificProduct($message, $specificProductKeywords) {
    $messageLower = mb_strtolower($message, 'UTF-8');
    $bestMatch = null;
    $bestLength = 0;
    
    foreach ($specificProductKeywords as $keyword => $productName) {
        $keywordLower = mb_strtolower($keyword, 'UTF-8');
        if (strpos($messageLower, $keywordLower) !== false) {
            if (strlen($keywordLower) > $bestLength) {
                $bestMatch = $productName;
                $bestLength = strlen($keywordLower);
            }
        }
    }
    
    return $bestMatch;
}

// Hàm lọc sản phẩm theo category slug
function filterProductsByCategory($allProducts, $categorySlug, $specificProduct = null) {
    $filtered = [];
    foreach ($allProducts as $product) {
        if ($product['slug'] === $categorySlug) {
            // Nếu có sản phẩm cụ thể, chỉ hiển thị những sản phẩm có tên chứa từ khóa đó
            if ($specificProduct) {
                $productNameLower = mb_strtolower($product['name'], 'UTF-8');
                $specificLower = mb_strtolower($specificProduct, 'UTF-8');
                if (strpos($productNameLower, $specificLower) !== false) {
                    $filtered[] = $product;
                }
            } else {
                $filtered[] = $product;
            }
        }
    }
    return $filtered;
}

// Hàm tạo tin nhắn giới thiệu sản phẩm
function generateProductMessage($specificProduct, $categorySlug, $totalCount) {
    $messages = [
        // Nếu tìm sản phẩm cụ thể
        'táo' => '🍎 Tuyệt vời! Tôi tìm thấy các loại táo tươi cho bạn. Tất cả đều được chọn lọc kỹ, có chứng chỉ an toàn:',
        'cam' => '🍊 Các loại cam ngon lành đây! Cam tươi, giàu vitamin C, rất tốt cho sức khỏe:',
        'chuối' => '🍌 Chuối vàng ươm chín tới, rất ngon và bổ dưỡng:',
        'dâu' => '🫐 Dâu tươi mới về, vị ngọt tự nhiên, giàu chất chống oxy hóa:',
        'nho' => '🍇 Nho Mỹ cao cấp, hạt nhân hoặc không hạt, rất ngon:',
        'xoài' => '🥭 Xoài vàng chín, thơm lừng và ngọt tự nhiên:',
        'milo' => '🥛 Sữa Milo - thương hiệu tin cậy, bổ sung canxi và vitamin:',
        'vinamilk' => '🥛 Sữa Vinamilk - sản phẩm hàng đầu Việt Nam, chất lượng đảm bảo:',
        'cà rốt' => '🥕 Cà rốt tươi, sạch, giàu beta-carotene, rất tốt cho mắt:',
        'cà chua' => '🍅 Cà chua tươi, đỏ mọng, nguyên chất 100%:',
        'sprite' => '✨ Sprite - nước ngọt sáng tạo, mát lạnh, thơm mát:',
        'coca' => '🥤 Coca Cola - thương hiệu nước uống hội lá yêu thích thế giới:',
        'fanta' => '🎉 Fanta - nước ngọt trái cây sống động, có nhiều vị lựa chọn:',
    ];
    
    $defaultMessages = [
        'traicay' => '🍎 Các loại trái cây tươi ngon đây! Tôi gợi ý 4 loại phổ biến nhất, bạn có thể xem thêm nhiều loại khác:',
        'raucu' => '🥬 Rau củ sạch, không hóa chất, tươi ngay hôm nay:',
        'sua' => '🥛 Sữa và sản phẩm từ sữa - bổ sung canxi và dưỡng chất:',
        'nuocngot' => '🥤 Nước ngọt mát lạnh, nhiều vị, rất phù hợp cho các bữa ăn:',
        'douong' => '🧋 Trà sữa thơm ngon, được pha chế tươi mới:',
        'anvat' => '🥨 Đồ ăn vặt nhàn rỗi, đủ loại, vị ngon lạ:',
        'banhngot' => '🎂 Bánh ngọt tươi, nhiều loại lựa chọn:',
        'thucannhanh' => '🍔 Thức ăn nhanh ngon, tiện lợi cho bạn bận:',
        'giadung' => '🧹 Dụng cụ nhà bếp, khăn giấy và các vật dụng gia đình chất lượng cao:',
        'mypham' => '💄 Mỹ phẩm chăm sóc da từ các thương hiệu uy tín:',
        'kem' => '🍦 Kem lạnh các vị, rất tốt để tươi mát trong ngày hè:',
        'mianlien' => '🍜 Mỳ ăn liền ngon, nhanh gọn, tiện cho những lúc vội:',
        'tuoisong' => '🐟 Tươi sống tươi mới, được chọn lọc kỹ cảnh:',
        'dohop' => '🥫 Đồ hộp tiện lợi, bảo quản lâu, dùng nhanh:',
        'giavi' => '🧂 Gia vị nấu ăn, muối, tiêu, tương, mắm - những thứ không thể thiếu trong bếp:',
        'bia' => '🍺 Bia và các loại đồ uống có cồn - thích hợp cho những dịp đặc biệt:',
    ];
    
    // Nếu có sản phẩm cụ thể, ưu tiên dùng message đó
    if ($specificProduct && isset($messages[$specificProduct])) {
        return $messages[$specificProduct];
    }
    
    // Nếu không, dùng message mặc định theo category
    if (isset($defaultMessages[$categorySlug])) {
        return $defaultMessages[$categorySlug];
    }
    
    // Fallback
    return "🛍️ Dưới đây là các sản phẩm tôi gợi ý cho bạn:";
}

// Lấy tất cả sản phẩm từ database
$allProducts = catalogFetchProducts();

$messageLower = mb_strtolower($userMessage, 'UTF-8');

// ===== BƯỚC 1: TÌM KIẾM SẢN PHẨM CỤ THỂ THEO TÊN (ƯU TIÊN CAO) =====
$searchResults = [];
foreach ($allProducts as $product) {
    $productNameLower = mb_strtolower($product['name'], 'UTF-8');
    // Tìm match chính xác (toàn bộ hoặc bắt đầu từ tên)
    if (strpos($productNameLower, $messageLower) !== false && strlen($messageLower) > 2) {
        $searchResults[] = $product;
    }
}

// Nếu tìm được sản phẩm cụ thể (3+ kết quả)
if (count($searchResults) >= 1) {
    $displayLimit = 4;
    $totalResults = count($searchResults);
    $displayedResults = array_slice($searchResults, 0, $displayLimit);
    $hasMore = $totalResults > $displayLimit;
    
    // Tìm category slug để điều hướng
    $categorySlug = '';
    if (!empty($displayedResults)) {
        $categorySlug = $displayedResults[0]['slug'];
    }
    
    echo json_encode([
        'type' => 'products',
        'reply' => "🔍 Tôi tìm thấy $totalResults sản phẩm \"$userMessage\" cho bạn:",
        'products' => $displayedResults,
        'hasMore' => $hasMore,
        'totalCount' => $totalResults,
        'displayedCount' => count($displayedResults),
        'categorySlug' => $categorySlug
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== BƯỚC 2: TÌM DANH MỤC (ƯU TIÊN THỨ 2) =====
// Tìm category slug từ tin nhắn
$categorySlug = findCategorySlug($userMessage, $categoryMap);

if ($categorySlug) {
    // Lọc sản phẩm theo category (và theo sản phẩm cụ thể nếu có)
    $allFilteredProducts = filterProductsByCategory($allProducts, $categorySlug, null);
    
    if (!empty($allFilteredProducts)) {
        // Số lượng sản phẩm hiển thị mặc định
        $displayLimit = 4;
        $totalProducts = count($allFilteredProducts);
        $displayedProducts = array_slice($allFilteredProducts, 0, $displayLimit);
        $hasMore = $totalProducts > $displayLimit;
        
        // Tạo message giới thiệu
        $introMessage = generateProductMessage(null, $categorySlug, $totalProducts);
        
        // Trả về danh sách sản phẩm
        echo json_encode([
            'type' => 'products',
            'reply' => $introMessage,
            'products' => $displayedProducts,
            'hasMore' => $hasMore,
            'totalCount' => $totalProducts,
            'displayedCount' => count($displayedProducts),
            'categorySlug' => $categorySlug
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Nếu không tìm thấy sản phẩm, trả lời mặc định
$suggestions = ['Nước ngọt', 'Trà sữa', 'Trái cây', 'Rau sạch', 'Bánh ngọt', 'Mỹ phẩm', 'Sữa'];
$randomSuggestion = $suggestions[array_rand($suggestions)];
echo json_encode([
    'type' => 'text',
    'reply' => "😊 Tôi chưa hiểu rõ lắm. Bạn muốn tìm gì? Gợi ý: $randomSuggestion, Kem lạnh, Mỳ ăn liền, v.v...\n\n💬 Hãy gõ tên sản phẩm hoặc loại hàng bạn muốn!"
], JSON_UNESCAPED_UNICODE);
?>