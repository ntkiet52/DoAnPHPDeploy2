(function () {
  const CART_KEY = "ack_cart";
  const CART_API_ENDPOINT = "cart-handler.php";
  const NOTIFICATION_API_ENDPOINT = "notification-handler.php";
  const USER_SESSION_ENDPOINT = "user-session.php";
  const LOCATION_API_ENDPOINT = "locations.php";
  const PRODUCT_SEARCH_ENDPOINT = "product-search.php";
  const AI_ADVISOR_ENDPOINT = "ai-advisor.php";
  const USER_MENU_STYLE_ID = "ack-global-user-menu-style";
  const CHATBOT_STYLE_ID = "ack-global-chatbot-style";
  const LOCATION_STORAGE_KEY = "ack_selected_location";
  let userSessionPromise = null;
  let locationListPromise = null;

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

  function injectGlobalFooterLayoutStyles() {
    if (document.getElementById("ack-global-footer-layout-style")) {
      return;
    }

    const style = document.createElement("style");
    style.id = "ack-global-footer-layout-style";
    style.textContent = `
      .newsletter-section,
      footer,
      .site-footer,
      .bg-light.py-4,
      .feedback-section {
        width: 100%;
        margin-left: 0 !important;
        margin-right: 0 !important;
        left: 0 !important;
        right: 0 !important;
      }

      .newsletter-section > .container,
      .feedback-section > .container,
      .bg-light.py-4 > .container,
      footer > .container {
        width: 100%;
        max-width: 1320px !important;
        margin-left: auto !important;
        margin-right: auto !important;
        padding-left: 60px !important;
        padding-right: 60px !important;
      }

      footer,
      .site-footer {
        background-color: #1e2743;
      }

      footer > .container {
        padding-top: 40px !important;
        padding-bottom: 24px !important;
      }

      footer > .container > .row {
        display: grid !important;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 48px;
        align-items: flex-start;
        margin: 0 !important;
      }

      footer > .container > .row > [class*="col"] {
        width: auto !important;
        max-width: none !important;
        flex: 0 0 auto !important;
        padding: 0 !important;
        margin: 0 !important;
      }

      .site-footer {
        padding: 0 !important;
      }

      .site-footer .footer-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 48px;
        align-items: flex-start;
        max-width: 1320px;
        margin: 0 auto;
        padding: 40px 60px 0;
      }

      footer .copyright-border,
      .site-footer .copyright-border {
        border-top: 1px solid rgba(255, 255, 255, 0.14) !important;
        margin-top: 20px !important;
        padding-top: 20px !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        gap: 16px;
        flex-wrap: wrap;
      }

      .site-footer .copyright-border {
        max-width: 1320px;
        margin-left: auto !important;
        margin-right: auto !important;
        padding-left: 60px !important;
        padding-right: 60px !important;
        padding-bottom: 24px !important;
      }

      @media (max-width: 991.98px) {
        .newsletter-section > .container,
        .feedback-section > .container,
        .bg-light.py-4 > .container,
        footer > .container {
          padding-left: 28px !important;
          padding-right: 28px !important;
        }

        footer > .container > .row,
        .site-footer .footer-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
          gap: 32px;
        }

        .site-footer .footer-grid,
        .site-footer .copyright-border {
          padding-left: 28px !important;
          padding-right: 28px !important;
        }
      }

      @media (max-width: 575.98px) {
        footer > .container > .row,
        .site-footer .footer-grid {
          grid-template-columns: 1fr;
          gap: 24px;
        }

        footer .copyright-border,
        .site-footer .copyright-border {
          flex-direction: column;
          align-items: flex-start !important;
        }
      }
    `;

    document.head.appendChild(style);
  }

  function injectStockUiStyles() {
    if (document.getElementById("ack-stock-ui-style")) return;

    const style = document.createElement("style");
    style.id = "ack-stock-ui-style";
    style.textContent = `
      .ack-stock-badge {
        margin-top: 8px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #dc2626;
        background: #fee2e2;
        border: 1px solid #fecaca;
        border-radius: 999px;
        padding: 4px 10px;
      }

      .ack-out-of-stock-btn {
        opacity: 0.65 !important;
        cursor: not-allowed !important;
      }
    `;

    document.head.appendChild(style);
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

      .ack-notify-menu .ack-notify-toggle {
        border: none;
        background: transparent;
        color: #111827;
        padding: 0;
        position: relative;
      }

      .ack-notify-menu .ack-notify-toggle::after {
        display: none;
      }

      .ack-notify-badge {
        font-size: 10px;
        min-width: 17px;
        height: 17px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
      }

      .ack-notify-menu .ack-notify-dropdown {
        width: min(420px, 92vw);
        max-height: 420px;
        overflow: auto;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 0;
      }

      .ack-notify-menu .ack-notify-head {
        position: sticky;
        top: 0;
        background: #f8fbff;
        border-bottom: 1px solid #e5e7eb;
        padding: 10px 12px;
        font-weight: 700;
        z-index: 1;
      }

      .ack-notify-item {
        display: block;
        text-decoration: none;
        color: #111827;
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
      }

      .ack-notify-item:hover {
        background: #f8fbff;
        color: #111827;
      }

      .ack-notify-item.unread {
        background: #f4f8ff;
      }

      .ack-notify-item-title {
        font-size: 0.86rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 2px;
      }

      .ack-notify-item-desc {
        font-size: 0.8rem;
        color: #475569;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }

      .ack-notify-item-meta {
        margin-top: 3px;
        font-size: 0.74rem;
        color: #64748b;
      }

      .ack-notify-empty {
        padding: 18px 12px;
        text-align: center;
        color: #64748b;
        font-size: 0.85rem;
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

  function findNotificationAnchor(container) {
    if (!container) return null;
    const links = container.querySelectorAll("a");
    for (const link of links) {
      if (link.querySelector(".fa-bell")) {
        return link;
      }
    }
    return null;
  }

  function formatNotificationTime(value) {
    if (!value) return "";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "";
    return date.toLocaleString("vi-VN", {
      hour: "2-digit",
      minute: "2-digit",
      day: "2-digit",
      month: "2-digit",
    });
  }

  async function fetchNotifications() {
    try {
      const form = new URLSearchParams();
      form.set("action", "list");
      const response = await fetch(NOTIFICATION_API_ENDPOINT, {
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
        return {
          is_logged_in: false,
          notifications: [],
          unseen_count: 0,
        };
      }

      return {
        is_logged_in: !!data?.is_logged_in,
        notifications: Array.isArray(data?.notifications)
          ? data.notifications
          : [],
        unseen_count: Math.max(
          0,
          Number.parseInt(String(data?.unseen_count || 0), 10) || 0,
        ),
      };
    } catch (_) {
      return {
        is_logged_in: false,
        notifications: [],
        unseen_count: 0,
      };
    }
  }

  async function markNotificationsSeen() {
    try {
      const form = new URLSearchParams();
      form.set("action", "mark_seen");
      await fetch(NOTIFICATION_API_ENDPOINT, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: form.toString(),
        cache: "no-store",
      });
    } catch (_) {
      // noop
    }
  }

  async function markNotificationSeenById(notificationId) {
    const id = String(notificationId || "").trim();
    if (!id) return;

    try {
      const form = new URLSearchParams();
      form.set("action", "mark_one");
      form.set("notification_id", id);
      await fetch(NOTIFICATION_API_ENDPOINT, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: form.toString(),
        cache: "no-store",
        keepalive: true,
      });
    } catch (_) {
      // noop
    }
  }

  function updateNotificationBadgeByDelta(menuNode, delta) {
    const badge = menuNode?.querySelector("[data-notify-count]");
    if (!badge) return;

    const current = Number.parseInt(String(badge.textContent || "0"), 10) || 0;
    const next = Math.max(0, current + Number(delta || 0));

    if (next <= 0) {
      badge.textContent = "0";
      badge.style.display = "none";
      return;
    }

    badge.textContent = next > 99 ? "99+" : String(next);
    badge.style.display = "inline-flex";
  }

  function buildNotificationMenuHtml(payload) {
    const notifications = Array.isArray(payload?.notifications)
      ? payload.notifications
      : [];
    const unseenCount = Math.max(
      0,
      Number.parseInt(String(payload?.unseen_count || 0), 10) || 0,
    );
    const displayCount = unseenCount > 99 ? "99+" : String(unseenCount);

    let listHtml =
      '<li class="ack-notify-empty">Chưa có thông báo phản hồi nào.</li>';
    if (notifications.length > 0) {
      listHtml = notifications
        .map((item) => {
          const title = escapeHtml(item?.title || "Thông báo mới");
          const sender = escapeHtml(item?.sender_name || "Người dùng");
          const content = escapeHtml(item?.content || "");
          const productName = escapeHtml(item?.product_name || "");
          const when = formatNotificationTime(item?.created_at || "");
          const url = escapeHtml(item?.url || "#");
          const notificationId = escapeHtml(item?.id || "");
          const isRead = !!item?.is_read;
          return `
            <li>
              <a class="ack-notify-item ${isRead ? "" : "unread"}" href="${url}" data-notify-id="${notificationId}" data-notify-read="${isRead ? "1" : "0"}">
                <div class="ack-notify-item-title">${title}</div>
                <div class="ack-notify-item-desc"><strong>${sender}:</strong> ${content || "(không có nội dung)"}</div>
                <div class="ack-notify-item-meta">${productName ? `Sản phẩm: ${productName} · ` : ""}${when}</div>
              </a>
            </li>
          `;
        })
        .join("");
    }

    return `
      <div class="dropdown ack-notify-menu">
        <button class="dropdown-toggle ack-notify-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-bell fa-lg"></i>
          <span class="ack-notify-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" data-notify-count ${unseenCount > 0 ? "" : 'style="display:none"'}>${displayCount}</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end ack-notify-dropdown shadow">
          <li class="ack-notify-head">Thông báo phản hồi</li>
          ${listHtml}
        </ul>
      </div>
    `;
  }

  async function setupGlobalNotificationMenu() {
    if (document.querySelector(".ack-notify-menu")) return;

    const container = findTopActionContainer();
    if (!container) return;

    const bellAnchor = findNotificationAnchor(container);
    if (!bellAnchor) return;

    injectGlobalUserMenuStyles();

    const payload = await fetchNotifications();
    const wrapper = document.createElement("div");
    wrapper.innerHTML = buildNotificationMenuHtml(payload).trim();
    const menuNode = wrapper.firstElementChild;
    if (!menuNode) return;

    bellAnchor.replaceWith(menuNode);

    menuNode.addEventListener("click", (event) => {
      const target = event.target;
      if (!(target instanceof Element)) return;

      const notifyLink = target.closest(".ack-notify-item[data-notify-id]");
      if (!notifyLink) return;

      if (notifyLink.getAttribute("data-notify-read") === "1") {
        return;
      }

      notifyLink.setAttribute("data-notify-read", "1");
      notifyLink.classList.remove("unread");

      const notificationId = notifyLink.getAttribute("data-notify-id") || "";
      updateNotificationBadgeByDelta(menuNode, -1);
      markNotificationSeenById(notificationId);
    });
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

  async function fetchCurrentUserSession() {
    if (userSessionPromise) return userSessionPromise;

    userSessionPromise = fetch(USER_SESSION_ENDPOINT, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
      cache: "no-store",
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({}))
          .then((data) => {
            if (response.ok && data?.ok === true) {
              return data;
            }
            return {
              is_logged_in: false,
              name: "Khách hàng",
              email: "",
              role: "guest",
              role_label: "Khách",
            };
          }),
      )
      .catch(() => ({
        is_logged_in: false,
        name: "Khách hàng",
        email: "",
        role: "guest",
        role_label: "Khách",
      }));

    return userSessionPromise;
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

    const userData = await fetchCurrentUserSession();

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

  function setCartBadgeCount(qty) {
    const safeQty = Math.max(0, Number.parseInt(String(qty || 0), 10) || 0);

    document.querySelectorAll("[data-cart-count]").forEach((el) => {
      el.textContent = String(safeQty);
    });
  }

  function ensureCartBadgeElements() {
    const cartAnchors = document.querySelectorAll('a[href*="giohang.php"]');
    cartAnchors.forEach((anchor) => {
      if (anchor.querySelector("[data-cart-count]")) {
        return;
      }

      const hasCartIcon =
        !!anchor.querySelector(".fa-shopping-basket") ||
        !!anchor.querySelector(".fa-cart-shopping") ||
        !!anchor.querySelector(".fa-cart-plus");

      if (!hasCartIcon) {
        return;
      }

      anchor.classList.add("position-relative");

      const badge = document.createElement("span");
      badge.setAttribute("data-cart-count", "");
      badge.className =
        "position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger";
      badge.textContent = "0";
      anchor.appendChild(badge);
    });
  }

  async function updateCartBadge() {
    ensureCartBadgeElements();

    try {
      const form = new URLSearchParams();
      form.set("action", "get_cart_count");

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
      if (response.ok && data?.success === true) {
        setCartBadgeCount(data?.cart_count || 0);
        return;
      }
    } catch (_) {
      // fallback local only when server endpoint is temporarily unavailable
    }

    const cart = loadCart();
    const qty = cart.reduce((sum, i) => sum + Number(i.qty || 0), 0);
    setCartBadgeCount(qty);
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

    const stockRaw =
      card.getAttribute("data-product-stock") ||
      card
        .querySelector("[data-product-stock]")
        ?.getAttribute("data-product-stock") ||
      "";
    const stock = Number.parseInt(String(stockRaw || ""), 10);

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
      stock: Number.isFinite(stock) ? Math.max(0, stock) : null,
      qty: 1,
      desc: "",
    };
  }

  async function fetchStockMapByIds(productIds) {
    const ids = Array.from(
      new Set(
        (Array.isArray(productIds) ? productIds : [])
          .map((id) => String(id || "").trim())
          .filter(Boolean),
      ),
    );

    if (!ids.length) return {};

    const form = new URLSearchParams();
    form.set("action", "get_stock_map");
    ids.forEach((id) => form.append("ids[]", id));

    try {
      const res = await fetch(CART_API_ENDPOINT, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: form.toString(),
        cache: "no-store",
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok || data?.success !== true) return {};
      return data?.stock_map && typeof data.stock_map === "object"
        ? data.stock_map
        : {};
    } catch (_) {
      return {};
    }
  }

  function markOutOfStock(card, stock) {
    if (!card) return;

    card.setAttribute(
      "data-product-stock",
      String(Math.max(0, Number(stock || 0))),
    );

    const actionButtons = card.querySelectorAll(
      ".btn-add, .btn-add-cart, [data-add-cart], .btn-buy-now",
    );

    actionButtons.forEach((btn) => {
      btn.disabled = true;
      btn.classList.add("ack-out-of-stock-btn");
      btn.setAttribute("title", "Sản phẩm đã hết hàng");
    });

    if (!card.querySelector(".ack-stock-badge")) {
      const badge = document.createElement("div");
      badge.className = "ack-stock-badge";
      badge.textContent = "Hết hàng - vui lòng chờ admin nhập thêm";
      const host = card.querySelector(".card-body") || card;
      host.appendChild(badge);
    }
  }

  async function syncProductStockStates() {
    injectStockUiStyles();

    const productCards = Array.from(
      document.querySelectorAll(".product-card[data-product-id]"),
    );
    const productContainers = Array.from(
      document.querySelectorAll(".product-container[data-product-id]"),
    );
    const allNodes = [...productCards, ...productContainers];

    if (!allNodes.length) return;

    const ids = allNodes
      .map((node) => String(node.getAttribute("data-product-id") || "").trim())
      .filter(Boolean);

    const stockMap = await fetchStockMapByIds(ids);
    allNodes.forEach((node) => {
      const productId = String(
        node.getAttribute("data-product-id") || "",
      ).trim();
      if (!productId) return;

      if (Object.prototype.hasOwnProperty.call(stockMap, productId)) {
        const stock = Math.max(
          0,
          Number.parseInt(String(stockMap[productId]), 10) || 0,
        );
        node.setAttribute("data-product-stock", String(stock));
        if (stock <= 0) {
          markOutOfStock(node, 0);
        }
      }
    });
  }

  function addItemToCart(item) {
    if (!item || !item.id) return Promise.resolve(false);

    if (Number.isFinite(item.stock) && Number(item.stock) <= 0) {
      toast("Sản phẩm đã hết hàng. Vui lòng chờ admin nhập thêm.");
      return Promise.resolve(false);
    }

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
    injectGlobalHeaderUxStyles();

    const inputs = document.querySelectorAll(
      ".search-box input, .search-input, input[placeholder*='Tìm kiếm sản phẩm']",
    );
    if (!inputs.length) return;

    const cards = Array.from(document.querySelectorAll(".product-card"));

    const runLocalFilter = (keyword) => {
      if (!cards.length) return;
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

    const fetchSearchResults = async (keyword) => {
      const q = String(keyword || "").trim();
      if (!q) return [];

      try {
        const response = await fetch(
          `${PRODUCT_SEARCH_ENDPOINT}?q=${encodeURIComponent(q)}&limit=8&_=${Date.now()}`,
          {
            headers: { "X-Requested-With": "XMLHttpRequest" },
            cache: "no-store",
          },
        );
        const data = await response.json().catch(() => ({}));

        if (
          !response.ok ||
          data?.ok !== true ||
          !Array.isArray(data?.results)
        ) {
          return [];
        }

        return data.results;
      } catch (_) {
        return [];
      }
    };

    inputs.forEach((input) => {
      if (!(input instanceof HTMLInputElement)) return;
      if (input.dataset.ackSearchBound === "1") return;

      input.dataset.ackSearchBound = "1";

      const host =
        input.closest(".search-box") || input.parentElement || document.body;

      const suggest = document.createElement("div");
      suggest.className = "ack-search-suggest";
      host.appendChild(suggest);

      let debounceTimer = null;
      let latestResults = [];

      const hideSuggest = () => {
        suggest.style.display = "none";
      };

      const showSuggest = () => {
        suggest.style.display = "block";
      };

      const renderSuggest = (results) => {
        latestResults = Array.isArray(results) ? results : [];

        if (!latestResults.length) {
          suggest.innerHTML =
            '<div class="ack-search-suggest-empty">Không tìm thấy sản phẩm phù hợp.</div>';
          showSuggest();
          return;
        }

        suggest.innerHTML = latestResults
          .map((item) => {
            const link = String(item?.link || "#").trim() || "#";
            return `
              <a class="ack-search-suggest-item" href="${escapeHtml(link)}">
                <img class="ack-search-suggest-img" src="${escapeHtml(item?.img || "../TrangUser/ack.png")}" alt="${escapeHtml(item?.name || "")}">
                <div>
                  <div class="ack-search-suggest-name">${escapeHtml(item?.name || "Sản phẩm")}</div>
                  <div class="ack-search-suggest-price">${escapeHtml(item?.price || "")}</div>
                </div>
              </a>
            `;
          })
          .join("");
        showSuggest();
      };

      const runBackendSearch = async () => {
        const keyword = String(input.value || "").trim();
        runLocalFilter(keyword);

        if (!keyword) {
          hideSuggest();
          latestResults = [];
          return;
        }

        const results = await fetchSearchResults(keyword);
        renderSuggest(results);
      };

      input.addEventListener("input", () => {
        if (debounceTimer) {
          clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(runBackendSearch, 180);
      });

      input.addEventListener("keydown", async (event) => {
        if (event.key !== "Enter") return;
        event.preventDefault();

        const keyword = String(input.value || "").trim();
        if (!keyword) {
          hideSuggest();
          runLocalFilter("");
          return;
        }

        if (!latestResults.length) {
          latestResults = await fetchSearchResults(keyword);
        }

        const firstLink = String(latestResults?.[0]?.link || "").trim();
        if (firstLink) {
          window.location.href = firstLink;
          return;
        }

        toast("Không tìm thấy sản phẩm phù hợp.");
      });

      const icon = host.querySelector("i.fa-search");
      if (icon) {
        icon.style.cursor = "pointer";
        icon.addEventListener("click", async () => {
          const keyword = String(input.value || "").trim();
          if (!keyword) {
            hideSuggest();
            runLocalFilter("");
            return;
          }

          if (!latestResults.length) {
            latestResults = await fetchSearchResults(keyword);
          }

          const firstLink = String(latestResults?.[0]?.link || "").trim();
          if (firstLink) {
            window.location.href = firstLink;
          } else {
            toast("Không tìm thấy sản phẩm phù hợp.");
          }
        });
      }

      document.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        if (target.closest(".search-box") === host) return;
        hideSuggest();
      });
    });
  }

  function normalizeTextForMatch(value) {
    return String(value || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/\s+/g, " ")
      .trim();
  }

  function normalizePathForMatch(rawPath) {
    try {
      const url = new URL(String(rawPath || ""), window.location.href);
      return decodeURIComponent(url.pathname || "")
        .replace(/\\/g, "/")
        .replace(/\/+/g, "/")
        .toLowerCase();
    } catch (_) {
      return String(rawPath || "")
        .replace(/\\/g, "/")
        .replace(/\/+/g, "/")
        .toLowerCase();
    }
  }

  function extractBaseName(pathValue) {
    const normalized = normalizePathForMatch(pathValue);
    if (!normalized) return "";
    const parts = normalized.split("/");
    return parts[parts.length - 1] || "";
  }

  async function fetchProductLinksMap() {
    try {
      const res = await fetch(`product-links.php?_=${Date.now()}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
        cache: "no-store",
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data?.ok !== true || !Array.isArray(data?.products)) {
        return null;
      }

      const byId = new Map();
      const byName = new Map();
      const byPath = new Map();
      const byBase = new Map();

      data.products.forEach((item) => {
        const link = String(item?.link || "").trim();
        if (!link) return;

        const idKey = String(item?.id || "")
          .trim()
          .toUpperCase();
        if (idKey && !byId.has(idKey)) byId.set(idKey, link);

        const nameKey = normalizeTextForMatch(item?.name || "");
        if (nameKey && !byName.has(nameKey)) byName.set(nameKey, link);

        const pathKey = normalizePathForMatch(item?.img || "");
        if (pathKey && !byPath.has(pathKey)) byPath.set(pathKey, link);

        const baseKey = extractBaseName(item?.img || "");
        if (baseKey && !byBase.has(baseKey)) byBase.set(baseKey, link);
      });

      return { byId, byName, byPath, byBase };
    } catch (_) {
      return null;
    }
  }

  async function bindProductCardNavigation() {
    const cards = Array.from(document.querySelectorAll(".product-card"));
    if (!cards.length) return;

    const linkMaps = await fetchProductLinksMap();
    if (!linkMaps) return;

    const userData = await fetchCurrentUserSession();
    const isAdminUser =
      String(userData?.role || "")
        .trim()
        .toLowerCase() === "admin";

    if (isAdminUser) {
      injectAdminEditButtonStyles();
    }

    cards.forEach((card) => {
      if (card.dataset.navBound === "1") return;
      if (card.closest(".admin-home-toolbar")) return;

      const linkInside = card.querySelector(
        'a[href*="drink-detail.php"], a[href*="id="]',
      );
      const inlineLink = String(card.getAttribute("data-link") || "").trim();

      let resolvedLink = inlineLink;
      if (!resolvedLink && linkInside) {
        resolvedLink = String(linkInside.getAttribute("href") || "").trim();
      }

      if (!resolvedLink) {
        const imgEl = card.querySelector("img");
        const titleEl = card.querySelector(".product-title");
        const idFromCard = String(card.getAttribute("data-product-id") || "")
          .trim()
          .toUpperCase();
        const idFromChild = String(
          card
            .querySelector("[data-product-id]")
            ?.getAttribute("data-product-id") || "",
        )
          .trim()
          .toUpperCase();
        const idKey = idFromCard || idFromChild;

        const titleKey = normalizeTextForMatch(titleEl?.textContent || "");
        const imgSrc = imgEl?.getAttribute("src") || "";
        const imgPathKey = normalizePathForMatch(imgSrc);
        const imgBaseKey = extractBaseName(imgSrc);

        resolvedLink =
          (idKey && linkMaps.byId.get(idKey)) ||
          (titleKey && linkMaps.byName.get(titleKey)) ||
          (imgPathKey && linkMaps.byPath.get(imgPathKey)) ||
          (imgBaseKey && linkMaps.byBase.get(imgBaseKey)) ||
          "";
      }

      if (!resolvedLink) return;

      card.dataset.link = resolvedLink;
      card.dataset.navBound = "1";
      card.style.cursor = "pointer";
      card.setAttribute("role", "link");
      if (!card.hasAttribute("tabindex")) {
        card.setAttribute("tabindex", "0");
      }

      if (isAdminUser) {
        attachAdminEditButtonToCard(card, resolvedLink);
      }

      const navigate = () => {
        if (!card.dataset.link) return;
        window.location.href = card.dataset.link;
      };

      card.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        if (
          target.closest("button") ||
          target.closest("a") ||
          target.closest("input") ||
          target.closest("textarea") ||
          target.closest("select")
        ) {
          return;
        }

        navigate();
      });

      card.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          navigate();
        }
      });
    });
  }

  function injectAdminEditButtonStyles() {
    if (document.getElementById("ack-admin-edit-btn-style")) return;

    const style = document.createElement("style");
    style.id = "ack-admin-edit-btn-style";
    style.textContent = `
      .admin-edit-btn {
        position: absolute;
        top: 10px;
        left: 10px;
        width: 28px;
        height: 28px;
        border: none;
        border-radius: 50%;
        background: #5865f8;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px rgba(88, 101, 248, 0.35);
        z-index: 15;
        cursor: pointer;
      }

      .admin-edit-btn:hover {
        transform: translateY(-1px);
        background: #3f4ae6;
      }
    `;

    document.head.appendChild(style);
  }

  function extractProductIdFromLink(rawLink) {
    const link = String(rawLink || "").trim();
    if (!link) return "";

    try {
      const url = new URL(link, window.location.href);
      return String(url.searchParams.get("id") || "").trim();
    } catch (_) {
      const match = link.match(/[?&]id=([^&#]+)/i);
      return match ? decodeURIComponent(match[1]).trim() : "";
    }
  }

  function attachAdminEditButtonToCard(card, resolvedLink) {
    if (!card || card.querySelector(".admin-edit-btn")) return;

    const explicitId = String(
      card.getAttribute("data-product-id") || "",
    ).trim();
    const productId = explicitId || extractProductIdFromLink(resolvedLink);
    if (!productId) return;

    const editBtn = document.createElement("button");
    editBtn.type = "button";
    editBtn.className = "admin-edit-btn";
    editBtn.title = "Sửa sản phẩm này";
    editBtn.setAttribute("aria-label", "Sửa sản phẩm này");
    editBtn.innerHTML = '<i class="fas fa-pen"></i>';

    editBtn.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      const returnTo = encodeURIComponent(
        window.location.pathname + window.location.search,
      );
      window.location.href = `../TrangAdmin/admin-sanpham.php?edit=${encodeURIComponent(productId)}&return_to=${returnTo}`;
    });

    const currentPosition = window.getComputedStyle(card).position;
    if (!currentPosition || currentPosition === "static") {
      card.style.position = "relative";
    }

    card.appendChild(editBtn);
  }

  function normalizeFooterPaymentLogos() {
    const replacements = [
      {
        match: /upload\.wikimedia\.org\/wikipedia\/commons\/0\/04\/Visa\.svg/i,
        src: "https://cdn.simpleicons.org/visa/1A1F71",
        alt: "Visa",
      },
      {
        match:
          /upload\.wikimedia\.org\/wikipedia\/commons\/2\/2a\/Mastercard-logo\.svg/i,
        src: "https://cdn.simpleicons.org/mastercard/EB001B",
        alt: "Mastercard",
      },
      {
        match:
          /upload\.wikimedia\.org\/wikipedia\/commons\/b\/b5\/PayPal\.svg/i,
        src: "https://cdn.simpleicons.org/paypal/00457C",
        alt: "Paypal",
      },
    ];

    const paymentImages = document.querySelectorAll(
      "footer img, .site-footer img",
    );

    paymentImages.forEach((img) => {
      const currentSrc = String(img.getAttribute("src") || "");
      const currentAlt = String(img.getAttribute("alt") || "");

      const replacement = replacements.find(
        (item) =>
          item.match.test(currentSrc) ||
          currentAlt.toLowerCase().includes(item.alt.toLowerCase()),
      );

      if (!replacement) return;

      img.setAttribute("src", replacement.src);
      img.setAttribute("alt", replacement.alt);

      img.addEventListener(
        "error",
        () => {
          img.setAttribute(
            "src",
            `https://img.shields.io/badge/${encodeURIComponent(replacement.alt)}-1f2937?style=flat-square&logoColor=white`,
          );
        },
        { once: true },
      );
    });
  }

  function applyHeroVisibilityRules() {
    const fileName = String(window.location.pathname || "")
      .split("/")
      .pop()
      .toLowerCase();

    const isHomepage =
      fileName === "" ||
      fileName === "trangchu.php" ||
      fileName === "index.php";

    if (!document.getElementById("ack-hero-visibility-style")) {
      const style = document.createElement("style");
      style.id = "ack-hero-visibility-style";
      style.textContent = `
        body.ack-category-page #tetCarousel,
        body.ack-category-page .main-banner {
          display: none !important;
        }
      `;
      document.head.appendChild(style);
    }

    if (!isHomepage && document.body) {
      document.body.classList.add("ack-category-page");
    }
  }

  function keepHeroOnlyOnHomepage() {
    applyHeroVisibilityRules();
  }

  function injectGlobalHeaderUxStyles() {
    if (document.getElementById("ack-global-header-ux-style")) return;

    const style = document.createElement("style");
    style.id = "ack-global-header-ux-style";
    style.textContent = `
      .ack-location-select {
        border-radius: 20px !important;
        background: #eee !important;
        border: none !important;
        padding: 5px 32px 5px 14px !important;
        font-size: 0.9rem !important;
        min-width: 170px;
      }

      .ack-location-wrap {
        position: relative;
        min-width: 190px;
      }

      .ack-location-btn {
        width: 100%;
        border: none;
        border-radius: 999px;
        background: #f1f5f9;
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 500;
        padding: 8px 12px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.06);
      }

      .ack-location-btn:hover {
        background: #e8eef8;
      }

      .ack-location-wrap.open .ack-location-btn {
        background: #e6efff;
        box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.35);
      }

      .ack-location-label {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 145px;
      }

      .ack-location-caret {
        margin-left: auto;
        transition: transform 0.2s ease;
      }

      .ack-location-wrap.open .ack-location-caret {
        transform: rotate(180deg);
      }

      .ack-location-menu {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        width: 100%;
        min-width: 260px;
        max-height: 320px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #dbe3ef;
        border-radius: 14px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.18);
        padding: 6px;
        z-index: 1200;
        display: none;
      }

      .ack-location-wrap.open .ack-location-menu {
        display: block;
      }

      .ack-location-item {
        width: 100%;
        border: none;
        background: transparent;
        border-radius: 10px;
        padding: 8px 10px;
        text-align: left;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        color: #0f172a;
        font-size: 0.92rem;
      }

      .ack-location-item:hover {
        background: #f3f7ff;
      }

      .ack-location-item.active {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        color: #fff;
      }

      .search-box {
        position: relative;
      }

      .ack-search-suggest {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.15);
        z-index: 1050;
        max-height: 380px;
        overflow-y: auto;
        display: none;
      }

      .ack-search-suggest-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        text-decoration: none;
        color: #111827;
        border-bottom: 1px solid #f1f5f9;
      }

      .ack-search-suggest-item:last-child {
        border-bottom: none;
      }

      .ack-search-suggest-item:hover {
        background: #f8fbff;
        color: #111827;
      }

      .ack-search-suggest-img {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        object-fit: cover;
        flex-shrink: 0;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
      }

      .ack-search-suggest-name {
        font-size: 0.88rem;
        font-weight: 600;
        line-height: 1.25;
      }

      .ack-search-suggest-price {
        font-size: 0.8rem;
        color: #dc2626;
        margin-top: 2px;
      }

      .ack-search-suggest-empty {
        padding: 10px 12px;
        color: #64748b;
        font-size: 0.84rem;
      }
    `;
    document.head.appendChild(style);
  }

  async function fetchLocations() {
    if (locationListPromise) return locationListPromise;

    locationListPromise = fetch(`${LOCATION_API_ENDPOINT}?_=${Date.now()}`, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
      cache: "no-store",
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({}))
          .then((data) => {
            if (
              response.ok &&
              data?.ok === true &&
              Array.isArray(data?.locations)
            ) {
              return data.locations
                .map((item) => String(item || "").trim())
                .filter(Boolean);
            }

            return [];
          }),
      )
      .catch(() => []);

    return locationListPromise;
  }

  function updateDeliveryNoticeLocation(locationName) {
    const location = String(locationName || "").trim();
    if (!location) return;

    document.querySelectorAll(".delivery-notice").forEach((node) => {
      node.innerHTML = `<i class=\"fas fa-truck-fast me-1\"></i> Miễn phí giao hàng tại ${escapeHtml(location)}`;
    });
  }

  async function setupLocationSelector() {
    injectGlobalHeaderUxStyles();

    const locationNodes = Array.from(
      document.querySelectorAll(".location-select"),
    );
    if (!locationNodes.length) return;

    const locations = await fetchLocations();
    if (!locations.length) return;

    const fallback = locations.includes("Đồng Tháp")
      ? "Đồng Tháp"
      : locations[0];
    const stored = String(
      localStorage.getItem(LOCATION_STORAGE_KEY) || "",
    ).trim();
    const selected = locations.includes(stored) ? stored : fallback;

    locationNodes.forEach((node) => {
      if (!(node instanceof HTMLElement)) return;
      if (node.dataset.ackLocationBound === "1") return;

      const wrap = document.createElement("div");
      wrap.className = "ack-location-wrap";
      wrap.dataset.ackLocationBound = "1";

      wrap.innerHTML = `
        <button type="button" class="ack-location-btn" aria-haspopup="listbox" aria-expanded="false">
          <i class="fas fa-map-marker-alt text-danger"></i>
          <span class="ack-location-label">${escapeHtml(selected)}</span>
          <i class="fas fa-caret-down ack-location-caret"></i>
        </button>
        <div class="ack-location-menu" role="listbox"></div>
      `;

      const button = wrap.querySelector(".ack-location-btn");
      const label = wrap.querySelector(".ack-location-label");
      const menu = wrap.querySelector(".ack-location-menu");
      if (!button || !label || !menu) return;

      menu.innerHTML = locations
        .map((name) => {
          const active = name === selected ? " active" : "";
          return `<button type=\"button\" class=\"ack-location-item${active}\" data-location=\"${escapeHtml(name)}\"><i class=\"fas fa-location-dot text-danger\"></i><span>${escapeHtml(name)}</span></button>`;
        })
        .join("");

      const closeAllMenus = () => {
        document.querySelectorAll(".ack-location-wrap.open").forEach((item) => {
          if (!(item instanceof HTMLElement)) return;
          item.classList.remove("open");
          const btn = item.querySelector(".ack-location-btn");
          if (btn instanceof HTMLElement) {
            btn.setAttribute("aria-expanded", "false");
          }
        });
      };

      button.addEventListener("click", (event) => {
        event.stopPropagation();
        const willOpen = !wrap.classList.contains("open");
        closeAllMenus();
        if (willOpen) {
          wrap.classList.add("open");
          button.setAttribute("aria-expanded", "true");
        }
      });

      menu.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const option = target.closest(".ack-location-item[data-location]");
        if (!(option instanceof HTMLElement)) return;

        const value = String(option.dataset.location || "").trim();
        if (!value) return;

        localStorage.setItem(LOCATION_STORAGE_KEY, value);
        label.textContent = value;
        updateDeliveryNoticeLocation(value);

        menu
          .querySelectorAll(".ack-location-item")
          .forEach((item) => item.classList.remove("active"));
        option.classList.add("active");

        wrap.classList.remove("open");
        button.setAttribute("aria-expanded", "false");
      });

      if (!document.body.dataset.ackLocationOutsideBound) {
        document.addEventListener("click", (event) => {
          const target = event.target;
          if (!(target instanceof Element)) return;
          if (target.closest(".ack-location-wrap")) return;

          document
            .querySelectorAll(".ack-location-wrap.open")
            .forEach((item) => {
              if (!(item instanceof HTMLElement)) return;
              item.classList.remove("open");
              const btn = item.querySelector(".ack-location-btn");
              if (btn instanceof HTMLElement) {
                btn.setAttribute("aria-expanded", "false");
              }
            });
        });
        document.body.dataset.ackLocationOutsideBound = "1";
      }

      node.replaceWith(wrap);
    });

    updateDeliveryNoticeLocation(selected);
  }

  function injectGlobalChatbotStyles() {
    if (document.getElementById(CHATBOT_STYLE_ID)) return;

    const style = document.createElement("style");
    style.id = CHATBOT_STYLE_ID;
    style.textContent = `
      #chatbot-button {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border: none;
        color: #fff;
        font-size: 28px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9998;
        transition: all 0.25s ease;
      }

      #chatbot-button:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 18px rgba(0, 123, 255, 0.6);
      }

      #chatbot-button.active {
        bottom: 390px;
      }

      #chatbot-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 380px;
        height: 500px;
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 5px 40px rgba(0, 0, 0, 0.16);
        display: none;
        flex-direction: column;
        z-index: 9999;
        animation: ackChatbotSlideUp 0.25s ease;
        overflow: hidden;
      }

      #chatbot-widget.active {
        display: flex;
      }

      @keyframes ackChatbotSlideUp {
        from {
          opacity: 0;
          transform: translateY(24px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .chatbot-header {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: #fff;
        padding: 14px 15px;
        border-radius: 15px 15px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      .chatbot-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
      }

      .chatbot-close {
        background: none;
        border: none;
        color: #fff;
        font-size: 20px;
        cursor: pointer;
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
      }

      .chatbot-messages {
        flex: 1;
        overflow-y: auto;
        padding: 12px;
        background: #f9f9f9;
      }

      .chatbot-message {
        margin-bottom: 10px;
        display: flex;
      }

      .chatbot-message.user {
        justify-content: flex-end;
      }

      .chatbot-message.ai {
        justify-content: flex-start;
      }

      .chatbot-bubble {
        max-width: 78%;
        padding: 10px 14px;
        border-radius: 10px;
        word-wrap: break-word;
        line-height: 1.45;
        font-size: 14px;
        white-space: pre-line;
      }

      .chatbot-message.user .chatbot-bubble {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: #fff;
        border-bottom-right-radius: 2px;
      }

      .chatbot-message.ai .chatbot-bubble {
        background: #e9ecef;
        color: #333;
        border-bottom-left-radius: 2px;
      }

      .chatbot-input-area {
        padding: 12px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        gap: 8px;
        background: #fff;
      }

      .chatbot-input-area input {
        flex: 1;
        border: 1px solid #d1d5db;
        border-radius: 999px;
        padding: 10px 14px;
        font-size: 14px;
        outline: none;
      }

      .chatbot-input-area input:focus {
        border-color: #0b74e5;
      }

      .chatbot-send-btn {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border: none;
        color: #fff;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
      }

      .chatbot-products {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
        margin: 10px 0 2px;
      }

      .chatbot-product-item {
        background: white;
        border: 2px solid #0d6efd;
        border-radius: 12px;
        padding: 0;
        overflow: hidden;
        text-align: left;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none !important;
        color: #333 !important;
        display: block;
      }

      .chatbot-product-item:hover {
        border-color: #007bff;
        background: #ffffff;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 123, 255, 0.24);
      }

      .chatbot-product-img {
        width: 100%;
        aspect-ratio: 1 / 1;
        height: auto;
        object-fit: cover;
        display: block;
        background: #fff;
      }

      .chatbot-product-body {
        padding: 8px 10px 10px;
      }

      .chatbot-product-name {
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 4px;
        line-height: 1.3;
      }

      .chatbot-product-price {
        font-size: 14px;
        color: #dc3545;
        font-weight: 700;
      }

      .chatbot-view-more {
        margin-top: 8px;
        text-align: center;
        padding-top: 8px;
        border-top: 1px solid #f0f0f0;
      }

      .chatbot-view-more a {
        text-decoration: none !important;
      }

      .chatbot-view-more-btn {
        background: none;
        border: none;
        color: #007bff;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        padding: 3px 8px;
      }

      @media (max-width: 480px) {
        #chatbot-widget {
          width: calc(100% - 10px);
          height: 62vh;
          bottom: 10px;
          right: 5px;
        }

        #chatbot-button {
          width: 52px;
          height: 52px;
          font-size: 24px;
        }
      }
    `;

    document.head.appendChild(style);
  }

  function ensureGlobalChatbotWidget() {
    const hasButton = !!document.getElementById("chatbot-button");
    const hasWidget = !!document.getElementById("chatbot-widget");

    // Trang nào đã tự có chatbot riêng thì không đụng vào
    if (hasButton || hasWidget) {
      return false;
    }

    injectGlobalChatbotStyles();

    const button = document.createElement("button");
    button.id = "chatbot-button";
    button.title = "Hỏi AI tư vấn";
    button.innerHTML = '<i class="fas fa-robot" aria-hidden="true"></i>';

    const widget = document.createElement("div");
    widget.id = "chatbot-widget";
    widget.innerHTML = `
      <div class="chatbot-header">
        <h3>🤖 AI Tư Vấn</h3>
        <button class="chatbot-close" type="button" aria-label="Đóng"><i class="fas fa-times"></i></button>
      </div>
      <div class="chatbot-messages">
        <div class="chatbot-message ai">
          <div class="chatbot-bubble">👋 Xin chào! Tôi có thể giúp bạn chọn sản phẩm nào không? Hỏi tôi về nước ngọt, trái cây, sữa, v.v...</div>
        </div>
      </div>
      <div class="chatbot-input-area">
        <input type="text" placeholder="Nhập câu hỏi..." autocomplete="off">
        <button class="chatbot-send-btn" type="button" title="Gửi"><i class="fas fa-paper-plane"></i></button>
      </div>
    `;

    document.body.appendChild(button);
    document.body.appendChild(widget);
    return true;
  }

  function bindGlobalChatbot() {
    const createdByGlobal = ensureGlobalChatbotWidget();
    if (!createdByGlobal) {
      return;
    }

    const chatbotButton = document.getElementById("chatbot-button");
    const chatbotWidget = document.getElementById("chatbot-widget");
    const chatbotClose = document.querySelector(
      "#chatbot-widget .chatbot-close",
    );
    const chatbotMessages = document.querySelector(
      "#chatbot-widget .chatbot-messages",
    );
    const chatbotInput = document.querySelector(
      "#chatbot-widget .chatbot-input-area input",
    );
    const chatbotSendBtn = document.querySelector(
      "#chatbot-widget .chatbot-send-btn",
    );

    if (
      !chatbotButton ||
      !chatbotWidget ||
      !chatbotClose ||
      !chatbotMessages ||
      !chatbotInput ||
      !chatbotSendBtn
    ) {
      return;
    }

    if (chatbotWidget.dataset.ackBound === "1") {
      return;
    }
    chatbotWidget.dataset.ackBound = "1";

    chatbotButton.addEventListener("click", () => {
      chatbotWidget.classList.toggle("active");
      chatbotButton.classList.toggle("active");
      if (chatbotWidget.classList.contains("active")) {
        chatbotInput.focus();
      }
    });

    chatbotClose.addEventListener("click", () => {
      chatbotWidget.classList.remove("active");
      chatbotButton.classList.remove("active");
    });

    function appendAiMessage(text) {
      const aiDiv = document.createElement("div");
      aiDiv.className = "chatbot-message ai";
      aiDiv.innerHTML = `<div class="chatbot-bubble">${escapeHtml(text)}</div>`;
      chatbotMessages.appendChild(aiDiv);
      chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function buildProductsHtml(data) {
      let html = '<div class="chatbot-products">';
      (Array.isArray(data?.products) ? data.products : []).forEach(
        (product) => {
          const productUrl = product?.link || "#";
          html += `
          <a href="${escapeHtml(productUrl)}" class="chatbot-product-item" target="_blank" title="${escapeHtml(product?.name || "")}">
            <img src="${escapeHtml(product?.img || "")}" alt="${escapeHtml(product?.name || "")}" class="chatbot-product-img">
            <div class="chatbot-product-body">
              <div class="chatbot-product-name">${escapeHtml(product?.name || "")}</div>
              <div class="chatbot-product-price">${escapeHtml(product?.price || "")}</div>
            </div>
          </a>
        `;
        },
      );
      html += "</div>";

      if (data?.hasMore) {
        const categoryNameMap = {
          nuocngot: "Trangnuocngot.php",
          douong: "Trangdouong.php",
          anvat: "Tranganvat.php",
          thucannhanh: "Trangthucannhanh.php",
          traicay: "Trangtraicay.php",
          raucu: "Trangraucu.php",
          sua: "Trangsua.php",
          banhngot: "Trangbanhngot.php",
          giadung: "Tranggiadung.php",
          mypham: "Trangmypham.php",
          kem: "Trangkem.php",
          mianlien: "Trangmianlien.php",
          tuoisong: "Trangtuoisong.php",
          dohop: "Trangdohop.php",
          giavi: "Tranggiavi.php",
          bia: "Trangbia.php",
        };
        const categoryLink = categoryNameMap[data.categorySlug] || "#";
        const remaining = Math.max(
          0,
          Number(data?.totalCount || 0) - Number(data?.displayedCount || 0),
        );

        html += `
          <div class="chatbot-view-more">
            <a href="${escapeHtml(categoryLink)}" target="_blank">
              <button class="chatbot-view-more-btn" type="button">Xem thêm ${remaining} sản phẩm →</button>
            </a>
          </div>
        `;
      }

      return html;
    }

    async function sendMessage() {
      const message = String(chatbotInput.value || "").trim();
      if (!message) return;

      const userDiv = document.createElement("div");
      userDiv.className = "chatbot-message user";
      userDiv.innerHTML = `<div class="chatbot-bubble">${escapeHtml(message)}</div>`;
      chatbotMessages.appendChild(userDiv);
      chatbotInput.value = "";

      const typingDiv = document.createElement("div");
      typingDiv.className = "chatbot-message ai";
      typingDiv.id = "ack-chatbot-typing";
      typingDiv.innerHTML =
        '<div class="chatbot-bubble">Đang suy nghĩ...</div>';
      chatbotMessages.appendChild(typingDiv);
      chatbotMessages.scrollTop = chatbotMessages.scrollHeight;

      try {
        const body = new URLSearchParams();
        body.set("message", message);

        const response = await fetch(AI_ADVISOR_ENDPOINT, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest",
          },
          body: body.toString(),
          cache: "no-store",
        });

        const data = await response.json().catch(() => ({}));
        const typingEl = document.getElementById("ack-chatbot-typing");
        if (typingEl) typingEl.remove();

        appendAiMessage(data?.reply || "Xin lỗi, tôi chưa hiểu câu hỏi này.");

        if (
          data?.type === "products" &&
          Array.isArray(data?.products) &&
          data.products.length > 0
        ) {
          const productsDiv = document.createElement("div");
          productsDiv.className = "chatbot-message ai";
          productsDiv.innerHTML = buildProductsHtml(data);
          chatbotMessages.appendChild(productsDiv);
          chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        }
      } catch (_) {
        const typingEl = document.getElementById("ack-chatbot-typing");
        if (typingEl) typingEl.remove();
        appendAiMessage("Xin lỗi, tôi gặp lỗi. Vui lòng thử lại!");
      }
    }

    chatbotSendBtn.addEventListener("click", sendMessage);
    chatbotInput.addEventListener("keypress", (event) => {
      if (event.key === "Enter") {
        sendMessage();
      }
    });
  }

  function init() {
    keepHeroOnlyOnHomepage();
    injectGlobalFooterLayoutStyles();
    normalizeFooterPaymentLogos();
    setupLocationSelector();
    bindGlobalChatbot();
    syncProductStockStates();
    bindAddToCartButtons();
    bindProductCardNavigation();
    bindSearchFilter();
    updateCartBadge();
    setupGlobalNotificationMenu();
    bindPromotionForms();
    setupGlobalUserMenu();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
