// ====================================================
// WireGuard Panel — Main JS
// ====================================================

document.addEventListener("DOMContentLoaded", function () {
  // --- Sidebar mobile toggle ---
  const toggleBtn = document.getElementById("sidebarToggle");
  const sidebar = document.querySelector(".sidebar");

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", function () {
      sidebar.classList.toggle("open");
    });

    // Close sidebar when clicking outside on mobile
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

  // --- Delete confirmation ---
  document.querySelectorAll(".confirm-delete").forEach(function (link) {
    link.addEventListener("click", function (e) {
      if (
        !confirm("آیا از حذف این مورد مطمئن هستید؟\nاین عمل قابل بازگشت نیست.")
      ) {
        e.preventDefault();
      }
    });
  });

  // --- Auto-dismiss flash alerts ---
  setTimeout(function () {
    document.querySelectorAll(".alert.alert-success").forEach(function (el) {
      var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    });
  }, 4000);
});
