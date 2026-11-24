<?php
$page_title = 'Manajemen Pengurus';
require_once __DIR__ . '/../../includes/header.php';

// Pastikan hanya admin yang bisa akses
require_admin();

$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');

$sql = "SELECT id, username, nama_lengkap, role, permissions, created_at FROM users WHERE role IN ('pengurus_1', 'pengurus_2', 'pengurus')";
$params = [];

if ($search !== '') {
    $sql .= " AND (nama_lengkap LIKE :s1 OR username LIKE :s2)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
}

if ($role_filter !== '') {
    $sql .= " AND role = :role";
    $params['role'] = $role_filter;
}

$sql .= " ORDER BY role ASC, nama_lengkap ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pengurusList = $stmt->fetchAll();

// Hitung total pengurus
$totalP1 = 0;
$totalP2 = 0;
foreach ($pengurusList as $p) {
    if ($p['role'] === 'pengurus_1') $totalP1++;
    if ($p['role'] === 'pengurus_2') $totalP2++;
}
?>

<!-- Title & Action -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
  <div>
    <h3 class="fw-bold text-dark mb-1"><i class="fas fa-users-cog text-primary me-2"></i> Manajemen Pengurus Pondok</h3>
    <p class="text-muted small mb-0">Kelola akun dan penugasan Pengurus 1 dan Pengurus 2 untuk operasional pesantren.</p>
  </div>
  <div class="mt-3 mt-md-0">
    <a href="<?= base_url('modules/pengurus/create.php') ?>" class="btn btn-dark-pill px-4 shadow-sm">
      <i class="fas fa-user-plus me-1.5"></i> Tambah Pengurus Baru
    </a>
  </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-md-4">
    <div class="card-soft p-3.5 d-flex align-items-center gap-3">
      <div class="activity-avatar bg-primary text-white" style="width:48px; height:48px; font-size:1.1rem; border-radius:14px;">
        <i class="fas fa-user-shield"></i>
      </div>
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Pengurus 1</div>
        <h4 class="fw-bold mb-0 text-dark"><?= $totalP1 ?> <span class="fs-6 fw-normal text-muted">Akun</span></h4>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4">
    <div class="card-soft p-3.5 d-flex align-items-center gap-3">
      <div class="activity-avatar bg-info text-white" style="width:48px; height:48px; font-size:1.1rem; border-radius:14px;">
        <i class="fas fa-user-tie"></i>
      </div>
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Pengurus 2</div>
        <h4 class="fw-bold mb-0 text-dark"><?= $totalP2 ?> <span class="fs-6 fw-normal text-muted">Akun</span></h4>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-12 col-md-4">
    <div class="card-soft p-3.5 d-flex align-items-center gap-3">
      <div class="activity-avatar bg-dark text-white" style="width:48px; height:48px; font-size:1.1rem; border-radius:14px;">
        <i class="fas fa-users"></i>
      </div>
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Total Pengurus</div>
        <h4 class="fw-bold mb-0 text-dark"><?= count($pengurusList) ?> <span class="fs-6 fw-normal text-muted">Orang</span></h4>
      </div>
    </div>
  </div>
</div>

<!-- Filter & Search Card -->
<div class="card-soft p-3 mb-4">
  <form action="" method="GET" class="row g-3 align-items-center">
    <div class="col-12 col-md-6">
      <div class="input-group">
        <span class="input-group-text bg-white border-end-0 rounded-start-pill text-muted"><i class="fas fa-search"></i></span>
        <input type="text" name="search" class="form-control border-start-0 rounded-end-pill" placeholder="Cari nama atau username pengurus..." value="<?= htmlspecialchars($search) ?>">
      </div>
    </div>
    <div class="col-12 col-md-4">
      <select name="role" class="form-select rounded-pill" onchange="this.form.submit()">
        <option value="">-- Semua Role Pengurus --</option>
        <option value="pengurus_1" <?= $role_filter === 'pengurus_1' ? 'selected' : '' ?>>Pengurus 1 (Keamanan / Kedisiplinan)</option>
        <option value="pengurus_2" <?= $role_filter === 'pengurus_2' ? 'selected' : '' ?>>Pengurus 2 (Akademik / Administrasi)</option>
      </select>
    </div>
    <div class="col-12 col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-dark-pill px-3 w-100">Filter</button>
      <?php if ($search !== '' || $role_filter !== ''): ?>
        <a href="<?= base_url('modules/pengurus/index.php') ?>" class="btn btn-light rounded-pill px-3">Reset</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Table of Pengurus -->
<div class="card-soft p-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-bold text-dark mb-0">Daftar Pengurus Terdaftar</h5>
    <span class="badge bg-light text-dark px-3 py-1.5 rounded-pill border fw-semibold"><?= count($pengurusList) ?> Pengurus</span>
  </div>

  <?php if (count($pengurusList) > 0): ?>
    <div class="table-responsive">
      <table class="table-modern">
        <thead>
          <tr>
            <th class="ps-3">No</th>
            <th>Nama Lengkap</th>
            <th>Username</th>
            <th>Role / Jabatan</th>
            <th>Hak Akses Modul</th>
            <th>Terdaftar Sejak</th>
            <th class="text-end pe-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pengurusList as $idx => $p): ?>
            <?php 
              $pPerms = json_decode($p['permissions'] ?? '[]', true);
              if (!is_array($pPerms) || empty($pPerms)) {
                  $pPerms = ($p['role'] === 'pengurus_1') ? ['perizinan', 'pelanggaran'] : ['santri', 'spp'];
              }
            ?>
            <tr>
              <td class="ps-3 text-muted fw-semibold"><?= $idx + 1 ?></td>
              <td>
                <div class="d-flex align-items-center gap-2.5">
                  <div class="avatar-pill-circle <?= $p['role'] === 'pengurus_1' ? 'blue' : 'indigo' ?>" style="width:34px; height:34px; font-size:0.8rem; margin:0;">
                    <?= strtoupper(substr($p['nama_lengkap'], 0, 1)) ?>
                  </div>
                  <div>
                    <strong class="d-block text-dark"><?= htmlspecialchars($p['nama_lengkap']) ?></strong>
                    <small class="text-muted">ID: #<?= $p['id'] ?></small>
                  </div>
                </div>
              </td>
              <td>
                <code class="text-dark bg-light px-2 py-1 rounded-2">@<?= htmlspecialchars($p['username']) ?></code>
              </td>
              <td>
                <?php if ($p['role'] === 'pengurus_1'): ?>
                  <span class="badge-soft-blue"><i class="fas fa-shield-alt me-1"></i> Pengurus 1</span>
                <?php else: ?>
                  <span class="badge-soft-purple"><i class="fas fa-user-tie me-1"></i> Pengurus 2</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex flex-wrap gap-1" style="max-width:240px;">
                  <?php if (in_array('santri', $pPerms)): ?>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2 py-0.5 small" style="font-size:0.72rem;">Santri</span>
                  <?php endif; ?>
                  <?php if (in_array('perizinan', $pPerms)): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-0.5 small" style="font-size:0.72rem;">Perizinan</span>
                  <?php endif; ?>
                  <?php if (in_array('pelanggaran', $pPerms)): ?>
                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 rounded-pill px-2 py-0.5 small" style="font-size:0.72rem;">Pelanggaran</span>
                  <?php endif; ?>
                  <?php if (in_array('spp', $pPerms)): ?>
                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill px-2 py-0.5 small" style="font-size:0.72rem;">Keuangan</span>
                  <?php endif; ?>
                  <?php if (empty($pPerms)): ?>
                    <span class="badge bg-secondary bg-opacity-25 text-muted rounded-pill px-2 py-0.5 small">Tidak Ada Akses</span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="text-muted small">
                <?= date('d M Y, H:i', strtotime($p['created_at'])) ?>
              </td>
              <td class="text-end pe-3">
                <div class="d-flex justify-content-end gap-1.5">
                  <a href="<?= base_url('modules/pengurus/edit.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3" title="Edit Pengurus">
                    <i class="fas fa-pen me-1"></i> Edit
                  </a>
                  <a href="<?= base_url('modules/pengurus/delete.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-danger rounded-circle p-2 btn-confirm-delete" data-confirm="Yakin ingin menghapus akun pengurus <?= htmlspecialchars($p['nama_lengkap']) ?>?" title="Hapus Pengurus">
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
    <div class="text-center py-5 text-muted">
      <i class="fas fa-user-friends fa-3x mb-3 d-block text-secondary opacity-50"></i>
      <h6 class="fw-bold text-dark">Belum ada data pengurus</h6>
      <p class="small text-muted mb-3">Klik tombol di bawah untuk menambahkan akun Pengurus 1 atau Pengurus 2.</p>
      <a href="<?= base_url('modules/pengurus/create.php') ?>" class="btn btn-dark-pill px-4">
        <i class="fas fa-plus me-1"></i> Tambah Pengurus Baru
      </a>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
