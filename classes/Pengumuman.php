<?php
// classes/Pengumuman.php

require_once __DIR__ . '/../classes/NotificationQueue.php'; 
require_once __DIR__ . '/../classes/SchoolSettings.php'; 
require_once __DIR__ . '/../classes/Siswa.php'; 

class Pengumuman {
    private $conn;
    private $siswa_model;
    private $notification_queue;

    public function __construct($db) {
        $this->conn = $db;
        $this->siswa_model = new Siswa($db);
        $this->notification_queue = new NotificationQueue($db);
    }

    /**
     * Mengirim pengumuman ke antrean notifikasi berdasarkan jenis penerima.
     * @param string $jenisPenerima 'umum', 'kelas', atau 'perorangan'
     * @param string $judul Judul pengumuman
     * @param string $pesan Pesan pengumuman
     * @param int|array|null $targetIds ID kelas, atau array ID siswa (opsional)
     * @return array Hasil pengiriman, termasuk status dan pesan
     */
    public function kirimPengumuman($jenisPenerima, $judul, $pesan, $targetIds = null) {
        $students = [];
        $sentCount = 0;
        $failedCount = 0;

        try {
            switch ($jenisPenerima) {
                case 'umum':
                    $students = $this->siswa_model->getAllPengumuman();
                    break;
                case 'kelas':
                    if (empty($targetIds)) {
                        return ['status' => 'error', 'message' => 'ID kelas tidak valid.'];
                    }
                    $students = $this->siswa_model->getByClassId($targetIds);
                    break;
                case 'perorangan':
                    // PERUBAHAN DI SINI: loop melalui array ID siswa
                    if (empty($targetIds) || !is_array($targetIds)) {
                        return ['status' => 'error', 'message' => 'ID siswa tidak valid.'];
                    }
                    foreach ($targetIds as $studentId) {
                        $student = $this->siswa_model->findById($studentId);
                        if ($student) {
                            $students[] = $student;
                        } else {
                            $failedCount++;
                            error_log("Pengumuman: Siswa dengan ID $studentId tidak ditemukan.");
                        }
                    }
                    break;
                default:
                    return ['status' => 'error', 'message' => 'Jenis penerima tidak valid.'];
            }

            if (empty($students)) {
                return ['status' => 'warning', 'message' => 'Tidak ada siswa yang ditemukan untuk pengumuman ini.'];
            }

            foreach ($students as $student) {
                if (empty($student['no_telp_ortu'])) {
                    $failedCount++;
                    error_log("Pengumuman: Nomor telepon ortu siswa '{$student['nama_lengkap']}' tidak ditemukan.");
                    continue;
                }

                $recipientPhoneNumber = ltrim($student['no_telp_ortu'], '+');
                
                $templateName = 'pengumuman';
                $templateParameters = [$judul, $pesan];

                $queue_result = $this->notification_queue->addNotificationToQueue($recipientPhoneNumber, $templateName, $templateParameters);

                if ($queue_result['status'] === 'success') {
                    $sentCount++;
                } else {
                    $failedCount++;
                    error_log("Pengumuman: Gagal menambahkan notifikasi untuk '{$student['nama_lengkap']}' ke antrean: " . $queue_result['message']);
                }
            }

            if ($sentCount > 0) {
                return ['status' => 'success', 'message' => "Pengumuman berhasil ditambahkan ke antrean untuk $sentCount penerima. $failedCount pengiriman gagal."];
            } else {
                return ['status' => 'error', 'message' => "Pengumuman gagal dikirim. Tidak ada notifikasi yang ditambahkan ke antrean."];
            }

        } catch (Exception $e) {
            error_log("Error di Pengumuman::kirimPengumuman: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Terjadi kesalahan internal saat mengirim pengumuman.'];
        }
    }
}