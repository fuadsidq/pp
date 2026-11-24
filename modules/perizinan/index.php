<?php
$page_title = 'Manajemen Perizinan Santri';
require_once __DIR__ . '/../../includes/header.php';
require_permission('perizinan');

$search       = trim($_GET['search'] ?? '');
$jenis_filter = trim($_GET['jenis_izin'] ?? '');

// ── Base WHERE clause ───────────────────────────────────────────────
$baseWhere  = "WHERE 1=1";
$baseParams = [];

if ($search !== '') {
    $baseWhere .= " AND (s.nama LIKE :search1 OR s.nis LIKE :search2 OR p.keterangan LIKE :search3)";
    $baseParams['search1'] = "%{$search}%";
    $baseParams['search2'] = "%{$search}%";
    $baseParams['search3'] = "%{$search}%";
}
if ($jenis_filter !== '') {
    $baseWhere .= " AND TRIM(p.jenis_izin) = :jenis_izin";
    $baseParams['jenis_izin'] = $jenis_filter;
}

// ── Query: Izin Aktif (tanggal_selesai >= hari ini) ─────────────────
$sqlAktif  = "SELECT p.*, s.nama, s.nis, s.kamar
               FROM perizinan p
               JOIN santri s ON p.santri_id = s.id
               {$baseWhere} AND p.tanggal_selesai >= CURDATE()
               ORDER BY p.tanggal ASC, p.id DESC";
$stmtAktif = $pdo->prepare($sqlAktif);
$stmtAktif->execute($baseParams);
$aktif_list = $stmtAktif->fetchAll();

// ── Query: Riwayat Izin (tanggal_selesai < hari ini) ────────────────
$sqlRiwayat  = "SELECT p.*, s.nama, s.nis, s.kamar
                FROM perizinan p
                JOIN santri s ON p.santri_id = s.id
                {$baseWhere} AND p.tanggal_selesai < CURDATE()
                ORDER BY p.tanggal_selesai DESC, p.id DESC";
$stmtRiwayat = $pdo->prepare($sqlRiwayat);
$stmtRiwayat->execute($baseParams);
$riwayat_list = $stmtRiwayat->fetchAll();

// Helper: sisa hari
function sisaHari($tglSelesai): int {
    $today = new DateTime(date('Y-m-d'));
    $end   = new DateTime($tglSelesai);
    $diff  = $today->diff($end);
    return (int) $diff->days * ($diff->invert ? -1 : 1);
}

// Helper: durasi izin dalam hari
function durasiIzin($tglMulai, $tglSelesai): int {
    $a = new DateTime($tglMulai);
    $b = new DateTime($tglSelesai);
    return (int) $a->diff($b)->days + 1;
}
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
  <div>
    <h3 class="fw-bold text-dark mb-1"><i class="fas fa-clipboard-check text-primary me-2"></i> Perizinan Santri</h3>
    <p class="text-muted small mb-0">Pencatatan & pemantauan perizinan santri. Izin yang sudah selesai otomatis masuk ke riwayat.</p>
  </div>
  <div class="mt-3 mt-md-0">
    <a href="<?= base_url('modules/perizinan/create.php') ?>" class="btn btn-primary rounded-pill px-4 shadow-sm">
      <i class="fas fa-plus-circle me-1"></i> Catat Perizinan Baru
    </a>
  </div>
</div>

<!-- Search & Filter -->
<div class="card card-custom mb-4">
  <div class="card-body p-3">
    <form action="" method="GET" class="row g-3 align-items-center">
      <div class="col-12 col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
          <input type="text" name="search" class="form-control bg-light border-start-0"
                 placeholder="Cari santri, NIS, atau keterangan..."
                 value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-12 col-md-4">
        <select name="jenis_izin" class="form-select bg-light fw-semibold text-dark" onchange="this.form.submit()">
          <option value="">-- Semua Jenis Izin --</option>
          <option value="Pulang" <?= $jenis_filter === 'Pulang' ? 'selected' : '' ?>>Pulang</option>
          <option value="Sakit"  <?= $jenis_filter === 'Sakit'  ? 'selected' : '' ?>>Sakit</option>
          <option value="Acara"  <?= $jenis_filter === 'Acara'  ? 'selected' : '' ?>>Acara</option>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-emerald rounded-pill px-3 flex-grow-1">
          <i class="fas fa-filter me-1"></i> Filter
        </button>
        <?php if ($search !== '' || $jenis_filter !== ''): ?>
          <a href="<?= base_url('modules/perizinan/index.php') ?>" class="btn btn-outline-secondary rounded-pill px-3">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-pills mb-3 gap-2" id="perizinanTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active fw-semibold" id="tab-aktif" data-bs-toggle="pill"
            data-bs-target="#pane-aktif" type="button" role="tab">
      <i class="fas fa-user-clock me-1"></i> Izin Aktif
      <span class="badge bg-primary ms-1"><?= count($aktif_list) ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link fw-semibold" id="tab-riwayat" data-bs-toggle="pill"
            data-bs-target="#pane-riwayat" type="button" role="tab">
      <i class="fas fa-history me-1"></i> Riwayat Izin
      <span class="badge bg-secondary ms-1"><?= count($riwayat_list) ?></span>
    </button>
  </li>
</ul>

<div class="tab-content">

  <!-- ── TAB AKTIF ── -->
  <div class="tab-pane fade show active" id="pane-aktif" role="tabpanel">
    <div class="card card-custom">
      <div class="card-body p-0">
        <?php if (count($aktif_list) > 0): ?>
          <div class="table-responsive">
            <table class="table table-custom mb-0">
              <thead>
                <tr>
                  <th class="ps-4">No</th>
                  <th>Nama Santri</th>
                  <th>Jenis Izin</th>
                  <th>Periode Izin</th>
                  <th>Keterangan</th>
                  <th>Status</th>
                  <th class="text-end pe-4">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($aktif_list as $i => $row): ?>
                  <?php
                    $sisa    = sisaHari($row['tanggal_selesai']);
                    $durasi  = durasiIzin($row['tanggal'], $row['tanggal_selesai']);
                    $badgeJenis = 'badge-soft-info';
                    if ($row['jenis_izin'] == 'Sakit')  $badgeJenis = 'badge-soft-warning';
                    if ($row['jenis_izin'] == 'Pulang') $badgeJenis = 'badge-soft-success';
                  ?>
                  <tr>
                    <td class="ps-4 text-muted fw-semibold"><?= $i + 1 ?></td>
                    <td>
                      <strong class="d-block text-dark"><?= htmlspecialchars($row['nama']) ?></strong>
                      <small class="text-muted">NIS: <?= htmlspecialchars($row['nis']) ?> | Kamar: <?= htmlspecialchars($row['kamar']) ?></small>
                    </td>
                    <td>
                      <span class="badge <?= $badgeJenis ?> fs-6">
                        <i class="fas fa-tag me-1"></i> <?= htmlspecialchars($row['jenis_izin']) ?>
                      </span>
                    </td>
                    <td>
                      <div class="text-dark small fw-medium">
                        <i class="far fa-calendar-alt text-muted me-1"></i>
                        <?= date('d M Y', strtotime($row['tanggal'])) ?>
                      </div>
                      <div class="text-muted small">
                        s/d <?= date('d M Y', strtotime($row['tanggal_selesai'])) ?>
                        <span class="text-secondary">(<?= $durasi ?> hari)</span>
                      </div>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($row['keterangan']) ?></td>
                    <td>
                      <?php if ($sisa === 0): ?>
                        <span class="badge badge-soft-warning">Berakhir Hari Ini</span>
                      <?php else: ?>
                        <span class="badge badge-soft-success">Sedang Izin</span>
                        <div class="small text-muted mt-1"><?= $sisa ?> hari lagi</div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                      <a href="<?= base_url('modules/perizinan/delete.php?id=' . $row['id']) ?>"
                         class="btn btn-sm btn-outline-danger rounded-circle btn-confirm-delete"
                         data-confirm="Hapus catatan perizinan ini?" title="Hapus Izin">
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
            <i class="fas fa-user-check fa-3x text-muted mb-3 d-block"></i>
            <h5 class="fw-semibold text-secondary">Tidak ada izin aktif saat ini</h5>
            <p class="text-muted small">Semua santri sedang berada di pondok, atau belum ada perizinan yang tercatat.</p>
            <a href="<?= base_url('modules/perizinan/create.php') ?>" class="btn btn-primary btn-sm rounded-pill px-4 mt-1">
              <i class="fas fa-plus me-1"></i> Catat Perizinan
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── TAB RIWAYAT ── -->
  <div class="tab-pane fade" id="pane-riwayat" role="tabpanel">
    <div class="card card-custom">
      <div class="card-body p-0">
        <?php if (count($riwayat_list) > 0): ?>
          <div class="table-responsive">
            <table class="table table-custom mb-0">
              <thead>
                <tr>
                  <th class="ps-4">No</th>
                  <th>Nama Santri</th>
                  <th>Jenis Izin</th>
                  <th>Periode Izin</th>
                  <th>Keterangan</th>
                  <th>Status</th>
                  <th class="text-end pe-4">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($riwayat_list as $i => $row): ?>
                  <?php
                    $durasi     = durasiIzin($row['tanggal'], $row['tanggal_selesai']);
                    $badgeJenis = 'badge-soft-info';
                    if ($row['jenis_izin'] == 'Sakit')  $badgeJenis = 'badge-soft-warning';
                    if ($row['jenis_izin'] == 'Pulang') $badgeJenis = 'badge-soft-success';
                  ?>
                  <tr>
                    <td class="ps-4 text-muted fw-semibold"><?= $i + 1 ?></td>
                    <td>
                      <strong class="d-block text-dark"><?= htmlspecialchars($row['nama']) ?></strong>
                      <small class="text-muted">NIS: <?= htmlspecialchars($row['nis']) ?> | Kamar: <?= htmlspecialchars($row['kamar']) ?></small>
                    </td>
                    <td>
                      <span class="badge <?= $badgeJenis ?> fs-6">
                        <i class="fas fa-tag me-1"></i> <?= htmlspecialchars($row['jenis_izin']) ?>
                      </span>
                    </td>
                    <td>
                      <div class="text-muted small">
                        <?= date('d M Y', strtotime($row['tanggal'])) ?>
                        &ndash;
                        <?= date('d M Y', strtotime($row['tanggal_selesai'])) ?>
                      </div>
                      <div class="small text-secondary"><?= $durasi ?> hari</div>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($row['keterangan']) ?></td>
                    <td>
                      <span class="badge bg-secondary bg-opacity-25 text-secondary">Selesai</span>
                    </td>
                    <td class="text-end pe-4">
                      <a href="<?= base_url('modules/perizinan/delete.php?id=' . $row['id']) ?>"
                         class="btn btn-sm btn-outline-danger rounded-circle btn-confirm-delete"
                         data-confirm="Hapus riwayat perizinan ini?" title="Hapus">
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
            <i class="fas fa-history fa-3x text-muted mb-3 d-block"></i>
            <h5 class="fw-semibold text-secondary">Belum ada riwayat izin</h5>
            <p class="text-muted small">Perizinan yang sudah melewati tanggal selesai akan otomatis muncul di sini.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
