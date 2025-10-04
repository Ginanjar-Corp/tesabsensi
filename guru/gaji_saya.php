<?php
// project-root/guru/gaji_saya.php

// Memastikan file konfigurasi dan helper yang diperlukan sudah di-include
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php'; // Asumsi untuk autentikasi

// --- Class Includes ---
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/AbsensiHarianGuru.php';
require_once __DIR__ . '/../classes/AbsensiMapelGuru.php';
require_once __DIR__ . '/../classes/TunjanganBulananGuru.php';

// Pastikan guru sudah login dan mendapatkan ID guru yang login
require_login();
require_role(['Guru']); // Hanya peran 'Guru' yang boleh mengakses

$user_id = $_SESSION['user_id'];

// Inisialisasi objek kelas
$guru = new Guru($pdo);
$tahunAjaranObj = new TahunAjaran($pdo);
$absensiHarianGuru = new AbsensiHarianGuru($pdo);
$tunjanganBulananGuru = new TunjanganBulananGuru($pdo);

// Dapatkan data guru yang sedang login
$teacher_data = $guru->getGuruByUserId($user_id); 

if (!$teacher_data) {
    set_flash('error', 'Data guru tidak ditemukan.');
    redirect_to('dashboard.php'); // Arahkan ke dashboard jika data guru tidak ada
}

$guru_id = $teacher_data['id'];

// Inisialisasi variabel filter dan data
$rekap_gaji_data = [];
$error_message = flash('error');
$success_message = flash('success');

// Daftar semester dan bulan
$semesters = [1 => 'Ganjil', 2 => 'Genap'];
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Mendapatkan data tahun ajaran untuk dropdown
$tahun_ajaran_list = $tahunAjaranObj->getAllTahunAjaran();

// Set nilai filter default
$default_bulan = date('n');
$default_semester = ($default_bulan >= 7 && $default_bulan <= 12) ? 1 : 2;
$default_tahun_ajaran_id = null;
$active_ta = array_filter($tahun_ajaran_list, function($ta) { return $ta['is_active'] == 1; });
if (!empty($active_ta)) {
    $default_tahun_ajaran_id = reset($active_ta)['id'];
} elseif (!empty($tahun_ajaran_list)) {
    $default_tahun_ajaran_id = $tahun_ajaran_list[0]['id'];
}

$selected_tahun_ajaran_id = $_GET['tahun_ajaran_id'] ?? $default_tahun_ajaran_id;
$selected_semester = $_GET['semester'] ?? $default_semester;
$selected_bulan = $_GET['bulan'] ?? $default_bulan;

// Mendapatkan string tahun ajaran berdasarkan ID yang dipilih
$selected_ta_string = '';
foreach ($tahun_ajaran_list as $ta) {
    if ($ta['id'] == $selected_tahun_ajaran_id) {
        $selected_ta_string = $ta['tahun_ajaran'];
        break;
    }
}
$selected_semester_string = $semesters[$selected_semester] ?? '';

// Tentukan rentang tanggal (Start & End Date of the Month)
$year_for_month = date('Y'); // Default ke tahun saat ini
if (!empty($selected_ta_string) && $selected_bulan) {
    $ta_parts = explode('/', $selected_ta_string);
    $year_start = (int)($ta_parts[0] ?? date('Y'));
    $year_end = (int)($ta_parts[1] ?? date('Y') + 1);
    
    // Bulan 1 (Jan) - 6 (Jun) = Tahun kedua TA (Semester Genap)
    if ($selected_bulan >= 1 && $selected_bulan <= 6) {
        $year_for_month = $year_end;
    } else {
        $year_for_month = $year_start;
    }

    $start_date = date('Y-m-d', strtotime("{$year_for_month}-{$selected_bulan}-01"));
    $end_date = date('Y-m-t', strtotime("{$year_for_month}-{$selected_bulan}-01"));
}

// --- DATA PROCESSING & CALCULATION (HANYA UNTUK GURU YANG LOGIN) ---
if ($selected_tahun_ajaran_id && $selected_semester && $selected_bulan && isset($start_date) && isset($end_date)) {
    
    $teacher = $teacher_data; // Menggunakan data guru yang login

    // --- CALCULATION 1: Gaji Berdasarkan Jadwal (Total Jam Jadwal * Gaji/Jam) ---
    // Jumlahkan nilai kolom jumlah_jam dari tabel jadwal_pelajaran
    $stmt_jadwal = $pdo->prepare("
        SELECT SUM(jp.jumlah_jam) AS total_jam_jadwal
        FROM jadwal_pelajaran jp
        WHERE jp.guru_id = :guru_id
        AND jp.semester = :semester_string
        AND jp.tahun_ajaran = :tahun_ajaran_string
    ");
    $stmt_jadwal->execute([
        ':guru_id' => $guru_id,
        ':semester_string' => $selected_semester_string,
        ':tahun_ajaran_string' => $selected_ta_string
    ]);
    $total_jam_jadwal = (int)$stmt_jadwal->fetchColumn();
    $gaji_per_pertemuan = (int)($teacher['gaji_per_pertemuan'] ?? 0);
    $gaji_berdasarkan_jadwal = $total_jam_jadwal * $gaji_per_pertemuan;

    // --- CALCULATION 2: Gaji Berdasarkan Absensi PTM (Total Jam PTM * Tunjangan Transport) ---
    // jumlahkan jumlah_jam jadwal berdasarkan absensi_mapel_guru (hanya yang sudah dikonfirmasi)
    $stmt_ptm = $pdo->prepare("
        SELECT 
            SUM(jp.jumlah_jam) AS total_jam_ptm,
            COUNT(amg.id) AS jumlah_pertemuan,
            GROUP_CONCAT(DISTINCT mp.nama_mapel SEPARATOR ', ') AS unique_subjects
        FROM absensi_mapel_guru amg
        JOIN jadwal_pelajaran jp ON amg.jadwal_id = jp.id
        LEFT JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
        WHERE amg.guru_id = :guru_id
        AND amg.tanggal_ajar BETWEEN :start_date AND :end_date
        AND amg.waktu_mulai_ajar IS NOT NULL -- Memastikan sesi sudah dimulai/dikonfirmasi
    ");
    $stmt_ptm->execute([
        ':guru_id' => $guru_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $ptm_data = $stmt_ptm->fetch(PDO::FETCH_ASSOC);
    $total_jam_ptm = (int)($ptm_data['total_jam_ptm'] ?? 0);
    $jumlah_ptm = (int)($ptm_data['jumlah_pertemuan'] ?? 0);
    $unique_subjects = $ptm_data['unique_subjects'] ?? '-';
    $tunjangan_transport_ptm = (int)($teacher['tunjangan_transport'] ?? 0);
    $gaji_berdasarkan_ptm = $total_jam_ptm * $tunjangan_transport_ptm;
    
    // --- CALCULATION 3: Gaji Jabatan (Fixed Tunjangan Jabatan) ---
    $gaji_jabatan = (int)($teacher['tunjangan_jabatan'] ?? 0);
    $jabatan_string = $teacher['jabatan'] ?? '-'; // Asumsi ada kolom jabatan di tabel guru

    // --- CALCULATION 4 & 5: Tunjangan Jabatan & Piket (Absensi Harian) ---
    $transport_jabatan = (int)($teacher['transport_jabatan'] ?? 0);
    $transport_piket = (int)($teacher['transport_piket'] ?? 0);
    $hari_piket_guru_indo = strtolower(trim($teacher['hari_piket'] ?? '')); // Misalnya 'senin', 'selasa'

    // Mendapatkan semua tanggal Hadir guru dalam periode
    // PERHATIAN: Asumsi Anda telah menambahkan/memodifikasi method ini
    // getValidAttendanceDatesExcludingOffDays harusnya memfilter status_masuk='Hadir'
    $absensi_harian = $absensiHarianGuru->getValidAttendanceDates($guru_id, $start_date, $end_date); 
    
    $jumlah_kehadiran_transport_jabatan = 0; // Kehadiran untuk Transport Jabatan (Sabtu-Kamis)
    $jumlah_kehadiran_piket = 0;             // Kehadiran untuk Piket (Sesuai hari piket guru)
    $hari_libur_sekolah = ['jumat']; // Hari libur/non-sekolah yang dikecualikan

    foreach ($absensi_harian as $absensi_date) {
        $day_of_week_num = date('N', strtotime($absensi_date)); // 1 (Mon) to 7 (Sun)
        $day_name_indo = strtolower(getDayName($day_of_week_num)); // Asumsi getDayName di helpers.php

        // LOGIKA 1: PERHITUNGAN KEHADIRAN UNTUK TRANSPORT JABATAN
        if (!in_array($day_name_indo, $hari_libur_sekolah)) {
            $jumlah_kehadiran_transport_jabatan++;
        }
        
        // LOGIKA 2: PERHITUNGAN KEHADIRAN UNTUK PIKET
        if ($day_name_indo == $hari_piket_guru_indo && $hari_piket_guru_indo !== '') {
            $jumlah_kehadiran_piket++;
        }
    }
    
    $jumlah_kehadiran_total = count($absensi_harian);

    // 4. Gaji Tunjangan Jabatan
    $gaji_tunjangan_jabatan = $jumlah_kehadiran_transport_jabatan * $transport_jabatan;
    
    // 5. Gaji Piket
    $gaji_piket = $jumlah_kehadiran_piket * $transport_piket;
    
    // --- CALCULATION 6: Tunjangan Bulanan Lainnya ---
    $tunjangan_bulanan_data = $tunjanganBulananGuru->findByGuruAndPeriod($guru_id, $selected_tahun_ajaran_id, $selected_bulan);
    $nama_kegiatan_lainnya = $tunjangan_bulanan_data['kegiatan_lainnya'] ?? '-';
    $tunjangan_kegiatan_lainnya = (int)($tunjangan_bulanan_data['tunjangan_kegiatan_lainnya'] ?? 0);
    $gaji_kegiatan_lainnya = $tunjangan_kegiatan_lainnya;
    
    // --- TOTAL GAJI ---
    $total_gaji = $gaji_berdasarkan_jadwal + $gaji_berdasarkan_ptm + $gaji_jabatan + $gaji_tunjangan_jabatan + $gaji_piket + $gaji_kegiatan_lainnya;

    // Simpan data rekap (hanya satu baris)
    $rekap_gaji_data[] = [
        'guru_id' => $guru_id,
        'nama_guru' => htmlspecialchars($teacher['nama_lengkap']),
        'gaji_per_pertemuan_raw' => $gaji_per_pertemuan,
        'tunjangan_transport_ptm_raw' => $tunjangan_transport_ptm,
        'transport_jabatan_raw' => $transport_jabatan,
        'transport_piket_raw' => $transport_piket,
        'total_jam_jadwal' => $total_jam_jadwal,
        'gaji_jadwal' => $gaji_berdasarkan_jadwal,
        'jumlah_ptm' => $jumlah_ptm,
        'mapel_diampu_ptm' => $unique_subjects,
        'gaji_ptm' => $gaji_berdasarkan_ptm,
        'nama_jabatan' => $jabatan_string,
        'gaji_jabatan' => $gaji_jabatan,
        'kehadiran_harian' => $jumlah_kehadiran_transport_jabatan,
        'gaji_tunjangan_jabatan' => $gaji_tunjangan_jabatan,
        'hari_piket' => $hari_piket_guru_indo ? ucwords($hari_piket_guru_indo) : '-',
        'kehadiran_piket' => $jumlah_kehadiran_piket,
        'gaji_piket' => $gaji_piket,
        'nama_kegiatan_lainnya' => htmlspecialchars($nama_kegiatan_lainnya),
        'tunjangan_kegiatan_lainnya' => $tunjangan_kegiatan_lainnya,
        'total_gaji' => $total_gaji,
    ];
}


// Menyertakan header (yang sekarang berisi sidebar dan pembuka div konten utama)
// Asumsi ada file header_guru.php
require_once __DIR__ . '/../components/header_guru.php'; 
?>

<style>
/* ... (Gaya CSS dari rekap_gaji_guru.php, bisa dipindahkan ke file CSS eksternal) ... */
.form-container {
    background-color: #ffffff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.table-container {
    background-color: #ffffff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow-x: auto; 
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}
th {
    background-color: #edf2f7; 
    font-weight: 600;
    color: #2d3748; 
    text-transform: uppercase;
    font-size: 0.875rem;
}
tbody tr:last-child td {
    border-bottom: none;
}
tbody tr:hover {
    background-color: #f7fafc; 
}
/* Tambahkan gaya khusus untuk tampilan guru */
.text-success { color: #10b981; } /* Tailwind green-500 */
.text-danger { color: #ef4444; } /* Tailwind red-500 */
.font-weight-bold { font-weight: 700; }
</style>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
    <h1 class="text-3xl font-semibold text-gray-800 mb-4 sm:mb-0">Catatan Gaji Saya (<?= htmlspecialchars($teacher_data['nama_lengkap'] ?? '-') ?>)</h1>
</div>

<div class="container-fluid">

    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <?= $error_message ?>
        </div>
    <?php endif; ?>

    <div class="card-shadow mb-4 p-4 rounded-lg bg-white shadow-md">
        <div class="card-header py-3 border-b border-gray-200">
            <h6 class="m-0 font-bold text-lg text-blue-600">Pilih Periode Gaji</h6>
        </div>
        <div class="card-body pt-4">
            <form method="GET" action="">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    
                    <div>
                        <label for="tahun_ajaran_id" class="block text-sm font-medium text-gray-700 mb-1">Tahun Ajaran:</label>
                        <select name="tahun_ajaran_id" id="tahun_ajaran_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                            <?php foreach ($tahun_ajaran_list as $ta): ?>
                                <option value="<?= $ta['id'] ?>" <?= ($selected_tahun_ajaran_id == $ta['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ta['tahun_ajaran']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="semester" class="block text-sm font-medium text-gray-700 mb-1">Semester:</label>
                        <select name="semester" id="semester" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                            <?php foreach ($semesters as $id => $name): ?>
                                <option value="<?= $id ?>" <?= ($selected_semester == $id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="bulan" class="block text-sm font-medium text-gray-700 mb-1">Bulan:</label>
                        <select name="bulan" id="bulan" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                            <?php foreach ($months as $id => $name): ?>
                                <option value="<?= $id ?>" <?= ($selected_bulan == $id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300">
                            Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Detail Gaji Periode: <?= htmlspecialchars($months[$selected_bulan] . ' ' . $year_for_month . ' - ' . $selected_semester_string . ' ' . $selected_ta_string) ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th rowspan="2" class="text-center align-middle">Komponen Gaji</th>
                            <th colspan="3" class="text-center">Perhitungan</th>
                            <th rowspan="2" class="text-center align-middle">Total Gaji (Rp)</th>
                        </tr>
                        <tr>
                            <th class="text-center">Keterangan</th>
                            <th class="text-center">Satuan/Tarif</th>
                            <th class="text-center">Dasar Hitung</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rekap_gaji_data)): ?>
                            <tr>
                                <td colspan="5" class="text-center">Tidak ada data gaji untuk periode ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php $data = $rekap_gaji_data[0]; ?>
                            
                            <tr>
                                <td>Gaji Berdasarkan Jadwal</td>
                                <td class="text-center">Fixed Jadwal</td>
                                <td class="text-right"><?= number_format($data['gaji_per_pertemuan_raw'], 0, ',', '.') ?></td>
                                <td class="text-center"><?= number_format($data['total_jam_jadwal'], 0) ?> Jam</td>
                                <td class="text-right font-weight-bold"><?= number_format($data['gaji_jadwal'], 0, ',', '.') ?></td>
                            </tr>

                            <tr>
                                <td>Tunjangan Transport PTM</td>
                                <td><?= htmlspecialchars($data['mapel_diampu_ptm']) ?></td>
                                <td class="text-right"><?= number_format($data['tunjangan_transport_ptm_raw'], 0, ',', '.') ?></td>
                                <td class="text-center"><?= number_format($data['jumlah_ptm'], 0) ?> Pertemuan</td>
                                <td class="text-right font-weight-bold"><?= number_format($data['gaji_ptm'], 0, ',', '.') ?></td>
                            </tr>

                            <tr>
                                <td>Gaji Jabatan</td>
                                <td><?= htmlspecialchars($data['nama_jabatan']) ?></td>
                                <td class="text-right">-</td>
                                <td class="text-center">Fixed</td>
                                <td class="text-right font-weight-bold"><?= number_format($data['gaji_jabatan'], 0, ',', '.') ?></td>
                            </tr>

                            <tr>
                                <td>Transport Tunjangan Jabatan</td>
                                <td>Transport Harian</td>
                                <td class="text-right"><?= number_format($data['transport_jabatan_raw'], 0, ',', '.') ?></td>
                                <td class="text-center"><?= number_format($data['kehadiran_harian'], 0) ?> Hari Hadir*</td>
                                <td class="text-right font-weight-bold"><?= number_format($data['gaji_tunjangan_jabatan'], 0, ',', '.') ?></td>
                            </tr>
                            
                            <tr>
                                <td>Gaji Piket</td>
                                <td>Hari Piket: <?= $data['hari_piket'] ?></td>
                                <td class="text-right"><?= number_format($data['transport_piket_raw'], 0, ',', '.') ?></td>
                                <td class="text-center"><?= number_format($data['kehadiran_piket'], 0) ?> Hari Piket</td>
                                <td class="text-right font-weight-bold"><?= number_format($data['gaji_piket'], 0, ',', '.') ?></td>
                            </tr>

                            <tr>
                                <td>Tunjangan Kegiatan Lainnya</td>
                                <td><?= $data['nama_kegiatan_lainnya'] ?></td>
                                <td class="text-right">-</td>
                                <td class="text-center">Bulanan</td>
                                <td class="text-right font-weight-bold"><?= number_format($data['tunjangan_kegiatan_lainnya'], 0, ',', '.') ?></td>
                            </tr>

                            <tr class="bg-gray-100">
                                <td colspan="4" class="text-right font-weight-bold">TOTAL GAJI KOTOR</td>
                                <td class="text-right text-success font-weight-bold border-top-2 border-primary"><?= 'Rp ' . number_format($data['total_gaji'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="text-sm text-gray-500">*Kehadiran dihitung dari hari kerja (Sabtu-Kamis).</td>
                            </tr>

                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
// Menyertakan footer
require_once __DIR__ . '/../components/footer.php';
?>