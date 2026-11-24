<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_permission('santri');

$error = '';
$nis   = '';
$nama  = '';
$kamar = '';
$status= 'Aktif';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis    = strtoupper(trim($_POST['nis'] ?? ''));
    $nama   = trim($_POST['nama'] ?? '');
    $kamar  = trim($_POST['kamar'] ?? '');
    $status = trim($_POST['status'] ?? 'Aktif');

    if (empty($nis) || empty($nama) || empty($kamar)) {
        $error = 'Nomor Induk Santri (NIS), Nama, dan Kamar wajib diisi!';
    } else {
        try {
            // Check if NIS already exists using PDO Prepared Statements
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM santri WHERE UPPER(nis) = :nis");
            $stmtCheck->execute(['nis' => $nis]);
            
            if ($stmtCheck->fetchColumn() > 0) {
                $error = "NIS '" . htmlspecialchars($nis) . "' sudah terdaftar! Data santri tidak boleh duplikat.";
            } else {
                // Insert into Database
                $stmtInsert = $pdo->prepare("INSERT INTO santri (nis, nama, kamar, status) VALUES (:nis, :nama, :kamar, :status)");
                $success = $stmtInsert->execute([
                    'nis'    => $nis,
                    'nama'   => $nama,
                    'kamar'  => $kamar,
                    'status' => $status
                ]);

                if ($success) {
                    set_flash_message('success', 'Data santri ' . htmlspecialchars($nama) . ' (' . htmlspecialchars($nis) . ') berhasil disimpan!');
                    header('Location: ' . base_url('modules/santri/index.php'));
                    exit;
                } else {
                    $error = 'Gagal menyimpan data santri ke database.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

$page_title = 'Tambah Santri Baru';
require_once __DIR__ . '/../../includes/header.php';
?>
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = "Gagal! NIS '" . htmlspecialchars($nis) . "' sudah ada di dalam database (Duplikat).";
            } else {
                $error = 'Error Database: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-6">
    
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="fw-bold text-dark mb-0"><i class="fas fa-user-plus text-emerald me-2"></i> Tambah Santri Baru</h4>
      <a href="<?= base_url('modules/santri/index.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Kembali
      </a>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="card card-custom">
      <div class="card-body p-4">
        <form action="" method="POST" onsubmit="preventDoubleSubmit(this)">
          <div class="mb-3">
            <label for="nis" class="form-label fw-semibold">Nomor Induk Santri (NIS) <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="nis" name="nis" value="<?= htmlspecialchars($nis) ?>" placeholder="Contoh: 2024006" required autofocus>
            <div class="form-text"><i class="fas fa-shield-alt text-success me-1"></i> NIS wajib unik dan tidak boleh sama dengan santri lain.</div>
          </div>

          <div class="mb-3">
            <label for="nama" class="form-label fw-semibold">Nama Lengkap Santri <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($nama) ?>" placeholder="Masukkan nama lengkap" required>
          </div>

          <div class="mb-3">
            <label for="kamar" class="form-label fw-semibold">Gedung / Nomor Kamar <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="kamar" name="kamar" value="<?= htmlspecialchars($kamar) ?>" placeholder="Contoh: Al-Ghazali 03" required>
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
            <button type="submit" id="btnSubmit" class="btn btn-emerald rounded-pill px-4 fw-semibold">
              <i class="fas fa-save me-1.5"></i> Simpan Data Santri
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
function preventDoubleSubmit(form) {
  const btn = document.getElementById('btnSubmit');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1.5"></i> Menyimpan...';
  }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
