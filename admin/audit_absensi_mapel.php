<?php
// project-root/admin/audit_absensi_mapel.php

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/AbsensiMapelSiswa.php';
require_once __DIR__ . '/../classes/AbsensiMapelGuru.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/NotificationQueue.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';
require_once __DIR__ . '/../includes/whatsapp_notifier.php';

// Fitur ini khusus untuk admin, jadi batasi peran
require_login();
require_role(['Admin', 'TataUsaha']);

// --- START DEBUG ID ---
echo "";
// --- END DEBUG ID ---

$error_message = flash('error');
$success_message = flash('success');

$jadwal_id = isset($_GET['jadwal_id']) ? $_GET['jadwal_id'] : null;
$tanggal_ajar = isset($_GET['tanggal']) && !empty($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

$jadwal_model = null;
$siswa_model = null;
$absensi_mapel_siswa_model = null;
$absensi_mapel_guru_model = null;
$guru_model = null;
$pdo = null;

$jadwal_data = null;
$list_siswa_kelas = [];
$absensi_guru = null;
$current_attendance = [];
$nama_guru_pencatat = 'Admin/Tata Usaha';
$guru_id_pencatat = 0;

try {
    $pdo = Database::getInstance()->getConnection();
    $jadwal_model = new JadwalPelajaran($pdo);
    $siswa_model = new Siswa($pdo);
    $absensi_mapel_siswa_model = new AbsensiMapelSiswa($pdo);
    $absensi_mapel_guru_model = new AbsensiMapelGuru($pdo);
    $guru_model = new Guru($pdo);
    $settings_model = new SchoolSettings($pdo);

    $user = get_current_user();
    if ($user && isset($user['id']) && (int)$user['id'] > 0) {
        $guru_id_pencatat = (int)$user['id'];
        $nama_guru_pencatat = $user['nama_lengkap'];
    }

    if ($jadwal_id) {
        $jadwal_data = $jadwal_model->findByIdJadwal($jadwal_id);

    if ($jadwal_data) {
         if ($jadwal_data['tipe_jadwal'] === 'Siswa') {
         $list_siswa_kelas = $jadwal_model->getStudentsByJadwalId($jadwal_id);
    } else {
         $list_siswa_kelas = $siswa_model->getByKelasId($jadwal_data['kelas_id']);
        }

    $absensi_guru = $absensi_mapel_guru_model->getAbsensiByJadwalAndDate($jadwal_id, $tanggal_ajar);
     $current_attendance = $absensi_mapel_siswa_model->getAttendanceForSchedule($jadwal_id, $tanggal_ajar);

            // [PERBAIKAN KRITIS B]: Buat Lookup Table untuk data absensi (wajib sebelum loop HTML)
            $attendance_lookup = [];
            foreach ($current_attendance as $record) {
                $attendance_lookup[$record['siswa_id']] = $record;
            }

         // Logika untuk menandai bahwa sesi sudah selesai
         $sesi_selesai = $absensi_guru && !empty($absensi_guru['waktu_selesai_ajar']);
         }
    }

} catch (Exception $e) {
    $error_message = "Terjadi kesalahan: " . $e->getMessage();
}

function getStatusKehadiranText($status_code) {
    switch ($status_code) {
        case 'H': return 'Hadir';
        case 'I': return 'Izin';
        case 'S': return 'Sakit';
        case 'A': return 'Alpa';
        default: return 'Belum Absen';
    }
}
?>

<?php require_once __DIR__ . '/../components/header_admin.php'; ?>

<div class="min-h-screen bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-4">Audit Absensi Siswa</h2>
        
        <a href="cek_absensi_guru.php?tanggal=<?php echo htmlspecialchars($tanggal_ajar); ?>" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded mb-4">
            &larr; Kembali
        </a>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($jadwal_data): ?>
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h3 class="text-xl font-bold mb-2">Informasi Jadwal</h3>
                <p><strong>Mata Pelajaran:</strong> <?php echo htmlspecialchars($jadwal_data['nama_mapel']); ?></p>
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($jadwal_data['nama_kelas'] ?? 'Jadwal Siswa'); ?></p>
                <p><strong>Guru Pengampu:</strong> <?php echo htmlspecialchars($jadwal_data['nama_guru']); ?></p>
                <p><strong>Tanggal:</strong> <?php echo htmlspecialchars(format_tanggal_indo($tanggal_ajar)); ?></p>
                <?php if ($absensi_guru): ?>
                    <p><strong>Waktu Mulai Absen:</strong> <span class="text-green-500 font-bold"><?php echo htmlspecialchars(substr($absensi_guru['waktu_mulai_ajar'], 0, 5)); ?></span></p>
                    <p><strong>Waktu Selesai Absen:</strong> <span id="waktu-selesai-ajar" class="text-green-500 font-bold"><?php echo htmlspecialchars(substr($absensi_guru['waktu_selesai_ajar'], 0, 5) ?? 'Belum Selesai'); ?></span></p>
                <?php endif; ?>
            </div>
            

            <div class="bg-white p-4 rounded-lg shadow-md mt-6">
                <h3 class="text-xl font-bold mb-4">Daftar Siswa dan Absensi</h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full leading-normal" id="absensiTable">
                        <thead>
                            <tr>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    No
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Nama Siswa
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Status Kehadiran
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Catatan
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; ?>
                            <?php foreach ($list_siswa_kelas as $siswa): ?>
                                <?php
                                $status = 'H';
                                $catatan = '';
                                // Ganti dengan O(1) lookup
                                $attendance_record = $attendance_lookup[$siswa['id']] ?? null;
                                
                                if ($attendance_record) {
                                    $status = $attendance_record['status_kehadiran'];
                                    $catatan = $attendance_record['catatan'];
                                }
                                ?>
                                <tr>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo $i++; ?></td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <?php echo htmlspecialchars($siswa['nama_lengkap']); ?>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <select class="form-select status-select rounded-md shadow-sm" data-siswa-id="<?php echo htmlspecialchars($siswa['id']); ?>" <?php echo $sesi_selesai ? 'disabled' : ''; ?>>
                                            <option value="H" <?php echo $status == 'H' ? 'selected' : ''; ?>>Hadir</option>
                                            <option value="I" <?php echo $status == 'I' ? 'selected' : ''; ?>>Izin</option>
                                            <option value="S" <?php echo $status == 'S' ? 'selected' : ''; ?>>Sakit</option>
                                            <option value="A" <?php echo $status == 'A' ? 'selected' : ''; ?>>Alpa</option>
                                        </select>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <input type="text" class="form-input catatan-input w-full rounded-md shadow-sm" placeholder="Catatan (opsional)" value="<?php echo htmlspecialchars($catatan); ?>" data-siswa-id="<?php echo htmlspecialchars($siswa['id']); ?>" <?php echo $sesi_selesai ? 'disabled' : ''; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 text-right">
                    <button id="simpanAbsensiBtn" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded" <?php echo $sesi_selesai ? 'disabled' : ''; ?>>
                        Simpan Perubahan Absensi
                    </button>
                    <button id="selesaiMengajarBtn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded ml-2" <?php echo $sesi_selesai ? 'disabled' : ''; ?>>
                        Selesaikan Audit
                    </button>
                </div>
            </div>

        <?php else: ?>
            <p class="text-center text-gray-500">Jadwal tidak ditemukan. Silakan kembali ke halaman utama.</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
const jadwalId = <?php echo json_encode($jadwal_id); ?>;
const tanggalAjar = <?php echo json_encode($tanggal_ajar); ?>;
// Gunakan ID guru pengampu yang sebenarnya
const guruPengampuId = <?php echo json_encode($jadwal_data['guru_id'] ?? null); ?>;
// ID Admin/TU yang mencatat
const guruPencatatId = '1';

const simpanAbsensiBtn = document.getElementById('simpanAbsensiBtn');
const selesaiMengajarBtn = document.getElementById('selesaiMengajarBtn');
const waktuSelesaiAjarSpan = document.getElementById('waktu-selesai-ajar');
const statusSelects = document.querySelectorAll('.status-select');
const catatanInputs = document.querySelectorAll('.catatan-input');
const isSesiSelesai = <?php echo json_encode($sesi_selesai); ?>;

// Fungsi untuk menonaktifkan elemen form
function disableTableAndButtons() {
    statusSelects.forEach(select => select.disabled = true);
    catatanInputs.forEach(input => input.disabled = true);
    simpanAbsensiBtn.disabled = true;
    selesaiMengajarBtn.disabled = true;
}

if (isSesiSelesai) {
    disableTableAndButtons();
}

// Listener untuk tombol "Simpan Perubahan Absensi" (Sudah diperbaiki di langkah sebelumnya)
// Listener untuk tombol "Simpan Perubahan Absensi"
if (simpanAbsensiBtn) {
    simpanAbsensiBtn.addEventListener('click', function() {
        // ⭐ PERBAIKAN: Validasi data sebelum dikirim
        if (statusSelects.length === 0) {
            alert('Tidak ada siswa dalam daftar untuk disimpan.');
            return; // Hentikan proses jika daftar siswa kosong
        }

        const formData = new URLSearchParams();

        // Tambahkan data utama
        formData.append('jadwal_id', jadwalId);
        formData.append('tanggal_ajar', tanggalAjar);
        formData.append('guru_id', guruPencatatId);

        // 2. Kumpulkan data absensi siswa
        statusSelects.forEach(select => {
            const siswaId = select.dataset.siswaId;
            const status = select.value;
            const catatanInput = document.querySelector(`.catatan-input[data-siswa-id="${siswaId}"]`);
            const catatan = catatanInput ? catatanInput.value : '';

            // Menggunakan format array yang dikenal PHP
            formData.append(`attendance[${siswaId}][status_kehadiran]`, status);
            formData.append(`attendance[${siswaId}][catatan]`, catatan);
        });

        // 3. Kirim data
        fetch('<?php echo ROOT_URL; ?>api/audit_absensi_mapel_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(response => {
             // Cek jika response bukan JSON (misalnya error PHP mentah)
             const contentType = response.headers.get("content-type");
             if (contentType && contentType.indexOf("application/json") !== -1) {
                 return response.json();
             } else {
                 throw new Error("Respon server bukan format JSON.");
             }
        })
        .then(data => {
            if (data.status === 'success' || data.status === 'warning') {
                alert(data.message);
                window.location.reload(); 
            } else {
                alert('Gagal menyimpan absensi: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan jaringan atau server saat menyimpan absensi. (' + error.message + ')');
        });
    });
}
// Listener untuk tombol "Selesaikan Audit"
if (selesaiMengajarBtn) {
    selesaiMengajarBtn.addEventListener('click', function() {
        if (confirm('Apakah Anda yakin ingin menyelesaikan audit? Setelah ini, absensi tidak dapat diubah lagi.')) {
            fetch('<?php echo ROOT_URL; ?>api/audit_waktu_selesai_ajar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                // Mengirimkan ID guru pengampu, bukan ID admin
                body: `jadwal_id=${jadwalId}&guru_id=${guruPengampuId}&tanggal_ajar=${tanggalAjar}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);

                    // SOLUSI: Memastikan elemen ada sebelum mengakses textContent.
                    if (waktuSelesaiAjarSpan) {
                        waktuSelesaiAjarSpan.textContent = data.waktu_selesai_ajar.substring(0, 5);
                    }

                    disableTableAndButtons();
                    window.location.reload();
                } else {
                    alert('Gagal menandai sesi selesai: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan jaringan atau server saat menandai sesi selesai.');
            });
        }
    });
}
});
</script>

<?php
require_once __DIR__ . '/../components/footer.php';
?>