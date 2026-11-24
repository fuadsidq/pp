<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_permission('spp');

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmtDelete = $pdo->prepare("DELETE FROM tagihan_spp WHERE id = :id");
    if ($stmtDelete->execute(['id' => $id])) {
        set_flash_message('success', 'Data tagihan SPP berhasil dihapus.');
    } else {
        set_flash_message('danger', 'Gagal menghapus data tagihan SPP.');
    }
}

header('Location: ' . base_url('modules/spp/index.php'));
exit;
