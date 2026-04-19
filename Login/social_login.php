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

function socialBase64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function socialBase64UrlDecode(string $value): string
{
    $decoded = strtr($value, '-_', '+/');
    $padding = strlen($decoded) % 4;
    if ($padding !== 0) {
        $decoded .= str_repeat('=', 4 - $padding);
    }

    $result = base64_decode($decoded, true);
    return is_string($result) ? $result : '';
}

function socialStateSigningSecret(string $provider, string $clientSecret, array $fileEnv = []): string
{
    $appSecret = socialEnv('SOCIAL_STATE_SECRET', $fileEnv);
    if ($appSecret !== '') {
        return hash('sha256', $provider . '|' . $appSecret . '|oauth-state');
    }

    if ($clientSecret !== '') {
        return hash('sha256', $provider . '|' . $clientSecret . '|oauth-state');
    }

    return hash('sha256', $provider . '|fallback-local-secret|oauth-state');
}

function socialCreateSignedState(string $provider, string $signingSecret): string
{
    $payload = [
        'p' => $provider,
        't' => time(),
        'n' => bin2hex(random_bytes(12)),
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || $json === '') {
        $json = '{"p":"' . addslashes($provider) . '","t":' . time() . ',"n":"' . bin2hex(random_bytes(8)) . '"}';
    }

    $payloadEncoded = socialBase64UrlEncode($json);
    $signature = hash_hmac('sha256', $payloadEncoded, $signingSecret);

    return $payloadEncoded . '.' . $signature;
}

function socialValidateSignedState(string $provider, string $state, string $signingSecret, int $maxAgeSeconds = 900): bool
{
    if ($state === '' || !str_contains($state, '.')) {
        return false;
    }

    [$payloadEncoded, $incomingSignature] = explode('.', $state, 2);
    $expectedSignature = hash_hmac('sha256', $payloadEncoded, $signingSecret);

    if ($incomingSignature === '' || !hash_equals($expectedSignature, $incomingSignature)) {
        return false;
    }

    $json = socialBase64UrlDecode($payloadEncoded);
    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        return false;
    }

    $payloadProvider = strtolower(trim((string) ($payload['p'] ?? '')));
    $issuedAt = (int) ($payload['t'] ?? 0);
    $nonce = (string) ($payload['n'] ?? '');

    if ($payloadProvider !== $provider || $issuedAt <= 0 || $nonce === '') {
        return false;
    }

    return (time() - $issuedAt) <= $maxAgeSeconds;
}

function socialProviderErrorMessage(string $provider, array $query): string
{
    $providerLabel = $provider === 'google' ? 'Google' : 'Facebook';
    $rawError = trim((string) ($query['error'] ?? ''));
    $rawDescription = trim((string) ($query['error_description'] ?? ''));

    if ($rawError === '' && $rawDescription === '') {
        return '';
    }

    $text = $rawDescription !== '' ? $rawDescription : $rawError;
    $text = preg_replace('/\s+/', ' ', (string) $text);
    $text = trim((string) $text);

    if ($text === '') {
        return 'Đăng nhập ' . $providerLabel . ' không thành công. Vui lòng thử lại.';
    }

    return 'Đăng nhập ' . $providerLabel . ' thất bại: ' . $text;
}

function socialExtractProviderError(array $response): string
{
    $body = is_array($response['body'] ?? null) ? $response['body'] : [];
    $error = trim((string) ($body['error_description'] ?? $body['error'] ?? ''));

    if ($error !== '') {
        return preg_replace('/\s+/', ' ', $error) ?: $error;
    }

    $transportError = trim((string) ($response['error'] ?? ''));
    if ($transportError !== '') {
        return 'Lỗi kết nối: ' . $transportError;
    }

    $status = (int) ($response['status'] ?? 0);
    $raw = trim((string) ($response['raw'] ?? ''));
    if ($status >= 400) {
        if ($raw !== '') {
            $snippet = mb_substr($raw, 0, 220, 'UTF-8');
            return 'HTTP ' . $status . ': ' . $snippet;
        }

        return 'HTTP ' . $status;
    }

    return '';
}

function socialBuildTokenFailureMessage(string $provider, array $response): string
{
    $providerLabel = $provider === 'google' ? 'Google' : 'Facebook';
    $providerError = socialExtractProviderError($response);
    if ($providerError !== '') {
        return 'Không thể xác thực ' . $providerLabel . ': ' . $providerError;
    }

    $status = (int) ($response['status'] ?? 0);
    $raw = trim((string) ($response['raw'] ?? ''));

    if ($status > 0 && $raw !== '') {
        $snippet = mb_substr($raw, 0, 220, 'UTF-8');
        return 'Không thể xác thực ' . $providerLabel . ' (HTTP ' . $status . '): ' . $snippet;
    }

    if ($status > 0) {
        return 'Không thể xác thực ' . $providerLabel . ' (HTTP ' . $status . ').';
    }

    return 'Không thể xác thực ' . $providerLabel . '. Vui lòng thử lại.';
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

function socialFacebookFallbackEmail(string $facebookId): string
{
    $clean = preg_replace('/[^a-zA-Z0-9]/', '', $facebookId) ?? '';
    $clean = strtolower(trim($clean));

    if ($clean === '') {
        $clean = bin2hex(random_bytes(8));
    }

    return 'fb_' . $clean . '@facebook.local';
}

function socialParseHttpStatusFromHeaders(array $headers): int
{
    foreach ($headers as $headerLine) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $headerLine, $matches) === 1) {
            return (int) $matches[1];
        }
    }

    return 0;
}

function socialHttpRequest(string $method, string $url, ?array $payload = null): array
{
    $normalizedMethod = strtoupper(trim($method));
    $requestBody = $payload !== null ? http_build_query($payload) : '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            $headers = [
                'Accept: application/json',
                'User-Agent: ACK-Store-OAuth/1.0',
            ];

            $curlOpts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CUSTOMREQUEST => $normalizedMethod,
            ];

            if ($normalizedMethod === 'POST') {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                $curlOpts[CURLOPT_POSTFIELDS] = $requestBody;
                $curlOpts[CURLOPT_HTTPHEADER] = $headers;
            }

            curl_setopt_array($ch, $curlOpts);
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $raw = is_string($response) ? $response : '';
            $decoded = json_decode($raw, true);

            return [
                'status' => $status,
                'body' => is_array($decoded) ? $decoded : [],
                'raw' => $raw,
                'error' => $curlError,
            ];
        }
    }

    $headers = [
        'Accept: application/json',
        'User-Agent: ACK-Store-OAuth/1.0',
    ];
    if ($normalizedMethod === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    $opts = [
        'http' => [
            'method' => $normalizedMethod,
            'header' => implode("\r\n", $headers) . "\r\n",
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ];

    if ($normalizedMethod === 'POST') {
        $opts['http']['content'] = $requestBody;
    }

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    $status = 0;
    $streamHeaders = [];
    if (isset($http_response_header) && is_array($http_response_header)) {
        $streamHeaders = $http_response_header;
        $status = socialParseHttpStatusFromHeaders($streamHeaders);
    }

    $lastError = error_get_last();
    $streamError = '';
    if ($response === false && is_array($lastError)) {
        $streamError = trim((string) ($lastError['message'] ?? ''));
    }

    $raw = is_string($response) ? $response : '';
    $decoded = json_decode($raw, true);

    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : [],
        'raw' => $raw,
        'error' => $streamError,
    ];
}

function socialHttpPostForm(string $url, array $payload): array
{
    return socialHttpRequest('POST', $url, $payload);
}

function socialHttpGetJson(string $url): array
{
    return socialHttpRequest('GET', $url, null);
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

$signingSecret = socialStateSigningSecret($provider, $clientSecret, $fileEnv);

$providerErrorMessage = socialProviderErrorMessage($provider, $_GET);
if ($providerErrorMessage !== '') {
    socialRedirectToLogin($providerErrorMessage);
}

if ($action === 'start') {
    $state = socialCreateSignedState($provider, $signingSecret);
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

    $facebookScope = socialEnv('FACEBOOK_SCOPE', $fileEnv);
    if ($facebookScope === '') {
        // Keep default minimal to avoid "Invalid Scopes: email" on apps not approved for email permission yet.
        $facebookScope = 'public_profile';
    }

    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'state' => $state,
        'scope' => $facebookScope,
        'response_type' => 'code',
    ]);
    header('Location: https://www.facebook.com/v19.0/dialog/oauth?' . $query);
    exit;
}

$incomingState = trim((string) ($_GET['state'] ?? ''));
$sessionState = (string) ($_SESSION['oauth_state_' . $provider] ?? '');
unset($_SESSION['oauth_state_' . $provider]);

$isSignedStateValid = socialValidateSignedState($provider, $incomingState, $signingSecret);
$isLegacySessionStateValid = $sessionState !== '' && $incomingState !== '' && hash_equals($sessionState, $incomingState);

if (!$isSignedStateValid && !$isLegacySessionStateValid) {
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
        socialRedirectToLogin(socialBuildTokenFailureMessage('google', $tokenResponse));
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
        socialRedirectToLogin(socialBuildTokenFailureMessage('facebook', $tokenResponse));
    }

    $profileUrl = 'https://graph.facebook.com/me?' . http_build_query([
        'fields' => 'id,name,email',
        'access_token' => $accessToken,
    ]);
    $profileResponse = socialHttpGetJson($profileUrl);
    $profile = $profileResponse['body'];

    $userEmail = strtolower(trim((string) ($profile['email'] ?? '')));
    $userName = trim((string) ($profile['name'] ?? ''));

    if ($userEmail === '') {
        $facebookId = trim((string) ($profile['id'] ?? ''));
        if ($facebookId !== '') {
            $userEmail = socialFacebookFallbackEmail($facebookId);
        }
    }
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