<?php
// project-root/includes/auth_parent.php

// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Memeriksa apakah orang tua sudah login.
 * Jika belum, akan mengarahkan ke halaman login.
 */
function require_parent_login() {
    if (!isset($_SESSION['parent_logged_in']) || $_SESSION['parent_logged_in'] !== true) {
        // Arahkan ke halaman login orang tua
        header('Location: ' . ROOT_URL . 'public_parent/index.php');
        exit();
    }
}

/**
 * Mendapatkan ID siswa yang sedang login.
 *
 * @return int|null ID siswa jika login, null jika tidak.
 */
function get_current_parent_siswa_id() {
    return $_SESSION['siswa_id'] ?? null;
}

/**
 * Mendapatkan username siswa yang sedang login.
 *
 * @return string|null Username siswa jika login, null jika tidak.
 */
function get_current_parent_username() {
    return $_SESSION['username'] ?? null;
}

/**
 * Mendapatkan nama lengkap siswa yang sedang login.
 *
 * @return string|null Nama lengkap siswa jika login, null jika tidak.
 */
function get_current_parent_nama_lengkap() {
    return $_SESSION['nama_lengkap'] ?? null;
}

/**
 * Mendapatkan ID kelas siswa yang sedang login.
 *
 * @return int|null ID kelas siswa jika login, null jika tidak.
 */
function get_current_parent_kelas_id() {
    return $_SESSION['kelas_id'] ?? null;
}

/**
 * Fungsi untuk mengelola pesan flash (notifikasi sekali pakai).
 *
 * @param string|null $key Kunci pesan flash (misal: 'success', 'error').
 * @param string|null $message Pesan yang akan disimpan.
 * @return string|null Pesan yang diambil jika $message null, atau null jika pesan disimpan.
 */
function parent_flash($key = null, $message = null) {
    $flash_key = 'parent_flash_' . $key;
    if ($message !== null) {
        // Set pesan flash
        $_SESSION[$flash_key] = $message;
        return null;
    } elseif ($key !== null && isset($_SESSION[$flash_key])) {
        // Dapatkan pesan flash dan hapus dari session
        $msg = $_SESSION[$flash_key];
        unset($_SESSION[$flash_key]);
        return $msg;
    }
    return null;
}

?>
