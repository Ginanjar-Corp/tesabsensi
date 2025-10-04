<?php
// project-root/public_parent/logout.php

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_parent.php'; // Sertakan file autentikasi orang tua

// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua variabel sesi
$_SESSION = array();

// Hancurkan sesi
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect ke halaman login orang tua
header('Location: ' . ROOT_URL . 'public_parent/index.php');
exit();
?>
