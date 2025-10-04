<?php
// project-root/admin/rekap_gaji_bulanan_guru.php

// Memastikan file konfigurasi dan helper yang diperlukan sudah di-include
require_once __DIR__ . '/../config/database.php'; // Pastikan ini menginisialisasi $pdo
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

// --- PHP SPREADSHEET INTEGRATION START ---
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
// --- PHP SPREADSHEET INTEGRATION END ---

// Asumsi jumlah minggu dalam satu bulan untuk perhitungan gaji
const FIXED_WEEKS_PER_MONTH = 1;

// Inisialisasi variabel
$pdo = null;
$rekap_data_bulanan = []; // Data rekap per bulan
$detail_data_for_excel = []; // Data detail untuk sheet kedua di Excel
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
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, tahun_ajaran FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
        $available_academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($available_academic_years)) {
            set_flash('error', 'Tidak ada data tahun ajaran ditemukan. Harap tambahkan data tahun ajaran terlebih dahulu.');
        }

    } catch (PDOException $e) {
        error_log("Database error fetching academic years: " . $e->getMessage());
        set_flash('error', 'Terjadi kesalahan database saat mengambil tahun ajaran.');
    }
} else {
    set_flash('error', 'Koneksi database tidak tersedia untuk mengambil tahun ajaran.');
}

// Set nilai default untuk filter
// Ambil nilai default dari APP_CONFIG atau set default
$default_semester_id = APP_SEMESTER_AKTIF ?? 1;
$default_academic_year_id = APP_TAHUN_AJARAN_AKTIF ?? ($available_academic_years[0]['id'] ?? null);

// Pastikan $selected_academic_year_id adalah integer, jika tidak, set ke 0
if (!is_numeric($default_academic_year_id)) {
    $default_academic_year_id = 0;
}

$selected_semester_id = $_POST['semester'] ?? $default_semester_id;
$selected_academic_year_id = $_POST['academic_year_id'] ?? $default_academic_year_id;
$selected_month = $_POST['month'] ?? date('n'); // Default ke bulan saat ini

// Cari nama tahun ajaran berdasarkan ID yang dipilih
$selected_academic_year_str = '';
foreach ($available_academic_years as $year) {
    if ($year['id'] == $selected_academic_year_id) {
        $selected_academic_year_str = $year['tahun_ajaran'];
        break;
    }
}

// Map selected semester ID to its string name for database query
$selected_semester_name = $semesters[$selected_semester_id] ?? 'Ganjil'; // Default to 'Ganjil' if not found

/**
 * Fungsi untuk mengambil data jadwal mengajar guru untuk bulan, semester (nama string), dan tahun ajaran (nama string) tertentu.
 * Perhitungan gaji bulanan didasarkan pada jumlah slot jadwal unik guru, dikalikan dengan FIXED_WEEKS_PER_MONTH.
 * Detail pertemuan tetap mencatat setiap kejadian terjadwal.
 *
 * @param string $semester_name Nama Semester (misal: "Ganjil", "Genap")
 * @param string $academic_year_str String Tahun Ajaran (misal: "2023/2024")
 * @param int $selected_month_num Nomor bulan yang dipilih (1-12)
 * @return array Array data rekap bulanan guru dan detail pertemuan
 */
function getGuruMonthlyTeachingData($semester_name, $academic_year_str, $selected_month_num) {
    global $pdo; // Menggunakan koneksi PDO dari global scope
    global $months; // Menggunakan array months dari global scope

    $rekap_bulanan = [];
    $detail_pertemuan = [];

    if (!$pdo) {
        error_log("PDO connection not available in getGuruMonthlyTeachingData.");
        return ['rekap' => [], 'detail' => []];
    }

    // Debugging: Log input parameters
    error_log("getGuruMonthlyTeachingData called with: Semester Name = {$semester_name}, Academic Year String = {$academic_year_str}, Month Num = {$selected_month_num}");

    // Tentukan tahun yang benar untuk bulan yang dipilih berdasarkan semester
    // Ini penting untuk menentukan tahun kalender yang benar untuk bulan yang dipilih
    $academic_year_parts = explode('/', $academic_year_str);
    $start_year_acad = (int)($academic_year_parts[0] ?? date('Y'));
    $end_year_acad = (int)($academic_year_parts[1] ?? ($start_year_acad + 1));

    $year_for_month = $start_year_acad;
    // Asumsi: Semester Ganjil (Juli-Desember) di tahun pertama ajaran
    // Semester Genap (Januari-Juni) di tahun kedua ajaran
    if ($selected_month_num >= 1 && $selected_month_num <= 6) { // Jan-Jun
        $year_for_month = $end_year_acad;
    } else { // Jul-Des
        $year_for_month = $start_year_acad;
    }

    $month_start_date = new DateTime("{$year_for_month}-{$selected_month_num}-01");
    $month_end_date = new DateTime($month_start_date->format('Y-m-t')); // Last day of the month

    // Query SQL untuk mendapatkan jadwal pelajaran yang relevan dengan TAHUN AJARAN dan SEMESTER (nama string) yang dipilih.
    // Menggunakan kolom 'semester' dan 'tahun_ajaran' langsung dari tabel jadwal_pelajaran
    // Mengubah JOIN ke tabel kelas menjadi LEFT JOIN agar jadwal tanpa kelas (tipe_jadwal siswa) tetap terbaca.
    // Menambahkan COALESCE untuk menampilkan 'Pribadi' jika nama_kelas kosong.
    $sql = "
        SELECT
            jp.id AS jadwal_id,
            jp.guru_id,
            g.nama_lengkap,
            g.gaji_per_pertemuan, -- Ambil gaji per pertemuan dari tabel guru
            jp.mapel_id,
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

        // Debugging: Log number of rows fetched
        error_log("Fetched " . count($scheduled_classes) . " scheduled classes for academic_year: '{$academic_year_str}' and semester: '{$semester_name}'");


        // Peta nama hari ke representasi numerik ISO (1=Senin, ..., 7=Minggu)
        $day_map = [
            'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4,
            'Jumat' => 5, 'Sabtu' => 6, 'Minggu' => 7,
        ];

        // --- Perhitungan Rekap Bulanan (berdasarkan jumlah slot unik * FIXED_WEEKS_PER_MONTH) ---
        $teacher_unique_scheduled_slots = []; // Untuk menyimpan slot jadwal unik per guru
        foreach ($scheduled_classes as $class) {
            $guru_id = $class['guru_id'];
            // Gunakan jadwal_id sebagai identifikasi unik untuk slot jadwal
            // Ini akan memastikan setiap entri jadwal_pelajaran yang berbeda dihitung sebagai slot unik
            $teacher_unique_scheduled_slots[$guru_id][$class['jadwal_id']] = $class;
        }

        $month_key = $month_start_date->format('Y-m-01'); // Key untuk agregasi bulanan

        foreach ($teacher_unique_scheduled_slots as $guru_id => $unique_slots) {
            // Ambil data guru dari salah satu slot (asumsi nama dan gaji sama untuk semua slot guru tersebut)
            $nama_guru = $unique_slots[array_key_first($unique_slots)]['nama_lengkap'];
            $gaji_per_pertemuan_guru = (float)$unique_slots[array_key_first($unique_slots)]['gaji_per_pertemuan'];

            $num_unique_scheduled_slots = count($unique_slots); // Jumlah slot jadwal unik guru
            $jumlah_pertemuan_rekap = $num_unique_scheduled_slots * FIXED_WEEKS_PER_MONTH; // Dikali 4
            $total_gaji_rekap = $jumlah_pertemuan_rekap * $gaji_per_pertemuan_guru;

            $unique_subjects_rekap = [];
            foreach ($unique_slots as $slot) {
                if (!in_array($slot['nama_mapel'], $unique_subjects_rekap)) {
                    $unique_subjects_rekap[] = $slot['nama_mapel'];
                }
            }
            sort($unique_subjects_rekap); // Urutkan mata pelajaran

            $rekap_bulanan[$guru_id][$month_key] = [
                'nama_guru' => $nama_guru,
                'month_start_date' => $month_start_date->format('Y-m-d'),
                'month_end_date' => $month_end_date->format('Y-m-d'),
                'jumlah_pertemuan' => $jumlah_pertemuan_rekap,
                'total_gaji' => $total_gaji_rekap,
                'unique_subjects' => $unique_subjects_rekap,
            ];
        }

        // --- Generasi Detail Pertemuan (tetap berdasarkan setiap kejadian terjadwal) ---
        // Ini akan mencatat setiap kali jadwal seharusnya terjadi dalam bulan yang dipilih.
        $current_day_iterator = clone $month_start_date;
        while ($current_day_iterator <= $month_end_date) {
            $current_day_iso_num = (int)$current_day_iterator->format('N'); // 1 (Senin) sampai 7 (Minggu)
            $current_date_str = $current_day_iterator->format('Y-m-d');

            foreach ($scheduled_classes as $class) { // $scheduled_classes sudah difilter berdasarkan tahun ajaran dan semester
                $hari_num = $day_map[$class['hari']] ?? null;

                if ($hari_num === null) { continue; }

                if ($current_day_iso_num == $hari_num) {
                    // Hitung durasi per pertemuan untuk detail sheet
                    $jam_mulai_dt = new DateTime($class['jam_mulai']);
                    $jam_selesai_dt = new DateTime($class['jam_selesai']);
                    $duration_interval = $jam_mulai_dt->diff($jam_selesai_dt);
                    $pertemuan_duration_in_minutes = ($duration_interval->h * 60) + $duration_interval->i;

                    $detail_pertemuan[] = [
                        'nama_guru' => $class['nama_lengkap'],
                        'nama_mapel' => $class['nama_mapel'],
                        'nama_kelas' => $class['nama_kelas'],
                        'tanggal_ajar' => $current_date_str, // Tanggal terjadwal
                        'waktu_mulai' => substr($class['jam_mulai'], 0, 5),
                        'waktu_selesai' => substr($class['jam_selesai'], 0, 5),
                        'durasi_menit' => $pertemuan_duration_in_minutes,
                        'status_absen' => 'Terjadwal' // Status akan menjadi 'Terjadwal'
                    ];
                }
            }
            $current_day_iterator->modify('+1 day'); // Pindah ke hari berikutnya
        }

        // Urutkan detail_pertemuan untuk konsistensi
        usort($detail_pertemuan, function($a, $b) {
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
        error_log("ERROR: Error in getGuruMonthlyTeachingData (fetching jadwal_pelajaran): " . $e->getMessage());
        return ['rekap' => [], 'detail' => []];
    }

    return ['rekap' => $rekap_bulanan, 'detail' => $detail_pertemuan];
}


// Proses form submission untuk generate report atau export
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Filter input untuk memastikan keamanan dan tipe data yang benar
    $selected_semester_id = filter_input(INPUT_POST, 'semester', FILTER_VALIDATE_INT);
    $selected_academic_year_id = filter_input(INPUT_POST, 'academic_year_id', FILTER_VALIDATE_INT);
    $selected_month = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT);

    // Map selected semester ID to its string name for database query
    $selected_semester_name = $semesters[$selected_semester_id] ?? 'Ganjil'; // Default to 'Ganjil' if not found

    // Validasi input filter
    if (empty($available_academic_years)) {
        set_flash('error', 'Tidak ada tahun ajaran yang tersedia. Harap tambahkan tahun ajaran terlebih dahulu.');
        $rekap_data_bulanan = []; // Ensure rekap_data_bulanan remains empty so no table is shown
    } elseif (!$selected_semester_id || !$selected_academic_year_id || !$selected_month) {
        set_flash('error', 'Pilihan semester, tahun ajaran, atau bulan tidak valid. Pastikan semua opsi telah dipilih.');
        $rekap_data_bulanan = []; // Ensure rekap_data_bulanan remains empty so no table is shown
    } else {
        // Ambil kembali nama tahun ajaran yang dipilih
        $selected_academic_year_str = '';
        foreach ($available_academic_years as $year) {
            if ($year['id'] == $selected_academic_year_id) {
                $selected_academic_year_str = $year['tahun_ajaran'];
                break;
            }
        }

        // Panggil fungsi dengan parameter yang diperbarui (menggunakan nama semester dan string tahun ajaran)
        $result = getGuruMonthlyTeachingData($selected_semester_name, $selected_academic_year_str, $selected_month);
        $rekap_data_bulanan = $result['rekap'];
        $detail_data_for_excel = $result['detail'];

        // Jika tombol export_excel ditekan, generate XLSX
        if (isset($_POST['export_excel']) && !empty($rekap_data_bulanan)) {
            try {
                $spreadsheet = new Spreadsheet();

                // --- Sheet 1: Rekap Gaji Bulanan Guru ---
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Rekap Gaji Bulanan');

                // Set Judul
                $sheet->setCellValue('A1', 'Rekap Gaji Guru Mengajar Bulanan (Berdasarkan Jadwal)');
                $sheet->mergeCells('A1:F1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Sub-judul Bulan, Semester dan Tahun Ajaran
                $sheet->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Semester: ' . $semesters[$selected_semester_id] . ' Tahun Ajaran: ' . $selected_academic_year_str);
                $sheet->mergeCells('A2:F2');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header Tabel
                $sheet->setCellValue('A4', 'No.');
                $sheet->setCellValue('B4', 'Nama Guru');
                $sheet->setCellValue('C4', 'Bulan');
                $sheet->setCellValue('D4', 'Jumlah Pertemuan Terjadwal');
                $sheet->setCellValue('E4', 'Total Gaji (Rp)');
                $sheet->setCellValue('F4', 'Mata Pelajaran Diampu');

                // Styling Header
                $headerStyle = [
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
                ];
                $sheet->getStyle('A4:F4')->applyFromArray($headerStyle);

                // Data Tabel
                $row_num = 5;
                $no = 1;
                // Mengurutkan data untuk tampilan yang lebih baik di Excel
                $sorted_rekap_data = [];
                foreach ($rekap_data_bulanan as $guru_id => $months_data) {
                    foreach ($months_data as $month_start_str => $data) {
                        $sorted_rekap_data[] = $data;
                    }
                }
                // Sort by guru name, then by month start date
                usort($sorted_rekap_data, function($a, $b) {
                    $cmp_guru = strcmp($a['nama_guru'], $b['nama_guru']);
                    if ($cmp_guru !== 0) {
                        return $cmp_guru;
                    }
                    return strtotime($a['month_start_date']) - strtotime($b['month_start_date']);
                });


                foreach ($sorted_rekap_data as $data) {
                    $sheet->setCellValue('A' . $row_num, $no++);
                    $sheet->setCellValue('B' . $row_num, htmlspecialchars_decode($data['nama_guru']));
                    $sheet->setCellValue('C' . $row_num, $months[date('n', strtotime($data['month_start_date']))] . ' ' . date('Y', strtotime($data['month_start_date'])));
                    $sheet->setCellValue('D' . $row_num, $data['jumlah_pertemuan']);
                    $sheet->setCellValue('E' . $row_num, $data['total_gaji']);
                    $sheet->setCellValue('F' . $row_num, implode(', ', $data['unique_subjects']));

                    // Styling border untuk data
                    $sheet->getStyle('A' . $row_num . ':F' . $row_num)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    $row_num++;
                }

                // Auto size columns
                foreach (range('A', 'F') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Set number format for currency and meetings (as integer)
                $sheet->getStyle('D5:D' . ($row_num - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                $sheet->getStyle('E5:E' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0');

                // --- Sheet 2: Detail Jadwal Mengajar ---
                $spreadsheet->createSheet();
                $spreadsheet->setActiveSheetIndex(1);
                $detail_sheet = $spreadsheet->getActiveSheet();
                $detail_sheet->setTitle('Detail Jadwal Mengajar');

                // Set Judul untuk Sheet 2
                $detail_sheet->setCellValue('A1', 'Detail Jadwal Mengajar Guru');
                $detail_sheet->mergeCells('A1:I1');
                $detail_sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $detail_sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Sub-judul Bulan, Semester dan Tahun Ajaran untuk Sheet 2
                $detail_sheet->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Semester: ' . $semesters[$selected_semester_id] . ' Tahun Ajaran: ' . $selected_academic_year_str);
                $detail_sheet->mergeCells('A2:I2');
                $detail_sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $detail_sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header Tabel untuk Sheet 2
                $detail_sheet->setCellValue('A4', 'No.');
                $detail_sheet->setCellValue('B4', 'Nama Guru');
                $detail_sheet->setCellValue('C4', 'Mata Pelajaran');
                $detail_sheet->setCellValue('D4', 'Kelas');
                $detail_sheet->setCellValue('E4', 'Tanggal Terjadwal');
                $detail_sheet->setCellValue('F4', 'Waktu Mulai');
                $detail_sheet->setCellValue('G4', 'Waktu Selesai');
                $detail_sheet->setCellValue('H4', 'Durasi (Menit)');
                $detail_sheet->setCellValue('I4', 'Status');

                // Styling Header untuk Sheet 2
                $detail_sheet->getStyle('A4:I4')->getFont()->setBold(true);
                $detail_sheet->getStyle('A4:I4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
                $detail_sheet->getStyle('A4:I4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Data Tabel untuk Sheet 2
                $row_num_detail = 5;
                $no_detail = 1;
                foreach ($detail_data_for_excel as $data) {
                    $detail_sheet->setCellValue('A' . $row_num_detail, $no_detail++);
                    $detail_sheet->setCellValue('B' . $row_num_detail, htmlspecialchars_decode($data['nama_guru']));
                    $detail_sheet->setCellValue('C' . $row_num_detail, htmlspecialchars_decode($data['nama_mapel']));
                    $detail_sheet->setCellValue('D' . $row_num_detail, htmlspecialchars_decode($data['nama_kelas']));
                    $detail_sheet->setCellValue('E' . $row_num_detail, $data['tanggal_ajar']);
                    $detail_sheet->setCellValue('F' . $row_num_detail, $data['waktu_mulai']);
                    $detail_sheet->setCellValue('G' . $row_num_detail, $data['waktu_selesai']);
                    $detail_sheet->setCellValue('H' . $row_num_detail, $data['durasi_menit']);
                    $detail_sheet->setCellValue('I' . $row_num_detail, $data['status_absen']);

                    // Styling border untuk data
                    $detail_sheet->getStyle('A' . $row_num_detail . ':I' . $row_num_detail)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    $row_num_detail++;
                }

                // Auto size columns untuk Sheet 2
                foreach (range('A', 'I') as $column) {
                    $detail_sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Set sheet aktif kembali ke yang pertama untuk tampilan awal
                $spreadsheet->setActiveSheetIndex(0);

                $filename = "rekap_gaji_bulanan_guru_Bulan_" . $months[$selected_month] . "_Semester_" . $semesters[$selected_semester_id] . "_" . str_replace('/', '-', $selected_academic_year_str) . ".xlsx";

                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: max-age=0');

                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
                exit();

            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                error_log("ERROR: Error generating XLSX: " . $e->getMessage());
                echo "<script>alert('Terjadi kesalahan saat membuat file Excel: " . addslashes($e->getMessage()) . ".');</script>";
            }
        }
    }
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

<!-- Konten Rekap Gaji Bulanan Guru -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
    <h1 class="text-3xl font-semibold text-gray-800 mb-4 sm:mb-0">Rekap Bisyaroh Guru Bulanan</h1>
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
                <?php if (isset($rekap_data_bulanan) && !empty($rekap_data_bulanan)): ?>
                <button type="submit" name="export_excel" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
                    <i class="fas fa-file-excel mr-2"></i> Export Excel
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if (isset($rekap_data_bulanan) && !empty($rekap_data_bulanan)): ?>
<div class="table-container mb-8">
    <h3 class="text-xl font-semibold text-gray-800 mb-4 p-4">Rekap Bisyaroh Guru Mengajar Bulan <?= $months[$selected_month] ?> Semester <?= $semesters[$selected_semester_id] ?> Tahun Ajaran <?= $selected_academic_year_str ?> (Berdasarkan Jadwal)</h3>
    <?php if (!empty($rekap_data_bulanan)): ?>
    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Nama Guru</th>
                <th>Bulan</th>
                <th>Jumlah Pertemuan Terjadwal</th>
                <th>Total Bisyaroh (Rp)</th>
                <th>Mata Pelajaran Diampu</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            // Mengurutkan data untuk tampilan tabel
            $sorted_rekap_data_display = [];
            foreach ($rekap_data_bulanan as $guru_id => $months_data) {
                foreach ($months_data as $month_start_str => $data) {
                    $sorted_rekap_data_display[] = $data;
                }
            }
            // Sort by guru name, then by month start date
            usort($sorted_rekap_data_display, function($a, $b) {
                $cmp_guru = strcmp($a['nama_guru'], $b['nama_guru']);
                if ($cmp_guru !== 0) {
                    return $cmp_guru;
                }
                return strtotime($a['month_start_date']) - strtotime($b['month_start_date']);
            });

            foreach ($sorted_rekap_data_display as $data): ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= htmlspecialchars($data['nama_guru']) ?></td>
                <td><?= $months[date('n', strtotime($data['month_start_date']))] . ' ' . date('Y', strtotime($data['month_start_date'])) ?></td>
                <td class="text-center"><?= number_format($data['jumlah_pertemuan'], 0) ?></td>
                <td class="text-right"><?= 'Rp ' . number_format($data['total_gaji'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars(implode(', ', $data['unique_subjects'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="p-4 text-gray-600 text-center">Tidak ada data rekap untuk bulan, semester, dan tahun ajaran yang dipilih. Pastikan data jadwal pelajaran guru tersedia di tabel `jadwal_pelajaran` untuk periode ini.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
// Menyertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
