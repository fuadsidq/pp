<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Download template import santri in CSV format (Excel compatible)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=template_import_santri.csv');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Microsoft Excel auto-encoding recognition
fputs($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, ['nis', 'nama', 'kamar', 'status'], ';');

// Example rows
fputcsv($output, ['2024006', 'Budi Santoso', 'Al-Ghazali 03', 'Aktif'], ';');
fputcsv($output, ['2024007', 'Siti Rahmawati', 'Aisyah 03', 'Aktif'], ';');
fputcsv($output, ['2024008', 'Muhammad Hasan', 'Ibnu Sina 01', 'Aktif'], ';');

fclose($output);
exit;
