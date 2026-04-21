// ====================================================
// WireGuard Panel — Main JS
// ====================================================

/** Read CSRF token from meta tag injected by PHP */
function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? "";
}

/**
 * Generic AJAX POST helper.
 * Returns a Promise that resolves with parsed JSON or rejects on network/parse error.
 */
function ajaxPost(url, data) {
  const body = new URLSearchParams(data);
  body.set("csrf_token", getCsrfToken());
  return fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: body.toString(),
  }).then((r) => r.json());
}

document.addEventListener("DOMContentLoaded", function () {
  // --- Sidebar mobile toggle ---
  const toggleBtn = document.getElementById("sidebarToggle");
  const sidebar = document.querySelector(".sidebar");

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", function () {
      sidebar.classList.toggle("open");
    });

    document.addEventListener("click", function (e) {
      if (
        window.innerWidth < 992 &&
        !sidebar.contains(e.target) &&
        !toggleBtn.contains(e.target)
      ) {
        sidebar.classList.remove("open");
      }
    });
  }

  // --- Auto-dismiss flash alerts ---
  setTimeout(function () {
    document.querySelectorAll(".alert.alert-success").forEach(function (el) {
      var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    });
  }, 4000);

  // ── AJAX Toggle (فعال/غیرفعال) ──────────────────────────────────
  document.querySelectorAll(".btn-ajax-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      const id = this.dataset.id;
      const row = document.querySelector(`tr[data-user-id="${id}"]`);
      const isNowActive = parseInt(this.dataset.active, 10);

      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

      ajaxPost("ajax_actions.php", { action: "toggle_user", id })
        .then((data) => {
          if (data.success) {
            const newActive = data.is_active;
            // Update button
            btn.dataset.active = newActive;
            btn.className = `btn btn-sm ${newActive ? "btn-outline-warning" : "btn-outline-success"} btn-ajax-toggle`;
            btn.innerHTML = `<i class="fas ${newActive ? "fa-pause" : "fa-play"}"></i>`;
            btn.title = newActive ? "غیرفعال کردن" : "فعال کردن";
            // Update status badge
            const statusCell = row?.querySelector(".status-cell");
            if (statusCell) {
              statusCell.innerHTML =
                newActive ?
                  '<span class="badge bg-success">فعال</span>'
                : '<span class="badge bg-secondary">غیرفعال</span>';
            }
            if (!data.router_ok) {
              showToast(
                "warning",
                `وضعیت در دیتابیس تغییر کرد اما روتر پاسخ نداد: ${data.router_msg}`,
              );
            }
          } else {
            showToast("danger", data.error ?? "خطای ناشناخته");
            btn.disabled = false;
            btn.innerHTML = `<i class="fas ${isNowActive ? "fa-pause" : "fa-play"}"></i>`;
          }
        })
        .catch(() => {
          showToast("danger", "خطا در ارتباط با سرور");
          btn.disabled = false;
          btn.innerHTML = `<i class="fas ${isNowActive ? "fa-pause" : "fa-play"}"></i>`;
        });
    });
  });

  // ── AJAX Delete ─────────────────────────────────────────────────
  document.querySelectorAll(".btn-ajax-delete").forEach(function (btn) {
    btn.addEventListener("click", function () {
      const id = this.dataset.id;
      const name = this.dataset.name;

      if (
        !confirm(
          `آیا از حذف کاربر «${name}» مطمئن هستید؟\nاین عمل قابل بازگشت نیست.`,
        )
      ) {
        return;
      }

      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

      ajaxPost("ajax_actions.php", { action: "delete_user", id })
        .then((data) => {
          if (data.success) {
            const row = document.querySelector(`tr[data-user-id="${id}"]`);
            if (row) {
              row.style.transition = "opacity .3s";
              row.style.opacity = "0";
              setTimeout(() => row.remove(), 300);
            }
            if (!data.router_ok) {
              showToast(
                "warning",
                `کاربر حذف شد اما روتر پاسخ نداد: ${data.router_msg}`,
              );
            }
          } else {
            showToast("danger", data.error ?? "خطا در حذف");
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash"></i>';
          }
        })
        .catch(() => {
          showToast("danger", "خطا در ارتباط با سرور");
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-trash"></i>';
        });
    });
  });
});

// ── Toast notification helper ────────────────────────────────────
function showToast(type, message) {
  let container = document.getElementById("toastContainer");
  if (!container) {
    container = document.createElement("div");
    container.id = "toastContainer";
    container.style.cssText =
      "position:fixed;top:1rem;left:50%;transform:translateX(-50%);z-index:9999;min-width:300px;max-width:500px";
    document.body.appendChild(container);
  }
  const colorMap = {
    success: "success",
    danger: "danger",
    warning: "warning",
    info: "info",
  };
  const bgClass = `bg-${colorMap[type] ?? "secondary"}`;
  const toast = document.createElement("div");
  toast.className = `alert alert-${type} alert-dismissible fade show shadow mb-2`;
  toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
  container.appendChild(toast);
  setTimeout(() => {
    try {
      bootstrap.Alert.getOrCreateInstance(toast).close();
    } catch (_) {
      toast.remove();
    }
  }, 5000);
}
