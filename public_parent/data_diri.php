<?php
// project-root/public_parent/data_diri.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_parent.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Kelas.php'; // Diperlukan untuk menampilkan nama kelas

// Pastikan orang tua sudah login
require_parent_login();

$siswa_id = get_current_parent_siswa_id();
$username_parent = get_current_parent_username();
$nama_lengkap_siswa = get_current_parent_nama_lengkap();
$kelas_id = get_current_parent_kelas_id(); // Ambil ID kelas dari sesi

$error_message = parent_flash('error');
$success_message = parent_flash('success');

$siswa_model = null;
$kelas_model = null; // Inisialisasi model kelas
$pdo = null;
$siswa_data = [];
$nama_kelas_siswa = 'N/A';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $siswa_model = new Siswa($pdo);
    $kelas_model = new Kelas($pdo); // Inisialisasi

    $siswa_data = $siswa_model->findById($siswa_id);

    if (!$siswa_data) {
        parent_flash('error', 'Data siswa tidak ditemukan.');
        header('Location: ' . ROOT_URL . 'public_parent/dashboard.php');
        exit;
    }

    // Dapatkan nama kelas
    if ($siswa_data['kelas_id']) {
        $kelas_info = $kelas_model->getById($siswa_data['kelas_id']);
        if ($kelas_info) {
            $nama_kelas_siswa = $kelas_info['nama_kelas'];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $current_password_input = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';

        // Data yang bisa diubah (selain password)
        $update_data = [
            'username' => $_POST['username'] ?? $siswa_data['username'],
            'nama_lengkap' => $_POST['nama_lengkap'] ?? $siswa_data['nama_lengkap'],
            'nisn' => $_POST['nisn'] ?? $siswa_data['nisn'],
            'nis' => $_POST['nis'] ?? $siswa_data['nis'],
            // RFID/QR Tag tidak diubah di sini karena hanya admin yang bisa
            'tempat_lahir' => $_POST['tempat_lahir'] ?? $siswa_data['tempat_lahir'],
            'tanggal_lahir' => $_POST['tanggal_lahir'] ?? $siswa_data['tanggal_lahir'],
            'jenis_kelamin' => $_POST['jenis_kelamin'] ?? $siswa_data['jenis_kelamin'],
            'alamat' => $_POST['alamat'] ?? $siswa_data['alamat'],
            'no_telp_ortu' => $_POST['no_telp_ortu'] ?? $siswa_data['no_telp_ortu'],
            'email_ortu' => $_POST['email_ortu'] ?? $siswa_data['email_ortu'],
        ];

        $errors = [];

        // Validasi kata sandi saat ini jika kata sandi baru diisi ATAU ada perubahan data lain
        // Memaksa verifikasi password saat ini untuk setiap perubahan data
        if (empty($current_password_input)) {
            $errors[] = 'Kata sandi saat ini wajib diisi untuk mengubah data apapun.';
        } else {
            $siswa_auth_data = $siswa_model->authenticate($username_parent, $current_password_input);
            if (!$siswa_auth_data) {
                $errors[] = 'Kata sandi saat ini salah.';
            }
        }
        
        // Validasi kata sandi baru (hanya jika diisi)
        if (!empty($new_password)) {
            if ($new_password !== $confirm_new_password) {
                $errors[] = 'Kata sandi baru dan konfirmasi kata sandi tidak cocok.';
            } elseif (strlen($new_password) < 6) {
                $errors[] = 'Kata sandi baru minimal 6 karakter.';
            } else {
                $update_data['password'] = $new_password; // Password akan di-hash di model
            }
        }

        // Cek username unik (kecuali jika username tidak berubah)
        if ($update_data['username'] !== $siswa_data['username']) {
            $existing_siswa_by_username = $siswa_model->findByUsername($update_data['username']);
            if ($existing_siswa_by_username && $existing_siswa_by_username['id'] != $siswa_id) {
                $errors[] = 'Username sudah digunakan oleh akun lain. Silakan pilih username yang berbeda.';
            }
        }
        // Cek NISN unik (kecuali jika NISN tidak berubah)
        if ($update_data['nisn'] !== ($siswa_data['nisn'] ?? '')) { // Handle null/empty string for comparison
            $existing_siswa_by_nisn = $siswa_model->findByNisn($update_data['nisn']);
            if ($existing_siswa_by_nisn && $existing_siswa_by_nisn['id'] != $siswa_id) {
                $errors[] = 'NISN sudah digunakan oleh siswa lain.';
            }
        }
        // Cek NIS unik (kecuali jika NIS tidak berubah)
        if ($update_data['nis'] !== ($siswa_data['nis'] ?? '')) { // Handle null/empty string for comparison
            $existing_siswa_by_nis = $siswa_model->findByNis($update_data['nis']);
            if ($existing_siswa_by_nis && $existing_siswa_by_nis['id'] != $siswa_id) {
                $errors[] = 'NIS sudah digunakan oleh siswa lain.';
            }
        }
        // RFID/QR Tag tidak perlu dicek karena readonly dan tidak diubah


        if (empty($errors)) {
            if ($siswa_model->update($siswa_id, $update_data)) {
                // Perbarui sesi jika username, nama lengkap, atau no_telp_ortu berubah
                if ($update_data['username'] !== $username_parent) {
                    $_SESSION['parent_username'] = $update_data['username'];
                }
                if ($update_data['nama_lengkap'] !== $nama_lengkap_siswa) {
                    $_SESSION['nama_lengkap'] = $update_data['nama_lengkap'];
                }
                // Jika Anda menyimpan no_telp_ortu di sesi, perbarui juga
                if (isset($_SESSION['no_telp_ortu']) && isset($update_data['no_telp_ortu']) && $_SESSION['no_telp_ortu'] !== $update_data['no_telp_ortu']) {
                     $_SESSION['no_telp_ortu'] = $update_data['no_telp_ortu'];
                }

                parent_flash('success', 'Data diri dan kata sandi berhasil diperbarui.');
            } else {
                parent_flash('error', 'Gagal memperbarui data diri. Silakan coba lagi.');
            }
        } else {
            parent_flash('error', implode('<br>', $errors));
        }
        header('Location: ' . ROOT_URL . 'public_parent/data_diri.php');
        exit;
    }

} catch (Exception $e) {
    error_log("Parent Data Diri Error: " . $e->getMessage());
    parent_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    header('Location: ' . ROOT_URL . 'public_parent/data_diri.php');
    exit;
}

$page_title = "Data Diri Anak";

require_once __DIR__ . '/../components/header_parent.php';
?>
    <!-- Main Content -->
    <div class="flex-1 p-8 w-full"> <!-- Added w-full here for full width -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Data Diri Anak</h1>
            <div class="flex items-center space-x-4">
                <span class="text-gray-700">Selamat datang, <span class="font-semibold"><?php echo htmlspecialchars($nama_lengkap_siswa); ?></span>!</span>
                <img src="https://placehold.co/40x40/cbd5e1/475569?text=PT" alt="User Avatar" class="rounded-full border-2 border-indigo-400">
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md mb-6 w-full lg:max-w-4xl xl:max-w-5xl"> <!-- Adjusted max-w and removed mx-auto -->
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Informasi Pribadi</h2>
            <form action="" method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="nama_lengkap" class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap Anak:</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($siswa_data['nama_lengkap'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    <div>
                        <label for="kelas_nama" class="block text-gray-700 text-sm font-bold mb-2">Kelas Anak:</label>
                        <input type="text" id="kelas_nama" name="kelas_nama" value="<?php echo htmlspecialchars($nama_kelas_siswa); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed" readonly>
                        <p class="text-xs text-gray-500 mt-1">Kelas hanya bisa diubah oleh admin.</p>
                    </div>
                    <div>
                        <label for="nisn" class="block text-gray-700 text-sm font-bold mb-2">NISN:</label>
                        <input type="text" id="nisn" name="nisn" value="<?php echo htmlspecialchars($siswa_data['nisn'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div>
                        <label for="nis" class="block text-gray-700 text-sm font-bold mb-2">NIS:</label>
                        <input type="text" id="nis" name="nis" value="<?php echo htmlspecialchars($siswa_data['nis'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="md:col-span-2">
                        <label for="rfid_tag" class="block text-gray-700 text-sm font-bold mb-2">RFID/QR Tag:</label>
                        <input type="text" id="rfid_tag" name="rfid_tag" value="<?php echo htmlspecialchars($siswa_data['rfid_tag'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed" readonly>
                        <p class="text-xs text-gray-500 mt-1">RFID/QR Tag hanya bisa diubah oleh admin.</p>
                    </div>
                    <div>
                        <label for="tempat_lahir" class="block text-gray-700 text-sm font-bold mb-2">Tempat Lahir:</label>
                        <input type="text" id="tempat_lahir" name="tempat_lahir" value="<?php echo htmlspecialchars($siswa_data['tempat_lahir'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div>
                        <label for="tanggal_lahir" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Lahir:</label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo htmlspecialchars($siswa_data['tanggal_lahir'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div>
                        <label for="jenis_kelamin" class="block text-gray-700 text-sm font-bold mb-2">Jenis Kelamin:</label>
                        <select id="jenis_kelamin" name="jenis_kelamin" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">-- Pilih --</option>
                            <option value="L" <?php echo (($siswa_data['jenis_kelamin'] ?? '') == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="P" <?php echo (($siswa_data['jenis_kelamin'] ?? '') == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="alamat" class="block text-gray-700 text-sm font-bold mb-2">Alamat:</label>
                        <textarea id="alamat" name="alamat" rows="2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($siswa_data['alamat'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label for="no_telp_ortu" class="block text-gray-700 text-sm font-bold mb-2">No. Telp Orang Tua:</label>
                        <input type="text" id="no_telp_ortu" name="no_telp_ortu" value="<?php echo htmlspecialchars($siswa_data['no_telp_ortu'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div>
                        <label for="email_ortu" class="block text-gray-700 text-sm font-bold mb-2">Email Orang Tua:</label>
                        <input type="email" id="email_ortu" name="email_ortu" value="<?php echo htmlspecialchars($siswa_data['email_ortu'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                </div>

                <h3 class="text-lg font-semibold mt-6 mb-3 text-gray-800">Informasi Akun</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username Akun:</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($siswa_data['username'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    <div>
                        <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">Kata Sandi Saat Ini (untuk verifikasi perubahan):</label>
                        <input type="password" id="current_password" name="current_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <p class="text-xs text-red-500 mt-1">Wajib diisi untuk mengubah data apapun.</p>
                    </div>
                    <div>
                        <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">Kata Sandi Baru (kosongkan jika tidak berubah):</label>
                        <input type="password" id="new_password" name="new_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div>
                        <label for="confirm_new_password" class="block text-gray-700 text-sm font-bold mb-2">Konfirmasi Kata Sandi Baru:</label>
                        <input type="password" id="confirm_new_password" name="confirm_new_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Perbarui Data Diri
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php
require_once __DIR__ . '/../components/footer_parent.php';
?>
