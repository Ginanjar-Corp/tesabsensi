<?php
// project-root/admin/manage_guru.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Guru.php';

// --- PHP SPREADSHEET INTEGRATION START ---
// PASTIKAN FILE INI ADA DAN PATHNYA BENAR SETELAH INSTALASI COMPOSER/PHPSPREADSHEET
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
// --- PHP SPREADSHEET INTEGRATION END ---

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$guru_model = null;
$pdo = null;
$all_guru = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $guru_model = new Guru($pdo);

    // Handle form submission for Add/Edit/Delete
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];

            if ($action == 'import') {
                // Handle file upload for import
                if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == UPLOAD_ERR_OK) {
                    $file_extension = pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION);
                    $allowed_extensions = ['xls', 'xlsx'];

                    if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                        set_flash('error', 'Format file tidak didukung. Harap unggah file Excel (.xls atau .xlsx).');
                        redirect(ROOT_URL . 'admin/manage_guru.php');
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
                            'Nama Lengkap', 'NIP', 'RFID Tag', 'Tempat Lahir',
                            'Tanggal Lahir', 'Jenis Kelamin', 'Alamat', 'No. Telepon',
                            'Email', 'Gaji Per Pertemuan', 'Aktif',
                            'Jabatan', 'Tunjangan Jabatan', 'Kegiatan Lainnya', 'Tunjangan Kegiatan Lainnya',
                            // --- NEW HEADERS ---
                            'Transport Jabatan', 'Hari Piket', 'Transport Piket', 'Tunjangan Sertifikasi' 
                            // ---------------------
                        ];

                        // Validasi header
                        $missing_headers = array_diff($expected_headers, $header);
                        if (!empty($missing_headers)) {
                            set_flash('error', 'Header kolom tidak lengkap atau tidak sesuai. Pastikan ada kolom: ' . implode(', ', $expected_headers));
                            redirect(ROOT_URL . 'admin/manage_guru.php');
                        }

                        // Buat mapping kolom dari header
                        $col_map = [];
                        foreach ($expected_headers as $expected_h) {
                            $col_map[$expected_h] = array_search($expected_h, $header);
                            if ($col_map[$expected_h] === false) {
                                // Ini seharusnya sudah ditangani oleh missing_headers, tapi sebagai fallback
                                throw new Exception("Kolom '$expected_h' tidak ditemukan di file Excel.");
                            }
                        }

                        for ($i = 2; $i <= count($sheetData); $i++) {
                            $row = $sheetData[$i];

                            $nama_lengkap = trim($row[$col_map['Nama Lengkap']] ?? '');
                            $nip = trim($row[$col_map['NIP']] ?? '');
                            $rfid_tag = trim($row[$col_map['RFID Tag']] ?? '');
                            $tempat_lahir = trim($row[$col_map['Tempat Lahir']] ?? '');
                            $tanggal_lahir_excel = trim($row[$col_map['Tanggal Lahir']] ?? '');
                            $jenis_kelamin = trim($row[$col_map['Jenis Kelamin']] ?? '');
                            $alamat = trim($row[$col_map['Alamat']] ?? '');
                            $no_telp = trim($row[$col_map['No. Telepon']] ?? '');
                            $email = trim($row[$col_map['Email']] ?? '');
                            $gaji_per_pertemuan_raw = trim($row[$col_map['Gaji Per Pertemuan']] ?? '0');
                            $is_active_str = trim($row[$col_map['Aktif']] ?? 'Ya'); // Asumsi default 'Ya'
                            $jabatan = trim($row[$col_map['Jabatan']] ?? '');
                            $tunjangan_jabatan_raw = trim($row[$col_map['Tunjangan Jabatan']] ?? '0');
                            // NOTE: Assuming 'Kegiatan Lainnya' corresponds to 'transport' and 'Tunjangan Kegiatan Lainnya' to 'tunjangan_transport' from the original code/file. 
                            $transport = trim($row[$col_map['Kegiatan Lainnya']] ?? '');
                            $tunjangan_transport_raw = trim($row[$col_map['Tunjangan Kegiatan Lainnya']] ?? '0');
                            
                            // --- NEW FIELDS IMPORT ---
                            $transport_jabatan_raw = trim($row[$col_map['Transport Jabatan']] ?? '0');
                            $hari_piket = trim($row[$col_map['Hari Piket']] ?? '');
                            $transport_piket_raw = trim($row[$col_map['Transport Piket']] ?? '0');
                            $tunjangan_sertifikasi_raw = trim($row[$col_map['Tunjangan Sertifikasi']] ?? '0');
                            // -------------------------

                            // Validasi data
                            if (empty($nama_lengkap)) {
                                $errors[] = "Baris " . $i . ": Nama Lengkap tidak boleh kosong. Baris dilewati.";
                                $skipped_count++;
                                continue;
                            }

                            // Konversi tanggal lahir dari format Excel jika numerik
                            $tanggal_lahir = null;
                            if (is_numeric($tanggal_lahir_excel) && Date::is
                                ($tanggal_lahir_excel)) {
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

                            // Bersihkan dan konversi nilai Rupiah/float
                            $gaji_per_pertemuan = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $gaji_per_pertemuan_raw));
                            $tunjangan_jabatan = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $tunjangan_jabatan_raw));
                            $tunjangan_transport = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $tunjangan_transport_raw));
                            
                            // --- NEW FIELDS CONVERSION ---
                            $transport_jabatan = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $transport_jabatan_raw));
                            $transport_piket = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $transport_piket_raw));
                            $tunjangan_sertifikasi = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $tunjangan_sertifikasi_raw));
                            // -----------------------------

                            // Konversi status aktif
                            $is_active = (strtolower($is_active_str) == 'ya' || strtolower($is_active_str) == 'true' || $is_active_str == '1') ? 1 : 0;

                            $guru_data = [
                                'nama_lengkap' => $nama_lengkap,
                                'nip' => !empty($nip) ? $nip : null,
                                'rfid_tag' => !empty($rfid_tag) ? $rfid_tag : null,
                                'tempat_lahir' => !empty($tempat_lahir) ? $tempat_lahir : null,
                                'tanggal_lahir' => $tanggal_lahir,
                                'jenis_kelamin' => !empty($jenis_kelamin) ? $jenis_kelamin : null,
                                'alamat' => !empty($alamat) ? $alamat : null,
                                'no_telp' => !empty($no_telp) ? $no_telp : null,
                                'email' => !empty($email) ? $email : null,
                                'gaji_per_pertemuan' => $gaji_per_pertemuan,
                                'is_active' => $is_active,
                                'jabatan' => !empty($jabatan) ? $jabatan : null,
                                'tunjangan_jabatan' => $tunjangan_jabatan,
                                'transport' => !empty($transport) ? $transport : null,
                                'tunjangan_transport' => $tunjangan_transport,
                                // --- NEW FIELDS DATA ---
                                'transport_jabatan' => $transport_jabatan,
                                'hari_piket' => !empty($hari_piket) ? $hari_piket : null,
                                'transport_piket' => $transport_piket,
                                'tunjangan_sertifikasi' => $tunjangan_sertifikasi
                                // -----------------------
                            ];

                            // Cek apakah guru sudah ada berdasarkan NIP atau RFID Tag
                            $existing_guru = null;
                            if (!empty($nip)) {
                                $existing_guru = $guru_model->findByNip($nip);
                            }
                            if (!$existing_guru && !empty($rfid_tag)) {
                                $existing_guru = $guru_model->findByRfidTag($rfid_tag);
                            }

                            if ($existing_guru) {
                                // Update data guru yang sudah ada
                                if ($guru_model->update($existing_guru['id'], $guru_data)) {
                                    $updated_count++;
                                } else {
                                    $errors[] = "Baris " . $i . ": Gagal memperbarui guru '" . htmlspecialchars($nama_lengkap) . "'.";
                                }
                            } else {
                                // Tambah guru baru
                                if ($guru_model->add($guru_data)) {
                                    $imported_count++;
                                } else {
                                    $errors[] = "Baris " . $i . ": Gagal menambahkan guru '" . htmlspecialchars($nama_lengkap) . "'.";
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
                redirect(ROOT_URL . 'admin/manage_guru.php');
            }

            // Common fields for add/edit (existing logic)
            $id = (int)($_POST['id'] ?? 0);
            $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
            $nip = trim($_POST['nip'] ?? '');
            $rfid_tag = trim($_POST['rfid_tag'] ?? '');
            $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
            $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
            $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $no_telp = trim($_POST['no_telp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            // Ambil gaji_per_pertemuan dari POST dan bersihkan
            $gaji_per_pertemuan = (float)str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['gaji_per_pertemuan'] ?? '0'));
            $jabatan = trim($_POST['jabatan'] ?? null);
            $tunjangan_jabatan = (float)str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['tunjangan_jabatan'] ?? '0'));
            $transport = trim($_POST['transport'] ?? null);
            $tunjangan_transport = (float)str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['tunjangan_transport'] ?? '0'));
            
            // --- NEW FIELDS POST HANDLING ---
            $transport_jabatan = (float)str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['transport_jabatan'] ?? '0'));
            $hari_piket = trim($_POST['hari_piket'] ?? null);
            $transport_piket = (float)str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['transport_piket'] ?? '0'));
            $tunjangan_sertifikasi = (float)str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['tunjangan_sertifikasi'] ?? '0'));
            // --------------------------------
            
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $guru_data = [
                'nama_lengkap' => $nama_lengkap,
                'nip' => !empty($nip) ? $nip : null, // Set null jika kosong
                'rfid_tag' => !empty($rfid_tag) ? $rfid_tag : null, // Set null jika kosong
                'tempat_lahir' => !empty($tempat_lahir) ? $tempat_lahir : null,
                'tanggal_lahir' => !empty($tanggal_lahir) ? $tanggal_lahir : null,
                'jenis_kelamin' => !empty($jenis_kelamin) ? $jenis_kelamin : null,
                'alamat' => !empty($alamat) ? $alamat : null,
                'no_telp' => !empty($no_telp) ? $no_telp : null,
                'email' => !empty($email) ? $email : null,
                'gaji_per_pertemuan' => $gaji_per_pertemuan,
                'jabatan' => $jabatan,
                'tunjangan_jabatan' => $tunjangan_jabatan,
                'transport' => $transport,
                'tunjangan_transport' => $tunjangan_transport,
                // --- NEW FIELDS DATA ARRAY ---
                'transport_jabatan' => $transport_jabatan,
                'hari_piket' => $hari_piket,
                'transport_piket' => $transport_piket,
                'tunjangan_sertifikasi' => $tunjangan_sertifikasi,
                // -----------------------------
                'is_active' => $is_active
            ];

            if ($action == 'add') {
                if ($guru_model->add($guru_data)) {
                    set_flash('success', 'Guru berhasil ditambahkan.');
                } else {
                    set_flash('error', 'Gagal menambahkan guru.');
                }
            } elseif ($action == 'edit') {
                if ($guru_model->update($id, $guru_data)) {
                    set_flash('success', 'Data guru berhasil diperbarui.');
                } else {
                    set_flash('error', 'Gagal memperbarui data guru.');
                }
            } elseif ($action == 'soft_delete') {
                if ($guru_model->softDelete($id)) {
                    set_flash('success', 'Guru berhasil dinonaktifkan.');
                } else {
                    set_flash('error', 'Gagal menonaktifkan guru.');
                }
            } elseif ($action == 'activate') {
                if ($guru_model->activate($id)) {
                    set_flash('success', 'Guru berhasil diaktifkan kembali.');
                } else {
                    set_flash('error', 'Gagal mengaktifkan guru.');
                }
            } elseif ($action == 'hard_delete') {
                // Konfirmasi hard delete (bisa ditambahkan JS konfirmasi di frontend)
                if ($guru_model->hardDelete($id)) {
                    set_flash('success', 'Guru dan data terkait berhasil dihapus permanen.');
                } else {
                    set_flash('error', 'Gagal menghapus guru secara permanen.');
                }
            }
            redirect(ROOT_URL . 'admin/manage_guru.php');
        }
    }

    // Fetch all guru after any POST operations
    $all_guru = $guru_model->getAll();

} catch (Exception $e) {
    error_log("Error in manage_guru.php: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan: ' . $e->getMessage());
    // Redirect to prevent form resubmission on refresh, even if an error occurred
    redirect(ROOT_URL . 'admin/manage_guru.php');
}

// Sertakan header admin
require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">Manajemen Data Guru</h1>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $success_message; ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold mb-4 text-gray-700" id="form-title">Tambah Guru Baru</h2>
        <form id="guruForm" action="manage_guru.php" method="POST">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="id" id="guru-id">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-4">
                    <label for="nama_lengkap" class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap:</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label for="nip" class="block text-gray-700 text-sm font-bold mb-2">NIP (Nomor Induk Pegawai):</label>
                    <input type="text" id="nip" name="nip" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="rfid_tag" class="block text-gray-700 text-sm font-bold mb-2">RFID Tag (Opsional):</label>
                    <input type="text" id="rfid_tag" name="rfid_tag" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Digunakan untuk absensi otomatis.</p>
                </div>
                <div class="mb-4">
                    <label for="tempat_lahir" class="block text-gray-700 text-sm font-bold mb-2">Tempat Lahir:</label>
                    <input type="text" id="tempat_lahir" name="tempat_lahir" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="tanggal_lahir" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Lahir:</label>
                    <input type="date" id="tanggal_lahir" name="tanggal_lahir" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="jenis_kelamin" class="block text-gray-700 text-sm font-bold mb-2">Jenis Kelamin:</label>
                    <select id="jenis_kelamin" name="jenis_kelamin" class="shadow border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                        <option value="">Pilih</option>
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                </div>
                <div class="mb-4 col-span-full">
                    <label for="alamat" class="block text-gray-700 text-sm font-bold mb-2">Alamat:</label>
                    <textarea id="alamat" name="alamat" rows="3" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"></textarea>
                </div>
                <div class="mb-4">
                    <label for="no_telp" class="block text-gray-700 text-sm font-bold mb-2">Nomor Telepon:</label>
                    <input type="text" id="no_telp" name="no_telp" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                    <input type="email" id="email" name="email" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="gaji_per_pertemuan" class="block text-gray-700 text-sm font-bold mb-2">Gaji Per Pertemuan (Rp):</label>
                    <input type="text" id="gaji_per_pertemuan" name="gaji_per_pertemuan" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" value="0" pattern="[0-9.,]+" title="Masukkan angka, gunakan koma untuk desimal jika perlu.">
                    <p class="text-xs text-gray-500 mt-1">Contoh: 50.000 atau 75.500</p>
                </div>
                <div class="mb-4">
                    <label for="jabatan" class="block text-gray-700 text-sm font-bold mb-2">Jabatan:</label>
                    <input type="text" id="jabatan" name="jabatan" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="tunjangan_jabatan" class="block text-gray-700 text-sm font-bold mb-2">Tunjangan Jabatan (Rp):</label>
                    <input type="text" id="tunjangan_jabatan" name="tunjangan_jabatan" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" value="0" pattern="[0-9.,]+" title="Masukkan angka, gunakan koma untuk desimal jika perlu.">
                </div>
                <div class="mb-4">
                    <label for="transport_jabatan" class="block text-gray-700 text-sm font-bold mb-2">Transport Jabatan (Rp):</label>
                    <input type="text" id="transport_jabatan" name="transport_jabatan" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" value="0" pattern="[0-9.,]+" title="Masukkan angka, gunakan koma untuk desimal jika perlu.">
                </div>
                <div class="mb-4 col-span-full">
                    <label for="transport" class="block text-gray-700 text-sm font-bold mb-2">Keterangan Transport :</label>
                    <textarea id="transport" name="transport" rows="3" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"></textarea>
                </div>
                <div class="mb-4">
                    <label for="tunjangan_transport" class="block text-gray-700 text-sm font-bold mb-2">Tunjangan Transport (Rp):</label>
                    <input type="text" id="tunjangan_transport" name="tunjangan_transport" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" value="0" pattern="[0-9.,]+" title="Masukkan angka, gunakan koma untuk desimal jika perlu.">
                </div>
                <div class="mb-4">
                    <label for="hari_piket" class="block text-gray-700 text-sm font-bold mb-2">Hari Piket (Opsional, cth: Senin, Kamis):</label>
                    <input type="text" id="hari_piket" name="hari_piket" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="transport_piket" class="block text-gray-700 text-sm font-bold mb-2">Transport Piket (Rp):</label>
                    <input type="text" id="transport_piket" name="transport_piket" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" value="0" pattern="[0-9.,]+" title="Masukkan angka, gunakan koma untuk desimal jika perlu.">
                </div>
                <div class="mb-4">
                    <label for="tunjangan_sertifikasi" class="block text-gray-700 text-sm font-bold mb-2">Tunjangan Sertifikasi (Rp):</label>
                    <input type="text" id="tunjangan_sertifikasi" name="tunjangan_sertifikasi" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" value="0" pattern="[0-9.,]+" title="Masukkan angka, gunakan koma untuk desimal jika perlu.">
                </div>
                
                <div class="mb-4 flex items-center col-span-full">
                    <input type="checkbox" id="is_active" name="is_active" class="mr-2 leading-tight" checked>
                    <label for="is_active" class="text-sm text-gray-700">Aktif</label>
                </div>
            </div>

            <div class="flex items-center justify-between mt-6">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    Simpan Guru
                </button>
                <button type="button" id="cancelEdit" class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 hidden">
                    Batal Edit
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white shadow-md rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold mb-4 text-gray-700">Impor Data Guru dari Excel</h2>
        <form action="manage_guru.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">
            <div class="mb-4">
                <label for="excel_file" class="block text-gray-700 text-sm font-bold mb-2">Pilih File Excel (.xls, .xlsx):</label>
                <input type="file" id="excel_file" name="excel_file" accept=".xls,.xlsx" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                <p class="text-xs text-gray-500 mt-1">Pastikan file Excel memiliki kolom: Nama Lengkap, NIP, RFID Tag, Tempat Lahir, Tanggal Lahir (YYYY-MM-DD), Jenis Kelamin, Alamat, No. Telepon, Email, Gaji Per Pertemuan, Aktif (Ya/Tidak), Jabatan, Tunjangan Jabatan, Kegiatan Lainnya, Tunjangan Kegiatan Lainnya, **Transport Jabatan, Hari Piket, Transport Piket**, Tunjangan Sertifikasi.</p>
            </div>
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                <i class="fas fa-file-excel mr-2"></i> Impor Guru
            </button>
            <a href="download_guru_template.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    <i class="fas fa-download mr-2"></i> Download Template
                </a>
        </form>
    </div>


    <div class="bg-white shadow-md rounded-lg p-6">
    <h2 class="text-2xl font-semibold mb-4 text-gray-700">Daftar Guru</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead>
                <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">Nama Lengkap</th>
                    <th class="py-3 px-6 text-left">RFID Tag</th>
                    <th class="py-3 px-6 text-left">No. Telp</th>
                    <th class="py-3 px-6 text-right">Gaji/Pertemuan</th>
                    <th class="py-3 px-6 text-left">Jabatan</th>
                    <th class="py-3 px-6 text-right">Tunjangan Jabatan</th>
                    <th class="py-3 px-6 text-right">Transport Jabatan</th>
                    <th class="py-3 px-6 text-left">Transport</th>
                    <th class="py-3 px-6 text-right">Tunjangan Transport</th>
                    <th class="py-3 px-6 text-left">Hari Piket</th>
                    <th class="py-3 px-6 text-right">Transport Piket</th>
                    <th class="py-3 px-6 text-right">Tunjangan Sertifikasi</th>
                    <th class="py-3 px-6 text-center">Status</th>
                    <th class="py-3 px-6 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm font-light">
                <?php if (empty($all_guru)): ?>
                    <tr>
                        <td colspan="12" class="py-3 px-6 text-center">Tidak ada data guru.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_guru as $guru): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($guru['nama_lengkap']); ?></td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($guru['rfid_tag'] ?? '-'); ?></td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($guru['no_telp'] ?? '-'); ?></td>
                            <td class="py-3 px-6 text-right">Rp <?php echo number_format($guru['gaji_per_pertemuan'], 0, ',', '.'); ?></td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($guru['jabatan'] ?? '-'); ?></td>
                            <td class="py-3 px-6 text-right">Rp <?php echo number_format($guru['tunjangan_jabatan'], 0, ',', '.'); ?></td>
                            <td class="py-3 px-6 text-right">Rp <?php echo number_format($guru['transport_jabatan'], 0, ',', '.'); ?></td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($guru['transport'] ?? '-'); ?></td>
                            <td class="py-3 px-6 text-right">Rp <?php echo number_format($guru['tunjangan_transport'], 0, ',', '.'); ?></td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($guru['hari_piket'] ?? '-'); ?></td>
                            <td class="py-3 px-6 text-right">Rp <?php echo number_format($guru['transport_piket'], 0, ',', '.'); ?></td>
                            <td class="py-3 px-6 text-right">Rp <?php echo number_format($guru['tunjangan_sertifikasi'], 0, ',', '.'); ?></td>
                            <td class="py-3 px-6 text-center">
                                <span class="relative inline-block px-3 py-1 font-semibold leading-tight">
                                    <span aria-hidden="true" class="absolute inset-0 <?php echo $guru['is_active'] ? 'bg-green-200' : 'bg-red-200'; ?> opacity-50 rounded-full"></span>
                                    <span class="relative text-xs <?php echo $guru['is_active'] ? 'text-green-900' : 'text-red-900'; ?>">
                                        <?php echo $guru['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                    </span>
                                </span>
                            </td>
                            <td class="py-3 px-6 text-center">
                                <div class="flex item-center justify-center">
                                    <button onclick="editGuru(<?php echo htmlspecialchars(json_encode($guru)); ?>)" class="w-8 h-8 mr-2 transform hover:text-purple-500 hover:scale-110">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($guru['is_active']): ?>
                                        <form action="manage_guru.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan guru ini?');" class="inline-block">
                                            <input type="hidden" name="action" value="soft_delete">
                                            <input type="hidden" name="id" value="<?php echo $guru['id']; ?>">
                                            <button type="submit" class="w-8 h-8 mr-2 transform hover:text-red-500 hover:scale-110">
                                                <i class="fas fa-toggle-off"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form action="manage_guru.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengaktifkan guru ini kembali?');" class="inline-block">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="id" value="<?php echo $guru['id']; ?>">
                                            <button type="submit" class="w-8 h-8 mr-2 transform hover:text-green-500 hover:scale-110">
                                                <i class="fas fa-toggle-on"></i>
                                            </button>
                                        </form>
                                        <form action="manage_guru.php" method="POST" onsubmit="return confirm('PERINGATAN: Menghapus permanen akan menghapus semua data terkait guru ini (user, jadwal). Apakah Anda yakin?');" class="inline-block">
                                            <input type="hidden" name="action" value="hard_delete">
                                            <input type="hidden" name="id" value="<?php echo $guru['id']; ?>">
                                            <button type="submit" class="w-8 h-8 transform hover:text-red-700 hover:scale-110">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<script>
    function editGuru(guruData) {
        document.getElementById('form-title').textContent = 'Edit Data Guru';
        document.getElementById('form-action').value = 'edit';
        document.getElementById('guru-id').value = guruData.id;
        document.getElementById('nama_lengkap').value = guruData.nama_lengkap;
        document.getElementById('nip').value = guruData.nip || '';
        document.getElementById('rfid_tag').value = guruData.rfid_tag || '';
        document.getElementById('tempat_lahir').value = guruData.tempat_lahir || '';
        document.getElementById('tanggal_lahir').value = guruData.tanggal_lahir || '';
        document.getElementById('jenis_kelamin').value = guruData.jenis_kelamin || '';
        document.getElementById('alamat').value = guruData.alamat || '';
        document.getElementById('no_telp').value = guruData.no_telp || '';
        document.getElementById('email').value = guruData.email || '';
        // Format gaji_per_pertemuan ke format lokal (misal: 50.000)
        document.getElementById('gaji_per_pertemuan').value = new Intl.NumberFormat('id-ID').format(guruData.gaji_per_pertemuan);
        document.getElementById('is_active').checked = guruData.is_active == 1;
        document.getElementById('jabatan').value = guruData.jabatan || '';
        document.getElementById('tunjangan_jabatan').value = new Intl.NumberFormat('id-ID').format(guruData.tunjangan_jabatan);
        document.getElementById('transport').value = guruData.transport || '';
        document.getElementById('tunjangan_transport').value = new Intl.NumberFormat('id-ID').format(guruData.tunjangan_transport);

        // --- NEW FIELDS JAVASCRIPT ---
        document.getElementById('transport_jabatan').value = new Intl.NumberFormat('id-ID').format(guruData.transport_jabatan);
        document.getElementById('hari_piket').value = guruData.hari_piket || '';
        document.getElementById('transport_piket').value = new Intl.NumberFormat('id-ID').format(guruData.transport_piket);
        document.getElementById('tunjangan_sertifikasi').value = new Intl.NumberFormat('id-ID').format(guruData.tunjangan_sertifikasi);
        // -----------------------------


        document.getElementById('cancelEdit').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' }); // Scroll to top to see the form
    }

    document.getElementById('cancelEdit').addEventListener('click', function() {
        document.getElementById('form-title').textContent = 'Tambah Guru Baru';
        document.getElementById('form-action').value = 'add';
        document.getElementById('guru-id').value = '';
        document.getElementById('guruForm').reset(); // Reset form fields
        document.getElementById('is_active').checked = true; // Default to active for new
        document.getElementById('gaji_per_pertemuan').value = '0'; // Reset gaji to 0
        document.getElementById('tunjangan_jabatan').value = '0'; // Reset tunjangan to 0
        document.getElementById('tunjangan_transport').value = '0'; // Reset tunjangan to 0
        document.getElementById('transport_jabatan').value = '0'; // Reset transport to 0
        document.getElementById('transport_piket').value = '0'; // Reset transport to 0
        document.getElementById('tunjangan_sertifikasi').value = '0'; // Reset tunjangan_sertifikasi to 0
        this.classList.add('hidden');
    });

    // Event listener untuk memformat input gaji_per_pertemuan saat diketik
    // Perlu diulang untuk tunjangan_jabatan, tunjangan_transport, transport_jabatan, dan transport_piket
    const currencyFields = ['gaji_per_pertemuan', 'tunjangan_jabatan', 'tunjangan_transport', 'transport_jabatan', 'transport_piket', 'tunjangan_sertifikasi'];
    
    currencyFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function(e) {
                let value = e.target.value;
                // Hapus semua karakter non-digit kecuali koma dan titik
                value = value.replace(/[^0-9,.]/g, '');

                // Jika ada koma, ganti dengan titik untuk pemrosesan float yang benar
                value = value.replace(/,/g, '.');

                // Pastikan hanya ada satu titik desimal
                const parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }
                
                // HANYA UNTUK KEPERLUAN FORM SUBMISSION: 
                // Biarkan value raw di input field (atau bisa implementasi formatting yang lebih canggih)
                // Karena PHP POST akan memproses value string ini.
            });
        }
    });

</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>