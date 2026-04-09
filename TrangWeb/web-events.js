(function () {
  const CART_KEY = "ack_cart";
  const CART_API_ENDPOINT = "cart-handler.php";
  const USER_SESSION_ENDPOINT = "user-session.php";
  const USER_MENU_STYLE_ID = "ack-global-user-menu-style";

  function parseMoney(value) {
    if (typeof value === "number") return value;
    const digits = String(value || "")
      .replace(/[^\d]/g, "")
      .trim();
    return digits ? parseInt(digits, 10) : 0;
  }

  function formatMoney(amount) {
    return `${Number(amount || 0).toLocaleString("vi-VN")} ₫`;
  }

  function slugify(text) {
    return String(text || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/(^-|-$)/g, "");
  }

  function loadCart() {
    try {
      const raw = localStorage.getItem(CART_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (_) {
      return [];
    }
  }

  function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
  }

  function toast(message) {
    const node = document.createElement("div");
    node.textContent = message;
    Object.assign(node.style, {
      position: "fixed",
      right: "16px",
      bottom: "16px",
      zIndex: "4000",
      background: "#0b74e5",
      color: "#fff",
      padding: "10px 14px",
      borderRadius: "10px",
      fontSize: "13px",
      boxShadow: "0 8px 24px rgba(0,0,0,0.18)",
    });
    document.body.appendChild(node);
    setTimeout(() => node.remove(), 1600);
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function injectGlobalUserMenuStyles() {
    if (document.getElementById(USER_MENU_STYLE_ID)) {
      return;
    }

    const style = document.createElement("style");
    style.id = USER_MENU_STYLE_ID;
    style.textContent = `
      .ack-user-menu .ack-user-menu-toggle {
        border: none;
        background: transparent;
        padding: 0;
        display: inline-flex;
        align-items: center;
        gap: 8px;
      }

      .ack-user-menu .ack-user-menu-toggle::after {
        display: none;
      }

      .ack-user-menu .ack-user-avatar-chip {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #fff5d6;
        color: #f59f00;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #ffe8a1;
        font-size: 1.2rem;
      }

      .ack-user-menu .ack-user-mini-info {
        line-height: 1.1;
        text-align: left;
      }

      .ack-user-menu .ack-user-mini-name {
        max-width: 140px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 600;
        font-size: 0.86rem;
        color: #111827;
      }

      .ack-user-menu .ack-user-mini-role {
        font-size: 0.72rem;
        color: #6b7280;
      }

      .ack-user-menu .ack-user-dropdown-menu {
        min-width: 280px;
        border-radius: 12px;
        border: 1px solid #edf2f7;
        padding-top: 0;
        overflow: hidden;
      }

      .ack-user-menu .ack-user-dropdown-head {
        background: #f8fbff;
        padding: 12px 14px;
        border-bottom: 1px solid #e9eef5;
      }

      .ack-user-menu .ack-user-dropdown-head .name {
        font-weight: 700;
        color: #0f172a;
      }

      .ack-user-menu .ack-user-dropdown-head .email {
        font-size: 0.82rem;
        color: #475569;
      }

      .ack-user-menu .ack-user-dropdown-head .role {
        font-size: 0.75rem;
        color: #0b74e5;
        font-weight: 600;
      }

      .ack-user-menu .ack-user-dropdown-menu .dropdown-item {
        padding: 10px 14px;
        font-size: 0.92rem;
      }

      .ack-user-menu .ack-user-dropdown-menu .dropdown-item i {
        width: 18px;
      }

      .ack-user-menu .ack-promo-form-item {
        padding: 10px 14px 12px;
        border-top: 1px dashed #e5e7eb;
        background: #fcfdff;
      }

      .ack-user-menu .ack-promo-form-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 600;
        color: #334155;
        margin-bottom: 6px;
      }

      .ack-user-menu .ack-promo-form-wrap {
        display: flex;
        gap: 6px;
      }

      .ack-user-menu .ack-promo-select {
        font-size: 0.8rem;
        border-radius: 8px;
      }

      .ack-user-menu .ack-promo-submit {
        white-space: nowrap;
        border-radius: 8px;
      }
    `;

    document.head.appendChild(style);
  }

  function ensureOrderItemInDropdown(dropdownMenu) {
    if (!dropdownMenu) return;
    if (dropdownMenu.querySelector('a[href*="don-hang-cua-toi.php"]')) {
      return;
    }

    const orderItem = document.createElement("li");
    orderItem.innerHTML =
      '<a class="dropdown-item" href="don-hang-cua-toi.php"><i class="fas fa-receipt me-2 text-primary"></i>Đơn hàng của tôi</a>';

    const manageItem = dropdownMenu.querySelector(
      'a[href*="tai-khoan.php?tab=manage"]',
    );
    const settingsItem = dropdownMenu.querySelector(
      'a[href*="tai-khoan.php?tab=settings"]',
    );

    if (manageItem?.parentElement) {
      manageItem.parentElement.insertAdjacentElement("afterend", orderItem);
      return;
    }

    if (settingsItem?.parentElement) {
      settingsItem.parentElement.insertAdjacentElement(
        "beforebegin",
        orderItem,
      );
      return;
    }

    dropdownMenu.appendChild(orderItem);
  }

  function syncExistingDropdown() {
    document.querySelectorAll(".user-dropdown-menu").forEach((menu) => {
      const hasLogout = !!menu.querySelector('a[href*="logout.php"]');
      if (hasLogout) {
        ensureOrderItemInDropdown(menu);
      }
    });
  }

  function findTopActionContainer() {
    return (
      document.querySelector(".top-bar .d-flex.align-items-center.gap-3") ||
      document.querySelector("nav .d-flex.gap-3") ||
      document.querySelector(
        ".top-bar .d-flex.align-items-center:last-child",
      ) ||
      null
    );
  }

  function findUserAnchor(container) {
    if (!container) return null;
    const links = container.querySelectorAll("a");
    for (const link of links) {
      if (
        link.querySelector(".fa-user-circle") ||
        link.querySelector(".fa-user") ||
        link.querySelector(".fa-user-large")
      ) {
        return link;
      }
    }

    return null;
  }

  function buildUserMenuHtml(userData) {
    const isLoggedIn = !!userData?.is_logged_in;
    const name = escapeHtml(userData?.name || "Khách hàng");
    const role = escapeHtml(userData?.role_label || "Khách");
    const email = escapeHtml(userData?.email || "Chưa cập nhật email");

    const loggedInItems = `
      <li><a class="dropdown-item" href="tai-khoan.php?tab=info"><i class="fas fa-id-badge me-2 text-primary"></i>Thông tin tài khoản</a></li>
      <li><a class="dropdown-item" href="tai-khoan.php?tab=manage"><i class="fas fa-briefcase me-2 text-primary"></i>Quản lý tài khoản</a></li>
      <li><a class="dropdown-item" href="don-hang-cua-toi.php"><i class="fas fa-receipt me-2 text-primary"></i>Đơn hàng của tôi</a></li>
      <li><a class="dropdown-item" href="tai-khoan.php?tab=settings"><i class="fas fa-gear me-2 text-primary"></i>Cài đặt tài khoản</a></li>
      <li class="ack-promo-form-item">
        <label class="ack-promo-form-label">Lấy khuyến mãi nhanh</label>
        <form class="ack-promo-form-wrap" data-promo-form>
          <select class="form-select form-select-sm ack-promo-select" data-promo-select>
            <option value="">-- Chọn mã --</option>
          </select>
          <button class="btn btn-sm btn-primary ack-promo-submit" type="submit">Lấy mã</button>
        </form>
      </li>
      <li><hr class="dropdown-divider my-1"></li>
      <li><a class="dropdown-item text-danger" href="../Login/logout.php"><i class="fas fa-right-from-bracket me-2"></i>Đăng xuất</a></li>
    `;

    const guestItems = `
      <li><a class="dropdown-item" href="../Login/Dangnhap.php"><i class="fas fa-right-to-bracket me-2 text-primary"></i>Đăng nhập</a></li>
      <li><a class="dropdown-item" href="../Login/Dangnhap.php"><i class="fas fa-user-plus me-2 text-primary"></i>Tạo tài khoản mới</a></li>
    `;

    return `
      <div class="dropdown ack-user-menu">
        <button class="dropdown-toggle ack-user-menu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="ack-user-avatar-chip"><i class="fas fa-user"></i></span>
          <span class="d-none d-md-block ack-user-mini-info">
            <span class="d-block ack-user-mini-name">${name}</span>
            <span class="d-block ack-user-mini-role">${role}</span>
          </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end ack-user-dropdown-menu shadow">
          <li class="ack-user-dropdown-head">
            <div class="name">${name}</div>
            <div class="email">${email}</div>
            <div class="role">${role}</div>
          </li>
          ${isLoggedIn ? loggedInItems : guestItems}
        </ul>
      </div>
    `;
  }

  async function setupGlobalUserMenu() {
    syncExistingDropdown();

    if (document.querySelector(".user-menu-toggle, .ack-user-menu-toggle")) {
      return;
    }

    const container = findTopActionContainer();
    if (!container) {
      return;
    }

    const userAnchor = findUserAnchor(container);
    if (!userAnchor) {
      return;
    }

    let userData = {
      is_logged_in: false,
      name: "Khách hàng",
      email: "",
      role_label: "Khách",
    };

    try {
      const response = await fetch(USER_SESSION_ENDPOINT, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
        cache: "no-store",
      });
      const data = await response.json();
      if (response.ok && data?.ok === true) {
        userData = data;
      }
    } catch (_) {
      // Keep guest fallback
    }

    injectGlobalUserMenuStyles();

    const wrapper = document.createElement("div");
    wrapper.innerHTML = buildUserMenuHtml(userData).trim();
    const menuNode = wrapper.firstElementChild;
    if (!menuNode) {
      return;
    }

    userAnchor.replaceWith(menuNode);
  }

  async function fetchAvailablePromotions() {
    const form = new URLSearchParams();
    form.set("action", "get_available_vouchers");

    const response = await fetch(CART_API_ENDPOINT, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: form.toString(),
      cache: "no-store",
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok || data?.success !== true) {
      return [];
    }

    return Array.isArray(data?.vouchers) ? data.vouchers : [];
  }

  function populatePromotionSelect(select, vouchers) {
    if (!select) return;

    const options = ['<option value="">-- Chọn mã --</option>'];
    vouchers.forEach((voucher) => {
      const code = String(voucher?.ma_voucher || "").trim();
      if (!code) return;
      const label = String(voucher?.label || code).trim();
      options.push(
        `<option value="${escapeHtml(code)}">${escapeHtml(label)}</option>`,
      );
    });

    select.innerHTML = options.join("");
    select.dataset.loaded = "1";
  }

  function bindPromotionForms() {
    document.addEventListener("shown.bs.dropdown", async (event) => {
      const dropdown = event.target?.closest(".ack-user-menu");
      if (!dropdown) return;

      const select = dropdown.querySelector("[data-promo-select]");
      if (!select || select.dataset.loaded === "1") return;

      const vouchers = await fetchAvailablePromotions();
      populatePromotionSelect(select, vouchers);
    });

    document.addEventListener("submit", (event) => {
      const form = event.target?.closest("[data-promo-form]");
      if (!form) return;

      event.preventDefault();
      const select = form.querySelector("[data-promo-select]");
      const code = String(select?.value || "").trim();
      if (!code) {
        toast("Bạn chưa chọn mã khuyến mãi.");
        return;
      }

      window.location.href = `giohang.php?voucher=${encodeURIComponent(code)}`;
    });
  }

  function updateCartBadge() {
    const cart = loadCart();
    const qty = cart.reduce((sum, i) => sum + Number(i.qty || 0), 0);

    document.querySelectorAll("[data-cart-count]").forEach((el) => {
      el.textContent = String(qty);
    });
  }

  function getProductFromCard(card) {
    if (!card) return null;

    const title =
      card.getAttribute("data-product-name") ||
      card.querySelector(".product-title")?.textContent ||
      card.querySelector(".card-title")?.textContent ||
      "Sản phẩm";

    const priceText =
      card.getAttribute("data-product-price") ||
      card.querySelector(".price-current")?.textContent ||
      card.querySelector(".price-new")?.textContent ||
      "0";

    const image =
      card.getAttribute("data-product-img") ||
      card.querySelector("img")?.getAttribute("src") ||
      "../TrangUser/ack.png";

    const id =
      card.getAttribute("data-product-id") ||
      slugify(title) ||
      String(Date.now());

    return {
      id,
      name: String(title).trim(),
      price: parseMoney(priceText),
      old_price: parseMoney(
        card.getAttribute("data-product-old-price") ||
          card.querySelector(".price-old")?.textContent ||
          "0",
      ),
      img: image,
      qty: 1,
      desc: "",
    };
  }

  function addItemToCart(item) {
    if (!item || !item.id) return Promise.resolve(false);

    const form = new URLSearchParams();
    form.set("action", "add_to_cart");
    form.set("ma_san_pham", String(item.id));
    form.set("ten_san_pham", String(item.name || "Sản phẩm"));
    form.set("mo_ta", String(item.desc || ""));
    form.set("gia_ban", String(Number(item.price || 0)));
    form.set("gia_goc", String(Number(item.old_price || item.price || 0)));
    form.set("so_luong", "1");
    form.set("hinh_anh", String(item.img || "../TrangUser/ack.png"));
    form.set("voucher", "");
    form.set("thong_tin_giao", "Giao nhanh trong ngày");

    return fetch(CART_API_ENDPOINT, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: form.toString(),
    })
      .then((res) => res.json().catch(() => ({})))
      .then((data) => {
        if (!data?.success) {
          throw new Error(data?.message || "Thêm vào giỏ hàng thất bại.");
        }

        const cart = loadCart();
        const found = cart.find((c) => c.id === item.id);
        if (found) {
          found.qty = Number(found.qty || 0) + 1;
        } else {
          cart.push({ ...item, qty: 1 });
        }
        saveCart(cart);
        updateCartBadge();
        toast(`Đã thêm "${item.name}" vào giỏ`);
        return true;
      })
      .catch((error) => {
        toast(error?.message || "Không thể thêm sản phẩm vào giỏ.");
        return false;
      });
  }

  function bindAddToCartButtons() {
    const buttons = document.querySelectorAll(
      ".btn-add, .btn-add-cart, [data-add-cart]",
    );

    buttons.forEach((btn) => {
      if (
        btn.tagName === "BUTTON" &&
        (!btn.getAttribute("type") || btn.getAttribute("type") === "submit")
      ) {
        btn.setAttribute("type", "button");
      }

      btn.addEventListener("click", async (event) => {
        event.preventDefault();
        event.stopPropagation();

        const card =
          btn.closest(".product-card") || btn.closest(".product-container");
        const item = getProductFromCard(card);
        if (!item) return;

        await addItemToCart(item);
      });
    });

    const buyNowButton = document.querySelector(".btn-buy-now");
    if (buyNowButton) {
      buyNowButton.addEventListener("click", async (event) => {
        event.preventDefault();
        const card =
          buyNowButton.closest(".product-container") ||
          document.querySelector(".product-container") ||
          document.body;
        const item = getProductFromCard(card);
        if (item) {
          const ok = await addItemToCart(item);
          if (!ok) {
            return;
          }
        }
        window.location.href = "giohang.php";
      });
    }
  }

  function bindSearchFilter() {
    const inputs = document.querySelectorAll(
      ".search-box input, .search-input, input[placeholder*='Tìm kiếm sản phẩm']",
    );
    if (!inputs.length) return;

    const cards = Array.from(document.querySelectorAll(".product-card"));
    if (!cards.length) return;

    const runFilter = (keyword) => {
      const q = String(keyword || "")
        .trim()
        .toLowerCase();
      cards.forEach((card) => {
        const title = (
          card.querySelector(".product-title")?.textContent ||
          card.querySelector(".card-title")?.textContent ||
          ""
        ).toLowerCase();

        card.parentElement.style.display =
          !q || title.includes(q) ? "" : "none";
      });
    };

    inputs.forEach((input) => {
      input.addEventListener("input", () => runFilter(input.value));
    });
  }

  function init() {
    bindAddToCartButtons();
    bindSearchFilter();
    updateCartBadge();
    bindPromotionForms();
    setupGlobalUserMenu();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
