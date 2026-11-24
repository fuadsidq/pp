<?php
// Start output buffering early to allow redirects after header includes
if (ob_get_level() === 0) {
    ob_start();
}

/**
 * Database Connection Config using PDO (PHP Data Objects)
 * Sistem Informasi Manajemen Pondok Pesantren
 */

$host     = 'localhost';
$dbname   = 'db_pondok';
$username = 'root';
$password = ''; // Default XAMPP/Laragon password is empty
$charset  = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Attempt auto database creation if missing
    try {
        $pdoTemp = new PDO("mysql:host={$host};charset={$charset}", $username, $password);
        $pdoTemp->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $pdo = new PDO($dsn, $username, $password, $options);
        
        // Auto-run schema if database was newly created
        $sqlPath = __DIR__ . '/../database.sql';
        if (file_exists($sqlPath)) {
            $sqlContent = file_get_contents($sqlPath);
            $pdo->exec($sqlContent);
        }
    } catch (PDOException $ex) {
        die("<div style='font-family:sans-serif; padding:20px; color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; border-radius:8px;'>
            <h3 style='margin-top:0;'>Gagal Terhubung ke Database!</h3>
            <p><strong>Pesan Error:</strong> " . htmlspecialchars($ex->getMessage()) . "</p>
            <p>Silakan pastikan <strong>MySQL (XAMPP / Laragon)</strong> sudah berjalan dan file <code>database.sql</code> telah diimpor.</p>
            </div>");
    }
}

/**
 * Base URL helper function
 */
function base_url($path = '') {
    // Detect protocol
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    // Get domain/host
    $host = $_SERVER['HTTP_HOST'];
    
    // Determine relative root directory of project
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Normalize path to root folder level
    $root = rtrim($scriptDir, '/');
    
    // If inside auth or modules directory, navigate up
    if (strpos($root, '/auth') !== false) {
        $root = preg_replace('/\/auth.*/', '', $root);
    } elseif (strpos($root, '/modules') !== false) {
        $root = preg_replace('/\/modules.*/', '', $root);
    } elseif (strpos($root, '/config') !== false) {
        $root = preg_replace('/\/config.*/', '', $root);
    }
    
    return $protocol . $host . $root . '/' . ltrim($path, '/');
}

/**
 * Helper to set flash notification message
 */
function set_flash_message($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type' => $type, // success, danger, warning, info
        'text' => $message
    ];
}

/**
 * Helper to display and clear flash notification message
 */
function display_flash_message() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        $icon = $msg['type'] == 'success' ? 'fa-check-circle' : ($msg['type'] == 'danger' ? 'fa-exclamation-triangle' : 'fa-info-circle');
        echo "<div class='alert alert-{$msg['type']} alert-dismissible fade show shadow-sm' role='alert'>
                <i class='fas {$icon} me-2'></i> " . htmlspecialchars($msg['text']) . "
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
}
