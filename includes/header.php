<?php
// includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check login status
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: /agrotamex1/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$nama = $_SESSION['nama'];
$role = $_SESSION['role'];

// Determine path level to root for links
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$is_subfolder = ($current_dir === 'manajer' || $current_dir === 'mandor' || $current_dir === 'karyawan');
$root_path = $is_subfolder ? '../' : './';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agrotamex Productivity</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/style.css">
</head>
<body>
    <nav class="navbar no-print">
        <div class="nav-container">
            <a href="<?php echo $root_path; ?>index.php" class="brand">
                <i class="fa-solid fa-seedling" style="color: #6ec088;"></i> AGROTAMEX <span>SUMINDO ABADI</span>
            </a>
            
            <ul class="nav-menu">
                <?php if ($role === 'manajer'): ?>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && $current_dir == 'manajer') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/manajer/index.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'targets.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/manajer/targets.php"><i class="fa-solid fa-list-check"></i> Penugasan</a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'verifikasi.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/manajer/verifikasi.php"><i class="fa-solid fa-circle-check"></i> Verifikasi</a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'karyawan.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/manajer/karyawan.php"><i class="fa-solid fa-users"></i> Pengguna</a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'laporan.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/manajer/laporan.php"><i class="fa-solid fa-file-invoice"></i> Histori Laporan</a>
                    </li>
                <?php elseif ($role === 'mandor'): ?>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && $current_dir == 'mandor') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/mandor/index.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'verifikasi.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/mandor/verifikasi.php"><i class="fa-solid fa-circle-check"></i> Verifikasi Kerja</a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'laporan.php' && $current_dir == 'mandor') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/mandor/laporan.php"><i class="fa-solid fa-file-invoice"></i> Laporan Pengawasan</a>
                    </li>
                <?php elseif ($role === 'karyawan'): ?>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && $current_dir == 'karyawan') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/karyawan/index.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'input_laporan.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/karyawan/input_laporan.php"><i class="fa-solid fa-file-arrow-up"></i> Input Laporan</a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'laporan_kinerja.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/karyawan/laporan_kinerja.php"><i class="fa-solid fa-chart-line"></i> Laporan Kinerja</a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="nav-user">
                <a href="/agrotamex1/logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
            </div>
        </div>
    </nav>
    <main class="container">
        <!-- Welcome Banner (Indonesian Welcome & Role Tag) -->
        <div class="welcome-banner no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding: 12px 20px; background: var(--gold-light); border: 1px solid var(--card-border); border-radius: 8px; font-size: 0.9rem; color: var(--text-color);">
            <div>
                <i class="fa-regular fa-circle-user" style="color: var(--primary); font-size: 1.1rem; margin-right: 6px; vertical-align: middle;"></i>
                Halo, <strong><?php echo htmlspecialchars($nama); ?></strong> 
                <span class="role-tag" style="background: var(--primary); color: #fff; font-weight: 700; padding: 2px 8px; border-radius: 4px; font-size: 0.65rem; text-transform: uppercase; margin-left: 8px; border: 1px solid var(--primary-light);"><?php echo $role; ?></span>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500; display: flex; align-items: center; gap: 15px;">
                <span>
                    <i class="fa-regular fa-calendar" style="margin-right: 5px;"></i>
                    <?php 
                    // Indonesian date format helper
                    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                    echo $days[date('w')] . ', ' . date('d') . ' ' . $months[date('n')] . ' ' . date('Y');
                    ?>
                </span>
                <span style="color: var(--card-border);">|</span>
                <a href="/agrotamex1/change_password.php" style="color: var(--primary); font-weight: 600; display: flex; align-items: center; gap: 4px;"><i class="fa-solid fa-key" style="font-size: 0.8rem;"></i> Ubah Sandi</a>
            </div>
        </div>
