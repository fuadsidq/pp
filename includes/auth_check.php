<?php
/**
 * Session Authentication Middleware
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    set_flash_message('danger', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

/**
 * Check admin role access helper
 */
function require_admin() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        set_flash_message('danger', 'Akses ditolak! Fitur ini hanya dapat diakses oleh Admin.');
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

/**
 * Get current user active permissions array
 */
function get_user_permissions($pdo = null) {
    if (isset($_SESSION['user_permissions']) && is_array($_SESSION['user_permissions'])) {
        return $_SESSION['user_permissions'];
    }

    // Admin has access to all modules by default
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        $all = ['santri', 'perizinan', 'pelanggaran', 'spp', 'pengurus'];
        $_SESSION['user_permissions'] = $all;
        return $all;
    }

    // Refresh from DB if PDO provided
    if ($pdo && isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT permissions, role FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $u = $stmt->fetch();
            if ($u) {
                if ($u['role'] === 'admin') {
                    $perms = ['santri', 'perizinan', 'pelanggaran', 'spp', 'pengurus'];
                } else {
                    $perms = json_decode($u['permissions'] ?? '[]', true);
                    if (!is_array($perms)) {
                        // Fallback defaults based on role
                        $perms = ($u['role'] === 'pengurus_1') ? ['perizinan', 'pelanggaran'] : ['santri', 'spp'];
                    }
                }
                $_SESSION['user_permissions'] = $perms;
                return $perms;
            }
        } catch (Exception $e) {}
    }

    // Fallback based on session role
    $r = $_SESSION['user_role'] ?? 'pengurus_1';
    return ($r === 'pengurus_1') ? ['perizinan', 'pelanggaran'] : ['santri', 'spp'];
}

/**
 * Check if user has permission to access a specific module
 * @param string $module_key (santri | perizinan | pelanggaran | spp | pengurus)
 */
function has_permission($module_key) {
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        return true;
    }
    $perms = get_user_permissions();
    return in_array($module_key, $perms);
}

/**
 * Enforce module permission access guard
 * @param string $module_key
 */
function require_permission($module_key) {
    if (!has_permission($module_key)) {
        set_flash_message('danger', 'Akses ditolak! Akun Anda tidak diberi izin oleh Admin untuk membuka menu ini.');
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

/**
 * Check permission access for specific roles (Backward compatibility)
 * @param array|string $allowed_roles
 */
function require_role($allowed_roles) {
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    if (!in_array('admin', $allowed_roles)) {
        $allowed_roles[] = 'admin';
    }

    $current_role = $_SESSION['user_role'] ?? '';
    if (!in_array($current_role, $allowed_roles)) {
        set_flash_message('danger', 'Akses ditolak! Akun Anda tidak memiliki izin untuk mengakses halaman tersebut.');
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

/**
 * Helper to check if current user has any of the given roles (Backward compatibility)
 */
function has_role($roles) {
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!in_array('admin', $roles)) {
        $roles[] = 'admin';
    }
    return in_array($_SESSION['user_role'] ?? '', $roles);
}
