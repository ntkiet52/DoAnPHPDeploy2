(function () {
  const CART_ENDPOINT = "cart-handler.php";
  let appliedVoucher = null;

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
    } = options;

    return new Promise((resolve) => {
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
        backdrop.remove();
        resolve(result);
      };

      backdrop.addEventListener("click", (event) => {
        if (event.target === backdrop && showCancel) {
          close(false);
        }
      });

      backdrop
        .querySelector('[data-role="ok"]')
        ?.addEventListener("click", () => {
          close(true);
        });

      backdrop
        .querySelector('[data-role="cancel"]')
        ?.addEventListener("click", () => close(false));

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

    const minOrder = Math.max(0, Number(appliedVoucher.min_order_value || 0));
    if (subtotal < minOrder) {
      return {
        amount: 0,
        isEligible: false,
        reason: `Mã ${appliedVoucher.code} cần đơn tối thiểu ${formatMoney(minOrder)}.`,
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
      reason: `Đã áp dụng mã ${appliedVoucher.code} (${appliedVoucher.discount_text}).`,
    };
  }

  function getRowByDetailId(detailId) {
    return document.getElementById(`item-${detailId}`);
  }

  function updateRowTotal(detailId) {
    const checkbox = document.querySelector(
      `.item-checkbox[data-id="${detailId}"]`,
    );
    const qtyInput = document.getElementById(`qty-${detailId}`);
    const totalEl = document.getElementById(`total-${detailId}`);
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

  window.updateQty = async function updateQty(detailId, change) {
    const id = Number(detailId || 0);
    if (id <= 0) return;

    const qtyInput = document.getElementById(`qty-${id}`);
    if (!qtyInput) return;

    const current = Math.max(1, parseInt(qtyInput.value || "1", 10) || 1);
    const nextQty = current + Number(change || 0);
    if (nextQty < 1) return;

    try {
      await postCartAction("update_quantity", {
        id_chi_tiet: id,
        so_luong: nextQty,
      });
      qtyInput.value = String(nextQty);
      updateRowTotal(id);
      calculateTotal();
    } catch (error) {
      await showAlertCenter(error.message || "Không thể cập nhật số lượng.");
    }
  };

  window.deleteFromCart = async function deleteFromCart(chiTietId, element) {
    const id = Number(chiTietId || 0);
    if (id <= 0) return;
    const allowDelete = await showConfirmCenter(
      "Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng?",
      "Xác nhận xóa",
    );
    if (!allowDelete) {
      return;
    }

    try {
      await postCartAction("remove_item", { id_chi_tiet: id });
      const card = element?.closest(".cart-item-card") || getRowByDetailId(id);
      if (card) card.remove();
      ensureEmptyCartView();
      calculateTotal();
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
      const row = getRowByDetailId(detailId);
      if (!row) return;

      if (cb.checked) {
        const price = Math.max(0, parseFloat(cb.dataset.price || "0") || 0);
        const qty = Math.max(
          1,
          parseInt(
            document.getElementById(`qty-${detailId}`)?.value || "1",
            10,
          ) || 1,
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
  };

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
          `<button type="button" class="voucher-quick-item" data-code="${String(item.ma_voucher || "").replace(/\"/g, "&quot;")}">${item.ma_voucher} • ${item.discount_text}</button>`,
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
      const productId = String(checkbox?.dataset?.productId || "").trim();
      const productName = String(checkbox?.dataset?.productName || "").trim();
      if (detailId <= 0) return;

      const qtyInput = document.getElementById(`qty-${detailId}`);
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

  function bindCheckoutButton() {
    const checkoutButton = document.querySelector(".btn-checkout-big");
    if (!checkoutButton) return;

    checkoutButton.addEventListener("click", async function () {
      const selectedItems = getSelectedCheckoutItems();
      if (selectedItems.length === 0) {
        await showAlertCenter("Vui lòng chọn ít nhất 1 sản phẩm để đặt hàng.");
        return;
      }

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
            voucher: appliedVoucher
              ? {
                  code: appliedVoucher.code,
                  discount_amount: voucherMeta.amount,
                  discount_type: appliedVoucher.discount_type,
                  discount_value: appliedVoucher.discount_value,
                }
              : null,
          }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data?.ok !== true) {
          throw new Error(
            data?.message || "Không thể đặt hàng. Vui lòng thử lại.",
          );
        }

        await postCartAction("clear_cart");

        const orderId = data?.order_id || "(đang cập nhật)";
        const voucherHint =
          appliedVoucher && voucherMeta.amount > 0
            ? `\nVoucher ${appliedVoucher.code} đã giảm ${formatMoney(voucherMeta.amount)}.`
            : "";

        const askOpenOrderPage = await showConfirmCenter(
          `Đặt hàng thành công. Mã đơn: ${orderId}.\nTrạng thái hiện tại: Chờ duyệt.${voucherHint}\n\nBạn có muốn mở trang "Đơn hàng của tôi" để theo dõi ngay không?`,
          "Đặt hàng thành công",
        );

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
        checkoutButton.disabled = false;
        checkoutButton.textContent = originalText;
      }
    });
  }

  function init() {
    bindDeleteSelectedActions();
    bindVoucherActions();
    loadVoucherQuickList();
    bindCheckoutButton();
    calculateTotal();
    tryApplyVoucherFromQuery();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
