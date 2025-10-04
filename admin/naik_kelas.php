<?php
// project-root/admin/naik_kelas.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Kelas.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$siswa_model = null;
$kelas_model = null;
$pdo = null;

$all_kelas = [];
$siswa_di_kelas_asal = [];
$selected_kelas_asal_id = null;
$selected_kelas_tujuan_id = null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $siswa_model = new Siswa($pdo);
    $kelas_model = new Kelas($pdo);

    $all_kelas = $kelas_model->getAll();

    // Handle form submission untuk menampilkan siswa
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['kelas_asal_id'])) {
        $selected_kelas_asal_id = (int)$_GET['kelas_asal_id'];
        $selected_kelas_tujuan_id = (int)$_GET['kelas_tujuan_id'] ?? null; // Ambil juga kelas tujuan jika ada

        if ($selected_kelas_asal_id > 0) {
            // getSiswaByKelasId sekarang hanya mengambil siswa aktif (is_active = 1)
            $siswa_di_kelas_asal = $siswa_model->getSiswaByKelasId($selected_kelas_asal_id); 
        }
    }

    // Handle form submission untuk proses naik kelas
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'naik_kelas') {
        $kelas_asal_id_post = (int)$_POST['kelas_asal_id'] ?? 0;
        $kelas_tujuan_id_post = (int)$_POST['kelas_tujuan_id'] ?? 0;
        $siswa_terpilih_ids = $_POST['siswa_ids'] ?? []; // Array of student IDs to promote/graduate

        if (empty($kelas_asal_id_post)) {
            set_flash('error', 'Kelas asal harus dipilih.');
            redirect(ROOT_URL . 'admin/naik_kelas.php');
        }

        // Ambil data kelas asal untuk menentukan tingkat
        $kelas_asal_data = $kelas_model->findById($kelas_asal_id_post);
        if (!$kelas_asal_data) {
            set_flash('error', 'Kelas asal tidak valid.');
            redirect(ROOT_URL . 'admin/naik_kelas.php');
        }

        $tingkat_kelas_asal = $kelas_asal_data['tingkat'];
        $is_final_year_class = false;
        // Asumsi kelas akhir adalah 'XII' untuk SMA
        if ($tingkat_kelas_asal === 'XII') {
            $is_final_year_class = true;
        }

        if ($is_final_year_class) {
            // Jika kelas XII, siswa akan diluluskan
            if (!empty($siswa_terpilih_ids)) {
                // Panggil graduateSiswa yang akan mengatur status_siswa = 'Lulus' dan is_active = 0
                if ($siswa_model->graduateSiswa($siswa_terpilih_ids)) {
                    set_flash('success', count($siswa_terpilih_ids) . ' siswa dari kelas ' . htmlspecialchars($kelas_asal_data['nama_kelas']) . ' berhasil diluluskan.');
                } else {
                    set_flash('error', 'Gagal meluluskan siswa dari kelas ' . htmlspecialchars($kelas_asal_data['nama_kelas']) . '.');
                }
            } else {
                set_flash('info', 'Tidak ada siswa yang dipilih untuk diluluskan.');
            }
        } else {
            // Jika bukan kelas akhir, siswa akan dinaikkan kelas
            if (empty($kelas_tujuan_id_post)) {
                set_flash('error', 'Kelas tujuan harus dipilih untuk kenaikan kelas.');
                redirect(ROOT_URL . 'admin/naik_kelas.php?kelas_asal_id=' . $kelas_asal_id_post);
            }
            if (!empty($siswa_terpilih_ids)) {
                // updateSiswaKelas akan mengatur kelas_id, dan memastikan status_siswa = 'Aktif', is_active = 1
                if ($siswa_model->updateSiswaKelas($siswa_terpilih_ids, $kelas_tujuan_id_post)) {
                    $kelas_tujuan_data = $kelas_model->findById($kelas_tujuan_id_post);
                    set_flash('success', count($siswa_terpilih_ids) . ' siswa dari kelas ' . htmlspecialchars($kelas_asal_data['nama_kelas']) . ' berhasil dinaikkan ke kelas ' . htmlspecialchars($kelas_tujuan_data['nama_kelas']) . '.');
                } else {
                    set_flash('error', 'Gagal menaikkan kelas siswa.');
                }
            } else {
                set_flash('info', 'Tidak ada siswa yang dipilih untuk dinaikkan kelas.');
            }
        }
        redirect(ROOT_URL . 'admin/naik_kelas.php'); // Redirect kembali ke halaman ini tanpa query params
    }

} catch (PDOException $e) {
    error_log("Naik Kelas Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Naik Kelas General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header
require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Naik Kelas & Kelulusan Siswa</h1>

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

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Pilih Kelas Asal dan Tujuan</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label for="kelas_asal_id" class="block text-gray-700 text-sm font-bold mb-2">Kelas Asal:</label>
                <select id="kelas_asal_id" name="kelas_asal_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">-- Pilih Kelas Asal --</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>" <?php echo ($selected_kelas_asal_id == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?> (Tingkat: <?php echo htmlspecialchars($kelas['tingkat']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="kelas_tujuan_id" class="block text-gray-700 text-sm font-bold mb-2">Kelas Tujuan (Kosongkan jika Lulus):</label>
                <select id="kelas_tujuan_id" name="kelas_tujuan_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">-- Pilih Kelas Tujuan --</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>" <?php echo ($selected_kelas_tujuan_id == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?> (Tingkat: <?php echo htmlspecialchars($kelas['tingkat']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-start items-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                    Tampilkan Siswa
                </button>
            </div>
        </form>
    </div>

    <?php if ($selected_kelas_asal_id): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Daftar Siswa Kelas <?php echo htmlspecialchars($kelas_asal_data['nama_kelas'] ?? 'Tidak Ditemukan'); ?></h2>
            <?php if (empty($siswa_di_kelas_asal)): ?>
                <p class="text-gray-600">Tidak ada siswa aktif di kelas ini.</p>
            <?php else: ?>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="naik_kelas">
                    <input type="hidden" name="kelas_asal_id" value="<?php echo htmlspecialchars($selected_kelas_asal_id); ?>">
                    <input type="hidden" name="kelas_tujuan_id" value="<?php echo htmlspecialchars($selected_kelas_tujuan_id ?? ''); ?>">

                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                    <th class="py-3 px-6 text-left">
                                        <input type="checkbox" id="checkAllSiswa" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                    </th>
                                    <th class="py-3 px-6 text-left">NISN</th>
                                    <th class="py-3 px-6 text-left">Nama Siswa</th>
                                    <th class="py-3 px-6 text-left">Status Saat Ini</th> <!-- Tambahkan kolom status -->
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 text-sm">
                                <?php foreach ($siswa_di_kelas_asal as $siswa): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-3 px-6">
                                            <input type="checkbox" name="siswa_ids[]" value="<?php echo htmlspecialchars($siswa['id']); ?>" class="form-checkbox h-4 w-4 text-blue-600 rounded siswa-checkbox">
                                        </td>
                                        <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                                        <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                        <td class="py-3 px-6">
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold
                                                <?php
                                                    if ($siswa['status_siswa'] == 'Aktif') echo 'bg-green-200 text-green-800';
                                                    elseif ($siswa['status_siswa'] == 'Pindah') echo 'bg-yellow-200 text-yellow-800';
                                                    elseif ($siswa['status_siswa'] == 'Lulus') echo 'bg-purple-200 text-purple-800';
                                                    else echo 'bg-gray-200 text-gray-800'; // Fallback
                                                ?>">
                                                <?php echo htmlspecialchars($siswa['status_siswa']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end mt-6">
                        <?php if (($kelas_asal_data['tingkat'] ?? '') === 'XII'): // Asumsi kelas XII adalah kelas terakhir ?>
                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                                Luluskan Siswa Terpilih
                            </button>
                        <?php else: ?>
                            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                                Naikkan Kelas Siswa Terpilih
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkAllSiswa = document.getElementById('checkAllSiswa');
    const siswaCheckboxes = document.querySelectorAll('.siswa-checkbox');

    if (checkAllSiswa) {
        checkAllSiswa.addEventListener('change', function() {
            siswaCheckboxes.forEach(checkbox => {
                checkbox.checked = checkAllSiswa.checked;
            });
        });
    }

    siswaCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!this.checked) {
                if (checkAllSiswa) {
                    checkAllSiswa.checked = false;
                }
            } else {
                // Check if all other checkboxes are now checked
                const allChecked = Array.from(siswaCheckboxes).every(cb => cb.checked);
                if (allChecked && checkAllSiswa) {
                    checkAllSiswa.checked = true;
                }
            }
        });
    });

    // Validasi saat submit form naik kelas
    const naikKelasForm = document.querySelector('form[name="action"][value="naik_kelas"]');
    if (naikKelasForm) {
        naikKelasForm.addEventListener('submit', function(event) {
            const selectedStudents = document.querySelectorAll('.siswa-checkbox:checked').length;
            if (selectedStudents === 0) {
                // Mengganti alert dengan modal custom jika memungkinkan
                alert('Pilih setidaknya satu siswa untuk diproses.'); 
                event.preventDefault(); // Mencegah form disubmit
                return; // Hentikan eksekusi lebih lanjut
            }

            const kelasAsalSelect = document.getElementById('kelas_asal_id');
            const selectedKelasAsalOption = kelasAsalSelect.options[kelasAsalSelect.selectedIndex];
            const tingkatKelasAsal = selectedKelasAsalOption.textContent.match(/\(Tingkat: (.*?)\)/);

            if (!tingkatKelasAsal) {
                // Mengganti alert dengan modal custom jika memungkinkan
                alert('Tidak dapat menentukan tingkat kelas asal.');
                event.preventDefault();
                return;
            }

            const tingkat = tingkatKelasAsal[1]; // Ambil nilai tingkat (misal: 'X', 'XI', 'XII')
            const kelasTujuanId = document.querySelector('input[name="kelas_tujuan_id"]').value;

            if (tingkat !== 'XII') { // Jika bukan kelas akhir
                if (kelasTujuanId === '' || kelasTujuanId === '0') { // Periksa juga '0' jika value default option adalah 0
                    // Mengganti alert dengan modal custom jika memungkinkan
                    alert('Untuk kenaikan kelas, Anda harus memilih Kelas Tujuan.');
                    event.preventDefault();
                    return;
                }
            } else { // Jika kelas XII (kelas akhir)
                if (kelasTujuanId !== '' && kelasTujuanId !== '0') {
                    // Mengganti alert dengan modal custom jika memungkinkan
                    alert('Siswa kelas XII akan diluluskan. Kelas tujuan tidak diperlukan dan akan diabaikan.');
                    // Tidak mencegah submit, hanya memberi peringatan
                    // event.preventDefault(); // Tidak perlu mencegah, biarkan proses kelulusan
                }
            }
        });
    }
});
</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
