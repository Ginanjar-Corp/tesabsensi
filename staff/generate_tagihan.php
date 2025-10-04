<?php
// project-root/admin/generate_tagihan.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/JenisPembayaran.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/TagihanSiswa.php';

// Memastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$siswa_model = null;
$jenis_pembayaran_model = null;
$tahun_ajaran_model = null;
$kelas_model = null;
$tagihan_siswa_model = null;
$pdo = null;

$all_siswa_plain = []; // Digunakan untuk daftar siswa saat ini (tidak terfilter kelas)
$all_jenis_pembayaran = [];
$all_tahun_ajaran = [];
$all_kelas = [];
$active_tahun_ajaran_id = null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $siswa_model = new Siswa($pdo);
    $jenis_pembayaran_model = new JenisPembayaran($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);
    $kelas_model = new Kelas($pdo);
    $tagihan_siswa_model = new TagihanSiswa($pdo);

    // Dapatkan data master
    $all_siswa_plain = $siswa_model->getAll(); // Untuk dropdown siswa umum jika diperlukan
    $all_jenis_pembayaran = $jenis_pembayaran_model->getAll();
    $all_tahun_ajaran = $tahun_ajaran_model->getAll();
    $all_kelas = $kelas_model->getAll();
    
    $active_tahun_ajaran = $tahun_ajaran_model->getActiveTahunAjaran();
    if ($active_tahun_ajaran) {
        $active_tahun_ajaran_id = $active_tahun_ajaran['id'];
    }

    // Handle AJAX request untuk mendapatkan siswa berdasarkan kelas
    if (isset($_GET['action']) && $_GET['action'] === 'get_siswa_by_kelas' && isset($_GET['kelas_id'])) {
        $kelas_id = (int)$_GET['kelas_id'];
        $siswa_in_kelas = $siswa_model->getAllStudentsByKelas($kelas_id);
        header('Content-Type: application/json');
        echo json_encode($siswa_in_kelas);
        exit;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_tagihan') {
        $jenis_pembayaran_id = $_POST['jenis_pembayaran_id'];
        $tahun_ajaran_id = $_POST['tahun_ajaran_id'];
        $tanggal_jatuh_tempo_umum = $_POST['tanggal_jatuh_tempo'] ?? null; // Jatuh tempo umum
        $selected_kelas_id = $_POST['kelas_id'] ?? null;
        $selected_siswa_ids = $_POST['selected_siswa_ids'] ?? []; // Siswa yang dipilih dari daftar dinamis
        $tipe_pembayaran_form = $_POST['tipe_pembayaran_hidden']; // Ambil tipe pembayaran dari hidden input

        // Validasi input
        if (empty($jenis_pembayaran_id) || empty($tahun_ajaran_id)) {
            set_flash('error', 'Jenis Pembayaran dan Tahun Ajaran harus diisi.');
            redirect(ROOT_URL . 'admin/generate_tagihan.php');
        }

        if (empty($selected_siswa_ids)) {
            set_flash('error', 'Pilih setidaknya satu siswa untuk membuat tagihan.');
            redirect(ROOT_URL . 'admin/generate_tagihan.php');
        }

        $jenis_pembayaran_detail = $jenis_pembayaran_model->getById($jenis_pembayaran_id);
        if (!$jenis_pembayaran_detail) {
            set_flash('error', 'Jenis Pembayaran tidak valid.');
            redirect(ROOT_URL . 'admin/generate_tagihan.php');
        }

        $successful_generations = 0;
        $tipe_pembayaran = $jenis_pembayaran_detail['tipe_pembayaran'];

        foreach ($selected_siswa_ids as $siswa_id) {
            $siswa_detail = $siswa_model->findById($siswa_id);
            if (!$siswa_detail) {
                error_log("Siswa dengan ID {$siswa_id} tidak ditemukan.");
                continue;
            }

            // Untuk pembayaran bulanan, buat tagihan untuk setiap bulan yang dipilih
            if ($tipe_pembayaran === 'Rutin Bulanan') {
                $selected_months_with_amounts = $_POST['monthly_amounts'] ?? []; // Ambil dari input dinamis
                if (empty($selected_months_with_amounts)) {
                    set_flash('error', 'Untuk jenis pembayaran rutin bulanan, Anda harus memilih setidaknya satu bulan dan mengisi jumlahnya.');
                    redirect(ROOT_URL . 'admin/generate_tagihan.php');
                }

                // Ambil tahun ajaran aktif untuk menentukan tahun yang benar
                $active_tahun_ajaran_detail = $tahun_ajaran_model->getActiveTahunAjaran();
                $tahun_mulai_ajaran = null;
                if ($active_tahun_ajaran_detail) {
                    // Asumsi format 'YYYY/YYYY'
                    $tahun_parts = explode('/', $active_tahun_ajaran_detail['tahun_ajaran']);
                    $tahun_mulai_ajaran = (int)$tahun_parts[0];
                }

                foreach ($selected_months_with_amounts as $month_year_str => $jumlah_bulan) {
                    if (empty($jumlah_bulan) || $jumlah_bulan <= 0) {
                        continue; // Lewati bulan yang tidak diisi jumlahnya
                    }
                    
                    // month_year_str akan seperti 'Juli 2024'
                    list($month_name, $year_for_period) = explode(' ', $month_year_str);
                    $month_num = array_search($month_name, [
                        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
                        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
                        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                    ]);
                    // Jika array search tidak langsung mengembalikan angka, perlu penyesuaian:
                    if ($month_num === false) { // Coba array search dengan nilai yang disimpan
                        $month_num_map = [
                            'Juli' => '07', 'Agustus' => '08', 'September' => '09',
                            'Oktober' => '10', 'November' => '11', 'Desember' => '12',
                            'Januari' => '01', 'Februari' => '02', 'Maret' => '03',
                            'April' => '04', 'Mei' => '05', 'Juni' => '06'
                        ];
                        $month_num = $month_num_map[$month_name] ?? null;
                    }


                    $periode_tagihan = $month_name . ' ' . $year_for_period; // Contoh: 'Juli 2024'
                    
                    // Tentukan tanggal jatuh tempo otomatis (tanggal 1 setiap bulan)
                    // Jika $tanggal_jatuh_tempo_umum tidak kosong, gunakan itu
                    // Jika tidak, gunakan tanggal 1 bulan ini
                    $current_tanggal_jatuh_tempo = !empty($tanggal_jatuh_tempo_umum) 
                                                    ? $tanggal_jatuh_tempo_umum 
                                                    : ($year_for_period . '-' . $month_num . '-01');

                    $tagihan_siswa_model->siswa_id = $siswa_detail['id'];
                    $tagihan_siswa_model->jenis_pembayaran_id = $jenis_pembayaran_id;
                    $tagihan_siswa_model->tahun_ajaran_id = $tahun_ajaran_id;
                    $tagihan_siswa_model->kelas_id_saat_tagih = $siswa_detail['kelas_id'];
                    $tagihan_siswa_model->jumlah_tagihan = $jumlah_bulan;
                    $tagihan_siswa_model->sisa_tagihan = $jumlah_bulan;
                    $tagihan_siswa_model->periode_tagihan = $periode_tagihan;
                    $tagihan_siswa_model->tanggal_jatuh_tempo = $current_tanggal_jatuh_tempo;
                    $tagihan_siswa_model->status_tagihan = 'Belum Lunas';

                    if ($tagihan_siswa_model->create()) {
                        $successful_generations++;
                    } else {
                        error_log("Gagal membuat tagihan bulanan untuk siswa ID {$siswa_id}, bulan {$periode_tagihan}.");
                    }
                }
            } 
            // Untuk pembayaran bebas, buat satu tagihan
            else if ($tipe_pembayaran === 'Bebas') {
                $jumlah_tagihan_bebas = $_POST['jumlah_tagihan'] ?? 0;
                if (empty($jumlah_tagihan_bebas) || $jumlah_tagihan_bebas <= 0) {
                    set_flash('error', 'Jumlah tagihan untuk pembayaran bebas harus diisi.');
                    redirect(ROOT_URL . 'admin/generate_tagihan.php');
                }

                $periode_tagihan = $_POST['periode_tagihan_bebas'] ?? null; // Deskripsi bebas
                $tagihan_siswa_model->siswa_id = $siswa_detail['id'];
                $tagihan_siswa_model->jenis_pembayaran_id = $jenis_pembayaran_id;
                $tagihan_siswa_model->tahun_ajaran_id = $tahun_ajaran_id;
                $tagihan_siswa_model->kelas_id_saat_tagih = $siswa_detail['kelas_id'];
                $tagihan_siswa_model->jumlah_tagihan = $jumlah_tagihan_bebas;
                $tagihan_siswa_model->sisa_tagihan = $jumlah_tagihan_bebas;
                $tagihan_siswa_model->periode_tagihan = $periode_tagihan;
                $tagihan_siswa_model->tanggal_jatuh_tempo = !empty($tanggal_jatuh_tempo_umum) ? $tanggal_jatuh_tempo_umum : null;
                $tagihan_siswa_model->status_tagihan = 'Belum Lunas';

                if ($tagihan_siswa_model->create()) {
                    $successful_generations++;
                } else {
                    error_log("Gagal membuat tagihan bebas untuk siswa ID {$siswa_id}.");
                }
            }
        }

        if ($successful_generations > 0) {
            set_flash('success', "Berhasil membuat {$successful_generations} tagihan.");
        } else {
            set_flash('error', 'Gagal membuat tagihan. Periksa log.');
        }
        redirect(ROOT_URL . 'admin/generate_tagihan.php');
    }

} catch (PDOException $e) {
    error_log("Generate Tagihan Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Generate Tagihan General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Buat Tagihan Siswa</h1>

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

    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border border-gray-200">
        <h2 class="text-xl font-semibold mb-4 text-gray-800">Formulir Pembuatan Tagihan</h2>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="generate_tagihan">
            <input type="hidden" id="tipePembayaranHidden" name="tipe_pembayaran_hidden" value="">
            
            <div>
                <label for="tahun_ajaran_id" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                <select id="tahun_ajaran_id" name="tahun_ajaran_id"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                    <option value="">Pilih Tahun Ajaran</option>
                    <?php foreach ($all_tahun_ajaran as $ta): ?>
                        <option value="<?php echo htmlspecialchars($ta['id']); ?>" 
                                <?php echo ($active_tahun_ajaran_id == $ta['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ta['tahun_ajaran']); ?> <?php echo ($ta['is_active'] ? '(Aktif)' : ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="jenis_pembayaran_id" class="block text-gray-700 text-sm font-bold mb-2">Jenis Pembayaran:</label>
                <select id="jenis_pembayaran_id" name="jenis_pembayaran_id"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                    <option value="">Pilih Jenis Pembayaran</option>
                    <?php foreach ($all_jenis_pembayaran as $jp): ?>
                        <option value="<?php echo htmlspecialchars($jp['id']); ?>" 
                                data-tipe-pembayaran="<?php echo htmlspecialchars($jp['tipe_pembayaran']); ?>">
                            <?php echo htmlspecialchars($jp['nama_pembayaran']); ?> (<?php echo htmlspecialchars($jp['tipe_pembayaran']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="jumlahTagihanUtamaGroup">
                <label for="jumlah_tagihan_utama" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Tagihan Utama (Rp):</label>
                <input type="number" step="0.01" id="jumlah_tagihan_utama" name="jumlah_tagihan_utama" value=""
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                       placeholder="Contoh: 275000.00">
                <p class="text-xs text-gray-500 mt-1">Ini akan mengisi kolom jumlah per bulan secara otomatis (untuk bulanan) atau menjadi jumlah tagihan (untuk bebas).</p>
            </div>

            <div id="periodeTagihanGroup" class="hidden">
                <label for="periode_tagihan_bebas" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi Tagihan (Untuk Pembayaran Bebas):</label>
                <input type="text" id="periode_tagihan_bebas" name="periode_tagihan_bebas"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                       placeholder="Contoh: Ujian Semester Ganjil">
                <p class="text-xs text-gray-500 mt-1">Isi ini hanya untuk jenis pembayaran 'Bebas'.</p>
            </div>

            <div id="monthlySelectionGroup" class="hidden">
                <label class="block text-gray-700 text-sm font-bold mb-2">Pilih Bulan & Jumlah Tagihan (Untuk Pembayaran Rutin Bulanan):</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <?php
                    // Bulan dari Juli sampai Juni
                    $months = [
                        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                        '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
                        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                        '04' => 'April', '05' => 'Mei', '06' => 'Juni'
                    ];
                    $current_year = date('Y');
                    foreach ($months as $num => $name):
                        $display_year = ($num >= 7) ? $current_year : $current_year + 1; // Untuk tahun ajaran berjalan
                        $month_full_name = htmlspecialchars($name . ' ' . $display_year);
                    ?>
                        <div class="flex items-center space-x-2 border p-2 rounded-lg bg-gray-50">
                            <input type="checkbox" name="selected_months_checkbox[]" value="<?php echo $month_full_name; ?>" id="month-<?php echo $num . '-' . $display_year; ?>" class="form-checkbox h-4 w-4 text-blue-600 month-checkbox">
                            <label for="month-<?php echo $num . '-' . $display_year; ?>" class="text-gray-700 flex-1"><?php echo $month_full_name; ?></label>
                            <input type="number" step="0.01" name="monthly_amounts[<?php echo $month_full_name; ?>]" class="shadow border rounded py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm w-32 monthly-amount-input" placeholder="Jumlah" disabled>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-gray-500 mt-1">Pilih bulan dan isi jumlah tagihan. Jumlah tagihan utama di atas akan otomatis mengisi semua kolom jumlah di sini.</p>
            </div>


            <div>
                <label for="tanggal_jatuh_tempo" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Jatuh Tempo (Untuk Pembayaran Bebas, Opsional):</label>
                <input type="date" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">Untuk pembayaran bulanan, tanggal jatuh tempo akan otomatis tanggal 1 setiap bulan. Ini hanya berlaku untuk pembayaran bebas.</p>
            </div>

            <hr class="my-6 border-gray-300">

            <div>
                <label for="kelas_id" class="block text-gray-700 text-sm font-bold mb-2">Pilih Kelas:</label>
                <div class="flex flex-col md:flex-row items-end md:space-x-4">
                    <select id="kelas_id" name="kelas_id"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 md:flex-1" required>
                        <option value="">Pilih Kelas</option>
                        <?php foreach ($all_kelas as $kelas): ?>
                            <option value="<?php echo htmlspecialchars($kelas['id']); ?>">
                                <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="showStudentsButton" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 mt-2 md:mt-0">
                        Tampilkan Siswa
                    </button>
                </div>
            </div>

            <div id="studentListContainer" class="hidden bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-2">Pilih Siswa yang Akan Ditagih:</h3>
                <div id="studentList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 max-h-60 overflow-y-auto p-2">
                    <!-- Daftar siswa akan dimuat di sini oleh JavaScript -->
                    <p class="text-gray-600">Pilih kelas terlebih dahulu untuk menampilkan daftar siswa.</p>
                </div>
                <div class="flex items-center mt-4">
                    <input type="checkbox" id="selectAllStudents" class="form-checkbox h-4 w-4 text-blue-600">
                    <label for="selectAllStudents" class="ml-2 text-gray-700 text-sm font-bold">Pilih Semua Siswa</label>
                </div>
            </div>

            <div class="flex items-center">
                <button type="submit"
                        class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    Generate Tagihan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const jenisPembayaranSelect = document.getElementById('jenis_pembayaran_id');
        const tipePembayaranHidden = document.getElementById('tipePembayaranHidden');
        const jumlahTagihanUtamaInput = document.getElementById('jumlah_tagihan_utama');
        const periodeTagihanGroup = document.getElementById('periodeTagihanGroup');
        const periodeTagihanBebasInput = document.getElementById('periode_tagihan_bebas');
        const monthlySelectionGroup = document.getElementById('monthlySelectionGroup');
        const monthlyAmountInputs = document.querySelectorAll('.monthly-amount-input');
        const monthCheckboxes = document.querySelectorAll('.month-checkbox');
        const tanggalJatuhTempoInput = document.getElementById('tanggal_jatuh_tempo'); // Input jatuh tempo umum

        const kelasSelect = document.getElementById('kelas_id');
        const showStudentsButton = document.getElementById('showStudentsButton');
        const studentListContainer = document.getElementById('studentListContainer');
        const studentListDiv = document.getElementById('studentList');
        const selectAllStudentsCheckbox = document.getElementById('selectAllStudents');
        const form = document.querySelector('form');

        // Fungsi untuk mengupdate tampilan grup input berdasarkan tipe pembayaran
        function updatePaymentTypeFields() {
            const selectedOption = jenisPembayaranSelect.options[jenisPembayaranSelect.selectedIndex];
            const tipePembayaran = selectedOption.dataset.tipePembayaran;
            tipePembayaranHidden.value = tipePembayaran; // Update hidden input

            // Reset visibility
            monthlySelectionGroup.classList.add('hidden');
            periodeTagihanGroup.classList.add('hidden');
            jumlahTagihanUtamaInput.required = false; // Default: tidak wajib
            periodeTagihanBebasInput.required = false; // Default: tidak wajib
            tanggalJatuhTempoInput.required = false; // Default: tidak wajib
            tanggalJatuhTempoInput.parentNode.classList.remove('hidden'); // Default: tampilkan

            // Reset monthly inputs
            monthCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
                const amountInput = checkbox.closest('.flex').querySelector('.monthly-amount-input');
                if (amountInput) {
                    amountInput.value = '';
                    amountInput.disabled = true;
                }
            });

            if (tipePembayaran === 'Rutin Bulanan') {
                monthlySelectionGroup.classList.remove('hidden');
                // Untuk bulanan, jatuh tempo umum tidak wajib, akan diisi otomatis tanggal 1
                tanggalJatuhTempoInput.parentNode.classList.add('hidden'); 
            } else if (tipePembayaran === 'Bebas') {
                periodeTagihanGroup.classList.remove('hidden');
                periodeTagihanBebasInput.required = true;
                // Untuk bebas, jatuh tempo umum opsional
            }
            // Jumlah tagihan utama selalu ada, tapi jadi required kalau tipe Bebas
            jumlahTagihanUtamaInput.required = (tipePembayaran === 'Bebas');
        }

        // Event listener untuk perubahan jenis pembayaran
        jenisPembayaranSelect.addEventListener('change', updatePaymentTypeFields);

        // Panggil saat halaman dimuat untuk inisialisasi awal
        updatePaymentTypeFields();

        // Event listener untuk mengisi otomatis jumlah per bulan
        jumlahTagihanUtamaInput.addEventListener('change', function() {
            const mainAmount = this.value;
            monthCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    const amountInput = checkbox.closest('.flex').querySelector('.monthly-amount-input');
                    if (amountInput) {
                        amountInput.value = mainAmount;
                    }
                }
            });
        });

        // Event listener untuk checkbox bulan
        monthCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const amountInput = this.closest('.flex').querySelector('.monthly-amount-input');
                if (amountInput) {
                    amountInput.disabled = !this.checked;
                    if (this.checked) {
                        amountInput.value = jumlahTagihanUtamaInput.value; // Isi dengan jumlah utama saat dicentang
                    } else {
                        amountInput.value = ''; // Kosongkan jika tidak dicentang
                    }
                }
            });
        });


        // Event listener untuk tombol 'Tampilkan Siswa'
        showStudentsButton.addEventListener('click', function() {
            const kelasId = kelasSelect.value;
            if (kelasId) {
                // Tampilkan loading state atau kosongkan daftar
                studentListDiv.innerHTML = '<p class="text-gray-600">Memuat siswa...</p>';
                studentListContainer.classList.remove('hidden');
                
                // Lakukan AJAX request
                fetch(`<?php echo ROOT_URL; ?>admin/generate_tagihan.php?action=get_siswa_by_kelas&kelas_id=${kelasId}`)
                    .then(response => response.json())
                    .then(data => {
                        studentListDiv.innerHTML = ''; // Bersihkan konten sebelumnya
                        if (data.length > 0) {
                            data.forEach(siswa => {
                                const checkboxDiv = document.createElement('div');
                                checkboxDiv.classList.add('flex', 'items-center');
                                checkboxDiv.innerHTML = `
                                    <input type="checkbox" name="selected_siswa_ids[]" value="${siswa.id}" id="siswa-${siswa.id}" class="form-checkbox h-4 w-4 text-blue-600">
                                    <label for="siswa-${siswa.id}" class="ml-2 text-gray-700">${siswa.nama_lengkap} (NISN: ${siswa.nisn})</label>
                                `;
                                studentListDiv.appendChild(checkboxDiv);
                            });
                            selectAllStudentsCheckbox.checked = false; // Reset select all
                        } else {
                            studentListDiv.innerHTML = '<p class="text-gray-600">Tidak ada siswa aktif di kelas ini.</p>';
                            selectAllStudentsCheckbox.checked = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching students:', error);
                        studentListDiv.innerHTML = '<p class="text-red-600">Gagal memuat daftar siswa.</p>';
                        studentListContainer.classList.remove('hidden');
                    });
            } else {
                alert('Pilih kelas terlebih dahulu.');
                studentListContainer.classList.add('hidden');
            }
        });

        // Event listener untuk 'Pilih Semua Siswa'
        selectAllStudentsCheckbox.addEventListener('change', function() {
            const checkboxes = studentListDiv.querySelectorAll('input[type="checkbox"][name="selected_siswa_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllStudentsCheckbox.checked;
            });
        });

        // Validasi sebelum submit
        form.addEventListener('submit', function(event) {
            const selectedOption = jenisPembayaranSelect.options[jenisPembayaranSelect.selectedIndex];
            const tipePembayaran = selectedOption.dataset.tipePembayaran;
            const selectedStudents = studentListDiv.querySelectorAll('input[name="selected_siswa_ids[]"]:checked');
            
            if (selectedStudents.length === 0) {
                alert('Anda harus memilih setidaknya satu siswa.');
                event.preventDefault();
                return;
            }

            if (tipePembayaran === 'Rutin Bulanan') {
                const selectedMonthlyAmounts = {};
                let monthSelectedCount = 0;
                let monthAmountFilledCount = 0;

                monthlyAmountInputs.forEach(input => {
                    const monthName = input.name.match(/\[(.*?)\]/)[1]; // Extract month name
                    const checkbox = document.querySelector(`input[type="checkbox"][value="${monthName}"]`);

                    if (checkbox && checkbox.checked) {
                        monthSelectedCount++;
                        if (input.value && parseFloat(input.value) > 0) {
                            monthAmountFilledCount++;
                            selectedMonthlyAmounts[monthName] = parseFloat(input.value);
                        }
                    }
                });

                if (monthSelectedCount === 0) {
                    alert('Untuk jenis pembayaran rutin bulanan, Anda harus memilih setidaknya satu bulan.');
                    event.preventDefault();
                    return;
                }
                if (monthSelectedCount > monthAmountFilledCount) {
                    alert('Semua bulan yang dipilih harus memiliki jumlah tagihan.');
                    event.preventDefault();
                    return;
                }

                // Tambahkan input hidden untuk menyimpan data bulanan yang dipilih dengan jumlahnya
                // Ini akan menggantikan `selected_months[]` yang lama
                for (const month in selectedMonthlyAmounts) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = `monthly_amounts[${month}]`;
                    hiddenInput.value = selectedMonthlyAmounts[month];
                    form.appendChild(hiddenInput);
                }
            } else if (tipePembayaran === 'Bebas') {
                if (parseFloat(jumlahTagihanUtamaInput.value) <= 0) {
                    alert('Jumlah tagihan untuk pembayaran bebas harus lebih besar dari 0.');
                    event.preventDefault();
                    return;
                }
                // Pastikan periode_tagihan_bebas ada dan tidak kosong
                if (!periodeTagihanBebasInput.value.trim()) {
                    alert('Deskripsi Tagihan untuk pembayaran bebas harus diisi.');
                    event.preventDefault();
                    return;
                }
                 // Tambahkan input hidden untuk jumlah_tagihan_utama sebagai jumlah_tagihan untuk pembayaran bebas
                const hiddenInputJumlahTagihan = document.createElement('input');
                hiddenInputJumlahTagihan.type = 'hidden';
                hiddenInputJumlahTagihan.name = 'jumlah_tagihan'; // Sesuai dengan yang diharapkan oleh PHP
                hiddenInputJumlahTagihan.value = jumlahTagihanUtamaInput.value;
                form.appendChild(hiddenInputJumlahTagihan);
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
