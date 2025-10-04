<?php
/**
 * File: index.php
 * Deskripsi: Halaman utama yang menampilkan selamat datang atau mengarahkan pengguna ke dashboard yang sesuai berdasarkan peran mereka setelah login.
 */

// Sertakan file konfigurasi utama terlebih dahulu
// Ini akan memastikan konstanta database dan sesi dimulai
require_once __DIR__ . '/config/app_config.php';

// Sertakan fungsi-fungsi helper dan kelas-kelas yang diperlukan
require_once __DIR__ . '/includes/helpers.php'; // Contoh helper untuk fungsi-fungsi umum
require_once __DIR__ . '/includes/auth.php'; // Memuat fungsi otentikasi
require_once __DIR__ . '/classes/Database.php'; // Kelas Database sekarang bisa diinisialisasi

// Inisialisasi koneksi database
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    // Jika koneksi berhasil, Anda bisa melanjutkan dengan logika aplikasi
    // echo "Koneksi database berhasil!"; // Hanya untuk debugging awal
} catch (PDOException $e) {
    // Tangani error koneksi database
    error_log("Database connection error: " . $e->getMessage());
    die("Terjadi masalah pada koneksi database. Silakan coba lagi nanti.");
}

// Periksa apakah pengguna sudah login
if (is_logged_in()) {
    // Pengguna sudah login, arahkan ke dashboard yang sesuai berdasarkan peran
    $role_name = $_SESSION['role_name'] ?? '';

    switch ($role_name) {
        case 'Admin':
            header('Location: ' . ROOT_URL . 'admin/dashboard.php');
            break;
        case 'TataUsaha':
            header('Location: ' . ROOT_URL . 'staff/dashboard.php');
            break;
        case 'Guru':
            header('Location: ' . ROOT_URL . 'guru/dashboard.php');
            break;
        case 'Siswa':
            header('Location: ' . ROOT_URL . 'siswa/dashboard.php');
            break;
        default:
            // Jika peran tidak dikenal atau tidak ada, arahkan ke halaman login atau pesan error
            session_destroy(); // Hancurkan sesi yang tidak valid
            header('Location: ' . ROOT_URL . 'login.php');
            break;
    }
    exit();
} else {
    // Jika belum login, arahkan langsung ke halaman login
    header('Location: ' . ROOT_URL . 'login.php');
    exit();
}


// Jika belum login, tampilkan halaman selamat datang
// Sertakan komponen header (pastikan header.php adalah header dasar tanpa sidebar)
require_once __DIR__ . '/components/header.php';
?>

<main class="min-h-screen flex flex-col items-center justify-center bg-gray-100">

    <!-- Hero Section -->
    <section class="w-full bg-gradient-to-r from-blue-600 to-blue-800 text-white text-center py-20 lg:py-32 rounded-b-3xl shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight mb-4 animate-fade-in-down">
                Selamat Datang di Sistem Absensi <br class="hidden sm:inline">SMA IT AL-BURHANY
            </h1>
            <p class="text-lg sm:text-xl lg:text-2xl mb-8 opacity-90 animate-fade-in-up">
                Solusi modern untuk manajemen kehadiran Siswa dan Guru yang efisien.
            </p>
            <a href="<?php echo ROOT_URL; ?>login.php" class="inline-block bg-white text-blue-700 hover:bg-gray-100 font-bold py-3 px-8 rounded-full shadow-lg transition duration-300 ease-in-out transform hover:scale-105 animate-pop-in">
                Login ke Aplikasi
            </a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="w-full py-16 lg:py-24 bg-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-800 text-center mb-12 animate-fade-in">
                Fitur Utama Kami
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Card: Absensi Harian -->
                <div class="bg-white rounded-xl shadow-lg p-8 text-center transition duration-300 ease-in-out transform hover:-translate-y-2 hover:shadow-xl animate-fade-in-left">
                    <i class="fas fa-fingerprint text-5xl mb-4 mx-auto text-blue-500"></i>
                    <h3 class="text-2xl font-semibold mb-3 text-gray-800">Absensi Harian</h3>
                    <p class="text-gray-600 mb-6">Catat kehadiran masuk dan pulang siswa dan guru melalui RFID atau QR Code dengan cepat dan akurat.</p>
                    <a href="<?php echo ROOT_URL; ?>login.php" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded-full transition duration-300">Mulai Absen Gerbang</a>
                </div>

                <!-- Card: Absensi Mata Pelajaran -->
                <div class="bg-white rounded-xl shadow-lg p-8 text-center transition duration-300 ease-in-out transform hover:-translate-y-2 hover:shadow-xl animate-fade-in">
                    <i class="fas fa-clipboard-check text-5xl mb-4 mx-auto text-green-500"></i>
                    <h3 class="text-2xl font-semibold mb-3 text-gray-800">Absensi Mata Pelajaran</h3>
                    <p class="text-gray-600 mb-6">Guru dapat mencatat absensi siswa per mata pelajaran di kelas secara detail dan terstruktur.</p>
                    <a href="<?php echo ROOT_URL; ?>login.php" class="inline-block bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-6 rounded-full transition duration-300">Lihat Lebih Lanjut</a>
                </div>

                <!-- Card: Laporan & Statistik -->
                <div class="bg-white rounded-xl shadow-lg p-8 text-center transition duration-300 ease-in-out transform hover:-translate-y-2 hover:shadow-xl animate-fade-in-right">
                    <i class="fas fa-chart-bar text-5xl mb-4 mx-auto text-purple-500"></i>
                    <h3 class="text-2xl font-semibold mb-3 text-gray-800">Laporan & Statistik</h3>
                    <p class="text-gray-600 mb-6">Akses laporan kehadiran komprehensif, statistik, dan analisis data untuk pengambilan keputusan yang lebih baik.</p>
                    <a href="<?php echo ROOT_URL; ?>login.php" class="inline-block bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-6 rounded-full transition duration-300">Akses Laporan</a>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section (Opsional) -->
    <section class="w-full py-12 lg:py-16 bg-blue-50 text-center rounded-xl shadow-md mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 mt-8 mb-8 animate-fade-in-up">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Tentang Sistem Ini</h2>
        <p class="text-gray-700 text-lg max-w-3xl mx-auto">
            Sistem Absensi SMA IT AL-BURHANY dirancang untuk meningkatkan efisiensi dan akurasi dalam pengelolaan kehadiran, memberikan kemudahan bagi administrasi, guru, siswa, dan orang tua.
        </p>
    </section>

</main>

<style>
    /* Keyframe Animations */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInLeft {
        from { opacity: 0; transform: translateX(-20px); }
        to { opacity: 1; transform: translateX(0); }
    }
    @keyframes fadeInRight {
        from { opacity: 0; transform: translateX(20px); }
        to { opacity: 1; transform: translateX(0); }
    }
    @keyframes popIn {
        from { opacity: 0; transform: scale(0.8); }
        to { opacity: 1; transform: scale(1); }
    }

    /* Apply Animations */
    .animate-fade-in-down { animation: fadeInDown 0.8s ease-out forwards; }
    .animate-fade-in-up { animation: fadeInUp 0.8s ease-out forwards; }
    .animate-fade-in-left { animation: fadeInLeft 0.8s ease-out forwards; }
    .animate-fade-in-right { animation: fadeInRight 0.8s ease-out forwards; }
    .animate-pop-in { animation: popIn 0.6s ease-out forwards; }

    /* Delay animations for sequential appearance */
    .animate-fade-in-down { animation-delay: 0.1s; }
    .animate-fade-in-up { animation-delay: 0.3s; }
    .animate-pop-in { animation-delay: 0.5s; }
    .animate-fade-in { animation-delay: 0.7s; }
    .animate-fade-in-left { animation-delay: 0.9s; }
    .animate-fade-in-right { animation-delay: 1.1s; }
</style>

<?php
// Sertakan komponen footer (pastikan footer.php adalah footer dasar tanpa JS sidebar yang tidak relevan untuk landing page)
require_once __DIR__ . '/components/footer.php';
?>
