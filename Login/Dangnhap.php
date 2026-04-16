<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $role = strtolower(trim((string) ($_SESSION['user_role'] ?? '')));
    $canAccessAdmin = !empty($_SESSION['is_admin']) && (!empty($_SESSION['admin_created_account']) || strtolower(trim((string) ($_SESSION['user_email'] ?? ''))) === 'admin@gmail.com');

    if ($role === '' && !empty($_SESSION['is_admin'])) {
        $role = 'admin';
    }

    if ($role === 'admin' && $canAccessAdmin) {
        header('Location: ../TrangAdmin/admin.php');
        exit;
    }

    if ($role === 'khachhang' || $role === 'customer') {
        header('Location: ../TrangWeb/trangchu.php');
        exit;
    }

    header('Location: ../TrangWeb/index.php');
    exit;
}

$resetSuccess = isset($_GET['reset']) && $_GET['reset'] === 'success';
$socialError = trim((string) ($_GET['social_error'] ?? ''));
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập | Đăng Ký - ACK Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php if ($resetSuccess): ?>
    <div style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 2000; background: #e8f7ee; color: #1a7f37; border: 1px solid #b7ebc6; padding: 10px 16px; border-radius: 10px; font-size: 14px; box-shadow: 0 8px 24px rgba(0,0,0,0.08);">
        Đổi mật khẩu thành công! Bạn hãy đăng nhập lại nhé.
    </div>
    <?php endif; ?>

    <?php if ($socialError !== ''): ?>
    <div style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 2000; background: #fdecec; color: #842029; border: 1px solid #f2b6bc; padding: 10px 16px; border-radius: 10px; font-size: 14px; box-shadow: 0 8px 24px rgba(0,0,0,0.08);">
        <?php echo htmlspecialchars($socialError); ?>
    </div>
    <?php endif; ?>

    <div class="container" id="container">

        <div class="form-container sign-up">
            <form id="form-register">
                <h1>Tạo Tài Khoản</h1>

                <span>Điền thông tin để đăng ký</span>

                <input type="text" placeholder="Tên của bạn" required>
                <input type="email" placeholder="Email" required>

                <div class="input-group">
                    <input type="password" id="reg-pass" placeholder="Mật khẩu" required>
                    <i class='bx bx-hide eye-icon' onclick="togglePass('reg-pass', this)"></i>
                </div>

                <div class="input-group">
                    <input type="password" id="reg-repass" placeholder="Nhập lại mật khẩu" required>
                    <i class='bx bx-hide eye-icon' onclick="togglePass('reg-repass', this)"></i>
                </div>

                <button type="submit">Đăng Ký</button>

                <p style="margin-top: 15px; font-size: 12px; color: #666;">Hoặc đăng ký bằng</p>
                <div class="social-icons">
                    <a href="social_login.php?provider=google&action=start" class="icon" title="Đăng ký bằng Google"><i class="bx bxl-google"></i></a>
                    <a href="social_login.php?provider=facebook&action=start" class="icon" title="Đăng ký bằng Facebook"><i class="bx bxl-facebook"></i></a>
                </div>
            </form>
        </div>

        <div class="form-container sign-in">
            <form id="form-login">
                <h1>Đăng Nhập</h1>

                <span>Sử dụng tài khoản của bạn</span>

                <input type="email" placeholder="Email" required>

                <div class="input-group">
                    <input type="password" id="login-pass" placeholder="Mật khẩu" required>
                    <i class='bx bx-hide eye-icon' onclick="togglePass('login-pass', this)"></i>
                </div>

                <a href="forgot-password.php" class="fp">Quên mật khẩu?</a>
                <button type="submit">Đăng Nhập</button>

                <p style="margin-top: 20px; font-size: 12px; color: #666;">Hoặc đăng nhập bằng</p>
                <div class="social-icons">
                    <a href="social_login.php?provider=google&action=start" class="icon" title="Đăng nhập bằng Google"><i class="bx bxl-google"></i></a>
                    <a href="social_login.php?provider=facebook&action=start" class="icon" title="Đăng nhập bằng Facebook"><i class="bx bxl-facebook"></i></a>
                </div>
            </form>
        </div>

        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <h1>Chào mừng trở lại!</h1>
                    <p>Nhập thông tin cá nhân của bạn để sử dụng tất cả các tính năng của trang web</p>
                    <button class="hidden" id="login">Đăng Nhập</button>
                </div>
                <div class="toggle-panel toggle-right">
                    <h1>Chào bạn mới!</h1>
                    <p>Đăng ký với thông tin cá nhân của bạn để bắt đầu trải nghiệm mua sắm cùng ACK</p>
                    <button class="hidden" id="register">Đăng Ký</button>
                </div>
            </div>
        </div>
    </div>

    <script src="xuli.js"></script>
</body>

</html>