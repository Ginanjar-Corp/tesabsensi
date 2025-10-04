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

// Inisialisasi bulan dan semester yang dipilih
$selected_month = $_POST['month'] ?? date('n');
$selected_semester_id = $_POST['semester'] ?? (APP_SEMESTER_AKTIF ?? 1);
$selected_semester_name = $semesters[$selected_semester_id] ?? 'Ganjil';

// Inisialisasi model dan ambil nominal gaji per pertemuan
$nominal_gaji_per_pertemuan = 0;
if ($pdo) {
    try {
        $gaji_settings_model = new GajiSettings($pdo);
        $gaji_settings = $gaji_settings_model->getSettings();
        if ($gaji_settings && isset($gaji_settings['nominal_per_pertemuan'])) {
            $nominal_gaji_per_pertemuan = (float)$gaji_settings['nominal_per_pertemuan'];
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


/**
 * Fungsi untuk mengambil data jadwal mengajar guru untuk bulan, semester (nama string), dan tahun ajaran (nama string) tertentu.
 * Perhitungan gaji bulanan didasarkan pada jumlah slot jadwal unik guru, dikalikan dengan FIXED_WEEKS_PER_MONTH.
 *
 * @param string $semester_name Nama Semester (misal: "Ganjil", "Genap")
 * @param string $academic_year_str String Tahun Ajaran (misal: "2023/2024")
 * @param int $selected_month_num Nomor bulan yang dipilih (1-12)
 * @return array Array data rekap bulanan guru berdasarkan jadwal
 */
function getGuruScheduledTeachingData($semester_name, $academic_year_str, $selected_month_num) {
    global $pdo;

    $rekap_bulanan = [];

    if (!$pdo) {
        error_log("PDO connection not available in getGuruScheduledTeachingData.");
        return [];
    }

    $year_for_month = getCalendarYearForMonthInAcademicYear($selected_month_num, $academic_year_str);

    $month_start_date = new DateTime("{$year_for_month}-{$selected_month_num}-01");

    // Mengubah JOIN ke tabel kelas menjadi LEFT JOIN agar jadwal tanpa kelas (tipe_jadwal siswa) tetap terbaca.
    // Menambahkan COALESCE untuk menampilkan 'Pribadi' jika nama_kelas kosong.
    $sql = "
        SELECT
            jp.id AS jadwal_id,
            jp.guru_id,
            g.nama_lengkap,
            g.gaji_per_pertemuan,
            mp.nama_mapel,
            jp.kelas_id,
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

        $teacher_unique_scheduled_slots = [];
        foreach ($scheduled_classes as $class) {
            $guru_id = $class['guru_id'];
            $teacher_unique_scheduled_slots[$guru_id][$class['jadwal_id']] = $class;
        }

        foreach ($teacher_unique_scheduled_slots as $guru_id => $unique_slots) {
            $nama_guru = $unique_slots[array_key_first($unique_slots)]['nama_lengkap'];
            $gaji_per_pertemuan_guru = (float)$unique_slots[array_key_first($unique_slots)]['gaji_per_pertemuan'];

            $num_unique_scheduled_slots = count($unique_slots);
            $jumlah_pertemuan_rekap = $num_unique_scheduled_slots * FIXED_WEEKS_PER_MONTH;
            $total_gaji_rekap = $jumlah_pertemuan_rekap * $gaji_per_pertemuan_guru;

            $unique_subjects_rekap = [];
            foreach ($unique_slots as $slot) {
                if (!in_array($slot['nama_mapel'], $unique_subjects_rekap)) {
                    $unique_subjects_rekap[] = $slot['nama_mapel'];
                }
            }
            sort($unique_subjects_rekap);

            $rekap_bulanan[$guru_id] = [
                'nama_guru' => $nama_guru,
                'month_start_date' => $month_start_date->format('Y-m-d'),
                'jumlah_pertemuan_jadwal' => $jumlah_pertemuan_rekap,
                'total_gaji_jadwal' => $total_gaji_rekap,
                'unique_subjects_jadwal' => $unique_subjects_rekap,
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
 * Ini adalah data aktual dari absensi_mapel_guru.
 *
 * @param int $month Bulan (1-12)
 * @param string $academic_year_str Tahun Ajaran (misal: "2023/2024")
 * @return array Array data absensi mengajar guru (rekap dan detail)
 */
function getGuruActualTeachingData($month, $academic_year_str) {
    global $pdo;
    global $nominal_gaji_per_pertemuan; // Menggunakan nominal gaji dari global scope

    $rekap_absensi = [];
    $detail_absensi = [];

    if (!$pdo) {
        error_log("PDO connection not available in getGuruActualTeachingData.");
        return ['rekap' => [], 'detail' => []];
    }

    // Mengubah JOIN ke tabel kelas menjadi LEFT JOIN dan menambahkan COALESCE untuk menangani tipe_jadwal siswa
    $sql = "
        SELECT
            g.id AS id_guru,
            g.nama_lengkap,
            amg.waktu_mulai_ajar,
            amg.waktu_selesai_ajar,
            amg.tanggal_ajar,
            mp.nama_mapel,
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

        $guru_hours_info = []; // Untuk menghitung total jam mengajar (info)
        foreach ($guru_teaching_data as $entry) {
            $guru_id = $entry['id_guru'];
            $waktu_mulai_ajar = new DateTime($entry['waktu_mulai_ajar']);
            $waktu_selesai_ajar = new DateTime($entry['waktu_selesai_ajar']);
            
            $duration_in_minutes = 0;
            if ($waktu_selesai_ajar > $waktu_mulai_ajar) {
                $interval = $waktu_mulai_ajar->diff($waktu_selesai_ajar);
                $duration_in_minutes = ($interval->h * 60) + $interval->i;
            } else {
                error_log("Invalid teaching time for guru ID {$guru_id} on {$entry['tanggal_ajar']}: End time before start time or invalid. Duration set to 0.");
            }

            if (!isset($guru_hours_info[$guru_id])) {
                $guru_hours_info[$guru_id] = [
                    'total_minutes' => 0,
                ];
            }
            $guru_hours_info[$guru_id]['total_minutes'] += $duration_in_minutes;
        }

        // Format total jam mengajar
        foreach ($guru_hours_info as $guru_id => $data) {
            $total_minutes = $data['total_minutes'];
            $hours = floor($total_minutes / 60);
            $minutes = $total_minutes % 60;
            $guru_hours_info[$guru_id]['formatted_duration'] = "{$hours} jam {$minutes} menit";
        }


        foreach ($guru_teaching_data as $entry) {
            $guru_id = $entry['id_guru'];
            $nama_guru = $entry['nama_lengkap'];
            $nama_mapel = $entry['nama_mapel'];
            $nama_kelas = $entry['nama_kelas'];
            $tanggal_ajar = $entry['tanggal_ajar'];
            $waktu_mulai_ajar = new DateTime($entry['waktu_mulai_ajar']);
            $waktu_selesai_ajar = new DateTime($entry['waktu_selesai_ajar']);

            if (!isset($rekap_absensi[$guru_id])) {
                $rekap_absensi[$guru_id] = [
                    'nama_guru' => $nama_guru,
                    'jumlah_pertemuan_absensi' => 0,
                    'total_gaji_absensi' => 0.0,
                    'total_jam_mengajar_formatted' => '0 jam 0 menit',
                    'unique_subjects_absensi' => [],
                ];
            }

            $pertemuan_duration_in_minutes = 0;
            if ($waktu_selesai_ajar > $waktu_mulai_ajar) {
                $pertemuan_interval = $waktu_mulai_ajar->diff($waktu_selesai_ajar);
                $pertemuan_duration_in_minutes = ($pertemuan_interval->h * 60) + $pertemuan_interval->i;
            }

            $rekap_absensi[$guru_id]['jumlah_pertemuan_absensi']++;
            $rekap_absensi[$guru_id]['total_gaji_absensi'] += $nominal_gaji_per_pertemuan; // Menggunakan nominal gaji per pertemuan

            if (!in_array($nama_mapel, $rekap_absensi[$guru_id]['unique_subjects_absensi'])) {
                $rekap_absensi[$guru_id]['unique_subjects_absensi'][] = $nama_mapel;
            }

            // Set total jam mengajar dari hasil perhitungan
            if (isset($guru_hours_info[$guru_id])) {
                $rekap_absensi[$guru_id]['total_jam_mengajar_formatted'] = $guru_hours_info[$guru_id]['formatted_duration'];
            }

            $detail_absensi[] = [
                'nama_guru' => $nama_guru,
                'nama_mapel' => $nama_mapel,
                'nama_kelas' => $nama_kelas,
                'tanggal_ajar' => $tanggal_ajar,
                'waktu_mulai' => substr($waktu_mulai_ajar->format('H:i:s'), 0, 5),
                'waktu_selesai' => substr($waktu_selesai_ajar->format('H:i:s'), 0, 5),
                'durasi_menit' => $pertemuan_duration_in_minutes,
                'status_absen' => 'Hadir'
            ];
        }

        // Urutkan mata pelajaran unik untuk setiap entri rekap
        foreach ($rekap_absensi as $guru_id => &$data) {
            sort($data['unique_subjects_absensi']);
        }
        unset($data); // Putuskan referensi

        // Urutkan detail_absensi untuk konsistensi
        usort($detail_absensi, function($a, $b) {
            $cmp_guru = strcmp($a['nama_guru'], $b['nama_guru']);
            if ($cmp_guru !== 0) {
                return $cmp_guru;
            }
            $cmp_date = strtotime($a['tanggal_ajar']) - strtotime($b['tanggal_ajar']);
            if ($cmp_date !== 0) {
                return $cmp_date;
            }
            return strtotime($a['waktu_mulai']) - strtotime($b['waktu_mulai']);
        });

    } catch (PDOException $e) {
        error_log("ERROR: Error in getGuruActualTeachingData: " . $e->getMessage());
        return ['rekap' => [], 'detail' => []];
    }
    return ['rekap' => $rekap_absensi, 'detail' => $detail_absensi];
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

        // Gabungkan data dari kedua sumber untuk tampilan di halaman web dan sheet ringkasan Excel
        $combined_rekap_data = [];
        foreach ($rekap_data_jadwal as $guru_id => $data) {
            $combined_rekap_data[$guru_id] = [
                'nama_guru' => $data['nama_guru'],
                'jumlah_pertemuan_jadwal' => $data['jumlah_pertemuan_jadwal'],
                'total_gaji_jadwal' => $data['total_gaji_jadwal'],
                'unique_subjects_jadwal' => $data['unique_subjects_jadwal'],
                'jumlah_pertemuan_absensi' => 0, // Default
                'total_gaji_absensi' => 0.0,    // Default
                'total_jam_mengajar_formatted' => '0 jam 0 menit', // Default
                'unique_subjects_absensi' => [], // Default
            ];
        }

        foreach ($rekap_data_absensi as $guru_id => $data) {
            if (!isset($combined_rekap_data[$guru_id])) {
                $combined_rekap_data[$guru_id] = [
                    'nama_guru' => $data['nama_guru'],
                    'jumlah_pertemuan_jadwal' => 0, // Default
                    'total_gaji_jadwal' => 0.0,    // Default
                    'unique_subjects_jadwal' => [], // Default
                ];
            }
            $combined_rekap_data[$guru_id]['jumlah_pertemuan_absensi'] = $data['jumlah_pertemuan_absensi'];
            $combined_rekap_data[$guru_id]['total_gaji_absensi'] = $data['total_gaji_absensi'];
            $combined_rekap_data[$guru_id]['total_jam_mengajar_formatted'] = $data['total_jam_mengajar_formatted'];
            $combined_rekap_data[$guru_id]['unique_subjects_absensi'] = $data['unique_subjects_absensi'];
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

                $sheet_summary->setCellValue('A1', 'Ringkasan Gaji Komprehensif Guru');
                $sheet_summary->mergeCells('A1:I1'); // Merge sesuai jumlah kolom
                $sheet_summary->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet_summary->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet_summary->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Tahun Ajaran: ' . $selected_academic_year_str);
                $sheet_summary->mergeCells('A2:I2');
                $sheet_summary->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $sheet_summary->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $sheet_summary->setCellValue('A3', 'Nominal Gaji per Pertemuan (dari Pengaturan Gaji): Rp ' . number_format($nominal_gaji_per_pertemuan, 0, ',', '.'));
                $sheet_summary->mergeCells('A3:I3');
                $sheet_summary->getStyle('A3')->getFont()->setBold(true)->setSize(10);
                $sheet_summary->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


                // Header Tabel untuk Sheet Ringkasan
                $headerStyle = [
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
                ];

                // Baris Header 1
                $sheet_summary->setCellValue('A5', 'No.');
                $sheet_summary->setCellValue('B5', 'Nama Guru');
                $sheet_summary->setCellValue('C5', 'Berdasarkan Jadwal (Asumsi '.FIXED_WEEKS_PER_MONTH.' Minggu/Bulan)');
                $sheet_summary->mergeCells('C5:E5');
                $sheet_summary->setCellValue('F5', 'Berdasarkan Absensi Aktual');
                $sheet_summary->mergeCells('F5:H5');
                $sheet_summary->setCellValue('I5', 'Mata Pelajaran Diampu (Gabungan)');

                // Baris Header 2
                $sheet_summary->setCellValue('C6', 'Jml Pertemuan');
                $sheet_summary->setCellValue('D6', 'Total Gaji (Rp)');
                $sheet_summary->setCellValue('E6', 'Mapel');
                $sheet_summary->setCellValue('F6', 'Jml Pertemuan');
                $sheet_summary->setCellValue('G6', 'Total Gaji (Rp)');
                $sheet_summary->setCellValue('H6', 'Total Jam Mengajar (Info)');

                // Apply style to headers
                $sheet_summary->getStyle('A5:I6')->applyFromArray($headerStyle); // Apply to both header rows
                $sheet_summary->getStyle('A5:B6')->applyFromArray($headerStyle); // For No. and Nama Guru

                // Data Tabel untuk Sheet Ringkasan
                $row_num = 7;
                $no = 1;
                foreach ($combined_rekap_data as $data) {
                    $sheet_summary->setCellValue('A' . $row_num, $no++);
                    $sheet_summary->setCellValue('B' . $row_num, htmlspecialchars_decode($data['nama_guru']));
                    $sheet_summary->setCellValue('C' . $row_num, $data['jumlah_pertemuan_jadwal']);
                    $sheet_summary->setCellValue('D' . $row_num, $data['total_gaji_jadwal']);
                    $sheet_summary->setCellValue('E' . $row_num, implode(', ', $data['unique_subjects_jadwal']));
                    $sheet_summary->setCellValue('F' . $row_num, $data['jumlah_pertemuan_absensi']);
                    $sheet_summary->setCellValue('G' . $row_num, $data['total_gaji_absensi']);
                    $sheet_summary->setCellValue('H' . $row_num, $data['total_jam_mengajar_formatted']);
                    
                    $all_subjects = array_unique(array_merge($data['unique_subjects_jadwal'], $data['unique_subjects_absensi']));
                    sort($all_subjects);
                    $sheet_summary->setCellValue('I' . $row_num, implode(', ', $all_subjects));

                    $sheet_summary->getStyle('A' . $row_num . ':I' . $row_num)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $row_num++;
                }
                foreach (range('A', 'I') as $column) {
                    $sheet_summary->getColumnDimension($column)->setAutoSize(true);
                }
                $sheet_summary->getStyle('C7:C' . ($row_num - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                $sheet_summary->getStyle('D7:D' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet_summary->getStyle('F7:F' . ($row_num - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                $sheet_summary->getStyle('G7:G' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0');


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
                $sheet_jadwal->setCellValue('D4', 'Jumlah Pertemuan Terjadwal');
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
                    $sheet_jadwal->setCellValue('D' . $row_num, $data['jumlah_pertemuan_jadwal']);
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
                $spreadsheet->createSheet();
                $sheet_absensi = $spreadsheet->getActiveSheet();
                $sheet_absensi->setTitle('Gaji Berdasarkan Absensi');

                $sheet_absensi->setCellValue('A1', 'Rekap Gaji Guru Mengajar Bulanan (Berdasarkan Absensi Aktual)');
                $sheet_absensi->mergeCells('A1:E1');
                $sheet_absensi->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet_absensi->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet_absensi->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Tahun Ajaran: ' . $selected_academic_year_str);
                $sheet_absensi->mergeCells('A2:E2');
                $sheet_absensi->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $sheet_absensi->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet_absensi->setCellValue('A3', 'Nominal Gaji per Pertemuan: Rp ' . number_format($nominal_gaji_per_pertemuan, 0, ',', '.'));
                $sheet_absensi->mergeCells('A3:E3');
                $sheet_absensi->getStyle('A3')->getFont()->setBold(true)->setSize(10);
                $sheet_absensi->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


                $sheet_absensi->getStyle('A4:E4')->applyFromArray($headerStyle);

                $sheet_absensi->setCellValue('A4', 'Nama Guru');
                $sheet_absensi->setCellValue('B4', 'Jumlah Pertemuan');
                $sheet_absensi->setCellValue('C4', 'Total Jam Mengajar (Info)');
                $sheet_absensi->setCellValue('D4', 'Total Gaji (Rp)');
                $sheet_absensi->setCellValue('E4', 'Mata Pelajaran Diampu');

                $row_num = 5;
                $sorted_rekap_absensi = [];
                foreach ($rekap_data_absensi as $data) {
                    $sorted_rekap_absensi[] = $data;
                }
                usort($sorted_rekap_absensi, function($a, $b) {
                    return strcmp($a['nama_guru'], $b['nama_guru']);
                });

                foreach ($sorted_rekap_absensi as $data) {
                    $sheet_absensi->setCellValue('A' . $row_num, htmlspecialchars_decode($data['nama_guru']));
                    $sheet_absensi->setCellValue('B' . $row_num, $data['jumlah_pertemuan_absensi']);
                    $sheet_absensi->setCellValue('C' . $row_num, $data['total_jam_mengajar_formatted']);
                    $sheet_absensi->setCellValue('D' . $row_num, $data['total_gaji_absensi']);
                    $sheet_absensi->setCellValue('E' . $row_num, implode(', ', $data['unique_subjects_absensi']));
                    $sheet_absensi->getStyle('A' . $row_num . ':E' . $row_num)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $row_num++;
                }
                foreach (range('A', 'E') as $column) {
                    $sheet_absensi->getColumnDimension($column)->setAutoSize(true);
                }
                $sheet_absensi->getStyle('B5:B' . ($row_num - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                $sheet_absensi->getStyle('D5:D' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0');

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
                $detail_sheet_absensi->setCellValue('G4', 'Durasi (Menit)');
                $detail_sheet_absensi->setCellValue('H4', 'Status Absen');

                $row_num_detail = 5;
                foreach ($detail_data_absensi_for_excel as $data) {
                    $detail_sheet_absensi->setCellValue('A' . $row_num_detail, htmlspecialchars_decode($data['nama_guru']));
                    $detail_sheet_absensi->setCellValue('B' . $row_num_detail, htmlspecialchars_decode($data['nama_mapel']));
                    $detail_sheet_absensi->setCellValue('C' . $row_num_detail, htmlspecialchars_decode($data['nama_kelas']));
                    $detail_sheet_absensi->setCellValue('D' . $row_num_detail, $data['tanggal_ajar']);
                    $detail_sheet_absensi->setCellValue('E' . $row_num_detail, $data['waktu_mulai']);
                    $detail_sheet_absensi->setCellValue('F' . $row_num_detail, $data['waktu_selesai']);
                    $detail_sheet_absensi->setCellValue('G' . $row_num_detail, $data['durasi_menit']);
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

<!-- Konten Rekap Gaji Komprehensif Guru -->
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
                <!--<button type="submit" name="export_excel" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
                    <i class="fas fa-file-excel mr-2"></i> Export Excel
                </button>-->
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($rekap_data_jadwal) || !empty($rekap_data_absensi)): ?>
<div class="table-container mb-8">
    <h3 class="text-xl font-semibold text-gray-800 mb-4 p-4">Ringkasan Bisyaroh Guru Bulan <?= $months[$selected_month] ?> Tahun Ajaran <?= $selected_academic_year_str ?></h3>
    <p class="text-gray-600 mb-4 p-4">Nominal Bisyaroh per Pertemuan (dari Pengaturan Bisyaroh): **Rp <?= number_format($nominal_gaji_per_pertemuan, 0, ',', '.') ?>**</p>
    
    <?php if (!empty($combined_rekap_data)): ?>
    <table>
        <thead>
            <tr>
                <th rowspan="2">No.</th>
                <th rowspan="2">Nama Guru</th>
                <th colspan="3" class="text-center">Berdasarkan Jadwal</th>
                <th colspan="3" class="text-center">Berdasarkan Absensi Aktual</th>
                <th rowspan="2">Mata Pelajaran Diampu (Gabungan)</th>
            </tr>
            <tr>
                <th>Jml Pertemuan</th>
                <th>Total Bisyaroh (Rp)</th>
                <th>Mapel</th>
                <th>Jml Pertemuan</th>
                <th>Total Bisyaroh (Rp)</th>
                <th>Total Jam Mengajar (Info)</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($combined_rekap_data as $data): ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= htmlspecialchars($data['nama_guru']) ?></td>
                <td class="text-center"><?= number_format($data['jumlah_pertemuan_jadwal'], 0) ?></td>
                <td class="text-right"><?= 'Rp ' . number_format($data['total_gaji_jadwal'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars(implode(', ', $data['unique_subjects_jadwal'])) ?></td>
                <td class="text-center"><?= number_format($data['jumlah_pertemuan_absensi'], 0) ?></td>
                <td class="text-right"><?= 'Rp ' . number_format($data['total_gaji_absensi'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($data['total_jam_mengajar_formatted']) ?></td>
                <td>
                    <?php
                    $all_subjects = array_unique(array_merge($data['unique_subjects_jadwal'], $data['unique_subjects_absensi']));
                    sort($all_subjects);
                    echo htmlspecialchars(implode(', ', $all_subjects));
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="p-4 text-gray-600 text-center">Tidak ada data rekap untuk bulan, semester, dan tahun ajaran yang dipilih. Pastikan data jadwal pelajaran dan absensi guru tersedia untuk periode ini.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
// Menyertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
