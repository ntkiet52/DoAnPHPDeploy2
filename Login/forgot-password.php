<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$status = trim((string) ($_GET['status'] ?? ''));
$previewLink = (string) ($_SESSION['reset_link_preview'] ?? '');
unset($_SESSION['reset_link_preview']);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu - ACK Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        max-width: 460px;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
        padding: 28px;
    }

    h1 {
        margin: 0 0 10px;
        font-size: 24px;
        color: #1f2d3d;
    }

    p {
        margin: 0 0 18px;
        color: #6b7280;
        font-size: 14px;
        line-height: 1.6;
    }

    label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 6px;
        color: #344054;
    }

    input[type="email"] {
        width: 100%;
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 14px;
        margin-bottom: 14px;
        outline: none;
    }

    input[type="email"]:focus {
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

    .back-link {
        margin-top: 14px;
        display: inline-block;
        color: #0062cc;
        text-decoration: none;
        font-size: 13px;
    }

    .alert {
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 14px;
        font-size: 13px;
    }

    .alert.success {
        background: #e8f7ee;
        color: #1a7f37;
        border: 1px solid #b7ebc6;
    }

    .alert.error {
        background: #fff1f1;
        color: #b42318;
        border: 1px solid #ffd4d4;
    }

    .preview {
        margin-top: 12px;
        background: #f7f9fc;
        border: 1px dashed #b8c2cc;
        padding: 10px;
        border-radius: 8px;
        word-break: break-all;
        font-size: 12px;
    }

    .preview a {
        color: #0056b3;
    }

    .ack-notice-wrap {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 3000;
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: min(360px, calc(100vw - 24px));
        pointer-events: none;
    }

    .ack-notice-item {
        pointer-events: auto;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 13px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        border: 1px solid;
    }

    .ack-notice-item.success {
        background: #e8f7ee;
        color: #1a7f37;
        border-color: #b7ebc6;
    }

    .ack-notice-item.error {
        background: #fff1f1;
        color: #b42318;
        border-color: #ffd4d4;
    }
    </style>
</head>

<body>
    <?php if ($status === 'sent' || $status === 'invalid_email'): ?>
    <div class="ack-notice-wrap">
        <?php if ($status === 'sent'): ?>
        <div class="ack-notice-item success">Nếu email tồn tại, liên kết đặt lại mật khẩu đã được tạo.</div>
        <?php endif; ?>

        <?php if ($status === 'invalid_email'): ?>
        <div class="ack-notice-item error">Email không hợp lệ. Vui lòng kiểm tra lại.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <h1>Quên mật khẩu</h1>
        <p>Nhập email đã đăng ký. Hệ thống sẽ tạo liên kết đặt lại mật khẩu.</p>

        <form action="request_reset.php" method="post">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" placeholder="you@example.com" required>
            <button type="submit">Tạo liên kết đặt lại</button>
        </form>

        <?php if ($previewLink !== ''): ?>
        <div class="preview">
            <strong>Liên kết reset (môi trường local):</strong><br>
            <a href="<?php echo htmlspecialchars($previewLink, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($previewLink, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>
        <?php endif; ?>

        <a class="back-link" href="Dangnhap.php">← Quay lại đăng nhập</a>
    </div>

    <script>
    document.querySelectorAll('.ack-notice-item').forEach((node) => {
        window.setTimeout(() => {
            node.style.transition = 'opacity 0.2s ease';
            node.style.opacity = '0';
            window.setTimeout(() => node.remove(), 220);
        }, 3500);
    });
    </script>
</body>

</html>
