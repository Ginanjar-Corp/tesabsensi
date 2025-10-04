<?php
require_once 'includes/auth.php';
require_login();

$current_user = get_current_user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-school"></i> <?php echo APP_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> <?php echo $current_user['nama_lengkap']; ?>
                    (<?php echo $current_user['role_name']; ?>)
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Dashboard</h1>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Selamat Datang, <?php echo $current_user['nama_lengkap']; ?>!</h5>
                        <p class="card-text">Anda login sebagai <?php echo $current_user['role_name']; ?></p>
                        
                        <?php if ($current_user['role_name'] == 'Admin'): ?>
                            <div class="row">
                                <div class="col-md-3">
                                    <a href="admin/manage_siswa.php" class="btn btn-primary btn-block">
                                        <i class="fas fa-users"></i> Kelola Siswa
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="admin/manage_guru.php" class="btn btn-success btn-block">
                                        <i class="fas fa-chalkboard-teacher"></i> Kelola Guru
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="admin/jadwal.php" class="btn btn-info btn-block">
                                        <i class="fas fa-calendar"></i> Jadwal
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="admin/laporan.php" class="btn btn-warning btn-block">
                                        <i class="fas fa-chart-bar"></i> Laporan
                                    </a>
                                </div>
                            </div>
                        <?php elseif ($current_user['role_name'] == 'Guru'): ?>
                            <div class="row">
                                <div class="col-md-4">
                                    <a href="guru/absensi_mapel.php" class="btn btn-primary btn-block">
                                        <i class="fas fa-clipboard-check"></i> Absensi Mata Pelajaran
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="guru/jadwal_saya.php" class="btn btn-info btn-block">
                                        <i class="fas fa-calendar"></i> Jadwal Saya
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="guru/profile.php" class="btn btn-secondary btn-block">
                                        <i class="fas fa-user"></i> Profile
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>