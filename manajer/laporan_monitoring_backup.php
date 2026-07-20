<?php
// manajer/laporan_monitoring.php
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'manajer') {
    header("Location: ../index.php");
    exit;
}

$error = "";

// 1. Fetch Mandor & Karyawan for Filter Dropdowns
try {
    $stmt = $pdo->query("SELECT id_mandor, nama FROM mandor ORDER BY nama ASC");
    $foremen = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT id_karyawan, nama FROM karyawan ORDER BY nama ASC");
    $employees = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// 2. Build Query based on Filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$filter_activity = isset($_GET['aktivitas']) ? $_GET['aktivitas'] : '';
$filter_mandor = isset($_GET['id_mandor']) ? (int)$_GET['id_mandor'] : 0;
$filter_karyawan = isset($_GET['id_karyawan']) ? (int)$_GET['id_karyawan'] : 0;

$query_str = "
    SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan, m.nama as nama_mandor
    FROM work_reports r
    JOIN assignments a ON r.id_assignment = a.id
    JOIN karyawan k ON r.id_karyawan = k.id_karyawan
    JOIN mandor m ON a.id_mandor = m.id_mandor
    WHERE 1=1
";
$params = [];

if (!empty($start_date)) {
    $query_str .= " AND a.tanggal >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $query_str .= " AND a.tanggal <= ?";
    $params[] = $end_date;
}
if (!empty($filter_activity)) {
    $query_str .= " AND a.aktivitas = ?";
    $params[] = $filter_activity;
}
if ($filter_mandor > 0) {
    $query_str .= " AND a.id_mandor = ?";
    $params[] = $filter_mandor;
}
if ($filter_karyawan > 0) {
    $query_str .= " AND a.id_karyawan = ?";
    $params[] = $filter_karyawan;
}

$query_str .= " ORDER BY a.tanggal DESC, r.created_at DESC";

try {
    $stmt = $pdo->prepare($query_str);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error = "Gagal memuat laporan: " . $e->getMessage();
}

// Calculate statistics from the filtered reports
$total_masuk = count($reports);
$total_pending = 0;
$total_diverifikasi = 0;
$total_disetujui = 0;
$total_ditolak = 0;
$total_tanpa_foto = 0;
$total_tanpa_gps = 0;

if (!empty($reports)) {
    foreach ($reports as $rep) {
        // Status counts for Ringkasan Monitoring
        if ($rep['status'] === 'pending_mandor' || $rep['status'] === 'verified_by_mandor' || $rep['status'] === 'pending_manajer_tolak') {
            $total_pending++;
        }
        if ($rep['status'] === 'verified_by_mandor') {
            $total_diverifikasi++;
        }
        if ($rep['status'] === 'approved') {
            $total_disetujui++;
        }
        if ($rep['status'] === 'rejected') {
            $total_ditolak++;
        }
        if (empty($rep['foto_bukti'])) {
            $total_tanpa_foto++;
        }
        
        // GPS detection
        $lat = '';
        $lng = '';
        if (preg_match('/\[Lokasi GPS(?:\s*Terkunci)?:\s*Lat\s*([eE\d\.-]+)\s*\|\s*Lng\s*([eE\d\.-]+)\]/is', $rep['catatan_karyawan'], $matches)) {
            $lat = trim($matches[1]);
            $lng = trim($matches[2]);
        }
        if (empty($lat) || empty($lng)) {
            $total_tanpa_gps++;
        }
    }
}
?>

<!-- Welcome/Header Banner -->
<div style="margin-bottom: 25px;" class="no-print">
    <a href="index.php" style="color: var(--primary-dark); font-weight: 600; text-decoration: none; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 15px; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-light)'" onmouseout="this.style.color='var(--primary-dark)'">
        <i class="fa-solid fa-arrow-left-long"></i> Kembali ke Dashboard
    </a>
    
    <div class="card glass-panel" style="background: linear-gradient(135deg, var(--primary-dark) 0%, #113013 100%); border-left: 5px solid var(--primary-light); color: #fff; padding: 20px 25px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: relative; overflow: hidden; margin: 0;">
        <div style="position: absolute; right: -15px; top: -20px; opacity: 0.1; font-size: 6rem; color: #fff; transform: rotate(10deg); pointer-events: none;">
            <i class="fa-solid fa-desktop"></i>
        </div>
        <div style="position: relative; z-index: 2;">
            <h2 style="margin: 0; font-size: 1.45rem; color: #fff; font-weight: 700;">Laporan Pengawasan &amp; Monitoring Lapangan</h2>
            <p style="margin: 6px 0 0 0; font-size: 0.85rem; color: #d0e7da; line-height: 1.4; font-weight: normal;">
                Memantau keselarasan operasional fisik di lahan sawit secara visual melalui dokumentasi bukti lapangan, catatan kendala, dan koordinat GPS Nominatim.
            </p>
        </div>
    </div>
</div>

<!-- Kop Surat & Judul Laporan (Hanya Terlihat Saat Cetak/PDF) -->
<div class="print-only" style="margin-bottom: 20px;">
    <div style="display: flex; align-items: center; justify-content: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px;">
        <div style="text-align: center; width: 100%;">
            <h2 style="font-family: 'Times New Roman', Times, serif; font-size: 1.6rem; font-weight: bold; margin: 0; color: #000; letter-spacing: 1px;">PT AGROTAMEX SUMINDO ABADI</h2>
            <p style="font-family: 'Times New Roman', Times, serif; font-size: 0.85rem; margin: 5px 0 0 0; color: #555;">Desa Nyogan, Kecamatan Mestong, Kabupaten Muaro Jambi, Provinsi Jambi.</p>
        </div>
    </div>
    
    <div style="text-align: center; margin-bottom: 25px;">
        <h3 style="font-family: 'Times New Roman', Times, serif; font-size: 1.3rem; font-weight: bold; text-decoration: underline; margin: 0 0 5px 0; color: #000;">
            LAPORAN MONITORING &amp; PENGAWASAN KERJA
        </h3>
        <p style="font-size: 0.85rem; color: #444; margin: 0;">
            Tanggal Cetak: <?php echo date('d-m-Y H:i'); ?> WIB 
            <?php if (!empty($start_date) || !empty($end_date)): ?>
                | Periode: <?php echo date('d-m-Y', strtotime($start_date)); ?> s/d <?php echo date('d-m-Y', strtotime($end_date)); ?>
            <?php endif; ?>
        </p>
    </div>

    <!-- Ringkasan Parameter Laporan Cetak/PDF -->
    <div style="margin-bottom: 25px; font-size: 0.9rem;">
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #000;">
            <tr style="background-color: #f5f5f5;">
                <td style="border: 1px solid #000; padding: 6px 10px; font-weight: bold; width: 45%;">Ringkasan Parameter Laporan</td>
                <td style="border: 1px solid #000; padding: 6px 10px; font-weight: bold; text-align: right; width: 55%;">Nominal / Jumlah</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 6px 10px;">Total Data Laporan Ditemukan (Laporan Masuk)</td>
                <td style="border: 1px solid #000; padding: 6px 10px; text-align: right; font-weight: bold; color: #000;"><?php echo count($reports); ?> Laporan</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 6px 10px;">Total Laporan Status Pending</td>
                <td style="border: 1px solid #000; padding: 6px 10px; text-align: right; font-weight: bold; color: #000;"><?php echo $total_pending; ?> Laporan</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 6px 10px;">Total Laporan Diverifikasi Mandor</td>
                <td style="border: 1px solid #000; padding: 6px 10px; text-align: right; font-weight: bold; color: #000;"><?php echo $total_diverifikasi; ?> Laporan</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 6px 10px;">Total Laporan Disetujui Manajer</td>
                <td style="border: 1px solid #000; padding: 6px 10px; text-align: right; font-weight: bold; color: #000;"><?php echo $total_disetujui; ?> Laporan</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 6px 10px;">Total Laporan Ditolak</td>
                <td style="border: 1px solid #000; padding: 6px 10px; text-align: right; font-weight: bold; color: #000;"><?php echo $total_ditolak; ?> Laporan</td>
            </tr>
        </table>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger no-print"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filter Panel (Only visible on screen) -->
<div class="card glass-panel no-print" style="margin-bottom: 20px; padding: 12px;">
    <form method="GET" action="laporan_monitoring.php" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 0;">
        <span style="font-size: 0.85rem; font-weight: bold; color: var(--text-color); display: flex; align-items: center; gap: 5px;">
            <i class="fa-solid fa-filter" style="color: var(--primary-light);"></i> Filter:
        </span>
        
        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 6px 10px; font-size: 0.8rem; height: 36px; width: 140px; margin: 0;" title="Mulai Tanggal">
        <span style="font-size: 0.8rem; color: var(--text-muted);">s/d</span>
        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 6px 10px; font-size: 0.8rem; height: 36px; width: 140px; margin: 0;" title="Sampai Tanggal">
        
        <select name="aktivitas" class="form-control" style="padding: 6px 10px; font-size: 0.8rem; height: 36px; width: 145px; margin: 0;">
            <option value="">Semua Aktivitas</option>
            <option value="Pemanenan" <?php echo $filter_activity === 'Pemanenan' ? 'selected' : ''; ?>>Pemanenan</option>
            <option value="Penyemprotan" <?php echo $filter_activity === 'Penyemprotan' ? 'selected' : ''; ?>>Penyemprotan</option>
            <option value="Pemupukan" <?php echo $filter_activity === 'Pemupukan' ? 'selected' : ''; ?>>Pemupukan</option>
        </select>
        
        <select name="id_mandor" class="form-control" style="padding: 6px 10px; font-size: 0.8rem; height: 36px; width: 145px; margin: 0;">
            <option value="0">Semua Mandor</option>
            <?php foreach ($foremen as $m): ?>
                <option value="<?php echo $m['id_mandor']; ?>" <?php echo $filter_mandor === (int)$m['id_mandor'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($m['nama']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <select name="id_karyawan" class="form-control" style="padding: 6px 10px; font-size: 0.8rem; height: 36px; width: 145px; margin: 0;">
            <option value="0">Semua Karyawan</option>
            <?php foreach ($employees as $k): ?>
                <option value="<?php echo $k['id_karyawan']; ?>" <?php echo $filter_karyawan === (int)$k['id_karyawan'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($k['nama']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <div style="display: flex; gap: 6px; margin-left: auto;">
            <a href="laporan_monitoring.php" class="btn btn-secondary" style="padding: 0 12px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; height: 36px; border-radius: 6px;" title="Reset Filter"><i class="fa-solid fa-rotate-left"></i></a>
            <button type="submit" class="btn btn-primary" style="padding: 0 16px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; gap: 6px; height: 36px; border-radius: 6px;"><i class="fa-solid fa-magnifying-glass"></i> Cari</button>
        </div>
    </form>
</div>

<!-- Ringkasan Monitoring Card Grid (Only visible on screen) -->
<div class="no-print" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <!-- Total Masuk -->
    <div class="card glass-panel" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px 10px; margin: 0; border-top: 4px solid var(--primary-light); text-align: center;">
        <i class="fa-solid fa-inbox" style="font-size: 1.3rem; color: var(--primary-light); margin-bottom: 6px;"></i>
        <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Laporan Masuk</div>
        <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-color); margin-top: 2px;"><?php echo $total_masuk; ?></div>
    </div>
    <!-- Pending -->
    <div class="card glass-panel" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px 10px; margin: 0; border-top: 4px solid #f5b041; text-align: center;">
        <i class="fa-solid fa-clock-rotate-left" style="font-size: 1.3rem; color: #f5b041; margin-bottom: 6px;"></i>
        <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Pending</div>
        <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-color); margin-top: 2px;"><?php echo $total_pending; ?></div>
    </div>
    <!-- Diverifikasi -->
    <div class="card glass-panel" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px 10px; margin: 0; border-top: 4px solid var(--primary); text-align: center;">
        <i class="fa-solid fa-user-check" style="font-size: 1.3rem; color: var(--primary); margin-bottom: 6px;"></i>
        <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Diverifikasi</div>
        <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-color); margin-top: 2px;"><?php echo $total_diverifikasi; ?></div>
    </div>
    <!-- Disetujui -->
    <div class="card glass-panel" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px 10px; margin: 0; border-top: 4px solid var(--success); text-align: center;">
        <i class="fa-solid fa-circle-check" style="font-size: 1.3rem; color: var(--success); margin-bottom: 6px;"></i>
        <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Disetujui</div>
        <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-color); margin-top: 2px;"><?php echo $total_disetujui; ?></div>
    </div>
    <!-- Ditolak -->
    <div class="card glass-panel" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px 10px; margin: 0; border-top: 4px solid var(--danger); text-align: center;">
        <i class="fa-solid fa-circle-xmark" style="font-size: 1.3rem; color: var(--danger); margin-bottom: 6px;"></i>
        <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Ditolak</div>
        <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-color); margin-top: 2px;"><?php echo $total_ditolak; ?></div>
    </div>
</div>

<!-- Main Section Title Bar -->
<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--text-color);"><i class="fa-solid fa-desktop" style="color: var(--primary);"></i> Log Alur Monitoring Lapangan (<?php echo count($reports); ?> data)</h3>
    <button onclick="window.print()" class="btn btn-gold btn-sm">
        <i class="fa-solid fa-print"></i> Cetak Laporan PDF
    </button>
</div>

<!-- SCREEN VIEW: Visual Card Grid (no-print) -->
<?php if (empty($reports)): ?>
    <div class="card glass-panel no-print" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
        <i class="fa-solid fa-folder-open" style="font-size: 3.5rem; opacity: 0.3; margin-bottom: 15px; display: block;"></i>
        Belum ada data monitoring lapangan yang cocok dengan filter pencarian.
    </div>
<?php else: ?>
    <div class="no-print" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <?php foreach ($reports as $rep): 
            // Extract GPS coordinates
            $lat = '';
            $lng = '';
            $clean_notes = $rep['catatan_karyawan'];
            if (preg_match('/\[Lokasi GPS(?:\s*Terkunci)?:\s*Lat\s*([eE\d\.-]+)\s*\|\s*Lng\s*([eE\d\.-]+)\](.*)/is', $rep['catatan_karyawan'], $matches)) {
                $lat = trim($matches[1]);
                $lng = trim($matches[2]);
                $clean_notes = trim($matches[3]);
            }
            
            // Map status styling
            $status_label = "Pending Mandor";
            $status_color = "#f5b041";
            $status_bg = "rgba(245,176,65,0.1)";
            
            if ($rep['status'] === 'verified_by_mandor') {
                $status_label = "Terverifikasi Mandor";
                $status_color = "var(--primary)";
                $status_bg = "rgba(46,125,50,0.1)";
            } elseif ($rep['status'] === 'approved') {
                $status_label = "Disetujui Manajer";
                $status_color = "var(--success)";
                $status_bg = "rgba(110,192,136,0.15)";
            } elseif ($rep['status'] === 'rejected') {
                $status_label = "Ditolak";
                $status_color = "var(--danger)";
                $status_bg = "rgba(198,40,40,0.1)";
            } elseif ($rep['status'] === 'pending_manajer_tolak') {
                $status_label = "Tinjauan Penalti JEM";
                $status_color = "#e65100";
                $status_bg = "rgba(230,81,0,0.1)";
            }
        ?>
            <!-- Report Card Box -->
            <div class="card glass-panel" style="margin: 0; padding: 15px; display: flex; flex-direction: column; justify-content: space-between; border-top: 5px solid <?php echo $status_color; ?>; box-shadow: 0 4px 15px rgba(0,0,0,0.04);">
                <div>
                    <!-- Card Header: User profile info -->
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <div style="background: rgba(0,0,0,0.04); border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border: 1.5px solid <?php echo $status_color; ?>;">
                            <i class="fa-solid fa-user-gear" style="font-size: 1.1rem; color: var(--primary-dark);"></i>
                        </div>
                        <div>
                            <div style="font-weight: bold; font-size: 0.88rem; color: var(--text-color);"><?php echo htmlspecialchars($rep['nama_karyawan']); ?></div>
                            <div style="font-size: 0.72rem; color: var(--text-muted);"><i class="fa-regular fa-calendar"></i> <?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></div>
                        </div>
                        <span style="margin-left: auto; padding: 3px 8px; font-size: 0.7rem; font-weight: bold; border-radius: 4px; color: <?php echo $status_color; ?>; background: <?php echo $status_bg; ?>;">
                            <?php echo $status_label; ?>
                        </span>
                    </div>
                    
                    <!-- Activity & Target info -->
                    <div style="background: rgba(0,0,0,0.02); padding: 8px 12px; border-radius: 6px; font-size: 0.8rem; margin-bottom: 12px; border: 1px solid rgba(0,0,0,0.03);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Aktivitas Kerja:</span>
                            <strong style="color: var(--primary-dark);"><?php echo htmlspecialchars($rep['aktivitas']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Hasil / Target:</span>
                            <strong>
                                <span style="color: var(--primary-light); font-size: 0.85rem;"><?php echo (float)$rep['jumlah_realisasi'] . ' ' . htmlspecialchars($rep['unit']); ?></span>
                                <span style="font-weight: normal; color: var(--text-muted);">/ <?php echo (float)$rep['target_jumlah'] . ' ' . htmlspecialchars($rep['unit']); ?></span>
                            </strong>
                        </div>
                    </div>
                    
                    <!-- Bukti Dokumentasi -->
                    <div style="margin-bottom: 12px; text-align: center; background: rgba(0,0,0,0.05); border-radius: 6px; overflow: hidden; height: 160px; display: flex; align-items: center; justify-content: center; position: relative;">
                        <?php if (!empty($rep['foto_bukti'])): ?>
                            <img src="../<?php echo htmlspecialchars($rep['foto_bukti']); ?>" 
                                 alt="Bukti Dokumentasi" 
                                 style="width: 100%; height: 100%; object-fit: cover; cursor: pointer; transition: transform 0.2s;"
                                 onmouseover="this.style.transform='scale(1.03)'"
                                 onmouseout="this.style.transform='scale(1.0)'"
                                 onclick="openModal('../<?php echo htmlspecialchars($rep['foto_bukti']); ?>', 'Bukti Kerja: <?php echo htmlspecialchars($rep['nama_karyawan']); ?>')">
                        <?php else: ?>
                            <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; flex-direction: column; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-image-slash" style="font-size: 2rem; opacity: 0.4;"></i>
                                Tidak ada unggahan bukti dokumentasi
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Geolocation & Address -->
                    <div class="gps-container-cell" data-lat="<?php echo $lat; ?>" data-lng="<?php echo $lng; ?>" style="font-size: 0.8rem; line-height: 1.4; margin-bottom: 12px;">
                        <span style="font-size: 0.72rem; color: var(--text-muted); display: block; margin-bottom: 3px;">
                            <i class="fa-solid fa-location-crosshairs"></i> Koordinat: <?php echo !empty($lat) ? $lat . ', ' . $lng : 'Tidak terdeteksi'; ?>
                        </span>
                        <?php if (!empty($lat) && !empty($lng)): ?>
                            <span class="address-text" style="color: var(--primary-dark); font-weight: 600; display: block; font-size: 0.75rem;">
                                <i class="fa-solid fa-spinner fa-spin"></i> Geocoding Lokasi...
                            </span>
                        <?php else: ?>
                            <span style="color: var(--danger); font-size: 0.75rem; font-weight: 600; display: block;">
                                <i class="fa-solid fa-triangle-exclamation"></i> GPS tidak terlampir!
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Catatan & Foreman info at the bottom -->
                <div style="margin-top: auto; border-top: 1px solid rgba(0,0,0,0.06); padding-top: 10px; font-size: 0.78rem;">
                    <?php if (!empty($clean_notes)): ?>
                        <div style="background: rgba(0,0,0,0.02); padding: 6px 10px; border-radius: 4px; color: var(--text-color); margin-bottom: 8px; border-left: 3px solid var(--primary-light);">
                            <strong>Catatan:</strong> <?php echo nl2br(htmlspecialchars($clean_notes)); ?>
                        </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; font-size: 0.72rem; color: var(--text-muted);">
                        <span>Mandor Pengawas:</span>
                        <strong><?php echo htmlspecialchars($rep['nama_mandor']); ?></strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- PRINT VIEW: Structured Table Layout (print-only) -->
<div class="print-only">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif;">Tanggal</th>
                <th style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif;">Nama Karyawan</th>
                <th style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif;">Nama Mandor</th>
                <th style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif;">Aktivitas Kerja</th>
                <th style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; text-align: center;">Target</th>
                <th style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; text-align: center;">Hasil</th>
                <th style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; text-align: center;">Status</th>
                <th style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif;">Alamat Lokasi</th>
                <th style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif;">Catatan</th>
                <th style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; text-align: center;">Dokumentasi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr>
                    <td colspan="10" style="border: 1px solid #000; padding: 10px; text-align: center; font-family: 'Times New Roman', serif;">Tidak ada data laporan monitoring ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($reports as $rep): 
                    $lat = '';
                    $lng = '';
                    $clean_notes = $rep['catatan_karyawan'];
                    if (preg_match('/\[Lokasi GPS(?:\s*Terkunci)?:\s*Lat\s*([eE\d\.-]+)\s*\|\s*Lng\s*([eE\d\.-]+)\](.*)/is', $rep['catatan_karyawan'], $matches)) {
                        $lat = trim($matches[1]);
                        $lng = trim($matches[2]);
                        $clean_notes = trim($matches[3]);
                    }
                    
                    $status_label = "Pending Mandor";
                    if ($rep['status'] === 'verified_by_mandor') $status_label = "Terverifikasi Mandor";
                    elseif ($rep['status'] === 'approved') $status_label = "Disetujui";
                    elseif ($rep['status'] === 'rejected') $status_label = "Ditolak";
                    elseif ($rep['status'] === 'pending_manajer_tolak') $status_label = "Tinjauan Penalti";
                ?>
                    <tr>
                        <td style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; font-size: 9pt; white-space: nowrap;"><?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></td>
                        <td style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; font-size: 9pt;"><strong><?php echo htmlspecialchars($rep['nama_karyawan']); ?></strong></td>
                        <td style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; font-size: 9pt;"><?php echo htmlspecialchars($rep['nama_mandor']); ?></td>
                        <td style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; font-size: 9pt;"><?php echo htmlspecialchars($rep['aktivitas']); ?></td>
                        <td style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; font-size: 9pt; text-align: center;"><?php echo (float)$rep['target_jumlah'] . ' ' . htmlspecialchars($rep['unit']); ?></td>
                        <td style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; font-size: 9pt; text-align: center;"><?php echo (float)$rep['jumlah_realisasi'] . ' ' . htmlspecialchars($rep['unit']); ?></td>
                        <td style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; font-size: 9pt; text-align: center;"><?php echo $status_label; ?></td>
                        <td style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; font-size: 8pt;" class="gps-container-cell" data-lat="<?php echo $lat; ?>" data-lng="<?php echo $lng; ?>">
                            <?php if (!empty($lat) && !empty($lng)): ?>
                                <span class="address-text">GPS: <?php echo $lat . ', ' . $lng; ?></span>
                            <?php else: ?>
                                <span style="color: #000; font-style: italic;">GPS Kosong</span>
                            <?php endif; ?>
                        </td>
                        <td style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; font-size: 8pt;"><?php echo htmlspecialchars($clean_notes); ?></td>
                        <td style="border: 1px solid #000; padding: 6px; font-family: 'Times New Roman', serif; font-size: 8pt; text-align: center; font-style: italic; font-weight: bold;">
                            <?php echo !empty($rep['foto_bukti']) ? 'Ada Dokumentasi' : 'Tanpa Dokumentasi'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="10" style="border: 1px solid #000; padding: 8px 10px; font-family: 'Times New Roman', serif; font-size: 10pt; font-weight: bold; background-color: #f9f9f9; text-align: left; color: #000;">
                    Laporan Pengawasan Lapangan PT Agrotamex Sumindo Abadi
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Signature block (Only visible on Print/PDF) -->
    <div style="margin-top: 40px; display: flex; justify-content: flex-end;">
        <div style="text-align: center; width: 250px; font-family: 'Times New Roman', Times, serif;">
            <p style="margin: 0 0 70px 0; font-size: 0.95rem;">
                Jambi, <?php echo date('d-m-Y'); ?><br>
                <strong>Manajer JEM</strong>
            </p>
            <div style="border-bottom: 1.5px solid #000; width: 180px; margin: 0 auto; font-weight: bold; font-size: 1rem;">
                <?php echo htmlspecialchars($_SESSION['nama'] ?? 'Manajer JEM'); ?>
            </div>
            <p style="margin: 5px 0 0 0; font-size: 0.8rem; color: #444;">PT Agrotamex Sumindo Abadi</p>
        </div>
</div>

<style>
    /* Printing print-only class visibility */
    @media screen {
        .print-only { display: none !important; }
    }
    @media print {
        @page {
            size: A4 landscape;
            margin: 1cm;
        }
        body {
            background: #fff !important;
            color: #000 !important;
            font-family: "Times New Roman", Times, serif !important;
            font-size: 8.5pt !important;
        }
        .no-print {
            display: none !important;
        }
        .print-only {
            display: block !important;
        }
        .card {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .table-responsive {
            overflow: visible !important;
        }
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 15px !important;
            color: #000 !important;
            border: 1px solid #000 !important;
        }
        table th, table td {
            border: 1px solid #000 !important;
            padding: 5px 4px !important;
            font-size: 8.5pt !important;
            color: #000 !important;
            background: transparent !important;
        }
        table th {
            font-weight: bold !important;
            text-align: center !important;
            background-color: #f2f2f2 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            border: 1px solid #000 !important;
        }
        .badge {
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            color: #000 !important;
            font-weight: bold !important;
        }
        /* Make sure geocoded and other location texts print black */
        .address-text, span, strong, td, th, div, h2, h3, p {
            color: #000 !important;
        }
    }
</style>

<!-- Image Lightbox Modal for Photo Zooming (Only screen) -->
<div id="imageModal" class="modal no-print" onclick="closeModal()" style="display: none; position: fixed; z-index: 1000; padding-top: 80px; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.85);">
    <span style="position: absolute; top: 25px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer;">&times;</span>
    <img class="modal-content" id="img01" style="margin: auto; display: block; width: 80%; max-width: 700px; max-height: 500px; object-fit: contain; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.5);">
    <div id="caption" style="margin: auto; display: block; width: 80%; max-width: 700px; text-align: center; color: #ccc; padding: 15px 0; font-size: 0.95rem; font-weight: 500;"></div>
</div>

<script>
// Lightbox Zoom Functions
function openModal(src, caption) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById("img01");
    const captionText = document.getElementById("caption");
    
    modal.style.display = "block";
    modalImg.src = src;
    captionText.innerHTML = caption;
}

function closeModal() {
    document.getElementById('imageModal').style.display = "none";
}

// Asynchronously resolve GPS coordinates to place names for card layouts and print layouts
document.addEventListener('DOMContentLoaded', () => {
    const gpsContainers = document.querySelectorAll('.gps-container-cell');
    gpsContainers.forEach((container, idx) => {
        const lat = container.getAttribute('data-lat');
        const lng = container.getAttribute('data-lng');
        const addressSpan = container.querySelector('.address-text');
        
        if (!lat || !lng || !addressSpan) return;
        
        setTimeout(() => {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.address) {
                        let parts = [];
                        if (data.address.village) parts.push(data.address.village);
                        else if (data.address.suburb) parts.push(data.address.suburb);
                        else if (data.address.town) parts.push(data.address.town);
                        
                        if (data.address.city_district) parts.push(data.address.city_district);
                        else if (data.address.district) parts.push(data.address.district);
                        
                        if (data.address.city) parts.push(data.address.city);
                        else if (data.address.county) parts.push(data.address.county);
                        
                        if (data.address.state) parts.push(data.address.state);
                        
                        if (parts.length > 0) {
                            addressSpan.innerHTML = `${parts.join(', ')}`;
                        } else {
                            addressSpan.innerHTML = `${data.display_name}`;
                        }
                    } else {
                        addressSpan.innerHTML = `GPS: ${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
                    }
                })
                .catch(err => {
                    addressSpan.innerHTML = `GPS: ${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
                });
        }, idx * 1000);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
