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

// Inisialisasi variabel
$pdo = null;
$rekap_data_bulanan = []; // Data rekap per bulan
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
 * Perhitungan gaji bulanan didasarkan pada jumlah slot jadwal unik guru.
 *
 * @param string $semester_name Nama Semester (misal: "Ganjil", "Genap")
 * @param string $academic_year_str String Tahun Ajaran (misal: "2023/2024")
 * @param int $selected_month_num Nomor bulan yang dipilih (1-12)
 * @return array Array data rekap bulanan guru
 */
function getGuruMonthlyTeachingData($semester_name, $academic_year_str, $selected_month_num) {
    global $pdo; // Menggunakan koneksi PDO dari global scope
    global $months; // Menggunakan array months dari global scope

    $rekap_bulanan = [];

    if (!$pdo) {
        error_log("PDO connection not available in getGuruMonthlyTeachingData.");
        return ['rekap' => []];
    }

    // Debugging: Log input parameters
    error_log("getGuruMonthlyTeachingData called with: Semester Name = {$semester_name}, Academic Year String = {$academic_year_str}, Month Num = {$selected_month_num}");

    // Query SQL untuk mendapatkan jadwal pelajaran yang relevan dengan TAHUN AJARAN dan SEMESTER (nama string) yang dipilih.
    // Menggunakan `GROUP BY` untuk menjumlahkan `jumlah_jam` per guru dan mengumpulkan mata pelajaran.
    $sql = "
        SELECT
            jp.guru_id,
            g.nama_lengkap,
            g.gaji_per_pertemuan,
            SUM(jp.jumlah_jam) AS total_pertemuan,
            GROUP_CONCAT(DISTINCT mp.nama_mapel SEPARATOR ', ') AS unique_subjects
        FROM
            jadwal_pelajaran jp
        JOIN
            guru g ON jp.guru_id = g.id
        JOIN
            mata_pelajaran mp ON jp.mapel_id = mp.id
        WHERE
            jp.tahun_ajaran = :tahun_ajaran_str_filter AND jp.semester = :semester_str_filter
        GROUP BY
            jp.guru_id
        ORDER BY
            g.nama_lengkap;
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':tahun_ajaran_str_filter', $academic_year_str, PDO::PARAM_STR);
        $stmt->bindParam(':semester_str_filter', $semester_name, PDO::PARAM_STR);
        $stmt->execute();
        $rekap_guru_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debugging: Log number of rows fetched
        error_log("Fetched " . count($rekap_guru_data) . " rekap rows for academic_year: '{$academic_year_str}' and semester: '{$semester_name}'");

        $month_start_date = new DateTime(date("Y-{$selected_month_num}-01"));
        $month_key = $month_start_date->format('Y-m-01');

        foreach ($rekap_guru_data as $data) {
            $guru_id = $data['guru_id'];
            $gaji_per_pertemuan_guru = (float)$data['gaji_per_pertemuan'];
            $jumlah_pertemuan_rekap = (int)$data['total_pertemuan'];
            $total_gaji_rekap = $jumlah_pertemuan_rekap * $gaji_per_pertemuan_guru;
            $unique_subjects_array = explode(', ', $data['unique_subjects']);

            $rekap_bulanan[$guru_id][$month_key] = [
                'nama_guru' => $data['nama_lengkap'],
                'month_start_date' => $month_start_date->format('Y-m-d'),
                'month_end_date' => (new DateTime($month_start_date->format('Y-m-t')))->format('Y-m-d'),
                'jumlah_pertemuan' => $jumlah_pertemuan_rekap,
                'total_gaji' => $total_gaji_rekap,
                'unique_subjects' => $unique_subjects_array,
            ];
        }

    } catch (PDOException $e) {
        error_log("ERROR: Error in getGuruMonthlyTeachingData (fetching rekap data): " . $e->getMessage());
        return ['rekap' => []];
    }

    return ['rekap' => $rekap_bulanan];
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
    
        // Jika tombol export_excel ditekan, generate XLSX
        if (isset($_POST['export_excel']) && !empty($rekap_data_bulanan)) {
            try {
                $spreadsheet = new Spreadsheet();

                // --- Sheet 1: Rekap Gaji Bulanan Guru ---
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Rekap Gaji Bulanan');

                // Set Judul
                $sheet->setCellValue('A1', 'Rekap Bisyaroh Guru Bulanan (Berdasarkan Jam Mengajar)');
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
                $sheet->setCellValue('E4', 'Total Bisyaroh (Rp)');
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
    <h3 class="text-xl font-semibold text-gray-800 mb-4 p-4">Rekap Bisyaroh Guru Mengajar Bulan <?= $months[$selected_month] ?> Semester <?= $semesters[$selected_semester_id] ?> Tahun Ajaran <?= $selected_academic_year_str ?> (Berdasarkan Jumlah Jam)</h3>
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