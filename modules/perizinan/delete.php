<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_permission('perizinan');

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmtDelete = $pdo->prepare("DELETE FROM perizinan WHERE id = :id");
    if ($stmtDelete->execute(['id' => $id])) {
        set_flash_message('success', 'Catatan perizinan berhasil dihapus.');
    } else {
        set_flash_message('danger', 'Gagal menghapus data perizinan.');
    }
}

header('Location: ' . base_url('modules/perizinan/index.php'));
exit;
