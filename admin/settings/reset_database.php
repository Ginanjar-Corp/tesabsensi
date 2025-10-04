<?php
// project-root/admin/settings/reset_database.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Database.php';

// Pastikan pengguna sudah login dan memiliki peran Admin
require_login();
require_role(['Admin']);

$user_id = $_SESSION['user_id'];
$error_message = flash('error');
$success_message = flash('success');

$pdo = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input_password = $_POST['password'] ?? '';
    $tables_to_delete = $_POST['tables'] ?? [];
    
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // Ambil password hash dari user yang sedang login
        $query_password = "SELECT password FROM users WHERE id = :user_id";
        $stmt_password = $pdo->prepare($query_password);
        $stmt_password->execute(['user_id' => $user_id]);
        $user_data = $stmt_password->fetch(PDO::FETCH_ASSOC);

        if ($user_data && password_verify($input_password, $user_data['password'])) {
            // Password cocok, lanjutkan proses penghapusan
            if (!empty($tables_to_delete)) {
                
                // Menonaktifkan pemeriksaan kunci asing sementara
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
                
                $deleted_tables = [];
                foreach ($tables_to_delete as $table) {
                    // Validasi nama tabel untuk mencegah SQL Injection
                    $allowed_tables = [
                        'absensi_gerbang',
                        'absensi_harian_guru',
                        'absensi_harian_siswa',
                        'absensi_mapel_guru',
                        'absensi_mapel_siswa',
                        'siswa',
                        'sorogan_siswa',
                        'ngaji_siswa',
                        'hafalan_siswa',
                        'detail_pembayaran_transaksi',
                        'gaji_settings',
                        'informasi_umum',
                        'izin_keluar_pulang',
                        'jadwal_pelajaran',
                        'jadwal_siswa',
                        'jenis_pelanggaran',
                        'jenis_pembayaran',
                        'kalender_pendidikan',
                        'kas_transaksi',
                        'pelanggaran_siswa',
                        'pembayaran_iuran_pondok',
                        'pengumuman',
                        'tagihan_siswa',
                        // Tabel baru dan yang dikoreksi:
                        'app_settings',
                        'guru',
                        'kas_kategori',
                        'kas_unit',
                        'kelas',
                        'kitab_nadhom',
                        'mapel',
                        'roles',
                        'tahun_ajaran',
                        'users'
                    ];

                    if (in_array($table, $allowed_tables)) {
                        $query = "TRUNCATE TABLE `$table`";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute();
                        $deleted_tables[] = $table;
                    }
                }
                
                // Mengaktifkan kembali pemeriksaan kunci asing
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
                
                if (!empty($deleted_tables)) {
                    $success_message = "Data dari tabel berikut berhasil dihapus: " . implode(', ', $deleted_tables);
                    set_flash('success', $success_message);
                } else {
                    $error_message = "Tidak ada tabel yang dipilih untuk dihapus.";
                    set_flash('error', $error_message);
                }
                
            } else {
                $error_message = "Harap pilih minimal satu data yang ingin dihapus.";
                set_flash('error', $error_message);
            }
        } else {
            $error_message = "Password tidak valid. Penghapusan data dibatalkan.";
            set_flash('error', $error_message);
        }
    } catch (PDOException $e) {
        $error_message = "Terjadi kesalahan database: " . $e->getMessage();
        set_flash('error', $error_message);
    }
    
    header('Location: reset_database.php');
    exit();
}

// Data tabel yang akan ditampilkan di menu
$tables_to_show = [
    'Data Siswa, Akademik & Ngaji' => [
        'siswa' => 'Data Induk Siswa',
        'hafalan_siswa' => 'Hafalan Siswa',
        'sorogan_siswa' => 'Data Sorogan Siswa',
        'ngaji_siswa' => 'Data Ngaji Siswa',
        'jadwal_pelajaran' => 'Jadwal Pelajaran',
        'jadwal_siswa' => 'Jadwal Siswa',
        'kalender_pendidikan' => 'Kalender Pendidikan',
        'kelas' => 'Data Kelas',
        'kitab_nadhom' => 'Data Kitab/Nadhom',
        'mapel' => 'Data Mata Pelajaran',
        'tahun_ajaran' => 'Data Tahun Ajaran',
    ],
    'Absensi' => [
        'absensi_gerbang' => 'Absensi Gerbang (Siswa & Guru)',
        'absensi_harian_guru' => 'Absensi Harian Guru',
        'absensi_harian_siswa' => 'Absensi Harian Siswa',
        'absensi_mapel_guru' => 'Absensi Mapel Guru',
        'absensi_mapel_siswa' => 'Absensi Mapel Siswa',
    ],
    'Kepegawaian' => [
        'guru' => 'Data Guru/Pegawai',
        'gaji_settings' => 'Pengaturan Gaji',
    ],
    'Keuangan' => [
        'kas_transaksi' => 'Transaksi Kas',
        'detail_pembayaran_transaksi' => 'Detail Pembayaran Transaksi',
        'pembayaran_iuran_pondok' => 'Pembayaran Iuran Pondok',
        'tagihan_siswa' => 'Tagihan Siswa',
        'kas_kategori' => 'Kategori Kas',
        'kas_unit' => 'Unit Kas',
    ],
    'Pelanggaran & Perizinan' => [
        'izin_keluar_pulang' => 'Izin Keluar/Pulang',
        'pelanggaran_siswa' => 'Pelanggaran Siswa',
        'jenis_pelanggaran' => 'Jenis Pelanggaran',
    ],
    'Lainnya' => [
        'informasi_umum' => 'Informasi Umum',
        'jenis_pembayaran' => 'Jenis Pembayaran',
        'pengumuman' => 'Pengumuman',
    ],
    'Pengaturan Sistem' => [
        'app_settings' => 'Pengaturan Aplikasi',
        'roles' => 'Peran Pengguna',
        'users' => 'Data Pengguna (Akun Login)',
    ]
];
require_once __DIR__ . '/../../components/header_admin.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Database</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg p-6 md:p-8 card">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6">Reset Database</h1>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="mb-8 p-4 md:p-6 bg-yellow-100 rounded-lg border-l-4 border-yellow-500 text-yellow-800">
            <h2 class="font-bold text-lg mb-2">Peringatan Keras!</h2>
            <p>Tindakan ini akan <strong class="text-red-600">MENGHAPUS SEMUA DATA</strong> dari tabel yang Anda pilih secara permanen. Tindakan ini tidak dapat dibatalkan. Pastikan Anda telah melakukan backup database sebelum melanjutkan.</p>
        </div>
        
        <div class="mb-8 p-4 md:p-6 bg-red-100 rounded-lg border-l-4 border-red-500 text-red-800">
            <h2 class="font-bold text-lg mb-2">Peringatan Tambahan!</h2>
            <p>Tabel-tabel dalam kategori **'Pengaturan Sistem'** berisi data konfigurasi aplikasi dan akun pengguna. Sangat disarankan untuk tidak menghapus data ini kecuali Anda tahu persis apa yang Anda lakukan, karena dapat membuat aplikasi tidak berfungsi dengan baik dan menghapus semua akun login.</p>
        </div>


        <form action="" method="POST">
            <h2 class="text-xl font-semibold mb-4">Pilih Data yang Akan Dihapus</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                <?php foreach ($tables_to_show as $category => $tables): ?>
                    <div class="p-4 rounded-lg border border-gray-200">
                        <h3 class="font-bold text-lg mb-2 text-gray-700"><?php echo htmlspecialchars($category); ?></h3>
                        <?php foreach ($tables as $table_name => $table_label): ?>
                            <div class="flex items-center mb-2">
                                <input type="checkbox" id="<?php echo htmlspecialchars($table_name); ?>" name="tables[]" value="<?php echo htmlspecialchars($table_name); ?>" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="<?php echo htmlspecialchars($table_name); ?>" class="ml-2 text-sm font-medium text-gray-700 cursor-pointer"><?php echo htmlspecialchars($table_label); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2 class="text-xl font-semibold mb-4">Validasi Penghapusan</h2>
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Masukkan Password Anda:</label>
                <input type="password" id="password" name="password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>

            <div class="flex justify-end space-x-4">
                <a href="download_backup.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                    Download Database Backup
                </a>
                <button type="submit" onclick="return confirm('Apakah Anda yakin ingin menghapus data yang dipilih? Tindakan ini tidak dapat dibatalkan!');" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                    Hapus Data
                </button>
            </div>
        </form>
    </div>
</body>
</html>