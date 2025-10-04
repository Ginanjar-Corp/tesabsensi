<?php
// project-root/public_parent/informasi_umum.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_parent.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/InformasiUmum.php'; // Sertakan kelas InformasiUmum

// Pastikan orang tua sudah login
require_parent_login();

$siswa_id = get_current_parent_siswa_id();
$nama_lengkap_siswa = get_current_parent_nama_lengkap();

$informasi_umum_model = null;
$pdo = null;
$informasi_data = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $informasi_umum_model = new InformasiUmum($pdo);

    // Ambil semua informasi umum yang berstatus 'Aktif'
    $filters = ['status_publikasi' => 'Aktif'];
    $informasi_data = $informasi_umum_model->getAll($filters);

} catch (Exception $e) {
    error_log("Parent Informasi Umum Page Error: " . $e->getMessage());
    // Handle error gracefully
    $informasi_data = [];
}

$page_title = "Informasi Umum";

require_once __DIR__ . '/../components/header_parent.php';
?>
    <!-- Main Content -->
    <div class="flex-1 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Informasi Umum untuk Orang Tua</h1>
            <div class="flex items-center space-x-4">
                <span class="text-gray-700">Selamat datang, <span class="font-semibold"><?php echo htmlspecialchars($nama_lengkap_siswa); ?></span>!</span>
                <img src="https://placehold.co/40x40/cbd5e1/475569?text=PT" alt="User Avatar" class="rounded-full border-2 border-indigo-400">
            </div>
        </div>

        <?php if (empty($informasi_data)): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
                <p class="font-bold">Tidak ada informasi umum yang tersedia saat ini.</p>
                <p>Silakan periksa kembali nanti untuk pembaruan.</p>
            </div>
        <?php else: ?>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Daftar Informasi Umum</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">No.</th>
                                <th class="py-3 px-6 text-left">Judul</th>
                                <th class="py-3 px-6 text-left">Tanggal Publikasi</th>
                                <th class="py-3 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm">
                            <?php $no = 1; ?>
                            <?php foreach ($informasi_data as $info): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($info['judul']); ?></td>
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(format_date_indonesian($info['tanggal_publikasi'])); ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <button
                                            type="button"
                                            class="show-detail-btn bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-1 px-3 rounded-lg text-xs"
                                            data-id="<?php echo htmlspecialchars($info['id']); ?>"
                                            data-judul="<?php echo htmlspecialchars($info['judul']); ?>"
                                            data-tanggal="<?php echo htmlspecialchars(format_date_indonesian($info['tanggal_publikasi'])); ?>"
                                            data-konten="<?php echo htmlspecialchars(nl2br($info['konten'])); ?>"
                                        >
                                            Lihat Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Detail Informasi -->
    <div id="informasiDetailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                <h3 class="text-2xl font-bold text-gray-800" id="modalJudul"></h3>
                <button class="close-modal-btn text-gray-400 hover:text-gray-600 text-3xl leading-none font-semibold">&times;</button>
            </div>
            <div class="mt-4">
                <p class="text-gray-600 text-sm mb-3">Dipublikasikan: <span id="modalTanggal"></span></p>
                <div class="prose max-w-none text-gray-800" id="modalKonten"></div>
            </div>
            <div class="flex justify-end pt-4 border-t border-gray-200 mt-4">
                <button class="close-modal-btn bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Tutup</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('informasiDetailModal');
            const closeButtons = document.querySelectorAll('.close-modal-btn');
            const showDetailButtons = document.querySelectorAll('.show-detail-btn');
            const modalJudul = document.getElementById('modalJudul');
            const modalTanggal = document.getElementById('modalTanggal');
            const modalKonten = document.getElementById('modalKonten');

            showDetailButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modalJudul.textContent = this.dataset.judul;
                    modalTanggal.textContent = this.dataset.tanggal;
                    modalKonten.innerHTML = this.dataset.konten; // Gunakan innerHTML karena konten sudah di-nl2br
                    modal.classList.remove('hidden');
                });
            });

            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modal.classList.add('hidden');
                });
            });

            // Tutup modal jika mengklik di luar area modal
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        });
    </script>
<?php
require_once __DIR__ . '/../components/footer_parent.php';
?>
