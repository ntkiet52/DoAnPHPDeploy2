/* Admin Search & Scroll Management */

const SCROLL_POSITION_KEY = "ack_main_scroll_pos";
const SIDEBAR_SCROLL_POSITION_KEY = "ack_sidebar_scroll_pos";

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

function bindModalSelectSearch() {
  const modalSelects = Array.from(
    document.querySelectorAll(".modal select.form-select"),
  ).filter((select) => {
    if (select.dataset.enableSearch === "false") return false;
    return select.options && select.options.length >= 8;
  });

  modalSelects.forEach((select) => {
    if (select.dataset.ackSelectSearchBound === "1") return;

    const wrapper = document.createElement("div");
    wrapper.className = "mb-2";

    const input = document.createElement("input");
    input.type = "text";
    input.className = "form-control form-control-sm";
    input.placeholder = "Tìm nhanh trong danh sách...";

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(input);

    const options = Array.from(select.options);

    const runFilter = () => {
      const keyword = normalizeText(input.value);
      let hasVisibleSelected = false;

      options.forEach((option, index) => {
        if (index === 0 && (option.value || "") === "") {
          option.hidden = false;
          return;
        }

        const text = normalizeText(option.textContent);
        const visible = keyword === "" || text.includes(keyword);
        option.hidden = !visible;

        if (visible && option.selected) {
          hasVisibleSelected = true;
        }
      });

      if (!hasVisibleSelected && keyword !== "") {
        const current = select.options[select.selectedIndex];
        if (current && current.hidden) {
          select.selectedIndex = 0;
          select.dispatchEvent(new Event("change", { bubbles: true }));
        }
      }
    };

    input.addEventListener("input", runFilter);

    const modal = select.closest(".modal");
    if (modal) {
      modal.addEventListener("show.bs.modal", () => {
        input.value = "";
        runFilter();
      });
    }

    select.dataset.ackSelectSearchBound = "1";
  });
}

function initAdminSearch() {
  document
    .querySelectorAll("input.search-top, input.search-input")
    .forEach((input) => bindTableSearch(input));

  bindModalSelectSearch();
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
