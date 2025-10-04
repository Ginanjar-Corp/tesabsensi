<?php
// project-root/guru/rekap_gaji_guru.php

// Mulai output buffering di awal file
ob_start();

// Memastikan file konfigurasi dan helper yang diperlukan sudah di-include
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/GajiSettings.php';
require_once __DIR__ . '/../classes/Guru.php';
// PASTIKAN PATH KE FILE INI BENAR
require_once __DIR__ . '/../classes/AbsensiHarianGuru.php';

// --- PHP SPREADSHEET INTEGRATION START ---
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
// --- PHP SPREADSHEET INTEGRATION END ---

// Pastikan pengguna sudah login dan memiliki peran Guru
require_login();
require_role('Guru');

// Inisialisasi variabel
$pdo = null;
$error_message = flash('error');
$success_message = flash('success');
$guru_id_session = $_SESSION['user_data']['related_id'] ?? null;
$guru_data = null;
$rekap_data = [];
$detail_data_absensi_for_excel = [];
// Inisialisasi class
$absensi_harian_model = null;

// Daftar semester untuk dropdown (numeric ID ke nama)
$semesters = [
    1 => 'Ganjil',
    2 => 'Genap'
];

// Daftar bulan untuk dropdown
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $guru_model = new Guru($pdo);
    $gaji_settings_model = new GajiSettings($pdo);
    // Inisialisasi AbsensiHarianGuru
    $absensi_harian_model = new AbsensiHarianGuru($pdo);
    
    // Ambil data guru yang sedang login
    if ($guru_id_session) {
        $guru_data = $guru_model->findById($guru_id_session);
    } else {
        throw new Exception("ID guru tidak ditemukan dalam sesi.");
    }
    
    // Ambil tahun ajaran yang tersedia dari database
    $stmt = $pdo->query("SELECT id, tahun_ajaran FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
    $available_academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $selected_academic_year_id = $_POST['academic_year_id'] ?? ($available_academic_years[0]['id'] ?? null);
    $selected_academic_year_str = '';
    if ($selected_academic_year_id) {
        foreach ($available_academic_years as $year) {
            if ($year['id'] == $selected_academic_year_id) {
                $selected_academic_year_str = $year['tahun_ajaran'];
                break;
            }
        }
    }
    
    $selected_month = $_POST['month'] ?? date('n');
    $selected_semester_id = $_POST['semester'] ?? (APP_SEMESTER_AKTIF ?? 1);
    $selected_semester_name = $semesters[$selected_semester_id] ?? 'Ganjil';
    
    // Nominal per pertemuan dari pengaturan digunakan sebagai nominal umum
    $nominal_per_pertemuan_global = (float)($gaji_settings_model->getSettings()['nominal_per_pertemuan'] ?? 0);
    // Ambil gaji_per_pertemuan spesifik dari data guru
    $gaji_per_pertemuan_guru = (float)($guru_data['gaji_per_pertemuan'] ?? 0);

} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan koneksi database. Silakan coba lagi nanti.');
} catch (Exception $e) {
    set_flash('error', $e->getMessage());
}

// ... Fungsi getSingleGuruScheduledData (Tidak ada perubahan) ...
/**
 * Fungsi untuk mengambil data jadwal mengajar guru untuk semester dan tahun ajaran tertentu.
 * Disesuaikan untuk menggunakan gaji_per_pertemuan dari data guru.
 *
 * @param int $guru_id ID guru.
 * @param string $semester_name Nama semester.
 * @param string $academic_year_str Tahun ajaran.
 * @param float $gaji_per_pertemuan_guru Nominal gaji per pertemuan spesifik guru.
 * @return array Data jadwal guru.
 */
function getSingleGuruScheduledData($guru_id, $semester_name, $academic_year_str, $gaji_per_pertemuan_guru) {
    global $pdo;
    $sql = "
        SELECT
            jp.jumlah_jam,
            mp.nama_mapel
        FROM
            jadwal_pelajaran jp
        JOIN
            mata_pelajaran mp ON jp.mapel_id = mp.id
        WHERE
            jp.guru_id = :guru_id
            AND jp.tahun_ajaran = :tahun_ajaran_str
            AND jp.semester = :semester_str
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_str', $academic_year_str, PDO::PARAM_STR);
        $stmt->bindParam(':semester_str', $semester_name, PDO::PARAM_STR);
        $stmt->execute();
        $scheduled_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_jam_jadwal = 0;
        $unique_subjects_jadwal = [];
        foreach ($scheduled_classes as $class) {
            $total_jam_jadwal += (float)$class['jumlah_jam'];
            if (!in_array($class['nama_mapel'], $unique_subjects_jadwal)) {
                $unique_subjects_jadwal[] = $class['nama_mapel'];
            }
        }
        
        $total_gaji_pokok_jadwal = $total_jam_jadwal * $gaji_per_pertemuan_guru;

        return [
            'total_jam_jadwal' => $total_jam_jadwal,
            'total_gaji_pokok_jadwal' => $total_gaji_pokok_jadwal,
            'unique_subjects_jadwal' => $unique_subjects_jadwal
        ];
    } catch (PDOException $e) {
        error_log("ERROR: Error in getSingleGuruScheduledData: " . $e->getMessage());
        return ['total_jam_jadwal' => 0, 'total_gaji_pokok_jadwal' => 0, 'unique_subjects_jadwal' => []];
    }
}


// ... Fungsi getSingleGuruActualData (Tidak ada perubahan) ...
/**
 * Fungsi untuk mengambil data absensi mengajar guru untuk bulan dan tahun ajaran tertentu.
 * Disesuaikan agar perhitungan gaji pokok menggunakan tunjangan_transport dari data guru.
 *
 * @param int $guru_id ID guru.
 * @param int $month Bulan (1-12).
 * @param string $academic_year_str Tahun Ajaran (misal: "2023/2024").
 * @param float $tunjangan_transport_guru Nominal tunjangan transport spesifik guru.
 * @return array Data absensi mengajar guru.
 */
function getSingleGuruActualData($guru_id, $month, $academic_year_str, $tunjangan_transport_guru) {
    global $pdo;
    
    $rekap_absensi = [
        'jumlah_pertemuan_absensi' => 0,
        'total_gaji_absensi' => 0.0,
        'total_jam_absensi' => 0,
        'unique_subjects_absensi' => [],
    ];
    $detail_absensi = [];
    $sql = "
        SELECT
            amg.waktu_mulai_ajar,
            amg.waktu_selesai_ajar,
            amg.tanggal_ajar,
            mp.nama_mapel,
            jp.jumlah_jam,
            COALESCE(k.nama_kelas, 'Pribadi') AS nama_kelas
        FROM
            absensi_mapel_guru amg
        JOIN
            jadwal_pelajaran jp ON amg.jadwal_id = jp.id
        JOIN
            mata_pelajaran mp ON jp.mapel_id = mp.id
        LEFT JOIN
            kelas k ON jp.kelas_id = k.id
        WHERE
            amg.guru_id = :guru_id
            AND jp.tahun_ajaran = :academic_year_str
            AND MONTH(amg.tanggal_ajar) = :month
        ORDER BY
            amg.tanggal_ajar, amg.waktu_mulai_ajar
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':academic_year_str', $academic_year_str);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->execute();
        $guru_teaching_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($guru_teaching_data as $entry) {
            $rekap_absensi['jumlah_pertemuan_absensi']++;
            $rekap_absensi['total_jam_absensi'] += (float)$entry['jumlah_jam'];
            // Perubahan: Menggunakan tunjangan_transport dari data guru untuk perhitungan gaji pokok Bisyaroh per pertemuan
            $rekap_absensi['total_gaji_absensi'] += ((float)$entry['jumlah_jam']) * $tunjangan_transport_guru;
            if (!in_array($entry['nama_mapel'], $rekap_absensi['unique_subjects_absensi'])) {
                $rekap_absensi['unique_subjects_absensi'][] = $entry['nama_mapel'];
            }
            $detail_absensi[] = [
                'nama_mapel' => $entry['nama_mapel'],
                'nama_kelas' => $entry['nama_kelas'],
                'tanggal_ajar' => $entry['tanggal_ajar'],
                'jumlah_jam_jadwal' => (float)$entry['jumlah_jam'],
                'waktu_mulai_ajar' => $entry['waktu_mulai_ajar'],
                'waktu_selesai_ajar' => $entry['waktu_selesai_ajar'],
                'status_absen' => 'Hadir'
            ];
        }
        return ['rekap' => $rekap_absensi, 'detail' => $detail_absensi];
    } catch (PDOException $e) {
        error_log("ERROR: Error in getSingleGuruActualData: " . $e->getMessage());
        return ['rekap' => $rekap_absensi, 'detail' => []];
    }
}

// ... Fungsi getTunjanganKegiatanLainnya (Tidak ada perubahan) ...
/**
 * Mengambil tunjangan kegiatan lainnya dari tabel tunjangan_bulanan_guru.
 *
 * @param int $guru_id
 * @param int $month
 * @param int $academic_year_id
 * @return array Tunjangan kegiatan lainnya dan keterangannya.
 */
function getTunjanganKegiatanLainnya($guru_id, $month, $academic_year_id) {
    global $pdo;
    if (!$pdo) {
        // Mengembalikan array default jika koneksi tidak tersedia
        return ['tunjangan' => 0.0, 'keterangan' => '-'];
    }

    try {
        // Query untuk mengambil tunjangan dan keterangan kegiatan
        $sql = "SELECT tunjangan_kegiatan_lainnya, kegiatan_lainnya FROM tunjangan_bulanan_guru WHERE guru_id = :guru_id AND bulan = :month AND tahun_ajaran_id = :tahun_ajaran_id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $academic_year_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Mengembalikan tunjangan dan keterangan dalam bentuk array
        return $result ? [
            'tunjangan' => (float)$result['tunjangan_kegiatan_lainnya'],
            'keterangan' => $result['kegiatan_lainnya'] ?? '-'
        ] : ['tunjangan' => 0.0, 'keterangan' => '-'];
    } catch (PDOException $e) {
        error_log("ERROR: Error fetching tunjangan_kegiatan_lainnya: " . $e->getMessage());
        return ['tunjangan' => 0.0, 'keterangan' => '-'];
    }
}

/**
 * Menghitung Transport Jabatan dan Transport Piket berdasarkan absensi harian.
 *
 * @param AbsensiHarianGuru $absensi_harian_model Instance AbsensiHarianGuru.
 * @param int $guru_id
 * @param int $month
 * @param string $academic_year_str Tahun ajaran (misal: '2023/2024').
 * @param array $guru_data Data guru (harus mengandung: transport_jabatan, hari_piket, transport_piket).
 * @return array ['transport_jabatan' => float, 'transport_piket' => float, 'jumlah_kehadiran' => int, 'jumlah_hari_piket' => int]
 */
function calculateTransportTunjangan(AbsensiHarianGuru $absensi_harian_model, $guru_id, $month, $academic_year_str, $guru_data) {
    
    // --- PERBAIKAN 1: MENGGANTI str_contains() DENGAN strpos() UNTUK KOMPATIBILITAS PHP LAMA ---
    // Baris 297 berada di sekitar sini.
    if (empty($academic_year_str) || strpos($academic_year_str, '/') === false) {
        error_log("ERROR: Tahun ajaran tidak valid: " . $academic_year_str);
        return [
            'transport_jabatan' => 0.0,
            'transport_piket' => 0.0,
            'jumlah_kehadiran' => 0,
            'jumlah_hari_piket' => 0
        ];
    }

    $year_parts = explode('/', $academic_year_str); 
    $start_year = (int)($year_parts[0] ?? date('Y'));
    
    // Logika Penentuan Tahun
    if ($month >= 7 && $month <= 12) {
        $target_year = $start_year;
    } else {
        $target_year = $start_year + 1;
    }
    
    if ($target_year > (int)date('Y') && $month <= (int)date('n')) {
         $target_year = (int)date('Y');
    }

    $startDate = date("$target_year-$month-01");
    $endDate = date("$target_year-$month-t");
    // -----------------------------------------------------------------------------------------

    // 1. Hitung Transport Jabatan
    $transport_jabatan_nominal = (float)($guru_data['transport_jabatan'] ?? 0);
    $transport_piket_nominal = (float)($guru_data['transport_piket'] ?? 0);
    $hari_piket_str = trim($guru_data['hari_piket'] ?? '');
    
    $valid_dates = $absensi_harian_model->getValidAttendanceDates($guru_id, $startDate, $endDate);
    $jumlah_kehadiran = count($valid_dates);
    
    $total_transport_jabatan = $jumlah_kehadiran * $transport_jabatan_nominal;
    
    // 2. Hitung Transport Piket
    $hari_piket_array = array_filter(array_map('trim', explode(',', $hari_piket_str)));
    
    // Konversi nama hari piket ke format numeric (0=Minggu, 1=Senin, ..., 6=Sabtu)
    $day_name_to_num = ['Minggu' => 0, 'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];
    $piket_days_of_week = [];
    foreach ($hari_piket_array as $day_name) {
        $piket_days_of_week[] = $day_name_to_num[$day_name] ?? -1;
    }
    
    // PERBAIKAN 2: MENGGANTI arrow function DENGAN anonymous function
    $piket_days_of_week = array_filter($piket_days_of_week, function($d) {
        return $d !== -1;
    });
    
    $jumlah_hari_piket = 0;
    
    if (!empty($piket_days_of_week)) {
        foreach ($valid_dates as $date) {
            // Dapatkan hari dalam seminggu (0=Minggu, 1=Senin, ...)
            $day_of_week = (int)date('w', strtotime($date));
            if (in_array($day_of_week, $piket_days_of_week)) {
                $jumlah_hari_piket++;
            }
        }
    }
    
    $total_transport_piket = $jumlah_hari_piket * $transport_piket_nominal;
    
    return [
        'transport_jabatan' => $total_transport_jabatan,
        'transport_piket' => $total_transport_piket,
        'jumlah_kehadiran' => $jumlah_kehadiran,
        'jumlah_hari_piket' => $jumlah_hari_piket
    ];
}
// END NEW FUNCTIONS

// Proses form submission
if (isset($_POST['generate_report']) || isset($_POST['export_excel'])) {
    if (!$guru_id_session || !$selected_academic_year_str || !$selected_semester_name || !$selected_month || !$absensi_harian_model) {
        // Cek kondisi yang mungkin menyebabkan error
        $errorMessage = 'Terjadi kesalahan. Pastikan semua data filter dan sesi guru tersedia.';
        if (!$absensi_harian_model) {
             $errorMessage .= ' (Error: AbsensiHarianGuru model tidak terinisialisasi. Periksa path require_once.)';
        }
        set_flash('error', $errorMessage);
    } else {
        // Ambil data tunjangan tetap dari data guru
        $tunjangan_jabatan = (float)($guru_data['tunjangan_jabatan'] ?? 0);
        $tunjangan_transport = (float)($guru_data['tunjangan_transport'] ?? 0); // tunjangan_transport digunakan sebagai gaji per pertemuan di absensi

        // Nominal gaji per pertemuan spesifik guru, digunakan untuk jadwal
        $gaji_per_pertemuan_guru = (float)($guru_data['gaji_per_pertemuan'] ?? 0);

        // Ambil data tunjangan kegiatan lainnya
        $tunjangan_kegiatan = getTunjanganKegiatanLainnya($guru_id_session, $selected_month, $selected_academic_year_id);
        $tunjangan_kegiatan_lainnya = $tunjangan_kegiatan['tunjangan'];
        $keterangan_kegiatan_lainnya = $tunjangan_kegiatan['keterangan'];

        // Hitung Transport Jabatan dan Transport Piket
        $transport_tunjangan_data = calculateTransportTunjangan($absensi_harian_model, $guru_id_session, $selected_month, $selected_academic_year_str, $guru_data);
        $total_transport_jabatan = $transport_tunjangan_data['transport_jabatan'];
        $total_transport_piket = $transport_tunjangan_data['transport_piket'];
        $jumlah_kehadiran = $transport_tunjangan_data['jumlah_kehadiran'];
        $jumlah_hari_piket = $transport_tunjangan_data['jumlah_hari_piket'];
        
        // Ambil data berdasarkan jadwal
        $scheduled_data = getSingleGuruScheduledData($guru_id_session, $selected_semester_name, $selected_academic_year_str, $gaji_per_pertemuan_guru);
        
        // Ambil data berdasarkan absensi, kirim tunjangan transport (digunakan untuk Bisyaroh Pokok per Pertemuan)
        $actual_data = getSingleGuruActualData($guru_id_session, $selected_month, $selected_academic_year_str, $tunjangan_transport);
        
        // Hitung total gaji akhir berdasarkan absensi
        // Total Gaji Aktual = (Total Bisyaroh Pokok Aktual) + (Tunjangan Jabatan) + (Tunjangan Kegiatan Lainnya) + (Total Transport Jabatan) + (Total Transport Piket)
        $total_gaji_akhir = $total_gaji_pokok_jadwal +
                            $actual_data['rekap']['total_gaji_absensi'] + 
                            $tunjangan_jabatan + 
                            $tunjangan_kegiatan_lainnya +
                            $total_transport_jabatan + 
                            $total_transport_piket;
        
        // Gabungkan semua data
        $rekap_data = [
            'nama_guru' => $guru_data['nama_lengkap'],
            'jabatan' => $guru_data['jabatan'],
            'tunjangan_jabatan' => $tunjangan_jabatan,
            'kegiatan_lainnya' => $keterangan_kegiatan_lainnya, 
            'tunjangan_kegiatan_lainnya' => $tunjangan_kegiatan_lainnya,

            // NEW TRANSPORT DATA
            'transport_jabatan_per_hari' => (float)($guru_data['transport_jabatan'] ?? 0),
            'total_transport_jabatan' => $total_transport_jabatan,
            'jumlah_kehadiran' => $jumlah_kehadiran,
            'transport_piket_per_hari' => (float)($guru_data['transport_piket'] ?? 0),
            'total_transport_piket' => $total_transport_piket,
            'jumlah_hari_piket' => $jumlah_hari_piket,
            
            // Data Jadwal
            'total_jam_jadwal' => $scheduled_data['total_jam_jadwal'],
            'total_gaji_pokok_jadwal' => $scheduled_data['total_gaji_pokok_jadwal'],
            'unique_subjects_jadwal' => $scheduled_data['unique_subjects_jadwal'],
            
            // Data Absensi
            'total_jam_absensi' => $actual_data['rekap']['total_jam_absensi'],
            'total_gaji_pokok_absensi' => $actual_data['rekap']['total_gaji_absensi'],
            'unique_subjects_absensi' => $actual_data['rekap']['unique_subjects_absensi'],
            
            // Gaji Komprehensif
            'total_gaji_komprehensif_absensi' => $total_gaji_akhir,
            
            // Data nominal untuk tampilan
            'gaji_per_pertemuan' => $gaji_per_pertemuan_guru, // Untuk tampilan di tabel
            'tunjangan_transport' => $tunjangan_transport // Bisyaroh Pokok per Jam/Pertemuan Aktual
        ];
        
        $detail_data_absensi_for_excel = $actual_data['detail'];

        // Jika tombol export_excel ditekan, generate XLSX
        if (isset($_POST['export_excel'])) {
            try {
                // ... (Kode export Excel tetap sama seperti sebelumnya) ...
                $spreadsheet = new Spreadsheet();
                // --- Sheet 1: Ringkasan Gaji ---
                $sheet_summary = $spreadsheet->getActiveSheet();
                $sheet_summary->setTitle('Ringkasan Gaji');
                $sheet_summary->setCellValue('A1', 'Ringkasan Bisyaroh Guru');
                $sheet_summary->mergeCells('A1:C1');
                $sheet_summary->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet_summary->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Perbaikan: Menambahkan Jabatan dan Keterangan Kegiatan Lainnya
                $sheet_summary->setCellValue('A3', 'Nama Guru: ' . $rekap_data['nama_guru']);
                $sheet_summary->setCellValue('A4', 'Jabatan: ' . $rekap_data['jabatan']);
                $sheet_summary->setCellValue('A5', 'Keterangan Kegiatan Lainnya: ' . $rekap_data['keterangan_kegiatan_lainnya']);
                $sheet_summary->setCellValue('A6', 'Bulan: ' . $months[$selected_month] . ', Tahun Ajaran: ' . $selected_academic_year_str);

                // Perbaikan: Baris data disesuaikan karena ada tambahan baris
                $data_array = [
                    ['Keterangan', 'Berdasarkan Jadwal', 'Berdasarkan Absensi Aktual'],
                    ['Gaji per Pertemuan (Bisyaroh Pokok)', 'Rp ' . number_format($rekap_data['gaji_per_pertemuan'], 0, ',', '.'), 'Rp ' . number_format($rekap_data['tunjangan_transport'], 0, ',', '.')],
                    ['Jumlah Jam Mengajar', $rekap_data['total_jam_jadwal'], $rekap_data['total_jam_absensi']],
                    ['Total Bisyaroh Pokok', $rekap_data['total_gaji_pokok_jadwal'], $rekap_data['total_gaji_pokok_absensi']],
                    ['Tunjangan Jabatan (Tetap)', 'Rp ' . number_format($rekap_data['tunjangan_jabatan'], 0, ',', '.'), 'Rp ' . number_format($rekap_data['tunjangan_jabatan'], 0, ',', '.')],
                    ['Tunjangan Kegiatan Lainnya', 'Rp ' . number_format($rekap_data['tunjangan_kegiatan_lainnya'], 0, ',', '.'), 'Rp ' . number_format($rekap_data['tunjangan_kegiatan_lainnya'], 0, ',', '.')],
                    // NEW: Transport Jabatan
                    ['Transport Jabatan (' . number_format($rekap_data['transport_jabatan_per_hari'], 0, ',', '.') . ' x ' . $rekap_data['jumlah_kehadiran'] . ' Hari)', 'N/A', 'Rp ' . number_format($rekap_data['total_transport_jabatan'], 0, ',', '.')],
                    // NEW: Transport Piket
                    ['Transport Piket (' . number_format($rekap_data['transport_piket_per_hari'], 0, ',', '.') . ' x ' . $rekap_data['jumlah_hari_piket'] . ' Hari)', 'N/A', 'Rp ' . number_format($rekap_data['total_transport_piket'], 0, ',', '.')],
                    ['Total Bisyaroh Komprehensif', 'N/A', 'Rp ' . number_format($rekap_data['total_gaji_komprehensif_absensi'], 0, ',', '.')],
                ];
                
                $sheet_summary->fromArray($data_array, NULL, 'A8');
                // Sesuaikan range cell untuk format mata uang
                $sheet_summary->getStyle('B9:C11')->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet_summary->getStyle('B12:C15')->getNumberFormat()->setFormatCode('"Rp "#,##0'); // NEW: Sesuaikan dengan baris yang ditambahkan
                $sheet_summary->getStyle('C16')->getNumberFormat()->setFormatCode('"Rp "#,##0'); // NEW: Total baris akhir
                
                foreach (range('A', 'C') as $column) {
                    $sheet_summary->getColumnDimension($column)->setAutoSize(true);
                }

                // --- Sheet 2: Detail Absensi Mengajar Aktual ---
                $spreadsheet->createSheet();
                $detail_sheet_absensi = $spreadsheet->getActiveSheet();
                $detail_sheet_absensi->setTitle('Detail Absensi Aktual');
                $detail_sheet_absensi->setCellValue('A1', 'Detail Absensi Mengajar Aktual');
                $detail_sheet_absensi->mergeCells('A1:F1');
                $detail_sheet_absensi->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $detail_sheet_absensi->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $detail_sheet_absensi->setCellValue('A3', 'Nama Guru: ' . $rekap_data['nama_guru']);
                $detail_sheet_absensi->setCellValue('A4', 'Bulan: ' . $months[$selected_month] . ', Tahun Ajaran: ' . $selected_academic_year_str);

                $detail_header_data = ['No.', 'Tanggal', 'Mata Pelajaran', 'Kelas', 'Jumlah Jam Mengajar', 'Status Absensi'];
                $detail_sheet_absensi->fromArray($detail_header_data, NULL, 'A6');
                
                $row_num_detail = 7;
                $no_detail = 1;
                foreach ($detail_data_absensi_for_excel as $data) {
                    $detail_sheet_absensi->setCellValue('A' . $row_num_detail, $no_detail++);
                    $detail_sheet_absensi->setCellValue('B' . $row_num_detail, $data['tanggal_ajar']);
                    $detail_sheet_absensi->setCellValue('C' . $row_num_detail, $data['nama_mapel']);
                    $detail_sheet_absensi->setCellValue('D' . $row_num_detail, $data['nama_kelas']);
                    $detail_sheet_absensi->setCellValue('E' . $row_num_detail, $data['jumlah_jam_jadwal']);
                    $detail_sheet_absensi->setCellValue('F' . $row_num_detail, $data['status_absen']);
                    $row_num_detail++;
                }

                foreach (range('A', 'F') as $column) {
                    $detail_sheet_absensi->getColumnDimension($column)->setAutoSize(true);
                }

                $detail_sheet_absensi->getStyle('E7:E' . ($row_num_detail - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                
                // Export file
                $writer = new Xlsx($spreadsheet);
                $file_name = 'Rekap Gaji ' . $rekap_data['nama_guru'] . ' - ' . $months[$selected_month] . ' ' . $selected_academic_year_str . '.xlsx';
                
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $file_name . '"');
                header('Cache-Control: max-age=0');
                
                $writer->save('php://output');
                exit();
            } catch (Exception $e) {
                set_flash('error', 'Gagal membuat file Excel: ' . $e->getMessage());
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Gaji Guru - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .filter-form {
            background-color: #f0f4f8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .table-container {
            overflow-x: auto;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table-custom th, .table-custom td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        .table-custom th {
            background-color: #e2e8f0;
        }
        .table-summary {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        .table-summary th, .table-summary td {
            border: 1px solid #ccc;
            padding: 10px;
        }
        .table-summary th {
            background-color: #f0f4f8;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex min-h-screen">
        <?php include __DIR__ . '/../components/header_guru.php'; ?>
        <main class="flex-1 p-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-4">Rekap Gaji Guru</h1>
                <hr class="mb-6">

                <?php if ($error_message): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?= htmlspecialchars($success_message) ?></span>
                    </div>
                <?php endif; ?>

                <div class="filter-form">
                    <form method="post" class="flex flex-col md:flex-row items-center gap-4">
                        <div class="w-full md:w-1/3">
                            <label for="academic_year_id" class="block text-sm font-medium text-gray-700">Tahun Ajaran</label>
                            <select name="academic_year_id" id="academic_year_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <?php foreach ($available_academic_years as $year): ?>
                                    <option value="<?= htmlspecialchars($year['id']) ?>" <?= ($selected_academic_year_id == $year['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year['tahun_ajaran']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-full md:w-1/3">
                            <label for="month" class="block text-sm font-medium text-gray-700">Bulan</label>
                            <select name="month" id="month" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <?php foreach ($months as $num => $name): ?>
                                    <option value="<?= htmlspecialchars($num) ?>" <?= ($selected_month == $num) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-full md:w-1/3 flex-shrink-0 mt-6 md:mt-0">
                            <button type="submit" name="generate_report" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md shadow focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Tampilkan
                            </button>
                            <button type="submit" name="export_excel" class="w-full mt-2 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md shadow focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                Export Excel
                            </button>
                        </div>
                    </form>
                </div>

                <?php if (!empty($rekap_data)): ?>
                    <div class="table-container">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4">Ringkasan Gaji Bulan <?= htmlspecialchars($months[$selected_month]) ?></h2>
                        <table class="table-summary">
                            <thead>
                                <tr>
                                    <th class="w-1/2">Keterangan</th>
                                    <th class="w-1/2">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Nama Guru</td>
                                    <td><?= htmlspecialchars($rekap_data['nama_guru']) ?></td>
                                </tr>
                                <tr>
                                    <td>Jabatan</td>
                                    <td><?= htmlspecialchars($rekap_data['jabatan']) ?></td>
                                </tr>
                                <tr>
                                    <td>Keterangan Kegiatan Lainnya</td>
                                    <td><?= htmlspecialchars($rekap_data['kegiatan_lainnya']) ?></td>
                                </tr>
                                <tr>
                                    <td>Total Jam Mengajar Berdasarkan Jadwal</td>
                                    <td><?= number_format($rekap_data['total_jam_jadwal'], 0) ?> Jam (Nominal: Rp <?= number_format($rekap_data['gaji_per_pertemuan'], 0, ',', '.') ?>/pertemuan)</td>
                                </tr>
                                <tr>
                                    <td>Total Bisyaroh Pokok Berdasarkan Jadwal</td>
                                    <td>Rp <?= number_format($rekap_data['total_gaji_pokok_jadwal'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>Total Jam Mengajar Aktual</td>
                                    <td><?= number_format($rekap_data['total_jam_absensi'], 0) ?> Jam (Nominal: Rp <?= number_format($rekap_data['tunjangan_transport'], 0, ',', '.') ?>/pertemuan)</td>
                                </tr>
                                <tr>
                                    <td>Total Bisyaroh Pokok Aktual</td>
                                    <td>Rp <?= number_format($rekap_data['total_gaji_pokok_absensi'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>Tunjangan Jabatan (Tetap Bulanan)</td>
                                    <td>Rp <?= number_format($rekap_data['tunjangan_jabatan'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>Tunjangan Kegiatan Lainnya</td>
                                    <td>Rp <?= number_format($rekap_data['tunjangan_kegiatan_lainnya'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>Transport Jabatan (<?= number_format($rekap_data['transport_jabatan_per_hari'], 0, ',', '.') ?> x <?= $rekap_data['jumlah_kehadiran'] ?> Hari Kehadiran)</td>
                                    <td>Rp <?= number_format($rekap_data['total_transport_jabatan'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>Transport Guru Piket (<?= number_format($rekap_data['transport_piket_per_hari'], 0, ',', '.') ?> x <?= $rekap_data['jumlah_hari_piket'] ?> Hari Piket)</td>
                                    <td>Rp <?= number_format($rekap_data['total_transport_piket'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td class="font-bold text-lg">Total Gaji Komprehensif (Aktual)</td>
                                    <td class="font-bold text-lg text-green-600">Rp <?= number_format($rekap_data['total_gaji_komprehensif_absensi'], 0, ',', '.') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="p-4 text-gray-600 text-center">Tidak ada data rekap untuk bulan, semester, dan tahun ajaran yang dipilih. Pastikan data jadwal pelajaran dan absensi guru tersedia untuk periode ini.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburgerButton = document.getElementById('guru-hamburger-button');
        const guruSidebar = document.getElementById('guru-sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        function toggleSidebar() {
            guruSidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('active');
        }

        if (hamburgerButton && guruSidebar && sidebarOverlay) {
            hamburgerButton.addEventListener('click', toggleSidebar);
            sidebarOverlay.addEventListener('click', toggleSidebar);
        }
    });
</script>