<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_permission('santri');

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    // Get Santri name first
    $stmtName = $pdo->prepare("SELECT nama FROM santri WHERE id = :id LIMIT 1");
    $stmtName->execute(['id' => $id]);
    $santri = $stmtName->fetch();

    if ($santri) {
        // Delete using Prepared Statement
        $stmtDelete = $pdo->prepare("DELETE FROM santri WHERE id = :id");
        if ($stmtDelete->execute(['id' => $id])) {
            set_flash_message('success', 'Data santri ' . htmlspecialchars($santri['nama']) . ' berhasil dihapus beserta riwayatnya.');
        } else {
            set_flash_message('danger', 'Gagal menghapus data santri.');
        }
    } else {
        set_flash_message('danger', 'Data santri tidak ditemukan.');
    }
}

header('Location: ' . base_url('modules/santri/index.php'));
exit;
