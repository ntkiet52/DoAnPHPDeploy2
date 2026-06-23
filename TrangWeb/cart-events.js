(function () {
  if (window.__ackCartEventsInitialized) {
    return;
  }
  window.__ackCartEventsInitialized = true;

  const CART_ENDPOINT = "cart-handler.php";
  let appliedVoucher = null;
  let isCheckoutSubmitting = false;

  function ensureDialogStyle() {
    if (document.getElementById("ack-cart-dialog-style")) return;
    const style = document.createElement("style");
    style.id = "ack-cart-dialog-style";
    style.textContent = `
      .ack-dialog-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
      }

      .ack-dialog-card {
        width: min(460px, 100%);
        background: #ffffff;
        border-radius: 14px;
        box-shadow: 0 24px 60px rgba(2, 6, 23, 0.25);
        overflow: hidden;
      }

      .ack-dialog-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        padding: 16px 18px 6px;
      }

      .ack-dialog-message {
        font-size: 0.95rem;
        color: #334155;
        padding: 0 18px 14px;
      }

      .ack-dialog-actions {
        border-top: 1px solid #e2e8f0;
        padding: 12px 18px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
      }

      .ack-dialog-btn-loading {
        display: inline-flex;
        align-items: center;
        gap: 8px;
      }

      .ack-dialog-btn-spinner {
        width: 14px;
        height: 14px;
        border: 2px solid rgba(255, 255, 255, 0.45);
        border-top-color: #fff;
        border-radius: 50%;
        animation: ack-dialog-spin 0.65s linear infinite;
      }

      @keyframes ack-dialog-spin {
        to {
          transform: rotate(360deg);
        }
      }

    `;
    document.head.appendChild(style);
  }

  function showDialog(options = {}) {
    ensureDialogStyle();

    const {
      title = "Thông báo",
      message = "",
      confirmText = "OK",
      cancelText = "Hủy",
      showCancel = false,
      confirmClass = "btn btn-primary",
      cancelClass = "btn btn-outline-secondary",
      confirmLoadingMs = 0,
      confirmLoadingText = "Đang xử lý...",
    } = options;

    return new Promise((resolve) => {
      let isResolving = false;

      const backdrop = document.createElement("div");
      backdrop.className = "ack-dialog-backdrop";
      backdrop.innerHTML = `
        <div class="ack-dialog-card" role="dialog" aria-modal="true">
          <div class="ack-dialog-title"></div>
          <div class="ack-dialog-message"></div>
          <div class="ack-dialog-actions">
            ${showCancel ? `<button type="button" class="${cancelClass}" data-role="cancel">${cancelText}</button>` : ""}
            <button type="button" class="${confirmClass}" data-role="ok">${confirmText}</button>
          </div>
        </div>
      `;

      const titleEl = backdrop.querySelector(".ack-dialog-title");
      const messageEl = backdrop.querySelector(".ack-dialog-message");
      if (titleEl) titleEl.textContent = String(title || "Thông báo");
      if (messageEl) messageEl.textContent = String(message || "");

      const close = (result) => {
        if (isResolving) return;
        isResolving = true;
        backdrop.remove();
        resolve(result);
      };

      const setButtonsDisabled = (disabled) => {
        backdrop
          .querySelectorAll(".ack-dialog-actions button")
          .forEach((btn) => {
            btn.disabled = !!disabled;
          });
      };

      backdrop.addEventListener("click", (event) => {
        if (isResolving) return;
        if (event.target === backdrop && showCancel) {
          close(false);
        }
      });

      backdrop
        .querySelector('[data-role="ok"]')
        ?.addEventListener("click", (event) => {
          if (isResolving) return;

          const okButton = event.currentTarget;
          const waitMs = Math.max(0, Number(confirmLoadingMs || 0));
          if (waitMs > 0 && okButton instanceof HTMLElement) {
            setButtonsDisabled(true);
            okButton.innerHTML = `<span class="ack-dialog-btn-loading"><span class="ack-dialog-btn-spinner" aria-hidden="true"></span>${String(confirmLoadingText || "Đang xử lý...")}</span>`;
            window.setTimeout(() => {
              close(true);
            }, waitMs);
            return;
          }

          close(true);
        });

      backdrop
        .querySelector('[data-role="cancel"]')
        ?.addEventListener("click", () => {
          if (isResolving) return;
          close(false);
        });

      document.body.appendChild(backdrop);
    });
  }

  function showAlertCenter(message, title = "Thông báo") {
    return showDialog({
      title,
      message,
      confirmText: "Đóng",
      showCancel: false,
      confirmClass: "btn btn-primary",
    });
  }

  function showConfirmCenter(message, title = "Xác nhận") {
    return showDialog({
      title,
      message,
      confirmText: "Đồng ý",
      cancelText: "Hủy",
      showCancel: true,
      confirmClass: "btn btn-primary",
      cancelClass: "btn btn-outline-secondary",
    });
  }

  function formatMoney(amount) {
    return `${Number(amount || 0).toLocaleString("vi-VN")}₫`;
  }

  function parseMoneyText(value) {
    const cleaned = String(value || "").replace(/[^\d]/g, "");
    return Math.max(0, Number(cleaned || 0));
  }

  function setHeaderCartCount(qty) {
    const safeQty = Math.max(0, Number.parseInt(String(qty || 0), 10) || 0);
    document.querySelectorAll("[data-cart-count]").forEach((el) => {
      el.textContent = String(safeQty);
    });
  }

  async function refreshHeaderCartCount() {
    try {
      const data = await postCartAction("get_cart_count");
      setHeaderCartCount(data?.cart_count || 0);
    } catch (_) {
      // keep silent: cart screen still works even if badge refresh fails
    }
  }

  function getSelectedPaymentMethod() {
    const selected = document.querySelector(
      'input[name="payment_method"]:checked',
    );
    const method = String(selected?.value || "cod").toLowerCase();
    return method === "qr" ? "qr" : "cod";
  }

  function buildQrUrl(amount, transferNote) {
    const qrData = [
      "BANK:MB",
      "ACC:123456789",
      "NAME:ACK MART",
      `AMOUNT:${Math.max(0, Number(amount || 0))}`,
      `NOTE:${String(transferNote || "ACKMART THANH TOAN")}`,
    ].join("|");

    return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(qrData)}`;
  }

  function updateQrPaymentPreview() {
    const panel = document.getElementById("qrPaymentPanel");
    if (!panel) return;

    const qrImage = document.getElementById("qrPaymentImage");
    const qrAmountText = document.getElementById("qrAmountText");
    const qrTransferNote = document.getElementById("qrTransferNote");
    const qrConfirm = document.getElementById("qrPaidConfirm");

    const method = getSelectedPaymentMethod();
    const finalPriceText =
      document.getElementById("final-price")?.textContent || "0₫";
    const amount = parseMoneyText(finalPriceText);
    const transferNote = `ACKMART-${new Date().toISOString().slice(0, 10)}`;

    if (method === "qr") {
      panel.classList.add("active");
      if (qrAmountText) qrAmountText.textContent = formatMoney(amount);
      if (qrTransferNote) qrTransferNote.textContent = transferNote;
      if (qrImage) qrImage.src = buildQrUrl(amount, transferNote);
      return;
    }

    panel.classList.remove("active");
    if (qrConfirm) qrConfirm.checked = false;
  }

  function setVoucherFeedback(message, type = "info") {
    const feedback = document.getElementById("voucherFeedback");
    if (!feedback) return;
    feedback.className = `voucher-feedback ${type}`;
    feedback.textContent = message;
  }

  async function postCartAction(action, payload = {}) {
    const form = new URLSearchParams();
    form.set("action", action);

    Object.entries(payload).forEach(([key, value]) => {
      if (Array.isArray(value)) {
        value.forEach((v) => form.append(`${key}[]`, String(v)));
      } else if (value !== undefined && value !== null) {
        form.set(key, String(value));
      }
    });

    const res = await fetch(CART_ENDPOINT, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: form.toString(),
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || data?.success !== true) {
      throw new Error(data?.message || "Có lỗi khi xử lý giỏ hàng.");
    }
    return data;
  }

  function clearVoucher(shouldNotify = true) {
    appliedVoucher = null;
    const input = document.getElementById("voucherCodeInput");
    const select = document.getElementById("voucherSelect");
    const clearBtn = document.getElementById("clearVoucherBtn");

    if (input) input.value = "";
    if (select) select.value = "";
    if (clearBtn) clearBtn.style.display = "none";

    if (shouldNotify) {
      setVoucherFeedback("Chưa áp dụng voucher.", "info");
    }
    calculateTotal();
  }

  function getVoucherDiscount(subtotal) {
    if (!appliedVoucher) {
      return { amount: 0, isEligible: false, reason: "" };
    }

    const voucherName = String(appliedVoucher.name || "").trim();
    const voucherLabel = voucherName
      ? `${appliedVoucher.code} - ${voucherName}`
      : `${appliedVoucher.code}`;

    const minOrder = Math.max(0, Number(appliedVoucher.min_order_value || 0));
    if (subtotal < minOrder) {
      return {
        amount: 0,
        isEligible: false,
        reason: `Mã ${voucherLabel} cần đơn tối thiểu ${formatMoney(minOrder)}.`,
      };
    }

    let discountAmount = 0;
    if (String(appliedVoucher.discount_type).toLowerCase() === "percent") {
      const percent = Math.max(
        0,
        Math.min(100, Number(appliedVoucher.discount_value || 0)),
      );
      discountAmount = Math.round((subtotal * percent) / 100);
    } else {
      discountAmount = Math.max(0, Number(appliedVoucher.discount_value || 0));
    }

    discountAmount = Math.min(discountAmount, subtotal);
    return {
      amount: discountAmount,
      isEligible: true,
      reason: `Đã áp dụng mã ${voucherLabel} (${appliedVoucher.discount_text}).`,
    };
  }

  function getRowByDetailId(detailId) {
    const id = String(detailId || "").trim();
    if (!id) return null;
    return (
      document.getElementById(`item-${id}`) ||
      document.getElementById(`item-d${id}`)
    );
  }

  function getRowByKey(rowKey, detailId) {
    const key = String(rowKey || "").trim();
    if (key) {
      const byKey = document.getElementById(`item-${key}`);
      if (byKey) return byKey;
    }
    return getRowByDetailId(detailId);
  }

  function getQtyInput(detailId, rowKey) {
    const key = String(rowKey || "").trim();
    if (key) {
      const byKey = document.getElementById(`qty-${key}`);
      if (byKey) return byKey;
    }
    const id = String(detailId || "").trim();
    if (!id) return null;
    return (
      document.getElementById(`qty-${id}`) ||
      document.getElementById(`qty-d${id}`)
    );
  }

  function getTotalEl(detailId, rowKey) {
    const key = String(rowKey || "").trim();
    if (key) {
      const byKey = document.getElementById(`total-${key}`);
      if (byKey) return byKey;
    }
    const id = String(detailId || "").trim();
    if (!id) return null;
    return (
      document.getElementById(`total-${id}`) ||
      document.getElementById(`total-d${id}`)
    );
  }

  function updateRowTotal(detailId, rowKey) {
    const key = String(rowKey || "").trim();
    const checkboxByKey = key
      ? document.querySelector(`.item-checkbox[data-row-key="${key}"]`)
      : null;
    const checkbox =
      checkboxByKey ||
      document.querySelector(`.item-checkbox[data-id="${detailId}"]`);
    const qtyInput = getQtyInput(detailId, key);
    const totalEl = getTotalEl(detailId, key);
    if (!checkbox || !qtyInput || !totalEl) return;

    const price = Math.max(0, parseFloat(checkbox.dataset.price || "0") || 0);
    const qty = Math.max(1, parseInt(qtyInput.value || "1", 10) || 1);
    totalEl.textContent = formatMoney(price * qty);
  }

  function ensureEmptyCartView() {
    const list = document.getElementById("cart-list");
    if (!list) return;

    const cards = list.querySelectorAll(".cart-item-card");
    if (cards.length === 0) {
      list.innerHTML =
        '<div class="bg-white rounded p-4 border">Giỏ hàng đang trống.</div>';
      clearVoucher(false);
      calculateTotal();
    }
  }

  window.updateQty = async function updateQty(
    detailId,
    change,
    productId,
    rowKey,
  ) {
    const id = Number(detailId || 0);
    const maSanPham = String(productId || "").trim();
    const key = String(rowKey || "").trim();
    if (id <= 0 && !maSanPham) return;

    const qtyInput = getQtyInput(id, key);
    if (!qtyInput) return;

    const current = Math.max(1, parseInt(qtyInput.value || "1", 10) || 1);
    const nextQty = current + Number(change || 0);
    if (nextQty < 1) return;

    try {
      await postCartAction("update_quantity", {
        id_chi_tiet: id,
        ma_san_pham: maSanPham,
        so_luong: nextQty,
      });
      qtyInput.value = String(nextQty);
      updateRowTotal(id, key);
      calculateTotal();
      refreshHeaderCartCount();
    } catch (error) {
      await showAlertCenter(error.message || "Không thể cập nhật số lượng.");
    }
  };

  window.deleteFromCart = async function deleteFromCart(chiTietId, element) {
    const allowDelete = await showConfirmCenter(
      "Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng?",
      "Xác nhận xóa",
    );
    if (!allowDelete) {
      return;
    }

    try {
      const id = Number(chiTietId || 0);
      const cardFromElement = element?.closest(".cart-item-card") || null;

      if (id > 0) {
        await postCartAction("remove_item", { id_chi_tiet: id });
      } else {
        const productId = String(
          cardFromElement?.dataset?.productId ||
            element?.dataset?.productId ||
            "",
        ).trim();

        if (!productId) {
          throw new Error("Không xác định được sản phẩm cần xóa.");
        }

        await postCartAction("remove_item_by_product", {
          ma_san_pham: productId,
        });
      }

      const card =
        cardFromElement || getRowByKey(element?.dataset?.rowKey || "", id);
      if (card) card.remove();
      ensureEmptyCartView();
      calculateTotal();
      refreshHeaderCartCount();
      await showAlertCenter("Đã xóa sản phẩm khỏi giỏ hàng.", "Thành công");
    } catch (error) {
      await showAlertCenter(error.message || "Không thể xóa sản phẩm.");
    }
  };

  async function removeSelectedItems() {
    const selectedIds = Array.from(
      document.querySelectorAll(".item-checkbox:checked"),
    )
      .map((cb) => Number(cb?.dataset?.id || 0))
      .filter((v) => v > 0);

    if (selectedIds.length === 0) {
      await showAlertCenter("Vui lòng chọn sản phẩm cần xóa.");
      return;
    }

    const ok = await showConfirmCenter(
      `Bạn có chắc muốn xóa ${selectedIds.length} sản phẩm đã chọn khỏi giỏ hàng?`,
      "Xác nhận xóa",
    );
    if (!ok) return;

    try {
      await postCartAction("remove_selected", { ids: selectedIds });
      selectedIds.forEach((id) => {
        const row = getRowByDetailId(id);
        if (row) row.remove();
      });
      ensureEmptyCartView();
      calculateTotal();
      refreshHeaderCartCount();
      await showAlertCenter(
        `Đã xóa ${selectedIds.length} sản phẩm.`,
        "Thành công",
      );
    } catch (error) {
      await showAlertCenter(
        error.message || "Không thể xóa danh sách đã chọn.",
      );
    }
  }

  window.toggleAll = function toggleAll(source) {
    const checked = !!source?.checked;
    document.querySelectorAll(".item-checkbox").forEach((cb) => {
      cb.checked = checked;
    });
    calculateTotal();
  };

  window.calculateTotal = function calculateTotal() {
    let subtotal = 0;
    let count = 0;

    document.querySelectorAll(".item-checkbox").forEach((cb) => {
      const detailId = cb.dataset.id;
      const rowKey = cb.dataset.rowKey || detailId;
      const row = getRowByKey(rowKey, detailId);
      if (!row) return;

      if (cb.checked) {
        const price = Math.max(0, parseFloat(cb.dataset.price || "0") || 0);
        const qty = Math.max(
          1,
          parseInt(getQtyInput(detailId, rowKey)?.value || "1", 10) || 1,
        );
        subtotal += price * qty;
        count += qty;
        row.classList.add("active");
      } else {
        row.classList.remove("active");
      }
    });

    const discount = getVoucherDiscount(subtotal);
    const finalTotal = Math.max(0, subtotal - discount.amount);

    if (appliedVoucher) {
      setVoucherFeedback(
        discount.reason,
        discount.isEligible ? "success" : "error",
      );
    } else {
      setVoucherFeedback("Chưa áp dụng voucher.", "info");
    }

    const countEl = document.getElementById("count-items-head");
    const subTotalEl = document.getElementById("sub-total");
    const discountEl = document.getElementById("discount-amount");
    const finalPriceEl = document.getElementById("final-price");

    if (countEl) countEl.textContent = String(count);
    if (subTotalEl) subTotalEl.textContent = formatMoney(subtotal);
    if (discountEl) discountEl.textContent = `-${formatMoney(discount.amount)}`;
    if (finalPriceEl) finalPriceEl.textContent = formatMoney(finalTotal);

    updateQrPaymentPreview();
  };

  function bindPaymentMethodActions() {
    const paymentRadios = document.querySelectorAll(
      'input[name="payment_method"]',
    );
    if (!paymentRadios.length) return;

    paymentRadios.forEach((radio) => {
      radio.addEventListener("change", updateQrPaymentPreview);
    });

    updateQrPaymentPreview();
  }

  function renderVoucherQuickList(items) {
    const wrap = document.getElementById("voucherQuickList");
    if (!wrap) return;

    if (!Array.isArray(items) || items.length === 0) {
      wrap.innerHTML = "";
      return;
    }

    wrap.innerHTML = items
      .map(
        (item) =>
          `<button type="button" class="voucher-quick-item" data-code="${String(item.ma_voucher || "").replace(/\"/g, "&quot;")}">${item.ma_voucher}${item.ten_voucher ? ` - ${item.ten_voucher}` : ""} • ${item.discount_text}</button>`,
      )
      .join("");
  }

  async function loadVoucherQuickList() {
    try {
      const data = await postCartAction("get_available_vouchers");
      const list = Array.isArray(data?.vouchers) ? data.vouchers : [];
      renderVoucherQuickList(list);

      const select = document.getElementById("voucherSelect");
      if (select) {
        const options = ['<option value="">-- Chọn voucher có sẵn --</option>'];
        list.forEach((item) => {
          const code = String(item.ma_voucher || "").trim();
          if (!code) return;
          options.push(
            `<option value="${code.replace(/\"/g, "&quot;")}">${item.label || code}</option>`,
          );
        });
        select.innerHTML = options.join("");
      }
    } catch (_) {
      renderVoucherQuickList([]);
    }
  }

  async function applyVoucherByCode(rawCode) {
    const code = String(rawCode || "")
      .trim()
      .toUpperCase();
    if (!code) {
      setVoucherFeedback("Vui lòng nhập mã voucher.", "error");
      return;
    }

    const applyBtn = document.getElementById("applyVoucherBtn");
    const input = document.getElementById("voucherCodeInput");
    const select = document.getElementById("voucherSelect");
    const clearBtn = document.getElementById("clearVoucherBtn");

    if (applyBtn) {
      applyBtn.disabled = true;
      applyBtn.textContent = "Đang kiểm tra...";
    }

    try {
      const data = await postCartAction("apply_voucher", { ma_voucher: code });
      if (!data?.voucher) {
        throw new Error("Mã voucher không hợp lệ.");
      }

      appliedVoucher = {
        code: data.voucher.code,
        name: data.voucher.name || "",
        discount_type: data.voucher.discount_type,
        discount_value: data.voucher.discount_value,
        discount_text: data.voucher.discount_text,
        min_order_value: data.voucher.min_order_value,
      };

      if (input) input.value = code;
      if (select) select.value = code;
      if (clearBtn) clearBtn.style.display = "inline-block";
      calculateTotal();
    } catch (error) {
      setVoucherFeedback(error.message || "Mã voucher không hợp lệ.", "error");
    } finally {
      if (applyBtn) {
        applyBtn.disabled = false;
        applyBtn.textContent = "Áp dụng";
      }
    }
  }

  function bindVoucherActions() {
    const applyBtn = document.getElementById("applyVoucherBtn");
    const clearBtn = document.getElementById("clearVoucherBtn");
    const input = document.getElementById("voucherCodeInput");
    const select = document.getElementById("voucherSelect");
    const quickList = document.getElementById("voucherQuickList");

    if (applyBtn) {
      applyBtn.addEventListener("click", function () {
        applyVoucherByCode(input?.value || select?.value || "");
      });
    }

    if (input) {
      input.addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
          event.preventDefault();
          applyVoucherByCode(input.value || "");
        }
      });
    }

    if (select) {
      select.addEventListener("change", function () {
        const code = String(select.value || "").trim();
        if (!code) return;
        if (input) input.value = code;
        applyVoucherByCode(code);
      });
    }

    if (clearBtn) {
      clearBtn.addEventListener("click", function () {
        clearVoucher(true);
      });
    }

    if (quickList) {
      quickList.addEventListener("click", function (event) {
        const target = event.target.closest(".voucher-quick-item");
        if (!target) return;
        const code = target.getAttribute("data-code") || "";
        if (input) input.value = code;
        if (select) select.value = code;
        applyVoucherByCode(code);
      });
    }
  }

  function bindDeleteSelectedActions() {
    const btn = document.getElementById("deleteSelectedBtn");
    const iconBtn = document.getElementById("deleteSelectedIcon");

    if (btn) btn.addEventListener("click", removeSelectedItems);
    if (iconBtn) iconBtn.addEventListener("click", removeSelectedItems);
  }

  function bindCartItemActions() {
    const cartList = document.getElementById("cart-list");
    if (!cartList) return;

    cartList.addEventListener("click", function (event) {
      const qtyBtn = event.target.closest(".qty-btn[data-change]");
      if (qtyBtn) {
        event.preventDefault();
        const detailId = Number(qtyBtn.getAttribute("data-detail-id") || 0);
        const change = Number(qtyBtn.getAttribute("data-change") || 0);
        const productId = String(
          qtyBtn.getAttribute("data-product-id") ||
            qtyBtn.closest(".cart-item-card")?.dataset?.productId ||
            "",
        ).trim();
        const rowKey = String(qtyBtn.getAttribute("data-row-key") || "").trim();
        if (change !== 0 && (detailId > 0 || productId)) {
          window.updateQty(detailId, change, productId, rowKey);
        }
        return;
      }

      const deleteBtn = event.target.closest(".delete-item-btn");
      if (!deleteBtn) return;

      event.preventDefault();
      const detailId = Number(deleteBtn.getAttribute("data-delete-id") || 0);
      window.deleteFromCart(detailId, deleteBtn);
    });

    cartList.addEventListener("change", function (event) {
      const checkbox = event.target.closest(".item-checkbox");
      if (!checkbox) return;
      calculateTotal();
    });

    // Fallback binding: đảm bảo nút +/- vẫn chạy nếu event delegation bị chặn bởi layout khác
    cartList.querySelectorAll(".qty-btn[data-change]").forEach((btn) => {
      if (btn.dataset.qtyBound === "1") return;
      btn.dataset.qtyBound = "1";

      btn.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();

        const detailId = Number(btn.getAttribute("data-detail-id") || 0);
        const change = Number(btn.getAttribute("data-change") || 0);
        const productId = String(
          btn.getAttribute("data-product-id") ||
            btn.closest(".cart-item-card")?.dataset?.productId ||
            "",
        ).trim();
        const rowKey = String(btn.getAttribute("data-row-key") || "").trim();
        if (change !== 0 && (detailId > 0 || productId)) {
          window.updateQty(detailId, change, productId, rowKey);
        }
      });
    });
  }

  function normalizeInitialSelection() {
    const itemCheckboxes = Array.from(
      document.querySelectorAll(".item-checkbox"),
    );
    if (itemCheckboxes.length === 0) {
      return;
    }

    const hasChecked = itemCheckboxes.some((cb) => !!cb.checked);
    if (!hasChecked) {
      itemCheckboxes.forEach((cb) => {
        cb.checked = true;
      });
    }

    const checkAllTop = document.getElementById("checkAllTop");
    if (checkAllTop) {
      checkAllTop.checked = itemCheckboxes.every((cb) => !!cb.checked);
    }
  }

  function tryApplyVoucherFromQuery() {
    const params = new URLSearchParams(window.location.search || "");
    const code = String(params.get("voucher") || "")
      .trim()
      .toUpperCase();
    if (!code) return;

    const input = document.getElementById("voucherCodeInput");
    const select = document.getElementById("voucherSelect");
    if (input) input.value = code;
    if (select) select.value = code;

    applyVoucherByCode(code);
  }

  function getSelectedCheckoutItems() {
    const selectedItems = [];

    document.querySelectorAll(".item-checkbox:checked").forEach((checkbox) => {
      const detailId = Number(checkbox?.dataset?.id || 0);
      const rowKey = String(checkbox?.dataset?.rowKey || detailId || "").trim();
      const productId = String(checkbox?.dataset?.productId || "").trim();
      const productName = String(checkbox?.dataset?.productName || "").trim();
      if (!productId) return;

      const qtyInput = getQtyInput(detailId, rowKey);
      const qty = Math.max(1, parseInt(qtyInput?.value || "1", 10) || 1);
      const price = Math.max(
        0,
        parseFloat(checkbox?.dataset?.price || "0") || 0,
      );

      selectedItems.push({
        id: productId,
        chi_tiet_id: detailId,
        qty,
        price,
        name: productName,
      });
    });

    return selectedItems;
  }

  function getAllCheckoutItems() {
    const allItems = [];

    document.querySelectorAll(".item-checkbox").forEach((checkbox) => {
      const detailId = Number(checkbox?.dataset?.id || 0);
      const rowKey = String(checkbox?.dataset?.rowKey || detailId || "").trim();
      const productId = String(checkbox?.dataset?.productId || "").trim();
      const productName = String(checkbox?.dataset?.productName || "").trim();
      if (!productId) return;

      const qtyInput = getQtyInput(detailId, rowKey);
      const qty = Math.max(1, parseInt(qtyInput?.value || "1", 10) || 1);
      const price = Math.max(
        0,
        parseFloat(checkbox?.dataset?.price || "0") || 0,
      );

      allItems.push({
        id: productId,
        chi_tiet_id: detailId,
        qty,
        price,
        name: productName,
      });
    });

    return allItems;
  }

  function collectItemsFromDomFallback() {
    const rows = Array.from(document.querySelectorAll(".cart-item-card"));
    const items = [];

    rows.forEach((row, index) => {
      const productId = String(row?.dataset?.productId || "").trim();
      const productName = String(row?.dataset?.productName || "").trim();
      if (!productId) return;

      const qtyInput = row.querySelector(".qty-input");
      const qty = Math.max(1, parseInt(qtyInput?.value || "1", 10) || 1);

      const checkbox = row.querySelector(".item-checkbox");
      const dataPrice = Number.parseFloat(checkbox?.dataset?.price || "0") || 0;
      const priceFromTotal = parseMoneyText(
        row.querySelector(".total-price-col span")?.textContent || "0",
      );
      const price = Math.max(
        0,
        dataPrice || (qty > 0 ? priceFromTotal / qty : 0),
      );

      const rawDetailId = Number(checkbox?.dataset?.id || 0);
      const detailId = rawDetailId > 0 ? rawDetailId : index + 1;

      items.push({
        id: productId,
        chi_tiet_id: detailId,
        qty,
        price,
        name: productName,
      });
    });

    return items;
  }

  function bindCheckoutButton() {
    const checkoutButton = document.querySelector(".btn-checkout-big");
    if (!checkoutButton) return;

    if (checkoutButton.dataset.boundCheckout === "1") {
      return;
    }
    checkoutButton.dataset.boundCheckout = "1";

    checkoutButton.addEventListener("click", async function () {
      if (isCheckoutSubmitting) {
        return;
      }

      let selectedItems = getSelectedCheckoutItems();
      if (selectedItems.length === 0) {
        const allItems = getAllCheckoutItems();
        if (allItems.length === 0) {
          const fallbackItems = collectItemsFromDomFallback();
          if (fallbackItems.length > 0) {
            selectedItems = fallbackItems;
          }
        }

        if (selectedItems.length === 0 && allItems.length === 0) {
          await showAlertCenter("Giỏ hàng đang trống, chưa thể đặt hàng.");
          return;
        }

        if (selectedItems.length === 0) {
          const useAllItems = await showDialog({
            title: "Chưa chọn sản phẩm",
            message:
              "Bạn chưa tick sản phẩm để đặt hàng. Dùng tất cả sản phẩm hiện có trong giỏ để đặt luôn không?",
            confirmText: "Mua tất cả",
            cancelText: "Để mình chọn lại",
            showCancel: true,
            confirmClass: "btn btn-primary",
            cancelClass: "btn btn-outline-secondary",
          });

          if (!useAllItems) {
            return;
          }

          document.querySelectorAll(".item-checkbox").forEach((cb) => {
            cb.checked = true;
          });
          selectedItems = allItems;
          calculateTotal();
        }
      }

      const paymentMethod = getSelectedPaymentMethod();
      const qrConfirmed =
        paymentMethod === "qr" &&
        !!document.getElementById("qrPaidConfirm")?.checked;
      if (paymentMethod === "qr" && !qrConfirmed) {
        await showAlertCenter(
          "Bạn vui lòng xác nhận đã quét QR và hoàn tất chuyển khoản trước khi đặt hàng.",
          "Thiếu xác nhận thanh toán QR",
        );
        return;
      }

      const confirmPlaceOrder = await showDialog({
        title: "Xác nhận đặt hàng",
        message: `Bạn chắc chắn muốn đặt ${selectedItems.length} sản phẩm đã chọn không?\nBấm \"Hủy\" để giữ nguyên giỏ hàng và KHÔNG gửi đơn.`,
        confirmText: "Đặt hàng",
        cancelText: "Hủy",
        showCancel: true,
        confirmClass: "btn btn-primary",
        cancelClass: "btn btn-outline-secondary",
        confirmLoadingMs: 1500,
        confirmLoadingText: "Đang xử lý...",
      });
      if (!confirmPlaceOrder) {
        return;
      }

      isCheckoutSubmitting = true;
      const originalText = checkoutButton.textContent;
      checkoutButton.disabled = true;
      checkoutButton.textContent = "Đang gửi đơn...";

      try {
        const subtotal = selectedItems.reduce(
          (sum, item) => sum + Number(item.price || 0) * Number(item.qty || 0),
          0,
        );
        const voucherMeta = getVoucherDiscount(subtotal);

        const response = await fetch("checkout-order.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
          body: JSON.stringify({
            items: selectedItems,
            payment_method: paymentMethod,
            qr_paid_confirmed: qrConfirmed,
            voucher: appliedVoucher
              ? {
                  code: appliedVoucher.code,
                  name: appliedVoucher.name || "",
                  discount_amount: voucherMeta.amount,
                  discount_type: appliedVoucher.discount_type,
                  discount_value: appliedVoucher.discount_value,
                }
              : null,
          }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data?.ok !== true) {
          if (data?.needs_profile_completion === true) {
            const missingFields = Array.isArray(data?.missing_fields)
              ? data.missing_fields.filter(Boolean)
              : [];
            const missingText =
              missingFields.length > 0
                ? `\nThông tin còn thiếu: ${missingFields.join(", ")}.`
                : "";

            const goProfile = await showDialog({
              title: "Cần cập nhật hồ sơ",
              message: `${data?.message || "Bạn cần cập nhật hồ sơ trước khi đặt hàng."}${missingText}\n\nBạn có muốn mở trang Cài đặt tài khoản ngay bây giờ không?`,
              confirmText: "Đi đến cập nhật",
              cancelText: "Để sau",
              showCancel: true,
              confirmClass: "btn btn-primary",
              cancelClass: "btn btn-outline-secondary",
            });

            if (goProfile) {
              const profileUrl =
                String(
                  data?.profile_url || "tai-khoan.php?tab=settings",
                ).trim() || "tai-khoan.php?tab=settings";
              window.location.href = profileUrl;
              return;
            }

            return;
          }

          throw new Error(
            data?.message || "Không thể đặt hàng. Vui lòng thử lại.",
          );
        }

        await postCartAction("clear_cart");
        await refreshHeaderCartCount();

        const orderId = data?.order_id || "(đang cập nhật)";
        const serverDiscount = Math.max(
          0,
          Number(data?.discount_amount || voucherMeta.amount || 0),
        );
        const voucherHint =
          appliedVoucher && serverDiscount > 0
            ? `\nVoucher ${appliedVoucher.code}${appliedVoucher.name ? ` - ${appliedVoucher.name}` : ""} đã giảm ${formatMoney(serverDiscount)}.`
            : "";

        const askOpenOrderPage = await showDialog({
          title: "Đặt hàng thành công",
          message: `Đặt hàng thành công. Mã đơn: ${orderId}.\nTrạng thái hiện tại: Chờ duyệt.\nThanh toán: ${paymentMethod === "qr" ? "QR chuyển khoản" : "COD"}.${voucherHint}\n\nBạn có muốn mở trang "Đơn hàng của tôi" để theo dõi ngay không?`,
          confirmText: "Mở đơn hàng",
          cancelText: "Ở lại giỏ hàng",
          showCancel: true,
          confirmClass: "btn btn-primary",
          cancelClass: "btn btn-outline-secondary",
        });

        if (askOpenOrderPage) {
          window.location.href = "don-hang-cua-toi.php";
          return;
        }

        window.location.reload();
      } catch (error) {
        await showAlertCenter(
          error.message || "Không thể kết nối máy chủ. Vui lòng thử lại sau.",
        );
      } finally {
        isCheckoutSubmitting = false;
        checkoutButton.disabled = false;
        checkoutButton.textContent = originalText;
      }
    });
  }

  function init() {
    bindCartItemActions();
    bindDeleteSelectedActions();
    bindVoucherActions();
    bindPaymentMethodActions();
    loadVoucherQuickList();
    bindCheckoutButton();
    refreshHeaderCartCount();
    normalizeInitialSelection();
    calculateTotal();
    tryApplyVoucherFromQuery();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
