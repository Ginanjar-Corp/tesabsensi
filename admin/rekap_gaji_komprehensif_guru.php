<?php
// project-root/admin/rekap_gaji_komprehensif_guru.php

// Mulai output buffering di awal file
ob_start();

// Aktifkan pelaporan error untuk debugging (Hapus di produksi!)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Memastikan file konfigurasi dan helper yang diperlukan sudah di-include
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/GajiSettings.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/AbsensiHarianGuru.php';

// --- PHP SPREADSHEET INTEGRATION START ---
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
// --- PHP SPREADSHEET INTEGRATION END ---

// Memastikan pengguna sudah login dan memiliki peran yang sesuai
require_login();
require_role(['Admin', 'TataUsaha']);

// Asumsi jumlah minggu dalam satu bulan untuk perhitungan gaji berdasarkan jadwal
const FIXED_WEEKS_PER_MONTH = 1;

// Inisialisasi variabel
$pdo = null;
$rekap_data_jadwal = []; // Data rekap berdasarkan jadwal (4 minggu)
$rekap_data_absensi = []; // Data rekap berdasarkan absensi aktual
$detail_data_absensi_for_excel = []; // Detail absensi aktual untuk sheet Excel
$error_message = flash('error');
$success_message = flash('success');

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

// Inisialisasi koneksi database di awal
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan koneksi database. Silakan coba lagi nanti.');
}

// Ambil tahun ajaran yang tersedia dari database
$available_academic_years = [];
$selected_academic_year_id = null;
$selected_academic_year_str = '';

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, tahun_ajaran FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
        $available_academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($available_academic_years)) {
            set_flash('error', 'Tidak ada data tahun ajaran ditemukan. Harap tambahkan data tahun ajaran terlebih dahulu.');
        } else {
            $default_academic_year_id = APP_TAHUN_AJARAN_AKTIF ?? ($available_academic_years[0]['id'] ?? null);
            $selected_academic_year_id = $_POST['academic_year_id'] ?? $default_academic_year_id;

            if (!is_numeric($selected_academic_year_id)) {
                $selected_academic_year_id = null;
            }

            foreach ($available_academic_years as $year) {
                if ($year['id'] == $selected_academic_year_id) {
                    $selected_academic_year_str = $year['tahun_ajaran'];
                    break;
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database error fetching academic years: " . $e->getMessage());
        set_flash('error', 'Terjadi kesalahan database saat mengambil tahun ajaran.');
    }
} else {
    set_flash('error', 'Koneksi database tidak tersedia untuk mengambil tahun ajaran.');
}

$guru_model = $pdo ? new Guru($pdo) : null;
$absensi_harian_model = $pdo ? new AbsensiHarianGuru($pdo) : null;

// Inisialisasi bulan dan semester yang dipilih
$selected_month = $_POST['month'] ?? date('n');
$selected_semester_id = $_POST['semester'] ?? (APP_SEMESTER_AKTIF ?? 1);
$selected_semester_name = $semesters[$selected_semester_id] ?? 'Ganjil';

// Ambil nominal gaji per pertemuan dari pengaturan, meskipun tidak digunakan dalam perhitungan absensi aktual
$nominal_gaji_per_pertemuan_settings = 0;
if ($pdo) {
    try {
        $gaji_settings_model = new GajiSettings($pdo);
        $gaji_settings = $gaji_settings_model->getSettings();
        if ($gaji_settings && isset($gaji_settings['nominal_per_pertemuan'])) {
            $nominal_gaji_per_pertemuan_settings = (float)$gaji_settings['nominal_per_pertemuan'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching Gaji Settings: " . $e->getMessage());
    }
}

/**
 * Helper function to determine the calendar year for a given month within an academic year string.
 * @param int $month_num Month number (1-12)
 * @param string $academic_year_str Academic year string (e.g., "2023/2024")
 * @return int The calendar year for that month.
 */
function getCalendarYearForMonthInAcademicYear($month_num, $academic_year_str) {
    $academic_year_parts = explode('/', $academic_year_str);
    $start_year_acad = (int)($academic_year_parts[0] ?? date('Y'));
    $end_year_acad = (int)($academic_year_parts[1] ?? ($start_year_acad + 1));

    // Assuming academic year starts in July (month 7) of the first year
    // and ends in June (month 6) of the second year.
    if ($month_num >= 1 && $month_num <= 6) { // January to June
        return $end_year_acad;
    } else { // July to December
        return $start_year_acad;
    }
}


function getGuruScheduledTeachingData($semester_name, $academic_year_str, $selected_month_num) {
    global $pdo;
    $rekap_bulanan = [];
    if (!$pdo) {
        error_log("PDO connection not available in getGuruScheduledTeachingData.");
        return [];
    }
    $sql = "
        SELECT
            jp.id AS jadwal_id,
            jp.guru_id,
            g.nama_lengkap,
            g.gaji_per_pertemuan,
            mp.nama_mapel,
            jp.kelas_id,
            jp.jumlah_jam, -- Tambahkan kolom jumlah_jam
            COALESCE(k.nama_kelas, 'Pribadi') AS nama_kelas,
            jp.hari,
            jp.jam_mulai,
            jp.jam_selesai
        FROM
            jadwal_pelajaran jp
        JOIN
            guru g ON jp.guru_id = g.id
        JOIN
            mata_pelajaran mp ON jp.mapel_id = mp.id
        LEFT JOIN
            kelas k ON jp.kelas_id = k.id
        WHERE
            jp.tahun_ajaran = :tahun_ajaran_str_filter AND jp.semester = :semester_str_filter
        ORDER BY
            g.nama_lengkap, jp.hari, jp.jam_mulai
    ";
     try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':tahun_ajaran_str_filter', $academic_year_str, PDO::PARAM_STR);
        $stmt->bindParam(':semester_str_filter', $semester_name, PDO::PARAM_STR);
        $stmt->execute();
        $scheduled_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $teacher_scheduled_summary = [];
        foreach ($scheduled_classes as $class) {
            $guru_id = $class['guru_id'];
            if (!isset($teacher_scheduled_summary[$guru_id])) {
                $teacher_scheduled_summary[$guru_id] = [
                    'nama_guru' => $class['nama_lengkap'],
                    'gaji_per_pertemuan' => (float)$class['gaji_per_pertemuan'],
                    'total_pertemuan' => 0,
                    'total_jam_terjadwal' => 0,
                    'unique_subjects' => [],
                ];
            }
            $teacher_scheduled_summary[$guru_id]['total_pertemuan']++;
            $teacher_scheduled_summary[$guru_id]['total_jam_terjadwal'] += (float)$class['jumlah_jam'];
            if (!in_array($class['nama_mapel'], $teacher_scheduled_summary[$guru_id]['unique_subjects'])) {
                $teacher_scheduled_summary[$guru_id]['unique_subjects'][] = $class['nama_mapel'];
            }
        }
        foreach ($teacher_scheduled_summary as $guru_id => $summary) {
            $total_gaji_rekap = $summary['total_jam_terjadwal'] * $summary['gaji_per_pertemuan'];
            sort($summary['unique_subjects']);
            $rekap_bulanan[$guru_id] = [
                'nama_guru' => $summary['nama_guru'],
                // Ubah ini dari 'jumlah_pertemuan_jadwal' ke 'total_jam_jadwal'
                'total_jam_jadwal' => $summary['total_jam_terjadwal'],
                'total_gaji_jadwal' => $total_gaji_rekap,
                'unique_subjects_jadwal' => $summary['unique_subjects'],
            ];
        }
    } catch (PDOException $e) {
        error_log("ERROR: Error in getGuruScheduledTeachingData: " . $e->getMessage());
        return [];
    }
    return $rekap_bulanan;
}


/**
 * Fungsi untuk mengambil data absensi mengajar guru untuk bulan dan tahun ajaran tertentu.
 * Perhitungan gaji didasarkan pada jumlah jam mengajar aktual dikalikan dengan tunjangan transport guru.
 *
 * @param int $month Bulan (1-12)
 * @param string $academic_year_str Tahun Ajaran (misal: "2023/2024")
 * @return array Array data absensi mengajar guru (rekap dan detail)
 */
function getGuruActualTeachingData($month, $academic_year_str) {
    global $pdo;
    $rekap_absensi = [];
    $detail_absensi = [];
    if (!$pdo) {
        error_log("PDO connection not available in getGuruActualTeachingData.");
        return ['rekap' => [], 'detail' => []];
    }
    $sql = "
        SELECT
            g.id AS id_guru,
            g.nama_lengkap,
            g.tunjangan_jabatan,
            g.tunjangan_transport,
            amg.waktu_mulai_ajar,
            amg.waktu_selesai_ajar,
            amg.tanggal_ajar,
            mp.nama_mapel,
            jp.jumlah_jam,
            COALESCE(k.nama_kelas, 'Pribadi') AS nama_kelas
        FROM
            guru g
        JOIN
            absensi_mapel_guru amg ON g.id = amg.guru_id
        JOIN
            jadwal_pelajaran jp ON amg.jadwal_id = jp.id
        JOIN
            mata_pelajaran mp ON jp.mapel_id = mp.id
        LEFT JOIN
            kelas k ON jp.kelas_id = k.id
        WHERE
            g.is_active = 1
            AND jp.tahun_ajaran = :academic_year_str
            AND MONTH(amg.tanggal_ajar) = :month
        ORDER BY
            g.nama_lengkap, amg.tanggal_ajar, amg.waktu_mulai_ajar
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':academic_year_str', $academic_year_str);
        $stmt->bindParam(':month', $month);
        $stmt->execute();
        $guru_teaching_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pre-fetch all fixed allowances for active teachers
        $teacher_fixed_allowances = [];
        $stmt_fixed_allowances = $pdo->query("SELECT id, nama_lengkap, tunjangan_jabatan, tunjangan_transport, transport_jabatan, hari_piket, transport_piket FROM guru WHERE is_active = 1");
        while ($row = $stmt_fixed_allowances->fetch(PDO::FETCH_ASSOC)) {
            $teacher_fixed_allowances[$row['id']] = $row;
        }

        foreach ($guru_teaching_data as $entry) {
            $guru_id = $entry['id_guru'];
            if (!isset($rekap_absensi[$guru_id])) {
                $rekap_absensi[$guru_id] = [
                    'nama_guru' => $entry['nama_lengkap'],
                    'jumlah_pertemuan_absensi' => 0,
                    'total_gaji_absensi' => 0.0,
                    'total_jam_absensi' => 0,
                    'unique_subjects_absensi' => [],
                    'tunjangan_jabatan' => (float)($teacher_fixed_allowances[$guru_id]['tunjangan_jabatan'] ?? 0),
                    'tunjangan_transport' => (float)($teacher_fixed_allowances[$guru_id]['tunjangan_transport'] ?? 0),
                    'transport_jabatan_rate' => (float)($teacher_fixed_allowances[$guru_id]['transport_jabatan'] ?? 0),
                    'hari_piket_str' => $teacher_fixed_allowances[$guru_id]['hari_piket'] ?? '',
                    'transport_piket_rate' => (float)($teacher_fixed_allowances[$guru_id]['transport_piket'] ?? 0),
                    'tunjangan_kegiatan_lainnya' => 0.0, // Akan diisi nanti
                ];
            }
            $rekap_absensi[$guru_id]['jumlah_pertemuan_absensi']++;
            $rekap_absensi[$guru_id]['total_jam_absensi'] += (float)$entry['jumlah_jam'];
            // Perhitungan gaji per pertemuan menggunakan tunjangan_transport
            $rekap_absensi[$guru_id]['total_gaji_absensi'] += ((float)$entry['jumlah_jam']) * $rekap_absensi[$guru_id]['tunjangan_transport'];
            
            if (!in_array($entry['nama_mapel'], $rekap_absensi[$guru_id]['unique_subjects_absensi'])) {
                $rekap_absensi[$guru_id]['unique_subjects_absensi'][] = $entry['nama_mapel'];
            }
            $detail_absensi[] = [
                'nama_guru' => $entry['nama_lengkap'],
                'nama_mapel' => $entry['nama_mapel'],
                'nama_kelas' => $entry['nama_kelas'],
                'tanggal_ajar' => $entry['tanggal_ajar'],
                'jumlah_jam_jadwal' => (float)$entry['jumlah_jam'],
                'status_absen' => 'Hadir'
            ];
        }
        usort($detail_absensi, function($a, $b) {
            $cmp_guru = strcmp($a['nama_guru'], $b['nama_guru']);
            if ($cmp_guru !== 0) {
                return $cmp_guru;
            }
            return strtotime($a['tanggal_ajar']) - strtotime($b['tanggal_ajar']);
        });
    } catch (PDOException $e) {
        error_log("ERROR: Error in getGuruActualTeachingData: " . $e->getMessage());
        return ['rekap' => [], 'detail' => []];
    }
    return ['rekap' => $rekap_absensi, 'detail' => $detail_absensi];
}

/**
 * Mengambil tunjangan kegiatan lainnya dari tabel tunjangan_bulanan_guru.
 *
 * @param int $guru_id
 * @param int $month
 * @param int $academic_year_id
 * @return float Tunjangan kegiatan lainnya, 0 jika tidak ada.
 */
function getTunjanganKegiatanLainnya($guru_id, $month, $academic_year_id) {
    global $pdo;
    if (!$pdo) {
        return 0.0;
    }

    try {
        $sql = "SELECT tunjangan_kegiatan_lainnya FROM tunjangan_bulanan_guru WHERE guru_id = :guru_id AND bulan = :month AND tahun_ajaran_id = :tahun_ajaran_id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $academic_year_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (float)$result['tunjangan_kegiatan_lainnya'] : 0.0;
    } catch (PDOException $e) {
        error_log("ERROR: Error fetching tunjangan_kegiatan_lainnya: " . $e->getMessage());
        return 0.0;
    }
}


// Proses form submission untuk generate report atau export
if (isset($_POST['generate_report']) || isset($_POST['export_excel'])) {
    // Validasi input filter
    if (empty($available_academic_years)) {
        set_flash('error', 'Tidak ada tahun ajaran yang tersedia. Harap tambahkan tahun ajaran terlebih dahulu.');
    } elseif (!$selected_semester_id || !$selected_academic_year_id || !$selected_month) {
        set_flash('error', 'Pilihan semester, tahun ajaran, atau bulan tidak valid. Pastikan semua opsi telah dipilih.');
    } else {
        // Ambil data berdasarkan jadwal (untuk Sheet 1 Excel)
        $rekap_data_jadwal = getGuruScheduledTeachingData($selected_semester_name, $selected_academic_year_str, $selected_month);
    
        // Ambil data berdasarkan absensi aktual (untuk Sheet 2 & 3 Excel)
        $actual_data_result = getGuruActualTeachingData($selected_month, $selected_academic_year_str);
        $rekap_data_absensi = $actual_data_result['rekap'];
        $detail_data_absensi_for_excel = $actual_data_result['detail'];

        // Tambahkan tunjangan kegiatan lainnya ke rekap absensi dan hitung total gaji akhir
        foreach ($rekap_data_absensi as $guru_id => $data) {
            $tunjangan_kegiatan = getTunjanganKegiatanLainnya($guru_id, $selected_month, $selected_academic_year_id);
            $rekap_data_absensi[$guru_id]['tunjangan_kegiatan_lainnya'] = $tunjangan_kegiatan;
            $rekap_data_absensi[$guru_id]['total_gaji_absensi_final'] = $data['total_gaji_absensi'] + $data['tunjangan_jabatan'] + $tunjangan_kegiatan;
        }
        
        // Ambil tanggal mulai dan akhir bulan yang dipilih (untuk absensi harian)
        $calendar_year = getCalendarYearForMonthInAcademicYear($selected_month, $selected_academic_year_str);
        $start_date_str = $calendar_year . '-' . sprintf('%02d', $selected_month) . '-01';
        $end_date_str = date('Y-m-t', strtotime($start_date_str)); 
        
        if ($absensi_harian_model) {
        foreach ($rekap_data_absensi as $guru_id => $data) {
            // 1. Ambil tunjangan kegiatan lainnya (Langkah ini dipindahkan ke sini)
            $tunjangan_kegiatan = getTunjanganKegiatanLainnya($guru_id, $selected_month, $selected_academic_year_id);
            $rekap_data_absensi[$guru_id]['tunjangan_kegiatan_lainnya'] = $tunjangan_kegiatan;
            
            // 2. Ambil data absensi harian yang valid (Hadir)
            // Menggunakan fungsi yang diminta dari AbsensiHarianGuru.php
            $valid_hadir_dates = $absensi_harian_model->getValidAttendanceDates($guru_id, $start_date_str, $end_date_str);
            $jumlah_hadir_harian = count($valid_hadir_dates);
            
            // 3. Hitung Transport Jabatan
            // Rumus: Jumlah Kehadiran Harian * transport_jabatan (rate)
            $transport_jabatan_total = $jumlah_hadir_harian * $data['transport_jabatan_rate'];
            
            // 4. Hitung Transport Piket
            // Rumus: Jumlah Kehadiran Sesuai Hari Piket * transport_piket (rate)
            $jumlah_piket_hadir = 0;
            $hari_piket_arr = [];
            if (!empty($data['hari_piket_str'])) {
                $hari_piket_arr = array_map('trim', explode(',', $data['hari_piket_str']));
            }
    
            foreach ($valid_hadir_dates as $date_str) {
                $day_of_week_en = date('N', strtotime($date_str)); // 1=Mon, 7=Sun
                // Konversi ke nama hari dalam Bahasa Indonesia (sesuai format umum dan asumsi DB)
                $day_map = [
                    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 
                    5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
                ];
                $day_of_week_id = $day_map[$day_of_week_en] ?? '';
    
                if (in_array($day_of_week_id, $hari_piket_arr)) {
                    $jumlah_piket_hadir++;
                }
            }
            $transport_piket_total = $jumlah_piket_hadir * $data['transport_piket_rate'];
    
            // 5. Simpan hasil perhitungan
            $rekap_data_absensi[$guru_id]['jumlah_hadir_harian'] = $jumlah_hadir_harian;
            $rekap_data_absensi[$guru_id]['transport_jabatan_total'] = $transport_jabatan_total;
            $rekap_data_absensi[$guru_id]['jumlah_piket_hadir'] = $jumlah_piket_hadir;
            $rekap_data_absensi[$guru_id]['transport_piket_total'] = $transport_piket_total;

            // 6. Perbarui Total Gaji Final
            // Total Gaji Final = 
            //    Gaji Mengajar (total_gaji_absensi: jumlah_jam * tunjangan_transport)
            //  + Tunjangan Jabatan 
            //  + Tunjangan Kegiatan Lainnya 
            //  + Transport Jabatan 
            //  + Transport Piket
            $rekap_data_absensi[$guru_id]['total_gaji_absensi_final'] = 
                $data['total_gaji_absensi'] + 
                $data['tunjangan_jabatan'] + 
                $tunjangan_kegiatan +
                $transport_jabatan_total +
                $transport_piket_total;
        }
    }

        // Gabungkan data dari kedua sumber untuk tampilan di halaman web dan sheet ringkasan Excel
        $combined_rekap_data = [];
        foreach ($rekap_data_jadwal as $guru_id => $data) {
            $combined_rekap_data[$guru_id] = [
                'nama_guru' => $data['nama_guru'],
                'total_jam_jadwal' => $data['total_jam_jadwal'],
                'total_gaji_jadwal' => $data['total_gaji_jadwal'],
                'unique_subjects_jadwal' => $data['unique_subjects_jadwal'],
                'jumlah_pertemuan_absensi' => 0, // Default
                'total_gaji_absensi_final' => 0.0, // Default
                'total_jam_absensi' => 0, // Default
                'unique_subjects_absensi' => [], // Default
                'tunjangan_jabatan' => 0.0, // Default
                'tunjangan_kegiatan_lainnya' => 0.0, // Default
                'transport_jabatan_total' => 0.0,
                'transport_piket_total' => 0.0,
                'jumlah_hadir_harian' => 0,
                'jumlah_piket_hadir' => 0,
            ];
        }

        foreach ($rekap_data_absensi as $guru_id => $data) {
            if (!isset($combined_rekap_data[$guru_id])) {
                $combined_rekap_data[$guru_id] = [
                    'nama_guru' => $data['nama_guru'],
                    'total_jam_jadwal' => 0, // Default
                    'total_gaji_jadwal' => 0.0,    // Default
                    'unique_subjects_jadwal' => [], // Default
                ];
            }
            
            // Ambil total gaji jadwal dari data yang sudah disiapkan
            $gaji_jadwal = $combined_rekap_data[$guru_id]['total_gaji_jadwal'] ?? 0.0; // Pastikan diambil
            
            // --- BAGIAN YANG DIRUBAH ---
            // Hitung total akhir baru: Gaji Absensi (termasuk tunjangan) + Gaji Jadwal
            $total_gaji_akhir_baru = $data['total_gaji_absensi_final'] + $gaji_jadwal;

            // Update nilai di combined_rekap_data
            $combined_rekap_data[$guru_id]['jumlah_pertemuan_absensi'] = $data['jumlah_pertemuan_absensi'];
            
            // **PERUBAHAN INI**: Mengganti nilai total_gaji_absensi_final dengan total yang baru
            $combined_rekap_data[$guru_id]['total_gaji_absensi_final'] = $total_gaji_akhir_baru; 
            
            $combined_rekap_data[$guru_id]['transport_jabatan_total'] = $data['transport_jabatan_total'];
            $combined_rekap_data[$guru_id]['transport_piket_total'] = $data['transport_piket_total'];
            $combined_rekap_data[$guru_id]['jumlah_hadir_harian'] = $data['jumlah_hadir_harian'];
            $combined_rekap_data[$guru_id]['jumlah_piket_hadir'] = $data['jumlah_piket_hadir'];

            
            $combined_rekap_data[$guru_id]['total_jam_absensi'] = $data['total_jam_absensi'];
            $combined_rekap_data[$guru_id]['unique_subjects_absensi'] = $data['unique_subjects_absensi'];
            $combined_rekap_data[$guru_id]['tunjangan_jabatan'] = $data['tunjangan_jabatan'];
            $combined_rekap_data[$guru_id]['tunjangan_kegiatan_lainnya'] = $data['tunjangan_kegiatan_lainnya'];
            
            
            
            
        }

        // Urutkan data gabungan untuk tampilan dan Excel
        usort($combined_rekap_data, function($a, $b) {
            return strcmp($a['nama_guru'], $b['nama_guru']);
        });

        // Jika tombol export_excel ditekan, generate XLSX
        if (isset($_POST['export_excel'])) {
    try {
        $spreadsheet = new Spreadsheet();

        // Dapatkan tahun kalender yang relevan untuk bulan yang dipilih dalam tahun ajaran
        $year_for_month_display = getCalendarYearForMonthInAcademicYear($selected_month, $selected_academic_year_str);


        // --- Sheet 1: Ringkasan Gaji Komprehensif (Mirip Tabel Web) ---
        $sheet_summary = $spreadsheet->getActiveSheet(); // Get the first default sheet
        $sheet_summary->setTitle('Ringkasan Gaji');

        // Total kolom sekarang A sampai O (15 kolom)
        $sheet_summary->setCellValue('A1', 'Ringkasan Gaji Komprehensif Guru');
        $sheet_summary->mergeCells('A1:O1'); // Update merge range
        $sheet_summary->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet_summary->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet_summary->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Tahun Ajaran: ' . $selected_academic_year_str);
        $sheet_summary->mergeCells('A2:O2'); // Update merge range
        $sheet_summary->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet_summary->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet_summary->setCellValue('A3', 'Nominal Bisyaroh per JP (dari Jadwal): Rp ' . number_format($nominal_gaji_per_pertemuan_settings, 0, ',', '.'));
        $sheet_summary->mergeCells('A3:O3'); // Update merge range
        $sheet_summary->getStyle('A3')->getFont()->setBold(true)->setSize(10);
        $sheet_summary->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


        // Header Tabel untuk Sheet Ringkasan
        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
        ];

        // Baris Header 1 (Level 1 - Merge 3 baris atau Merge kolom)
        $sheet_summary->setCellValue('A5', 'No.');
        $sheet_summary->mergeCells('A5:A7'); // Merge 3 baris
        $sheet_summary->setCellValue('B5', 'Nama Guru');
        $sheet_summary->mergeCells('B5:B7'); // Merge 3 baris
        
        $sheet_summary->setCellValue('C5', 'Berdasarkan Jadwal');
        $sheet_summary->mergeCells('C5:E5'); // Merge 3 kolom (Jadwal)
        
        $sheet_summary->setCellValue('F5', 'Tunjangan dan Absensi Aktual');
        $sheet_summary->mergeCells('F5:N5'); // Merge 9 kolom (Absensi Aktual)
        
        $sheet_summary->setCellValue('O5', 'Mata Pelajaran Diampu (Gabungan)');
        $sheet_summary->mergeCells('O5:O7'); // Merge 3 baris

        // Baris Header 2 (Level 2 - Merge 2 baris atau Merge kolom)
        // Kolom Jadwal
        $sheet_summary->setCellValue('C6', 'Jml Jam Mengajar');
        $sheet_summary->mergeCells('C6:C7');
        $sheet_summary->setCellValue('D6', 'Total Bisyaroh (Rp)');
        $sheet_summary->mergeCells('D6:D7');
        $sheet_summary->setCellValue('E6', 'Mapel');
        $sheet_summary->mergeCells('E6:E7');
        
        // Kolom Absensi Aktual
        $sheet_summary->setCellValue('F6', 'Tunjangan Jabatan (Rp)');
        $sheet_summary->mergeCells('F6:F7');
        $sheet_summary->setCellValue('G6', 'Jumlah PTM');
        $sheet_summary->mergeCells('G6:G7');
        $sheet_summary->setCellValue('H6', 'Transport Mengajar (Rp)');
        $sheet_summary->mergeCells('H6:H7');

        // Transport Jabatan (Split ke R7)
        $sheet_summary->setCellValue('I6', 'Transport Jabatan');
        $sheet_summary->mergeCells('I6:J6');
        // Transport Piket (Split ke R7)
        $sheet_summary->setCellValue('K6', 'Transport Piket');
        $sheet_summary->mergeCells('K6:L6');

        $sheet_summary->setCellValue('M6', 'Kegiatan Lainnya (Rp)');
        $sheet_summary->mergeCells('M6:M7');
        $sheet_summary->setCellValue('N6', 'Total Bisyaroh Akhir (Rp)');
        $sheet_summary->mergeCells('N6:N7');

        // Baris Header 3 (Level 3 - Detail Transport)
        $sheet_summary->setCellValue('I7', 'Hadir (Hari)');
        $sheet_summary->setCellValue('J7', 'Total (Rp)');
        $sheet_summary->setCellValue('K7', 'Piket (Hari)');
        $sheet_summary->setCellValue('L7', 'Total (Rp)');


        // Apply style to headers (A5 sampai O7)
        $sheet_summary->getStyle('A5:O7')->applyFromArray($headerStyle);
        $sheet_summary->getRowDimension(7)->setRowHeight(30); // Beri tinggi pada baris detail
        $sheet_summary->getRowDimension(6)->setRowHeight(30);

        // Data Tabel untuk Sheet Ringkasan
        $row_num = 8; // Data dimulai dari baris ke-8
        $no = 1;
        foreach ($combined_rekap_data as $data) {
            // Hitung ulang Transport Mengajar untuk Excel
            $transport_mengajar = $data['total_gaji_absensi_final'] 
                                - $data['tunjangan_jabatan'] 
                                - $data['tunjangan_kegiatan_lainnya']
                                - $data['transport_jabatan_total'] 
                                - $data['transport_piket_total'];

            $sheet_summary->setCellValue('A' . $row_num, $no++);
            $sheet_summary->setCellValue('B' . $row_num, htmlspecialchars_decode($data['nama_guru']));
            // Jadwal
            $sheet_summary->setCellValue('C' . $row_num, $data['total_jam_jadwal']);
            $sheet_summary->setCellValue('D' . $row_num, $data['total_gaji_jadwal']);
            $sheet_summary->setCellValue('E' . $row_num, implode(', ', $data['unique_subjects_jadwal']));
            // Absensi Aktual
            $sheet_summary->setCellValue('F' . $row_num, $data['tunjangan_jabatan']);
            $sheet_summary->setCellValue('G' . $row_num, $data['total_jam_absensi']);
            $sheet_summary->setCellValue('H' . $row_num, $transport_mengajar); // Transport Mengajar
            // Transport Jabatan
            $sheet_summary->setCellValue('I' . $row_num, $data['jumlah_hadir_harian']);
            $sheet_summary->setCellValue('J' . $row_num, $data['transport_jabatan_total']);
            // Transport Piket
            $sheet_summary->setCellValue('K' . $row_num, $data['jumlah_piket_hadir']);
            $sheet_summary->setCellValue('L' . $row_num, $data['transport_piket_total']);
            // Lainnya
            $sheet_summary->setCellValue('M' . $row_num, $data['tunjangan_kegiatan_lainnya']);
            // Total Akhir
            $sheet_summary->setCellValue('N' . $row_num, $data['total_gaji_absensi_final']);
            
            // Mapel Gabungan
            $all_subjects = array_unique(array_merge($data['unique_subjects_jadwal'], $data['unique_subjects_absensi']));
            sort($all_subjects);
            $sheet_summary->setCellValue('O' . $row_num, implode(', ', $all_subjects));

            $sheet_summary->getStyle('A' . $row_num . ':O' . $row_num)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row_num++;
        }
        
        // Auto-size columns (A to O)
        foreach (range('A', 'O') as $column) {
            $sheet_summary->getColumnDimension($column)->setAutoSize(true);
        }

        // Format Angka dan Mata Uang
        $sheet_summary->getStyle('C8:C' . ($row_num - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER); // Jml Jam Mengajar
        $sheet_summary->getStyle('D8:D' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0'); // Total Bisyaroh (Jadwal)
        $sheet_summary->getStyle('F8:F' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0'); // Tunjangan Jabatan
        $sheet_summary->getStyle('G8:G' . ($row_num - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER); // Jumlah PTM
        $sheet_summary->getStyle('H8:H' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0'); // Transport Mengajar (Absensi)
        $sheet_summary->getStyle('I8:I' . ($row_num - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER); // Jabatan Hadir Hari
        $sheet_summary->getStyle('J8:J' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0'); // Jabatan Total Rp
        $sheet_summary->getStyle('K8:K' . ($row_num - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER); // Piket Hadir Hari
        $sheet_summary->getStyle('L8:L' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0'); // Piket Total Rp
        $sheet_summary->getStyle('M8:M' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0'); // Kegiatan Lainnya
        $sheet_summary->getStyle('N8:N' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0'); // Total Bisyaroh Akhir


        // --- Sheet 2: Rekap Gaji Bulanan (Berdasarkan Jadwal) ---
        $spreadsheet->createSheet();
        $sheet_jadwal = $spreadsheet->getActiveSheet();
        $sheet_jadwal->setTitle('Gaji Berdasarkan Jadwal');

        $sheet_jadwal->setCellValue('A1', 'Rekap Gaji Guru Mengajar Bulanan (Berdasarkan Jadwal)');
        $sheet_jadwal->mergeCells('A1:F1');
        $sheet_jadwal->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet_jadwal->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet_jadwal->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Semester: ' . $semesters[$selected_semester_id] . ' Tahun Ajaran: ' . $selected_academic_year_str);
        $sheet_jadwal->mergeCells('A2:F2');
        $sheet_jadwal->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet_jadwal->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet_jadwal->getStyle('A4:F4')->applyFromArray($headerStyle);

        $sheet_jadwal->setCellValue('A4', 'No.');
        $sheet_jadwal->setCellValue('B4', 'Nama Guru');
        $sheet_jadwal->setCellValue('C4', 'Bulan');
        $sheet_jadwal->setCellValue('D4', 'Jumlah Jam Terjadwal');
        $sheet_jadwal->setCellValue('E4', 'Total Gaji (Rp)');
        $sheet_jadwal->setCellValue('F4', 'Mata Pelajaran Diampu');

        $row_num = 5;
        $no = 1;
        $sorted_rekap_jadwal = [];
        foreach ($rekap_data_jadwal as $data) {
            $sorted_rekap_jadwal[] = $data;
        }
        usort($sorted_rekap_jadwal, function($a, $b) {
            return strcmp($a['nama_guru'], $b['nama_guru']);
        });

        foreach ($sorted_rekap_jadwal as $data) {
            $sheet_jadwal->setCellValue('A' . $row_num, $no++);
            $sheet_jadwal->setCellValue('B' . $row_num, htmlspecialchars_decode($data['nama_guru']));
            $sheet_jadwal->setCellValue('C' . $row_num, $months[$selected_month] . ' ' . $year_for_month_display);
            $sheet_jadwal->setCellValue('D' . $row_num, $data['total_jam_jadwal']);
            $sheet_jadwal->setCellValue('E' . $row_num, $data['total_gaji_jadwal']);
            $sheet_jadwal->setCellValue('F' . $row_num, implode(', ', $data['unique_subjects_jadwal']));
            $sheet_jadwal->getStyle('A' . $row_num . ':F' . $row_num)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row_num++;
        }
        foreach (range('A', 'F') as $column) {
            $sheet_jadwal->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet_jadwal->getStyle('D5:D' . ($row_num - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
        $sheet_jadwal->getStyle('E5:E' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0');


        // --- Sheet 3: Rekap Gaji Bulanan (Berdasarkan Absensi Aktual) ---
        // Catatan: Sheet ini perlu dipertimbangkan untuk diubah total agar lebih detail 
        // karena data tunjangan transport sudah dipecah di Sheet 1.
        $spreadsheet->createSheet();
        $sheet_absensi = $spreadsheet->getActiveSheet();
        $sheet_absensi->setTitle('Gaji Berdasarkan Absensi');

        $sheet_absensi->setCellValue('A1', 'Rekap Gaji Guru Mengajar Bulanan (Berdasarkan Absensi Aktual)');
        $sheet_absensi->mergeCells('A1:H1'); // Diperluas ke H
        $sheet_absensi->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet_absensi->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet_absensi->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Tahun Ajaran: ' . $selected_academic_year_str);
        $sheet_absensi->mergeCells('A2:H2'); // Diperluas ke H
        $sheet_absensi->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet_absensi->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header baru untuk Sheet 3 (Total 8 Kolom: A4 sampai H4)
        $sheet_absensi->getStyle('A4:H4')->applyFromArray($headerStyle);

        $sheet_absensi->setCellValue('A4', 'Nama Guru');
        $sheet_absensi->setCellValue('B4', 'Tunjangan Jabatan');
        $sheet_absensi->setCellValue('C4', 'Transport Mengajar'); // Perubahan
        $sheet_absensi->setCellValue('D4', 'Transport Jabatan');
        $sheet_absensi->setCellValue('E4', 'Transport Piket');
        $sheet_absensi->setCellValue('F4', 'Tunjangan Kegiatan Lainnya');
        $sheet_absensi->setCellValue('G4', 'Total Gaji (Rp)');
        $sheet_absensi->setCellValue('H4', 'Mata Pelajaran Diampu');

        $row_num = 5;
        $sorted_rekap_absensi = [];
        foreach ($rekap_data_absensi as $data) {
            $sorted_rekap_absensi[] = $data;
        }
        usort($sorted_rekap_absensi, function($a, $b) {
            return strcmp($a['nama_guru'], $b['nama_guru']);
        });

        foreach ($sorted_rekap_absensi as $data) {
            // Hitung ulang Transport Mengajar saja
            $transport_mengajar_absensi_sheet = $data['total_gaji_absensi_final'] 
                                            - $data['tunjangan_jabatan'] 
                                            - $data['tunjangan_kegiatan_lainnya']
                                            - $data['transport_jabatan_total'] 
                                            - $data['transport_piket_total'];
                                            
            $sheet_absensi->setCellValue('A' . $row_num, htmlspecialchars_decode($data['nama_guru']));
            $sheet_absensi->setCellValue('B' . $row_num, $data['tunjangan_jabatan']);
            $sheet_absensi->setCellValue('C' . $row_num, $transport_mengajar_absensi_sheet); // Transport Mengajar
            $sheet_absensi->setCellValue('D' . $row_num, $data['transport_jabatan_total']);
            $sheet_absensi->setCellValue('E' . $row_num, $data['transport_piket_total']);
            $sheet_absensi->setCellValue('F' . $row_num, $data['tunjangan_kegiatan_lainnya']);
            $sheet_absensi->setCellValue('G' . $row_num, $data['total_gaji_absensi_final']);
            $sheet_absensi->setCellValue('H' . $row_num, implode(', ', $data['unique_subjects_absensi']));
            $sheet_absensi->getStyle('A' . $row_num . ':H' . $row_num)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row_num++;
        }
        foreach (range('A', 'H') as $column) {
            $sheet_absensi->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet_absensi->getStyle('B5:G' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0');


        // --- Sheet 4: Detail Absensi Mengajar Aktual ---
        $spreadsheet->createSheet();
        $detail_sheet_absensi = $spreadsheet->getActiveSheet();
        $detail_sheet_absensi->setTitle('Detail Absensi Aktual');

        $detail_sheet_absensi->setCellValue('A1', 'Detail Absensi Mengajar Guru (Aktual)');
        $detail_sheet_absensi->mergeCells('A1:H1');
        $detail_sheet_absensi->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $detail_sheet_absensi->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $detail_sheet_absensi->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Tahun Ajaran: ' . $selected_academic_year_str);
        $detail_sheet_absensi->mergeCells('A2:H2');
        $detail_sheet_absensi->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $detail_sheet_absensi->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $detail_sheet_absensi->getStyle('A4:H4')->applyFromArray($headerStyle);

        $detail_sheet_absensi->setCellValue('A4', 'Nama Guru');
        $detail_sheet_absensi->setCellValue('B4', 'Mata Pelajaran');
        $detail_sheet_absensi->setCellValue('C4', 'Kelas');
        $detail_sheet_absensi->setCellValue('D4', 'Tanggal Ajar');
        $detail_sheet_absensi->setCellValue('E4', 'Waktu Mulai');
        $detail_sheet_absensi->setCellValue('F4', 'Waktu Selesai');
        $detail_sheet_absensi->setCellValue('G4', 'Jumlah Jam');
        $detail_sheet_absensi->setCellValue('H4', 'Status Absen');

        $row_num_detail = 5;
        foreach ($detail_data_absensi_for_excel as $data) {
            $detail_sheet_absensi->setCellValue('A' . $row_num_detail, htmlspecialchars_decode($data['nama_guru']));
            $detail_sheet_absensi->setCellValue('B' . $row_num_detail, htmlspecialchars_decode($data['nama_mapel']));
            $detail_sheet_absensi->setCellValue('C' . $row_num_detail, htmlspecialchars_decode($data['nama_kelas']));
            $detail_sheet_absensi->setCellValue('D' . $row_num_detail, $data['tanggal_ajar']);
            $detail_sheet_absensi->setCellValue('E' . $row_num_detail, $data['waktu_mulai_ajar']);
            $detail_sheet_absensi->setCellValue('F' . $row_num_detail, $data['waktu_selesai_ajar']);
            $detail_sheet_absensi->setCellValue('G' . $row_num_detail, $data['jumlah_jam_jadwal']);
            $detail_sheet_absensi->setCellValue('H' . $row_num_detail, $data['status_absen']);
            $detail_sheet_absensi->getStyle('A' . $row_num_detail . ':H' . $row_num_detail)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row_num_detail++;
        }
        foreach (range('A', 'H') as $column) {
            $detail_sheet_absensi->getColumnDimension($column)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0); // Set sheet summary sebagai yang aktif pertama

        $filename = "rekap_gaji_komprehensif_guru_" . str_replace('/', '-', $selected_academic_year_str) . "_Bulan_" . $months[$selected_month] . ".xlsx";

        // Bersihkan output buffer sebelum mengirim header
        ob_end_clean();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();

    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
        error_log("ERROR: Error generating XLSX: " . $e->getMessage());
        // Jika terjadi error, bersihkan buffer dan tampilkan pesan error
        ob_end_clean();
        echo "<script>alert('Terjadi kesalahan saat membuat file Excel: " . addslashes($e->getMessage()) . ".');</script>";
    }
}
    }
}

// Jika tidak ada export Excel, atau ada error sebelum export, lanjutkan dengan tampilan HTML
// Bersihkan output buffer jika belum dibersihkan oleh proses export
if (ob_get_level() > 0) {
    ob_end_flush();
}

// Menyertakan header (yang sekarang berisi sidebar dan pembuka div konten utama)
require_once __DIR__ . '/../components/header_admin.php';
?>

<style>
/* Gaya CSS tambahan untuk form dan tabel */
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
    overflow-x: auto; /* Untuk responsivitas tabel */
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0; /* gray-200 */
}
th {
    background-color: #edf2f7; /* gray-100 */
    font-weight: 600;
    color: #2d3748; /* gray-800 */
    text-transform: uppercase;
    font-size: 0.875rem; /* text-sm */
}
tbody tr:last-child td {
    border-bottom: none;
}
tbody tr:hover {
    background-color: #f7fafc; /* gray-50 */
}
</style>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
    <h1 class="text-3xl font-semibold text-gray-800 mb-4 sm:mb-0">Rekap Bisyaroh Komprehensif Guru</h1>
</div>

<div class="form-container">
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="semester" class="block text-sm font-medium text-gray-700 mb-1">Semester:</label>
                <select id="semester" name="semester" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                    <?php
                    foreach ($semesters as $num => $name) {
                        $selected = ($num == $selected_semester_id) ? 'selected' : '';
                        echo "<option value=\"$num\" $selected>$name</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="academic_year_id" class="block text-sm font-medium text-gray-700 mb-1">Tahun Ajaran:</label>
                <select id="academic_year_id" name="academic_year_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                    <?php if (!empty($available_academic_years)): ?>
                        <?php foreach ($available_academic_years as $year): ?>
                            <option value="<?php echo htmlspecialchars($year['id']); ?>" <?php echo ($selected_academic_year_id == $year['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['tahun_ajaran']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">Tidak ada tahun ajaran tersedia</option>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Bulan:</label>
                <select id="month" name="month" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                    <?php
                    foreach ($months as $num => $name) {
                        $selected = ($num == $selected_month) ? 'selected' : '';
                        echo "<option value=\"$num\" $selected>$name</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="flex space-x-2">
                <button type="submit" name="generate_report" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300">
                    <i class="fas fa-search mr-2"></i> Tampilkan Rekap
                </button>
                <?php if (!empty($rekap_data_jadwal) || !empty($rekap_data_absensi)): ?>
                <button type="submit" name="export_excel" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
                    <i class="fas fa-file-excel mr-2"></i> Export Excel
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($rekap_data_jadwal) || !empty($rekap_data_absensi)): ?>
<div class="table-container mb-8">
    <h3 class="text-xl font-semibold text-gray-800 mb-4 p-4">Ringkasan Bisyaroh Guru Bulan <?= $months[$selected_month] ?> Tahun Ajaran <?= $selected_academic_year_str ?></h3>
    
    <?php if (!empty($combined_rekap_data)): ?>
    <div class="overflow-x-auto shadow-md rounded-lg">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th rowspan="3" class="px-6 py-3 text-center text-xs font-medium text-black uppercase tracking-wider">No.</th>
                <th rowspan="3" class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">Nama Guru</th>
                <th colspan="3" class="text-center px-6 py-3 text-xs font-medium text-black uppercase tracking-wider border-b border-gray-200">Berdasarkan Jadwal</th>
                <th colspan="7" class="text-center px-6 py-3 text-xs font-medium text-black uppercase tracking-wider border-b border-gray-200">Tunjangan dan Absensi Aktual</th>
                <th rowspan="3" class="px-6 py-3 text-center text-xs font-medium text-black uppercase tracking-wider">Tunjangan Kegiatan Lainnya</th>
                <th rowspan="3" class="px-6 py-3 text-right text-xs font-medium text-black uppercase tracking-wider">Total Bisyaroh Akhir (Rp)</th>
            </tr>
            <tr>
                <!-- Jadwal -->
                <th rowspan="2" class="px-6 py-3 text-center text-xs font-medium text-black uppercase tracking-wider">Jml Jam Mengajar</th>
                <th rowspan="2" class="px-6 py-3 text-right text-xs font-medium text-black uppercase tracking-wider">Total Bisyaroh (Rp)</th>
                <th rowspan="2" class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">Mapel</th>
                
                <!-- Absensi Aktual -->
                <th rowspan="2" class="px-6 py-3 text-right text-xs font-medium text-black uppercase tracking-wider">Tunjangan Jabatan (Rp)</th>
                <th rowspan="2" class="px-6 py-3 text-center text-xs font-medium text-black uppercase tracking-wider">Jumlah PTM</th>
                <th rowspan="2" class="px-6 py-3 text-right text-xs font-medium text-black uppercase tracking-wider">Transport JP dan Mengajar (Rp)</th>

                <!-- Transport Harian & Piket (New Columns) -->
                <th colspan="2" class="text-center px-6 py-3 text-xs font-medium text-black uppercase tracking-wider border-b border-gray-200">Transport Jabatan</th>
                <th colspan="2" class="text-center px-6 py-3 text-xs font-medium text-black uppercase tracking-wider border-b border-gray-200">Transport Piket</th>

            </tr>
            <tr>
                <!-- Transport Jabatan Details -->
                <th class="px-3 py-2 text-center text-xs font-medium text-black uppercase tracking-wider">Hadir (Hari)</th>
                <th class="px-3 py-2 text-right text-xs font-medium text-black uppercase tracking-wider">Total (Rp)</th>
                
                <!-- Transport Piket Details -->
                <th class="px-3 py-2 text-center text-xs font-medium text-black uppercase tracking-wider">Piket (Hari)</th>
                <th class="px-3 py-2 text-right text-xs font-medium text-black uppercase tracking-wider">Total (Rp)</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php 
            $no = 1; 
            foreach ($combined_rekap_data as $data): 
                // Hitung ulang Transport Mengajar saja
                // total_gaji_absensi_final = GajiMengajar + TunjJab + TunjKeg + TrpJab + TrpPiket
                // Transport Mengajar = total_gaji_absensi (dari AbsensiMapelGuru)
                $transport_mengajar = $data['total_gaji_absensi_final'] 
                                    - $data['tunjangan_jabatan'] 
                                    - $data['tunjangan_kegiatan_lainnya']
                                    - $data['transport_jabatan_total'] // Kolom baru
                                    - $data['transport_piket_total'];  // Kolom baru
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-black"><?= $no++ ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($data['nama_guru']) ?></td>
                
                <!-- Berdasarkan Jadwal -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-black"><?= number_format($data['total_jam_jadwal'], 0) ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-black"><?= 'Rp ' . number_format($data['total_gaji_jadwal'], 0, ',', '.') ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-black"><?= htmlspecialchars(implode(', ', $data['unique_subjects_jadwal'])) ?></td>
                
                <!-- Absensi Aktual -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-black"><?= 'Rp ' . number_format($data['tunjangan_jabatan'], 0, ',', '.') ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-black"><?= number_format($data['total_jam_absensi'], 0) ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-black"><?= 'Rp ' . number_format($transport_mengajar, 0, ',', '.') ?></td>
                
                <!-- Transport Jabatan -->
                <td class="px-3 py-4 whitespace-nowrap text-sm text-center text-black"><?= number_format($data['jumlah_hadir_harian'], 0) ?></td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-right text-black"><?= 'Rp ' . number_format($data['transport_jabatan_total'], 0, ',', '.') ?></td>
                
                <!-- Transport Piket -->
                <td class="px-3 py-4 whitespace-nowrap text-sm text-center text-black"><?= number_format($data['jumlah_piket_hadir'], 0) ?></td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-right text-black"><?= 'Rp ' . number_format($data['transport_piket_total'], 0, ',', '.') ?></td>

                <!-- Kegiatan Lainnya -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-black"><?= 'Rp ' . number_format($data['tunjangan_kegiatan_lainnya'], 0, ',', '.') ?></td>
                
                <!-- Total Akhir -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-green-700 bg-green-50"><?= 'Rp ' . number_format($data['total_gaji_absensi_final'], 0, ',', '.') ?></td>
                
                <!-- Mata Pelajaran Gabungan -->
                <!--<td class="px-6 py-4 text-sm text-black max-w-xs overflow-hidden text-ellipsis">
                    <?php
                    $all_subjects = array_unique(array_merge($data['unique_subjects_jadwal'], $data['unique_subjects_absensi']));
                    sort($all_subjects);
                    echo htmlspecialchars(implode(', ', $all_subjects));
                    ?>
                </td>-->
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
        <p class="p-4 text-gray-600 text-center">Tidak ada data rekap untuk bulan, semester, dan tahun ajaran yang dipilih. Pastikan data jadwal pelajaran dan absensi guru tersedia untuk periode ini.</p>
    <?php endif; ?>
</div>
<style>
/* Gaya CSS tambahan untuk form dan tabel dari input pengguna */
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
    overflow-x: auto; /* Untuk responsivitas tabel */
}
table {
    width: 100%;
    border-collapse: collapse;
}
/* Menghapus padding dan border default agar tidak bentrok dengan kelas Tailwind */
/* th, td {
    padding: 1rem; 
    text-align: left;
    border-bottom: 1px solid #e2e8f0; 
} */
th {
    background-color: #edf2f7; /* gray-100 */
    font-weight: 600;
    color: #2d3748; /* gray-800 */
    text-transform: uppercase;
    font-size: 0.875rem; /* text-sm */
    /* Tambahkan border eksplisit untuk sel header karena aturan default dihapus */
    border: 1px solid #e2e8f0; 
    vertical-align: middle;
}
td {
    /* Tambahkan border eksplisit untuk sel data */
    border: 1px solid #e2e8f0; 
}
tbody tr:last-child td {
    border-bottom: none;
}
tbody tr:hover {
    background-color: #f7fafc; /* gray-50 */
}
</style>
<?php endif; ?>



<?php
// Menyertakan footer
require_once __DIR__ . '/../components/footer.php';
?>