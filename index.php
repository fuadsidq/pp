<?php
$page_title = 'Dashboard Management';
require_once __DIR__ . '/includes/header.php';

// 1. Get Summary Data
// Total Santri & Aktif
$stmtSantri = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='Aktif' THEN 1 ELSE 0 END) AS aktif FROM santri");
$statSantri = $stmtSantri->fetch();
$totalSantri = (int) ($statSantri['total'] ?? 0);
$aktifSantri = (int) ($statSantri['aktif'] ?? 0);
$santriPct   = $totalSantri > 0 ? round(($aktifSantri / $totalSantri) * 100) : 0;

// Total Izin Aktif (Hanya yang sedang aktif)
$stmtIzin = $pdo->query("SELECT COUNT(*) AS total FROM perizinan WHERE tanggal_selesai >= CURDATE()");
$statIzin = $stmtIzin->fetch();
$totalIzinAktif = (int) ($statIzin['total'] ?? 0);
$izinPct = $totalSantri > 0 ? min(100, round(($totalIzinAktif / $totalSantri) * 100)) : 0;

// Total Pelanggaran & Poin
$stmtLanggar = $pdo->query("SELECT COUNT(*) AS total_kasus, COALESCE(SUM(poin), 0) AS total_poin FROM pelanggaran");
$statLanggar = $stmtLanggar->fetch();
$totalKasus = (int) ($statLanggar['total_kasus'] ?? 0);
$totalPoin  = (int) ($statLanggar['total_poin'] ?? 0);

// Total SPP Belum Lunas vs Lunas
$stmtSpp = $pdo->query("SELECT 
    COUNT(CASE WHEN status='Belum Lunas' THEN 1 END) AS total_unpaid,
    COALESCE(SUM(CASE WHEN status='Belum Lunas' THEN nominal ELSE 0 END), 0) AS total_nominal,
    COUNT(CASE WHEN status='Lunas' THEN 1 END) AS total_paid
    FROM tagihan_spp");
$statSpp = $stmtSpp->fetch();
$totalUnpaid = (int) ($statSpp['total_unpaid'] ?? 0);
$totalPaid   = (int) ($statSpp['total_paid'] ?? 0);
$totalAllSpp = $totalUnpaid + $totalPaid;
$sppPaidPct  = $totalAllSpp > 0 ? round(($totalPaid / $totalAllSpp) * 100) : 100;

// 2. Fetch Recent Data
// Perizinan Aktif (Hanya yang sedang aktif)
$stmtRecentIzin = $pdo->query("
    SELECT p.*, s.nama, s.nis, s.kamar 
    FROM perizinan p 
    JOIN santri s ON p.santri_id = s.id 
    WHERE p.tanggal_selesai >= CURDATE()
    ORDER BY p.tanggal ASC, p.id DESC 
    LIMIT 4
");
$recentIzin = $stmtRecentIzin->fetchAll();

// Top Pelanggaran Santri
$stmtTopLanggar = $pdo->query("
    SELECT s.nama, s.nis, s.kamar, SUM(p.poin) as total_poin, COUNT(p.id) as total_kasus
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    GROUP BY s.id
    ORDER BY total_poin DESC
    LIMIT 4
");
$topLanggar = $stmtTopLanggar->fetchAll();

// Recent Activities (Mix of recent izin & recent pelanggaran)
$stmtActivity = $pdo->query("
    (SELECT 'izin' as tipe, p.created_at, s.nama, p.jenis_izin as detail, 'Sedang Izin' as info
     FROM perizinan p JOIN santri s ON p.santri_id = s.id)
    UNION ALL
    (SELECT 'pelanggaran' as tipe, pl.created_at, s.nama, pl.jenis_pelanggaran as detail, CONCAT('+', pl.poin, ' Poin') as info
     FROM pelanggaran pl JOIN santri s ON pl.santri_id = s.id)
    ORDER BY created_at DESC
    LIMIT 5
");
$activities = $stmtActivity->fetchAll();

// Current User Greeting
$userName = htmlspecialchars($_SESSION['user_nama'] ?? 'Admin');
?>

<!-- Top Page Title & Breadcrumb header like reference -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="fw-bold text-dark mb-0" style="letter-spacing:-0.5px;">Dashboard</h3>
    <p class="text-muted small mb-0">Hi, welcome to SIMPONTREN task management dashboard</p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <div class="dropdown">
      <button class="btn btn-white bg-white border rounded-pill px-3 py-1.5 small fw-semibold text-dark dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown">
        <i class="fas fa-layer-group text-primary me-1"></i> SIMPONTREN
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4">
        <li><a class="dropdown-item small" href="<?= base_url('modules/santri/index.php') ?>">Data Santri</a></li>
        <li><a class="dropdown-item small" href="<?= base_url('modules/perizinan/index.php') ?>">Perizinan</a></li>
        <li><a class="dropdown-item small" href="<?= base_url('modules/pelanggaran/index.php') ?>">Poin Pelanggaran</a></li>
      </ul>
    </div>
    <a href="<?= base_url('modules/perizinan/create.php') ?>" class="btn btn-white bg-white border rounded-circle shadow-sm" style="width:38px; height:38px; display:flex; align-items:center; justify-content:center; color:#1e293b;" title="Catat Baru">
      <i class="fas fa-plus"></i>
    </a>
  </div>
</div>

<!-- 3-Column Layout: Main Content (8 cols) & Side Widgets (4 cols) -->
<div class="row g-4">
  
  <!-- Left / Center Main Area (8 cols) -->
  <div class="col-12 col-xl-8">
    
    <!-- 1. Modern Welcome Banner (like photo) -->
    <div class="welcome-banner-modern mb-4">
      <div style="z-index:2;">
        <span class="small fw-semibold text-muted text-uppercase" style="letter-spacing:0.5px; color:#234a40 !important;">Hi, <?= $userName ?></span>
        <h2>Welcome to Management</h2>
        <p>Aktivitas santri, perizinan keluar, dan kedisiplinan tercatat secara realtime di sini. Pantau izin aktif dan poin santri setiap saat.</p>
      </div>

      <div class="d-none d-md-flex welcome-banner-illus" style="z-index:2;">
        <div class="banner-calendar-tag">
          <i class="fas fa-calendar-alt fa-2x text-emerald" style="color:#1b4d3e;"></i>
          <div>
            <div class="fw-bold small text-dark"><?= date('d M Y') ?></div>
            <div class="text-muted" style="font-size:0.72rem;"><?= date('l') ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. Statistics Row with Circular Progress (like "Project Statistics" in photo) -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold text-dark mb-0" style="letter-spacing:-0.3px;">Ringkasan Operasional Pesantren</h5>
      <a href="<?= base_url('modules/santri/index.php') ?>" class="text-primary text-decoration-none small fw-bold">View All</a>
    </div>

    <div class="mb-4">
      <!-- Item 1: Santri Aktif -->
      <div class="stat-progress-item">
        <div class="d-flex align-items-center gap-3">
          <div class="circle-progress-wrapper">
            <svg width="50" height="50" viewBox="0 0 36 36">
              <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
              <path class="circle-bar" stroke="#2563eb" stroke-dasharray="<?= $santriPct ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            </svg>
            <span class="circle-progress-text"><?= $santriPct ?>%</span>
          </div>
          <div>
            <h6 class="fw-bold text-dark mb-0">Total Santri Terdaftar</h6>
            <small class="text-muted"><?= $aktifSantri ?> Santri Aktif dari total <?= $totalSantri ?> santri</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="avatar-group-pills d-none d-sm-flex">
            <span class="avatar-pill-circle blue">S1</span>
            <span class="avatar-pill-circle teal">S2</span>
            <span class="avatar-pill-circle orange">S3</span>
            <span class="avatar-pill-circle indigo">+<?= max(0, $totalSantri - 3) ?></span>
          </div>
          <span class="badge-days-left"><i class="fas fa-check me-1 text-success"></i> Aktif</span>
        </div>
      </div>

      <!-- Item 2: Perizinan Aktif -->
      <div class="stat-progress-item">
        <div class="d-flex align-items-center gap-3">
          <div class="circle-progress-wrapper">
            <svg width="50" height="50" viewBox="0 0 36 36">
              <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
              <path class="circle-bar" stroke="#10b981" stroke-dasharray="<?= $izinPct ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            </svg>
            <span class="circle-progress-text"><?= $izinPct ?>%</span>
          </div>
          <div>
            <h6 class="fw-bold text-dark mb-0">Perizinan Aktif (Sedang Izin)</h6>
            <small class="text-muted"><?= $totalIzinAktif ?> santri di luar pondok (hari ini &le; tgl selesai)</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="avatar-group-pills d-none d-sm-flex">
            <span class="avatar-pill-circle teal"><i class="fas fa-clock" style="font-size:0.6rem;"></i></span>
            <span class="avatar-pill-circle blue"><i class="fas fa-home" style="font-size:0.6rem;"></i></span>
          </div>
          <span class="badge-days-left bg-emerald text-white" style="background:#10b981; color:#fff;"><?= $totalIzinAktif ?> Izin Berjalan</span>
        </div>
      </div>

      <!-- Item 3: Pelanggaran & Kedisiplinan -->
      <div class="stat-progress-item">
        <div class="d-flex align-items-center gap-3">
          <div class="circle-progress-wrapper">
            <svg width="50" height="50" viewBox="0 0 36 36">
              <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
              <path class="circle-bar" stroke="#f59e0b" stroke-dasharray="<?= min(100, $totalKasus * 10) ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            </svg>
            <span class="circle-progress-text"><?= min(100, $totalKasus * 10) ?>%</span>
          </div>
          <div>
            <h6 class="fw-bold text-dark mb-0">Total Pelanggaran Santri</h6>
            <small class="text-muted"><?= $totalKasus ?> kasus tercatat | <?= $totalPoin ?> total akumulasi poin</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge-days-left bg-warning bg-opacity-25 text-dark fw-bold"><?= $totalPoin ?> Poin</span>
        </div>
      </div>
    </div>

    <!-- 3. Two Mini Cards Row: Izin Aktif Table & Event Quick Info -->
    <div class="row g-3 mb-4">
      
      <!-- Table: Santri Sedang Izin Aktif -->
      <div class="col-12 col-md-8">
        <div class="card-soft p-4 h-100">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <h6 class="fw-bold text-dark mb-0"><i class="fas fa-user-clock text-primary me-2"></i> Santri Sedang Izin Aktif</h6>
              <small class="text-muted">Hanya yang belum melewati batas tanggal selesai</small>
            </div>
            <a href="<?= base_url('modules/perizinan/index.php') ?>" class="text-primary text-decoration-none small fw-bold">Detail</a>
          </div>

          <?php if (count($recentIzin) > 0): ?>
            <div class="table-responsive">
              <table class="table-modern">
                <thead>
                  <tr>
                    <th>Santri</th>
                    <th>Jenis</th>
                    <th>Periode Izin</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentIzin as $iz): ?>
                    <tr>
                      <td>
                        <strong class="d-block text-dark"><?= htmlspecialchars($iz['nama']) ?></strong>
                        <small class="text-muted">Kamar: <?= htmlspecialchars($iz['kamar']) ?></small>
                      </td>
                      <td>
                        <?php 
                          $bClass = 'badge-soft-blue';
                          if ($iz['jenis_izin'] === 'Sakit') $bClass = 'badge-soft-amber';
                          if ($iz['jenis_izin'] === 'Pulang') $bClass = 'badge-soft-emerald';
                        ?>
                        <span class="<?= $bClass ?>"><?= htmlspecialchars($iz['jenis_izin']) ?></span>
                      </td>
                      <td class="small">
                        <div class="fw-semibold text-dark"><?= date('d/m', strtotime($iz['tanggal'])) ?> - <?= date('d/m/Y', strtotime($iz['tanggal_selesai'])) ?></div>
                      </td>
                      <td>
                        <span class="badge-soft-emerald small" style="font-size:0.75rem;">Sedang Izin</span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="text-center py-4 text-muted">
              <i class="fas fa-check-circle fa-2x mb-2 d-block text-success opacity-75"></i>
              <div class="small fw-semibold">Tidak ada santri yang sedang izin aktif saat ini</div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Action / Pengurus Info Tile -->
      <div class="col-12 col-md-4">
        <div class="card-soft p-4 h-100 d-flex flex-column justify-content-between text-center">
          <div>
            <div class="d-flex align-items-center justify-content-between mb-3">
              <h6 class="fw-bold text-dark mb-0">Petugas Jaga</h6>
              <span class="badge bg-light text-dark rounded-pill border small">Hari ini</span>
            </div>
            <div class="tile-avatar mx-auto mb-2" style="width:52px; height:52px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#475569;">
              <i class="fas fa-user-shield"></i>
            </div>
            <h6 class="fw-bold text-dark mb-0"><?= $userName ?></h6>
            <small class="text-muted d-block mb-3"><?= htmlspecialchars($_SESSION['user_role'] ?? 'Pengurus') ?></small>
          </div>

          <a href="<?= base_url('modules/perizinan/create.php') ?>" class="btn btn-dark-pill w-100 py-2 small">
            <i class="fas fa-plus me-1"></i> Catat Perizinan
          </a>
        </div>
      </div>

    </div>

  </div>

  <!-- Right Side Column: Calendar & Activity Feed (4 cols) -->
  <div class="col-12 col-xl-4">
    
    <!-- 1. Side Calendar Widget (Identical to screenshot) -->
    <div class="calendar-widget mb-4">
      <div class="calendar-widget-header">
        <h6 class="fw-bold text-dark mb-0">Calender</h6>
        <span class="text-muted small fw-semibold"><i class="fas fa-chevron-down"></i></span>
      </div>

      <div class="d-flex align-items-center justify-content-between mb-3">
        <span class="fw-bold text-dark small"><?= date('F Y') ?></span>
        <div class="d-flex gap-2">
          <span class="text-muted small cursor-pointer"><i class="fas fa-chevron-left"></i></span>
          <span class="text-muted small cursor-pointer"><i class="fas fa-chevron-right"></i></span>
        </div>
      </div>

      <!-- Calendar Days -->
      <div class="calendar-days-grid">
        <div class="calendar-day-label">Sun</div>
        <div class="calendar-day-label">Mon</div>
        <div class="calendar-day-label">Tue</div>
        <div class="calendar-day-label">Wed</div>
        <div class="calendar-day-label">Thu</div>
        <div class="calendar-day-label">Fri</div>
        <div class="calendar-day-label">Sat</div>

        <?php
          $firstDayOfMonth = date('w', strtotime(date('Y-m-01')));
          $daysInMonth     = date('t');
          $todayDay        = (int) date('j');

          for ($i = 0; $i < $firstDayOfMonth; $i++) {
              echo '<div class="calendar-day-cell empty"></div>';
          }

          for ($day = 1; $day <= $daysInMonth; $day++) {
              $isToday = ($day === $todayDay);
              $isHighlight = ($day === 14 || $day === 17); // visual highlight like screenshot
              $classes = 'calendar-day-cell';
              if ($isToday) $classes .= ' today';
              elseif ($isHighlight) $classes .= ' highlight';
              echo "<div class='{$classes}'>{$day}</div>";
          }
        ?>
      </div>
    </div>

    <!-- 2. Activity Feed (like screenshot) -->
    <div class="card-soft p-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold text-dark mb-0">Activity</h6>
        <a href="<?= base_url('modules/pelanggaran/index.php') ?>" class="text-primary text-decoration-none small fw-bold">View All</a>
      </div>

      <?php if (count($activities) > 0): ?>
        <?php foreach ($activities as $idx => $act): ?>
          <?php
            $bgColors = ['#1e293b', '#6b21a8', '#0284c7', '#0f766e', '#be123c'];
            $color = $bgColors[$idx % count($bgColors)];
            $initials = strtoupper(substr($act['nama'], 0, 2));
          ?>
          <div class="activity-card-item">
            <div class="activity-avatar" style="background: <?= $color ?>;">
              <?= $initials ?>
            </div>
            <div class="activity-text w-100">
              <h6><?= htmlspecialchars($act['nama']) ?></h6>
              <div class="d-flex align-items-center justify-content-between">
                <small><?= htmlspecialchars($act['detail']) ?></small>
                <small class="fw-semibold text-dark"><?= htmlspecialchars($act['info']) ?></small>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="text-center py-3 text-muted small">
          Belum ada aktivitas terbaru.
        </div>
      <?php endif; ?>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
