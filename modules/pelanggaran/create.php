<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/pelanggaran_rules.php';
require_permission('pelanggaran');

// Fetch active santri list
$stmtSantri = $pdo->query("SELECT id, nis, nama, kamar FROM santri WHERE status = 'Aktif' ORDER BY nama ASC");
$activeSantri = $stmtSantri->fetchAll();

// Fetch accumulated points per santri for Rule-Based impact preview
$stmtTotals = $pdo->query("SELECT santri_id, SUM(poin) as total FROM pelanggaran GROUP BY santri_id");
$currentTotals = $stmtTotals->fetchAll(PDO::FETCH_KEY_PAIR);

$error = '';
$santri_id_single  = intval($_GET['santri_id'] ?? 0);
$santri_ids        = [];
$jenis_pelanggaran = '';
$poin              = 5;
$tanggal           = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $santri_ids = $_POST['santri_ids'] ?? [];
    if (!is_array($santri_ids)) {
        $santri_ids = [$santri_ids];
    }
    $santri_ids = array_map('intval', array_filter($santri_ids));

    $jenis_pelanggaran = trim($_POST['jenis_pelanggaran'] ?? '');
    $poin              = intval($_POST['poin'] ?? 0);
    $tanggal           = trim($_POST['tanggal'] ?? date('Y-m-d'));

    if (empty($santri_ids)) {
        $error = 'Silakan pilih minimal 1 (satu) santri dari daftar!';
    } elseif (empty($jenis_pelanggaran) || $poin <= 0 || empty($tanggal)) {
        $error = 'Bentuk Pelanggaran, Poin (>0), dan Tanggal wajib diisi!';
    } else {
        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO pelanggaran (santri_id, jenis_pelanggaran, poin, tanggal) VALUES (:santri_id, :jenis_pelanggaran, :poin, :tanggal)");
            $countSuccess = 0;

            foreach ($santri_ids as $sid) {
                if ($sid > 0) {
                    $stmtInsert->execute([
                        'santri_id'         => $sid,
                        'jenis_pelanggaran' => $jenis_pelanggaran,
                        'poin'              => $poin,
                        'tanggal'           => $tanggal
                    ]);
                    $countSuccess++;
                }
            }

            $pdo->commit();
            set_flash_message('success', "Berhasil mencatat poin pelanggaran untuk {$countSuccess} santri!");
            header('Location: ' . base_url('modules/pelanggaran/index.php'));
            exit;
        } catch (Exception $ex) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan sistem saat menyimpan data: ' . $ex->getMessage();
        }
    }
}

$thresholds = get_rule_thresholds($pdo);
$tR = $thresholds['ringan'];
$tS = $thresholds['sedang'];
$tB = $thresholds['berat'];
$rule_defs  = get_rule_definitions($pdo);

$page_title = 'Catat Pelanggaran Santri';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-10 col-lg-8">
    
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="fw-bold text-dark mb-0"><i class="fas fa-exclamation-triangle text-warning me-2"></i> Form Catat Pelanggaran Santri</h4>
      </div>
      <a href="<?= base_url('modules/pelanggaran/index.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
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
        <form action="" method="POST" id="formPelanggaran">
          
          <!-- 1. Multi-Select Santri Checklist Section -->
          <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label fw-bold text-dark mb-0">1. Pilih Santri yang Melanggar <span class="text-danger">*</span></label>
              <span id="selectedCounter" class="badge bg-warning text-dark px-2.5 py-1.5 rounded-pill fs-6">0 Santri Dipilih</span>
            </div>

            <!-- Search Bar Input -->
            <div class="input-group mb-2">
              <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-warning"></i></span>
              <input type="text" id="searchKeyword" class="form-control bg-light border-start-0" placeholder="Ketik nama atau NIS santri untuk mencari..." oninput="filterSantriList()" autocomplete="off" autofocus>
              <button type="button" class="btn btn-outline-secondary text-dark border d-none" id="btnSelectAll" onclick="toggleSelectAllFiltered()">
                <i class="fas fa-check-double text-warning me-1"></i> Pilih Semua Tampil
              </button>
            </div>

            <!-- Scrollable Santri List -->
            <div class="border rounded-3 p-2 bg-white shadow-sm" style="max-height: 250px; overflow-y: auto;" id="santriChecklist">
              <!-- Hint ketika belum mengetik -->
              <div id="typeHintMessage" class="p-4 text-center text-muted small">
                <i class="fas fa-keyboard fa-2x mb-2 text-secondary d-block"></i>
                Ketik nama atau NIS santri pada kolom pencarian di atas untuk memunculkan pilihan santri.
              </div>

              <?php foreach ($activeSantri as $s): ?>
                <?php 
                  $isChecked = (in_array($s['id'], $santri_ids) || $s['id'] == $santri_id_single);
                  $cPoin = intval($currentTotals[$s['id']] ?? 0);
                  $cEval = evaluate_rule_based($cPoin, $pdo);
                ?>
                <div class="form-check santri-item p-2 border-bottom d-flex align-items-center justify-content-between <?= $isChecked ? '' : 'd-none' ?>" data-search="<?= strtolower(htmlspecialchars($s['nama'] . ' ' . $s['nis'] . ' ' . $s['kamar'])) ?>">
                  <div class="d-flex align-items-center flex-grow-1">
                    <input class="form-check-input santri-checkbox ms-1 me-2" type="checkbox" name="santri_ids[]" value="<?= $s['id'] ?>" id="santri_<?= $s['id'] ?>" <?= $isChecked ? 'checked' : '' ?> data-current-poin="<?= $cPoin ?>" data-nama="<?= htmlspecialchars($s['nama']) ?>" onchange="updateSelectedCount()" style="border-color: #94a3b8; cursor: pointer;">
                    <label class="form-check-label text-dark w-100 cursor-pointer" for="santri_<?= $s['id'] ?>">
                      <strong class="text-dark"><?= htmlspecialchars($s['nama']) ?></strong>
                      <span class="text-muted small ms-2">(NIS: <?= htmlspecialchars($s['nis']) ?> | Kamar: <?= htmlspecialchars($s['kamar']) ?>)</span>
                      <span class="badge bg-warning text-dark small ms-1 selected-tag <?= $isChecked ? '' : 'd-none' ?>">Terpilih</span>
                    </label>
                  </div>
                  <div class="text-end ps-2">
                    <span class="badge <?= $cEval['badge_soft'] ?> small" title="Status saat ini: <?= $cEval['kategori'] ?>">
                      <?= $cPoin ?> Poin (<?= $cEval['tingkat'] ?>)
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
              
              <div id="noMatchMessage" class="p-4 text-center text-danger small d-none">
                <i class="fas fa-exclamation-circle fa-2x mb-2 d-block"></i>
                Tidak ditemukan santri yang cocok dengan kata kunci tersebut.
              </div>
            </div>

            <!-- Footer Action under List -->
            <div class="mt-2 d-flex justify-content-between align-items-center small text-muted">
              <span><i class="fas fa-info-circle text-primary me-1"></i> Centang kotak di sebelah nama santri yang melakukan pelanggaran.</span>
              <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none p-0" onclick="clearAllSelections()">
                <i class="fas fa-times me-1"></i> Hapus Semua Pilihan
              </button>
            </div>
          </div>

          <!-- 2. Detail Pelanggaran -->
          <div class="border-top pt-3 mb-3">
            <label for="jenis_pelanggaran" class="form-label fw-semibold">2. Bentuk / Jenis Pelanggaran <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="jenis_pelanggaran" name="jenis_pelanggaran" value="<?= htmlspecialchars($jenis_pelanggaran) ?>" placeholder="Contoh: Terlambat shalat berjamaah / Keluar area pondok tanpa izin" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
              <label for="poin" class="form-label fw-semibold">Bobot Poin Pelanggaran <span class="text-danger">*</span></label>
              <input type="number" min="1" max="100" class="form-control" id="poin" name="poin" value="<?= $poin ?>" oninput="updateRuleImpactPreview()" required>
              <div class="form-text">Batas Sanksi: Ringan (≥<?= $tR ?>), Sedang (≥<?= $tS ?>), Berat (≥<?= $tB ?>).</div>
            </div>
            
            <div class="col-12 col-md-6">
              <label for="tanggal" class="form-label fw-semibold">Tanggal Kejadian <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" required>
            </div>
          </div>

          <!-- 3. Rule-Based Live Impact Predictor -->
          <div class="p-3 rounded-3 border bg-light mb-4" id="ruleImpactCard">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="small fw-bold text-dark"><i class="fas fa-brain text-primary me-1"></i> Proyeksi Algoritma Rule-Based Pasca Simpan:</span>
              <span id="impactBadgeRule" class="badge bg-secondary">Pilih Santri Terlebih Dahulu</span>
            </div>
            <div id="impactDetails" class="small text-muted">
              Pilih satu atau lebih santri di atas untuk melihat prediksi perubahan tingkat hukuman dan penerbitan Surat Peringatan (SP).
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <a href="<?= base_url('modules/pelanggaran/index.php') ?>" class="btn btn-light rounded-pill px-4">Batal</a>
            <button type="submit" class="btn btn-warning text-dark rounded-pill px-4 fw-semibold">
              <i class="fas fa-save me-1.5"></i> Simpan Pelanggaran Santri
            </button>
          </div>

        </form>
      </div>
    </div>

  </div>
</div>

<script>
const THRESHOLDS = <?= json_encode($thresholds) ?>;
const APP_RULES  = <?= json_encode($rule_defs) ?>;

function evaluateRuleBasedJS(poin) {
  poin = parseInt(poin) || 0;
  if (poin < 0) poin = 0;
  if (poin >= THRESHOLDS.berat) return APP_RULES.R4;
  if (poin >= THRESHOLDS.sedang) return APP_RULES.R3;
  if (poin >= THRESHOLDS.ringan) return APP_RULES.R2;
  return APP_RULES.R1;
}

function updateRuleImpactPreview() {
  const checked = document.querySelectorAll('.santri-checkbox:checked');
  const inputPoin = parseInt(document.getElementById('poin').value) || 0;
  const impactCard = document.getElementById('ruleImpactCard');
  const impactBadge = document.getElementById('impactBadgeRule');
  const impactDetails = document.getElementById('impactDetails');

  if (checked.length === 0) {
    impactBadge.className = 'badge bg-secondary';
    impactBadge.textContent = 'Belum Ada Santri Dipilih';
    impactDetails.innerHTML = 'Pilih santri dari daftar di atas untuk melihat simulasi hasil evaluasi sistem berbasis aturan.';
    return;
  }

  if (checked.length === 1) {
    const cb = checked[0];
    const nama = cb.getAttribute('data-nama');
    const currPoin = parseInt(cb.getAttribute('data-current-poin')) || 0;
    const projPoin = currPoin + inputPoin;
    
    const currEval = evaluateRuleBasedJS(currPoin);
    const projEval = evaluateRuleBasedJS(projPoin);

    impactBadge.className = 'badge ' + projEval.badge_bg;
    impactBadge.textContent = projEval.code + ': ' + projEval.kategori;

    let alertHtml = '';
    if (projEval.prioritas > currEval.prioritas) {
      alertHtml = `<div class="alert alert-warning py-1.5 px-2.5 mb-2 small rounded-2">
        <i class="fas fa-exclamation-triangle me-1"></i> <strong>Perhatian:</strong> Santri ini akan <strong>naik tingkat sanksi</strong> dari <em>${currEval.kategori}</em> menjadi <strong>${projEval.kategori}</strong> (${projEval.sp})!
      </div>`;
    }

    impactDetails.innerHTML = `
      ${alertHtml}
      <div class="d-flex align-items-center justify-content-between mb-1">
        <span><strong>${nama}</strong></span>
        <span>${currPoin} Poin <i class="fas fa-arrow-right text-muted mx-1"></i> <strong class="${projEval.text_color} fs-6">${projPoin} Poin</strong></span>
      </div>
      <div class="text-muted">
        <strong>Ketentuan Sanksi:</strong> ${projEval.sanksi_singkat} | <strong>Rekomendasi Tindakan:</strong> ${projEval.tindakan}
      </div>
    `;
  } else {
    impactBadge.className = 'badge bg-primary';
    impactBadge.textContent = checked.length + ' Santri Akan Terkena Poin';

    let summaryHtml = `<ul class="list-unstyled mb-0">`;
    checked.forEach(cb => {
      const nama = cb.getAttribute('data-nama');
      const currPoin = parseInt(cb.getAttribute('data-current-poin')) || 0;
      const projPoin = currPoin + inputPoin;
      const projEval = evaluateRuleBasedJS(projPoin);

      summaryHtml += `<li class="d-flex justify-content-between align-items-center border-bottom py-1">
        <span>${nama}</span>
        <span>
          ${currPoin} + ${inputPoin} = <strong>${projPoin} Poin</strong> 
          <span class="badge ${projEval.badge_soft} ms-1">${projEval.kategori}</span>
        </span>
      </li>`;
    });
    summaryHtml += `</ul>`;
    impactDetails.innerHTML = summaryHtml;
  }
}

function filterSantriList() {
  const keyword = document.getElementById('searchKeyword').value.toLowerCase().trim();
  const items = document.querySelectorAll('.santri-item');
  const typeHint = document.getElementById('typeHintMessage');
  const noMatchMsg = document.getElementById('noMatchMessage');
  const btnSelectAll = document.getElementById('btnSelectAll');
  let matchCount = 0;

  if (keyword.length === 0) {
    // Mode tanpa ketikan: hanya tampilkan santri yang SUDAH dicentang
    let anyChecked = false;
    items.forEach(item => {
      const cb = item.querySelector('.santri-checkbox');
      if (cb && cb.checked) {
        item.classList.remove('d-none');
        anyChecked = true;
      } else {
        item.classList.add('d-none');
      }
    });

    if (anyChecked) {
      typeHint.classList.add('d-none');
    } else {
      typeHint.classList.remove('d-none');
    }
    noMatchMsg.classList.add('d-none');
    btnSelectAll.classList.add('d-none');
    return;
  }

  // Jika user mengetik kata kunci:
  typeHint.classList.add('d-none');
  items.forEach(item => {
    const searchText = item.getAttribute('data-search') || '';
    if (searchText.includes(keyword)) {
      item.classList.remove('d-none');
      matchCount++;
    } else {
      item.classList.add('d-none');
    }
  });

  if (matchCount === 0) {
    noMatchMsg.classList.remove('d-none');
    btnSelectAll.classList.add('d-none');
  } else {
    noMatchMsg.classList.add('d-none');
    btnSelectAll.classList.remove('d-none');
  }
}

function updateSelectedCount() {
  const checked = document.querySelectorAll('.santri-checkbox:checked');
  const counter = document.getElementById('selectedCounter');
  counter.innerHTML = checked.length + ' Santri Dipilih';
  if (checked.length > 0) {
    counter.className = 'badge bg-warning text-dark px-2.5 py-1.5 rounded-pill fs-6';
  } else {
    counter.className = 'badge bg-secondary text-white px-2.5 py-1.5 rounded-pill fs-6';
  }

  // Update badge 'Terpilih' pada item
  document.querySelectorAll('.santri-item').forEach(item => {
    const cb = item.querySelector('.santri-checkbox');
    const tag = item.querySelector('.selected-tag');
    if (tag) {
      if (cb && cb.checked) {
        tag.classList.remove('d-none');
      } else {
        tag.classList.add('d-none');
      }
    }
  });

  updateRuleImpactPreview();
}

function toggleSelectAllFiltered() {
  const visibleCheckboxes = document.querySelectorAll('.santri-item:not(.d-none) .santri-checkbox');
  let allChecked = true;
  visibleCheckboxes.forEach(cb => { if (!cb.checked) allChecked = false; });

  visibleCheckboxes.forEach(cb => { cb.checked = !allChecked; });
  updateSelectedCount();
}

function clearAllSelections() {
  const checkboxes = document.querySelectorAll('.santri-checkbox');
  checkboxes.forEach(cb => cb.checked = false);
  updateSelectedCount();
  filterSantriList();
}

document.addEventListener('DOMContentLoaded', function() {
  updateSelectedCount();
  filterSantriList();
  updateRuleImpactPreview();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
