<?php
$page_title = 'Import Data Santri via Excel/CSV';
require_once __DIR__ . '/../../includes/header.php';

$error = '';
$imported_count = 0;
$skipped_count  = 0;
$skipped_details = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_excel'])) {
    $file = $_FILES['file_excel'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Terjadi kesalahan saat mengunggah file. Silakan coba lagi.';
    } else {
        $filename  = $file['name'];
        $ext       = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $tmp_path  = $file['tmp_name'];

        if (!in_array($ext, ['csv', 'txt'])) {
            $error = 'Format file tidak didukung! Silakan gunakan file berkestensi .csv (dapat disave/export dari Microsoft Excel).';
        } else {
            $handle = fopen($tmp_path, 'r');
            if ($handle !== false) {
                $firstLine = fgets($handle);
                rewind($handle);

                // Detect delimiter (; or ,)
                $delimiter = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

                // Skip UTF-8 BOM if present
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                $rowCount = 0;
                $seenInFile = []; // Track seen NIS inside this import batch to prevent duplicate rows in same Excel file

                $pdo->beginTransaction();

                try {
                    $stmtCheck  = $pdo->prepare("SELECT COUNT(*) FROM santri WHERE UPPER(nis) = :nis");
                    $stmtInsert = $pdo->prepare("INSERT INTO santri (nis, nama, kamar, status) VALUES (:nis, :nama, :kamar, :status)");

                    while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                        $rowCount++;

                        // Skip header row if exists
                        if ($rowCount === 1 && (strtolower(trim($data[0] ?? '')) === 'nis' || strtolower(trim($data[1] ?? '')) === 'nama')) {
                            continue;
                        }

                        $nis    = strtoupper(trim($data[0] ?? ''));
                        $nama   = trim($data[1] ?? '');
                        $kamar  = trim($data[2] ?? '');
                        $status = trim($data[3] ?? 'Aktif');

                        if (!in_array($status, ['Aktif', 'Alumni', 'Non-Aktif'])) {
                            $status = 'Aktif';
                        }

                        if (empty($nis) || empty($nama) || empty($kamar)) {
                            $skipped_count++;
                            $skipped_details[] = "Baris #{$rowCount}: Kolom NIS, Nama, atau Kamar kosong.";
                            continue;
                        }

                        // Check 1: Duplicate inside the uploaded file
                        if (isset($seenInFile[$nis])) {
                            $skipped_count++;
                            $skipped_details[] = "Baris #{$rowCount}: NIS '{$nis}' ({$nama}) terdeteksi ganda di dalam file Excel yang sama.";
                            continue;
                        }
                        $seenInFile[$nis] = true;

                        // Check 2: Duplicate in Database
                        $stmtCheck->execute(['nis' => $nis]);
                        if ($stmtCheck->fetchColumn() > 0) {
                            $skipped_count++;
                            $skipped_details[] = "Baris #{$rowCount}: NIS '{$nis}' ({$nama}) sudah terdaftar di database.";
                            continue;
                        }

                        // Execute Insert with try-catch for SQLSTATE 23000 safety
                        try {
                            $stmtInsert->execute([
                                'nis'    => $nis,
                                'nama'   => $nama,
                                'kamar'  => $kamar,
                                'status' => $status
                            ]);
                            $imported_count++;
                        } catch (PDOException $exInsert) {
                            $skipped_count++;
                            $skipped_details[] = "Baris #{$rowCount}: Gagal menyimpan (NIS '{$nis}' duplikat).";
                        }
                    }

                    $pdo->commit();
                    fclose($handle);

                    if ($imported_count > 0) {
                        $msg = "Berhasil mengimpor {$imported_count} data santri baru tanpa ada duplikasi!";
                        if ($skipped_count > 0) {
                            $msg .= " ({$skipped_count} data dilewati).";
                        }
                        set_flash_message('success', $msg);
                        header('Location: ' . base_url('modules/santri/index.php'));
                        exit;
                    } else {
                        $error = "Tidak ada data santri baru yang diimpor. {$skipped_count} data dilewati.";
                    }

                } catch (Exception $e) {
                    $pdo->rollBack();
                    fclose($handle);
                    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
                }
            } else {
                $error = 'Gagal membuka file yang diunggah.';
            }
        }
    }
}
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-10 col-lg-8">
    
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="fw-bold text-dark mb-0"><i class="fas fa-file-excel text-success me-2"></i> Import Data Santri via Excel / CSV</h4>
      <a href="<?= base_url('modules/santri/index.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Kembali
      </a>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($skipped_details)): ?>
      <div class="alert alert-warning shadow-sm mb-4">
        <h6 class="fw-bold mb-2"><i class="fas fa-info-circle me-1"></i> Detail Data yang Dilewati / Cegah Duplikat:</h6>
        <ul class="mb-0 small" style="max-height: 200px; overflow-y: auto;">
          <?php foreach ($skipped_details as $detail): ?>
            <li><?= htmlspecialchars($detail) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Step Instructions Card -->
    <div class="card card-custom mb-4 border-start border-emerald border-4">
      <div class="card-body p-4">
        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-list-ol text-emerald me-2"></i> Panduan Langkah Import Data</h5>
        <ol class="mb-3 text-secondary lh-lg">
          <li>Unduh template file Excel/CSV resmi dengan mengklik tombol di bawah.</li>
          <li>Isi data santri pada file Excel sesuai format header: <strong>nis</strong>, <strong>nama</strong>, <strong>kamar</strong>, <strong>status</strong>.</li>
          <li>Simpan file dalam format <strong>CSV (Comma Delimited) (*.csv)</strong> dari Microsoft Excel (File &gt; Save As &gt; CSV).</li>
          <li>Unggah file CSV pada form di bawah ini lalu klik tombol <strong>Proses Import Data</strong>.</li>
        </ol>

        <a href="<?= base_url('modules/santri/download_template.php') ?>" class="btn btn-outline-success rounded-pill px-4">
          <i class="fas fa-download me-1.5"></i> Unduh Format Template CSV Excel
        </a>
      </div>
    </div>

    <!-- Upload Form Card -->
    <div class="card card-custom">
      <div class="card-body p-4">
        <form action="" method="POST" enctype="multipart/form-data" onsubmit="this.querySelector('button[type=submit]').disabled=true">
          
          <div class="mb-4">
            <label for="file_excel" class="form-label fw-semibold">Pilih File Excel/CSV (.csv) <span class="text-danger">*</span></label>
            <input type="file" class="form-control form-control-lg bg-light" id="file_excel" name="file_excel" accept=".csv, .txt" required>
            <div class="form-text mt-2"><i class="fas fa-shield-alt text-success me-1"></i> Sistem dilengkapi filter proteksi otomatis untuk mencegah data duplikat.</div>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <a href="<?= base_url('modules/santri/index.php') ?>" class="btn btn-light rounded-pill px-4">Batal</a>
            <button type="submit" class="btn btn-emerald rounded-pill px-4 fw-semibold">
              <i class="fas fa-file-upload me-1.5"></i> Proses Import Data
            </button>
          </div>

        </form>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
