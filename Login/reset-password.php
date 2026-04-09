<?php
require 'connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function findValidResetRecord(mysqli $conn, string $email, string $token): ?array
{
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare('SELECT id, token, expires_at FROM password_resets WHERE email = ? AND expires_at >= NOW() ORDER BY id DESC');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    $matched = null;
    while ($row = $result->fetch_assoc()) {
        $rowToken = (string) ($row['token'] ?? '');
        if ($rowToken !== '' && hash_equals($rowToken, $hash)) {
            $matched = [
                'id' => (int) ($row['id'] ?? 0),
                'expires_at' => (string) ($row['expires_at'] ?? ''),
            ];
            break;
        }
    }

    $stmt->close();
    return $matched;
}

$error = '';
$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

if ($email !== '') {
    $email = strtolower($email);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $token === '') {
        $error = 'Liên kết đặt lại mật khẩu không hợp lệ.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Mật khẩu nhập lại không khớp.';
    } else {
        $resetRecord = findValidResetRecord($conn, $email, $token);

        if ($resetRecord === null) {
            $error = 'Mã đặt lại không hợp lệ hoặc đã hết hạn.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?');

            if (!$updateStmt) {
                $error = 'Không thể cập nhật mật khẩu, vui lòng thử lại.';
            } else {
                $updateStmt->bind_param('ss', $newHash, $email);
                $updateStmt->execute();
                $updatedRows = $updateStmt->affected_rows;
                $updateStmt->close();

                $cleanupStmt = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
                if ($cleanupStmt) {
                    $cleanupStmt->bind_param('s', $email);
                    $cleanupStmt->execute();
                    $cleanupStmt->close();
                }

                if ($updatedRows >= 0) {
                    header('Location: Dangnhap.php?reset=success');
                    exit;
                }

                $error = 'Không thể cập nhật mật khẩu, vui lòng thử lại.';
            }
        }
    }
} else {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $token === '') {
        $error = 'Liên kết đặt lại mật khẩu không hợp lệ.';
    } else {
        $resetRecord = findValidResetRecord($conn, $email, $token);
        if ($resetRecord === null) {
            $error = 'Liên kết đặt lại không hợp lệ hoặc đã hết hạn.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu - ACK Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
    * {
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }

    body {
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #c3ecf8 0%, #ccdcff 100%);
        padding: 16px;
    }

    .card {
        width: 100%;
        max-width: 480px;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
        padding: 28px;
    }

    h1 {
        margin: 0 0 8px;
        font-size: 24px;
    }

    p {
        margin: 0 0 18px;
        color: #6b7280;
        font-size: 14px;
    }

    label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 6px;
        color: #344054;
    }

    input[type="password"] {
        width: 100%;
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 14px;
        margin-bottom: 14px;
        outline: none;
    }

    input[type="password"]:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.14);
    }

    button {
        width: 100%;
        border: none;
        border-radius: 999px;
        padding: 12px;
        font-weight: 600;
        color: #fff;
        background: linear-gradient(to right, #007bff, #0062cc);
        cursor: pointer;
    }

    .alert {
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 14px;
        font-size: 13px;
    }

    .alert.error {
        background: #fff1f1;
        color: #b42318;
        border: 1px solid #ffd4d4;
    }

    .links {
        margin-top: 14px;
    }

    .links a {
        display: inline-block;
        margin-right: 12px;
        color: #0062cc;
        text-decoration: none;
        font-size: 13px;
    }
    </style>
</head>

<body>
    <div class="card">
        <h1>Đặt lại mật khẩu</h1>
        <p>Nhập mật khẩu mới cho tài khoản của bạn.</p>

        <?php if ($error !== ''): ?>
        <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error === ''): ?>
        <form action="reset-password.php" method="post">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

            <label for="password">Mật khẩu mới</label>
            <input id="password" type="password" name="password" minlength="6" required>

            <label for="confirm_password">Nhập lại mật khẩu mới</label>
            <input id="confirm_password" type="password" name="confirm_password" minlength="6" required>

            <button type="submit">Cập nhật mật khẩu</button>
        </form>
        <?php endif; ?>

        <div class="links">
            <a href="forgot-password.php">Yêu cầu liên kết mới</a>
            <a href="Dangnhap.php">Quay lại đăng nhập</a>
        </div>
    </div>
</body>

</html>