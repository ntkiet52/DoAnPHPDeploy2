<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$status = trim((string) ($_GET['status'] ?? ''));
$verifiedEmail = (string) ($_SESSION['verified_email'] ?? '');
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
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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

    input[type="email"],
    input[type="password"] {
        width: 100%;
        border: 1px solid #d0d5dd;
        border-radius: 12px;
        padding: 14px 16px;
        font-size: 15px;
        min-height: 48px;
        margin-bottom: 14px;
        outline: none;
    }

    input[type="email"]:focus,
    input[type="password"]:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.14);
    }

    .input-group {
        position: relative;
        margin-bottom: 14px;
    }

    .input-group input {
        width: 100%;
        padding-right: 40px;
    }

    .eye-icon {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #6b7280;
        font-size: 18px;
    }

    .eye-icon:hover {
        color: #3b82f6;
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
        transition: all 0.2s ease;
    }

    button:hover {
        opacity: 0.9;
    }

    button:active {
        transform: scale(0.98);
    }

    button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .form-group {
        margin-bottom: 14px;
    }

    .otp-input {
        display: flex;
        gap: 10px;
        margin: 14px 0;
        flex-wrap: wrap;
        justify-content: center;
    }

    .otp-digit {
        width: 56px;
        height: 56px;
        text-align: center;
        font-size: 24px;
        font-weight: 600;
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        padding: 0;
        outline: none;
    }

    .otp-digit:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.14);
    }

    .step-indicator {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
        font-size: 12px;
    }

    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
    }

    .step-number {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #e5e7eb;
        color: #6b7280;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
    }

    .step.active .step-number {
        background: #007bff;
        color: #fff;
    }

    .step.completed .step-number {
        background: #22c55e;
        color: #fff;
    }

    .input-group-inline {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .input-group-inline input {
        flex: 1;
    }

    .input-group-inline button {
        width: 100%;
        min-width: 100%;
    }

    .hidden-form {
        display: none;
    }

    .timer {
        font-size: 12px;
        color: #666;
        text-align: center;
        margin-top: 10px;
    }

    .timer.expired {
        color: #dc2626;
    }

    .success-icon {
        color: #22c55e;
        font-size: 24px;
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
        <p>Nhập email để nhận mã OTP và đặt lại mật khẩu</p>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step-1">
                <div class="step-number">1</div>
                <span>Email</span>
            </div>
            <div class="step" id="step-2">
                <div class="step-number">2</div>
                <span>OTP</span>
            </div>
            <div class="step" id="step-3">
                <div class="step-number">3</div>
                <span>Mật khẩu</span>
            </div>
        </div>

        <!-- Step 1: Email -->
        <form id="email-form" class="form-step">
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-group-inline">
                    <input id="email" type="email" name="email" placeholder="you@example.com" required>
                    <button type="submit" id="send-otp-btn">Gửi OTP</button>
                </div>
            </div>
        </form>

        <!-- Step 2: OTP -->
        <form id="otp-form" class="form-step hidden-form">
            <div class="form-group">
                <label>Nhập mã OTP (gửi đến email)</label>
                <div class="otp-input" id="otp-inputs">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" autocomplete="off">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" autocomplete="off">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" autocomplete="off">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" autocomplete="off">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" autocomplete="off">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" autocomplete="off">
                </div>
                <div class="timer" id="otp-timer">Mã hết hạn trong: <span id="timer-seconds">600</span>s</div>
                <button type="submit" id="verify-otp-btn" style="margin-top: 10px;">Xác thực OTP</button>
                <button type="button" id="resend-otp-btn" style="margin-top: 8px; background: #6b7280;">Gửi lại mã OTP</button>
            </div>
        </form>

        <!-- Step 3: New Password -->
        <form id="password-form" class="form-step hidden-form">
            <div class="form-group">
                <label for="new-password">Mật khẩu mới</label>
                <div class="input-group">
                    <input type="password" id="new-password" name="new_password" placeholder="Nhập mật khẩu mới" required>
                    <i class='bx bx-hide eye-icon' onclick="togglePass('new-password', this)"></i>
                </div>
            </div>
            <div class="form-group">
                <label for="confirm-password">Xác nhận mật khẩu</label>
                <div class="input-group">
                    <input type="password" id="confirm-password" name="confirm_password" placeholder="Xác nhận mật khẩu" required>
                    <i class='bx bx-hide eye-icon' onclick="togglePass('confirm-password', this)"></i>
                </div>
            </div>
            <button type="submit" id="change-password-btn">Đổi mật khẩu</button>
        </form>

        <!-- Success Message -->
        <div id="success-message" class="hidden-form" style="text-align: center; padding: 20px;">
            <div style="font-size: 48px; margin-bottom: 10px;">✓</div>
            <h2 style="color: #22c55e; margin: 0 0 10px;">Thành công!</h2>
            <p style="color: #666; margin: 0 0 20px;">Mật khẩu của bạn đã được đổi.</p>
            <a href="Dangnhap.php" style="display: inline-block; padding: 10px 20px; background: #007bff; color: #fff; text-decoration: none; border-radius: 8px;">Đăng nhập</a>
        </div>

        <a class="back-link" href="Dangnhap.php">← Quay lại đăng nhập</a>
    </div>

    <script>
    let otpTimerInterval = null;
    let currentEmail = '';

    // Toggle password visibility
    function togglePass(inputId, icon) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bx-hide');
            icon.classList.add('bx-show');
        } else {
            input.type = 'password';
            icon.classList.remove('bx-show');
            icon.classList.add('bx-hide');
        }
    }

    // Show notification
    function showNotification(message, isError = false) {
        const noticeWrap = document.querySelector('.ack-notice-wrap') || createNoticeWrap();
        const noticeItem = document.createElement('div');
        noticeItem.className = `ack-notice-item ${isError ? 'error' : 'success'}`;
        noticeItem.textContent = message;
        noticeWrap.appendChild(noticeItem);

        setTimeout(() => {
            noticeItem.style.transition = 'opacity 0.2s ease';
            noticeItem.style.opacity = '0';
            setTimeout(() => noticeItem.remove(), 220);
        }, 3500);
    }

    function createNoticeWrap() {
        const wrap = document.createElement('div');
        wrap.className = 'ack-notice-wrap';
        document.body.appendChild(wrap);
        return wrap;
    }

    // Update step indicator
    function updateSteps(step) {
        document.querySelectorAll('.step').forEach((el, idx) => {
            el.classList.remove('active', 'completed');
            if (idx + 1 < step) {
                el.classList.add('completed');
            } else if (idx + 1 === step) {
                el.classList.add('active');
            }
        });
    }

    // Show form step
    function showStep(stepNum) {
        document.querySelectorAll('.form-step').forEach(el => el.classList.add('hidden-form'));
        document.getElementById('success-message').classList.add('hidden-form');
        
        if (stepNum === 1) {
            document.getElementById('email-form').classList.remove('hidden-form');
        } else if (stepNum === 2) {
            document.getElementById('otp-form').classList.remove('hidden-form');
            startOTPTimer();
        } else if (stepNum === 3) {
            document.getElementById('password-form').classList.remove('hidden-form');
        } else if (stepNum === 4) {
            document.getElementById('success-message').classList.remove('hidden-form');
        }
        updateSteps(stepNum);
    }

    // OTP input auto-tab
    document.querySelectorAll('.otp-digit').forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1 && index < 5) {
                document.querySelectorAll('.otp-digit')[index + 1].focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && input.value === '' && index > 0) {
                document.querySelectorAll('.otp-digit')[index - 1].focus();
            }
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const digits = paste.replace(/\D/g, '').split('');
            document.querySelectorAll('.otp-digit').forEach((input, idx) => {
                input.value = digits[idx] || '';
            });
        });
    });

    // Get OTP code from inputs
    function getOTPCode() {
        return Array.from(document.querySelectorAll('.otp-digit'))
            .map(input => input.value)
            .join('');
    }

    // Clear OTP inputs
    function clearOTPInputs() {
        document.querySelectorAll('.otp-digit').forEach(input => input.value = '');
        document.querySelectorAll('.otp-digit')[0].focus();
    }

    // Start OTP timer
    function startOTPTimer() {
        let seconds = 600; // 10 minutes
        const timerElement = document.getElementById('timer-seconds');
        const timerContainer = document.getElementById('otp-timer');

        if (otpTimerInterval) clearInterval(otpTimerInterval);

        otpTimerInterval = setInterval(() => {
            seconds--;
            timerElement.textContent = seconds;

            if (seconds <= 0) {
                clearInterval(otpTimerInterval);
                timerContainer.classList.add('expired');
                timerContainer.innerHTML = 'Mã OTP đã hết hạn. <button type="button" id="resend-expired-btn">Gửi lại mã</button>';
                document.getElementById('resend-expired-btn').addEventListener('click', resendOTP);
            } else if (seconds <= 60) {
                timerContainer.classList.add('expired');
            }
        }, 1000);
    }

    // Send OTP
    document.getElementById('email-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('email').value.trim();

        if (!email) {
            showNotification('Vui lòng nhập email', true);
            return;
        }

        const btn = document.getElementById('send-otp-btn');
        btn.disabled = true;
        btn.textContent = 'Đang gửi...';

        try {
            const response = await fetch('otp_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'send_otp',
                    email: email
                })
            });

            const data = await response.json();

            if (data.success) {
                currentEmail = email;
                showNotification(data.message);
                setTimeout(() => {
                    clearOTPInputs();
                    showStep(2);
                }, 500);
            } else {
                showNotification(data.message, true);
                btn.disabled = false;
                btn.textContent = 'Gửi OTP';
            }
        } catch (error) {
            showNotification('Lỗi: ' + error.message, true);
            btn.disabled = false;
            btn.textContent = 'Gửi OTP';
        }
    });

    // Verify OTP
    document.getElementById('otp-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const otpCode = getOTPCode();

        if (otpCode.length !== 6) {
            showNotification('Vui lòng nhập đầy đủ 6 chữ số', true);
            return;
        }

        const btn = document.getElementById('verify-otp-btn');
        btn.disabled = true;
        btn.textContent = 'Đang xác thực...';

        try {
            const response = await fetch('otp_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'verify_otp',
                    email: currentEmail,
                    otp_code: otpCode
                })
            });

            const data = await response.json();

            if (data.success) {
                showNotification(data.message);
                clearInterval(otpTimerInterval);
                setTimeout(() => showStep(3), 500);
            } else {
                showNotification(data.message, true);
                btn.disabled = false;
                btn.textContent = 'Xác thực OTP';
            }
        } catch (error) {
            showNotification('Lỗi: ' + error.message, true);
            btn.disabled = false;
            btn.textContent = 'Xác thực OTP';
        }
    });

    // Change Password
    document.getElementById('password-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const newPassword = document.getElementById('new-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;

        if (newPassword.length < 6) {
            showNotification('Mật khẩu phải có ít nhất 6 ký tự', true);
            return;
        }

        if (newPassword !== confirmPassword) {
            showNotification('Mật khẩu không khớp', true);
            return;
        }

        const btn = document.getElementById('change-password-btn');
        btn.disabled = true;
        btn.textContent = 'Đang cập nhật...';

        try {
            const response = await fetch('otp_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'change_password',
                    email: currentEmail,
                    new_password: newPassword
                })
            });

            const data = await response.json();

            if (data.success) {
                showNotification(data.message);
                setTimeout(() => showStep(4), 500);
            } else {
                showNotification(data.message, true);
                btn.disabled = false;
                btn.textContent = 'Đổi mật khẩu';
            }
        } catch (error) {
            showNotification('Lỗi: ' + error.message, true);
            btn.disabled = false;
            btn.textContent = 'Đổi mật khẩu';
        }
    });

    // Resend OTP
    function resendOTP() {
        const btn = document.getElementById('resend-otp-btn');
        btn.disabled = true;
        btn.textContent = 'Đang gửi...';

        fetch('otp_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'send_otp',
                email: currentEmail
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Mã OTP mới đã được gửi');
                clearOTPInputs();
                const timerContainer = document.getElementById('otp-timer');
                timerContainer.classList.remove('expired');
                timerContainer.innerHTML = 'Mã hết hạn trong: <span id="timer-seconds">600</span>s';
                startOTPTimer();
            } else {
                showNotification(data.message, true);
            }
            btn.disabled = false;
            btn.textContent = 'Gửi lại mã OTP';
        })
        .catch(error => {
            showNotification('Lỗi: ' + error.message, true);
            btn.disabled = false;
            btn.textContent = 'Gửi lại mã OTP';
        });
    }

    document.getElementById('resend-otp-btn').addEventListener('click', resendOTP);

    // Initialize
    showStep(1);
    </script>
</body>

</html>
