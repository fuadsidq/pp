<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_permission('santri');

$id = intval($_GET['id'] ?? 0);

// Fetch existing data
$stmt = $pdo->prepare("SELECT * FROM santri WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$santri = $stmt->fetch();

if (!$santri) {
    set_flash_message('danger', 'Data santri tidak ditemukan!');
    header('Location: ' . base_url('modules/santri/index.php'));
    exit;
}

$error = '';
$nis   = $santri['nis'];
$nama  = $santri['nama'];
$kamar = $santri['kamar'];
$status= $santri['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis    = trim($_POST['nis'] ?? '');
    $nama   = trim($_POST['nama'] ?? '');
    $kamar  = trim($_POST['kamar'] ?? '');
    $status = trim($_POST['status'] ?? 'Aktif');

    if (empty($nis) || empty($nama) || empty($kamar)) {
        $error = 'Nomor Induk Santri (NIS), Nama, dan Kamar wajib diisi!';
    } else {
        // Check NIS conflict with other ID
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM santri WHERE nis = :nis AND id != :id");
        $stmtCheck->execute(['nis' => $nis, 'id' => $id]);
        if ($stmtCheck->fetchColumn() > 0) {
            $error = 'NIS ' . htmlspecialchars($nis) . ' telah digunakan oleh santri lain!';
        } else {
            // Update Database with PDO Prepared Statements
            $stmtUpdate = $pdo->prepare("UPDATE santri SET nis = :nis, nama = :nama, kamar = :kamar, status = :status WHERE id = :id");
            $success = $stmtUpdate->execute([
                'nis'    => $nis,
                'nama'   => $nama,
                'kamar'  => $kamar,
                'status' => $status,
                'id'     => $id
            ]);

            if ($success) {
                set_flash_message('success', 'Data santri ' . htmlspecialchars($nama) . ' berhasil diperbarui!');
                header('Location: ' . base_url('modules/santri/index.php'));
                exit;
            } else {
                $error = 'Gagal memperbarui data santri.';
            }
        }
    }
}

$page_title = 'Edit Data Santri';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-6">
    
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="fw-bold text-dark mb-0"><i class="fas fa-user-edit text-warning me-2"></i> Edit Data Santri</h4>
      <a href="<?= base_url('modules/santri/index.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Kembali
      </a>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="card card-custom">
      <div class="card-body p-4">
        <form action="" method="POST">
          <div class="mb-3">
            <label for="nis" class="form-label fw-semibold">Nomor Induk Santri (NIS) <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="nis" name="nis" value="<?= htmlspecialchars($nis) ?>" required>
          </div>

          <div class="mb-3">
            <label for="nama" class="form-label fw-semibold">Nama Lengkap Santri <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($nama) ?>" required>
          </div>

          <div class="mb-3">
            <label for="kamar" class="form-label fw-semibold">Gedung / Nomor Kamar <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="kamar" name="kamar" value="<?= htmlspecialchars($kamar) ?>" required>
          </div>

          <div class="mb-4">
            <label for="status" class="form-label fw-semibold">Status Santri</label>
            <select class="form-select" id="status" name="status">
              <option value="Aktif" <?= $status == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
              <option value="Alumni" <?= $status == 'Alumni' ? 'selected' : '' ?>>Alumni</option>
              <option value="Non-Aktif" <?= $status == 'Non-Aktif' ? 'selected' : '' ?>>Non-Aktif</option>
            </select>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <a href="<?= base_url('modules/santri/index.php') ?>" class="btn btn-light rounded-pill px-4">Batal</a>
            <button type="submit" class="btn btn-warning text-dark rounded-pill px-4 fw-semibold">
              <i class="fas fa-check me-1.5"></i> Perbarui Data
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
