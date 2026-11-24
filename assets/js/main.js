/**
 * Main JavaScript for SIMPONTREN
 */

document.addEventListener('DOMContentLoaded', function () {
  // Toggle Sidebar
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarWrapper = document.getElementById('sidebar-wrapper');

  if (sidebarToggle && sidebarWrapper) {
    sidebarToggle.addEventListener('click', function (e) {
      e.preventDefault();
      sidebarWrapper.classList.toggle('show');
    });
  }

  // Auto dismiss alert after 5 seconds
  const alerts = document.querySelectorAll('.alert-dismissible');
  alerts.forEach(function (alert) {
    setTimeout(function () {
      const bsAlert = new bootstrap.Alert(alert);
      bsAlert.close();
    }, 5000);
  });

  // Global Delete Confirmation
  const confirmDeleteBtns = document.querySelectorAll('.btn-confirm-delete');
  confirmDeleteBtns.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      const message = this.getAttribute('data-confirm') || 'Apakah Anda yakin ingin menghapus data ini?';
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });
});
