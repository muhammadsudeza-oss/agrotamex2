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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="/agrotamex1/assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body class="has-sidebar">

    <!-- Dark Left Sidebar (100% Samping Kiri, Exact Match with Reference Photo) -->
    <aside class="sidebar no-print" id="appSidebar">
        <!-- Sidebar Brand Header -->
        <div class="sidebar-header">
            <a href="<?php echo $root_path; ?>index.php" class="sidebar-brand">
                <img src="<?php echo $root_path; ?>assets/logo.png" alt="Logo PT" class="sidebar-logo">
                <div class="brand-text">
                    <strong>AGROTAMEX</strong>
                    <span>SUMINDO ABADI</span>
                </div>
            </a>
        </div>

        <!-- Sidebar Navigation Menu -->
        <div class="sidebar-nav">
            <ul class="sidebar-menu">
                <?php if ($role === 'manajer'): ?>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && $current_dir == 'manajer') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/manajer/index.php"><i class="fa-solid fa-chart-line fa-fw"></i> <span>Dashboard</span></a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'karyawan.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/manajer/karyawan.php"><i class="fa-solid fa-users fa-fw"></i> <span>Data Pengguna</span></a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'targets.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/manajer/targets.php"><i class="fa-solid fa-list-check fa-fw"></i> <span>Penugasan</span></a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'verifikasi.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/manajer/verifikasi.php"><i class="fa-solid fa-circle-check fa-fw"></i> <span>Verifikasi</span></a>
                    </li>
                <?php elseif ($role === 'mandor'): ?>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && $current_dir == 'mandor') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/mandor/index.php"><i class="fa-solid fa-chart-line fa-fw"></i> <span>Dashboard</span></a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'verifikasi.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/mandor/verifikasi.php"><i class="fa-solid fa-circle-check fa-fw"></i> <span>Verifikasi Kerja</span></a>
                    </li>
                <?php elseif ($role === 'karyawan'): ?>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && $current_dir == 'karyawan') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/karyawan/index.php"><i class="fa-solid fa-chart-line fa-fw"></i> <span>Dashboard</span></a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'input_laporan.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/karyawan/input_laporan.php"><i class="fa-solid fa-file-arrow-up fa-fw"></i> <span>Input Laporan</span></a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="nav-section-title">LAPORAN</div>
            <ul class="sidebar-menu">
                <?php if ($role === 'manajer'): ?>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'laporan_monitoring.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/manajer/laporan_monitoring.php"><i class="fa-solid fa-file-lines fa-fw"></i> <span>Laporan Monitoring</span></a>
                    </li>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'laporan_produktivitas.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/manajer/laporan_produktivitas.php"><i class="fa-solid fa-chart-pie fa-fw"></i> <span>Laporan Produktivitas</span></a>
                    </li>
                <?php elseif ($role === 'mandor'): ?>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'laporan.php' && $current_dir == 'mandor') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/mandor/laporan.php"><i class="fa-solid fa-file-invoice fa-fw"></i> <span>Laporan Pengawasan</span></a>
                    </li>
                <?php elseif ($role === 'karyawan'): ?>
                    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'laporan_kinerja.php') ? 'active' : ''; ?>">
                        <a href="/agrotamex1/karyawan/laporan_kinerja.php"><i class="fa-solid fa-chart-line fa-fw"></i> <span>Laporan Kinerja</span></a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <div class="sidebar-user-info">
                <i class="fa-solid fa-user-gear"></i>
                <span><?php echo htmlspecialchars($nama); ?> (<?php echo strtoupper($role); ?>)</span>
            </div>
            <div style="display: flex; gap: 8px; margin-top: 8px;">
                <a href="/agrotamex1/change_password.php" class="btn-sidebar-sub" title="Ubah Sandi"><i class="fa-solid fa-key"></i> Sandi</a>
                <a href="/agrotamex1/logout.php" class="btn-sidebar-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
            </div>
        </div>
    </aside>

    <!-- Right Main Content Area Wrapper -->
    <div class="main-wrapper">
        <!-- Minimal Top Header Bar (Only Hamburger Menu Toggle on Top Left) -->
        <header class="top-header no-print">
            <button class="hamburger-btn" id="sidebarToggle" title="Toggle Sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
        </header>

        <!-- Main Page Container -->
        <main class="container">
