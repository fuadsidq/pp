<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_permission('spp');

$id     = intval($_GET['id'] ?? 0);
$action = trim($_GET['action'] ?? 'pay'); // pay or unpay

if ($id > 0) {
    if ($action === 'unpay') {
        // Toggle to Belum Lunas
        $stmt = $pdo->prepare("UPDATE tagihan_spp SET status = 'Belum Lunas', tanggal_bayar = NULL WHERE id = :id");
        if ($stmt->execute(['id' => $id])) {
            set_flash_message('warning', 'Status pembayaran SPP diubah menjadi Belum Lunas.');
        } else {
            set_flash_message('danger', 'Gagal memperbarui status SPP.');
        }
    } else {
        // Toggle to Lunas
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("UPDATE tagihan_spp SET status = 'Lunas', tanggal_bayar = :tanggal_bayar WHERE id = :id");
        if ($stmt->execute(['id' => $id, 'tanggal_bayar' => $today])) {
            set_flash_message('success', 'Pembayaran SPP berhasil dicatat sebagai LUNAS!');
        } else {
            set_flash_message('danger', 'Gagal memperbarui pembayaran SPP.');
        }
    }
}

header('Location: ' . base_url('modules/spp/index.php'));
exit;
