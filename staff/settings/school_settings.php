<?php
// project-root/admin/settings/school_settings.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app_config.php'; // Memuat APP_TAHUN_AJARAN_AKTIF dari DB
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SchoolSettings.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$school_settings_model = null;
$pdo = null;
$settings = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $school_settings_model = new SchoolSettings($pdo);

    // Ambil pengaturan saat ini
    $settings = $school_settings_model->getSettings();

    // Jika belum ada pengaturan, inisialisasi dengan nilai default atau kosong
    if (!$settings) {
        $settings = [
            'id' => null, // Akan di-insert
            'nama_sekolah' => '',
            'alamat_sekolah' => '',
            'kota' => '',
            'provinsi' => '',
            'kode_pos' => '',
            'telepon_sekolah' => '',
            'email_sekolah' => '',
            'website_sekolah' => '',
            'kepala_sekolah' => '',
            'nip_kepala_sekolah' => '',
            'logo_sekolah_path' => null,
            'semester_aktif' => APP_SEMESTER_AKTIF, // Ambil dari konstanta fallback
            'jam_masuk_sekolah_default' => '07:00:00',
            'toleransi_terlambat_menit' => 15,
            'whatsapp_api_url' => '',
            'whatsapp_api_key' => '',
            'whatsapp_sender_number' => '',
            'rfid_machine_api_key' => '' // Inisialisasi kolom baru
        ];
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = [
            'nama_sekolah' => trim($_POST['nama_sekolah'] ?? ''),
            'alamat_sekolah' => trim($_POST['alamat_sekolah'] ?? ''),
            'kota' => trim($_POST['kota'] ?? ''),
            'provinsi' => trim($_POST['provinsi'] ?? ''),
            'kode_pos' => trim($_POST['kode_pos'] ?? ''),
            'telepon_sekolah' => trim($_POST['telepon_sekolah'] ?? ''),
            'email_sekolah' => trim($_POST['email_sekolah'] ?? ''),
            'website_sekolah' => trim($_POST['website_sekolah'] ?? ''),
            'kepala_sekolah' => trim($_POST['kepala_sekolah'] ?? ''),
            'nip_kepala_sekolah' => trim($_POST['nip_kepala_sekolah'] ?? ''),
            'logo_sekolah_path' => $settings['logo_sekolah_path'], // Pertahankan yang sudah ada jika tidak diubah
            'semester_aktif' => trim($_POST['semester_aktif'] ?? ''),
            'jam_masuk_sekolah_default' => trim($_POST['jam_masuk_sekolah_default'] ?? '07:00:00'),
            'toleransi_terlambat_menit' => (int)($_POST['toleransi_terlambat_menit'] ?? 15),
            'whatsapp_api_url' => trim($_POST['whatsapp_api_url'] ?? ''),
            'whatsapp_api_key' => trim($_POST['whatsapp_api_key'] ?? ''),
            'whatsapp_sender_number' => trim($_POST['whatsapp_sender_number'] ?? ''),
            'rfid_machine_api_key' => trim($_POST['rfid_machine_api_key'] ?? '') // Ambil nilai dari POST
        ];

        // Handle upload logo (jika ada)
        if (isset($_FILES['logo_sekolah']) && $_FILES['logo_sekolah']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../assets/uploads/logo/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES['logo_sekolah']['name'], PATHINFO_EXTENSION);
            $new_file_name = 'logo_sekolah_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['logo_sekolah']['tmp_name'], $target_file)) {
                $data['logo_sekolah_path'] = ASSETS_URL . 'uploads/logo/' . $new_file_name; // Simpan path relatif ke BASE_URL
                // Hapus logo lama jika ada
                if ($settings['logo_sekolah_path'] && file_exists(ROOT_PATH . str_replace(BASE_URL, '', $settings['logo_sekolah_path']))) {
                    unlink(ROOT_PATH . str_replace(BASE_URL, '', $settings['logo_sekolah_path']));
                }
            } else {
                set_flash('error', 'Gagal mengunggah logo sekolah.');
                redirect(ROOT_URL . 'admin/settings/school_settings.php');
            }
        }

        if ($school_settings_model->updateSettings($data)) {
            set_flash('success', 'Pengaturan sekolah berhasil diperbarui.');
            redirect(ROOT_URL . 'admin/settings/school_settings.php');
        } else {
            set_flash('error', 'Gagal memperbarui pengaturan sekolah.');
            redirect(ROOT_URL . 'admin/settings/school_settings.php');
        }
    }

} catch (PDOException $e) {
    error_log("Pengaturan Sekolah Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Pengaturan Sekolah General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header
require_once __DIR__ . '/../../components/header_staff.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Pengaturan Sekolah</h1>

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

    <div class="bg-white p-6 rounded-lg shadow-md">
        <form action="" method="POST" enctype="multipart/form-data">
            <h2 class="text-xl font-semibold mb-4">Informasi Umum Sekolah</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="nama_sekolah" class="block text-gray-700 text-sm font-bold mb-2">Nama Sekolah:</label>
                    <input type="text" id="nama_sekolah" name="nama_sekolah" value="<?php echo htmlspecialchars($settings['nama_sekolah'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="kepala_sekolah" class="block text-gray-700 text-sm font-bold mb-2">Kepala Sekolah:</label>
                    <input type="text" id="kepala_sekolah" name="kepala_sekolah" value="<?php echo htmlspecialchars($settings['kepala_sekolah'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="nip_kepala_sekolah" class="block text-gray-700 text-sm font-bold mb-2">NIP Kepala Sekolah:</label>
                    <input type="text" id="nip_kepala_sekolah" name="nip_kepala_sekolah" value="<?php echo htmlspecialchars($settings['nip_kepala_sekolah'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <!-- Kolom telepon_sekolah akan digunakan untuk notifikasi kepala sekolah -->
                <div>
                    <label for="telepon_sekolah" class="block text-gray-700 text-sm font-bold mb-2">Telepon Sekolah (untuk Notif Guru):</label>
                    <input type="tel" id="telepon_sekolah" name="telepon_sekolah" value="<?php echo htmlspecialchars($settings['telepon_sekolah'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Contoh: 6281234567890">
                </div>
                <div>
                    <label for="email_sekolah" class="block text-gray-700 text-sm font-bold mb-2">Email Sekolah:</label>
                    <input type="email" id="email_sekolah" name="email_sekolah" value="<?php echo htmlspecialchars($settings['email_sekolah'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="website_sekolah" class="block text-gray-700 text-sm font-bold mb-2">Website Sekolah:</label>
                    <input type="url" id="website_sekolah" name="website_sekolah" value="<?php echo htmlspecialchars($settings['website_sekolah'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>

            <h2 class="text-xl font-semibold mb-4">Pengaturan Sistem</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="semester_aktif" class="block text-gray-700 text-sm font-bold mb-2">Semester Aktif:</label>
                    <select id="semester_aktif" name="semester_aktif" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="Ganjil" <?php echo ($settings['semester_aktif'] ?? '') == 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                        <option value="Genap" <?php echo ($settings['semester_aktif'] ?? '') == 'Genap' ? 'selected' : ''; ?>>Genap</option>
                    </select>
                </div>
                <div>
                    <label for="jam_masuk_sekolah_default" class="block text-gray-700 text-sm font-bold mb-2">Jam Masuk Sekolah Default:</label>
                    <input type="time" id="jam_masuk_sekolah_default" name="jam_masuk_sekolah_default" value="<?php echo htmlspecialchars($settings['jam_masuk_sekolah_default'] ?? '07:00:00'); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="toleransi_terlambat_menit" class="block text-gray-700 text-sm font-bold mb-2">Toleransi Keterlambatan (menit):</label>
                    <input type="number" id="toleransi_terlambat_menit" name="toleransi_terlambat_menit" value="<?php echo htmlspecialchars($settings['toleransi_terlambat_menit'] ?? 15); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>

            <h2 class="text-xl font-semibold mb-4">Pengaturan WhatsApp Gateway (MPedia)</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="whatsapp_api_url" class="block text-gray-700 text-sm font-bold mb-2">WhatsApp API URL:</label>
                    <input type="url" id="whatsapp_api_url" name="whatsapp_api_url" value="<?php echo htmlspecialchars($settings['whatsapp_api_url'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Contoh: https://api1.codene.my.id/send-message">
                </div>
                <div>
                    <label for="whatsapp_api_key" class="block text-gray-700 text-sm font-bold mb-2">WhatsApp API Key:</label>
                    <input type="text" id="whatsapp_api_key" name="whatsapp_api_key" value="<?php echo htmlspecialchars($settings['whatsapp_api_key'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Masukkan API Key Anda">
                </div>
                <div class="md:col-span-2">
                    <label for="whatsapp_sender_number" class="block text-gray-700 text-sm font-bold mb-2">WhatsApp Sender Number (Ex: 62888xxxx):</label>
                    <input type="text" id="whatsapp_sender_number" name="whatsapp_sender_number" value="<?php echo htmlspecialchars($settings['whatsapp_sender_number'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Contoh: 6281234567890">
                </div>
            </div>

            <!-- New section for RFID Machine API Key -->
            <h2 class="text-xl font-semibold mb-4">Pengaturan Mesin Absensi RFID</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <label for="rfid_machine_api_key" class="block text-gray-700 text-sm font-bold mb-2">Kunci API Mesin RFID:</label>
                    <input type="text" id="rfid_machine_api_key" name="rfid_machine_api_key" value="<?php echo htmlspecialchars($settings['rfid_machine_api_key'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Masukkan kunci API unik dan kuat untuk mesin RFID">
                    <p class="text-gray-600 text-xs italic mt-1">Kunci ini digunakan oleh mesin absensi RFID untuk berkomunikasi dengan server. Jaga kerahasiaannya!</p>
                </div>
            </div>

            <h2 class="text-xl font-semibold mb-4">Logo Sekolah</h2>
            <div class="mb-6">
                <label for="logo_sekolah" class="block text-gray-700 text-sm font-bold mb-2">Unggah Logo Sekolah:</label>
                <input type="file" id="logo_sekolah" name="logo_sekolah" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <?php if (!empty($settings['logo_sekolah_path'])): ?>
                    <p class="text-gray-600 text-sm mt-2">Logo saat ini:</p>
                    <img src="<?php echo htmlspecialchars($settings['logo_sekolah_path']); ?>" alt="Logo Sekolah" class="mt-2 h-24 w-auto object-contain rounded-lg shadow-sm">
                <?php endif; ?>
                <p class="text-gray-500 text-xs mt-1">Ukuran maksimal file: 2MB. Format: JPG, PNG, GIF.</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Sertakan footer
require_once __DIR__ . '/../../components/footer.php';
?>
