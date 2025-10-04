<?php
// project-root/guru/dashboard.php

require_once __DIR__ . '/../config/database.php'; // Atau constants.php jika koneksi ada di sana
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php'; // Pastikan file ini ada dan berisi fungsi-fungsi hitung, termasuk isGuruCheckedInToday()
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Guru.php'; // Diperlukan untuk mengambil nama guru
require_once __DIR__ . '/../classes/JadwalPelajaran.php'; // Tambahkan ini untuk kelas JadwalPelajaran
require_once __DIR__ . '/../classes/Absensi.php'; // Pastikan kelas Absensi di-require

// Pastikan pengguna sudah login dan memiliki peran Guru
require_login();
require_role('Guru');

$error_message = flash('error');
$success_message = flash('success');

$guru_data = null;
$pdo = null; // Inisialisasi $pdo
$jadwal_hari_ini = []; // Inisialisasi array untuk jadwal hari ini

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection(); // Pastikan ini mengembalikan objek PDO

    $guru_model = new Guru($pdo); // Pastikan $pdo tersedia di sini
    $jadwal_model = new JadwalPelajaran($pdo); // Inisialisasi model JadwalPelajaran

    $related_id = $_SESSION['related_id'] ?? null;

    if (!$related_id) {
        set_flash('error', 'Data guru tidak ditemukan untuk akun Anda. Silakan hubungi administrator.');
        redirect(ROOT_URL . 'logout.php'); // Arahkan ke logout jika data terkait tidak ada
    }

    $guru_data = $guru_model->findById($related_id);
    if (!$guru_data) {
        set_flash('error', 'Data guru tidak ditemukan. Silakan hubungi administrator.');
        redirect(ROOT_URL . 'logout.php');
    }

    // Ambil jadwal pelajaran guru untuk hari ini
    // Asumsi ada fungsi di JadwalPelajaran yang bisa mengambil jadwal berdasarkan guru_id dan hari
    // Anda mungkin perlu menambahkan fungsi ini di kelas JadwalPelajaran jika belum ada.
    // Contoh: public function getJadwalByGuruAndDay($guruId, $dayOfWeek) { ... }
    $hari_ini = date('N'); // Mengambil hari dalam angka (1=Senin, 7=Minggu)
    $nama_hari_ini = getDayName($hari_ini); // Fungsi helper untuk mendapatkan nama hari (misal: "Senin")
    $jadwal_hari_ini = $jadwal_model->getJadwalByGuruAndDay($guru_data['id'], $nama_hari_ini);


} catch (PDOException $e) {
    error_log("Guru Dashboard Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
    // Tidak redirect, hanya tampilkan pesan error
} catch (Exception $e) {
    error_log("Guru Dashboard General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    // Tidak redirect, hanya tampilkan pesan error
}

// --- Cek status absensi guru hari ini ---
$is_guru_checked_in = false;
$is_guru_checked_out = false;
$absensi_model = null;

if (isset($_SESSION['related_id']) && $pdo) {
    // Pastikan kelas Absensi diinisialisasi
    $absensi_model = new Absensi($pdo);

    // Periksa status absensi masuk dan pulang hari ini
    $is_guru_checked_in = $absensi_model->hasAbsenMasukHariIni($_SESSION['related_id']);
    $is_guru_checked_out = $absensi_model->hasAbsenKeluarHariIni($_SESSION['related_id']);
}

// --- Mengambil data statistik khusus guru ---
// Asumsi fungsi-fungsi ini ada di includes/helpers.php
$total_mapel_diampu = getTotalMapelDiampuGuru($guru_data['id'] ?? null); // Asumsi ID guru ada di $guru_data['id']
$total_kelas_diampu = getTotalKelasDiampuGuru($guru_data['id'] ?? null);
$siswa_hadir_mapel_hari_ini = getSiswaHadirMapelHariIni($guru_data['id'] ?? null);
$siswa_belum_hadir_mapel_hari_ini = getSiswaBelumHadirMapelHariIni($guru_data['id'] ?? null);
// --- Akhir pengambilan data statistik ---


// Sertakan header guru yang baru
require_once __DIR__ . '/../components/header_guru.php';
?>

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <h1 class="text-3xl font-semibold text-gray-800 mb-4 sm:mb-0">Dashboard Guru</h1>
            <div class="text-gray-600 text-right">
                <p>Selamat datang, <span class="font-bold"><?php echo htmlspecialchars($guru_data['nama_lengkap'] ?? 'Guru'); ?></span>!</p>
                <p>Anda login sebagai: <span class="font-bold"><?php echo htmlspecialchars($_SESSION['role_name'] ?? 'Tidak Dikenal'); ?></span>.</p>
            </div>
        </div>

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

        <div class="bg-white rounded-lg shadow-md p-6 mb-8 border border-gray-200">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Status Absensi Anda Hari Ini</h2>
    <?php if (!$is_guru_checked_in || ($is_guru_checked_in && !$is_guru_checked_out)): ?>
        <div class="bg-blue-600 text-white rounded-xl shadow-lg p-6 mb-8 text-center">
            <h1 class="text-3xl font-bold mb-2">Absensi <?php echo !$is_guru_checked_in ? 'Masuk' : 'Pulang'; ?> via QR Code</h1>
            <p class="text-gray-100">Arahkan kamera ke QR Code yang tersedia di pintu <?php echo !$is_guru_checked_in ? 'masuk' : 'keluar'; ?> sekolah.</p>
            
            <div id="check-in-message-container" class="mt-4"></div>
    
            <div id="qr-scanner-container" class="mt-6 mb-4 relative mx-auto max-w-sm h-72 rounded-xl overflow-hidden shadow-2xl bg-gray-900 hidden">
                <div id="reader" class="w-full h-full"></div>
            </div>
    
            <p id="qr-code-status" class="text-gray-100 font-medium"></p>
            
            <?php if (!$is_guru_checked_in): ?>
                <button id="start-qr-button" class="mt-4 bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-6 rounded-full transition duration-300 focus:outline-none focus:shadow-outline">
                    <i class="fas fa-qrcode mr-2"></i> Mulai Scan QR Code (Masuk)
                </button>
            <?php else: ?>
                <button id="start-qr-checkout-button" class="mt-4 bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-6 rounded-full transition duration-300 focus:outline-none focus:shadow-outline">
                    <i class="fas fa-qrcode mr-2"></i> Mulai Scan QR Code (Pulang)
                </button>
            <?php endif; ?>
            
            <button id="stop-qr-button" class="mt-4 bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-6 rounded-full transition duration-300 focus:outline-none focus:shadow-outline hidden">
                <i class="fas fa-camera-slash mr-2"></i> Tutup Kamera
            </button>
        </div>
    <?php endif; ?>
</div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-8 border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Jadwal Pelajaran Anda Hari Ini (<?php echo htmlspecialchars($nama_hari_ini); ?>)</h2>
            <?php if (!empty($jadwal_hari_ini)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">Waktu</th>
                                <th class="py-3 px-6 text-left">Mata Pelajaran</th>
                                <th class="py-3 px-6 text-left">Kelas</th>
                                <th class="py-3 px-6 text-left">Ruangan</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            <?php foreach ($jadwal_hari_ini as $jadwal): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo substr(htmlspecialchars($jadwal['jam_mulai']), 0, 5) . ' - ' . substr(htmlspecialchars($jadwal['jam_selesai']), 0, 5); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['nama_mapel']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['nama_kelas']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['ruangan'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600">Tidak ada jadwal pelajaran untuk Anda hari ini.</p>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Mata Pelajaran Diampu</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($total_mapel_diampu); ?></p>
                </div>
                <i class="fas fa-book-open text-5xl text-blue-400 opacity-75"></i>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Kelas Diampu</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($total_kelas_diampu); ?></p>
                </div>
                <i class="fas fa-chalkboard text-5xl text-green-400 opacity-75"></i>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Siswa Hadir Hari Ini (Mapel)</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($siswa_hadir_mapel_hari_ini); ?></p>
                </div>
                <i class="fas fa-user-check text-5xl text-teal-400 opacity-75"></i>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Siswa Belum Hadir Hari Ini (Mapel)</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($siswa_belum_hadir_mapel_hari_ini); ?></p>
                </div>
                <i class="fas fa-user-times text-5xl text-red-400 opacity-75"></i>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Akses Cepat</h2>
                <div class="space-y-4">
                    <?php if ($is_guru_checked_in): ?>
                        <a href="<?php echo ROOT_URL; ?>guru/absensi_mapel.php" class="flex items-center justify-between p-4 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition duration-300 shadow-md">
                            <span class="font-medium">Catat Absensi Mata Pelajaran</span>
                            <i class="fas fa-clipboard-check"></i>
                        </a>
                    <?php else: ?>
                        <div class="flex items-center justify-between p-4 bg-gray-300 text-gray-600 rounded-lg cursor-not-allowed opacity-70">
                            <span class="font-medium">Catat Absensi Mata Pelajaran</span>
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <p class="text-sm text-red-500 mt-2">Anda harus absen masuk terlebih dahulu untuk mengaktifkan menu ini.</p>
                    <?php endif; ?>

                    <a href="<?php echo ROOT_URL; ?>guru/jadwal_saya.php" class="flex items-center justify-between p-4 bg-green-500 hover:bg-green-600 text-white rounded-lg transition duration-300 shadow-md">
                        <span class="font-medium">Lihat Jadwal Mengajar</span>
                        <i class="fas fa-calendar-alt"></i>
                    </a>
                    <a href="<?php echo ROOT_URL; ?>guru/laporan_absensi_siswa.php" class="flex items-center justify-between p-4 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition duration-300 shadow-md">
                        <span class="font-medium">Akses Laporan Absensi Siswa</span>
                        <i class="fas fa-chart-bar"></i>
                    </a>
                </div>
            </div>
        </div>
        
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const qrScannerContainer = document.getElementById('qr-scanner-container');
        const startQrButton = document.getElementById('start-qr-button');
        const startQrCheckoutButton = document.getElementById('start-qr-checkout-button');
        const qrCodeStatus = document.getElementById('qr-code-status');
        const stopQrButton = document.getElementById('stop-qr-button');
        const messageContainer = document.getElementById('check-in-message-container');
        
        let html5QrCode = null;
        let scanType = ''; // 'check_in' or 'check_out'

        function startQrScanner(type) {
            scanType = type;
            messageContainer.innerHTML = '';
            qrCodeStatus.textContent = "Memulai kamera...";
            
            if (startQrButton) startQrButton.classList.add('hidden');
            if (startQrCheckoutButton) startQrCheckoutButton.classList.add('hidden');
            stopQrButton.classList.remove('hidden');
            qrScannerContainer.classList.remove('hidden');

            const qrboxFunction = (viewfinderWidth, viewfinderHeight) => {
                const minEdgePercentage = 0.7;
                const minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
                const qrboxSize = Math.floor(minEdgeSize * minEdgePercentage);
                return {
                    width: qrboxSize,
                    height: qrboxSize
                };
            };
            
            html5QrCode = new Html5Qrcode("reader");
            
            html5QrCode.start(
                { facingMode: "environment" },
                { 
                    fps: 10, 
                    qrbox: qrboxFunction
                },
                (decodedText) => onScanSuccess(decodedText),
                (errorMessage) => onScanFailure(errorMessage)
            )
            .catch(err => {
                qrCodeStatus.textContent = "Gagal mengakses kamera. Pastikan browser Anda memiliki izin.";
                console.error("Gagal memulai kamera:", err);
                stopQrScanner();
            });
        }

        function stopQrScanner() {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().then(() => {
                    qrCodeStatus.textContent = "Pemindai berhenti.";
                }).catch(err => {
                    console.error("Gagal menghentikan kamera:", err);
                });
            }
            stopQrButton.classList.add('hidden');
            if (startQrButton) startQrButton.classList.remove('hidden');
            if (startQrCheckoutButton) startQrCheckoutButton.classList.remove('hidden');
            qrScannerContainer.classList.add('hidden');
        }

        function onScanSuccess(decodedText) {
            qrCodeStatus.textContent = "QR Code terdeteksi! Mengirim data...";
            stopQrScanner();
            sendAbsenceRequest(decodedText, scanType);
        }

        function onScanFailure(error) {
            // Biarkan kosong untuk menghindari spamming log
        }
        
        async function sendAbsenceRequest(token, type) {
            const endpoint = type === 'check_in' ? 'guru_check_in.php' : 'guru_check_out.php';
            const message = type === 'check_in' ? 'Memproses absensi masuk...' : 'Memproses absensi pulang...';
            
            showMessage(message, 'bg-blue-500');

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    async (position) => {
                        const lat = position.coords.latitude;
                        const long = position.coords.longitude;
                        
                        const formData = new FormData();
                        formData.append('qr_token', token);
                        formData.append('user_lat', lat);
                        formData.append('user_long', long);

                        try {
                            const response = await fetch(`<?php echo ROOT_URL; ?>api/${endpoint}`, {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();

                            if (result.success) {
                                showMessage(result.message, 'bg-green-500');
                            } else {
                                showMessage(result.message, 'bg-red-500');
                            }
                            
                            setTimeout(() => {
                                window.location.reload();
                            }, 3000);
                        } catch (error) {
                            showMessage('Terjadi kesalahan saat memproses absensi.', 'bg-red-500');
                            console.error('Absence request failed:', error);
                        }
                    },
                    (error) => {
                        showMessage("Gagal mendapatkan lokasi GPS. Absensi dibatalkan.", 'bg-red-500');
                        console.error('Geolocation failed:', error);
                    },
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                );
            } else {
                showMessage("Geolokasi tidak didukung oleh browser ini. Absensi dibatalkan.", 'bg-red-500');
            }
        }

        function showMessage(message, colorClass) {
            messageContainer.innerHTML = `
                <div class="p-4 rounded-lg text-white font-semibold transition-all duration-300 ${colorClass}">
                    ${message}
                </div>
            `;
        }

        // Event listeners
        if (startQrButton) {
            startQrButton.addEventListener('click', () => startQrScanner('check_in'));
        }
        if (startQrCheckoutButton) {
            startQrCheckoutButton.addEventListener('click', () => startQrScanner('check_out'));
        }
        if (stopQrButton) {
            stopQrButton.addEventListener('click', stopQrScanner);
        }
    });
</script>

<?php
// Sertakan footer (yang akan menutup div konten utama dan div utama)
require_once __DIR__ . '/../components/footer.php';
?>