<?php
// project-root/includes/whatsapp_notifier.php

// Hapus require_once ini karena PDO dan SchoolSettings akan dilewatkan sebagai parameter
// require_once __DIR__ . '/../classes/Database.php';
// require_once __DIR__ . '/../classes/SchoolSettings.php';

/**
 * Mengirim notifikasi WhatsApp menggunakan MPedia WhatsApp Gateway API.
 * Konfigurasi API (URL, Key, Sender Number) diambil dari pengaturan sekolah.
 *
 * @param PDO $pdo Koneksi database PDO yang sudah ada.
 * @param array $settings Pengaturan sekolah yang sudah dimuat.
 * @param string $recipientPhoneNumber Nomor telepon penerima (format internasional, misal: "6281234567890" tanpa '+').
 * @param string $templateName Nama template pesan (digunakan sebagai identifikasi internal, tidak dikirim ke API MPedia).
 * @param array $templateParameters Array parameter untuk mengisi pesan.
 * @return array Hasil pengiriman (status, pesan).
 */
function sendWhatsAppNotification(PDO $pdo, array $settings, $recipientPhoneNumber, $templateName, $templateParameters = []) {
    try {
        // Periksa apakah pengaturan WhatsApp API tersedia (gunakan $settings yang dilewatkan)
        if (empty($settings['whatsapp_api_url']) || empty($settings['whatsapp_api_key']) || empty($settings['whatsapp_sender_number'])) {
            error_log("WhatsApp API settings are incomplete in school settings.");
            return ['status' => 'error', 'message' => 'Konfigurasi WhatsApp API tidak lengkap di pengaturan sekolah.'];
        }

        $api_url = $settings['whatsapp_api_url'];
        $api_key = $settings['whatsapp_api_key'];
        $sender_number = $settings['whatsapp_sender_number'];

        // Pastikan nomor penerima dalam format yang benar (tanpa '+')
        $recipientPhoneNumber = ltrim($recipientPhoneNumber, '+'); // Hapus '+' jika ada
        
        // Tanggal hari ini dalam format Indonesia
        $today_date_indonesian = date('d M Y');

        // Konstruksi pesan dari template name dan parameters
        // Karena MPedia API mengirim pesan teks biasa, kita akan menggabungkan parameter
        $message_text = "";
        switch ($templateName) {
            case 'absensi_notifikasi':
                // Asumsi $templateParameters berisi: [nama_pengguna, tipe_absensi_teks, waktu_absensi, status_kehadiran]
                $nama_pengguna = $templateParameters[0] ?? 'Pengguna';
                $tipe_absensi_teks = $templateParameters[1] ?? 'absensi';
                $waktu_absensi = $templateParameters[2] ?? date('H:i');
                $status_kehadiran = $templateParameters[3] ?? 'Hadir'; // Default jika tidak ada
                $message_text = "🔔 *Notifikasi Absensi Siswa* 🔔 \n\nHalo, orang tua/wali dari ananda *{$nama_pengguna}* .\n\nAbsensi *{$tipe_absensi_teks}* ananda telah berhasil dicatat pada pukul *{$waktu_absensi}* WIB hari ini, {$today_date_indonesian}. Status: *{$status_kehadiran}*";
                if ($tipe_absensi_teks === 'masuk') {
                    $message_text .= "\n\nSemoga ananda dapat mengikuti pelajaran dengan baik dan semangat hari ini!";
                } elseif ($tipe_absensi_teks === 'pulang') {
                    $message_text .= "\n\nTerima kasih atas perhatiannya. Selamat beraktifitas kembali!";
                }
                $message_text .= "\n\n_Pesan ini dikirim otomatis oleh sistem absensi sekolah._";
                break;
                
            case 'absensi_notifikasi_guru':
                // Asumsi $templateParameters berisi: [nama_pengguna, tipe_absensi_teks, waktu_absensi, status_kehadiran]
                $nama_pengguna = $templateParameters[0] ?? 'Pengguna';
                $tipe_absensi_teks = $templateParameters[1] ?? 'absensi';
                $waktu_absensi = $templateParameters[2] ?? date('H:i');
                $status_kehadiran = $templateParameters[3] ?? 'Hadir'; // Default jika tidak ada
                $message_text = "📝 *Absensi Anda Terkonfirmasi!* 📝\n\nHalo Bapak/Ibu *{$nama_pengguna}*,\n\nAbsensi *{$tipe_absensi_teks}* Anda telah berhasil dicatat pada pukul *{$waktu_absensi}* WIB hari ini, {$today_date_indonesian}. Status: *{$status_kehadiran}*";
                if ($tipe_absensi_teks === 'masuk') {
                    $message_text .= "\n\nSelamat bertugas dan semoga hari Anda produktif!";
                } elseif ($tipe_absensi_teks === 'pulang') {
                    $message_text .= "\n\nTerima kasih atas dedikasi Anda hari ini. Selamat beristirahat!";
                }
                $message_text .= "\n\n_Pesan ini dikirim otomatis oleh sistem absensi sekolah._";
                break;
                
            case 'absensi_notifikasi_guru_kepsek':
                // Asumsi $templateParameters berisi: [nama_pengguna, tipe_absensi_teks, waktu_absensi, status_kehadiran]
                $nama_pengguna = $templateParameters[0] ?? 'Pengguna';
                $tipe_absensi_teks = $templateParameters[1] ?? 'absensi';
                $waktu_absensi = $templateParameters[2] ?? date('H:i');
                $status_kehadiran = $templateParameters[3] ?? 'Hadir'; // Default jika tidak ada
                $message_text = "🚨 *Laporan Absensi Guru* 🚨 \n\nHalo Kepala Sekolah,\n\nBapak/Ibu *{$nama_pengguna}* telah melakukan absensi *{$tipe_absensi_teks}* pada pukul *{$waktu_absensi}* WIB hari ini, {$today_date_indonesian}. Status: *{$status_kehadiran}*";
                $message_text .= "\n\nUntuk detail lebih lanjut, silakan cek laporan absensi guru di sistem.\n\n_Pesan ini dikirim otomatis oleh sistem absensi sekolah._";
                break;
                
            case 'absensi_notifikasi_guru_pulang':
                // Asumsi $templateParameters berisi: [nama_pengguna, tipe_absensi_teks, waktu_absensi, status_kehadiran]
                $nama_pengguna = $templateParameters[0] ?? 'Pengguna';
                $tipe_absensi_teks = $templateParameters[1] ?? 'absensi';
                $waktu_absensi = $templateParameters[2] ?? date('H:i');
                $status_kehadiran = $templateParameters[3] ?? 'Hadir'; // Default jika tidak ada
                $message_text = "📝 *Absensi Anda Terkonfirmasi!* 📝\n\nHalo Bapak/Ibu *{$nama_pengguna}*,\n\nAbsensi *{$tipe_absensi_teks}* Anda telah berhasil dicatat pada pukul *{$waktu_absensi}* WIB hari ini, {$today_date_indonesian}. Status: *{$status_kehadiran}*";
                if ($tipe_absensi_teks === 'masuk') {
                    $message_text .= "\n\nSelamat bertugas dan semoga hari Anda produktif!";
                } elseif ($tipe_absensi_teks === 'pulang') {
                    $message_text .= "\n\nTerima kasih atas dedikasi Anda hari ini. Selamat beristirahat!";
                }
                $message_text .= "\n\n_Pesan ini dikirim otomatis oleh sistem absensi sekolah._";
                break;
                
            case 'absensi_notifikasi_guru_kepsek_pulang':
                // Asumsi $templateParameters berisi: [nama_pengguna, tipe_absensi_teks, waktu_absensi, status_kehadiran]
                $nama_pengguna = $templateParameters[0] ?? 'Pengguna';
                $tipe_absensi_teks = $templateParameters[1] ?? 'absensi';
                $waktu_absensi = $templateParameters[2] ?? date('H:i');
                $status_kehadiran = $templateParameters[3] ?? 'Hadir'; // Default jika tidak ada
                $message_text = "🚨 *Laporan Absensi Guru* 🚨 \n\nHalo Kepala Sekolah,\n\nBapak/Ibu *{$nama_pengguna}* telah melakukan absensi *{$tipe_absensi_teks}* pada pukul *{$waktu_absensi}* WIB hari ini, {$today_date_indonesian}. Status: *{$status_kehadiran}*";
                $message_text .= "\n\nUntuk detail lebih lanjut, silakan cek laporan absensi guru di sistem.\n\n_Pesan ini dikirim otomatis oleh sistem absensi sekolah._";
                break;
                
                // ... di dalam fungsi sendWhatsAppNotification, setelah case 'absensi_notifikasi_kepsek_guru':

            case 'absensi_notifikasi_manual_siswa':
                // Parameter: [nama_siswa, status_absensi, tanggal_absensi, waktu_pencatatan_manual]
                $nama_siswa = $templateParameters[0] ?? 'Siswa';
                $status_absensi = $templateParameters[1] ?? 'Tidak Diketahui';
                $tanggal_absensi = $templateParameters[2] ?? date('Y-m-d');
                $waktu_pencatatan = $templateParameters[3] ?? date('H:i');
                $message_text = "🔔 *Pemberitahuan Absensi Manual Siswa* 🔔\n\nHalo, orang tua/wali dari ananda *{$nama_siswa}*.\n\nAbsensi ananda pada tanggal *{$tanggal_absensi}* telah dicatat secara manual oleh admin dengan status: *{$status_absensi}*. (Dicatat pukul {$waktu_pencatatan} WIB).\n\n_Pesan ini dikirim otomatis oleh sistem absensi sekolah._";
                break;
            
            case 'absensi_notifikasi_manual_guru':
                // Parameter: [nama_guru, status_absensi, tanggal_absensi, waktu_pencatatan_manual]
                $nama_guru = $templateParameters[0] ?? 'Guru';
                $status_absensi = $templateParameters[1] ?? 'Tidak Diketahui';
                $tanggal_absensi = $templateParameters[2] ?? date('Y-m-d');
                $waktu_pencatatan = $templateParameters[3] ?? date('H:i');
                $message_text = "📝 *Pemberitahuan Absensi Manual Anda* 📝\n\nHalo Bapak/Ibu *{$nama_guru}*,\n\nAbsensi Anda pada tanggal *{$tanggal_absensi}* telah dicatat secara manual oleh admin dengan status: *{$status_absensi}*. (Dicatat pukul {$waktu_pencatatan} WIB).\n\n_Pesan ini dikirim otomatis oleh sistem absensi sekolah._";
                break;
            
            case 'absensi_notifikasi_manual_guru_kepsek':
                // Parameter: [nama_guru, status_absensi, tanggal_absensi, waktu_pencatatan_manual]
                $nama_guru = $templateParameters[0] ?? 'Guru';
                $status_absensi = $templateParameters[1] ?? 'Tidak Diketahui';
                $tanggal_absensi = $templateParameters[2] ?? date('Y-m-d');
                $waktu_pencatatan = $templateParameters[3] ?? date('H:i');
                $message_text = "🚨 *Laporan Absensi Manual Guru* 🚨\n\nHalo Kepala Sekolah,\n\nAbsensi Bapak/Ibu *{$nama_guru}* pada tanggal *{$tanggal_absensi}* telah dicatat secara manual oleh admin dengan status: *{$status_absensi}*. (Dicatat pukul {$waktu_pencatatan} WIB).\n\n_Pesan ini dikirim otomatis oleh sistem absensi sekolah._";
                break;
                
            case 'absensi_mingguan_rekap':
                // Asumsi $templateParameters berisi: [nama_siswa, rekapitulasi_teks]
                $nama_siswa = $templateParameters[0] ?? 'Siswa';
                $rekapitulasi_teks = $templateParameters[1] ?? 'Tidak ada data.';
                
                $message_text = "🔔 *Notifikasi Rekapitulasi Absensi Mingguan* 🔔 \n\nHalo, orang tua/wali dari ananda *{$nama_siswa}*. \n\nBerikut adalah rekapitulasi absensi ananda selama satu minggu terakhir:\n\n{$rekapitulasi_teks}\n\nSemoga ananda dapat selalu hadir dan bersemangat dalam menimba ilmu.\n\n_Pesan ini dikirim otomatis oleh sistem absensi pesantren._";
                break;
                
            case 'absensi_mapel_rekap':
            // Asumsi $templateParameters berisi: [rekapitulasi_teks]
                $rekapitulasi_teks = $templateParameters[0] ?? 'Tidak ada data.';
                $message_text = $rekapitulasi_teks;
                break;
    
            // --- TEMPLATE BARU UNTUK HAFALAN ---
            case 'notifikasi_hafalan_siswa':
                // Parameter: [nama_siswa, nama_kitab, bagian_hafalan, nilai, nama_guru]
                $nama_siswa = $templateParameters[0] ?? 'Siswa';
                $nama_kitab = $templateParameters[1] ?? 'Kitab';
                $bagian_hafalan = $templateParameters[2] ?? 'Bagian Hafalan';
                $nilai = $templateParameters[3] ?? 'Tidak Diketahui';
                $nama_guru = $templateParameters[4] ?? 'Guru';
                $message_text = "🌟 *Laporan Setoran Hafalan Siswa* 🌟\n\nHalo, orang tua/wali dari ananda *{$nama_siswa}*.\n\nAnanda telah berhasil menyetorkan hafalan *{$nama_kitab}* bagian *{$bagian_hafalan}* kepada Ustaz/Ustazah *{$nama_guru}* pada tanggal {$today_date_indonesian}. Nilai yang didapat: *{$nilai}*.\n\nMohon terus bimbing dan semangat ananda dalam belajar!\n\n_Pesan ini dikirim otomatis oleh sistem akademik sekolah._";
                break;

            // --- TEMPLATE BARU UNTUK PELANGGARAN ---
            case 'notifikasi_pelanggaran_siswa':
                // Parameter: [nama_siswa, nama_pelanggaran, poin_deduksi, status_tindak_lanjut, nama_petugas]
                $nama_siswa = $templateParameters[0] ?? 'Siswa';
                $nama_pelanggaran = $templateParameters[1] ?? 'Pelanggaran';
                $poin_deduksi = $templateParameters[2] ?? '0';
                $status_tindak_lanjut = $templateParameters[3] ?? 'Belum Ditindak';
                $nama_petugas = $templateParameters[4] ?? 'Petugas';
                $message_text = "🚨 *Pemberitahuan Pelanggaran Disiplin Siswa* 🚨\n\nHalo, orang tua/wali dari ananda *{$nama_siswa}*.\n\nAnanda tercatat melakukan pelanggaran disiplin: *{$nama_pelanggaran}* pada tanggal {$today_date_indonesian}. Poin deduksi: *{$poin_deduksi}*.\n\nStatus tindak lanjut: *{$status_tindak_lanjut}*. Mohon perhatian dan kerja samanya untuk membimbing ananda.\n\n_Pesan ini dikirim otomatis oleh sistem akademik sekolah._";
                break;
                
             // --- TEMPLATE BARU UNTUK IZIN KELUAR/PULANG ---
            case 'notifikasi_izin_keluar_pulang':
                // Parameter: [nama_pengguna, tipe_pengguna, tanggal_izin, jenis_izin, waktu_keluar, waktu_kembali, sampai_tanggal, keperluan, nama_pemberi_izin, status_izin]
                $nama_pengguna = $templateParameters[0] ?? 'Pengguna';
                $tipe_pengguna = $templateParameters[1] ?? 'Pengguna';
                $tanggal_izin = $templateParameters[2] ?? date('Y-m-d');
                $jenis_izin = $templateParameters[3] ?? 'Keluar';
                $waktu_keluar = $templateParameters[4] ?? 'Tidak Diketahui';
                $waktu_kembali = $templateParameters[5] ?? 'Belum Kembali';
                $sampai_tanggal = $templateParameters[6] ?? 'Tidak Ditentukan';
                $keperluan = $templateParameters[7] ?? 'Tidak Diketahui';
                $nama_pemberi_izin = $templateParameters[8] ?? 'Admin';
                $status_izin = $templateParameters[9] ?? 'Pending';
            
                $target = ($tipe_pengguna === 'Siswa') ? 'orang tua/wali dari ananda' : 'Bapak/Ibu';
                $izin_detail = '';
            
                if ($jenis_izin === 'Keluar') {
                    $izin_detail = "pada tanggal *{$tanggal_izin}* pukul *{$waktu_keluar}* WIB.\n";
                    if ($waktu_kembali !== 'Belum Kembali' && !empty($waktu_kembali)) {
                        $izin_detail .= "Waktu kembali: *{$waktu_kembali}* WIB.\n";
                    }
                } else { // Pulang
                    $izin_detail = "tercatat mengajukan izin pulang pada tanggal *{$tanggal_izin}* sampai *{$sampai_tanggal}*.\n";
                }
            
                $message_text = "🚪 *Pemberitahuan Izin {$jenis_izin}* 🚪\n\nHalo, {$target} *{$nama_pengguna}*.\n\n{$tipe_pengguna} *{$nama_pengguna}* " .
                                "tercatat mengajukan izin {$jenis_izin} {$izin_detail}\n" .
                                "Keperluan: *{$keperluan}*\n" .
                                "Diberi izin oleh: *{$nama_pemberi_izin}*\n" .
                                "Status saat ini: *{$status_izin}*\n\n" .
                                "_Pesan ini dikirim otomatis oleh sistem informasi sekolah._";
                break;
                
                // --- TEMPLATE BARU UNTUK SOROGAN SISWA ---
            case 'notifikasi_sorogan_siswa':
                // Parameter: [nama_siswa, nama_kitab, halaman_dari, halaman_sampai, status_bacaan, nilai, nama_guru]
                $nama_siswa = $templateParameters[0] ?? 'Siswa';
                $nama_kitab = $templateParameters[1] ?? 'Kitab';
                $halaman_dari = $templateParameters[2] ?? 'Halaman/Bab';
                $halaman_sampai = $templateParameters[3] ?? 'Halaman/Bab';
                $status_bacaan = $templateParameters[4] ?? 'Belum Selesai';
                $nilai = $templateParameters[5] ?? 'Tidak Ada';
                $nama_guru = $templateParameters[6] ?? 'Guru';

                $message_text = "📖 *Laporan Sorogan Bacaan Kitab* 📖\n\nHalo, orang tua/wali dari ananda *{$nama_siswa}*.\n\nAnanda telah melaksanakan sorogan kitab *{$nama_kitab}* dari *{$halaman_dari}* sampai *{$halaman_sampai}* pada tanggal {$today_date_indonesian} kepada Ustaz/Ustazah *{$nama_guru}*.\n\nStatus Bacaan: *{$status_bacaan}*\nNilai: *{$nilai}*\n\nMohon terus bimbing dan semangat ananda dalam belajar!\n\n_Pesan ini dikirim otomatis oleh sistem akademik sekolah._";
                break;
                
            case 'konfirmasi_pembayaran':
                // $templateParameters: [$nama_siswa, $nama_pembayaran, $jumlah_bayar, $sisa_tagihan_baru, $tanggal_bayar, $periode_tagihan]
                if (count($templateParameters) === 6) {
                    list($nama_siswa, $nama_pembayaran, $jumlah_bayar, $sisa_tagihan_baru, $tanggal_bayar, $periode_tagihan) = $templateParameters;
                    $message_text = "Yth. Bapak/Ibu Wali Murid,\n";
                    $message_text .= "Kami informasikan pembayaran *{$nama_pembayaran}* (Periode: *{$periode_tagihan}*) untuk ananda *{$nama_siswa}* telah kami terima.\n";
                    $message_text .= "Jumlah dibayar: *Rp {$jumlah_bayar}* pada tanggal *{$tanggal_bayar}*.\n";
                    $message_text .= "Sisa tagihan: *Rp {$sisa_tagihan_baru}*.\n";
                    $message_text .= "Terima kasih atas perhatiannya.";
                } else {
                    error_log("Parameter tidak lengkap untuk template konfirmasi_pembayaran.");
                    return ['status' => 'error', 'message' => 'Parameter notifikasi konfirmasi pembayaran tidak lengkap.'];
                }
                break;
                
            case 'pembatalan_pembayaran': // Template baru untuk pembatalan
                // $templateParameters: [$nama_siswa, $nama_pembayaran, $jumlah_dibatalkan, $sisa_tagihan_baru, $tanggal_transaksi_asli, $periode_tagihan]
                if (count($templateParameters) === 6) {
                    list($nama_siswa, $nama_pembayaran, $jumlah_dibatalkan, $sisa_tagihan_baru, $tanggal_transaksi_asli, $periode_tagihan) = $templateParameters;
                    $message_text = "Yth. Bapak/Ibu Wali Murid,\n";
                    $message_text .= "Kami memberitahukan bahwa transaksi pembayaran *{$nama_pembayaran}* (Periode: *{$periode_tagihan}*) untuk ananda *{$nama_siswa}* pada tanggal *{$tanggal_transaksi_asli}* sebesar *Rp {$jumlah_dibatalkan}* telah dibatalkan.\n";
                    $message_text .= "Sisa tagihan terbaru: *Rp {$sisa_tagihan_baru}*.\n";
                    $message_text .= "Mohon diperiksa kembali. Terima kasih.";
                } else {
                    error_log("Parameter tidak lengkap untuk template pembatalan_pembayaran.");
                    return ['status' => 'error', 'message' => 'Parameter notifikasi pembatalan pembayaran tidak lengkap.'];
                }
                break;
                
            case 'pengingat_tagihan_bulanan': // BARU: Template untuk pengingat tagihan bulanan
                // $templateParameters: [$nama_siswa, $nama_pembayaran, $periode_tagihan, $jumlah_tagihan, $sisa_tagihan, $tanggal_jatuh_tempo]
                if (count($templateParameters) === 6) {
                    list($nama_siswa, $nama_pembayaran, $periode_tagihan, $jumlah_tagihan, $sisa_tagihan, $tanggal_jatuh_tempo) = $templateParameters;
                    $message_text = "Yth. Bapak/Ibu Wali Murid,\n";
                    $message_text .= "Kami mengingatkan tagihan *{$nama_pembayaran}* (Periode: *{$periode_tagihan}*) untuk ananda *{$nama_siswa}*.\n";
                    $message_text .= "Total Tagihan: *Rp {$jumlah_tagihan}*.\n";
                    $message_text .= "Sisa yang belum dibayar: *Rp {$sisa_tagihan}*.\n";
                    $message_text .= "Jatuh Tempo: *{$tanggal_jatuh_tempo}*.\n";
                    $message_text .= "Mohon segera melakukan pembayaran. Terima kasih.";
                } else {
                    error_log("Parameter tidak lengkap untuk template pengingat_tagihan_bulanan.");
                    return ['status' => 'error', 'message' => 'Parameter notifikasi pengingat tagihan bulanan tidak lengkap.'];
                }
                break;
                
            case 'pengingat_tagihan_detail': // Template lama untuk pengingat tagihan detail
                // Expected parameters: [nama_siswa, total_outstanding_bill_formatted, detail_tagihan_text]
                $nama_siswa = $templateParameters[0] ?? 'Siswa';
                $total_outstanding_bill_formatted = $templateParameters[1] ?? '0,00';
                $detail_tagihan_text = $templateParameters[2] ?? ''; // Ini adalah string yang sudah diformat

                $message_text = "Yth. Wali Murid dari *{$nama_siswa}*,\n\n";
                $message_text .= "Kami ingin mengingatkan bahwa terdapat total tagihan yang belum lunas sebesar *Rp {$total_outstanding_bill_formatted}*.\n";
                
                if (!empty($detail_tagihan_text)) {
                    $message_text .= "\n*Rincian Tagihan:*\n";
                    $message_text .= $detail_tagihan_text; // String rincian tagihan sudah termasuk newline
                }

                $message_text .= "\nMohon segera melakukan pembayaran. Abaikan pesan ini jika Anda sudah melakukan pembayaran.\n";
                $message_text .= "Terima kasih atas perhatiannya.\n";
                $message_text .= "Hormat kami,\n" . $school_name;
                break;

            case 'pengingat_total_tagihan': // Template baru yang diminta user
                // Expected parameters: [nama_siswa, nama_tahun_ajaran, total_outstanding_formatted, payment_details_array]
                $nama_siswa = $templateParameters[0] ?? 'Siswa';
                $nama_tahun_ajaran = $templateParameters[1] ?? 'Tahun Ajaran';
                $total_outstanding_formatted = $templateParameters[2] ?? '0,00';
                $payment_details_array = $templateParameters[3] ?? []; // Ini adalah array rincian

                $message_text = "Yth. Wali Murid dari ananda *{$nama_siswa}*.\n\n";
                $message_text .= "Kami ingin memberitahukan bahwa terdapat tagihan yang belum lunas untuk Tahun Ajaran *{$nama_tahun_ajaran}* dengan total sebesar *Rp {$total_outstanding_formatted}*.\n";

                if (!empty($payment_details_array)) {
                    $message_text .= "\n*Rincian Tagihan:*\n";
                    foreach ($payment_details_array as $detail) {
                        $message_text .= "- {$detail}\n"; // Setiap detail diharapkan sudah diformat
                    }
                }

                $message_text .= "\nMohon segera melakukan pembayaran. Abaikan pesan ini jika Anda sudah melakukan pembayaran.\n";
                $message_text .= "Terima kasih atas perhatiannya.\n";
                $message_text .= "Hormat kami,\n" . $school_name;
                break;
                
            // --- Ganti case ini dengan kode yang Anda usulkan ---
            case 'pengumuman':
                // Parameter: [judul_pengumuman, isi_pengumuman]
                $judul_pengumuman = $templateParameters[0] ?? 'Pengumuman Penting';
                $isi_pengumuman = $templateParameters[1] ?? 'Detail pengumuman belum tersedia.';
                
                $message_text = "📢 *PENGUMUMAN SEKOLAH* 📢\n\n";
                $message_text .= "*{$judul_pengumuman}*\n\n";
                $message_text .= "{$isi_pengumuman}\n\n";
                $message_text .= "Mohon perhatiannya. Terima kasih.\n\n";
                $message_text .= "_Pesan ini dikirim otomatis oleh sistem sekolah._";
                break;
                
            case 'ngaji_notifikasi':
                // --- TEMPLATE UNTUK NGAJI (MENIRU TEMPLATE SOROGAN) ---
                // Parameter: [nama_siswa, jenis_materi, halaman_dari, halaman_sampai, nama_guru]
                $nama_siswa = $templateParameters[0] ?? 'Siswa';
                $jenis_materi = $templateParameters[1] ?? 'Materi';
                $halaman_dari = $templateParameters[2] ?? '-';
                $halaman_sampai = $templateParameters[3] ?? '-';
                $nama_guru = $templateParameters[4] ?? 'Guru';
                $message_text = "✍️ *Laporan Mengaji Siswa* ✍️\n\nHalo, orang tua/wali dari ananda *{$nama_siswa}*.\n\nAnanda telah selesai mengaji *{$jenis_materi}* dari *{$halaman_dari}* sampai *{$halaman_sampai}* pada tanggal {$today_date_indonesian} kepada Ustaz/Ustazah *{$nama_guru}*.\n\nTerima kasih atas perhatian dan bimbingannya.\n\n_Pesan ini dikirim otomatis oleh sistem akademik sekolah._";
                break;
                
            case 'notifikasi_absensi_mapel':
                // Parameter: [nama_siswa, nama_mapel, nama_kelas, status_kehadiran_teks, nama_guru]
                $nama_siswa = $templateParameters[0] ?? 'Siswa';
                $nama_mapel = $templateParameters[1] ?? 'Mata Pelajaran';
                $nama_kelas = $templateParameters[2] ?? 'Kelas';
                $status_kehadiran_teks = $templateParameters[3] ?? 'Tidak Diketahui';
                $nama_guru = $templateParameters[4] ?? 'Guru';
                
                $message_text = "🔔 *Pemberitahuan Absensi Mata Pelajaran* 🔔\n\nHalo, orang tua/wali dari ananda *{$nama_siswa}*.\n\nAbsensi untuk mata pelajaran *{$nama_mapel}* di kelas *{$nama_kelas}* telah dicatat pada hari ini dengan status: *{$status_kehadiran_teks}*.\n\nDicatat oleh: Ustaz/Ustazah *{$nama_guru}*.\n\n_Pesan ini dikirim otomatis oleh sistem absensi sekolah._";
                break;
                
            case 'absensi_manual_mapel':
                // Parameter: [nama_siswa, nama_mapel, nama_kelas, status_kehadiran_teks, nama_guru_pengampu]
                $nama_siswa = $templateParameters[0] ?? 'Siswa';
                $nama_mapel = $templateParameters[1] ?? 'Mata Pelajaran';
                $nama_kelas = $templateParameters[2] ?? 'Kelas';
                $status_kehadiran_teks = $templateParameters[3] ?? 'Tidak Diketahui';
                $nama_guru = $templateParameters[4] ?? 'Guru';
    
                $message_text = "🔔 *Pemberitahuan Absensi Mata Pelajaran* 🔔\n\nHalo, orang tua/wali dari ananda *{$nama_siswa}*.\n\nAbsensi untuk mata pelajaran *{$nama_mapel}* di kelas *{$nama_kelas}* telah dicatat dengan status: *{$status_kehadiran_teks}*.\n\nGuru Pengampu: *{$nama_guru}*.\n\n_Pesan ini dikirim otomatis oleh sistem absensi sekolah._";
                break;
            
                

// ... sisa kode fungsi ...
                
            // Tambahkan case lain jika Anda memiliki jenis notifikasi/template lain
            default:
                $message_text = "Notifikasi: " . implode(" ", $templateParameters);
                break;
        }

        $payload = [
            "api_key" => $api_key,
            "sender" => $sender_number,
            "number" => $recipientPhoneNumber,
            "message" => $message_text
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Tambahkan timeout 10 detik

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("WhatsApp API cURL Error: " . $curl_error);
            return ['status' => 'error', 'message' => 'Gagal terhubung ke layanan WhatsApp: ' . $curl_error];
        }

        $response_data = json_decode($response, true);

        // MPedia API mungkin memberikan respons yang berbeda dari Twilio.
        // Sesuaikan logika pengecekan status berdasarkan respons MPedia.
        // Asumsi: Jika HTTP 200 OK dan ada 'status' sukses dalam JSON.
        if ($http_code >= 200 && $http_code < 300 && isset($response_data['status']) && $response_data['status'] === true) {
            return ['status' => 'success', 'message' => 'Notifikasi WhatsApp berhasil dikirim.', 'data' => $response_data];
        } else {
            error_log("WhatsApp API Error Response (HTTP " . $http_code . "): " . $response);
            return ['status' => 'error', 'message' => 'Gagal mengirim notifikasi WhatsApp. Kode HTTP: ' . $http_code . '. Pesan: ' . ($response_data['message'] ?? 'Tidak diketahui')];
        }
    } catch (Exception $e) { // Tangkap Exception umum
        error_log("WhatsApp Notifier General Error: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Terjadi kesalahan sistem saat mengirim notifikasi WhatsApp: ' . $e->getMessage()];
    }
}

// --- Cara Menggunakan (Contoh di api/absensi_gerbang_process.php) ---
/*
// Setelah absensi berhasil dicatat:
if ($absensi_result['status'] === 'success') {
    // Asumsi $user_info sudah didapatkan sebelumnya dan berisi data siswa/guru
    // Pastikan kolom nomor telepon di database ada dan berisi nomor dalam format "628xxxx"
    $phone_number = $user_info['nomor_telepon_ortu'] ?? $user_info['nomor_telepon_guru'] ?? null;
    $nama_pengguna = $user_info['nama_lengkap'] ?? 'Seseorang';
    $waktu_absensi = date('H:i');
    $tipe_absensi_teks = ($tipe_absensi === 'masuk') ? 'masuk' : 'pulang';

    if ($phone_number) {
        // MPedia API mengharapkan nomor tanpa '+' di depan
        $phone_number_clean = ltrim($phone_number, '+');

        $template_name = 'absensi_notifikasi'; // Nama template internal kita
        $template_params = [$nama_pengguna, $tipe_absensi_teks, $waktu_absensi];

        // Contoh pemanggilan dengan PDO dan settings yang dilewatkan
        // $whatsapp_send_result = sendWhatsAppNotification($pdo, $settings, $phone_number_clean, $template_name, $template_params);

        if ($whatsapp_send_result['status'] === 'success') {
            error_log("WhatsApp notification sent for " . $nama_pengguna);
        } else {
            error_log("Failed to send WhatsApp notification for " . $nama_pengguna . ": " . $whatsapp_send_result['message']);
        }
    } else {
        error_log("Nomor telepon tidak ditemukan untuk notifikasi WhatsApp.");
    }
}
*/
