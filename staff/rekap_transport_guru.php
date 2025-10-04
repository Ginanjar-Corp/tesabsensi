<?php
// project-root/admin/rekap_transport_guru.php

// Memastikan file konfigurasi dan helper yang diperlukan sudah di-include
require_once __DIR__ . '/../config/database.php'; // Pastikan ini menginisialisasi $pdo
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/GajiSettings.php'; // Tambahkan ini

// --- PHP SPREADSHEET INTEGRATION START ---
// PASTIKAN FILE INI ADA DAN PATHNYA BENAR SETELAH INSTALASI COMPOSER/PHPSPREADSHEET
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat; // Tambahkan ini untuk format angka
// --- PHP SPREADSHEET INTEGRATION END ---

// Memastikan pengguna sudah login dan memiliki peran yang sesuai
require_login();
require_role(['Admin', 'TataUsaha']); // Hanya Admin dan Tata Usaha yang bisa akses

// Inisialisasi variabel
$pdo = null;
$rekap_data = [];
$detail_data_for_excel = [];
$error_message = flash('error');
$success_message = flash('success');

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
$selected_academic_year_id = null; // Default null
$selected_academic_year_str = ''; // Default string kosong

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, tahun_ajaran FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
        $available_academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($available_academic_years)) {
            set_flash('error', 'Tidak ada data tahun ajaran ditemukan. Harap tambahkan data tahun ajaran terlebih dahulu.');
        } else {
            // Set nilai default untuk tahun ajaran yang dipilih
            // Jika ada POST, gunakan POST. Jika tidak, coba ambil dari APP_TAHUN_AJARAN_AKTIF,
            // jika tidak ada, gunakan tahun ajaran pertama dari hasil query.
            $default_academic_year_id = APP_TAHUN_AJARAN_AKTIF ?? ($available_academic_years[0]['id'] ?? null);
            $selected_academic_year_id = $_POST['academic_year_id'] ?? $default_academic_year_id;

            // Pastikan $selected_academic_year_id adalah integer, jika tidak, set ke null
            if (!is_numeric($selected_academic_year_id)) {
                $selected_academic_year_id = null;
            }

            // Cari nama tahun ajaran berdasarkan ID yang dipilih
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

// Inisialisasi bulan yang dipilih
$selected_month = $_POST['month'] ?? date('n'); // Default ke bulan saat ini

// Inisialisasi model dan ambil nominal gaji per pertemuan
$nominal_gaji_per_pertemuan = 0;
if ($pdo) {
    try {
        $gaji_settings_model = new GajiSettings($pdo);
        $gaji_settings = $gaji_settings_model->getSettings();
        if ($gaji_settings && isset($gaji_settings['nominal_per_pertemuan'])) { // Perhatikan perubahan nama kolom
            $nominal_gaji_per_pertemuan = (float)$gaji_settings['nominal_per_pertemuan'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching Gaji Settings: " . $e->getMessage());
        // Lanjutkan dengan 0 jika ada error
    }
}


/**
 * Fungsi untuk mengambil data absensi mengajar guru untuk bulan dan tahun ajaran tertentu.
 * Mengambil waktu_mulai_ajar dan waktu_selesai_ajar dari absensi_mapel_guru.
 *
 * @param int $month Bulan (1-12)
 * @param string $academic_year_str Tahun Ajaran (misal: "2023/2024")
 * @return array Array data mengajar guru
 */
function getGuruMonthlyTeachingData($month, $academic_year_str) {
    global $pdo; // Menggunakan koneksi PDO dari global scope

    $data = [];

    if (!$pdo) {
        error_log("PDO connection not available in getGuruMonthlyTeachingData.");
        return [];
    }

    // Query SQL untuk mengambil data absensi guru yang cocok dengan jadwal dan periode yang dipilih.
    // Menggunakan waktu_mulai_ajar dan waktu_selesai_ajar dari tabel absensi_mapel_guru
    // Mengubah JOIN ke tabel kelas menjadi LEFT JOIN agar jadwal tanpa kelas (tipe_jadwal siswa) tetap terbaca.
    // Menggunakan COALESCE untuk menampilkan 'Pribadi' jika nama_kelas kosong.
    $sql = "
        SELECT
            g.id AS id_guru,
            g.nama_lengkap,
            amg.waktu_mulai_ajar,    -- Mengambil waktu mulai aktual
            amg.waktu_selesai_ajar, -- Mengambil waktu selesai aktual
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
            g.is_active = 1 -- Menggunakan 1 untuk status aktif
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

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }
    } catch (PDOException $e) {
        error_log("Error in getGuruMonthlyTeachingData: " . $e->getMessage());
        return []; // Kembalikan array kosong jika ada error
    }
    return $data;
}


// Fungsi calculateGuruMonthlyHours tidak lagi digunakan untuk perhitungan gaji,
// tetapi bisa dipertahankan jika ingin menampilkan total jam mengajar sebagai informasi
// di laporan atau detail sheet Excel.
function calculateGuruMonthlyHoursInfo($guru_teaching_data) {
    $guru_hours = [];
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

        if (!isset($guru_hours[$guru_id])) {
            $guru_hours[$guru_id] = [
                'total_minutes' => 0,
            ];
        }
        $guru_hours[$guru_id]['total_minutes'] += $duration_in_minutes;
    }

    foreach ($guru_hours as $guru_id => $data) {
        $total_minutes = $data['total_minutes'];
        $hours = floor($total_minutes / 60);
        $minutes = $total_minutes % 60;
        $guru_hours[$guru_id]['formatted_duration'] = "{$hours} jam {$minutes} menit";
    }

    return $guru_hours;
}


// Proses form submission untuk generate report atau export
if (isset($_POST['generate_report']) || isset($_POST['export_excel'])) {
    // Filter input untuk memastikan keamanan dan tipe data yang benar
    $selected_month = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT);
    $selected_academic_year_id_post = filter_input(INPUT_POST, 'academic_year_id', FILTER_VALIDATE_INT);

    // Update selected_academic_year_id dan selected_academic_year_str based on POST
    $selected_academic_year_id = $selected_academic_year_id_post;
    $selected_academic_year_str = '';
    foreach ($available_academic_years as $year) {
        if ($year['id'] == $selected_academic_year_id) {
            $selected_academic_year_str = $year['tahun_ajaran'];
            break;
        }
    }

    // Validasi input filter
    if (empty($available_academic_years)) {
        set_flash('error', 'Tidak ada tahun ajaran yang tersedia. Harap tambahkan tahun ajaran terlebih dahulu.');
        $rekap_data = []; // Ensure rekap_data remains empty so no table is shown
    } elseif (!$selected_academic_year_id || !$selected_month) {
        set_flash('error', 'Pilihan tahun ajaran atau bulan tidak valid. Pastikan semua opsi telah dipilih.');
        $rekap_data = []; // Ensure rekap_data remains empty so no table is shown
    } else {
        // Panggil fungsi dengan bulan dan string tahun ajaran
        $guru_teaching_data = getGuruMonthlyTeachingData($selected_month, $selected_academic_year_str);
        $guru_monthly_hours_info = calculateGuruMonthlyHoursInfo($guru_teaching_data); // Hitung jam mengajar untuk informasi

        $rekap_data = [];
        $detail_data_for_excel = []; // Array baru untuk menyimpan data detail untuk sheet kedua di Excel

        foreach ($guru_teaching_data as $entry) {
            $guru_id = $entry['id_guru'];
            $nama_guru = $entry['nama_lengkap'];
            $nama_mapel = $entry['nama_mapel'];
            $nama_kelas = $entry['nama_kelas'];
            $tanggal_ajar = $entry['tanggal_ajar'];
            $waktu_mulai_ajar = new DateTime($entry['waktu_mulai_ajar']); // Gunakan waktu aktual
            $waktu_selesai_ajar = new DateTime($entry['waktu_selesai_ajar']); // Gunakan waktu aktual

            if (!isset($rekap_data[$guru_id])) {
                $rekap_data[$guru_id] = [
                    'nama_guru' => $nama_guru,
                    'jumlah_pertemuan' => 0,
                    'total_gaji' => 0.0, // Total gaji berdasarkan pertemuan
                    'total_jam_mengajar_formatted' => '0 jam 0 menit', // Untuk informasi, akan diisi nanti
                    'unique_subjects' => [], // Untuk menyimpan nama mata pelajaran unik yang diampu
                ];
            }

            // Hitung durasi per pertemuan untuk detail sheet Excel
            $pertemuan_duration_in_minutes = 0;
            if ($waktu_selesai_ajar > $waktu_mulai_ajar) {
                $pertemuan_interval = $waktu_mulai_ajar->diff($waktu_selesai_ajar);
                $pertemuan_duration_in_minutes = ($pertemuan_interval->h * 60) + $pertemuan_interval->i;
            }

            // Setiap entri dari absensi_mapel_guru yang cocok dengan jadwal dianggap satu pertemuan
            $rekap_data[$guru_id]['jumlah_pertemuan']++;
            
            // Tambahkan mata pelajaran ke daftar unik jika belum ada
            if (!in_array($nama_mapel, $rekap_data[$guru_id]['unique_subjects'])) {
                $rekap_data[$guru_id]['unique_subjects'][] = $nama_mapel;
            }

            // Tambahkan detail pertemuan ke array untuk sheet detail di Excel
            $detail_data_for_excel[] = [
                'nama_guru' => $nama_guru,
                'nama_mapel' => $nama_mapel,
                'nama_kelas' => $nama_kelas,
                'tanggal_ajar' => $tanggal_ajar,
                'waktu_mulai' => substr($waktu_mulai_ajar->format('H:i:s'), 0, 5), // Format HH:MM
                'waktu_selesai' => substr($waktu_selesai_ajar->format('H:i:s'), 0, 5), // Format HH:MM
                'durasi_menit' => $pertemuan_duration_in_minutes,
                'status_absen' => 'Hadir' // Diasumsikan hadir karena data berasal dari absensi_mapel_guru
            ];
        }

        // Hitung total gaji berdasarkan jumlah pertemuan dan urutkan mata pelajaran unik
        foreach ($rekap_data as $guru_id => $data) {
            // Hitung total gaji berdasarkan jumlah pertemuan dan nominal per pertemuan
            $rekap_data[$guru_id]['total_gaji'] = $data['jumlah_pertemuan'] * $nominal_gaji_per_pertemuan;

            // Ambil informasi total jam mengajar dari hasil calculateGuruMonthlyHoursInfo
            if (isset($guru_monthly_hours_info[$guru_id])) {
                $rekap_data[$guru_id]['total_jam_mengajar_formatted'] = $guru_monthly_hours_info[$guru_id]['formatted_duration'];
            }

            sort($rekap_data[$guru_id]['unique_subjects']); // Urutkan mata pelajaran secara alfabetis
        }

        // Jika tombol export_excel ditekan, generate XLSX
        if (isset($_POST['export_excel']) && !empty($rekap_data)) {
            // --- PHP SPREADSHEET INTEGRATION START ---
            try {
                $spreadsheet = new Spreadsheet();

                // --- Sheet 1: Rekap Gaji Guru ---
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Rekap Gaji Guru'); // Judul sheet

                // Set Judul
                $sheet->setCellValue('A1', 'Rekap Gaji Guru Mengajar'); // Judul
                $sheet->mergeCells('A1:E1'); // Merge sampai kolom E
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Sub-judul Bulan dan Tahun Ajaran
                $sheet->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Tahun Ajaran: ' . $selected_academic_year_str);
                $sheet->mergeCells('A2:E2'); // Merge sampai kolom E
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header Tabel
                $sheet->setCellValue('A4', 'Nama Guru');
                $sheet->setCellValue('B4', 'Jumlah Pertemuan');
                $sheet->setCellValue('C4', 'Total Jam Mengajar (Info)'); // Kolom informasi
                $sheet->setCellValue('D4', 'Total Gaji (Rp)'); // Ganti judul
                $sheet->setCellValue('E4', 'Mata Pelajaran Diampu'); // Kolom baru

                // Styling Header
                $headerStyle = [
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
                ];
                $sheet->getStyle('A4:E4')->applyFromArray($headerStyle);

                // Data Tabel
                $row_num = 5;
                foreach ($rekap_data as $data) {
                    $sheet->setCellValue('A' . $row_num, htmlspecialchars_decode($data['nama_guru']));
                    $sheet->setCellValue('B' . $row_num, $data['jumlah_pertemuan']);
                    $sheet->setCellValue('C' . $row_num, $data['total_jam_mengajar_formatted']); // Tampilkan jam terformat (info)
                    $sheet->setCellValue('D' . $row_num, $data['total_gaji']); // Gaji per pertemuan
                    $sheet->setCellValue('E' . $row_num, implode(', ', $data['unique_subjects'])); // Gabungkan mata pelajaran unik

                    // Styling border untuk data
                    $sheet->getStyle('A' . $row_num . ':E' . $row_num)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN); // Sampai E

                    $row_num++;
                }

                // Auto size columns
                foreach (range('A', 'E') as $column) { // Sampai E
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Set number format for currency and meetings (as integer)
                $sheet->getStyle('B5:B' . ($row_num - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER); // Format sebagai angka bulat
                $sheet->getStyle('D5:D' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0'); // Currency format

                // --- Sheet 2: Detail Absensi Mengajar ---
                $spreadsheet->createSheet();
                $spreadsheet->setActiveSheetIndex(1);
                $detail_sheet = $spreadsheet->getActiveSheet();
                $detail_sheet->setTitle('Detail Absensi Mengajar');

                // Set Judul untuk Sheet 2
                $detail_sheet->setCellValue('A1', 'Detail Absensi Mengajar Guru');
                $detail_sheet->mergeCells('A1:H1'); // Merge sesuai jumlah kolom detail
                $detail_sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $detail_sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Sub-judul Bulan dan Tahun Ajaran untuk Sheet 2
                $detail_sheet->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Tahun Ajaran: ' . $selected_academic_year_str);
                $detail_sheet->mergeCells('A2:H2'); // Merge sesuai jumlah kolom detail
                $detail_sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $detail_sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header Tabel untuk Sheet 2
                $detail_sheet->setCellValue('A4', 'Nama Guru');
                $detail_sheet->setCellValue('B4', 'Mata Pelajaran');
                $detail_sheet->setCellValue('C4', 'Kelas');
                $detail_sheet->setCellValue('D4', 'Tanggal Ajar');
                $detail_sheet->setCellValue('E4', 'Waktu Mulai'); // Ganti jadi Waktu Mulai
                $detail_sheet->setCellValue('F4', 'Waktu Selesai'); // Ganti jadi Waktu Selesai
                $detail_sheet->setCellValue('G4', 'Durasi (Menit)'); // Kolom Durasi
                $detail_sheet->setCellValue('H4', 'Status Absen'); // Kolom Status Absen

                // Styling Header untuk Sheet 2
                $detail_sheet->getStyle('A4:H4')->applyFromArray($headerStyle); // Use the same header style

                // Data Tabel untuk Sheet 2
                $row_num_detail = 5;
                foreach ($detail_data_for_excel as $data) {
                    $detail_sheet->setCellValue('A' . $row_num_detail, htmlspecialchars_decode($data['nama_guru']));
                    $detail_sheet->setCellValue('B' . $row_num_detail, htmlspecialchars_decode($data['nama_mapel']));
                    $detail_sheet->setCellValue('C' . $row_num_detail, htmlspecialchars_decode($data['nama_kelas']));
                    $detail_sheet->setCellValue('D' . $row_num_detail, $data['tanggal_ajar']);
                    $detail_sheet->setCellValue('E' . $row_num_detail, $data['waktu_mulai']);
                    $detail_sheet->setCellValue('F' . $row_num_detail, $data['waktu_selesai']);
                    $detail_sheet->setCellValue('G' . $row_num_detail, $data['durasi_menit']); // Tampilkan durasi menit
                    $detail_sheet->setCellValue('H' . $row_num_detail, $data['status_absen']);

                    // Styling border untuk data
                    $detail_sheet->getStyle('A' . $row_num_detail . ':H' . $row_num_detail)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    $row_num_detail++;
                }

                // Auto size columns untuk Sheet 2
                foreach (range('A', 'H') as $column) {
                    $detail_sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Set sheet aktif kembali ke yang pertama untuk tampilan awal
                $spreadsheet->setActiveSheetIndex(0);

                $filename = "rekap_gaji_guru_" . str_replace('/', '-', $selected_academic_year_str) . "_Bulan_" . $months[$selected_month] . ".xlsx"; // Ganti nama file

                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: max-age=0'); // No cache

                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
                exit();

            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                error_log("Error generating XLSX: " . $e->getMessage());
                // Tampilkan pesan error di browser (gunakan JavaScript alert karena ini adalah PHP)
                echo "<script>alert('Terjadi kesalahan saat membuat file Excel: " . addslashes($e->getMessage()) . ".');</script>";
                // Lanjutkan ke fallback CSV jika terjadi error pada PHPSpreadsheet
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

<!-- Konten Rekap Transport Guru -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
    <h1 class="text-3xl font-semibold text-gray-800 mb-4 sm:mb-0">Rekap Bisyaroh Transport Guru</h1>
</div>

<div class="form-container">
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Bulan:</label>
                <select id="month" name="month" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                    <?php
                    // $months sudah didefinisikan di awal
                    foreach ($months as $num => $name) {
                        $selected = ($num == $selected_month) ? 'selected' : '';
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
                <?php if (isset($rekap_data) && !empty($rekap_data)): ?>
                <button type="submit" name="export_excel" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
                    <i class="fas fa-file-excel mr-2"></i> Export Excel
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if (isset($rekap_data) && !empty($rekap_data)): ?>
<div class="table-container mb-8">
    <h3 class="text-xl font-semibold text-gray-800 mb-4 p-4">Rekap Bisyaroh Transport Guru Mengajar Bulan <?= $months[$selected_month] ?> Tahun Ajaran <?= $selected_academic_year_str ?></h3>
    <p class="text-gray-600 mb-4 p-4">Nominal Bisyaroh per Pertemuan: **Rp <?= number_format($nominal_gaji_per_pertemuan, 0, ',', '.') ?>**</p>
    <?php if (!empty($rekap_data)): ?>
    <table>
        <thead>
            <tr>
                <th>Nama Guru</th>
                <th>Jumlah Pertemuan</th>
                <th>Total Jam Mengajar (Info)</th>
                <th>Total Bisyaroh (Rp)</th>
                <th>Mata Pelajaran Diampu</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rekap_data as $data): ?>
            <tr>
                <td><?= htmlspecialchars($data['nama_guru']) ?></td>
                <td><?= number_format($data['jumlah_pertemuan'], 0) ?></td>
                <td><?= htmlspecialchars($data['total_jam_mengajar_formatted']) ?></td>
                <td><?= 'Rp ' . number_format($data['total_gaji'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars(implode(', ', $data['unique_subjects'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="p-4 text-gray-600 text-center">Tidak ada data rekap untuk bulan dan tahun ajaran yang dipilih. Pastikan data mengajar guru tersedia di tabel `absensi_mapel_guru` dan `jadwal_pelajaran` untuk periode ini.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
// Menyertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
