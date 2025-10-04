<?php
// project-root/includes/helpers.php

// Memulai sesi jika belum ada (biasanya sudah dimulai di app_config.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

global $pdo; // Variabel $pdo harus tersedia secara global atau di-pass ke fungsi

function redirect_to($path)
{
    // Menggunakan ROOT_URL yang didefinisikan di app_config.php
    $url = defined('ROOT_URL') ? ROOT_URL . $path : '/' . $path;
    header("Location: " . $url);
    exit();
}

/**
 * Mengatur pesan flash (pesan satu kali tampil) ke sesi.
 *
 * @param string $key Kunci untuk pesan flash (e.g., 'success', 'error', 'warning').
 * @param string $message Pesan yang akan ditampilkan.
 */
function set_flash($key, $message) {
    $_SESSION['flash_' . $key] = $message;
}

/**
 * Mengambil dan menghapus pesan flash dari sesi.
 *
 * @param string $key Kunci untuk pesan flash.
 * @return string|null Pesan flash jika ada, null jika tidak.
 */
function flash($key) {
    $flash_key = 'flash_' . $key;
    if (isset($_SESSION[$flash_key])) {
        $message = $_SESSION[$flash_key];
        unset($_SESSION[$flash_key]);
        return $message;
    }
    return null;
}



/**
 * Mendapatkan tahun ajaran yang sedang aktif.
 * @return array|null Data tahun ajaran aktif atau null jika tidak ada.
 */
function getActiveTahunAjaran() {
    global $pdo;
    if (!$pdo) { error_log("PDO object is null in getActiveTahunAjaran."); return null; }
    try {
        $stmt = $pdo->query("SELECT * FROM tahun_ajaran WHERE is_active = 1 LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching active tahun_ajaran: " . $e->getMessage());
        return null;
    }
}

/**
 * Mengambil nilai tahun ajaran yang aktif.
 * @return string|null Nilai tahun ajaran atau null jika tidak ada.
 */
function getActiveTahunAjaranValue() {
    global $pdo;
    if (!$pdo) {
        error_log("PDO object is null in getActiveTahunAjaranValue.");
        return null;
    }
    try {
        $stmt = $pdo->query("SELECT tahun_ajaran FROM tahun_ajaran WHERE is_active = 1 LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['tahun_ajaran'] : null;
    } catch (PDOException $e) {
        error_log("Error fetching active tahun_ajaran value: " . $e->getMessage());
        return null;
    }
}

/**
 * Mengambil ID tahun ajaran yang sedang aktif dari database.
 * Asumsi ada tabel `tahun_ajaran` dengan kolom `is_active` (boolean/tinyint).
 *
 * @return int|null ID tahun ajaran aktif atau null jika tidak ada.
 */
function get_active_tahun_ajaran_id() {
    global $pdo; // Pastikan $pdo tersedia secara global

    if (!$pdo) {
        error_log("PDO connection not available in get_active_tahun_ajaran_id()");
        return null;
    }

    try {
        $query = "SELECT id FROM tahun_ajaran WHERE is_active = 1 LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return (int) $result['id'];
        }
    } catch (PDOException $e) {
        error_log("Database error in get_active_tahun_ajaran_id(): " . $e->getMessage());
    }
    return null;
}

/**
 * Mendapatkan semua tahun ajaran dari database.
 * @return array Daftar semua tahun ajaran.
 */
function getAllTahunAjaran() {
    global $pdo;
    if (!$pdo) { error_log("PDO object is null in getAllTahunAjaran."); return []; }
    try {
        $stmt = $pdo->query("SELECT id, tahun_ajaran FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching all tahun_ajarans: " . $e->getMessage());
        return [];
    }
}


/**
 * Mengarahkan pengguna ke URL tertentu.
 *
 * @param string $url URL tujuan.
 */
function redirect($url) {
    header('Location: ' . $url);
    exit();
}

/**
 * Memformat tanggal ke format Indonesia.
 *
 * @param string $date_string Tanggal dalam format YYYY-MM-DD.
 * @return string Tanggal dalam format DD Bulan YYYY.
 */
function format_date_indonesian($date_string) {
    if (empty($date_string) || $date_string === '0000-00-00') {
        return '-';
    }

    $date_obj = new DateTime($date_string);
    $day = $date_obj->format('d');
    $month_num = $date_obj->format('m');
    $year = $date_obj->format('Y');

    $months = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];

    $month_name = $months[$month_num] ?? '';

    return "{$day} {$month_name} {$year}";
}

function getTotalSiswaAktif() {
    global $pdo;
    try {
        // Menggunakan kolom 'is_active' dari skema database yang diberikan (1 untuk aktif)
        $stmt = $pdo->query("SELECT COUNT(id) FROM siswa WHERE is_active = 1");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Catat error ke log server
        error_log("Error menghitung siswa aktif: " . $e->getMessage());
        return 0; // Kembalikan 0 jika ada error
    }
}

/**
 * Mengambil total guru aktif dari database.
 * @return int Jumlah guru aktif.
 */
function getTotalGuruAktif() {
    global $pdo;
    try {
        // Asumsi tabel 'guru' memiliki kolom 'status'
        $stmt = $pdo->query("SELECT COUNT(id) FROM guru WHERE is_active = '1'");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error menghitung guru aktif: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mengambil total kelas dari database.
 * @return int Jumlah kelas.
 */
function getTotalKelas() {
    global $pdo;
    try {
        // Asumsi tabel 'kelas'
        $stmt = $pdo->query("SELECT COUNT(id) FROM kelas");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error menghitung kelas: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mengambil total mata pelajaran dari database.
 * @return int Jumlah mata pelajaran.
 */
function getTotalMataPelajaran() {
    global $pdo;
    try {
        // Asumsi tabel 'mata_pelajaran'
        $stmt = $pdo->query("SELECT COUNT(id) FROM mata_pelajaran");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error menghitung mata pelajaran: " . $e->getMessage());
        return 0;
    }
}

/**
 * Menghitung jumlah siswa yang sudah absen masuk hari ini.
 * Menggunakan skema database absensi_harian_siswa.
 * @return int Jumlah siswa yang sudah absen hari ini.
 */
function getSiswaAbsenHariIni() {
    global $pdo;
    $today = date('Y-m-d'); // Mendapatkan tanggal hari ini dalam format YYYY-MM-DD
    try {
        // Menggunakan tabel 'absensi_harian_siswa' dengan kolom 'tanggal' dan 'waktu_masuk'
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT siswa_id) FROM absensi_harian_siswa WHERE tanggal = ? AND waktu_masuk IS NOT NULL");
        $stmt->execute([$today]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error menghitung siswa absen hari ini: " . $e->getMessage());
        return 0;
    }
}

/**
 * Menghitung jumlah siswa yang belum absen masuk hari ini.
 * Ini mengasumsikan semua siswa aktif diharapkan absen.
 * @return int Jumlah siswa yang belum absen hari ini.
 */
function getSiswaBelumAbsenHariIni() {
    $totalSiswa = getTotalSiswaAktif(); // Mengambil total siswa aktif
    $siswaSudahAbsen = getSiswaAbsenHariIni(); // Mengambil siswa yang sudah absen hari ini
    return max(0, $totalSiswa - $siswaSudahAbsen); // Pastikan hasilnya tidak negatif
}

/**
 * Menghitung jumlah guru yang sudah absen masuk hari ini.
 * Menggunakan skema database absensi_harian_guru.
 * @return int Jumlah guru yang sudah absen hari ini.
 */
function getGuruAbsenHariIni() {
    global $pdo;
    $today = date('Y-m-d'); // Mendapatkan tanggal hari ini
    try {
        // Menggunakan tabel 'absensi_harian_guru' dengan kolom 'tanggal' dan 'waktu_masuk'
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT guru_id) FROM absensi_harian_guru WHERE tanggal = ? AND waktu_masuk IS NOT NULL");
        $stmt->execute([$today]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error menghitung guru absen hari ini: " . $e->getMessage());
        return 0;
    }
}

/**
 * Menghitung jumlah guru yang belum absen masuk hari ini.
 * Ini mengasumsikan semua guru aktif diharapkan absen.
 * @return int Jumlah guru yang belum absen hari ini.
 */
function getGuruBelumAbsenHariIni() {
    $totalGuru = getTotalGuruAktif(); // Mengambil total guru aktif
    $guruSudahAbsen = getGuruAbsenHariIni(); // Mengambil guru yang sudah absen hari ini
    return max(0, $totalGuru - $guruSudahAbsen); // Pastikan hasilnya tidak negatif
}


/**
 * Menghitung jumlah guru yang memiliki jadwal mengajar pada hari ini.
 * @return int Jumlah guru dengan jadwal hari ini.
 */
function getTotalGuruWithScheduleToday() {
    global $pdo;
    if (!$pdo) {
        error_log("PDO object is null in getTotalGuruWithScheduleToday.");
        return 0;
    }

    $currentDayOfWeekNum = date('N'); // 1 (Senin) sampai 7 (Minggu)
    $dayNames = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];
    $currentDayOfWeekName = $dayNames[$currentDayOfWeekNum]; // Mendapatkan nama hari dalam Bahasa Indonesia

    $active_ta_data = getActiveTahunAjaran();
    $current_tahun_ajaran = $active_ta_data['tahun_ajaran'] ?? null;
    // Asumsi APP_SEMESTER_AKTIF didefinisikan di app_config.php
    $current_semester = defined('APP_SEMESTER_AKTIF') ? APP_SEMESTER_AKTIF : null;

    if (!$current_tahun_ajaran || !$current_semester) {
        error_log("Tahun Ajaran aktif atau Semester aktif tidak disetel untuk getTotalGuruWithScheduleToday. TA: " . ($current_tahun_ajaran ?? 'NULL') . ", Semester: " . ($current_semester ?? 'NULL'));
        return 0;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT jp.guru_id)
            FROM jadwal_pelajaran jp
            JOIN guru g ON jp.guru_id = g.id
            WHERE jp.hari = ?
            AND jp.tahun_ajaran = ?
            AND jp.semester = ?
            AND g.is_active = 1
        ");
        $stmt->execute([$currentDayOfWeekName, $current_tahun_ajaran, $current_semester]);
        $count = $stmt->fetchColumn();
        error_log("DEBUG: getTotalGuruWithScheduleToday() - Hari: {$currentDayOfWeekName}, TA: {$current_tahun_ajaran}, Semester: {$current_semester}, Count: {$count}");
        return $count;
    } catch (PDOException $e) {
        error_log("Error menghitung guru dengan jadwal hari ini: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mengambil daftar guru yang memiliki jadwal mengajar pada hari ini,
 * beserta detail jadwal dan status kehadiran harian mereka.
 *
 * @return array Daftar guru dengan jadwal hari ini dan status kehadiran.
 */
function getGuruJadwalDanKehadiranHariIni() {
    global $pdo;
    if (!$pdo) {
        error_log("PDO object is null in getGuruJadwalDanKehadiranHariIni.");
        return [];
    }

    $today = date('Y-m-d');
    $currentDayOfWeekNum = date('N'); // 1 (Senin) sampai 7 (Minggu)
    $dayNames = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];
    $currentDayOfWeekName = $dayNames[$currentDayOfWeekNum];

    $active_ta_data = getActiveTahunAjaran();
    $current_tahun_ajaran = $active_ta_data['tahun_ajaran'] ?? null;
    $current_semester = defined('APP_SEMESTER_AKTIF') ? APP_SEMESTER_AKTIF : null;

    if (!$current_tahun_ajaran || !$current_semester) {
        error_log("Tahun Ajaran aktif atau Semester aktif tidak disetel. TA: " . ($current_tahun_ajaran ?? 'NULL') . ", Semester: " . ($current_semester ?? 'NULL'));
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                g.nama_lengkap,
                mp.nama_mapel,
                -- Menggunakan COALESCE untuk menampilkan 'Multiple' jika nama_kelas NULL
                COALESCE(k.nama_kelas, 'Multiple') AS nama_kelas,
                jp.jam_mulai,
                jp.jam_selesai,
                jp.jumlah_jam,
                jp.tipe_jadwal, -- Menambahkan kolom tipe_jadwal
                CASE
                    WHEN ahg.waktu_masuk IS NOT NULL THEN 'Hadir'
                    ELSE 'Belum Absen'
                END AS status_kehadiran
            FROM
                jadwal_pelajaran jp
            JOIN
                guru g ON jp.guru_id = g.id
            JOIN
                mata_pelajaran mp ON jp.mapel_id = mp.id
            LEFT JOIN
                kelas k ON jp.kelas_id = k.id -- Perubahan penting: Menggunakan LEFT JOIN
            LEFT JOIN
                absensi_harian_guru ahg ON g.id = ahg.guru_id AND ahg.tanggal = ?
            WHERE
                jp.hari = ?
                AND jp.tahun_ajaran = ?
                AND jp.semester = ?
                AND g.is_active = 1
            ORDER BY
                g.nama_lengkap, jp.jam_mulai
        ");
        $stmt->execute([$today, $currentDayOfWeekName, $current_tahun_ajaran, $current_semester]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching guru jadwal dan kehadiran hari ini: " . $e->getMessage());
        return [];
    }
}

// --- Fungsi-fungsi Khusus Guru ---

/**
 * Menghitung total mata pelajaran yang diampu oleh seorang guru.
 * @param int $guruId ID guru.
 * @return int Jumlah mata pelajaran yang diampu.
 */
function getTotalMapelDiampuGuru($guruId) {
    global $pdo;
    if (!$guruId) return 0;
    try {
        // Menggunakan tabel 'jadwal_pelajaran' sesuai skema yang diberikan
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT mapel_id) FROM jadwal_pelajaran WHERE guru_id = ?");
        $stmt->execute([$guruId]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error menghitung mapel diampu guru: " . $e->getMessage());
        return 0;
    }
}

/**
 * Menghitung total kelas yang diampu oleh seorang guru.
 * @param int $guruId ID guru.
 * @return int Jumlah kelas yang diampu.
 */
function getTotalKelasDiampuGuru($guruId) {
    global $pdo;
    if (!$guruId) return 0;
    try {
        // Menggunakan tabel 'jadwal_pelajaran' sesuai skema yang diberikan
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT kelas_id) FROM jadwal_pelajaran WHERE guru_id = ?");
        $stmt->execute([$guruId]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error menghitung kelas diampu guru: " . $e->getMessage());
        return 0;
    }
}

/**
 * Menghitung jumlah siswa yang sudah hadir di mata pelajaran yang diampu guru hari ini.
 * Menggunakan skema database absensi_mapel_siswa.
 * @param int $guruId ID guru.
 * @return int Jumlah siswa yang sudah hadir.
 */
function getSiswaHadirMapelHariIni($guruId) {
    global $pdo;
    if (!$guruId) return 0;
    $today = date('Y-m-d');

    try {
        // Query untuk mendapatkan siswa yang hadir hari ini
        // dari tabel absensi_mapel_siswa yang relevan dengan guru ini.
        // Menggunakan 'H' untuk status_kehadiran Hadir
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT siswa_id)
            FROM absensi_mapel_siswa
            WHERE guru_id = ?
            AND tanggal_absen = ?
            AND status_kehadiran = 'H' -- Menggunakan 'H' untuk Hadir
        ");
        $stmt->execute([$guruId, $today]);
        $count = $stmt->fetchColumn();
        error_log("DEBUG: getSiswaHadirMapelHariIni (Guru ID: {$guruId}, Tanggal: {$today}): {$count} siswa hadir.");
        return $count;
    } catch (PDOException $e) {
        error_log("Error menghitung siswa hadir mapel hari ini: " . $e->getMessage());
        return 0;
    }
}

/**
 * Menghitung jumlah siswa yang belum hadir di mata pelajaran yang diampu guru hari ini.
 * Ini mengasumsikan semua siswa yang seharusnya diajar guru hari ini harus absen.
 * @param int $guruId ID guru.
 * @return int Jumlah siswa yang belum hadir.
 */
function getSiswaBelumHadirMapelHariIni($guruId) {
    global $pdo;
    if (!$guruId) return 0;
    $today = date('Y-m-d');
    $currentDayOfWeekNum = date('N'); // 1 (Senin) sampai 7 (Minggu)

    // Peta dari angka hari ke nama hari dalam Bahasa Indonesia
    $dayNames = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];
    $currentDayOfWeekName = $dayNames[$currentDayOfWeekNum]; // Mendapatkan nama hari dalam Bahasa Indonesia

    try {
        // Langkah 1: Dapatkan total siswa unik yang seharusnya diajar guru ini PADA HARI INI
        // Memfilter berdasarkan 'hari' dari tabel jadwal_pelajaran menggunakan nama hari
        $stmtTotalSiswaYangSeharusnyaHadir = $pdo->prepare("
            SELECT COUNT(DISTINCT s.id)
            FROM siswa s
            JOIN jadwal_pelajaran jp ON s.kelas_id = jp.kelas_id
            WHERE jp.guru_id = ?
            AND s.is_active = 1
            AND jp.hari = ? -- Filter berdasarkan nama hari ini (misal: 'Sabtu')
        ");
        $stmtTotalSiswaYangSeharusnyaHadir->execute([$guruId, $currentDayOfWeekName]);
        $totalSiswaYangSeharusnyaHadir = $stmtTotalSiswaYangSeharusnyaHadir->fetchColumn();

        // Langkah 2: Dapatkan jumlah siswa yang sudah absen di mapel guru ini hari ini
        $siswaHadir = getSiswaHadirMapelHariIni($guruId);

        // Debugging logs yang lebih detail
        error_log("DEBUG: getSiswaBelumHadirMapelHariIni (Guru ID: {$guruId})");
        error_log("DEBUG: Tanggal Hari Ini: {$today}, Hari dalam Minggu (Angka): {$currentDayOfWeekNum}, Hari (Nama): {$currentDayOfWeekName}");
        error_log("DEBUG: Total Siswa Yang Seharusnya Hadir (berdasarkan jadwal hari ini): {$totalSiswaYangSeharusnyaHadir}");
        error_log("DEBUG: Siswa Hadir (berdasarkan absensi mapel hari ini): {$siswaHadir}");

        return max(0, $totalSiswaYangSeharusnyaHadir - $siswaHadir);

    } catch (PDOException $e) {
        error_log("Error menghitung siswa belum hadir mapel hari ini: " . $e->getMessage());
        return 0;
    }
}

/**
 * Memeriksa apakah guru tertentu sudah absen masuk hari ini.
 *
 * @param PDO $pdo Objek PDO untuk koneksi database.
 * @param int $guruId ID guru yang akan diperiksa.
 * @return bool True jika guru sudah absen masuk, false jika belum.
 */
function isGuruCheckedInToday(PDO $pdo, int $guruId): bool {
    $today = date('Y-m-d');
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(id)
            FROM absensi_harian_guru
            WHERE guru_id = :guru_id
            AND tanggal = :tanggal
            AND waktu_masuk IS NOT NULL
        ");
        $stmt->execute([
            ':guru_id' => $guruId,
            ':tanggal' => $today
        ]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error checking guru check-in status: " . $e->getMessage());
        return false;
    }
}

// Di includes/helpers.php
function getDayName($dayNumber) {
    $days = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];
    return $days[$dayNumber] ?? 'Tidak Diketahui';
}

// Di includes/helpers.php
function format_tanggal_indo($tanggal) {
    if (!$tanggal || $tanggal === '0000-00-00') return '-';
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $pecah = explode('-', $tanggal);
    return $pecah[2] . ' ' . $bulan[$pecah[1]] . ' ' . $pecah[0];
}