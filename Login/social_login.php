<?php
require_once __DIR__ . '/connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function socialNormalizeRole(string $role): string
{
    $value = mb_strtolower(trim($role), 'UTF-8');
    if ($value === '') {
        return '';
    }

    if (in_array($value, ['admin', 'administrator', 'quantri', 'quản trị'], true)) {
        return 'admin';
    }

    if (in_array($value, ['khachhang', 'khách hàng', 'customer', 'client'], true)) {
        return 'khachhang';
    }

    if (in_array($value, ['user', 'member', 'staff', 'nhanvien', 'nhân viên'], true)) {
        return 'user';
    }

    return '';
}

function socialRoleRedirectPath(string $role): string
{
    if ($role === 'admin') {
        return '../TrangAdmin/admin.php';
    }

    if ($role === 'khachhang') {
        return '../TrangWeb/trangchu.php';
    }

    return '../TrangWeb/index.php';
}

function socialHasColumn(mysqli $conn, string $table, string $column): bool
{
    $tableEsc = $conn->real_escape_string($table);
    $columnEsc = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function socialFirstExistingColumn(mysqli $conn, string $table, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (socialHasColumn($conn, $table, (string) $candidate)) {
            return (string) $candidate;
        }
    }

    return null;
}

function socialResolveUserId(array $row): int
{
    $candidates = ['id', 'ID', 'user_id', 'UserID', 'userid'];
    foreach ($candidates as $key) {
        if (array_key_exists($key, $row)) {
            $value = (int) $row[$key];
            if ($value > 0) {
                return $value;
            }
        }
    }

    $lowerRow = array_change_key_case($row, CASE_LOWER);
    foreach ($candidates as $key) {
        $lowerKey = strtolower($key);
        if (array_key_exists($lowerKey, $lowerRow)) {
            $value = (int) $lowerRow[$lowerKey];
            if ($value > 0) {
                return $value;
            }
        }
    }

    foreach ($row as $key => $value) {
        $keyText = strtolower((string) $key);
        if (!preg_match('/(^id$|_id$|^id_|userid|userid$|iduser$)/i', $keyText)) {
            continue;
        }

        $numeric = (int) $value;
        if ($numeric > 0) {
            return $numeric;
        }
    }

    foreach ($lowerRow as $key => $value) {
        $keyText = strtolower((string) $key);
        if (!preg_match('/(^id$|_id$|^id_|userid|userid$|iduser$)/i', $keyText)) {
            continue;
        }

        $numeric = (int) $value;
        if ($numeric > 0) {
            return $numeric;
        }
    }

    return 0;
}

function socialEnsureSessionUserId(mysqli $conn, array &$userRow): int
{
    $resolved = socialResolveUserId($userRow);
    if ($resolved > 0) {
        return $resolved;
    }

    $email = strtolower(trim((string) ($userRow['email'] ?? '')));
    if ($email === '') {
        return 0;
    }

    $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $resolved = is_array($row) ? socialResolveUserId($row) : 0;
    if ($resolved > 0) {
        $userRow['id'] = $resolved;
        if (!empty($row['name'])) {
            $userRow['name'] = (string) $row['name'];
        }
        if (!empty($row['email'])) {
            $userRow['email'] = (string) $row['email'];
        }
    }

    if ($resolved <= 0) {
        $resolved = socialTryAssignPositiveUserId($conn, $email);
        if ($resolved > 0) {
            $userRow['id'] = $resolved;
        }
    }

    if ($resolved <= 0) {
        $resolved = (int) (abs(crc32($email)) + 100000);
        $userRow['id'] = $resolved;
    }

    return $resolved;
}

function socialFindIdColumn(mysqli $conn): ?string
{
    $preferred = socialFirstExistingColumn($conn, 'users', ['id', 'user_id', 'userid']);
    if ($preferred !== null) {
        return $preferred;
    }

    $result = $conn->query('SHOW COLUMNS FROM users');
    if (!($result instanceof mysqli_result)) {
        return null;
    }

    while ($row = $result->fetch_assoc()) {
        $field = (string) ($row['Field'] ?? '');
        $lower = strtolower($field);
        if (preg_match('/(^id$|_id$|^id_|userid|iduser)/i', $lower)) {
            return $field;
        }
    }

    return null;
}

function socialTryAssignPositiveUserId(mysqli $conn, string $email): int
{
    $idColumn = socialFindIdColumn($conn);
    if ($idColumn === null || $email === '') {
        return 0;
    }

    $maxRes = $conn->query("SELECT MAX(CAST(`{$idColumn}` AS UNSIGNED)) AS max_id FROM users");
    $maxId = 0;
    if ($maxRes instanceof mysqli_result) {
        $maxRow = $maxRes->fetch_assoc() ?: [];
        $maxId = (int) ($maxRow['max_id'] ?? 0);
    }

    $nextId = max(1, $maxId + 1);

    $updateSql = "UPDATE users SET `{$idColumn}` = ? WHERE email = ? AND (CAST(`{$idColumn}` AS SIGNED) <= 0 OR `{$idColumn}` IS NULL OR `{$idColumn}` = '') LIMIT 1";
    $updateStmt = $conn->prepare($updateSql);
    if ($updateStmt) {
        $updateStmt->bind_param('is', $nextId, $email);
        $updateStmt->execute();
        $updateStmt->close();
    }

    $selectStmt = $conn->prepare("SELECT `{$idColumn}` AS resolved_id FROM users WHERE email = ? LIMIT 1");
    if (!$selectStmt) {
        return 0;
    }
    $selectStmt->bind_param('s', $email);
    $selectStmt->execute();
    $res = $selectStmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $selectStmt->close();

    return (int) ($row['resolved_id'] ?? 0);
}

function socialLoadEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $env = [];
    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $equalPos = strpos($trimmed, '=');
        if ($equalPos === false) {
            continue;
        }

        $key = trim(substr($trimmed, 0, $equalPos));
        $value = trim(substr($trimmed, $equalPos + 1));
        $value = trim($value, "\"'");

        if ($key !== '') {
            $env[$key] = $value;
        }
    }

    return $env;
}

function socialEnv(string $key, array $fileEnv = []): string
{
    $value = getenv($key);
    if ($value !== false && trim((string) $value) !== '') {
        return trim((string) $value);
    }

    if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
        return trim((string) $_ENV[$key]);
    }

    if (isset($fileEnv[$key]) && trim((string) $fileEnv[$key]) !== '') {
        return trim((string) $fileEnv[$key]);
    }

    return '';
}

function socialBuildDefaultRedirectUri(string $provider): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/Login/social_login.php'))), '/');

    return $scheme . '://' . $host . $basePath . '/social_login.php?provider=' . rawurlencode($provider) . '&action=callback';
}

function socialRedirectToLogin(string $message): void
{
    $url = 'Dangnhap.php?social_error=' . rawurlencode($message);
    header('Location: ' . $url);
    exit;
}

function socialHttpPostForm(string $url, array $payload): array
{
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($payload),
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ];

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    $status = 0;

    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headerLine, $matches) === 1) {
                $status = (int) $matches[1];
                break;
            }
        }
    }

    $decoded = json_decode((string) $response, true);
    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : [],
        'raw' => (string) $response,
    ];
}

function socialHttpGetJson(string $url): array
{
    $opts = [
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ];

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    $status = 0;

    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headerLine, $matches) === 1) {
                $status = (int) $matches[1];
                break;
            }
        }
    }

    $decoded = json_decode((string) $response, true);
    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : [],
        'raw' => (string) $response,
    ];
}

function socialFindOrCreateUser(mysqli $conn, string $email, string $displayName): ?array
{
    $roleColumn = socialFirstExistingColumn($conn, 'users', ['role', 'user_role']);
    $adminCreatedColumn = socialFirstExistingColumn($conn, 'users', ['admin_created', 'is_admin_created', 'created_by_admin']);
    $statusColumn = socialFirstExistingColumn($conn, 'users', ['status']);
    $updatedAtColumn = socialFirstExistingColumn($conn, 'users', ['updated_at']);

    $selectStmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    if (!$selectStmt) {
        return null;
    }

    $selectStmt->bind_param('s', $email);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $existing = $result ? $result->fetch_assoc() : null;
    $selectStmt->close();

    if (is_array($existing)) {
        $setParts = ['name = ?'];
        $types = 's';
        $values = [$displayName];

        if ($roleColumn !== null) {
            $setParts[] = "{$roleColumn} = ?";
            $types .= 's';
            $values[] = 'khachhang';
        }

        if ($adminCreatedColumn !== null) {
            $setParts[] = "{$adminCreatedColumn} = ?";
            $types .= 'i';
            $zero = 0;
            $values[] = $zero;
        }

        if ($statusColumn !== null) {
            $setParts[] = "{$statusColumn} = ?";
            $types .= 's';
            $values[] = 'active';
        }

        if ($updatedAtColumn !== null) {
            $setParts[] = "{$updatedAtColumn} = NOW()";
        }

        $types .= 's';
        $values[] = $email;

        $updateSql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE email = ?';
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt) {
            $bindArgs = [$types];
            foreach ($values as $idx => $value) {
                $bindArgs[] = &$values[$idx];
            }
            call_user_func_array([$updateStmt, 'bind_param'], $bindArgs);
            $updateStmt->execute();
            $updateStmt->close();
        }

        $reloadStmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        if ($reloadStmt) {
            $reloadStmt->bind_param('s', $email);
            $reloadStmt->execute();
            $reloadRes = $reloadStmt->get_result();
            $reloaded = $reloadRes ? $reloadRes->fetch_assoc() : null;
            $reloadStmt->close();
            if (is_array($reloaded)) {
                $existing = $reloaded;
            }
        }

        return [
            'row' => $existing,
            'role_column' => $roleColumn,
            'admin_created_column' => $adminCreatedColumn,
        ];
    }

    $randomPassword = bin2hex(random_bytes(16));
    $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);

    $columns = ['name', 'email', 'password'];
    $placeholders = ['?', '?', '?'];
    $types = 'sss';
    $values = [$displayName, $email, $passwordHash];

    if ($statusColumn !== null) {
        $columns[] = $statusColumn;
        $placeholders[] = '?';
        $types .= 's';
        $values[] = 'active';
    }

    if ($roleColumn !== null) {
        $columns[] = $roleColumn;
        $placeholders[] = '?';
        $types .= 's';
        $values[] = 'khachhang';
    }

    if ($adminCreatedColumn !== null) {
        $columns[] = $adminCreatedColumn;
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = 0;
    }

    $insertSql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        return null;
    }

    $bindArgs = [$types];
    foreach ($values as $idx => $value) {
        $bindArgs[] = &$values[$idx];
    }
    call_user_func_array([$insertStmt, 'bind_param'], $bindArgs);
    $insertOk = $insertStmt->execute();
    $insertStmt->close();

    if (!$insertOk) {
        return null;
    }

    $selectNew = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    if (!$selectNew) {
        return null;
    }

    $selectNew->bind_param('s', $email);
    $selectNew->execute();
    $newRes = $selectNew->get_result();
    $newRow = $newRes ? $newRes->fetch_assoc() : null;
    $selectNew->close();

    if (!is_array($newRow)) {
        return null;
    }

    return [
        'row' => $newRow,
        'role_column' => $roleColumn,
        'admin_created_column' => $adminCreatedColumn,
    ];
}

function socialLoginUser(mysqli $conn, array $userRow, ?string $roleColumn, ?string $adminCreatedColumn): void
{
    // Social login policy: always customer-only access.
    // Even if the email belongs to an admin account in DB, this flow must not grant admin session.
    $resolvedRole = 'khachhang';
    $isAdminCreatedAccount = false;

    $sessionUserId = socialEnsureSessionUserId($conn, $userRow);
    if ($sessionUserId <= 0) {
        socialRedirectToLogin('Không thể khởi tạo phiên đăng nhập. Vui lòng thử lại.');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $sessionUserId;
    $_SESSION['user_name'] = (string) ($userRow['name'] ?? 'Khách hàng');
    $_SESSION['user_email'] = (string) ($userRow['email'] ?? '');
    $_SESSION['user_role'] = $resolvedRole;
    $_SESSION['is_admin'] = false;
    $_SESSION['admin_created_account'] = $isAdminCreatedAccount;

    header('Location: ../TrangWeb/trangchu.php');
    exit;
}

$provider = strtolower(trim((string) ($_GET['provider'] ?? '')));
$action = strtolower(trim((string) ($_GET['action'] ?? 'start')));
if (!in_array($provider, ['google', 'facebook'], true)) {
    socialRedirectToLogin('Nhà cung cấp đăng nhập không hợp lệ.');
}

$fileEnv = socialLoadEnvFile(dirname(__DIR__) . '/.env');

if ($provider === 'google') {
    $clientId = socialEnv('GOOGLE_CLIENT_ID', $fileEnv);
    $clientSecret = socialEnv('GOOGLE_CLIENT_SECRET', $fileEnv);
    $redirectUri = socialEnv('GOOGLE_REDIRECT_URI', $fileEnv);
    if ($redirectUri === '') {
        $redirectUri = socialBuildDefaultRedirectUri('google');
    }
} else {
    $clientId = socialEnv('FACEBOOK_CLIENT_ID', $fileEnv);
    $clientSecret = socialEnv('FACEBOOK_CLIENT_SECRET', $fileEnv);
    $redirectUri = socialEnv('FACEBOOK_REDIRECT_URI', $fileEnv);
    if ($redirectUri === '') {
        $redirectUri = socialBuildDefaultRedirectUri('facebook');
    }
}

if ($clientId === '' || $clientSecret === '') {
    socialRedirectToLogin('Chưa cấu hình đăng nhập ' . ($provider === 'google' ? 'Google' : 'Facebook') . '. Vui lòng cập nhật file .env.');
}

if ($action === 'start') {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state_' . $provider] = $state;

    if ($provider === 'google') {
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
        exit;
    }

    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'state' => $state,
        'scope' => 'email,public_profile',
        'response_type' => 'code',
    ]);
    header('Location: https://www.facebook.com/v19.0/dialog/oauth?' . $query);
    exit;
}

$incomingState = trim((string) ($_GET['state'] ?? ''));
$sessionState = (string) ($_SESSION['oauth_state_' . $provider] ?? '');
unset($_SESSION['oauth_state_' . $provider]);

if ($incomingState === '' || $sessionState === '' || !hash_equals($sessionState, $incomingState)) {
    socialRedirectToLogin('Phiên đăng nhập xã hội không hợp lệ, vui lòng thử lại.');
}

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    socialRedirectToLogin('Không nhận được mã xác thực từ ' . ($provider === 'google' ? 'Google' : 'Facebook') . '.');
}

$userEmail = '';
$userName = '';

if ($provider === 'google') {
    $tokenResponse = socialHttpPostForm('https://oauth2.googleapis.com/token', [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redirectUri,
    ]);

    $accessToken = (string) ($tokenResponse['body']['access_token'] ?? '');
    if ($accessToken === '') {
        socialRedirectToLogin('Không thể xác thực Google. Vui lòng thử lại.');
    }

    $profileResponse = socialHttpGetJson('https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . rawurlencode($accessToken));
    $profile = $profileResponse['body'];

    $userEmail = strtolower(trim((string) ($profile['email'] ?? '')));
    $userName = trim((string) ($profile['name'] ?? ''));
} else {
    $tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'code' => $code,
    ]);
    $tokenResponse = socialHttpGetJson($tokenUrl);

    $accessToken = (string) ($tokenResponse['body']['access_token'] ?? '');
    if ($accessToken === '') {
        socialRedirectToLogin('Không thể xác thực Facebook. Vui lòng thử lại.');
    }

    $profileUrl = 'https://graph.facebook.com/me?' . http_build_query([
        'fields' => 'id,name,email',
        'access_token' => $accessToken,
    ]);
    $profileResponse = socialHttpGetJson($profileUrl);
    $profile = $profileResponse['body'];

    $userEmail = strtolower(trim((string) ($profile['email'] ?? '')));
    $userName = trim((string) ($profile['name'] ?? ''));
}

if ($userEmail === '') {
    socialRedirectToLogin('Không lấy được email từ tài khoản xã hội. Vui lòng bật quyền email và thử lại.');
}

if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
    socialRedirectToLogin('Email trả về từ mạng xã hội không hợp lệ.');
}

if ($userName === '') {
    $userName = 'Khách hàng';
}

$userInfo = socialFindOrCreateUser($conn, $userEmail, $userName);
if (!is_array($userInfo) || !isset($userInfo['row']) || !is_array($userInfo['row'])) {
    socialRedirectToLogin('Không thể tạo/đăng nhập tài khoản lúc này.');
}

socialLoginUser(
    $conn,
    $userInfo['row'],
    $userInfo['role_column'] ?? null,
    $userInfo['admin_created_column'] ?? null
);