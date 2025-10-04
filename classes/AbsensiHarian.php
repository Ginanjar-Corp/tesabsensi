<?php
// project-root/classes/AbsensiHarian.php

class AbsensiHarian {
    private $pdo;
    private $table_name = "absensi_harian_siswa";

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ... metode lainnya ...
    
    /**
     * Mendapatkan semua catatan absensi harian untuk seorang siswa dalam rentang tanggal tertentu.
     * @param int $siswaId ID siswa.
     * @param string $startDate Tanggal mulai (format 'Y-m-d').
     * @param string $endDate Tanggal akhir (format 'Y-m-d').
     * @return array Data absensi harian.
     */
    public function getAbsensiHarianBySiswaId($siswaId, $startDate, $endDate) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE siswa_id = :siswa_id
                  AND tanggal BETWEEN :start_date AND :end_date
                  ORDER BY tanggal DESC";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':siswa_id', $siswaId, PDO::PARAM_INT);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mencatat absensi siswa (masuk/pulang) dengan tahun ajaran, semester, dan kelas ID.
     * @param int $siswaId ID siswa.
     * @param string $tipeAbsensi 'masuk' atau 'pulang'.
     * @param string $tahunAjaran Tahun ajaran saat ini.
     * @param string $semester Semester saat ini.
     * @param int|null $kelasId ID kelas siswa.
     * @return array Status dan pesan hasil absensi.
     */
    public function recordSiswaAttendanceWithType($siswaId, $tipeAbsensi, $tahunAjaran, $semester, $kelasId = null, $statusManual = null) {
    $tanggal = date('Y-m-d');
    $waktu = date('H:i:s');
    $status_masuk = null;
    $status_pulang = null;
    $message = '';

    try {
        // Ambil kelas_id siswa jika tidak disediakan (untuk record baru atau update)
        if ($kelasId === null) {
            $stmt_siswa_kelas = $this->pdo->prepare("SELECT kelas_id FROM siswa WHERE id = ?");
            $stmt_siswa_kelas->execute([$siswaId]);
            $kelasId = $stmt_siswa_kelas->fetchColumn();
        }

        // Cek apakah sudah ada catatan absensi untuk siswa dan tanggal ini
        $stmt = $this->pdo->prepare("SELECT * FROM absensi_harian_siswa WHERE siswa_id = ? AND tanggal = ? AND tahun_ajaran = ? AND semester = ?");
        $stmt->execute([$siswaId, $tanggal, $tahunAjaran, $semester]);
        $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

        // Jika status absensi yang dipilih adalah "Pulang"
        if ($statusManual === 'Pulang') {
            if (!$existing_record) {
                return ['status' => 'error', 'message' => 'Siswa belum absen masuk hari ini. Absensi pulang tidak dapat dicatat.'];
            }
            if ($existing_record['waktu_pulang'] !== null) {
                return ['status' => 'info', 'message' => 'Siswa sudah absen pulang hari ini pada pukul ' . substr($existing_record['waktu_pulang'], 0, 5) . '.'];
            }

            $status_pulang_to_save = 'Pulang';
            $waktu_pulang_to_save = $waktu;

            $stmt = $this->pdo->prepare("UPDATE absensi_harian_siswa SET waktu_pulang = ?, status_pulang = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$waktu_pulang_to_save, $status_pulang_to_save, $existing_record['id']]);

            $message = 'Absensi pulang berhasil dicatat.';
            return ['status' => 'success', 'message' => $message];
        }

        // Jika ada status manual selain "Pulang" yang diberikan, ini adalah pencatatan manual untuk absensi masuk
        if ($statusManual !== null) {
            // Jika sudah ada record dengan status manual (Sakit, Izin, Alpha), jangan override
            if ($existing_record && in_array($existing_record['status_masuk'], ['Sakit', 'Izin', 'Alpha']) && $statusManual !== $existing_record['status_masuk']) {
                return ['status' => 'info', 'message' => 'Siswa sudah tercatat sebagai ' . $existing_record['status_masuk'] . ' hari ini. Perubahan manual diabaikan.'];
            }

            $status_masuk = $statusManual;
            $waktu_masuk_to_save = null;
            $waktu_pulang_to_save = null;
            $status_pulang_to_save = 'Belum Pulang';

            if ($statusManual === 'Hadir' || $statusManual === 'Terlambat') {
                $waktu_masuk_to_save = $waktu;
                if ($existing_record && $existing_record['waktu_pulang'] !== null) {
                    $waktu_pulang_to_save = $existing_record['waktu_pulang'];
                    $status_pulang_to_save = $existing_record['status_pulang'];
                }
            } else { // Sakit, Izin, Alpha
                if ($existing_record && $existing_record['waktu_pulang'] !== null) {
                    $waktu_pulang_to_save = $existing_record['waktu_pulang'];
                    $status_pulang_to_save = $existing_record['status_pulang'];
                }
            }

            if ($existing_record) {
                $stmt = $this->pdo->prepare("UPDATE absensi_harian_siswa SET waktu_masuk = ?, waktu_pulang = ?, status_masuk = ?, status_pulang = ?, kelas_id = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$waktu_masuk_to_save, $waktu_pulang_to_save, $status_masuk, $status_pulang_to_save, $kelasId, $existing_record['id']]);
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO absensi_harian_siswa (siswa_id, kelas_id, tanggal, waktu_masuk, waktu_pulang, status_masuk, status_pulang, tahun_ajaran, semester, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$siswaId, $kelasId, $tanggal, $waktu_masuk_to_save, $waktu_pulang_to_save, $status_masuk, $status_pulang_to_save, $tahunAjaran, $semester]);
            }

            $message = 'Absensi siswa berhasil dicatat sebagai: ' . $status_masuk . '.';
            return ['status' => 'success', 'message' => $message];

        } else { // Ini adalah pencatatan absensi gerbang
            // ... (Kode untuk absensi gerbang 'masuk' dan 'pulang' tetap tidak berubah)
            if ($tipeAbsensi === 'masuk') {
                if ($existing_record && in_array($existing_record['status_masuk'], ['Sakit', 'Izin', 'Alpha'])) {
                    return ['status' => 'info', 'message' => 'Siswa sudah tercatat sebagai ' . $existing_record['status_masuk'] . ' hari ini. Absensi gerbang diabaikan.'];
                }
                if ($existing_record && $existing_record['waktu_masuk'] !== null) {
                    return ['status' => 'info', 'message' => 'Siswa sudah absen masuk hari ini pada pukul ' . substr($existing_record['waktu_masuk'], 0, 5) . '.'];
                }
                $status_masuk = 'Hadir';
                if ($existing_record) {
                    $stmt = $this->pdo->prepare("UPDATE absensi_harian_siswa SET waktu_masuk = ?, status_masuk = ?, kelas_id = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$waktu, $status_masuk, $kelasId, $existing_record['id']]);
                } else {
                    $stmt = $this->pdo->prepare("INSERT INTO absensi_harian_siswa (siswa_id, kelas_id, tanggal, waktu_masuk, status_masuk, tahun_ajaran, semester, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    $stmt->execute([$siswaId, $kelasId, $tanggal, $waktu, $status_masuk, $tahunAjaran, $semester]);
                }
                $message = 'Absensi masuk berhasil dicatat.';
                return ['status' => 'success', 'message' => $message, 'data' => ['waktu' => $waktu, 'type' => 'masuk', 'status' => $status_masuk]];
            } elseif ($tipeAbsensi === 'pulang') {
                if (!$existing_record) {
                    return ['status' => 'error', 'message' => 'Siswa belum absen masuk hari ini. Absensi pulang tidak dapat dicatat.'];
                }
                if ($existing_record['waktu_pulang'] !== null) {
                    return ['status' => 'info', 'message' => 'Siswa sudah absen pulang hari ini pada pukul ' . substr($existing_record['waktu_pulang'], 0, 5) . '.'];
                }
                $status_pulang = 'Pulang';
                $stmt = $this->pdo->prepare("UPDATE absensi_harian_siswa SET waktu_pulang = ?, status_pulang = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$waktu, $status_pulang, $existing_record['id']]);
                $message = 'Absensi pulang berhasil dicatat.';
                return ['status' => 'success', 'message' => $message, 'data' => ['waktu' => $waktu, 'type' => 'pulang', 'status' => $status_pulang]];
            }
        }
    } catch (PDOException $e) {
        error_log("Error recording siswa attendance: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Gagal mencatat absensi siswa.'];
    }
}

    /**
     * Mencatat absensi guru (masuk/pulang) dengan tahun ajaran dan semester.
     * Metode ini dirancang untuk menangani absensi gerbang dan manual.
     * @param int $guruId ID guru.
     * @param string $tipeAbsensi 'masuk' atau 'pulang'.
     * @param string $tahunAjaran Tahun ajaran saat ini.
     * @param string $semester Semester saat ini.
     * @param string|null $statusManual Status manual jika ada ('Sakit', 'Izin', 'Alpha', 'Terlambat'). Jika null, diasumsikan 'Hadir'.
     * @return array Status dan pesan hasil absensi.
     */
    public function recordGuruAttendanceWithType($guruId, $tipeAbsensi, $tahunAjaran, $semester, $statusManual = null) {
        $tanggal = date('Y-m-d');
        $waktu = date('H:i:s');
        $message = '';
        
        try {
            // Cek apakah sudah ada catatan absensi untuk guru dan tanggal ini
            $stmt = $this->pdo->prepare("SELECT * FROM absensi_harian_guru WHERE guru_id = ? AND tanggal = ? AND tahun_ajaran = ? AND semester = ?");
            $stmt->execute([$guruId, $tanggal, $tahunAjaran, $semester]);
            $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

            // Jika ada status manual yang diberikan, ini adalah pencatatan manual
            if ($statusManual !== null) {
                // Jika sudah ada record dengan status manual (Sakit, Izin, Alpha), jangan override
                if ($existing_record && in_array($existing_record['status_masuk'], ['Sakit', 'Izin', 'Alpha']) && $statusManual !== $existing_record['status_masuk']) {
                    return ['status' => 'info', 'message' => 'Guru sudah tercatat sebagai ' . $existing_record['status_masuk'] . ' hari ini. Perubahan manual diabaikan.'];
                }

                $status_masuk = $statusManual;
                $waktu_masuk_to_save = null; // Default untuk S, I, A
                $waktu_pulang_to_save = null; // Default untuk S, I, A
                $status_pulang_to_save = 'Belum Pulang'; // Default

                if ($statusManual === 'Hadir' || $statusManual === 'Terlambat') {
                    $waktu_masuk_to_save = $waktu;
                    // Jika sudah ada waktu pulang dari absensi gerbang, pertahankan
                    if ($existing_record && $existing_record['waktu_pulang'] !== null) {
                        $waktu_pulang_to_save = $existing_record['waktu_pulang'];
                        $status_pulang_to_save = $existing_record['status_pulang'];
                    }
                } else { // Sakit, Izin, Alpha
                    // Jika ada record yang sudah ada, pertahankan waktu pulang jika sudah ada
                    if ($existing_record && $existing_record['waktu_pulang'] !== null) {
                        $waktu_pulang_to_save = $existing_record['waktu_pulang'];
                        $status_pulang_to_save = $existing_record['status_pulang'];
                    }
                }

                if ($existing_record) {
                    $stmt = $this->pdo->prepare("UPDATE absensi_harian_guru SET waktu_masuk = ?, waktu_pulang = ?, status_masuk = ?, status_pulang = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$waktu_masuk_to_save, $waktu_pulang_to_save, $status_masuk, $status_pulang_to_save, $existing_record['id']]);
                } else {
                    $stmt = $this->pdo->prepare("INSERT INTO absensi_harian_guru (guru_id, tanggal, waktu_masuk, waktu_pulang, status_masuk, status_pulang, tahun_ajaran, semester, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    $stmt->execute([$guruId, $tanggal, $waktu_masuk_to_save, $waktu_pulang_to_save, $status_masuk, $status_pulang_to_save, $tahunAjaran, $semester]);
                }
                $message = 'Absensi guru berhasil dicatat sebagai: ' . $status_masuk . '.';
                return ['status' => 'success', 'message' => $message];

            } else { // Ini adalah pencatatan absensi gerbang
                // Ambil pengaturan waktu dari SchoolSettings (jika diperlukan)
                // $settings = $this->school_settings->getSettings();
                // $jam_masuk_sekolah_default = $settings['jam_masuk_sekolah_default'] ?? '07:00:00';
                // $toleransi_terlambat_menit = $settings['toleransi_terlambat_menit'] ?? 15;
                // $batas_terlambat_timestamp = strtotime($jam_masuk_sekolah_default) + ($toleransi_terlambat_menit * 60);
                // $batas_terlambat = date('H:i:s', $batas_terlambat_timestamp);

                if ($tipeAbsensi === 'masuk') {
                    // Jika sudah ada status Sakit/Izin/Alpha, jangan override dengan absensi gerbang
                    if ($existing_record && in_array($existing_record['status_masuk'], ['Sakit', 'Izin', 'Alpha'])) {
                        return ['status' => 'info', 'message' => 'Guru sudah tercatat sebagai ' . $existing_record['status_masuk'] . ' hari ini. Absensi gerbang diabaikan.'];
                    }

                    if ($existing_record && $existing_record['waktu_masuk'] !== null) {
                        return ['status' => 'info', 'message' => 'Anda sudah absen masuk hari ini.'];
                    } else {
                        $status_masuk = 'Hadir'; // Default status masuk untuk guru
                        // if ($waktu > $batas_terlambat) { $status_masuk = 'Terlambat'; } else { $status_masuk = 'Hadir'; }

                        if ($existing_record) {
                            $stmt = $this->pdo->prepare("UPDATE absensi_harian_guru SET waktu_masuk = ?, status_masuk = ?, tahun_ajaran = ?, semester = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$waktu, $status_masuk, $tahunAjaran, $semester, $existing_record['id']]);
                        } else {
                            $stmt = $this->pdo->prepare("INSERT INTO absensi_harian_guru (guru_id, tanggal, waktu_masuk, status_masuk, tahun_ajaran, semester, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                            $stmt->execute([$guruId, $tanggal, $waktu, $status_masuk, $tahunAjaran, $semester]);
                        }
                        $message = 'Absensi masuk guru berhasil dicatat.';
                        return ['status' => 'success', 'message' => $message, 'data' => ['waktu' => $waktu, 'type' => 'masuk', 'status' => $status_masuk]];
                    }
                } elseif ($tipeAbsensi === 'pulang') {
                    if ($existing_record) {
                        if ($existing_record['waktu_pulang'] !== null) {
                            $message = 'Anda sudah absen pulang hari ini.';
                            return ['status' => 'info', 'message' => $message];
                        } else {
                            $status_pulang = 'Pulang';
                            $stmt = $this->pdo->prepare("UPDATE absensi_harian_guru SET waktu_pulang = ?, status_pulang = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$waktu, $status_pulang, $existing_record['id']]);
                            $message = 'Absensi pulang guru berhasil dicatat.';
                            return ['status' => 'success', 'message' => $message, 'data' => ['waktu' => $waktu, 'type' => 'pulang', 'status' => $status_pulang]];
                        }
                    } else {
                        $message = 'Anda belum absen masuk hari ini. Absensi pulang tidak dapat dicatat.';
                        return ['status' => 'error', 'message' => $message];
                    }
                }
            }

        } catch (PDOException $e) {
            error_log("Error recording guru attendance: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Gagal mencatat absensi guru.'];
        }
    }

    // ... metode lainnya ...
}
