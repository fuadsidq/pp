<?php
$page_title = 'Manajemen Keuangan & SPP';
require_once __DIR__ . '/../../includes/header.php';
require_permission('spp');

$search        = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$bulan_filter  = trim($_GET['bulan'] ?? '');

// Build SQL Query
$sql = "SELECT t.*, s.nama, s.nis, s.kamar 
        FROM tagihan_spp t 
        JOIN santri s ON t.santri_id = s.id 
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (s.nama LIKE :search1 OR s.nis LIKE :search2)";
    $params['search1'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
}

if ($status_filter !== '') {
    $sql .= " AND t.status = :status";
    $params['status'] = $status_filter;
}

if ($bulan_filter !== '') {
    $sql .= " AND t.bulan LIKE :bulan";
    $params['bulan'] = "%{$bulan_filter}%";
}

$sql .= " ORDER BY t.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$spp_list = $stmt->fetchAll();

// Total Summary Statistics for SPP
$stmtStats = $pdo->query("
    SELECT 
        SUM(CASE WHEN status = 'Lunas' THEN nominal ELSE 0 END) as total_terbayar,
        SUM(CASE WHEN status = 'Belum Lunas' THEN nominal ELSE 0 END) as total_tunggakan,
        COUNT(CASE WHEN status = 'Belum Lunas' THEN 1 END) as count_unpaid
    FROM tagihan_spp
");
$sppStats = $stmtStats->fetch();
?>

<!-- Header Title & Action Buttons -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
  <div>
    <h3 class="fw-bold text-dark mb-1"><i class="fas fa-wallet text-success me-2"></i> Keuangan & Tagihan SPP</h3>
    <p class="text-muted small mb-0">Manajemen status pembayaran SPP santri bulanan dan pencatatan pembayaran manual.</p>
  </div>
  <div class="mt-3 mt-md-0 d-flex gap-2">
    <a href="<?= base_url('modules/spp/create.php') ?>" class="btn btn-emerald rounded-pill px-4 shadow-sm fw-semibold">
      <i class="fas fa-plus-circle me-1.5"></i> Buat Tagihan SPP
    </a>
  </div>
</div>

<!-- SPP Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-12 col-md-6">
    <div class="card card-custom p-3 border-start border-success border-4">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted small fw-semibold text-uppercase">Total SPP Terbayar (Lunas)</span>
          <h4 class="fw-bold text-success my-1">Rp <?= number_format($sppStats['total_terbayar'] ?? 0, 0, ',', '.') ?></h4>
        </div>
        <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle">
          <i class="fas fa-check-circle fa-2x"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6">
    <div class="card card-custom p-3 border-start border-danger border-4">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted small fw-semibold text-uppercase">Total Tunggakan SPP (Belum Lunas)</span>
          <h4 class="fw-bold text-danger my-1">Rp <?= number_format($sppStats['total_tunggakan'] ?? 0, 0, ',', '.') ?></h4>
        </div>
        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-circle">
          <i class="fas fa-exclamation-circle fa-2x"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filter Bar -->
<div class="card card-custom mb-4">
  <div class="card-body p-3">
    <form action="" method="GET" class="row g-3 align-items-center">
      <div class="col-12 col-md-4">
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
          <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Cari santri atau NIS..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      
      <div class="col-12 col-md-3">
        <!-- Auto submit on change -->
        <select name="status" class="form-select bg-light fw-semibold text-dark" onchange="this.form.submit()">
          <option value="">-- Semua Status --</option>
          <option value="Lunas" <?= $status_filter === 'Lunas' ? 'selected' : '' ?>>Lunas</option>
          <option value="Belum Lunas" <?= $status_filter === 'Belum Lunas' ? 'selected' : '' ?>>Belum Lunas</option>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <input type="text" name="bulan" class="form-control bg-light" placeholder="Filter Bulan (Misal: Juli 2026)" value="<?= htmlspecialchars($bulan_filter) ?>">
      </div>

      <div class="col-12 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary rounded-pill px-3 flex-grow-1">Filter</button>
        <?php if ($search !== '' || $status_filter !== '' || $bulan_filter !== ''): ?>
          <a href="<?= base_url('modules/spp/index.php') ?>" class="btn btn-outline-secondary rounded-pill px-3">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- SPP Table Card -->
<div class="card card-custom">
  <div class="card-body p-0">
    <?php if (count($spp_list) > 0): ?>
      <div class="table-responsive">
        <table class="table table-custom mb-0">
          <thead>
            <tr>
              <th class="ps-4">No</th>
              <th>Santri</th>
              <th>Bulan Tagihan</th>
              <th>Nominal</th>
              <th>Status</th>
              <th>Tanggal Pembayaran</th>
              <th class="text-end pe-4">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($spp_list as $index => $row): ?>
              <tr>
                <td class="ps-4 text-muted fw-semibold"><?= $index + 1 ?></td>
                <td>
                  <strong class="d-block text-dark"><?= htmlspecialchars($row['nama']) ?></strong>
                  <small class="text-muted">NIS: <?= htmlspecialchars($row['nis']) ?> | Kamar: <?= htmlspecialchars($row['kamar']) ?></small>
                </td>
                <td><span class="fw-semibold text-dark"><?= htmlspecialchars($row['bulan']) ?></span></td>
                <td><strong class="text-dark">Rp <?= number_format($row['nominal'], 0, ',', '.') ?></strong></td>
                <td>
                  <?php if ($row['status'] == 'Lunas'): ?>
                    <span class="badge badge-soft-success fs-6"><i class="fas fa-check-circle me-1"></i> Lunas</span>
                  <?php else: ?>
                    <span class="badge badge-soft-danger fs-6"><i class="fas fa-times-circle me-1"></i> Belum Lunas</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($row['tanggal_bayar'])): ?>
                    <span class="small text-muted"><i class="far fa-calendar-check text-success me-1"></i> <?= date('d M Y', strtotime($row['tanggal_bayar'])) ?></span>
                  <?php else: ?>
                    <span class="small text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td class="text-end pe-4">
                  <?php if ($row['status'] == 'Belum Lunas'): ?>
                    <a href="<?= base_url('modules/spp/pay.php?id=' . $row['id']) ?>" class="btn btn-sm btn-success rounded-pill px-3 me-1" onclick="return confirm('Konfirmasi pencatatan pembayaran manual SPP ini?');">
                      <i class="fas fa-hand-holding-usd me-1"></i> Bayar
                    </a>
                  <?php else: ?>
                    <a href="<?= base_url('modules/spp/pay.php?id=' . $row['id'] . '&action=unpay') ?>" class="btn btn-sm btn-outline-warning rounded-pill px-3 me-1" onclick="return confirm('Batalkan status lunas tagihan ini?');" title="Batal Lunas">
                      <i class="fas fa-undo me-1"></i> Batal
                    </a>
                  <?php endif; ?>
                  
                  <a href="<?= base_url('modules/spp/delete.php?id=' . $row['id']) ?>" class="btn btn-sm btn-outline-danger rounded-circle btn-confirm-delete" data-confirm="Hapus data tagihan SPP ini?" title="Hapus Tagihan">
                    <i class="fas fa-trash"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="text-center py-5">
        <i class="fas fa-receipt fa-3x text-muted mb-3 d-block"></i>
        <h5 class="fw-semibold text-secondary">Data tagihan SPP tidak ditemukan</h5>
        <p class="text-muted small">Silakan tambah tagihan SPP baru untuk santri.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
