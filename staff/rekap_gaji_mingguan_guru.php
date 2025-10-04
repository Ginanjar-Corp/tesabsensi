<?php
// project-root/admin/rekap_gaji_mingguan_guru.php

// Memastikan file konfigurasi dan helper yang diperlukan sudah di-include
require_once __DIR__ . '/../config/database.php'; // Pastikan ini menginisialisasi $pdo
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
// require_once __DIR__ . '/../classes/GajiSettings.php'; // Tidak diperlukan lagi, gaji diambil dari tabel guru

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

// Inisialisasi variabel
$pdo = null;
$rekap_data_mingguan = []; // Data rekap per minggu
$detail_data_for_excel = []; // Data detail untuk sheet kedua di Excel
$error_message = flash('error');
$success_message = flash('success');

// Daftar semester untuk dropdown
$semesters = [
    1 => 'Ganjil',
    2 => 'Genap'
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
            error_log("DEBUG: No academic years found in database.");
        }

    } catch (PDOException $e) {
        error_log("Database error fetching academic years: " . $e->getMessage());
        set_flash('error', 'Terjadi kesalahan database saat mengambil tahun ajaran.');
    }
} else {
    set_flash('error', 'Koneksi database tidak tersedia untuk mengambil tahun ajaran.');
    error_log("DEBUG: PDO connection not available for fetching academic years.");
}

// Set nilai default untuk filter
$selected_semester = $_POST['semester'] ?? (APP_SEMESTER_AKTIF ?? 1);
$selected_academic_year_id = $_POST['academic_year_id'] ?? (APP_TAHUN_AJARAN_AKTIF ?? ($available_academic_years[0]['id'] ?? null));

// Cari nama tahun ajaran berdasarkan ID yang dipilih
$selected_academic_year_str = '';
foreach ($available_academic_years as $year) {
    if ($year['id'] == $selected_academic_year_id) {
        $selected_academic_year_str = $year['tahun_ajaran'];
        break;
    }
}

/**
 * Fungsi untuk mengambil data jadwal mengajar guru untuk semester dan tahun ajaran tertentu,
 * dan mengelompokkannya per minggu berdasarkan jadwal yang seharusnya terjadi.
 *
 * @param int $semester_id ID Semester (1=Ganjil, 2=Genap)
 * @param string $academic_year_str String Tahun Ajaran (misal: "2023/2024")
 * @return array Array data rekap mingguan guru dan detail pertemuan
 */
function getGuruWeeklyTeachingData($semester_id, $academic_year_str) {
    global $pdo; // Menggunakan koneksi PDO dari global scope

    $rekap_mingguan = [];
    $detail_pertemuan = [];

    if (!$pdo) {
        error_log("DEBUG: PDO connection not available in getGuruWeeklyTeachingData.");
        return ['rekap' => [], 'detail' => []];
    }

    // Tentukan tanggal mulai dan akhir semester
    $academic_year_parts = explode('/', $academic_year_str);
    $start_year = (int)($academic_year_parts[0] ?? date('Y'));
    $end_year = (int)($academic_year_parts[1] ?? ($start_year + 1));

    $semester_start_date = null;
    $semester_end_date = null;

    if ($semester_id == 1) { // Ganjil (Juli - Desember tahun pertama)
        $semester_start_date = new DateTime("{$start_year}-07-01");
        $semester_end_date = new DateTime("{$start_year}-12-31");
    } elseif ($semester_id == 2) { // Genap (Januari - Juni tahun kedua)
        $semester_start_date = new DateTime("{$end_year}-01-01");
        $semester_end_date = new DateTime("{$end_year}-06-30");
    } else {
        error_log("DEBUG: Invalid semester ID: " . $semester_id);
        return ['rekap' => [], 'detail' => []];
    }

    // Query SQL untuk mendapatkan semua jadwal pelajaran.
    // Filter tahun ajaran dan semester tidak lagi dilakukan di SQL WHERE
    // karena kolom tersebut tidak ada di jadwal_pelajaran.
    // Filtering akan dilakukan secara logis di PHP berdasarkan rentang tanggal semester.
    $sql = "
        SELECT
            jp.id AS jadwal_id,
            jp.guru_id,
            g.nama_lengkap,
            g.gaji_per_pertemuan, -- Ambil gaji per pertemuan dari tabel guru
            jp.mapel_id,
            mp.nama_mapel,
            jp.kelas_id,
            k.nama_kelas,
            jp.hari,
            jp.jam_mulai,
            jp.jam_selesai
        FROM
            jadwal_pelajaran jp
        JOIN
            guru g ON jp.guru_id = g.id
        JOIN
            mata_pelajaran mp ON jp.mapel_id = mp.id
        JOIN
            kelas k ON jp.kelas_id = k.id
        ORDER BY
            g.nama_lengkap, jp.hari, jp.jam_mulai
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $scheduled_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("DEBUG: Number of scheduled classes fetched: " . count($scheduled_classes));
        if (!empty($scheduled_classes)) {
            error_log("DEBUG: Sample scheduled class: " . print_r($scheduled_classes[0], true));
        }

        // Peta nama hari ke representasi numerik ISO (1=Senin, ..., 7=Minggu)
        $day_map = [
            'Senin' => 1,
            'Selasa' => 2,
            'Rabu' => 3,
            'Kamis' => 4,
            'Jumat' => 5,
            'Sabtu' => 6,
            'Minggu' => 7,
        ];

        // Iterasi melalui setiap minggu dalam semester
        $current_date_iterator = clone $semester_start_date;
        // Pastikan iterator dimulai dari Senin pertama atau tanggal mulai semester jika bukan Senin
        if ($current_date_iterator->format('N') != 1) {
            $current_date_iterator->modify('last monday');
            if ($current_date_iterator < $semester_start_date) {
                $current_date_iterator = clone $semester_start_date;
            }
        }

        while ($current_date_iterator <= $semester_end_date) {
            $week_start_date = clone $current_date_iterator;
            $week_end_date = clone $current_date_iterator;
            $week_end_date->modify('+6 days');

            // Sesuaikan batas minggu agar tidak melebihi batas semester
            if ($week_start_date < $semester_start_date) {
                $week_start_date = clone $semester_start_date;
            }
            if ($week_end_date > $semester_end_date) {
                $week_end_date = clone $semester_end_date;
            }

            $week_start_str = $week_start_date->format('Y-m-d');
            $week_end_str = $week_end_date->format('Y-m-d');

            error_log("DEBUG: Processing week: " . $week_start_str . " to " . $week_end_str);

            // Iterasi melalui setiap kelas terjadwal dan hitung kejadian dalam minggu ini
            foreach ($scheduled_classes as $class) {
                $guru_id = $class['guru_id'];
                $nama_guru = $class['nama_lengkap'];
                $gaji_per_pertemuan_guru = (float)$class['gaji_per_pertemuan']; // Ambil gaji spesifik guru
                $nama_mapel = $class['nama_mapel'];
                $nama_kelas = $class['nama_kelas'];
                $hari_num = $day_map[$class['hari']] ?? null;

                if ($hari_num === null) {
                    error_log("DEBUG: Invalid day name: " . $class['hari'] . " for class ID: " . $class['jadwal_id']);
                    continue;
                }

                // Hitung durasi per pertemuan untuk detail sheet
                $jam_mulai_dt = new DateTime($class['jam_mulai']);
                $jam_selesai_dt = new DateTime($class['jam_selesai']);
                $duration_interval = $jam_mulai_dt->diff($jam_selesai_dt);
                $pertemuan_duration_in_minutes = ($duration_interval->h * 60) + $duration_interval->i;

                // Periksa setiap hari dalam minggu saat ini untuk kelas terjadwal ini
                $day_in_week_iterator = clone $week_start_date;
                while ($day_in_week_iterator <= $week_end_date) {
                    // Pastikan hari ini sesuai dengan hari jadwal dan berada dalam rentang semester
                    if ($day_in_week_iterator->format('N') == $hari_num &&
                        $day_in_week_iterator >= $semester_start_date &&
                        $day_in_week_iterator <= $semester_end_date) {

                        // Kelas ini dijadwalkan pada hari ini dalam minggu dan semester ini
                        if (!isset($rekap_mingguan[$guru_id][$week_start_str])) {
                            $rekap_mingguan[$guru_id][$week_start_str] = [
                                'nama_guru' => $nama_guru,
                                'week_start_date' => $week_start_str,
                                'week_end_date' => $week_end_str,
                                'jumlah_pertemuan' => 0,
                                'total_gaji' => 0.0,
                                'unique_subjects' => [],
                            ];
                            error_log("DEBUG: Initialized rekap for guru " . $nama_guru . " for week " . $week_start_str);
                        }

                        $rekap_mingguan[$guru_id][$week_start_str]['jumlah_pertemuan']++;
                        $rekap_mingguan[$guru_id][$week_start_str]['total_gaji'] += $gaji_per_pertemuan_guru; // Gunakan gaji spesifik guru

                        if (!in_array($nama_mapel, $rekap_mingguan[$guru_id][$week_start_str]['unique_subjects'])) {
                            $rekap_mingguan[$guru_id][$week_start_str]['unique_subjects'][] = $nama_mapel;
                        }

                        // Tambahkan ke detail untuk Excel (mewakili kejadian terjadwal)
                        $detail_pertemuan[] = [
                            'nama_guru' => $nama_guru,
                            'nama_mapel' => $nama_mapel,
                            'nama_kelas' => $nama_kelas,
                            'tanggal_ajar' => $day_in_week_iterator->format('Y-m-d'), // Tanggal terjadwal
                            'waktu_mulai' => substr($class['jam_mulai'], 0, 5),
                            'waktu_selesai' => substr($class['jam_selesai'], 0, 5),
                            'durasi_menit' => $pertemuan_duration_in_minutes,
                            'status_absen' => 'Terjadwal' // Status akan menjadi 'Terjadwal'
                        ];
                        error_log("DEBUG: Added scheduled class for " . $nama_guru . " on " . $day_in_week_iterator->format('Y-m-d'));
                    }
                    $day_in_week_iterator->modify('+1 day');
                }
            }
            // Pindah ke minggu berikutnya (mulai dari Senin minggu berikutnya)
            $current_date_iterator->modify('+1 week');
            // Pastikan iterator selalu berada di hari Senin untuk konsistensi perhitungan minggu ISO
            if ($current_date_iterator->format('N') != 1) {
                 $current_date_iterator->modify('last monday');
            }
            error_log("DEBUG: Next week iterator starts at: " . $current_date_iterator->format('Y-m-d'));
        }

        // Urutkan mata pelajaran unik untuk setiap entri rekap
        foreach ($rekap_mingguan as $guru_id => &$weeks) {
            foreach ($weeks as $week_str => &$data) {
                sort($data['unique_subjects']);
            }
        }
        unset($weeks); // Putuskan referensi
        unset($data); // Putuskan referensi

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
        error_log("ERROR: Error in getGuruWeeklyTeachingData (fetching jadwal_pelajaran): " . $e->getMessage());
        return ['rekap' => [], 'detail' => []];
    }

    error_log("DEBUG: Final rekap_mingguan count: " . count($rekap_mingguan));
    error_log("DEBUG: Final detail_pertemuan count: " . count($detail_pertemuan));

    return ['rekap' => $rekap_mingguan, 'detail' => $detail_pertemuan];
}


// Proses form submission untuk generate report atau export
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_semester = filter_input(INPUT_POST, 'semester', FILTER_VALIDATE_INT);
    $selected_academic_year_id = filter_input(INPUT_POST, 'academic_year_id', FILTER_VALIDATE_INT);

    if (!$selected_semester || !$selected_academic_year_id) {
        set_flash('error', 'Pilihan semester dan tahun ajaran tidak valid.');
        error_log("DEBUG: Invalid semester or academic year ID received from POST.");
    } else {
        // Ambil kembali nama tahun ajaran yang dipilih
        foreach ($available_academic_years as $year) {
            if ($year['id'] == $selected_academic_year_id) {
                $selected_academic_year_str = $year['tahun_ajaran'];
                break;
            }
        }

        // Panggil fungsi dengan parameter yang diperbarui
        $result = getGuruWeeklyTeachingData($selected_semester, $selected_academic_year_str);
        $rekap_data_mingguan = $result['rekap'];
        $detail_data_for_excel = $result['detail'];

        error_log("DEBUG: Result from getGuruWeeklyTeachingData - Rekap Count: " . count($rekap_data_mingguan));
        error_log("DEBUG: Result from getGuruWeeklyTeachingData - Detail Count: " . count($detail_data_for_excel));


        // Jika tombol export_excel ditekan, generate XLSX
        if (isset($_POST['export_excel']) && !empty($rekap_data_mingguan)) {
            try {
                $spreadsheet = new Spreadsheet();

                // --- Sheet 1: Rekap Gaji Mingguan Guru ---
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Rekap Gaji Mingguan');

                // Set Judul
                $sheet->setCellValue('A1', 'Rekap Gaji Guru Mengajar Mingguan (Berdasarkan Jadwal)');
                $sheet->mergeCells('A1:F1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Sub-judul Semester dan Tahun Ajaran
                $sheet->setCellValue('A2', 'Semester: ' . $semesters[$selected_semester] . ' Tahun Ajaran: ' . $selected_academic_year_str);
                $sheet->mergeCells('A2:F2');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header Tabel
                $sheet->setCellValue('A4', 'No.');
                $sheet->setCellValue('B4', 'Nama Guru');
                $sheet->setCellValue('C4', 'Periode Minggu');
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
                // Mengurutkan data rekap untuk tampilan yang lebih baik di Excel
                $sorted_rekap_data = [];
                foreach ($rekap_data_mingguan as $guru_id => $weeks) {
                    foreach ($weeks as $week_start_str => $data) {
                        $sorted_rekap_data[] = $data;
                    }
                }
                // Sort by guru name, then by week start date
                usort($sorted_rekap_data, function($a, $b) {
                    $cmp_guru = strcmp($a['nama_guru'], $b['nama_guru']);
                    if ($cmp_guru !== 0) {
                        return $cmp_guru;
                    }
                    return strtotime($a['week_start_date']) - strtotime($b['week_start_date']);
                });


                foreach ($sorted_rekap_data as $data) {
                    $sheet->setCellValue('A' . $row_num, $no++);
                    $sheet->setCellValue('B' . $row_num, htmlspecialchars_decode($data['nama_guru']));
                    $sheet->setCellValue('C' . $row_num, date('d/m', strtotime($data['week_start_date'])) . ' - ' . date('d/m', strtotime($data['week_end_date'])));
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

                // --- Sheet 2: Detail Absensi Mengajar ---
                $spreadsheet->createSheet();
                $spreadsheet->setActiveSheetIndex(1);
                $detail_sheet = $spreadsheet->getActiveSheet();
                $detail_sheet->setTitle('Detail Jadwal Mengajar'); // Nama sheet diubah

                // Set Judul untuk Sheet 2
                $detail_sheet->setCellValue('A1', 'Detail Jadwal Mengajar Guru');
                $detail_sheet->mergeCells('A1:H1');
                $detail_sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $detail_sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Sub-judul Semester dan Tahun Ajaran untuk Sheet 2
                $detail_sheet->setCellValue('A2', 'Semester: ' . $semesters[$selected_semester] . ' Tahun Ajaran: ' . $selected_academic_year_str);
                $detail_sheet->mergeCells('A2:H2');
                $detail_sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $detail_sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header Tabel untuk Sheet 2
                $detail_sheet->setCellValue('A4', 'No.');
                $detail_sheet->setCellValue('B4', 'Nama Guru');
                $detail_sheet->setCellValue('C4', 'Mata Pelajaran');
                $detail_sheet->setCellValue('D4', 'Kelas');
                $detail_sheet->setCellValue('E4', 'Tanggal Terjadwal'); // Teks header diubah
                $detail_sheet->setCellValue('F4', 'Waktu Mulai');
                $detail_sheet->setCellValue('G4', 'Waktu Selesai');
                $detail_sheet->setCellValue('H4', 'Durasi (Menit)');
                $detail_sheet->setCellValue('I4', 'Status'); // Teks header diubah

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

                $filename = "rekap_gaji_mingguan_guru_Semester_" . $semesters[$selected_semester] . "_" . str_replace('/', '-', $selected_academic_year_str) . ".xlsx";

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

<!-- Konten Rekap Gaji Mingguan Guru -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
    <h1 class="text-3xl font-semibold text-gray-800 mb-4 sm:mb-0">Rekap Gaji Guru Mingguan</h1>
</div>

<div class="form-container">
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label for="semester" class="block text-sm font-medium text-gray-700 mb-1">Semester:</label>
                <select id="semester" name="semester" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                    <?php
                    foreach ($semesters as $num => $name) {
                        $selected = ($num == $selected_semester) ? 'selected' : '';
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
            <div class="flex space-x-2">
                <button type="submit" name="generate_report" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300">
                    <i class="fas fa-search mr-2"></i> Tampilkan Rekap
                </button>
                <?php if (!empty($rekap_data_mingguan)): ?>
                <button type="submit" name="export_excel" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
                    <i class="fas fa-file-excel mr-2"></i> Export Excel
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if (isset($rekap_data_mingguan) && !empty($rekap_data_mingguan)): ?>
<div class="table-container mb-8">
    <h3 class="text-xl font-semibold text-gray-800 mb-4 p-4">Rekap Gaji Guru Mengajar Semester <?= $semesters[$selected_semester] ?> Tahun Ajaran <?= $selected_academic_year_str ?> (Berdasarkan Jadwal)</h3>
    <!-- Nominal Gaji per Pertemuan dihapus karena sekarang per guru -->
    <?php if (!empty($rekap_data_mingguan)): ?>
    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Nama Guru</th>
                <th>Periode Minggu</th>
                <th>Jumlah Pertemuan Terjadwal</th>
                <th>Total Gaji (Rp)</th>
                <th>Mata Pelajaran Diampu</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            // Mengurutkan data untuk tampilan tabel
            $sorted_rekap_data_display = [];
            foreach ($rekap_data_mingguan as $guru_id => $weeks) {
                foreach ($weeks as $week_start_str => $data) {
                    $sorted_rekap_data_display[] = $data;
                }
            }
            // Sort by guru name, then by week start date
            usort($sorted_rekap_data_display, function($a, $b) {
                $cmp_guru = strcmp($a['nama_guru'], $b['nama_guru']);
                if ($cmp_guru !== 0) {
                    return $cmp_guru;
                }
                return strtotime($a['week_start_date']) - strtotime($b['week_start_date']);
            });

            foreach ($sorted_rekap_data_display as $data): ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= htmlspecialchars($data['nama_guru']) ?></td>
                <td><?= date('d/m', strtotime($data['week_start_date'])) . ' - ' . date('d/m', strtotime($data['week_end_date'])) ?></td>
                <td class="text-center"><?= number_format($data['jumlah_pertemuan'], 0) ?></td>
                <td class="text-right"><?= 'Rp ' . number_format($data['total_gaji'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars(implode(', ', $data['unique_subjects'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="p-4 text-gray-600 text-center">Tidak ada data rekap untuk semester dan tahun ajaran yang dipilih. Pastikan data jadwal pelajaran guru tersedia di tabel `jadwal_pelajaran` untuk periode ini.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
// Menyertakan footer
require_once __DIR__ . '/../components/footer_admin.php'; // Pastikan ini mengacu ke footer_admin.php
?>
