<?php
// Ensure database config and auth guard are active
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/pelanggaran_rules.php';

// Determine active page for sidebar active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$userRole = $_SESSION['user_role'] ?? 'pengurus_1';
$userNama = $_SESSION['user_nama'] ?? $_SESSION['username'] ?? 'User';

// Role Label Formatter
$roleLabel = 'Pengurus';
if ($userRole === 'admin') {
    $roleLabel = 'Admin Utama';
} elseif ($userRole === 'pengurus_1') {
    $roleLabel = 'Pengurus 1';
} elseif ($userRole === 'pengurus_2') {
    $roleLabel = 'Pengurus 2';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>SIMPONTREN Management</title>
  
  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <!-- Custom Modern CSS (Reference Style) with versioning to prevent browser cache -->
  <link href="<?= base_url('assets/css/style.css?v=' . time()) ?>" rel="stylesheet">
</head>
<body>

<div id="wrapper">
  
  <!-- Modern Left Sidebar -->
  <aside id="sidebar-wrapper">
    <!-- Brand Header -->
    <div class="brand-header">
      <div class="brand-icon">
        <i class="fas fa-cube"></i>
      </div>
      <div>
        <h5 class="brand-title">Management</h5>
        <div class="small text-muted" style="font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">SIMPONTREN</div>
      </div>
    </div>
    
    <!-- Sidebar Navigation -->
    <div class="sidebar-nav">
      <!-- 1. Dashboard -->
      <?php $isDashboard = ($current_page == 'index.php' && !in_array($current_dir, ['santri', 'perizinan', 'pelanggaran', 'spp', 'pengurus'])); ?>
      <a href="<?= base_url('index.php') ?>" class="nav-pill-item <?= $isDashboard ? 'active' : '' ?>">
        <div class="nav-label">
          <i class="fas fa-th-large"></i>
          <span>Dashboard</span>
        </div>
        <?php if ($isDashboard): ?>
          <span class="active-arrow"><i class="fas fa-arrow-right"></i></span>
        <?php endif; ?>
      </a>

      <!-- 2. Data Santri -->
      <?php if (has_permission('santri')): ?>
        <?php $isSantri = ($current_dir == 'santri'); ?>
        <a href="<?= base_url('modules/santri/index.php') ?>" class="nav-pill-item <?= $isSantri ? 'active' : '' ?>">
          <div class="nav-label">
            <i class="fas fa-user-graduate"></i>
            <span>Data Santri</span>
          </div>
          <?php if ($isSantri): ?>
            <span class="active-arrow"><i class="fas fa-arrow-right"></i></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>

      <!-- 3. Perizinan -->
      <?php if (has_permission('perizinan')): ?>
        <?php $isPerizinan = ($current_dir == 'perizinan'); ?>
        <a href="<?= base_url('modules/perizinan/index.php') ?>" class="nav-pill-item <?= $isPerizinan ? 'active' : '' ?>">
          <div class="nav-label">
            <i class="fas fa-calendar-check"></i>
            <span>Perizinan</span>
          </div>
          <?php if ($isPerizinan): ?>
            <span class="active-arrow"><i class="fas fa-arrow-right"></i></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>

      <!-- 4. Poin Pelanggaran -->
      <?php if (has_permission('pelanggaran')): ?>
        <?php $isPelanggaran = ($current_dir == 'pelanggaran'); ?>
        <a href="<?= base_url('modules/pelanggaran/index.php') ?>" class="nav-pill-item <?= $isPelanggaran ? 'active' : '' ?>">
          <div class="nav-label">
            <i class="fas fa-gavel"></i>
            <span>Pelanggaran</span>
          </div>
          <?php if ($isPelanggaran): ?>
            <span class="active-arrow"><i class="fas fa-arrow-right"></i></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>

      <!-- 5. Keuangan / SPP -->
      <?php if (has_permission('spp')): ?>
        <?php $isSpp = ($current_dir == 'spp'); ?>
        <a href="<?= base_url('modules/spp/index.php') ?>" class="nav-pill-item <?= $isSpp ? 'active' : '' ?>">
          <div class="nav-label">
            <i class="fas fa-wallet"></i>
            <span>Keuangan SPP</span>
          </div>
          <?php if ($isSpp): ?>
            <span class="active-arrow"><i class="fas fa-arrow-right"></i></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>

      <!-- 6. Menu Khusus Admin: Manajemen Pengurus -->
      <?php if ($userRole === 'admin'): ?>
        <?php $isPengurus = ($current_dir == 'pengurus'); ?>
        <a href="<?= base_url('modules/pengurus/index.php') ?>" class="nav-pill-item <?= $isPengurus ? 'active' : '' ?>">
          <div class="nav-label">
            <i class="fas fa-users-cog"></i>
            <span>Data Pengurus</span>
          </div>
          <?php if ($isPengurus): ?>
            <span class="active-arrow"><i class="fas fa-arrow-right"></i></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    </div>

    <!-- Promo Card (like rocket card in photo) -->
    <div class="sidebar-promo-card">
      <div class="promo-icon-box">
        <i class="fas fa-rocket"></i>
      </div>
      <h6>SIMPONTREN v2.0</h6>
      <p>Sistem terintegrasi santri, perizinan & kedisiplinan</p>
      <span class="sidebar-promo-btn"><?= htmlspecialchars($roleLabel) ?></span>
    </div>

    <!-- Logout Button -->
    <a href="<?= base_url('auth/logout.php') ?>" class="sidebar-logout" onclick="return confirm('Apakah Anda yakin ingin keluar?');">
      <div class="sidebar-logout-icon">
        <i class="fas fa-power-off"></i>
      </div>
      <span>Logout</span>
    </a>
  </aside>

  <!-- Page Content Wrapper -->
  <main id="page-content-wrapper">
    
    <!-- Top Navbar Soft Clean UI -->
    <header class="top-navbar-modern">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-light d-lg-none rounded-circle p-2" id="sidebarToggle" aria-label="Toggle Sidebar">
          <i class="fas fa-bars"></i>
        </button>
        
        <!-- Global Search Pill -->
        <div class="search-input-pill d-none d-md-block">
          <i class="fas fa-search search-icon"></i>
          <input type="text" id="globalAppSearch" placeholder="Search for Application here..." autocomplete="off">
        </div>
      </div>

      <div class="navbar-right-actions">
        <!-- Notification Bell -->
        <a href="<?= base_url('modules/perizinan/index.php') ?>" class="nav-icon-circle" title="Notifikasi Izin Aktif">
          <i class="far fa-bell"></i>
          <span class="dot-alert"></span>
        </a>

        <!-- User Profile Pill -->
        <div class="user-avatar-pill">
          <div class="user-avatar-img">
            <?= strtoupper(substr($userNama, 0, 1)) ?>
          </div>
          <div class="d-none d-sm-block text-start lh-1 me-1">
            <div class="fw-bold" style="font-size:0.82rem; color:#0f172a;"><?= htmlspecialchars($userNama) ?></div>
            <small class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($roleLabel) ?></small>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content Container -->
    <div class="px-3 px-md-4 py-2 pb-5">
      <?php display_flash_message(); ?>
