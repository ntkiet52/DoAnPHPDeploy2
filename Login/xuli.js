const container = document.getElementById("container");
const registerBtn = document.getElementById("register");
const loginBtn = document.getElementById("login");

function ackEnsureNoticeHost() {
  let host = document.getElementById("ack-screen-notice-host");
  if (host) {
    return host;
  }

  host = document.createElement("div");
  host.id = "ack-screen-notice-host";
  host.setAttribute("aria-live", "polite");
  host.style.position = "fixed";
  host.style.top = "20px";
  host.style.right = "20px";
  host.style.zIndex = "3000";
  host.style.display = "flex";
  host.style.flexDirection = "column";
  host.style.gap = "10px";
  host.style.width = "min(360px, calc(100vw - 24px))";
  document.body.appendChild(host);
  return host;
}

function ackShowScreenNotice(message, type = "warning", duration = 2800) {
  const text = String(message ?? "").trim();
  if (!text) return;

  const palette = {
    success: {
      bg: "#e8f7ee",
      border: "#b7ebc6",
      color: "#1a7f37",
    },
    info: {
      bg: "#e9f2ff",
      border: "#b8d3ff",
      color: "#0f4fa8",
    },
    warning: {
      bg: "#fff7e6",
      border: "#ffe0a3",
      color: "#8a5a00",
    },
    error: {
      bg: "#fdecec",
      border: "#f2b6bc",
      color: "#842029",
    },
  };

  const tone = palette[type] || palette.warning;
  const host = ackEnsureNoticeHost();
  const item = document.createElement("div");
  item.style.background = tone.bg;
  item.style.border = `1px solid ${tone.border}`;
  item.style.color = tone.color;
  item.style.padding = "10px 14px";
  item.style.borderRadius = "10px";
  item.style.fontSize = "14px";
  item.style.boxShadow = "0 8px 24px rgba(0,0,0,0.12)";
  item.style.opacity = "1";
  item.style.transition = "opacity 0.2s ease";
  item.textContent = text;

  host.appendChild(item);

  window.setTimeout(() => {
    item.style.opacity = "0";
    window.setTimeout(() => item.remove(), 220);
  }, duration);
}

window.alert = function (message) {
  const normalized = String(message ?? "")
    .trim()
    .toLowerCase();

  if (
    normalized === "đăng nhập thành công!" ||
    normalized === "dang nhap thanh cong!"
  ) {
    return;
  }

  ackShowScreenNotice(message, "warning");
};

// 1. CHỨC NĂNG TRƯỢT QUA LẠI
const mobileLinkRegister = document.getElementById("link-register-mobile"); // Nếu có link mobile
const mobileLinkLogin = document.getElementById("link-login-mobile"); // Nếu có link mobile

function goToRegister() {
  container.classList.add("active");
}

function goToLogin() {
  container.classList.remove("active");
}

registerBtn.addEventListener("click", goToRegister);
loginBtn.addEventListener("click", goToLogin);

// (Nếu bạn có dùng link mobile text ở bước trước)
if (mobileLinkRegister)
  mobileLinkRegister.addEventListener("click", (e) => {
    e.preventDefault();
    goToRegister();
  });
if (mobileLinkLogin)
  mobileLinkLogin.addEventListener("click", (e) => {
    e.preventDefault();
    goToLogin();
  });

// 2. CHỨC NĂNG ẨN/HIỆN MẬT KHẨU
function togglePass(inputId, icon) {
  const input = document.getElementById(inputId);
  if (input.type === "password") {
    input.type = "text";
    icon.classList.remove("bx-hide");
    icon.classList.add("bx-show");
  } else {
    input.type = "password";
    icon.classList.remove("bx-show");
    icon.classList.add("bx-hide");
  }
}

// 3. XỬ LÝ ĐĂNG KÝ
const signUpForm = document.getElementById("form-register");
signUpForm.addEventListener("submit", function (e) {
  e.preventDefault();
  const name = signUpForm.querySelector(
    'input[placeholder="Tên của bạn"]',
  ).value;
  const email = signUpForm.querySelector('input[placeholder="Email"]').value;
  const password = document.getElementById("reg-pass").value;
  const rePassword = document.getElementById("reg-repass").value;

  if (password.length < 6) {
    alert("Mật khẩu phải có ít nhất 6 ký tự!");
    return;
  }
  if (password !== rePassword) {
    alert("Lỗi: Mật khẩu nhập lại không khớp!");
    return;
  }

  fetch("register.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`,
  })
    .then((res) => res.text())
    .then((data) => {
      if (data === "success") {
        alert("Đăng ký thành công! Vui lòng đăng nhập.");
        container.classList.remove("active");
        signUpForm.reset();
      } else if (data === "exists") {
        alert("Email đã tồn tại!");
      } else if (data === "invalid_email") {
        alert("Email không hợp lệ!");
      } else if (data === "weak_password") {
        alert("Mật khẩu phải có ít nhất 6 ký tự!");
      } else if (data === "missing") {
        alert("Vui lòng nhập đầy đủ thông tin đăng ký.");
      } else {
        alert("Đăng ký thất bại!");
      }
    })
    .catch(() => {
      alert("Không thể kết nối máy chủ khi đăng ký.");
    });
});

// 4. XỬ LÝ ĐĂNG NHẬP (QUAN TRỌNG: PHÂN QUYỀN TẠI ĐÂY)
const signInForm = document.getElementById("form-login");

function setButtonLoadingState(button, isLoading, loadingText = "Đang xử lý") {
  if (!button) return;

  if (!button.dataset.defaultText) {
    button.dataset.defaultText = button.textContent.trim();
  }

  button.disabled = Boolean(isLoading);
  button.classList.toggle("is-loading", Boolean(isLoading));
  button.textContent = isLoading
    ? loadingText
    : button.dataset.defaultText || "Đăng Nhập";
}

function showCenteredLoginLoader(durationMs = 2000) {
  const existing = document.querySelector(".ack-login-overlay");
  if (existing) {
    existing.remove();
  }

  const overlay = document.createElement("div");
  overlay.className = "ack-login-overlay";

  const spinner = document.createElement("div");
  spinner.className = "ack-login-loader";
  overlay.appendChild(spinner);

  document.body.appendChild(overlay);

  return new Promise((resolve) => {
    window.setTimeout(() => {
      resolve();
    }, durationMs);
  });
}

signInForm.addEventListener("submit", function (e) {
  e.preventDefault();
  const email = signInForm.querySelector('input[type="email"]').value;
  const password = document.getElementById("login-pass").value;
  const loginSubmitBtn = signInForm.querySelector('button[type="submit"]');

  setButtonLoadingState(loginSubmitBtn, true, "Đang đăng nhập");

  fetch("login.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`,
  })
    .then((res) => res.text())
    .then((raw) => {
      let data = raw;
      try {
        data = JSON.parse(raw);
      } catch (_) {
        // Backward-compatible with plain text responses
      }

      const status =
        typeof data === "object" && data !== null ? data.status : data;
      const redirect =
        typeof data === "object" && data !== null ? data.redirect : null;

      if (status === "success") {
        showCenteredLoginLoader(2000).then(() => {
          window.location.href = redirect || "../TrangWeb/index.php";
        });
        return;
      } else if (status === "forbidden_admin") {
        setButtonLoadingState(loginSubmitBtn, false);
        alert(
          "Tài khoản này không có quyền vào trang admin. Chỉ tài khoản do admin tạo mới được truy cập.",
        );
      } else if (status === "inactive") {
        setButtonLoadingState(loginSubmitBtn, false);
        alert("Tài khoản đang bị khóa hoặc chưa kích hoạt.");
      } else if (status === "wrong_password") {
        setButtonLoadingState(loginSubmitBtn, false);
        alert("Sai mật khẩu!");
      } else if (status === "not_found") {
        setButtonLoadingState(loginSubmitBtn, false);
        alert("Tài khoản không tồn tại!");
      } else if (status === "missing") {
        setButtonLoadingState(loginSubmitBtn, false);
        alert("Vui lòng nhập email và mật khẩu.");
      } else {
        setButtonLoadingState(loginSubmitBtn, false);
        alert("Lỗi đăng nhập!");
      }
    })
    .catch(() => {
      setButtonLoadingState(loginSubmitBtn, false);
      alert("Không thể kết nối máy chủ khi đăng nhập.");
    });
});
