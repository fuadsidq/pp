<?php
$page_title = 'Poin Pelanggaran & Tata Tertib Santri';
require_once __DIR__ . '/../../includes/header.php';
require_permission('pelanggaran');

// Handle POST: Update Batas Poin Rule-Based (Khusus Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_thresholds') {
    require_admin();
    $ringan = intval($_POST['poin_ringan'] ?? 15);
    $sedang = intval($_POST['poin_sedang'] ?? 25);
    $berat  = intval($_POST['poin_berat'] ?? 50);

    if ($ringan <= 0 || $sedang <= $ringan || $berat <= $sedang) {
        set_flash_message('danger', 'Urutan poin tidak valid! Pastikan: Poin Ringan < Poin Sedang < Poin Berat.');
    } else {
        update_rule_thresholds($pdo, $ringan, $sedang, $berat);
        set_flash_message('success', "Batas poin sanksi berhasil diperbarui! (Ringan: ≥{$ringan}, Sedang: ≥{$sedang}, Berat: ≥{$berat})");
    }
    header('Location: ' . base_url('modules/pelanggaran/index.php?tab=summary'));
    exit;
}

// Handle POST: Reset Poin / Pemutihan Santri (Khusus Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_poin') {
    require_admin();
    $konfirmasi = trim($_POST['konfirmasi'] ?? '');
    $bulan_reset = trim($_POST['bulan_reset'] ?? 'semua');

    if ($konfirmasi !== 'RESET POIN') {
        set_flash_message('danger', 'Konfirmasi gagal! Anda harus mengetik kata "RESET POIN" dengan tepat.');
    } else {
        if ($bulan_reset === 'semua') {
            $stmtReset = $pdo->query("DELETE FROM pelanggaran");
            set_flash_message('success', 'Pemutihan berhasil! Seluruh riwayat poin pelanggaran santri telah di-reset ke 0.');
        } else {
            // Reset per bulan tertentu
            $stmtReset = $pdo->prepare("DELETE FROM pelanggaran WHERE DATE_FORMAT(tanggal, '%Y-%m') = :bln");
            $stmtReset->execute(['bln' => $bulan_reset]);
            set_flash_message('success', "Data pelanggaran untuk periode bulan {$bulan_reset} berhasil di-reset.");
        }
    }
    header('Location: ' . base_url('modules/pelanggaran/index.php?tab=' . ($_POST['current_tab'] ?? 'summary')));
    exit;
}

// Ambil batas poin saat ini
$thresholds = get_rule_thresholds($pdo);
$tR = $thresholds['ringan'];
$tS = $thresholds['sedang'];
$tB = $thresholds['berat'];

// Filter & search params
$search      = trim($_GET['search'] ?? '');
$rule_filter = trim($_GET['rule'] ?? 'all');
$active_tab  = trim($_GET['tab'] ?? 'monthly'); // Default tab ke rekap bulanan

// Filter Bulan & Tahun untuk Rekap Bulanan
$current_month = date('m');
$current_year  = date('Y');
$selected_month = trim($_GET['bulan'] ?? $current_month);
$selected_year  = intval($_GET['tahun'] ?? $current_year);
$period_ym      = sprintf('%04d-%02d', $selected_year, $selected_month);

$nama_bulan_indo = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
    '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// =========================================================================
// 1. REKAP BULANAN: Ambil data pelanggaran KHUSUS bulan & tahun terpilih
// =========================================================================
$stmtMonthly = $pdo->prepare("
    SELECT s.id, s.nama, s.nis, s.kamar, 
           SUM(p.poin) as poin_bulan_ini, 
           COUNT(p.id) as kasus_bulan_ini
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    WHERE DATE_FORMAT(p.tanggal, '%Y-%m') = :periode
    GROUP BY s.id
    ORDER BY poin_bulan_ini DESC, kasus_bulan_ini DESC
");
$stmtMonthly->execute(['periode' => $period_ym]);
$raw_monthly = $stmtMonthly->fetchAll();

$monthly_list = [];
$monthly_stats = [
    'total_kasus' => 0,
    'total_poin'  => 0,
    'santri_count'=> count($raw_monthly),
    'ringan'      => 0,
    'sedang'      => 0,
    'berat'       => 0
];

foreach ($raw_monthly as $m) {
    $poin = intval($m['poin_bulan_ini']);
    $eval = evaluate_rule_based($poin, $pdo);
    $m['eval'] = $eval;
    $monthly_stats['total_kasus'] += intval($m['kasus_bulan_ini']);
    $monthly_stats['total_poin']  += $poin;

    if ($eval['id'] === 'R4') $monthly_stats['berat']++;
    elseif ($eval['id'] === 'R3') $monthly_stats['sedang']++;
    elseif ($eval['id'] === 'R2') $monthly_stats['ringan']++;

    $monthly_list[] = $m;
}

// =========================================================================
// 2. AKUMULASI TOTAL: Ambil akumulasi poin per santri (sepanjang masa)
// =========================================================================
$stmtSummary = $pdo->query("
    SELECT s.id, s.nama, s.nis, s.kamar, SUM(p.poin) as total_poin, COUNT(p.id) as total_kasus
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    GROUP BY s.id
    ORDER BY total_poin DESC, total_kasus DESC
");
$raw_summary = $stmtSummary->fetchAll();

$summary_list = [];
$counts = [
    'all'    => count($raw_summary),
    'aman'   => 0,
    'ringan' => 0,
    'sedang' => 0,
    'berat'  => 0
];

foreach ($raw_summary as $item) {
    $poin = intval($item['total_poin']);
    $eval = evaluate_rule_based($poin, $pdo);
    $item['eval'] = $eval;

    if ($eval['id'] === 'R4') {
        $counts['berat']++;
    } elseif ($eval['id'] === 'R3') {
        $counts['sedang']++;
    } elseif ($eval['id'] === 'R2') {
        $counts['ringan']++;
    } else {
        $counts['aman']++;
    }

    if ($rule_filter === 'all' || 
       ($rule_filter === 'berat'  && $eval['id'] === 'R4') ||
       ($rule_filter === 'sedang' && $eval['id'] === 'R3') ||
       ($rule_filter === 'ringan' && $eval['id'] === 'R2') ||
       ($rule_filter === 'aman'   && $eval['id'] === 'R1')) {
        $summary_list[] = $item;
    }
}

// =========================================================================
// 3. RIWAYAT LOG PELANGGARAN
// =========================================================================
$sqlHistory = "SELECT p.*, s.nama, s.nis, s.kamar 
               FROM pelanggaran p 
               JOIN santri s ON p.santri_id = s.id 
               WHERE 1=1";
$paramsHistory = [];

if (!empty($search)) {
    $sqlHistory .= " AND (s.nama LIKE :search1 OR s.nis LIKE :search2 OR p.jenis_pelanggaran LIKE :search3)";
    $paramsHistory['search1'] = "%{$search}%";
    $paramsHistory['search2'] = "%{$search}%";
    $paramsHistory['search3'] = "%{$search}%";
}

$sqlHistory .= " ORDER BY p.tanggal DESC, p.id DESC";
$stmtHistory = $pdo->prepare($sqlHistory);
$stmtHistory->execute($paramsHistory);
$pelanggaran_list = $stmtHistory->fetchAll();
?>

<!-- Header Section: Clean & Modern -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
  <div>
    <h3 class="fw-bold text-dark mb-1">
      <i class="fas fa-gavel text-warning me-2"></i> Poin Pelanggaran & Tata Tertib
    </h3>
    <p class="text-muted small mb-0">
      Pemantauan kedisiplinan santri, rekapitulasi poin bulanan, dan evaluasi sanksi bertingkat.
    </p>
  </div>
  <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
    <!-- Tombol Khusus Admin: Atur Poin & Reset Poin -->
    <?php if ($_SESSION['user_role'] === 'admin'): ?>
      <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalResetPoin">
        <i class="fas fa-undo-alt me-1"></i> Reset / Pemutihan Poin
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalPengaturanPoin">
        <i class="fas fa-sliders-h me-1 text-primary"></i> Atur Batas Poin
      </button>
    <?php endif; ?>

    <!-- Tombol Catat Pelanggaran -->
    <a href="<?= base_url('modules/pelanggaran/create.php') ?>" class="btn btn-dark-pill px-4 shadow-sm fw-semibold">
      <i class="fas fa-plus-circle me-1.5"></i> Catat Pelanggaran
    </a>
  </div>
</div>

<!-- Nav Tabs: 3 Sudut Pandang -->
<ul class="nav nav-pills mb-4 gap-2" id="violationTab" role="tablist">
  <!-- Tab 1: Rekap Bulanan -->
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $active_tab === 'monthly' ? 'active' : '' ?> rounded-pill px-4 fw-semibold" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly-pane" type="button" role="tab">
      <i class="fas fa-calendar-alt me-1.5"></i> Rekap Bulanan (<?= $nama_bulan_indo[$selected_month] ?> <?= $selected_year ?>)
    </button>
  </li>

  <!-- Tab 2: Akumulasi Total -->
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $active_tab === 'summary' ? 'active' : '' ?> rounded-pill px-4 fw-semibold" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary-pane" type="button" role="tab">
      <i class="fas fa-users me-1.5"></i> Akumulasi Total (<?= count($raw_summary) ?>)
    </button>
  </li>

  <!-- Tab 3: Riwayat Log -->
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $active_tab === 'history' ? 'active' : '' ?> rounded-pill px-4 fw-semibold" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab">
      <i class="fas fa-history me-1.5"></i> Riwayat Semua Pelanggaran (<?= count($pelanggaran_list) ?>)
    </button>
  </li>
</ul>

<div class="tab-content" id="violationTabContent">

  <!-- ========================================================================= -->
  <!-- TAB 1: REKAP BULANAN SANTRI                                               -->
  <!-- ========================================================================= -->
  <div class="tab-pane fade <?= $active_tab === 'monthly' ? 'show active' : '' ?>" id="monthly-pane" role="tabpanel">
    
    <!-- Filter Bar Periode Bulan & Tahun -->
    <div class="card-soft p-3 mb-4">
      <form action="" method="GET" class="row g-3 align-items-center">
        <input type="hidden" name="tab" value="monthly">
        
        <div class="col-12 col-md-4">
          <label class="form-label small text-muted fw-bold mb-1"><i class="fas fa-calendar-day me-1 text-primary"></i> Pilih Bulan Rekap</label>
          <select name="bulan" class="form-select rounded-pill">
            <?php foreach ($nama_bulan_indo as $code => $name): ?>
              <option value="<?= $code ?>" <?= $code === $selected_month ? 'selected' : '' ?>><?= $name ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label small text-muted fw-bold mb-1"><i class="fas fa-calendar me-1 text-primary"></i> Pilih Tahun</label>
          <select name="tahun" class="form-select rounded-pill">
            <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
              <option value="<?= $y ?>" <?= $y === $selected_year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="col-12 col-md-5 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-dark-pill px-4 flex-grow-1">
            <i class="fas fa-filter me-1"></i> Tampilkan Rekap
          </button>
          <button type="button" class="btn btn-outline-secondary rounded-pill px-3" onclick="window.print();" title="Cetak / Simpan PDF">
            <i class="fas fa-print me-1"></i> Cetak
          </button>
        </div>
      </form>
    </div>

    <!-- Mini Summary Banner Bulan Ini -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-soft p-3 d-flex align-items-center gap-3">
          <div class="activity-avatar bg-warning text-dark" style="width:46px; height:46px; border-radius:14px; font-size:1.1rem;">
            <i class="fas fa-user-times"></i>
          </div>
          <div>
            <span class="text-muted small fw-semibold text-uppercase">Santri Melanggar</span>
            <h4 class="fw-bold mb-0 text-dark"><?= $monthly_stats['santri_count'] ?> <span class="fs-6 fw-normal text-muted">Orang</span></h4>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-soft p-3 d-flex align-items-center gap-3">
          <div class="activity-avatar bg-danger text-white" style="width:46px; height:46px; border-radius:14px; font-size:1.1rem;">
            <i class="fas fa-fire"></i>
          </div>
          <div>
            <span class="text-muted small fw-semibold text-uppercase">Total Kasus</span>
            <h4 class="fw-bold mb-0 text-dark"><?= $monthly_stats['total_kasus'] ?> <span class="fs-6 fw-normal text-muted">Kasus</span></h4>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-soft p-3 d-flex align-items-center gap-3">
          <div class="activity-avatar bg-dark text-white" style="width:46px; height:46px; border-radius:14px; font-size:1.1rem;">
            <i class="fas fa-calculator"></i>
          </div>
          <div>
            <span class="text-muted small fw-semibold text-uppercase">Total Poin Masuk</span>
            <h4 class="fw-bold mb-0 text-dark"><?= $monthly_stats['total_poin'] ?> <span class="fs-6 fw-normal text-muted">Poin</span></h4>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-soft p-3 d-flex align-items-center gap-3">
          <div class="activity-avatar bg-info text-white" style="width:46px; height:46px; border-radius:14px; font-size:1.1rem;">
            <i class="fas fa-exclamation-circle"></i>
          </div>
          <div>
            <span class="text-muted small fw-semibold text-uppercase">Sanksi Berat/Sedang</span>
            <h4 class="fw-bold mb-0 text-dark"><?= ($monthly_stats['berat'] + $monthly_stats['sedang']) ?> <span class="fs-6 fw-normal text-muted">Santri</span></h4>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabel Rekapitulasi Pelanggaran Bulan Terpilih -->
    <div class="card-soft p-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h5 class="fw-bold text-dark mb-0">
            Daftar Pelanggar Terbanyak &mdash; <?= $nama_bulan_indo[$selected_month] ?> <?= $selected_year ?>
          </h5>
          <small class="text-muted">Diurutkan dari santri dengan perolehan poin terbanyak pada bulan ini.</small>
        </div>
        <span class="badge bg-light text-dark px-3 py-1.5 rounded-pill border fw-semibold">
          <?= count($monthly_list) ?> Santri Terdata
        </span>
      </div>

      <?php if (count($monthly_list) > 0): ?>
        <div class="table-responsive">
          <table class="table-modern">
            <thead>
              <tr>
                <th class="ps-3" style="width: 50px;">Ranking</th>
                <th>Nama Santri</th>
                <th>Kamar</th>
                <th>Kasus Bulan Ini</th>
                <th>Poin Bulan Ini</th>
                <th>Status Sanksi</th>
                <th>Rekomendasi Tindakan</th>
                <th class="text-end pe-3">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($monthly_list as $rank => $row): ?>
                <?php 
                  $eval = $row['eval'];
                  $poin = intval($row['poin_bulan_ini']);
                  $isTop3 = ($rank < 3);
                ?>
                <tr>
                  <td class="ps-3">
                    <?php if ($rank === 0): ?>
                      <span class="badge bg-danger rounded-circle p-2 text-white" style="width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center;">1</span>
                    <?php elseif ($rank === 1): ?>
                      <span class="badge bg-warning text-dark rounded-circle p-2" style="width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center;">2</span>
                    <?php elseif ($rank === 2): ?>
                      <span class="badge bg-secondary rounded-circle p-2 text-white" style="width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center;">3</span>
                    <?php else: ?>
                      <span class="text-muted fw-bold ps-2"><?= $rank + 1 ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <strong class="d-block text-dark"><?= htmlspecialchars($row['nama']) ?></strong>
                    <small class="text-muted">NIS: <?= htmlspecialchars($row['nis']) ?></small>
                  </td>
                  <td>
                    <span class="small text-dark"><i class="fas fa-bed text-muted me-1"></i> <?= htmlspecialchars($row['kamar']) ?></span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-semibold">
                      <?= $row['kasus_bulan_ini'] ?> Kasus
                    </span>
                  </td>
                  <td>
                    <span class="fs-5 fw-bold <?= $isTop3 ? 'text-danger' : 'text-dark' ?>"><?= $poin ?></span>
                    <small class="text-muted">Poin</small>
                  </td>
                  <td>
                    <span class="badge <?= $eval['badge_soft'] ?> fs-6 px-2.5 py-1 rounded-pill">
                      <i class="fas <?= $eval['icon'] ?> me-1"></i> <?= $eval['kategori'] ?>
                    </span>
                    <span class="badge <?= $eval['sp_badge'] ?> ms-1"><?= $eval['sp'] ?></span>
                  </td>
                  <td class="small text-muted" style="max-width: 250px;">
                    <?= htmlspecialchars($eval['sanksi_singkat']) ?>
                  </td>
                  <td class="text-end pe-3">
                    <a href="<?= base_url('modules/pelanggaran/create.php?santri_id=' . $row['id']) ?>" class="btn btn-sm btn-outline-warning rounded-pill px-3" title="Tambah Pelanggaran">
                      <i class="fas fa-plus me-1"></i> Tambah
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-smile-beam fa-3x mb-3 text-success d-block opacity-75"></i>
          <h6 class="fw-bold text-dark">Alhamdulillah, tidak ada pelanggaran!</h6>
          <p class="small text-muted mb-0">Pada periode <?= $nama_bulan_indo[$selected_month] ?> <?= $selected_year ?>, tidak ada santri yang tercatat melakukan pelanggaran.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- ========================================================================= -->
  <!-- TAB 2: AKUMULASI TOTAL (SEPANJANG MASA)                                   -->
  <!-- ========================================================================= -->
  <div class="tab-pane fade <?= $active_tab === 'summary' ? 'show active' : '' ?>" id="summary-pane" role="tabpanel">
    
    <!-- Filter Pills Aturan -->
    <div class="card-soft p-3 mb-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="small text-muted">
          <i class="fas fa-filter text-primary me-1"></i> Filter Tingkat Sanksi:
        </div>
        <div class="d-flex flex-wrap gap-1.5">
          <a href="?tab=summary&rule=all" class="btn btn-sm rounded-pill <?= $rule_filter === 'all' ? 'btn-dark' : 'btn-light border' ?>">
            Semua (<?= $counts['all'] ?>)
          </a>
          <a href="?tab=summary&rule=aman" class="btn btn-sm rounded-pill <?= $rule_filter === 'aman' ? 'btn-success text-white' : 'btn-light border text-success' ?>">
            <i class="fas fa-shield-alt me-1"></i> Aman (<?= $counts['aman'] ?>)
          </a>
          <a href="?tab=summary&rule=ringan" class="btn btn-sm rounded-pill <?= $rule_filter === 'ringan' ? 'btn-info text-white' : 'btn-light border text-info' ?>">
            <i class="fas fa-info-circle me-1"></i> Ringan (<?= $counts['ringan'] ?>)
          </a>
          <a href="?tab=summary&rule=sedang" class="btn btn-sm rounded-pill <?= $rule_filter === 'sedang' ? 'btn-warning text-dark' : 'btn-light border text-warning' ?>">
            <i class="fas fa-exclamation-triangle me-1"></i> Sedang (<?= $counts['sedang'] ?>)
          </a>
          <a href="?tab=summary&rule=berat" class="btn btn-sm rounded-pill <?= $rule_filter === 'berat' ? 'btn-danger text-white' : 'btn-light border text-danger' ?>">
            <i class="fas fa-radiation me-1"></i> Berat (<?= $counts['berat'] ?>)
          </a>
        </div>
      </div>
    </div>

    <!-- Tabel Akumulasi Total -->
    <div class="card-soft p-4">
      <?php if (count($summary_list) > 0): ?>
        <div class="table-responsive">
          <table class="table-modern">
            <thead>
              <tr>
                <th class="ps-3" style="width: 60px;">No</th>
                <th>Nama Santri</th>
                <th>Kamar</th>
                <th>Total Poin Akumulasi</th>
                <th>Status Sanksi</th>
                <th>Konsekuensi / Tindakan</th>
                <th class="text-end pe-3">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($summary_list as $idx => $sum): ?>
                <?php 
                  $eval = $sum['eval'];
                  $poin = intval($sum['total_poin']);
                ?>
                <tr>
                  <td class="ps-3 text-muted fw-semibold"><?= $idx + 1 ?></td>
                  <td>
                    <strong class="text-dark d-block"><?= htmlspecialchars($sum['nama']) ?></strong>
                    <small class="text-muted">NIS: <?= htmlspecialchars($sum['nis']) ?></small>
                  </td>
                  <td>
                    <span class="small text-dark"><i class="fas fa-bed text-muted me-1"></i> <?= htmlspecialchars($sum['kamar']) ?></span>
                  </td>
                  <td>
                    <span class="fs-5 fw-bold text-dark"><?= $poin ?></span>
                    <small class="text-muted">Poin (<?= $sum['total_kasus'] ?>x)</small>
                  </td>
                  <td>
                    <span class="badge <?= $eval['badge_soft'] ?> fs-6 px-2.5 py-1 rounded-pill">
                      <i class="fas <?= $eval['icon'] ?> me-1"></i> <?= $eval['kategori'] ?>
                    </span>
                    <span class="badge <?= $eval['sp_badge'] ?> ms-1"><?= $eval['sp'] ?></span>
                  </td>
                  <td class="small text-muted" style="max-width: 280px;">
                    <?= htmlspecialchars($eval['sanksi_singkat']) ?>
                  </td>
                  <td class="text-end pe-3">
                    <a href="<?= base_url('modules/pelanggaran/create.php?santri_id=' . $sum['id']) ?>" class="btn btn-sm btn-outline-warning rounded-pill px-3" title="Tambah Pelanggaran">
                      <i class="fas fa-plus me-1"></i> Tambah
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-check-circle fa-2x mb-2 text-success d-block"></i>
          Tidak ada santri yang sesuai dengan filter yang dipilih.
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- ========================================================================= -->
  <!-- TAB 3: RIWAYAT PELANGGARAN                                                -->
  <!-- ========================================================================= -->
  <div class="tab-pane fade <?= $active_tab === 'history' ? 'show active' : '' ?>" id="history-pane" role="tabpanel">
    
    <!-- Search Bar -->
    <div class="card-soft p-3 mb-4">
      <form action="" method="GET" class="d-flex gap-2">
        <input type="hidden" name="tab" value="history">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0 rounded-start-pill text-muted"><i class="fas fa-search"></i></span>
          <input type="text" name="search" class="form-control border-start-0 rounded-end-pill" placeholder="Cari nama santri, NIS, atau jenis pelanggaran..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn btn-dark-pill px-4">Cari</button>
        <?php if (!empty($search)): ?>
          <a href="?tab=history" class="btn btn-light rounded-pill px-3">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Data Table Card -->
    <div class="card-soft p-4">
      <?php if (count($pelanggaran_list) > 0): ?>
        <div class="table-responsive">
          <table class="table-modern">
            <thead>
              <tr>
                <th class="ps-3" style="width: 50px;">No</th>
                <th>Santri</th>
                <th>Jenis Pelanggaran</th>
                <th>Bobot Poin</th>
                <th>Tanggal Kejadian</th>
                <th class="text-end pe-3">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pelanggaran_list as $i => $row): ?>
                <tr>
                  <td class="ps-3 text-muted fw-semibold"><?= $i + 1 ?></td>
                  <td>
                    <strong class="d-block text-dark"><?= htmlspecialchars($row['nama']) ?></strong>
                    <small class="text-muted">NIS: <?= htmlspecialchars($row['nis']) ?> (<?= htmlspecialchars($row['kamar']) ?>)</small>
                  </td>
                  <td>
                    <span class="fw-semibold text-dark"><?= htmlspecialchars($row['jenis_pelanggaran']) ?></span>
                  </td>
                  <td>
                    <span class="badge bg-warning bg-opacity-25 text-dark fw-bold px-2.5 py-1 rounded-pill">
                      +<?= $row['poin'] ?> Poin
                    </span>
                  </td>
                  <td class="text-muted small">
                    <i class="far fa-calendar-alt me-1"></i> <?= date('d M Y', strtotime($row['tanggal'])) ?>
                  </td>
                  <td class="text-end pe-3">
                    <a href="<?= base_url('modules/pelanggaran/delete.php?id=' . $row['id']) ?>" class="btn btn-sm btn-outline-danger rounded-circle p-2 btn-confirm-delete" data-confirm="Hapus catatan pelanggaran ini?" title="Hapus">
                      <i class="fas fa-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50 d-block"></i>
          <h6 class="fw-bold text-dark">Data riwayat pelanggaran tidak ditemukan</h6>
          <p class="small text-muted mb-0">Belum ada pelanggaran yang dicatat atau sesuai dengan kata kunci pencarian.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>

</div>

<!-- ========================================================================= -->
<!-- MODAL 1: ATUR BATAS POIN SANKSI (Khusus Admin)                            -->
<!-- ========================================================================= -->
<?php if ($_SESSION['user_role'] === 'admin'): ?>
<div class="modal fade" id="modalPengaturanPoin" tabindex="-1" aria-labelledby="modalPengaturanPoinLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow">
      
      <div class="modal-header border-0 pb-0 pt-4 px-4">
        <h5 class="modal-title fw-bold text-dark" id="modalPengaturanPoinLabel">
          <i class="fas fa-sliders-h text-primary me-2"></i> Pengaturan Batas Poin Sanksi
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form action="" method="POST">
        <input type="hidden" name="action" value="update_thresholds">
        
        <div class="modal-body px-4 py-3">
          <p class="text-muted small">
            Tentukan ambang batas poin minimal untuk setiap klasifikasi sanksi. Sistem akan otomatis menerapkan sanksi kepada santri.
          </p>

          <div class="mb-3">
            <label for="poin_ringan" class="form-label fw-bold text-info mb-1">
              <i class="fas fa-info-circle me-1"></i> Batas Minimal Hukuman Ringan (SP 1)
            </label>
            <div class="input-group">
              <input type="number" class="form-control fw-bold" id="poin_ringan" name="poin_ringan" value="<?= $tR ?>" min="1" max="100" required oninput="previewThresholdChanges()">
              <span class="input-group-text bg-light">Poin</span>
            </div>
            <div class="form-text small">Santri di bawah poin ini masuk kategori <strong>Aman</strong>.</div>
          </div>

          <div class="mb-3">
            <label for="poin_sedang" class="form-label fw-bold text-warning mb-1">
              <i class="fas fa-exclamation-triangle me-1"></i> Batas Minimal Hukuman Sedang (SP 2)
            </label>
            <div class="input-group">
              <input type="number" class="form-control fw-bold" id="poin_sedang" name="poin_sedang" value="<?= $tS ?>" min="2" max="150" required oninput="previewThresholdChanges()">
              <span class="input-group-text bg-light">Poin</span>
            </div>
          </div>

          <div class="mb-3">
            <label for="poin_berat" class="form-label fw-bold text-danger mb-1">
              <i class="fas fa-radiation me-1"></i> Batas Minimal Hukuman Berat (SP 3 / D.O)
            </label>
            <div class="input-group">
              <input type="number" class="form-control fw-bold" id="poin_berat" name="poin_berat" value="<?= $tB ?>" min="3" max="200" required oninput="previewThresholdChanges()">
              <span class="input-group-text bg-light">Poin</span>
            </div>
          </div>

          <div class="p-3 rounded-3 bg-light border mt-3">
            <strong class="d-block small text-dark mb-1"><i class="fas fa-eye text-primary me-1"></i> Skema Aturan Baru:</strong>
            <ul class="list-unstyled small text-muted mb-0" id="rulePreviewList">
              <li>🟢 <strong>Aman:</strong> Poin &lt; <span id="prevR"><?= $tR ?></span></li>
              <li>🟡 <strong>Hukuman Ringan:</strong> <span id="prevR2"><?= $tR ?></span> s/d <span id="prevS"><?= $tS - 1 ?></span> Poin</li>
              <li>🟠 <strong>Hukuman Sedang:</strong> <span id="prevS2"><?= $tS ?></span> s/d <span id="prevB"><?= $tB - 1 ?></span> Poin</li>
              <li>🔴 <strong>Hukuman Berat:</strong> Poin ≥ <span id="prevB2"><?= $tB ?></span></li>
            </ul>
          </div>
        </div>

        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-dark-pill px-4 fw-semibold shadow-sm">
            <i class="fas fa-save me-1.5"></i> Simpan Batas Poin
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: RESET POIN / PEMUTIHAN SANTRI (Khusus Admin)                    -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalResetPoin" tabindex="-1" aria-labelledby="modalResetPoinLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow">
      
      <div class="modal-header border-0 pb-0 pt-4 px-4">
        <h5 class="modal-title fw-bold text-danger" id="modalResetPoinLabel">
          <i class="fas fa-exclamation-triangle me-2"></i> Reset / Pemutihan Poin Pelanggaran
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form action="" method="POST">
        <input type="hidden" name="action" value="reset_poin">
        <input type="hidden" name="current_tab" value="<?= htmlspecialchars($active_tab) ?>">
        
        <div class="modal-body px-4 py-3">
          <div class="alert alert-warning small rounded-3 mb-3">
            <i class="fas fa-info-circle me-1"></i> Fitur ini digunakan untuk <strong>pemutihan catatan pelanggaran santri</strong> (misal: saat pergantian semester baru, tahun ajaran baru, atau pembersihan data per bulan).
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold small text-dark">Pilih Cakupan Reset:</label>
            <select name="bulan_reset" class="form-select rounded-pill">
              <option value="semua">-- Reset Seluruh Pelanggaran (Semua Waktu) --</option>
              <option value="<?= $period_ym ?>">Hanya Bulan Terpilih (<?= $nama_bulan_indo[$selected_month] ?> <?= $selected_year ?>)</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold small text-dark">Ketik konfirmasi di bawah ini:</label>
            <input type="text" name="konfirmasi" class="form-control rounded-pill text-danger fw-bold" placeholder="Ketik: RESET POIN" required autocomplete="off">
            <small class="text-muted">Ketik tepat huruf besar: <code>RESET POIN</code></small>
          </div>
        </div>

        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger rounded-pill px-4 fw-semibold shadow-sm">
            <i class="fas fa-trash-alt me-1.5"></i> Eksekusi Pemutihan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function previewThresholdChanges() {
  const r = parseInt(document.getElementById('poin_ringan').value) || 0;
  const s = parseInt(document.getElementById('poin_sedang').value) || 0;
  const b = parseInt(document.getElementById('poin_berat').value) || 0;

  document.getElementById('prevR').textContent = r;
  document.getElementById('prevR2').textContent = r;
  document.getElementById('prevS').textContent = Math.max(r, s - 1);
  document.getElementById('prevS2').textContent = s;
  document.getElementById('prevB').textContent = Math.max(s, b - 1);
  document.getElementById('prevB2').textContent = b;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
