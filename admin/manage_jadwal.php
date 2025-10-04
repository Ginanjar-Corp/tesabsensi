<?php
// project-root/admin/manage_jadwal.php

require_once __DIR__ . '/../config/database.php'; // Atau constants.php jika koneksi ada di sana
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/MataPelajaran.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/Siswa.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$jadwal_model = null;
$kelas_model = null;
$mapel_model = null;
$guru_model = null;
$pdo = null;

$siswa_model = null;
$all_siswa = [];

$all_jadwal = [];
$all_kelas = [];
$all_mapel = [];
$all_guru = [];
$all_tahun_ajaran = []; // Variabel baru untuk tahun ajaran dinamis

$days_of_week = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$semesters = ['Ganjil', 'Genap'];
// $academic_years = ['2023/2024', '2024/2025', '2025/2026']; // Hapus baris ini

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $jadwal_model = new JadwalPelajaran($pdo);
    $kelas_model = new Kelas($pdo);
    $mapel_model = new MataPelajaran($pdo);
    $guru_model = new Guru($pdo);
    
    $siswa_model = new Siswa($pdo);
    $all_siswa = $siswa_model->getAll();

    // Mengambil semua jadwal dan memastikan kolom tipe_jadwal ada
    $all_jadwal = $jadwal_model->getAll();
    foreach ($all_jadwal as &$jadwal) {
        if (!isset($jadwal['tipe_jadwal'])) {
            // Tentukan tipe_jadwal berdasarkan keberadaan kelas_id
            $jadwal['tipe_jadwal'] = !empty($jadwal['kelas_id']) ? 'Kelas' : 'Siswa';
        }
    }
    unset($jadwal); // Hapus referensi untuk menghindari efek samping

    $all_kelas = $kelas_model->getAll();
    $all_mapel = $mapel_model->getAll();
    $all_guru = $guru_model->getAll();
    $all_tahun_ajaran = getAllTahunAjaran(); // Ambil tahun ajaran secara dinamis

    // Handle form submission for Add/Edit/Delete
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // Common fields for add/edit
        $id = (int)($_POST['id'] ?? 0);
        $tipe_jadwal = trim($_POST['tipe_jadwal'] ?? 'Kelas'); // Ambil tipe jadwal
        $mapel_id = (int)($_POST['mapel_id'] ?? 0);
        $guru_id = (int)($_POST['guru_id'] ?? 0);
        $hari = trim($_POST['hari'] ?? '');
        $jam_mulai = trim($_POST['jam_mulai'] ?? '');
        $jam_selesai = trim($_POST['jam_selesai'] ?? '');
        $jumlah_jam = (int)($_POST['jumlah_jam'] ?? 1);
        $ruangan = trim($_POST['ruangan'] ?? null);
        $semester = trim($_POST['semester'] ?? '');
        $tahun_ajaran = trim($_POST['tahun_ajaran'] ?? '');

        // Data yang akan dikirim ke model
        $data_to_save = [
            'tipe_jadwal' => $tipe_jadwal,
            'mapel_id' => $mapel_id,
            'guru_id' => $guru_id,
            'hari' => $hari,
            'jam_mulai' => $jam_mulai,
            'jam_selesai' => $jam_selesai,
            'jumlah_jam' => $jumlah_jam,
            'ruangan' => $ruangan,
            'semester' => $semester,
            'tahun_ajaran' => $tahun_ajaran,
        ];
        
        // Validasi berdasarkan tipe jadwal
        $isValid = true;
        if ($tipe_jadwal === 'Kelas') {
            $kelas_id = (int)($_POST['kelas_id'] ?? 0);
            if (empty($kelas_id)) {
                $isValid = false;
                set_flash('error', 'Pilih kelas untuk jadwal per kelas.');
            }
            $data_to_save['kelas_id'] = $kelas_id;
            $data_to_save['siswa_ids'] = []; // Kosongkan siswa_ids jika tidak diperlukan
        } else { // 'Siswa'
            $siswa_ids = $_POST['siswa_ids'] ?? [];
            if (empty($siswa_ids)) {
                $isValid = false;
                set_flash('error', 'Pilih minimal satu siswa untuk jadwal per siswa.');
            }
            $data_to_save['kelas_id'] = null; // Set kelas_id menjadi NULL
            $data_to_save['siswa_ids'] = $siswa_ids;
        }

        // Validasi input umum
        if ($isValid && (empty($mapel_id) || empty($guru_id) || empty($hari) || empty($jam_mulai) || empty($jam_selesai) || empty($semester) || empty($tahun_ajaran))) {
            $isValid = false;
            set_flash('error', 'Semua kolom wajib harus diisi.');
        }

        // Proses aksi jika validasi berhasil
        if ($isValid) {
            if ($action == 'add') {
                if ($jadwal_model->create($data_to_save)) {
                    set_flash('success', 'Jadwal pelajaran berhasil ditambahkan.');
                } else {
                    set_flash('error', 'Gagal menambahkan jadwal pelajaran.');
                }
            } elseif ($action == 'edit' && $id > 0) {
                if ($jadwal_model->update($id, $data_to_save)) {
                    set_flash('success', 'Jadwal pelajaran berhasil diperbarui.');
                } else {
                    set_flash('error', 'Gagal memperbarui jadwal pelajaran.');
                }
            }
        } elseif ($action == 'delete' && $id > 0) {
            if ($jadwal_model->deleteWithDependencies($id)) {
                set_flash('success', 'Data jadwal dan semua data absensi terkait berhasil dihapus.');
            } else {
                set_flash('error', 'Gagal menghapus data jadwal. Terjadi kesalahan sistem atau data terkait.');
            }
        }
        redirect(ROOT_URL . 'admin/manage_jadwal.php');
    }
}
} catch (PDOException $e) {
    error_log("Manage Jadwal Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
    redirect(ROOT_URL . 'admin/manage_jadwal.php');
} catch (Exception $e) {
    error_log("Manage Jadwal General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect(ROOT_URL . 'admin/manage_jadwal.php');
}

// Sertakan header
require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Jadwal Pelajaran</h1>

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

    <!-- Form Tambah/Edit Jadwal -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4" id="form-title">Tambah Jadwal Baru</h2>
        <form id="jadwalForm" action="<?php echo ROOT_URL; ?>admin/manage_jadwal.php" method="POST">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="id" id="jadwal-id" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="mb-4">
                    <label for="tipe_jadwal" class="block text-gray-700 text-sm font-bold mb-2">Tipe Jadwal:</label>
                    <select id="tipe_jadwal" name="tipe_jadwal" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="Kelas" selected>Per Kelas</option>
                        <option value="Siswa">Per Siswa</option>
                    </select>
                </div>
                <div id="kelas-container" class="mb-4">
                    <label for="kelas_id" class="block text-gray-700 text-sm font-bold mb-2">Pilih Kelas:</label>
                    <select id="kelas_id" name="kelas_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <?php foreach ($all_kelas as $kelas): ?>
                            <option value="<?php echo htmlspecialchars($kelas['id']); ?>">
                                <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="siswa-container" class="mb-4" style="display: none;">
                    <label for="siswa_ids" class="block text-gray-700 text-sm font-bold mb-2">Pilih Siswa:</label>
                    <select id="siswa_ids" name="siswa_ids[]" multiple class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" size="10">
                        <?php foreach ($all_siswa as $siswa): ?>
                            <option value="<?php echo htmlspecialchars($siswa['id']); ?>">
                                <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (Kelas: <?php echo htmlspecialchars($siswa['nama_kelas']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="mapel_id" class="block text-gray-700 text-sm font-bold mb-2">Mata Pelajaran:</label>
                    <select id="mapel_id" name="mapel_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Pilih Mata Pelajaran</option>
                        <?php foreach ($all_mapel as $mapel): ?>
                            <option value="<?php echo htmlspecialchars($mapel['id']); ?>"><?php echo htmlspecialchars($mapel['nama_mapel']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="guru_id" class="block text-gray-700 text-sm font-bold mb-2">Guru Pengajar:</label>
                    <select id="guru_id" name="guru_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Pilih Guru</option>
                        <?php foreach ($all_guru as $guru): ?>
                            <option value="<?php echo htmlspecialchars($guru['id']); ?>"><?php echo htmlspecialchars($guru['nama_lengkap']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="hari" class="block text-gray-700 text-sm font-bold mb-2">Hari:</label>
                    <select id="hari" name="hari" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Pilih Hari</option>
                        <?php foreach ($days_of_week as $day): ?>
                            <option value="<?php echo htmlspecialchars($day); ?>"><?php echo htmlspecialchars($day); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="jam_mulai" class="block text-gray-700 text-sm font-bold mb-2">Jam Mulai:</label>
                    <input type="time" id="jam_mulai" name="jam_mulai" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="jam_selesai" class="block text-gray-700 text-sm font-bold mb-2">Jam Selesai:</label>
                    <input type="time" id="jam_selesai" name="jam_selesai" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="jumlah_jam" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Jam Pelajaran:</label>
                    <input type="number" id="jumlah_jam" name="jumlah_jam" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="ruangan" class="block text-gray-700 text-sm font-bold mb-2">Ruangan (Opsional):</label>
                    <input type="text" id="ruangan" name="ruangan" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="semester" class="block text-gray-700 text-sm font-bold mb-2">Semester:</label>
                    <select id="semester" name="semester" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Pilih Semester</option>
                        <?php foreach ($semesters as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="tahun_ajaran" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                    <select id="tahun_ajaran" name="tahun_ajaran" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Pilih Tahun Ajaran</option>
                        <?php foreach ($all_tahun_ajaran as $ta): // Menggunakan $all_tahun_ajaran ?>
                            <option value="<?php echo htmlspecialchars($ta['tahun_ajaran']); ?>"><?php echo htmlspecialchars($ta['tahun_ajaran']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between mt-6">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Simpan Jadwal
                </button>
                <button type="button" id="cancelEdit" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hidden">
                    Batal Edit
                </button>
            </div>
        </form>
    </div>

    <!-- Daftar Jadwal dengan Tab -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Jadwal Pelajaran</h2>
        
        <?php if (empty($all_jadwal)): ?>
            <p class="text-gray-600">Belum ada data jadwal pelajaran.</p>
        <?php else: ?>
            <!-- Tab Navigation -->
            <div class="flex border-b border-gray-200 mb-6">
                <?php foreach ($days_of_week as $index => $day): ?>
                    <button id="tab-<?php echo strtolower($day); ?>" 
                        class="tab-button py-2 px-4 -mb-[2px] font-medium text-lg leading-normal focus:outline-none
                                <?php echo ($index == 0) ? 'border-b-2 border-blue-500 text-blue-500' : 'border-b-2 border-transparent text-gray-600 hover:text-blue-500 hover:border-blue-300'; ?>">
                        <?php echo htmlspecialchars($day); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Tab Content -->
            <div id="tab-content-container">
                <?php foreach ($days_of_week as $index => $day): ?>
                    <?php
                    // Filter jadwal untuk hari ini
                    $schedules_for_day = array_filter($all_jadwal, function($schedule) use ($day) {
                        return $schedule['hari'] === $day;
                    });
                    
                    // Urutkan jadwal untuk hari ini berdasarkan jam mulai
                    usort($schedules_for_day, function($a, $b) {
                        return strtotime($a['jam_mulai']) - strtotime($b['jam_mulai']);
                    });
                    ?>
                    <div id="content-<?php echo strtolower($day); ?>" class="tab-content <?php echo ($index == 0) ? 'block' : 'hidden'; ?>">
                        <?php if (!empty($schedules_for_day)): ?>
                            <div class="overflow-x-auto mb-6">
                                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                                    <thead>
                                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                            <th class="py-3 px-6 text-left">No.</th>
                                            <th class="py-3 px-6 text-left">Waktu</th>
                                            <th class="py-3 px-6 text-left">Tipe</th>
                                            <th class="py-3 px-6 text-left">Kelas/Siswa</th>
                                            <th class="py-3 px-6 text-left">Mata Pelajaran</th>
                                            <th class="py-3 px-6 text-left">Guru</th>
                                            <th class="py-3 px-6 text-left">JP</th>
                                            <th class="py-3 px-6 text-left">Ruangan</th>
                                            <th class="py-3 px-6 text-left">Semester</th>
                                            <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                                            <th class="py-3 px-6 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-gray-700 text-sm">
                                        <?php $no = 1; ?>
                                        <?php foreach ($schedules_for_day as $jadwal): ?>
                                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                                <td class="py-3 px-6"><?php echo substr(htmlspecialchars($jadwal['jam_mulai']), 0, 5) . ' - ' . substr(htmlspecialchars($jadwal['jam_selesai']), 0, 5); ?></td>
                                                <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['tipe_jadwal']); ?></td>
                                                <td class="py-3 px-6">
                                                    <?php 
                                                        if ($jadwal['tipe_jadwal'] == 'Kelas') {
                                                            echo htmlspecialchars($jadwal['nama_kelas']);
                                                        } else {
                                                            // Ambil daftar siswa untuk jadwal privat
                                                            $siswa_terkait = $jadwal_model->getStudentsByJadwalId($jadwal['id']);
                                                            $siswa_names = array_column($siswa_terkait, 'nama_lengkap');
                                                            echo htmlspecialchars(implode(', ', $siswa_names));
                                                        }
                                                    ?>
                                                </td>
                                                <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['nama_mapel']); ?></td>
                                                <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['nama_guru']); ?></td>
                                                <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['jumlah_jam']); ?></td>
                                                <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['ruangan'] ?? '-'); ?></td>
                                                <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['semester']); ?></td>
                                                <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['tahun_ajaran']); ?></td>
                                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                                    <button type="button" onclick="editJadwal(<?php echo htmlspecialchars(json_encode($jadwal)); ?>)" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-1 px-3 rounded text-xs mr-2">Edit</button>
                                                    <form action="<?php echo ROOT_URL; ?>admin/manage_jadwal.php" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus jadwal ini? Tindakan ini akan menghapus semua data absensi guru dan siswa yang terkait secara permanen!');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($jadwal['id']); ?>">
                                                        <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-xs">Hapus</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-600">Tidak ada jadwal untuk hari <?php echo htmlspecialchars($day); ?>.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function editJadwal(jadwalData) {
    document.getElementById('form-title').textContent = 'Edit Data Jadwal Pelajaran';
    document.getElementById('form-action').value = 'edit';
    document.getElementById('jadwal-id').value = jadwalData.id;

    // Set nilai untuk dropdown tipe jadwal
    document.getElementById('tipe_jadwal').value = jadwalData.tipe_jadwal;

    // Tampilkan/sembunyikan bagian form berdasarkan tipe jadwal
    if (jadwalData.tipe_jadwal === 'Siswa') {
        document.getElementById('kelas-container').style.display = 'none';
        document.getElementById('siswa-container').style.display = 'block';

        // Ambil data siswa terkait dari server dan tandai di select
        fetch(`<?php echo ROOT_URL; ?>api/get_siswa_by_jadwal.php?jadwal_id=${jadwalData.id}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(siswa_ids => {
                const siswaSelect = document.getElementById('siswa_ids');
                // Hapus pilihan sebelumnya
                Array.from(siswaSelect.options).forEach(option => option.selected = false);
                // Tandai siswa yang terkait
                siswa_ids.forEach(siswa_id => {
                    const option = siswaSelect.querySelector(`option[value="${siswa_id}"]`);
                    if (option) {
                        option.selected = true;
                    }
                });
            })
            .catch(error => {
                console.error('Error fetching student data:', error);
                alert('Gagal mengambil data siswa yang terkait. Silakan coba lagi.');
            });

    } else { // 'Kelas'
        document.getElementById('kelas-container').style.display = 'block';
        document.getElementById('siswa-container').style.display = 'none';
        document.getElementById('kelas_id').value = jadwalData.kelas_id;
    }

    // Isi bidang form lainnya
    document.getElementById('mapel_id').value = jadwalData.mapel_id;
    document.getElementById('guru_id').value = jadwalData.guru_id;
    document.getElementById('hari').value = jadwalData.hari;
    document.getElementById('jam_mulai').value = jadwalData.jam_mulai.substring(0, 5);
    document.getElementById('jam_selesai').value = jadwalData.jam_selesai.substring(0, 5);
    document.getElementById('jumlah_jam').value = jadwalData.jumlah_jam || '';
    document.getElementById('ruangan').value = jadwalData.ruangan || '';
    document.getElementById('semester').value = jadwalData.semester;
    document.getElementById('tahun_ajaran').value = jadwalData.tahun_ajaran;

    document.getElementById('cancelEdit').classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.getElementById('cancelEdit').addEventListener('click', function() {
    document.getElementById('form-title').textContent = 'Tambah Jadwal Baru';
    document.getElementById('form-action').value = 'add';
    document.getElementById('jadwal-id').value = '';
    document.getElementById('jadwalForm').reset();
    this.classList.add('hidden');
});

document.addEventListener('DOMContentLoaded', function() {
    const tipeJadwalSelect = document.getElementById('tipe_jadwal');
    const kelasContainer = document.getElementById('kelas-container');
    const siswaContainer = document.getElementById('siswa-container');

    function toggleFormSections() {
        if (tipeJadwalSelect.value === 'Siswa') {
            kelasContainer.style.display = 'none';
            siswaContainer.style.display = 'block';
        } else {
            kelasContainer.style.display = 'block';
            siswaContainer.style.display = 'none';
        }
    }

    tipeJadwalSelect.addEventListener('change', toggleFormSections);
    toggleFormSections();

    // === LOGIKA TAB PER HARI BARU ===
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    function showTab(day) {
        tabButtons.forEach(btn => {
            btn.classList.remove('border-blue-500', 'text-blue-500');
            btn.classList.add('border-transparent', 'text-gray-600', 'hover:text-blue-500', 'hover:border-blue-300');
        });
        tabContents.forEach(content => {
            content.classList.add('hidden');
        });

        const activeTab = document.getElementById(`tab-${day}`);
        const activeContent = document.getElementById(`content-${day}`);
        
        if(activeTab && activeContent) {
            activeTab.classList.add('border-blue-500', 'text-blue-500');
            activeTab.classList.remove('border-transparent', 'text-gray-600', 'hover:text-blue-500', 'hover:border-blue-300');
            activeContent.classList.remove('hidden');
        }
    }

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const day = this.id.replace('tab-', '');
            showTab(day);
        });
    });

    const today = new Date();
    const todayDayIndex = today.getDay(); // 0 = Minggu, 1 = Senin, dst
    const daysMap = ['minggu', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];
    const todayDayName = daysMap[todayDayIndex];
    
    const defaultDay = (todayDayName === 'minggu') ? 'senin' : todayDayName;
    showTab(defaultDay);
});
</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
