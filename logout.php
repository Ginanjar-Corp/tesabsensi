<?php
// project-root/logout.php

require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php'; // Sertakan file auth

// Lakukan proses logout
logout_user();

// Arahkan kembali ke halaman login atau beranda
set_flash('success', 'Anda telah berhasil logout.');
redirect(ROOT_URL . 'login.php');
?>
