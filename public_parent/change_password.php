<?php
// project-root/public_parent/change_password.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_parent.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php'; // Kelas Siswa digunakan untuk manajemen akun orang tua

// Pastikan orang tua sudah login
require_parent_login();

$siswa_id = get_current_parent_siswa_id();
$username_parent = get_current_parent_username(); // Username akun orang tua (siswa)
$nama_lengkap_siswa = get_current_parent_nama_lengkap();

$error_message = parent_flash('error');
$success_message = parent_flash('success');

$siswa_model = null;
$pdo = null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $siswa_model = new Siswa($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';

        // Validasi input
        if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
            parent_flash('error', 'Semua kolom harus diisi.');
        } elseif ($new_password !== $confirm_new_password) {
            parent_flash('error', 'Kata sandi baru dan konfirmasi kata sandi tidak cocok.');
        } elseif (strlen($new_password) < 6) { // Contoh: minimal 6 karakter
            parent_flash('error', 'Kata sandi baru minimal 6 karakter.');
        } else {
            // Autentikasi kata sandi saat ini
            $siswa_auth_data = $siswa_model->authenticate($username_parent, $current_password);

            if ($siswa_auth_data) {
                // Perbarui kata sandi di database
                $update_data = ['password' => $new_password];
                if ($siswa_model->update($siswa_id, $update_data)) {
                    parent_flash('success', 'Kata sandi berhasil diperbarui.');
                } else {
                    parent_flash('error', 'Gagal memperbarui kata sandi. Silakan coba lagi.');
                }
            } else {
                parent_flash('error', 'Kata sandi saat ini salah.');
            }
        }
        header('Location: ' . ROOT_URL . 'public_parent/change_password.php');
        exit;
    }

} catch (Exception $e) {
    error_log("Parent Change Password Error: " . $e->getMessage());
    parent_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

$page_title = "Ganti Kata Sandi";

require_once __DIR__ . '/../components/header_parent.php';
?>
    <!-- Main Content -->
    <div class="flex-1 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Ganti Kata Sandi Akun</h1>
            <div class="flex items-center space-x-4">
                <span class="text-gray-700">Selamat datang, <span class="font-semibold"><?php echo htmlspecialchars($nama_lengkap_siswa); ?></span>!</span>
                <img src="https://placehold.co/40x40/cbd5e1/475569?text=PT" alt="User Avatar" class="rounded-full border-2 border-indigo-400">
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md mb-6 max-w-lg mx-auto">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Formulir Perubahan Kata Sandi</h2>
            <form action="" method="POST" class="space-y-4">
                <div>
                    <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">Kata Sandi Saat Ini:</label>
                    <input type="password" id="current_password" name="current_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">Kata Sandi Baru:</label>
                    <input type="password" id="new_password" name="new_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="confirm_new_password" class="block text-gray-700 text-sm font-bold mb-2">Konfirmasi Kata Sandi Baru:</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Ganti Kata Sandi
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php
require_once __DIR__ . '/../components/footer_parent.php';
?>
