<?php
$page_title = 'Manajemen Data Santri';
require_once __DIR__ . '/../../includes/header.php';
require_permission('santri');

// Filter & Search Params
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

// Build Query dynamically with PDO
$sql = "SELECT * FROM santri WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (nama LIKE :search1 OR nis LIKE :search2 OR kamar LIKE :search3)";
    $params['search1'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
    $params['search3'] = "%{$search}%";
}

if ($status_filter !== '') {
    $sql .= " AND status = :status";
    $params['status'] = $status_filter;
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$santri_list = $stmt->fetchAll();
?>

<!-- Header Title & Add Button -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
  <div>
    <h3 class="fw-bold text-dark mb-1"><i class="fas fa-user-graduate text-emerald me-2"></i> Manajemen Data Santri</h3>
    <p class="text-muted small mb-0">Kelola informasi santri, nomor induk, alokasi kamar, dan status keaktifan.</p>
  </div>
  <div class="mt-3 mt-md-0 d-flex gap-2">
    <a href="<?= base_url('modules/santri/import.php') ?>" class="btn btn-outline-success rounded-pill px-3 shadow-sm">
      <i class="fas fa-file-excel me-1.5"></i> Import Excel
    </a>
    <a href="<?= base_url('modules/santri/create.php') ?>" class="btn btn-emerald rounded-pill px-4 shadow-sm">
      <i class="fas fa-plus-circle me-1.5"></i> Tambah Santri Baru
    </a>
  </div>
</div>

<!-- Search & Filter Card -->
<div class="card card-custom mb-4">
  <div class="card-body p-3">
    <form action="" method="GET" class="row g-3 align-items-center">
      <div class="col-12 col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
          <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Cari nama, NIS, atau kamar..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      
      <div class="col-12 col-md-4">
        <!-- Auto submit on change -->
        <select name="status" class="form-select bg-light fw-semibold text-dark" onchange="this.form.submit()">
          <option value="">-- Semua Status --</option>
          <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
          <option value="Alumni" <?= $status_filter === 'Alumni' ? 'selected' : '' ?>>Alumni</option>
          <option value="Non-Aktif" <?= $status_filter === 'Non-Aktif' ? 'selected' : '' ?>>Non-Aktif</option>
        </select>
      </div>

      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary rounded-pill px-3 flex-grow-1">
          <i class="fas fa-filter me-1"></i> Filter
        </button>
        <?php if ($search !== '' || $status_filter !== ''): ?>
          <a href="<?= base_url('modules/santri/index.php') ?>" class="btn btn-outline-secondary rounded-pill px-3">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Data Table Card -->
<div class="card card-custom">
  <div class="card-body p-0">
    <?php if (count($santri_list) > 0): ?>
      <div class="table-responsive">
        <table class="table table-custom mb-0">
          <thead>
            <tr>
              <th class="ps-4">No</th>
              <th>NIS</th>
              <th>Nama Lengkap</th>
              <th>Kamar</th>
              <th>Status</th>
              <th>Tanggal Terdaftar</th>
              <th class="text-end pe-4">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($santri_list as $index => $row): ?>
              <tr>
                <td class="ps-4 text-muted fw-semibold"><?= $index + 1 ?></td>
                <td><span class="badge bg-light text-dark font-monospace fs-6 border"><?= htmlspecialchars($row['nis']) ?></span></td>
                <td><strong class="text-dark"><?= htmlspecialchars($row['nama']) ?></strong></td>
                <td><i class="fas fa-bed text-secondary me-1.5"></i> <?= htmlspecialchars($row['kamar']) ?></td>
                <td>
                  <?php if ($row['status'] == 'Aktif'): ?>
                    <span class="badge badge-soft-success"><i class="fas fa-check-circle me-1"></i> Aktif</span>
                  <?php elseif ($row['status'] == 'Alumni'): ?>
                    <span class="badge badge-soft-info"><i class="fas fa-user-graduate me-1"></i> Alumni</span>
                  <?php else: ?>
                    <span class="badge badge-soft-danger"><i class="fas fa-times-circle me-1"></i> Non-Aktif</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                <td class="text-end pe-4">
                  <div class="btn-group">
                    <a href="<?= base_url('modules/santri/edit.php?id=' . $row['id']) ?>" class="btn btn-sm btn-outline-warning rounded-circle me-1" title="Edit Santri">
                      <i class="fas fa-edit"></i>
                    </a>
                    <a href="<?= base_url('modules/santri/delete.php?id=' . $row['id']) ?>" class="btn btn-sm btn-outline-danger rounded-circle btn-confirm-delete" data-confirm="Penghapusan santri akan menghapus seluruh data perizinan, pelanggaran, dan SPP terkait. Yakin?" title="Hapus Santri">
                      <i class="fas fa-trash"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="text-center py-5">
        <i class="fas fa-user-slash fa-3x text-muted mb-3 d-block"></i>
        <h5 class="fw-semibold text-secondary">Data santri tidak ditemukan</h5>
        <p class="text-muted small">Coba ubah kata kunci pencarian atau filter status.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
