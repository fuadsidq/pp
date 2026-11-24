<?php
require_once __DIR__ . '/../../includes/header.php';

// Pastikan hanya admin yang bisa akses
require_admin();

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    // Pastikan tidak menghapus diri sendiri atau akun admin
    $stmtCek = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmtCek->execute(['id' => $id]);
    $target = $stmtCek->fetch();

    if (!$target) {
        set_flash_message('danger', 'Data pengurus tidak ditemukan!');
    } elseif ($target['role'] === 'admin') {
        set_flash_message('danger', 'Tidak dapat menghapus akun Administrator Utama!');
    } elseif ($target['id'] == $_SESSION['user_id']) {
        set_flash_message('danger', 'Anda tidak dapat menghapus akun Anda sendiri saat sedang login!');
    } else {
        $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmtDel->execute(['id' => $id]);
        set_flash_message('success', "Akun pengurus '{$target['nama_lengkap']}' berhasil dihapus.");
    }
}

header('Location: ' . base_url('modules/pengurus/index.php'));
exit;
