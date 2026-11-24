<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_permission('perizinan');

// Fetch active santri list
$stmtSantri = $pdo->query("SELECT id, nis, nama, kamar FROM santri WHERE status = 'Aktif' ORDER BY nama ASC");
$activeSantri = $stmtSantri->fetchAll();

$error = '';
$santri_id_single  = intval($_GET['santri_id'] ?? 0);
$santri_ids        = [];
$jenis_izin        = 'Pulang';
$keterangan        = '';
$tanggal           = date('Y-m-d');
$tanggal_selesai   = date('Y-m-d', strtotime('+1 day'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $santri_ids = $_POST['santri_ids'] ?? [];
    if (!is_array($santri_ids)) {
        $santri_ids = [$santri_ids];
    }
    $santri_ids = array_map('intval', array_filter($santri_ids));

    $jenis_izin      = trim($_POST['jenis_izin'] ?? 'Pulang');
    $keterangan      = trim($_POST['keterangan'] ?? '');
    $tanggal         = trim($_POST['tanggal'] ?? date('Y-m-d'));
    $tanggal_selesai = trim($_POST['tanggal_selesai'] ?? date('Y-m-d', strtotime('+1 day')));

    if (empty($santri_ids)) {
        $error = 'Silakan pilih minimal 1 (satu) santri dari daftar!';
    } elseif (empty($keterangan) || empty($tanggal) || empty($tanggal_selesai)) {
        $error = 'Semua field wajib diisi!';
    } elseif ($tanggal_selesai < $tanggal) {
        $error = 'Tanggal selesai tidak boleh sebelum tanggal mulai izin!';
    } else {
        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare(
                "INSERT INTO perizinan (santri_id, jenis_izin, keterangan, tanggal, tanggal_selesai)
                 VALUES (:santri_id, :jenis_izin, :keterangan, :tanggal, :tanggal_selesai)"
            );
            $countSuccess = 0;

            foreach ($santri_ids as $sid) {
                if ($sid > 0) {
                    $stmtInsert->execute([
                        'santri_id'       => $sid,
                        'jenis_izin'      => $jenis_izin,
                        'keterangan'      => $keterangan,
                        'tanggal'         => $tanggal,
                        'tanggal_selesai' => $tanggal_selesai,
                    ]);
                    $countSuccess++;
                }
            }

            $pdo->commit();
            set_flash_message('success', "Berhasil mencatat perizinan untuk {$countSuccess} santri!");
            header('Location: ' . base_url('modules/perizinan/index.php'));
            exit;
        } catch (Exception $ex) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan sistem saat menyimpan data: ' . $ex->getMessage();
        }
    }
}

$page_title = 'Catat Perizinan Santri';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-9 col-lg-8">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="fw-bold text-dark mb-0"><i class="fas fa-clipboard-check text-primary me-2"></i> Form Catat Perizinan Santri</h4>
      <a href="<?= base_url('modules/perizinan/index.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Kembali
      </a>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="card card-custom">
      <div class="card-body p-4">
        <form action="" method="POST" id="formPerizinan">

          <!-- 1. Pilih Santri -->
          <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label fw-bold text-dark mb-0">1. Pilih Santri (Bisa Lebih Dari 1) <span class="text-danger">*</span></label>
              <span id="selectedCounter" class="badge bg-secondary text-white px-2 py-1 rounded-pill">0 Santri Dipilih</span>
            </div>

            <div class="input-group mb-2">
              <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
              <input type="text" id="searchKeyword" class="form-control bg-light border-start-0"
                     placeholder="Ketik nama atau NIS santri untuk mencari..."
                     oninput="filterSantriList()" autocomplete="off" autofocus>
              <button type="button" class="btn btn-outline-secondary text-dark border d-none" id="btnSelectAll" onclick="toggleSelectAllFiltered()">
                <i class="fas fa-check-double text-success me-1"></i> Pilih Semua
              </button>
            </div>

            <div class="border rounded-3 p-2 bg-white shadow-sm" style="max-height:250px;overflow-y:auto;" id="santriChecklist">
              <div id="typeHintMessage" class="p-4 text-center text-muted small">
                <i class="fas fa-keyboard fa-2x mb-2 text-secondary d-block"></i>
                Ketik nama atau NIS santri pada kolom pencarian di atas untuk memunculkan pilihan.
              </div>

              <?php foreach ($activeSantri as $s): ?>
                <?php $isChecked = (in_array($s['id'], $santri_ids) || $s['id'] == $santri_id_single); ?>
                <div class="form-check santri-item p-2 border-bottom <?= $isChecked ? '' : 'd-none' ?>"
                     data-search="<?= strtolower(htmlspecialchars($s['nama'] . ' ' . $s['nis'] . ' ' . $s['kamar'])) ?>">
                  <input class="form-check-input santri-checkbox ms-1 me-2" type="checkbox"
                         name="santri_ids[]" value="<?= $s['id'] ?>" id="santri_<?= $s['id'] ?>"
                         <?= $isChecked ? 'checked' : '' ?> onchange="updateSelectedCount()"
                         style="border-color:#94a3b8;cursor:pointer;">
                  <label class="form-check-label text-dark w-100" for="santri_<?= $s['id'] ?>">
                    <strong><?= htmlspecialchars($s['nama']) ?></strong>
                    <span class="text-muted small ms-2">(NIS: <?= htmlspecialchars($s['nis']) ?> | Kamar: <?= htmlspecialchars($s['kamar']) ?>)</span>
                    <span class="badge bg-success text-white small ms-1 selected-tag <?= $isChecked ? '' : 'd-none' ?>">Terpilih</span>
                  </label>
                </div>
              <?php endforeach; ?>

              <div id="noMatchMessage" class="p-4 text-center text-danger small d-none">
                <i class="fas fa-exclamation-circle fa-2x mb-2 d-block"></i>
                Tidak ditemukan santri yang cocok.
              </div>
            </div>

            <div class="mt-2 d-flex justify-content-between align-items-center small text-muted">
              <span><i class="fas fa-info-circle text-primary me-1"></i> Centang santri yang hendak diizinkan.</span>
              <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none p-0" onclick="clearAllSelections()">
                <i class="fas fa-times me-1"></i> Hapus Semua Pilihan
              </button>
            </div>
          </div>

          <!-- 2. Jenis Izin -->
          <div class="border-top pt-3 mb-3">
            <label for="jenis_izin" class="form-label fw-semibold">2. Jenis Izin <span class="text-danger">*</span></label>
            <select class="form-select" id="jenis_izin" name="jenis_izin" required>
              <option value="Pulang" <?= $jenis_izin == 'Pulang' ? 'selected' : '' ?>>Pulang (Rumah / Keluarga)</option>
              <option value="Sakit"  <?= $jenis_izin == 'Sakit'  ? 'selected' : '' ?>>Sakit (Rest / UKS / Rumah Sakit)</option>
              <option value="Acara"  <?= $jenis_izin == 'Acara'  ? 'selected' : '' ?>>Acara (Lomba / Undangan / Kegiatan)</option>
            </select>
          </div>

          <!-- 3. Periode Izin -->
          <div class="mb-3">
            <label class="form-label fw-semibold">3. Periode Izin <span class="text-danger">*</span></label>
            <div class="row g-3">
              <div class="col-6">
                <label for="tanggal" class="form-label small text-muted mb-1">
                  <i class="fas fa-calendar-day text-primary me-1"></i> Tanggal Mulai
                </label>
                <input type="date" class="form-control" id="tanggal" name="tanggal"
                       value="<?= htmlspecialchars($tanggal) ?>"
                       onchange="updateMinSelesai()" required>
              </div>
              <div class="col-6">
                <label for="tanggal_selesai" class="form-label small text-muted mb-1">
                  <i class="fas fa-calendar-check text-success me-1"></i> Tanggal Selesai
                </label>
                <input type="date" class="form-control" id="tanggal_selesai" name="tanggal_selesai"
                       value="<?= htmlspecialchars($tanggal_selesai) ?>"
                       min="<?= htmlspecialchars($tanggal) ?>" required>
              </div>
            </div>
            <div class="small text-muted mt-2">
              <i class="fas fa-info-circle text-primary me-1"></i>
              Izin otomatis masuk ke <strong>Riwayat Izin</strong> setelah tanggal selesai terlampaui.
            </div>
          </div>

          <!-- 4. Keterangan -->
          <div class="mb-4">
            <label for="keterangan" class="form-label fw-semibold">4. Keterangan / Alasan Izin <span class="text-danger">*</span></label>
            <textarea class="form-control" id="keterangan" name="keterangan" rows="3"
                      placeholder="Tuliskan alasan perizinan secara jelas..." required><?= htmlspecialchars($keterangan) ?></textarea>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <a href="<?= base_url('modules/perizinan/index.php') ?>" class="btn btn-light rounded-pill px-4">Batal</a>
            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
              <i class="fas fa-save me-1"></i> Simpan Perizinan
            </button>
          </div>

        </form>
      </div>
    </div>

  </div>
</div>

<script>
function updateMinSelesai() {
  const mulai = document.getElementById('tanggal').value;
  const selesaiInput = document.getElementById('tanggal_selesai');
  selesaiInput.min = mulai;
  if (selesaiInput.value < mulai) selesaiInput.value = mulai;
}

function filterSantriList() {
  const keyword = document.getElementById('searchKeyword').value.toLowerCase().trim();
  const items = document.querySelectorAll('.santri-item');
  const typeHint = document.getElementById('typeHintMessage');
  const noMatchMsg = document.getElementById('noMatchMessage');
  const btnSelectAll = document.getElementById('btnSelectAll');
  let matchCount = 0;

  if (keyword.length === 0) {
    let anyChecked = false;
    items.forEach(item => {
      const cb = item.querySelector('.santri-checkbox');
      if (cb && cb.checked) { item.classList.remove('d-none'); anyChecked = true; }
      else item.classList.add('d-none');
    });
    typeHint.classList.toggle('d-none', anyChecked);
    noMatchMsg.classList.add('d-none');
    btnSelectAll.classList.add('d-none');
    return;
  }

  typeHint.classList.add('d-none');
  items.forEach(item => {
    const txt = item.getAttribute('data-search') || '';
    if (txt.includes(keyword)) { item.classList.remove('d-none'); matchCount++; }
    else item.classList.add('d-none');
  });

  noMatchMsg.classList.toggle('d-none', matchCount > 0);
  btnSelectAll.classList.toggle('d-none', matchCount === 0);
}

function updateSelectedCount() {
  const checked = document.querySelectorAll('.santri-checkbox:checked');
  const counter = document.getElementById('selectedCounter');
  counter.innerHTML = checked.length + ' Santri Dipilih';
  counter.className = checked.length > 0
    ? 'badge bg-success text-white px-2 py-1 rounded-pill'
    : 'badge bg-secondary text-white px-2 py-1 rounded-pill';

  document.querySelectorAll('.santri-item').forEach(item => {
    const cb = item.querySelector('.santri-checkbox');
    const tag = item.querySelector('.selected-tag');
    if (tag) tag.classList.toggle('d-none', !(cb && cb.checked));
  });
}

function toggleSelectAllFiltered() {
  const visible = document.querySelectorAll('.santri-item:not(.d-none) .santri-checkbox');
  let allChecked = true;
  visible.forEach(cb => { if (!cb.checked) allChecked = false; });
  visible.forEach(cb => { cb.checked = !allChecked; });
  updateSelectedCount();
}

function clearAllSelections() {
  document.querySelectorAll('.santri-checkbox').forEach(cb => cb.checked = false);
  updateSelectedCount();
  filterSantriList();
}

document.addEventListener('DOMContentLoaded', function() {
  updateSelectedCount();
  filterSantriList();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
