<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_permission('spp');

// Fetch active santri list
$stmtSantri = $pdo->query("SELECT id, nis, nama, kamar FROM santri WHERE status = 'Aktif' ORDER BY nama ASC");
$activeSantri = $stmtSantri->fetchAll();

$error = '';
$tipe_input = 'single';
$santri_id  = 0;
$bulan      = date('F Y'); // e.g. July 2026
$nominal    = 500000;
$status     = 'Belum Lunas';

// Indonesian Month Name Helper
$months = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];
$currentMonthIndo = ($months[date('F')] ?? date('F')) . ' ' . date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipe_input = trim($_POST['tipe_input'] ?? 'single');
    $santri_id  = intval($_POST['santri_id'] ?? 0);
    $bulan      = trim($_POST['bulan'] ?? $currentMonthIndo);
    $nominal    = floatval($_POST['nominal'] ?? 0);
    $status     = trim($_POST['status'] ?? 'Belum Lunas');

    if (empty($bulan) || $nominal <= 0) {
        $error = 'Bulan Tagihan dan Nominal SPP wajib diisi!';
    } else {
        if ($tipe_input === 'batch') {
            // Generate for ALL Active Santri
            $stmtInsertBatch = $pdo->prepare("INSERT INTO tagihan_spp (santri_id, bulan, nominal, status) VALUES (:santri_id, :bulan, :nominal, :status)");
            $countSuccess = 0;
            foreach ($activeSantri as $s) {
                // Check if already exists for this santri and month
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM tagihan_spp WHERE santri_id = :santri_id AND bulan = :bulan");
                $stmtCheck->execute(['santri_id' => $s['id'], 'bulan' => $bulan]);
                if ($stmtCheck->fetchColumn() == 0) {
                    $stmtInsertBatch->execute([
                        'santri_id' => $s['id'],
                        'bulan'     => $bulan,
                        'nominal'   => $nominal,
                        'status'    => $status
                    ]);
                    $countSuccess++;
                }
            }

            set_flash_message('success', "Berhasil membuat tagihan SPP '{$bulan}' untuk {$countSuccess} santri aktif!");
            header('Location: ' . base_url('modules/spp/index.php'));
            exit;
        } else {
            // Single Santri
            if ($santri_id <= 0) {
                $error = 'Pilih santri terlebih dahulu!';
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO tagihan_spp (santri_id, bulan, nominal, status, tanggal_bayar) VALUES (:santri_id, :bulan, :nominal, :status, :tanggal_bayar)");
                $tanggal_bayar = ($status == 'Lunas') ? date('Y-m-d') : NULL;
                
                $success = $stmtInsert->execute([
                    'santri_id'     => $santri_id,
                    'bulan'         => $bulan,
                    'nominal'       => $nominal,
                    'status'        => $status,
                    'tanggal_bayar' => $tanggal_bayar
                ]);

                if ($success) {
                    set_flash_message('success', 'Tagihan SPP berhasil ditambahkan!');
                    header('Location: ' . base_url('modules/spp/index.php'));
                    exit;
                } else {
                    $error = 'Gagal menyimpan tagihan SPP.';
                }
            }
        }
    }
}

$page_title = 'Buat Tagihan SPP';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-6">
    
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="fw-bold text-dark mb-0"><i class="fas fa-file-invoice-dollar text-emerald me-2"></i> Buat Tagihan SPP</h4>
      <a href="<?= base_url('modules/spp/index.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
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
          
          <!-- Mode Input Choice -->
          <div class="mb-4 p-3 bg-light rounded-3">
            <label class="form-label fw-bold mb-2 text-dark">Mode Pembuatan Tagihan:</label>
            <div class="d-flex gap-4">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="tipe_input" id="tipe_single" value="single" <?= $tipe_input == 'single' ? 'checked' : '' ?> onclick="toggleSantriSelect(true)">
                <label class="form-check-label fw-semibold" for="tipe_single">
                  Per Santri (Single)
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="tipe_input" id="tipe_batch" value="batch" <?= $tipe_input == 'batch' ? 'checked' : '' ?> onclick="toggleSantriSelect(false)">
                <label class="form-check-label fw-semibold text-emerald" for="tipe_batch">
                  <i class="fas fa-users me-1"></i> Generasi Masal (Semua Santri Aktif)
                </label>
              </div>
            </div>
          </div>

          <div class="mb-3" id="santriSelectContainer">
            <label for="santri_id" class="form-label fw-semibold">Pilih Santri <span class="text-danger">*</span></label>
            <select class="form-select" id="santri_id" name="santri_id">
              <option value="">-- Pilih Santri --</option>
              <?php foreach ($activeSantri as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $santri_id == $s['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($s['nama']) ?> (NIS: <?= htmlspecialchars($s['nis']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="bulan" class="form-label fw-semibold">Bulan & Tahun Tagihan <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="bulan" name="bulan" value="<?= htmlspecialchars($currentMonthIndo) ?>" placeholder="Contoh: Juli 2026" required>
          </div>

          <div class="mb-3">
            <label for="nominal" class="form-label fw-semibold">Nominal SPP (Rp) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light fw-bold">Rp</span>
              <input type="number" step="5000" min="0" class="form-control" id="nominal" name="nominal" value="<?= $nominal ?>" required>
            </div>
          </div>

          <div class="mb-4">
            <label for="status" class="form-label fw-semibold">Status Tagihan Awal</label>
            <select class="form-select" id="status" name="status">
              <option value="Belum Lunas" <?= $status == 'Belum Lunas' ? 'selected' : '' ?>>Belum Lunas</option>
              <option value="Lunas" <?= $status == 'Lunas' ? 'selected' : '' ?>>Lunas (Sudah Dibayar)</option>
            </select>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <a href="<?= base_url('modules/spp/index.php') ?>" class="btn btn-light rounded-pill px-4">Batal</a>
            <button type="submit" class="btn btn-emerald rounded-pill px-4 fw-semibold">
              <i class="fas fa-save me-1.5"></i> Simpan Tagihan SPP
            </button>
          </div>

        </form>
      </div>
    </div>

  </div>
</div>

<script>
function toggleSantriSelect(show) {
  const container = document.getElementById('santriSelectContainer');
  const select = document.getElementById('santri_id');
  if (show) {
    container.style.display = 'block';
    select.required = true;
  } else {
    container.style.display = 'none';
    select.required = false;
  }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
