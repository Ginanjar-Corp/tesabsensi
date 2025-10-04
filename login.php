<?php
/**
 * File: login.php
 * Deskripsi: Halaman login untuk pengguna.
 */

// Sertakan file konfigurasi utama terlebih dahulu
// Ini akan memastikan konstanta database dan sesi dimulai
require_once __DIR__ . '/config/app_config.php';

// Sertakan kelas dan fungsi yang diperlukan
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/helpers.php'; // Diperlukan untuk set_flash dan redirect
require_once __DIR__ . '/includes/auth.php';    // Diperlukan untuk login_user
require_once __DIR__ . '/classes/SchoolSettings.php'; // Tambahkan ini untuk mengambil pengaturan sekolah

$error_message = '';

// Jika pengguna sudah login, arahkan ke index (yang akan menangani pengalihan berdasarkan peran)
if (is_logged_in()) {
    redirect(ROOT_URL . 'index.php');
}

$school_logo_path = 'https://placehold.co/120x120/4a5568/ffffff?text=LOGO+SEKOLAH'; // Fallback default

// Proses form login jika ada POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password harus diisi.';
    } else {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            // Ambil data user dari database
            // Perhatikan JOIN ke tabel roles untuk mendapatkan role_name
            // Memastikan related_id selalu ada dalam hasil query, meskipun null
            $stmt = $pdo->prepare("SELECT u.id, u.username, u.password, u.is_active, r.role_name, u.related_id
                                   FROM users u
                                   JOIN roles r ON u.role_id = r.id
                                   WHERE u.username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verifikasi password menggunakan password_verify()
                if (password_verify($password, $user['password'])) {
                    // Periksa status aktif pengguna
                    if ($user['is_active']) {
                        // --- PERBAIKAN: Pastikan 'related_id' selalu ada di array $user ---
                        // Jika related_id bisa null di DB, ini akan tetap mengambilnya sebagai null
                        // Jika kolom related_id tidak ada sama sekali atau ada masalah lain, ini akan menginisialisasi
                        $user['related_id'] = $user['related_id'] ?? null; 
                        
                        // Login berhasil, panggil fungsi login_user untuk mengatur sesi
                        login_user($user); // Panggil fungsi login_user di auth.php

                        // Arahkan ke halaman index yang akan mengarahkan lagi ke dashboard yang sesuai
                        redirect(ROOT_URL . 'index.php');
                    } else {
                        $error_message = 'Akun Anda tidak aktif. Silakan hubungi administrator.';
                    }
                } else {
                    $error_message = 'Username atau password salah.';
                }
            } else {
                $error_message = 'Username atau password salah.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = 'Terjadi kesalahan server. Silakan coba lagi nanti.';
        }
    }
}

// Ambil path logo sekolah terlepas dari metode request
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $school_settings_model = new SchoolSettings($pdo);
    $settings = $school_settings_model->getSettings();
    if (isset($settings['logo_sekolah_path']) && !empty($settings['logo_sekolah_path'])) {
        // Asumsi logo disimpan di folder 'assets/uploads/logo/'
        $school_logo_path =  htmlspecialchars($settings['logo_sekolah_path']);
    }
} catch (PDOException $e) {
    error_log("Error fetching school settings for logo: " . $e->getMessage());
    // Fallback path already set
}

//awal ambil logo

$school_logo_path = '';
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $school_settings_model = new SchoolSettings($pdo);
    $settings = $school_settings_model->getSettings();
    if ($settings && !empty($settings['logo_sekolah_path'])) {
        $school_logo_path = htmlspecialchars($settings['logo_sekolah_path']);
    }
} catch (PDOException $e) {
    // Log error koneksi database, tapi jangan hentikan eksekusi
    error_log("Database connection error in header: " . $e->getMessage());
} catch (Exception $e) {
    // Log error umum, tapi jangan hentikan eksekusi
    error_log("Error fetching school settings in header: " . $e->getMessage());
}

// Tambahkan favicon jika logo sekolah tersedia
if (!empty($school_logo_path)) {
    echo '<link rel="icon" href="' . $school_logo_path . '" type="image/x-icon">';
    echo '<link rel="shortcut icon" href="' . $school_logo_path . '" type="image/x-icon">';
} else {
    // Fallback favicon jika tidak ada logo sekolah (opsional)
    // Anda bisa menunjuk ke ikon default atau tidak menampilkan apa-apa
    // echo '<link rel="icon" href="' . ROOT_URL . 'assets/images/default_favicon.ico" type="image/x-icon">';
}

//akhir ambil logo


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Absensi SMA IT AL-BURHANY</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome untuk ikon mata -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #5b97f0 0%, #0746a3 100%); /* Gradien biru-ungu */
        }
        .login-card {
            background-color: rgba(255, 255, 255, 0.95); /* Sedikit transparan */
            backdrop-filter: blur(5px); /* Efek blur pada latar belakang */
            border-radius: 1.5rem; /* Lebih membulat */
            padding: 2.5rem; /* Padding lebih besar */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2); /* Bayangan lebih kuat */
        }
        .input-group {
            position: relative;
        }
        /* Memberi ruang di kanan input untuk ikon */
        .input-group input[type="password"],
        .input-group input[type="text"] {
            padding-right: 3rem; /* Sesuaikan jika ikon terlalu dekat */
        }
        .input-group .toggle-password {
            position: absolute;
            right: 1rem; /* Jarak dari kanan */
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280; /* Warna ikon mata */
            font-size: 1.1rem; /* Ukuran ikon */
            padding: 0.5rem; /* Area klik/tap yang lebih besar */
        }
        .input-group input:focus + .toggle-password {
            color: #3b82f6; /* Warna ikon mata saat input fokus */
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="login-card w-full max-w-md mx-auto">
        <div class="flex flex-col items-center mb-8">
            <!-- Logo Sekolah diambil dari pengaturan database -->
            <img src="<?php echo $school_logo_path; ?>" alt="Logo Sekolah" class="w-24 h-24 rounded-full mb-4 shadow-md object-cover">
            <h2 class="text-3xl font-bold text-center text-gray-800">Sistem Absensi</h2>
            <p class="text-gray-600 text-center mt-1">SMA IT AL-BURHANY</p>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                <input type="text" id="username" name="username" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 transition duration-200" required>
            </div>
            <div class="mb-6 input-group">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                <input type="password" id="password" name="password" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 transition duration-200" required>
                <span class="toggle-password" onclick="togglePasswordVisibility()">
                    <i id="togglePasswordIcon" class="fas fa-eye"></i>
                </span>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-300 transform hover:scale-105">
                    Login
                </button>
            </div>
        </form>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
