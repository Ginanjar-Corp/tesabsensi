<?php
// project-root/admin/settings/gaji_settings.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/GajiSettings.php'; // Pastikan ini di-include

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$gaji_settings_model = null;
$pdo = null;
$nominal_per_pertemuan = 0; // Nilai default

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $gaji_settings_model = new GajiSettings($pdo);

    // Ambil pengaturan gaji saat ini
    $current_gaji_settings = $gaji_settings_model->getSettings(); // Asumsi ada method getSettings()

    if ($current_gaji_settings && isset($current_gaji_settings['nominal_per_pertemuan'])) {
        $nominal_per_pertemuan = $current_gaji_settings['nominal_per_pertemuan'];
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['nominal_per_pertemuan'])) {
            // Hapus format mata uang dan ganti koma dengan titik untuk penyimpanan
            $new_nominal = str_replace('.', '', $_POST['nominal_per_pertemuan']);
            $new_nominal = str_replace(',', '.', $new_nominal);
            $new_nominal = (float)$new_nominal;

            if ($gaji_settings_model->updateNominalGajiPerPertemuan($new_nominal)) { // Asumsi ada method updateNominalGajiPerPertemuan()
                flash('success', 'Pengaturan gaji berhasil diperbarui.');
                // Setelah berhasil update, ambil kembali nilai terbaru untuk ditampilkan
                $current_gaji_settings = $gaji_settings_model->getSettings();
                if ($current_gaji_settings && isset($current_gaji_settings['nominal_per_pertemuan'])) {
                    $nominal_per_pertemuan = $current_gaji_settings['nominal_per_pertemuan'];
                }
                header('Location: ' . ROOT_URL . 'admin/settings/gaji_settings.php');
                exit();
            } else {
                flash('error', 'Gagal memperbarui pengaturan gaji.');
            }
        }
    }

} catch (PDOException $e) {
    $error_message = "Kesalahan database: " . $e->getMessage();
    // Anda bisa log error ini
}

// Sertakan header
require_once __DIR__ . '/../../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Pengaturan Gaji Guru</h1>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Sukses!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($success_message) ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Nominal Gaji per Pertemuan Mengajar</h2>
        <form action="" method="POST">
            <div class="mb-4">
                <label for="nominal_per_pertemuan" class="block text-gray-700 text-sm font-bold mb-2">Nominal per Pertemuan (Rp):</label>
                <input type="text" id="nominal_per_pertemuan" name="nominal_per_pertemuan"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       value="<?= htmlspecialchars(number_format($nominal_per_pertemuan, 0, ',', '.')) ?>"
                       placeholder="Masukkan nominal gaji untuk setiap pertemuan mengajar."
                       required>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 shadow-md">
                    Simpan Pengaturan Gaji
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Sertakan footer
require_once __DIR__ . '/../../components/footer.php';
?>