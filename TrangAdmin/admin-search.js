/* Admin Search & Scroll Management */

const SCROLL_POSITION_KEY = "ack_main_scroll_pos";
const SIDEBAR_SCROLL_POSITION_KEY = "ack_sidebar_scroll_pos";
const GLOBAL_LOADING_OVERLAY_ID = "ack-global-loading-overlay";
const GLOBAL_LOADING_MS = 1000;

let isLoading = false;
const nativeFormSubmit = HTMLFormElement.prototype.submit;

function getSidebarScrollContainer() {
  return (
    document.querySelector(".sidebar nav") || document.querySelector(".sidebar")
  );
}

function saveMainScroll() {
  const mainContent = document.querySelector(".main-content");
  if (mainContent) {
    sessionStorage.setItem(
      SCROLL_POSITION_KEY,
      String(mainContent.scrollTop || 0),
    );
  }
}

function saveSidebarScroll() {
  const sidebar = getSidebarScrollContainer();
  if (!sidebar) return;

  sessionStorage.setItem(
    SIDEBAR_SCROLL_POSITION_KEY,
    String(sidebar.scrollTop || 0),
  );
}

function restoreMainScroll() {
  const mainContent = document.querySelector(".main-content");
  if (!mainContent) return;

  const savedPos = sessionStorage.getItem(SCROLL_POSITION_KEY);
  if (savedPos !== null) {
    const top = Number.parseInt(savedPos, 10);
    if (Number.isFinite(top)) {
      mainContent.scrollTop = top;
    }
    sessionStorage.removeItem(SCROLL_POSITION_KEY);
  }
}

function restoreSidebarScroll() {
  const sidebar = getSidebarScrollContainer();
  if (!sidebar) return;

  const savedPos = sessionStorage.getItem(SIDEBAR_SCROLL_POSITION_KEY);
  if (savedPos !== null) {
    const top = Number.parseInt(savedPos, 10);
    if (Number.isFinite(top)) {
      sidebar.scrollTop = top;
    }
    sessionStorage.removeItem(SIDEBAR_SCROLL_POSITION_KEY);
  }

  const activeItem = sidebar.querySelector(".nav-item.active");
  if (activeItem && typeof activeItem.scrollIntoView === "function") {
    activeItem.scrollIntoView({ block: "nearest", inline: "nearest" });
  }
}

function normalizeText(value) {
  return (value || "")
    .toString()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function findSearchRows(searchInput) {
  const root = searchInput.closest(".main-content") || document;
  const body = root.querySelector("tbody");
  if (body) {
    const tableRows = Array.from(body.querySelectorAll("tr"));
    if (tableRows.length) {
      return tableRows;
    }
  }

  const cardRows = Array.from(
    root.querySelectorAll(
      ".customer-list .customer-row, .customer-row, [data-customer-id]",
    ),
  );

  return cardRows;
}

function bindTableSearch(searchInput) {
  if (!searchInput || searchInput.dataset.ackSearchBound === "1") return;

  const rows = findSearchRows(searchInput);
  if (!rows.length) return;

  const filter = () => {
    const keyword = normalizeText(searchInput.value);

    rows.forEach((row) => {
      const haystack = normalizeText(
        row.innerText ||
          [
            row.getAttribute("data-id"),
            row.getAttribute("data-name"),
            row.getAttribute("data-ma-hang"),
            row.getAttribute("data-ma-phieu"),
          ]
            .filter(Boolean)
            .join(" "),
      );

      row.style.display =
        keyword === "" || haystack.includes(keyword) ? "" : "none";
    });
  };

  searchInput.addEventListener("input", filter);
  searchInput.dataset.ackSearchBound = "1";
}

function initAdminSearch() {
  document
    .querySelectorAll("input.search-top, input.search-input")
    .forEach((input) => bindTableSearch(input));
}

function ensureAdminToastStack() {
  let stack = document.getElementById("ack-admin-toast-stack");
  if (stack) return stack;

  stack = document.createElement("div");
  stack.id = "ack-admin-toast-stack";
  document.body.appendChild(stack);
  return stack;
}

function isConvertibleAlert(node) {
  if (!(node instanceof HTMLElement)) return false;
  if (!node.classList.contains("alert")) return false;
  if (node.classList.contains("d-none")) return false;
  if (node.classList.contains("payment-warning")) return false;
  if (node.id === "orderPaymentHint") return false;
  if (node.dataset.ackToastConverted === "1") return false;
  if (node.closest("#ack-admin-toast-stack")) return false;
  if (!node.textContent || node.textContent.trim() === "") return false;
  if (node.closest(".modal")) return false;
  return true;
}

function initAdminToasts(root = document) {
  const scope =
    root instanceof Element || root instanceof Document ? root : document;
  const alerts = Array.from(scope.querySelectorAll(".alert"));
  if (!alerts.length) return;

  const stack = ensureAdminToastStack();

  alerts.filter(isConvertibleAlert).forEach((alert, index) => {
    alert.dataset.ackToastConverted = "1";
    alert.classList.add("ack-admin-toast");
    stack.appendChild(alert);

    requestAnimationFrame(() => {
      alert.classList.add("show");
    });

    const holdMs = 3000 + Math.min(index, 3) * 350;
    setTimeout(() => {
      alert.classList.remove("show");
      setTimeout(() => {
        if (alert.parentNode) {
          alert.parentNode.removeChild(alert);
        }
      }, 260);
    }, holdMs);
  });
}

function observeAdminAlerts() {
  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      mutation.addedNodes.forEach((node) => {
        if (!(node instanceof Element)) return;

        if (node.classList.contains("alert")) {
          initAdminToasts(node.parentElement || document);
          return;
        }

        if (node.querySelector?.(".alert")) {
          initAdminToasts(node);
        }
      });
    }
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
  });
}

function ensureGlobalLoadingOverlay() {
  let overlay = document.getElementById(GLOBAL_LOADING_OVERLAY_ID);
  if (overlay) return overlay;

  overlay = document.createElement("div");
  overlay.id = GLOBAL_LOADING_OVERLAY_ID;
  overlay.className = "ack-global-loading-overlay";
  overlay.setAttribute("aria-hidden", "true");
  overlay.innerHTML =
    '<div class="ack-global-loading-spinner" aria-label="Loading" role="status"></div>';
  document.body.appendChild(overlay);
  return overlay;
}

function showGlobalLoading() {
  closeActiveModalsForLoading();
  const overlay = ensureGlobalLoadingOverlay();
  isLoading = true;
  overlay.classList.add("show");
  overlay.setAttribute("aria-hidden", "false");
  document.body.classList.add("ack-loading-active");
}

function hideGlobalLoading() {
  const overlay = document.getElementById(GLOBAL_LOADING_OVERLAY_ID);
  isLoading = false;
  document.body.classList.remove("ack-loading-active");
  if (!overlay) return;
  overlay.classList.remove("show");
  overlay.setAttribute("aria-hidden", "true");
}

function closeActiveModalsForLoading() {
  const openModals = Array.from(document.querySelectorAll(".modal.show"));

  openModals.forEach((modalEl) => {
    try {
      if (window.bootstrap?.Modal) {
        const instance = window.bootstrap.Modal.getInstance(modalEl);
        if (instance) {
          instance.hide();
          return;
        }
      }
    } catch (_) {
      // fallback below
    }

    modalEl.classList.remove("show");
    modalEl.style.display = "none";
    modalEl.setAttribute("aria-hidden", "true");
  });

  document.querySelectorAll(".modal-backdrop").forEach((el) => el.remove());
  document.body.classList.remove("modal-open");
  document.body.style.removeProperty("padding-right");
}

function shouldTriggerCrudLoading(form) {
  if (!(form instanceof HTMLFormElement)) return false;

  const actionInput = form.querySelector(
    'input[name="crud_action"], input[name="action"], input[name="form_action"]',
  );

  const actionValue = String(actionInput?.value || "")
    .toLowerCase()
    .trim();

  // Add/Edit/Delete operations (treat update as edit)
  return /(^|_)(add|create|edit|update|delete|remove)(_|$)/i.test(actionValue);
}

function getViewLoadingTrigger(target) {
  if (!(target instanceof Element)) return null;

  const trigger = target.closest("button, a, [role='button']");
  if (!(trigger instanceof HTMLElement)) return null;
  if (trigger.closest("#ack-global-loading-overlay")) return false;
  if (trigger.dataset.ackSkipLoading === "1") return null;

  const disabled = trigger.getAttribute("disabled") !== null;
  if (disabled) return null;

  const type = String(trigger.getAttribute("type") || "").toLowerCase();
  if (type === "submit") return null;

  const idAndClass = `${trigger.id || ""} ${
    typeof trigger.className === "string" ? trigger.className : ""
  }`
    .toLowerCase()
    .trim();
  const text = normalizeText(trigger.textContent || "");

  const looksLikeViewButton =
    /(^|\s|[-_])(btnview|view|btn-view|xem)(\s|$|[-_])/i.test(idAndClass) ||
    text.includes("xem chi tiet") ||
    text.includes("xem chi tiết");

  return looksLikeViewButton ? trigger : null;
}

function scheduleCrudSubmit(form, submitMethod) {
  if (!(form instanceof HTMLFormElement)) return false;

  if (!shouldTriggerCrudLoading(form)) {
    submitMethod();
    return true;
  }

  if (form.dataset.ackBypassCrudLoading === "1") {
    form.dataset.ackBypassCrudLoading = "0";
    submitMethod();
    return true;
  }

  if (isLoading) {
    return false;
  }

  showGlobalLoading();
  form.dataset.ackBypassCrudLoading = "1";

  window.setTimeout(() => {
    submitMethod();
  }, GLOBAL_LOADING_MS);

  return false;
}

function initGlobalCrudLoading() {
  ensureGlobalLoadingOverlay();

  // In case page is restored from bfcache / interrupted navigation
  hideGlobalLoading();
  window.addEventListener("pageshow", hideGlobalLoading);

  HTMLFormElement.prototype.submit = function patchedAdminSubmit() {
    const form = this;
    const proceed = scheduleCrudSubmit(form, () => {
      form.dataset.ackBypassCrudLoading = "0";
      nativeFormSubmit.call(form);
    });

    if (!proceed) {
      return;
    }
  };

  document.addEventListener(
    "submit",
    (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) return;

      const proceed = scheduleCrudSubmit(form, () => {
        if (typeof form.requestSubmit === "function") {
          form.requestSubmit();
          return;
        }

        form.dataset.ackBypassCrudLoading = "0";
        nativeFormSubmit.call(form);
      });

      if (!proceed) {
        event.preventDefault();
      }
    },
    true,
  );

  document.addEventListener(
    "click",
    (event) => {
      const trigger = getViewLoadingTrigger(event.target);
      if (!trigger) return;

      if (trigger.dataset.ackViewLoadingBypass === "1") {
        trigger.dataset.ackViewLoadingBypass = "0";
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();

      if (isLoading) {
        return;
      }

      showGlobalLoading();
      window.setTimeout(() => {
        hideGlobalLoading();
        trigger.dataset.ackViewLoadingBypass = "1";
        if (typeof trigger.click === "function") {
          trigger.click();
        }
      }, GLOBAL_LOADING_MS);
    },
    true,
  );
}

(function setupScrollPersistence() {
  window.addEventListener("beforeunload", () => {
    saveMainScroll();
    saveSidebarScroll();
  });

  document.addEventListener("DOMContentLoaded", () => {
    restoreMainScroll();
    restoreSidebarScroll();
    initAdminSearch();
    initAdminToasts();
    setTimeout(() => initAdminToasts(), 80);
    observeAdminAlerts();
    initGlobalCrudLoading();
  });

  document.querySelectorAll("a[href*='admin']").forEach((link) => {
    link.addEventListener("click", () => {
      saveMainScroll();
      saveSidebarScroll();
    });
  });

  document.querySelectorAll("form").forEach((form) => {
    form.addEventListener("submit", () => {
      saveMainScroll();
      saveSidebarScroll();
    });
  });
})();
