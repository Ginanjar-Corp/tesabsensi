<?php
// project-root/includes/auth.php

// Memulai sesi jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Memeriksa apakah pengguna sudah login.
 *
 * @return bool True jika pengguna sudah login, false jika tidak.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Memeriksa peran pengguna yang sedang login.
 *
 * @param string|array $required_role Nama peran yang dibutuhkan (misal: 'Admin', atau ['Admin', 'Guru']).
 * @return bool True jika pengguna memiliki peran yang dibutuhkan, false jika tidak.
 */
function check_role($required_role) {
    if (!is_logged_in()) {
        return false;
    }
    $user_role = $_SESSION['role_name'] ?? '';

    if (is_array($required_role)) {
        return in_array($user_role, $required_role);
    }
    return $user_role === $required_role;
}

/**
 * Memastikan pengguna sudah login. Jika tidak, arahkan ke halaman login.
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('error', 'Anda harus login untuk mengakses halaman ini.');
        redirect(ROOT_URL . 'login.php');
    }
}

/**
 * Memastikan pengguna memiliki peran yang dibutuhkan. Jika tidak, arahkan ke halaman tidak diizinkan.
 *
 * @param string|array $required_role Nama peran yang dibutuhkan.
 */
function require_role($required_role) {
    if (!check_role($required_role)) {
        set_flash('error', 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        redirect(ROOT_URL . 'index.php'); // Arahkan ke index atau halaman error 403
    }
}

/**
 * Fungsi untuk menangani proses login yang berhasil.
 * Mengatur variabel sesi dan meregenerasi ID sesi.
 *
 * @param array $user_data Data pengguna dari database (id, username, role_name, related_id, dll).
 */
function login_user(array $user_data) {
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['role_name'] = $user_data['role_name'];
    // Pastikan related_id selalu ada, set ke null jika tidak ada di $user_data
    $_SESSION['related_id'] = $user_data['related_id'] ?? null; // --- PERBAIKAN DI SINI ---
    $_SESSION['user_data'] = $user_data; // Simpan semua data user jika diperlukan
    $_SESSION['user'] = $user_data; // Pastikan ini juga selalu ada dan lengkap

    // Regenerasi ID sesi untuk keamanan
    session_regenerate_id(true);
}

/**
 * Fungsi untuk menghapus sesi pengguna saat logout.
 */
function logout_user() {
    // Hapus semua variabel sesi
    $_SESSION = array();

    // Hapus cookie sesi
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Hancurkan sesi
    session_destroy();
}
