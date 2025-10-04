<?php
// project-root/public_parent/index.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_parent.php'; // Sertakan file autentikasi orang tua

// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect jika sudah login
if (isset($_SESSION['parent_logged_in']) && $_SESSION['parent_logged_in'] === true) {
    header('Location: ' . ROOT_URL . 'public_parent/dashboard.php');
    exit();
}

$error_message = parent_flash('error');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        parent_flash('error', 'Username dan password harus diisi.');
        header('Location: ' . ROOT_URL . 'public_parent/index.php');
        exit();
    }

    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        require_once __DIR__ . '/../classes/Siswa.php'; // Panggil kelas Siswa
        $siswa_model = new Siswa($pdo);

        $siswa_data = $siswa_model->authenticate($username, $password);

        if ($siswa_data) {
            // Login berhasil
            $_SESSION['parent_logged_in'] = true;
            $_SESSION['siswa_id'] = $siswa_data['id'];
            $_SESSION['username'] = $siswa_data['username'];
            $_SESSION['nama_lengkap'] = $siswa_data['nama_lengkap'];
            $_SESSION['no_telp_ortu'] = $siswa_data['no_telp_ortu'];
            $_SESSION['kelas_id'] = $siswa_data['kelas_id']; // Simpan ID kelas

            parent_flash('success', 'Berhasil login sebagai orang tua!');
            header('Location: ' . ROOT_URL . 'public_parent/dashboard.php');
            exit();
        } else {
            parent_flash('error', 'Username atau password salah.');
            header('Location: ' . ROOT_URL . 'public_parent/index.php');
            exit();
        }
    } catch (Exception $e) {
        error_log("Parent Login Error: " . $e->getMessage());
        parent_flash('error', 'Terjadi kesalahan sistem saat login.');
        header('Location: ' . ROOT_URL . 'public_parent/index.php');
        exit();
    }
}

// Tampilan halaman login
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Orang Tua - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="w-full max-w-md bg-white rounded-lg shadow-xl p-8 space-y-6 border border-gray-200">
        <div class="text-center">
            <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Login Orang Tua</h1>
            <p class="text-gray-600">Akses informasi anak Anda</p>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-5">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username Siswa</label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user text-gray-400"></i>
                    </div>
                    <input type="text" id="username" name="username" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-tight text-gray-900 placeholder-gray-500" placeholder="Masukkan username" required autofocus>
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" id="password" name="password" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-tight text-gray-900 placeholder-gray-500" placeholder="Masukkan password" required>
                </div>
            </div>

            <div>
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">
                    <i class="fas fa-sign-in-alt mr-2"></i> Login
                </button>
            </div>
        </form>

        <div class="text-center text-sm text-gray-600">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
