<?php
// project-root/admin/kirim_pengingat_tagihan.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/TagihanSiswa.php';
require_once __DIR__ . '/../classes/NotificationQueue.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';
require_once __DIR__ . '/../includes/whatsapp_notifier.php'; // Hapus ini, karena NotificationQueue sudah meng-include-nya

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');
$info_message = flash('info');

$siswa_model = null;
$tahun_ajaran_model = null;
$kelas_model = null;
$tagihan_siswa_model = null;
$notification_queue_model = null;
$school_settings_model = null;
$pdo = null;

$all_siswa_in_class = [];
$all_tahun_ajaran = [];
$all_kelas = [];

$selected_tahun_ajaran_id = $_GET['tahun_ajaran_id'] ?? get_active_tahun_ajaran_id();
$selected_kelas_id = $_GET['kelas_id'] ?? null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $siswa_model = new Siswa($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);
    $kelas_model = new Kelas($pdo);
    $tagihan_siswa_model = new TagihanSiswa($pdo);
    $notification_queue_model = new NotificationQueue($pdo);
    $school_settings_model = new SchoolSettings($pdo);

    $all_tahun_ajaran = $tahun_ajaran_model->getAll();
    $all_kelas = $kelas_model->getAll();

    // Jika tahun ajaran dan kelas dipilih, ambil daftar siswa dan total tagihan mereka
    if ($selected_tahun_ajaran_id && $selected_kelas_id) {
        $siswa_in_selected_class = $siswa_model->getSiswaByKelasId($selected_kelas_id);
        
        foreach ($siswa_in_selected_class as $siswa) {
            $total_outstanding = $tagihan_siswa_model->getStudentTotalOutstandingBillsForAcademicYear(
                $siswa['id'], 
                $selected_tahun_ajaran_id
            );
            // Hanya tambahkan siswa jika ada tagihan yang belum lunas
            if ($total_outstanding > 0) {
                $all_siswa_in_class[] = array_merge($siswa, ['total_outstanding_bill' => $total_outstanding]);
            }
        }
    }

    // Handle aksi pengiriman pengingat dari halaman ini (menggunakan POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_reminders_batch') {
        $selected_siswa_ids = $_POST['siswa_ids'] ?? [];
        $current_selected_tahun_ajaran_id = $_POST['tahun_ajaran_id'];

        if (empty($selected_siswa_ids)) {
            set_flash('info', 'Tidak ada siswa yang dipilih untuk dikirim pengingat.');
            redirect(ROOT_URL . 'admin/kirim_pengingat_tagihan.php?tahun_ajaran_id=' . $current_selected_tahun_ajaran_id . '&kelas_id=' . $selected_kelas_id);
        }

        $processed_count = 0;
        $failed_to_queue_count = 0;
        $failed_to_send_count = 0; // Menghitung kegagalan pengiriman WA meskipun sudah di-queue
        $settings = $school_settings_model->getSettings(); // Ambil pengaturan sekolah untuk WhatsApp API

        // Dapatkan nama tahun ajaran yang dipilih
        $selected_ta_name = '';
        foreach ($all_tahun_ajaran as $ta) {
            if ($ta['id'] == $current_selected_tahun_ajaran_id) {
                $selected_ta_name = $ta['tahun_ajaran'];
                break;
            }
        }

        foreach ($selected_siswa_ids as $siswa_id_to_remind) {
            $siswa_detail = $siswa_model->findById($siswa_id_to_remind);
            if (!$siswa_detail) {
                error_log("Data siswa ID {$siswa_id_to_remind} tidak ditemukan.");
                $failed_to_queue_count++;
                continue;
            }

            if (empty($siswa_detail['no_telp_ortu'])) {
                error_log("Nomor telepon orang tua siswa {$siswa_detail['nama_lengkap']} tidak ditemukan.");
                $failed_to_queue_count++;
                continue;
            }

            $total_outstanding_bill = $tagihan_siswa_model->getStudentTotalOutstandingBillsForAcademicYear(
                $siswa_id_to_remind,
                $current_selected_tahun_ajaran_id
            );

            if ($total_outstanding_bill <= 0) {
                error_log("Siswa {$siswa_detail['nama_lengkap']} tidak memiliki tagihan belum lunas untuk TA {$selected_ta_name}.");
                continue; // Jangan kirim pengingat jika sudah lunas
            }

            // Dapatkan rincian tagihan yang belum lunas
            $detailed_outstanding_bills = $tagihan_siswa_model->getDetailedOutstandingBillsForAcademicYear(
                $siswa_id_to_remind,
                $current_selected_tahun_ajaran_id
            );

            $payment_details_array = [];
            if (!empty($detailed_outstanding_bills)) {
                foreach ($detailed_outstanding_bills as $bill) {
                    $payment_details_array[] = htmlspecialchars($bill['nama_pembayaran']) . " (" . htmlspecialchars($bill['tahun_ajaran']) . "): Rp " . number_format($bill['sisa_tagihan'], 2, ',', '.') . "";
                }
            }
            
            $recipientPhoneNumber = ltrim($siswa_detail['no_telp_ortu'], '+');
            $templateName = 'pengingat_total_tagihan';
            $templateParameters = [
                $siswa_detail['nama_lengkap'],
                $selected_ta_name,
                number_format($total_outstanding_bill, 2, ',', '.'),
                $payment_details_array // Meneruskan array rincian pembayaran
            ];

            // Panggil metode addNotificationToQueue dari NotificationQueue model
            // Ini akan mencoba mengirim dan sekaligus mencatat hasilnya ke tabel antrean
            $queue_result = $notification_queue_model->addNotificationToQueue(
                $recipientPhoneNumber,
                $templateName,
                $templateParameters,
                $siswa_id_to_remind, // recipient_user_id
                $settings, // schoolSettings
                $current_selected_tahun_ajaran_id // tahun_ajaran_id
            );

            // Periksa hasil dari proses antrean dan pengiriman
            if ($queue_result['status'] === 'success') {
                $processed_count++;
                if ($queue_result['status'] !== 'success') {
                    $failed_to_send_count++; // Hitung jika gagal dikirim meskipun berhasil di-queue
                }
            } else {
                $failed_to_queue_count++;
                error_log("Gagal memproses pengingat WA untuk siswa {$siswa_detail['nama_lengkap']}: " . ($queue_result['message'] ?? 'Unknown error'));
            }
        }

        // Pesan flash yang lebih informatif
        $final_message = [];
        if ($processed_count > 0) {
            $final_message[] = "Berhasil memproses {$processed_count} pengingat tagihan dan mencatatnya ke antrean.";
        }
        if ($failed_to_send_count > 0) {
            $final_message[] = "Ada {$failed_to_send_count} pengingat yang dicatat ke antrean, namun gagal dikirim ke WhatsApp.";
        }
        if ($failed_to_queue_count > 0) {
            $final_message[] = "Terjadi {$failed_to_queue_count} kesalahan fatal saat mencoba memproses pengingat (tidak masuk antrean atau masalah data).";
        }
        if (empty($final_message) && !empty($selected_siswa_ids)) {
             $final_message[] = 'Tidak ada pengingat yang diproses. Mungkin tidak ada tagihan belum lunas, siswa tidak memiliki nomor telepon, atau terjadi masalah pada konfigurasi API.';
        } else if (empty($selected_siswa_ids)) {
             $final_message[] = 'Tidak ada siswa yang dipilih untuk dikirim pengingat.';
        }

        if (!empty($final_message)) {
            if ($failed_to_queue_count > 0 || $failed_to_send_count > 0) {
                set_flash('error', implode(' ', $final_message));
            } else {
                set_flash('success', implode(' ', $final_message));
            }
        }

        redirect(ROOT_URL . 'admin/kirim_pengingat_tagihan.php?tahun_ajaran_id=' . $current_selected_tahun_ajaran_id . '&kelas_id=' . $selected_kelas_id);
    }

} catch (PDOException $e) {
    error_log("Kirim Pengingat Tagihan Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Kirim Pengingat Tagihan General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Kirim Pengingat Tagihan Bulanan</h1>

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

    <?php if ($info_message): ?>
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($info_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border border-gray-200">
        <h2 class="text-xl font-semibold mb-4 text-gray-800">Filter Siswa Berdasarkan Tahun Ajaran dan Kelas</h2>
        <form action="" method="GET" class="flex flex-col md:flex-row items-end md:space-x-4 space-y-4 md:space-y-0 mb-4">
            <div>
                <label for="tahun_ajaran_id" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                <select id="tahun_ajaran_id" name="tahun_ajaran_id"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                        onchange="this.form.submit()" required>
                    <option value="">Pilih Tahun Ajaran</option>
                    <?php foreach ($all_tahun_ajaran as $ta): ?>
                        <option value="<?php echo htmlspecialchars($ta['id']); ?>"
                                <?php echo ($selected_tahun_ajaran_id == $ta['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="kelas_id" class="block text-gray-700 text-sm font-bold mb-2">Kelas:</label>
                <select id="kelas_id" name="kelas_id"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                        onchange="this.form.submit()" required>
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>"
                                <?php echo ($selected_kelas_id == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="hidden md:block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                Tampilkan Siswa
            </button>
        </form>
    </div>

    <?php if ($selected_tahun_ajaran_id && $selected_kelas_id): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-8 border border-gray-200">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Daftar Siswa dengan Tagihan Belum Lunas</h2>
            <form action="" method="POST" id="sendRemindersForm">
                <input type="hidden" name="action" value="send_reminders_batch">
                <input type="hidden" name="tahun_ajaran_id" value="<?php echo htmlspecialchars($selected_tahun_ajaran_id); ?>">
                <input type="hidden" name="kelas_id" value="<?php echo htmlspecialchars($selected_kelas_id); ?>">

                <?php if (empty($all_siswa_in_class)): ?>
                    <p class="text-gray-600">Tidak ada siswa dengan tagihan belum lunas di kelas ini untuk tahun ajaran yang dipilih.</p>
                <?php else: ?>
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                    <th class="py-3 px-6 text-left">
                                        <input type="checkbox" id="checkAllSiswa" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                    </th>
                                    <th class="py-3 px-6 text-left">No.</th>
                                    <th class="py-3 px-6 text-left">NISN</th>
                                    <th class="py-3 px-6 text-left">Nama Siswa</th>
                                    <th class="py-3 px-6 text-right">Total Tagihan Belum Lunas</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 text-sm">
                                <?php $no = 1; ?>
                                <?php foreach ($all_siswa_in_class as $siswa): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-3 px-6 whitespace-nowrap">
                                            <input type="checkbox" name="siswa_ids[]" value="<?php echo htmlspecialchars($siswa['id']); ?>" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                        </td>
                                        <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                        <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['nisn']); ?></td>
                                        <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                        <td class="py-3 px-6 text-right">Rp <?php echo number_format($siswa['total_outstanding_bill'], 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Kirim Pengingat WhatsApp ke yang Dipilih
                    </button>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkAllSiswa = document.getElementById('checkAllSiswa');
        const siswaCheckboxes = document.querySelectorAll('input[name="siswa_ids[]"]');

        if (checkAllSiswa) {
            checkAllSiswa.addEventListener('change', function() {
                siswaCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
