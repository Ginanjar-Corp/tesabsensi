<?php
// project-root/staff/manage_siswa.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Kelas.php'; // Diperlukan untuk daftar kelas

// --- PHP SPREADSHEET INTEGRATION START ---
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
// --- PHP SPREADSHEET INTEGRATION END ---

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$siswa_model = null;
$kelas_model = null;
$pdo = null;

$list_siswa = [];
$all_kelas = [];
$siswa_to_edit = null; // Untuk mode edit

// Parameter pencarian dan filter
$search_query = $_GET['search'] ?? '';
$status_filter = $_GET['status_filter'] ?? 'Aktif'; // 'Aktif', 'Pindah', 'Lulus', atau kosong untuk semua

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $siswa_model = new Siswa($pdo);
    $kelas_model = new Kelas($pdo);

    $all_kelas = $kelas_model->getAll(); // Ambil semua kelas untuk dropdown

    // Handle form submission (Add/Edit Siswa, Import)
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'import') {
            // Handle file upload for import
            if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == UPLOAD_ERR_OK) {
                $file_extension = pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['xls', 'xlsx'];

                if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                    set_flash('error', 'Format file tidak didukung. Harap unggah file Excel (.xls atau .xlsx).');
                    redirect(ROOT_URL . 'staff/manage_siswa.php');
                }

                $inputFileName = $_FILES['excel_file']['tmp_name'];

                try {
                    $spreadsheet = IOFactory::load($inputFileName);
                    $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

                    $imported_count = 0;
                    $skipped_count = 0;
                    $updated_count = 0;
                    $errors = [];

                    // Asumsi baris pertama adalah header
                    $header = array_map('trim', $sheetData[1]);
                    $expected_headers = [
                        'Nama Lengkap', 'Kelas ID', 'NISN', 'NIS', 'RFID/QR Tag',
                        'Username', 'Password', // Tambah header username dan password
                        'Tempat Lahir', 'Tanggal Lahir (YYYY-MM-DD)', 'Jenis Kelamin (L/P)', 'Alamat',
                        'No. Telp Orang Tua', 'Email Orang Tua', 'Status Siswa (Aktif/Pindah/Lulus)'
                    ];

                    // Validasi header
                    $missing_headers = array_diff($expected_headers, $header);
                    if (!empty($missing_headers)) {
                        set_flash('error', 'Header kolom tidak lengkap atau tidak sesuai. Pastikan ada kolom: ' . implode(', ', $expected_headers));
                        redirect(ROOT_URL . 'staff/manage_siswa.php');
                    }

                    // Buat mapping kolom dari header
                    $col_map = [];
                    foreach ($expected_headers as $expected_h) {
                        $col_map[$expected_h] = array_search($expected_h, $header);
                        if ($col_map[$expected_h] === false) {
                            throw new Exception("Kolom '$expected_h' tidak ditemukan di file Excel.");
                        }
                    }

                    for ($i = 2; $i <= count($sheetData); $i++) {
                        $row = $sheetData[$i];

                        $nama_lengkap = trim($row[$col_map['Nama Lengkap']] ?? '');
                        $kelas_id = (int)($row[$col_map['Kelas ID']] ?? 0);
                        $nisn = trim($row[$col_map['NISN']] ?? null);
                        $nis = trim($row[$col_map['NIS']] ?? null);
                        $rfid_tag = trim($row[$col_map['RFID/QR Tag']] ?? null);
                        $username = trim($row[$col_map['Username']] ?? null); // Ambil username
                        $password = trim($row[$col_map['Password']] ?? null); // Ambil password
                        $tempat_lahir = trim($row[$col_map['Tempat Lahir']] ?? null);
                        $tanggal_lahir_excel = trim($row[$col_map['Tanggal Lahir (YYYY-MM-DD)']] ?? '');
                        $jenis_kelamin = trim($row[$col_map['Jenis Kelamin (L/P)']] ?? null);
                        $alamat = trim($row[$col_map['Alamat']] ?? null);
                        $no_telp_ortu = trim($row[$col_map['No. Telp Orang Tua']] ?? null);
                        $email_ortu = trim($row[$col_map['Email Orang Tua']] ?? null);
                        $status_siswa_str = trim($row[$col_map['Status Siswa (Aktif/Pindah/Lulus)']] ?? 'Aktif');

                        // Validasi dasar
                        if (empty($nama_lengkap) || $kelas_id <= 0) {
                            $errors[] = "Baris " . $i . ": Nama Lengkap atau Kelas ID tidak valid. Baris dilewati.";
                            $skipped_count++;
                            continue;
                        }

                        // Konversi tanggal lahir dari format Excel jika numerik
                        $tanggal_lahir = null;
                        if (is_numeric($tanggal_lahir_excel) && Date::isExcelDate($tanggal_lahir_excel)) {
                            try {
                                $tanggal_lahir = Date::excelToDateTimeObject($tanggal_lahir_excel)->format('Y-m-d');
                            } catch (Exception $e) {
                                $errors[] = "Baris " . $i . ": Format Tanggal Lahir tidak valid ('" . htmlspecialchars($tanggal_lahir_excel) . "'). Menggunakan NULL.";
                                $tanggal_lahir = null;
                            }
                        } else if (!empty($tanggal_lahir_excel)) {
                            // Coba parse sebagai string tanggal biasa
                            try {
                                $dt = new DateTime($tanggal_lahir_excel);
                                $tanggal_lahir = $dt->format('Y-m-d');
                            } catch (Exception $e) {
                                $errors[] = "Baris " . $i . ": Format Tanggal Lahir tidak valid ('" . htmlspecialchars($tanggal_lahir_excel) . "'). Menggunakan NULL.";
                                $tanggal_lahir = null;
                            }
                        }

                        // Konversi jenis kelamin
                        $jenis_kelamin = strtoupper(substr($jenis_kelamin, 0, 1)); // Ambil huruf pertama (L/P)

                        // Konversi status siswa
                        $status_siswa = 'Aktif'; // Default
                        $valid_statuses = ['Aktif', 'Pindah', 'Lulus'];
                        if (in_array($status_siswa_str, $valid_statuses)) {
                            $status_siswa = $status_siswa_str;
                        } else {
                            $errors[] = "Baris " . $i . ": Status Siswa tidak valid ('" . htmlspecialchars($status_siswa_str) . "'). Menggunakan 'Aktif'.";
                        }


                        $siswa_data = [
                            'nama_lengkap' => $nama_lengkap,
                            'kelas_id' => $kelas_id,
                            'nisn' => !empty($nisn) ? $nisn : null,
                            'nis' => !empty($nis) ? $nis : null,
                            'rfid_tag' => !empty($rfid_tag) ? $rfid_tag : null,
                            'username' => !empty($username) ? $username : null, // Tambah username
                            'password' => !empty($password) ? $password : null, // Tambah password (akan di-hash di model)
                            'tempat_lahir' => !empty($tempat_lahir) ? $tempat_lahir : null,
                            'tanggal_lahir' => $tanggal_lahir,
                            'jenis_kelamin' => !empty($jenis_kelamin) ? $jenis_kelamin : null,
                            'alamat' => !empty($alamat) ? $alamat : null,
                            'no_telp_ortu' => !empty($no_telp_ortu) ? $no_telp_ortu : null,
                            'email_ortu' => !empty($email_ortu) ? $email_ortu : null,
                            'status_siswa' => $status_siswa
                        ];

                        // Cek apakah siswa sudah ada berdasarkan NISN, NIS, atau RFID Tag, atau Username
                        $existing_siswa = null;
                        if (!empty($nisn)) {
                            $existing_siswa = $siswa_model->findByNisn($nisn);
                        }
                        if (!$existing_siswa && !empty($nis)) {
                            $existing_siswa = $siswa_model->findByNis($nis);
                        }
                        if (!$existing_siswa && !empty($rfid_tag)) {
                            $existing_siswa = $siswa_model->findByRfidTag($rfid_tag);
                        }
                        // Tambahkan pengecekan username unik
                        if (!$existing_siswa && !empty($username)) {
                            // Perlu metode findByUsername di model Siswa jika ingin memeriksa unik via username
                            // Untuk saat ini, kita akan asumsikan unique constraint di DB cukup atau username akan diupdate jika siswa ditemukan
                        }


                        if ($existing_siswa) {
                            // Update data siswa yang sudah ada
                            if ($siswa_model->update($existing_siswa['id'], $siswa_data)) {
                                $updated_count++;
                            } else {
                                $errors[] = "Baris " . $i . ": Gagal memperbarui siswa '" . htmlspecialchars($nama_lengkap) . "'.";
                            }
                        } else {
                            // Tambah siswa baru
                            if ($siswa_model->create($siswa_data)) {
                                $imported_count++;
                            } else {
                                $errors[] = "Baris " . $i . ": Gagal menambahkan siswa '" . htmlspecialchars($nama_lengkap) . "'. NISN, NIS, RFID, atau Username mungkin sudah terdaftar/unik.";
                            }
                        }
                    }
                    $message = "Impor selesai. Ditambahkan: {$imported_count}, Diperbarui: {$updated_count}, Dilewati: {$skipped_count}.";
                    if (!empty($errors)) {
                        $message .= "<br>Detail Error:<br>" . implode("<br>", $errors);
                        set_flash('error', $message);
                    } else {
                        set_flash('success', $message);
                    }

                } catch (Exception $e) {
                    set_flash('error', 'Terjadi kesalahan saat membaca file Excel: ' . $e->getMessage());
                }
            } else {
                set_flash('error', 'Gagal mengunggah file. Pastikan file dipilih dan ukurannya tidak melebihi batas server.');
            }
            redirect(ROOT_URL . 'staff/manage_siswa.php');
        } else {
            // Existing logic for Add/Edit Siswa
            $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
            $kelas_id = (int)($_POST['kelas_id'] ?? 0);
            $nisn = trim($_POST['nisn'] ?? null);
            $nis = trim($_POST['nis'] ?? null);
            $rfid_tag = trim($_POST['rfid_tag'] ?? null);
            $username = trim($_POST['username'] ?? null); // Ambil username
            $password = trim($_POST['password'] ?? null); // Ambil password
            $tempat_lahir = trim($_POST['tempat_lahir'] ?? null);
            $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? null);
            $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? null);
            $alamat = trim($_POST['alamat'] ?? null);
            $no_telp_ortu = trim($_POST['no_telp_ortu'] ?? null);
            $email_ortu = trim($_POST['email_ortu'] ?? null);
            $status_siswa = trim($_POST['status_siswa'] ?? 'Aktif');

            // Validasi dasar
            if (empty($nama_lengkap) || empty($kelas_id)) {
                set_flash('error', 'Nama lengkap dan kelas harus diisi.');
                redirect(ROOT_URL . 'staff/manage_siswa.php');
            }

            $data = [
                'nama_lengkap' => $nama_lengkap,
                'kelas_id' => $kelas_id,
                'nisn' => $nisn,
                'nis' => $nis,
                'rfid_tag' => $rfid_tag,
                'username' => $username, // Tambah username
                'password' => $password, // Tambah password (akan di-hash di model)
                'tempat_lahir' => $tempat_lahir,
                'tanggal_lahir' => $tanggal_lahir,
                'jenis_kelamin' => $jenis_kelamin,
                'alamat' => $alamat,
                'no_telp_ortu' => $no_telp_ortu,
                'email_ortu' => $email_ortu,
                'status_siswa' => $status_siswa
            ];

            if ($action === 'add') {
                if ($siswa_model->create($data)) {
                    set_flash('success', 'Siswa berhasil ditambahkan.');
                } else {
                    set_flash('error', 'Gagal menambahkan siswa. NISN, NIS, RFID, atau Username mungkin sudah terdaftar/unik.');
                }
            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0 && $siswa_model->update($id, $data)) {
                    set_flash('success', 'Data siswa berhasil diperbarui.');
                } else {
                    set_flash('error', 'Gagal memperbarui data siswa atau ID tidak valid. NISN, NIS, RFID, atau Username mungkin sudah terdaftar/unik.');
                }
            }
            redirect(ROOT_URL . 'staff/manage_siswa.php');
        }
    }
    // Handle Delete or Edit request via GET
    elseif (isset($_GET['action'])) {
        $action = $_GET['action'];
        $id = (int)($_GET['id'] ?? 0);

        if ($id > 0) {
            if ($action === 'delete') {
                if ($siswa_model->delete($id)) { // Metode delete tetap hard delete
                    set_flash('success', 'Siswa berhasil dihapus.');
                } else {
                    set_flash('error', 'Gagal menghapus siswa. Pastikan tidak ada data absensi terkait.');
                }
                redirect(ROOT_URL . 'staff/manage_siswa.php');
            } elseif ($action === 'edit_form') {
                $siswa_to_edit = $siswa_model->findById($id);
                if (!$siswa_to_edit) {
                    set_flash('error', 'Siswa tidak ditemukan untuk diedit.');
                    redirect(ROOT_URL . 'staff/manage_siswa.php');
                }
            }
        }
    }

    // Ambil semua siswa untuk ditampilkan dengan filter
    $list_siswa = $siswa_model->getFilteredSiswa($search_query, $status_filter);

} catch (PDOException $e) {
    error_log("Manajemen Siswa Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Manajemen Siswa General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header
require_once __DIR__ . '/../components/header_staff.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Siswa</h1>

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

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4"><?php echo $siswa_to_edit ? 'Edit Data Siswa' : 'Tambah Siswa Baru'; ?></h2>
        <form action="" method="POST">
            <input type="hidden" name="action" value="<?php echo $siswa_to_edit ? 'edit' : 'add'; ?>">
            <?php if ($siswa_to_edit): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($siswa_to_edit['id']); ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="nama_lengkap" class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap:</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($siswa_to_edit['nama_lengkap'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="kelas_id" class="block text-gray-700 text-sm font-bold mb-2">Kelas:</label>
                    <select id="kelas_id" name="kelas_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($all_kelas as $kelas): ?>
                            <option value="<?php echo htmlspecialchars($kelas['id']); ?>" <?php echo (($siswa_to_edit['kelas_id'] ?? '') == $kelas['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="nisn" class="block text-gray-700 text-sm font-bold mb-2">NISN:</label>
                    <input type="text" id="nisn" name="nisn" value="<?php echo htmlspecialchars($siswa_to_edit['nisn'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="nis" class="block text-gray-700 text-sm font-bold mb-2">NIS:</label>
                    <input type="text" id="nis" name="nis" value="<?php echo htmlspecialchars($siswa_to_edit['nis'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="rfid_tag" class="block text-gray-700 text-sm font-bold mb-2">RFID/QR Tag:</label>
                    <input type="text" id="rfid_tag" name="rfid_tag" value="<?php echo htmlspecialchars($siswa_to_edit['rfid_tag'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Pindai atau masukkan manual">
                </div>
                <div>
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username Orang Tua:</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($siswa_to_edit['username'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password Orang Tua (kosongkan jika tidak berubah):</label>
                    <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <?php if ($siswa_to_edit): ?>
                        <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengubah password.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="tempat_lahir" class="block text-gray-700 text-sm font-bold mb-2">Tempat Lahir:</label>
                    <input type="text" id="tempat_lahir" name="tempat_lahir" value="<?php echo htmlspecialchars($siswa_to_edit['tempat_lahir'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="tanggal_lahir" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Lahir:</label>
                    <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo htmlspecialchars($siswa_to_edit['tanggal_lahir'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="jenis_kelamin" class="block text-gray-700 text-sm font-bold mb-2">Jenis Kelamin:</label>
                    <select id="jenis_kelamin" name="jenis_kelamin" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">-- Pilih --</option>
                        <option value="L" <?php echo (($siswa_to_edit['jenis_kelamin'] ?? '') == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                        <option value="P" <?php echo (($siswa_to_edit['jenis_kelamin'] ?? '') == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="alamat" class="block text-gray-700 text-sm font-bold mb-2">Alamat:</label>
                    <textarea id="alamat" name="alamat" rows="2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($siswa_to_edit['alamat'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label for="no_telp_ortu" class="block text-gray-700 text-sm font-bold mb-2">No. Telp Orang Tua:</label>
                    <input type="text" id="no_telp_ortu" name="no_telp_ortu" value="<?php echo htmlspecialchars($siswa_to_edit['no_telp_ortu'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="email_ortu" class="block text-gray-700 text-sm font-bold mb-2">Email Orang Tua:</label>
                    <input type="email" id="email_ortu" name="email_ortu" value="<?php echo htmlspecialchars($siswa_to_edit['email_ortu'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="md:col-span-2">
                    <label for="status_siswa" class="block text-gray-700 text-sm font-bold mb-2">Status Siswa:</label>
                    <select id="status_siswa" name="status_siswa" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="Aktif" <?php echo (($siswa_to_edit['status_siswa'] ?? 'Aktif') == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                        <option value="Pindah" <?php echo (($siswa_to_edit['status_siswa'] ?? '') == 'Pindah') ? 'selected' : ''; ?>>Pindah</option>
                        <option value="Lulus" <?php echo (($siswa_to_edit['status_siswa'] ?? '') == 'Lulus') ? 'selected' : ''; ?>>Lulus</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Mengubah status menjadi 'Pindah' atau 'Lulus' akan membuat siswa tidak aktif dalam sistem absensi.</p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    <?php echo $siswa_to_edit ? 'Update Siswa' : 'Tambah Siswa'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Form Import Siswa -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold mb-4 text-gray-700">Impor Data Siswa dari Excel</h2>
        <form action="manage_siswa.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">
            <div class="mb-4">
                <label for="excel_file" class="block text-gray-700 text-sm font-bold mb-2">Pilih File Excel (.xls, .xlsx):</label>
                <input type="file" id="excel_file" name="excel_file" accept=".xls,.xlsx" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                <p class="text-xs text-gray-500 mt-1">Pastikan file Excel memiliki kolom: Nama Lengkap, Kelas ID, NISN, NIS, RFID/QR Tag, Username, Password, Tempat Lahir, Tanggal Lahir (YYYY-MM-DD), Jenis Kelamin (L/P), Alamat, No. Telp Orang Tua, Email Orang Tua, Status Siswa (Aktif/Pindah/Lulus).</p>
            </div>
            <div class="flex items-center gap-4">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    <i class="fas fa-file-excel mr-2"></i> Impor Siswa
                </button>
                <a href="download_siswa_template.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    <i class="fas fa-download mr-2"></i> Download Template
                </a>
            </div>
        </form>
    </div>


    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Siswa</h2>

        <!-- Form Pencarian dan Filter -->
        <form action="" method="GET" class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="search" class="block text-gray-700 text-sm font-bold mb-2">Cari NISN / Nama / Username:</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Cari NISN, Nama, atau Username">
                </div>
                <div>
                    <label for="status_filter" class="block text-gray-700 text-sm font-bold mb-2">Filter Status:</label>
                    <select id="status_filter" name="status_filter" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Semua Status</option>
                        <option value="Aktif" <?php echo ($status_filter == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                        <option value="Pindah" <?php echo ($status_filter == 'Pindah') ? 'selected' : ''; ?>>Pindah</option>
                        <option value="Lulus" <?php echo ($status_filter == 'Lulus') ? 'selected' : ''; ?>>Lulus</option>
                    </select>
                </div>
                <div class="flex justify-start items-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                        Terapkan Filter
                    </button>
                </div>
            </div>
        </form>

        <?php if (empty($list_siswa)): ?>
            <p class="text-gray-600">Tidak ada data siswa yang ditemukan dengan kriteria ini.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">NISN</th>
                            <th class="py-3 px-6 text-left">Username</th>
                            <th class="py-3 px-6 text-left">Nama Lengkap</th>
                            <th class="py-3 px-6 text-left">Kelas</th>
                            <th class="py-3 px-6 text-left">RFID/QR Tag</th>
                            <th class="py-3 px-6 text-left">Status</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($list_siswa as $siswa): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['username'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['nama_kelas']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['rfid_tag'] ?? '-'); ?></td>
                                <td class="py-3 px-6">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold
                                        <?php
                                            if ($siswa['status_siswa'] == 'Aktif') echo 'bg-green-200 text-green-800';
                                            elseif ($siswa['status_siswa'] == 'Pindah') echo 'bg-yellow-200 text-yellow-800';
                                            elseif ($siswa['status_siswa'] == 'Lulus') echo 'bg-purple-200 text-purple-800';
                                            else echo 'bg-gray-200 text-gray-800'; // Fallback
                                        ?>">
                                        <?php echo htmlspecialchars($siswa['status_siswa']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <a href="<?php echo ROOT_URL; ?>staff/manage_siswa.php?action=edit_form&id=<?php echo htmlspecialchars($siswa['id']); ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded-lg text-xs">Edit</a>
                                    <a href="<?php echo ROOT_URL; ?>staff/manage_siswa.php?action=delete&id=<?php echo htmlspecialchars($siswa['id']); ?>" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-lg text-xs" onclick="return confirm('Apakah Anda yakin ingin menghapus siswa ini?');">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
