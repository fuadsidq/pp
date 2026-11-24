<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Pastikan hanya admin yang bisa akses
require_admin();

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role IN ('pengurus_1', 'pengurus_2', 'pengurus')");
$stmt->execute(['id' => $id]);
$pengurus = $stmt->fetch();

if (!$pengurus) {
    set_flash_message('danger', 'Data pengurus tidak ditemukan!');
    header('Location: ' . base_url('modules/pengurus/index.php'));
    exit;
}

$error = '';
$username     = $pengurus['username'];
$nama_lengkap = $pengurus['nama_lengkap'];
$role         = $pengurus['role'] === 'pengurus' ? 'pengurus_1' : $pengurus['role'];

// Parse current permissions
$permissions = json_decode($pengurus['permissions'] ?? '[]', true);
if (!is_array($permissions) || empty($permissions)) {
    $permissions = ($role === 'pengurus_1') ? ['perizinan', 'pelanggaran'] : ['santri', 'spp'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $password     = trim($_POST['password'] ?? '');
    $role         = trim($_POST['role'] ?? 'pengurus_1');
    $perms_input  = $_POST['permissions'] ?? [];

    if (!is_array($perms_input)) {
        $perms_input = [];
    }

    $allowed_modules = ['santri', 'perizinan', 'pelanggaran', 'spp'];
    $permissions = array_values(array_intersect($allowed_modules, $perms_input));

    if (empty($username) || empty($nama_lengkap)) {
        $error = 'Nama lengkap dan username tidak boleh kosong!';
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = 'Password baru minimal 6 karakter!';
    } elseif (!in_array($role, ['pengurus_1', 'pengurus_2'])) {
        $error = 'Role pengurus tidak valid!';
    } else {
        // Cek username unik untuk id selain yang ini
        $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u AND id != :id");
        $stmtCek->execute(['u' => $username, 'id' => $id]);
        if ($stmtCek->fetchColumn() > 0) {
            $error = "Username '{$username}' sudah digunakan oleh akun lain!";
        } else {
            $perms_json = json_encode($permissions);

            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare("UPDATE users SET username = :u, password = :p, nama_lengkap = :n, role = :r, permissions = :perms WHERE id = :id");
                $stmtUpdate->execute([
                    'u'     => $username,
                    'p'     => $hashedPassword,
                    'n'     => $nama_lengkap,
                    'r'     => $role,
                    'perms' => $perms_json,
                    'id'    => $id
                ]);
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE users SET username = :u, nama_lengkap = :n, role = :r, permissions = :perms WHERE id = :id");
                $stmtUpdate->execute([
                    'u'     => $username,
                    'n'     => $nama_lengkap,
                    'r'     => $role,
                    'perms' => $perms_json,
                    'id'    => $id
                ]);
            }

            // Jika mengedit akun yang sedang login sendiri, update session permissions
            if ($id == $_SESSION['user_id']) {
                $_SESSION['user_permissions'] = $permissions;
                $_SESSION['user_nama'] = $nama_lengkap;
                $_SESSION['username'] = $username;
            }

            set_flash_message('success', "Berhasil memperbarui data dan hak akses pengurus '{$nama_lengkap}'!");
            header('Location: ' . base_url('modules/pengurus/index.php'));
            exit;
        }
    }
}

$page_title = 'Edit Data & Hak Akses Pengurus';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-9 col-lg-7">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold text-dark mb-1"><i class="fas fa-user-edit text-primary me-2"></i> Edit Data & Hak Akses Pengurus</h4>
        <p class="text-muted small mb-0">Atur kredensial login dan modul apa saja yang boleh dibuka oleh pengurus ini.</p>
      </div>
      <a href="<?= base_url('modules/pengurus/index.php') ?>" class="btn btn-light rounded-pill px-3">
        <i class="fas fa-arrow-left me-1"></i> Kembali
      </a>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show rounded-4 py-2 px-3 small mb-3" role="alert">
        <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="card-soft p-4">
      <form action="" method="POST">
        
        <div class="mb-3">
          <label for="nama_lengkap" class="form-label fw-bold text-dark small">Nama Lengkap Pengurus <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($nama_lengkap) ?>" required>
        </div>

        <div class="mb-3">
          <label for="username" class="form-label fw-bold text-dark small">Username Login <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-light rounded-start-pill text-muted">@</span>
            <input type="text" class="form-control rounded-end-pill" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>
          </div>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label fw-bold text-dark small">Password Baru <span class="text-muted fw-normal">(Kosongkan jika tidak ingin mengubah)</span></label>
          <input type="password" class="form-control" id="password" name="password" placeholder="Biarkan kosong jika tidak diubah">
        </div>

        <div class="mb-4">
          <label for="role" class="form-label fw-bold text-dark small">Jabatan Utama Pengurus <span class="text-danger">*</span></label>
          <select class="form-select rounded-pill" id="role" name="role" required>
            <option value="pengurus_1" <?= $role === 'pengurus_1' ? 'selected' : '' ?>>Pengurus 1 (Keamanan & Kedisiplinan)</option>
            <option value="pengurus_2" <?= $role === 'pengurus_2' ? 'selected' : '' ?>>Pengurus 2 (Akademik & Administrasi)</option>
          </select>
        </div>

        <!-- ================= HAK AKSES PERMISSION MATRIX ================= -->
        <div class="mb-4 p-3.5 rounded-4 bg-light border">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
              <label class="form-label fw-bold text-dark small mb-0">
                <i class="fas fa-user-lock text-primary me-1.5"></i> Atur Hak Akses Modul untuk Pengurus Ini
              </label>
              <div class="text-muted" style="font-size:0.75rem;">Hapus centang untuk mengunci/menyembunyikan modul dari akun ini.</div>
            </div>
            <button type="button" class="btn btn-link btn-sm text-primary text-decoration-none p-0 fw-semibold" onclick="toggleAllPerms()">Pilih Semua</button>
          </div>

          <div class="row g-2 mt-1">
            <!-- Santri -->
            <div class="col-12 col-sm-6">
              <div class="form-check p-2.5 bg-white rounded-3 border d-flex align-items-center">
                <input class="form-check-input perm-cb ms-1 me-2.5" type="checkbox" name="permissions[]" value="santri" id="p_santri" <?= in_array('santri', $permissions) ? 'checked' : '' ?> style="cursor:pointer;">
                <label class="form-check-label text-dark small cursor-pointer w-100" for="p_santri">
                  <strong class="d-block"><i class="fas fa-user-graduate text-primary me-1"></i> Data Santri</strong>
                  <span class="text-muted" style="font-size:0.72rem;">Melihat, menambah, dan mengedit santri</span>
                </label>
              </div>
            </div>

            <!-- Perizinan -->
            <div class="col-12 col-sm-6">
              <div class="form-check p-2.5 bg-white rounded-3 border d-flex align-items-center">
                <input class="form-check-input perm-cb ms-1 me-2.5" type="checkbox" name="permissions[]" value="perizinan" id="p_perizinan" <?= in_array('perizinan', $permissions) ? 'checked' : '' ?> style="cursor:pointer;">
                <label class="form-check-label text-dark small cursor-pointer w-100" for="p_perizinan">
                  <strong class="d-block"><i class="fas fa-clipboard-check text-success me-1"></i> Perizinan Santri</strong>
                  <span class="text-muted" style="font-size:0.72rem;">Catat izin aktif & riwayat perizinan</span>
                </label>
              </div>
            </div>

            <!-- Pelanggaran -->
            <div class="col-12 col-sm-6">
              <div class="form-check p-2.5 bg-white rounded-3 border d-flex align-items-center">
                <input class="form-check-input perm-cb ms-1 me-2.5" type="checkbox" name="permissions[]" value="pelanggaran" id="p_pelanggaran" <?= in_array('pelanggaran', $permissions) ? 'checked' : '' ?> style="cursor:pointer;">
                <label class="form-check-label text-dark small cursor-pointer w-100" for="p_pelanggaran">
                  <strong class="d-block"><i class="fas fa-gavel text-warning me-1"></i> Poin Pelanggaran</strong>
                  <span class="text-muted" style="font-size:0.72rem;">Catat poin & buka rekap bulanan</span>
                </label>
              </div>
            </div>

            <!-- SPP -->
            <div class="col-12 col-sm-6">
              <div class="form-check p-2.5 bg-white rounded-3 border d-flex align-items-center">
                <input class="form-check-input perm-cb ms-1 me-2.5" type="checkbox" name="permissions[]" value="spp" id="p_spp" <?= in_array('spp', $permissions) ? 'checked' : '' ?> style="cursor:pointer;">
                <label class="form-check-label text-dark small cursor-pointer w-100" for="p_spp">
                  <strong class="d-block"><i class="fas fa-wallet text-info me-1"></i> Keuangan / SPP</strong>
                  <span class="text-muted" style="font-size:0.72rem;">Kelola tagihan & validasi pembayaran SPP</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
          <a href="<?= base_url('modules/pengurus/index.php') ?>" class="btn btn-light rounded-pill px-4">Batal</a>
          <button type="submit" class="btn btn-dark-pill px-4">
            <i class="fas fa-save me-1"></i> Simpan Perubahan Akses
          </button>
        </div>

      </form>
    </div>

  </div>
</div>

<script>
function toggleAllPerms() {
  const cbs = document.querySelectorAll('.perm-cb');
  let anyUnchecked = false;
  cbs.forEach(cb => { if (!cb.checked) anyUnchecked = true; });
  cbs.forEach(cb => { cb.checked = anyUnchecked; });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
