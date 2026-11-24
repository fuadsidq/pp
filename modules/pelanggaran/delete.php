<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_permission('pelanggaran');

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmtDelete = $pdo->prepare("DELETE FROM pelanggaran WHERE id = :id");
    if ($stmtDelete->execute(['id' => $id])) {
        set_flash_message('success', 'Catatan pelanggaran berhasil dihapus.');
    } else {
        set_flash_message('danger', 'Gagal menghapus catatan pelanggaran.');
    }
}

header('Location: ' . base_url('modules/pelanggaran/index.php'));
exit;
