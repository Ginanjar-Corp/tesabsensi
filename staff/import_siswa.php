<?php
// project-root/admin/import_siswa.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');
$import_results = flash('import_results'); // Untuk menampilkan hasil import

// Sertakan header
require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Import Data Siswa</h1>

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

    <!-- Bagian Unggah File Excel -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Unggah File Excel Data Siswa</h2>
        <p class="text-gray-600 mb-4">Silakan unggah file Excel (.xlsx atau .xls) yang berisi data siswa. Pastikan format kolom sesuai dengan template.</p>
        
        <div class="mb-4">
            <h3 class="font-semibold text-gray-700 mb-2">Format Kolom yang Diharapkan:</h3>
            <ul class="list-disc list-inside text-gray-600">
                <li>`nama_lengkap` (Wajib)</li>
                <li>`kelas_nama` (Wajib, harus sesuai dengan nama kelas di database, misal: "X IPA 1")</li>
                <li>`nisn` (Opsional, akan digunakan untuk update jika sudah ada)</li>
                <li>`nis` (Opsional, akan digunakan untuk update jika sudah ada)</li>
                <li>`rfid_tag` (Opsional)</li>
                <li>`tempat_lahir` (Opsional)</li>
                <li>`tanggal_lahir` (Opsional, format YYYY-MM-DD)</li>
                <li>`jenis_kelamin` (Opsional, 'L' atau 'P')</li>
                <li>`alamat` (Opsional)</li>
                <li>`no_telp_ortu` (Opsional)</li>
                <li>`email_ortu` (Opsional)</li>
                <li>`is_active` (Opsional, 1 untuk aktif, 0 untuk tidak aktif. Default 1 jika kosong)</li>
            </ul>
            <p class="mt-2 text-blue-600">
                <a href="<?php echo ASSETS_URL; ?>templates/template_import_siswa.xlsx" class="hover:underline">Unduh Template Excel</a>
            </p>
        </div>

        <form action="<?php echo ROOT_URL; ?>api/import_data.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="data_type" value="siswa">
            <div>
                <label for="excel_file" class="block text-gray-700 text-sm font-bold mb-2">Pilih File Excel:</label>
                <input type="file" id="excel_file" name="excel_file" accept=".xlsx, .xls" class="block w-full text-sm text-gray-500
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-full file:border-0
                    file:text-sm file:font-semibold
                    file:bg-blue-50 file:text-blue-700
                    hover:file:bg-blue-100" required>
            </div>
            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                Unggah dan Import
            </button>
        </form>
    </div>

    <!-- Hasil Import -->
    <?php if ($import_results): ?>
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
            <h3 class="font-bold text-lg mb-2">Hasil Import:</h3>
            <p>Total Baris Diproses: <span class="font-semibold"><?php echo htmlspecialchars($import_results['total']); ?></span></p>
            <p>Data Baru Ditambahkan: <span class="font-semibold text-green-800"><?php echo htmlspecialchars($import_results['inserted']); ?></span></p>
            <p>Data Diperbarui: <span class="font-semibold text-yellow-800"><?php echo htmlspecialchars($import_results['updated']); ?></span></p>
            <p>Data Gagal Diproses: <span class="font-semibold text-red-800"><?php echo htmlspecialchars($import_results['failed']); ?></span></p>
            <?php if (!empty($import_results['errors'])): ?>
                <h4 class="font-bold mt-3">Detail Kesalahan:</h4>
                <ul class="list-disc list-inside text-red-700">
                    <?php foreach ($import_results['errors'] as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
