<?php
// manajer/laporan_produktivitas.php
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
    WHERE (r.status = 'approved' OR r.potongan_penalti > 0)
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
$total_net_payout = 0.00;
$total_gross_bonus = 0.00;
$total_penalty_deducted = 0.00;
$avg_achievement_all = 0;

if (!empty($reports)) {
    $total_percentage_sum = 0;
    foreach ($reports as $rep) {
        // Financials
        $val = (float)$rep['bonus_diterima'];
        $total_net_payout += $val;
        if ($val > 0) {
            $total_gross_bonus += $val;
        }
        $total_penalty_deducted += (float)$rep['potongan_penalti'];
        
        // Performance Index
        $real_val = (float)$rep['jumlah_realisasi'];
        if ((float)$rep['potongan_penalti'] > 0) {
            $real_val = 0;
        }
        $percentage = $rep['target_jumlah'] > 0 ? (($real_val / (float)$rep['target_jumlah']) * 100) : 0;
        $total_percentage_sum += min(100.0, $percentage);
    }
    $avg_achievement_all = round($total_percentage_sum / count($reports), 1);
}

$predikat_text = "Kurang Produktif";
$predikat_color = "#c62828"; // Red
$predikat_bg = "rgba(198,40,40,0.01)";

if ($avg_achievement_all >= 80) {
    $predikat_text = "Sangat Produktif";
    $predikat_color = "#1b5e20"; // Green
    $predikat_bg = "rgba(46,125,50,0.02)";
} elseif ($avg_achievement_all >= 60) {
    $predikat_text = "Cukup Produktif";
    $predikat_color = "#d48b03"; // Gold/Orange
    $predikat_bg = "rgba(212,139,3,0.02)";
}
?>

<!-- Welcome/Header Banner -->
<div style="margin-bottom: 25px;" class="no-print">
    <a href="index.php" style="color: var(--primary-dark); font-weight: 600; text-decoration: none; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 15px; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-light)'" onmouseout="this.style.color='var(--primary-dark)'">
        <i class="fa-solid fa-arrow-left-long"></i> Kembali ke Dashboard
    </a>
    
    <div class="card glass-panel" style="background: linear-gradient(135deg, #1b4d3e 0%, #112a22 100%); border-left: 5px solid var(--primary-light); color: #fff; padding: 20px 25px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: relative; overflow: hidden; margin: 0;">
        <div style="position: absolute; right: -15px; top: -20px; opacity: 0.1; font-size: 6rem; color: #fff; transform: rotate(10deg); pointer-events: none;">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div style="position: relative; z-index: 2;">
            <h2 style="margin: 0; font-size: 1.45rem; color: #fff; font-weight: 700;">Laporan Analisis &amp; Evaluasi Produktivitas</h2>
            <p style="margin: 6px 0 0 0; font-size: 0.85rem; color: #d0e7da; line-height: 1.4; font-weight: normal;">
                Menganalisis pencapaian target kerja kelompok, predikat kategori produktivitas, bonus insentif keuangan, serta sanksi denda pemotongan.
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
            LAPORAN PRODUKTIVITAS &amp; BONUS KARYAWAN
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
                <td style="border: 1px solid #000; padding: 6px 10px; text-align: right; font-weight: bold;"><?php echo count($reports); ?> Laporan</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 6px 10px;">Indeks Kinerja (Avg)</td>
                <td style="border: 1px solid #000; padding: 6px 10px; text-align: right; font-weight: bold;"><?php echo $avg_achievement_all; ?>% (<?php echo $predikat_text; ?>)</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 6px 10px;">Total Akumulasi Bonus Bruto Karyawan</td>
                <td style="border: 1px solid #000; padding: 6px 10px; text-align: right; color: #2e7d32; font-weight: bold;">Rp <?php echo number_format($total_gross_bonus, 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 6px 10px;">Total Sanksi Potongan Denda (10% Manipulasi)</td>
                <td style="border: 1px solid #000; padding: 6px 10px; text-align: right; color: #c62828; font-weight: bold;">-Rp <?php echo number_format($total_penalty_deducted, 0, ',', '.'); ?></td>
            </tr>
            <tr style="background-color: #e8f5e9;">
                <td style="border: 1px solid #000; padding: 6px 10px; font-weight: bold;">Total Pengeluaran Bonus Bersih Perusahaan</td>
                <td style="border: 1px solid #000; padding: 6px 10px; text-align: right; font-weight: bold; font-size: 1rem; color: #1b5e20;">Rp <?php echo number_format($total_net_payout, 0, ',', '.'); ?></td>
            </tr>
        </table>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger no-print"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filter Panel (Only visible on screen) -->
<div class="card glass-panel no-print" style="margin-bottom: 20px; padding: 12px;">
    <form method="GET" action="laporan_produktivitas.php" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 0;">
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
            <a href="laporan_produktivitas.php" class="btn btn-secondary" style="padding: 0 12px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; height: 36px; border-radius: 6px;" title="Reset Filter"><i class="fa-solid fa-rotate-left"></i></a>
            <button type="submit" class="btn btn-primary" style="padding: 0 16px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; gap: 6px; height: 36px; border-radius: 6px;"><i class="fa-solid fa-magnifying-glass"></i> Cari</button>
        </div>
    </form>
</div>

<!-- Ringkasan Keuangan & Indeks Kinerja Grid (Only visible on screen) -->
<div class="no-print" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px;">
    <!-- Net Payout Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid var(--primary-light); background: rgba(46,125,50,0.02); margin: 0;">
        <div style="background: rgba(46,125,50,0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0;">
            <i class="fa-solid fa-wallet" style="font-size: 1.4rem; color: var(--primary-light);"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Pengeluaran Bonus (Bersih)</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary); margin-top: 2px;">
                Rp <?php echo number_format($total_net_payout, 0, ',', '.'); ?>
            </div>
        </div>
    </div>

    <!-- Gross Earned Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #2e7d32; background: rgba(46,125,50,0.02); margin: 0;">
        <div style="background: rgba(46,125,50,0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0;">
            <i class="fa-solid fa-money-bill-trend-up" style="font-size: 1.4rem; color: #2e7d32;"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Bonus Bruto Karyawan</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: #2e7d32; margin-top: 2px;">
                Rp <?php echo number_format($total_gross_bonus, 0, ',', '.'); ?>
            </div>
        </div>
    </div>

    <!-- Penalty Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #c62828; background: rgba(198,40,40,0.02); margin: 0;">
        <div style="background: rgba(198,40,40,0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0;">
            <i class="fa-solid fa-circle-minus" style="font-size: 1.4rem; color: #c62828;"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Akumulasi Potongan Sanksi</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: #c62828; margin-top: 2px;">
                Rp <?php echo number_format($total_penalty_deducted, 0, ',', '.'); ?>
            </div>
        </div>
    </div>

    <!-- Performance Index Tier Card -->
    <div class="card glass-panel clickable-stats-card" onclick="toggleFormulaDetails()" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid <?php echo $predikat_color; ?>; background: <?php echo $predikat_bg; ?>; margin: 0; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease;">
        <div style="background: rgba(0,0,0,0.05); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0;">
            <i class="fa-solid fa-gauge-high" style="font-size: 1.4rem; color: <?php echo $predikat_color; ?>;"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Indeks Kinerja (Avg)</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: <?php echo $predikat_color; ?>; margin-top: 2px;">
                <?php echo $avg_achievement_all; ?>%
            </div>
            <div style="font-size: 0.72rem; font-weight: 600; color: var(--text-muted); margin-top: 2px;">
                Predikat: <strong style="color: <?php echo $predikat_color; ?>;"><?php echo $predikat_text; ?></strong>
            </div>
            <div style="font-size: 0.65rem; color: var(--primary-light); margin-top: 4px; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                <i class="fa-solid fa-circle-chevron-down"></i> Klik untuk rincian &amp; rumus
            </div>
        </div>
    </div>
</div>

<!-- Rincian Evaluasi & Rumus Produktivitas (Collapsible card, only visible on screen) -->
<div id="formulaDetailsPanel" class="card glass-panel no-print" style="margin-bottom: 25px; border-left: 5px solid var(--primary); background: rgba(46,125,50,0.01); padding: 20px; display: none;">
    <h3 class="card-title" style="margin-bottom: 15px;"><i class="fa-solid fa-calculator" style="color: var(--primary);"></i> Rincian &amp; Rumus Perhitungan Indeks Kinerja</h3>
    <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text-color);">
        <p style="margin: 0 0 15px 0;">
            Berdasarkan parameter filter aktif (Periode Hari/Tanggal, Bulan, Tahun, Aktivitas, Mandor, dan Karyawan pelaksana), berikut rincian formula kalkulasi indeks rata-rata:
        </p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
            <div style="background: rgba(255,255,255,0.4); padding: 15px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.05);">
                <strong style="color: var(--primary-dark); display: block; margin-bottom: 8px;">1. Formula / Rumus Akademis:</strong>
                <div style="background: #f8faf8; padding: 10px; border-radius: 6px; font-family: monospace; font-size: 0.8rem; border-left: 3px solid var(--primary-light); color: var(--primary-dark);">
                    Rata-rata Indeks Kinerja (AVG) =<br>
                    (Total Jumlah Persen Pencapaian / Total Jumlah Penugasan)
                </div>
                <div style="margin-top: 10px; font-size: 0.78rem; color: var(--text-muted);">
                    *Catatan: Pencapaian harian individu dibatasi maks. 100% pada hitungan indeks kinerja untuk menjaga keandalan statistik rata-rata kelompok.
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.4); padding: 15px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.05);">
                <strong style="color: var(--primary-dark); display: block; margin-bottom: 8px;">2. Klasifikasi Produktivitas (Cluster Kinerja):</strong>
                <ul style="margin: 0; padding-left: 18px; list-style-type: square; font-size: 0.8rem; line-height: 1.5;">
                    <li><strong style="color: #1b5e20;">Sangat Produktif (Cluster A)</strong>: Rata-rata Indeks Kinerja &ge; 80%</li>
                    <li><strong style="color: #d48b03;">Cukup Produktif (Cluster B)</strong>: Rata-rata Indeks Kinerja 60% s/d 79%</li>
                    <li><strong style="color: #c62828;">Kurang Produktif (Cluster C)</strong>: Rata-rata Indeks Kinerja &lt; 60%</li>
                </ul>
            </div>
        </div>
        
        <div style="background: rgba(46,125,50,0.05); padding: 12px 15px; border-radius: 6px; border: 1.5px solid var(--primary-light); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div>
                Filter Aktif saat ini menghasilkan: 
                <strong><?php echo count($reports); ?> Laporan</strong> dengan akumulasi indeks persen rata-rata.
            </div>
            <div style="font-size: 0.95rem;">
                Indeks Kinerja (AVG): <strong style="color: <?php echo $predikat_color; ?>; font-size: 1.1rem;"><?php echo $avg_achievement_all; ?>%</strong> 
                &nbsp;|&nbsp; Predikat: <span class="badge" style="background: <?php echo $predikat_bg; ?>; color: <?php echo $predikat_color; ?>; border: 1px solid <?php echo $predikat_color; ?>; font-weight: bold; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;"><?php echo $predikat_text; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Laporan Table Card -->
<div class="card glass-panel">
    <div class="card-title">
        <span><i class="fa-solid fa-file-invoice" style="color: var(--primary);"></i> Laporan Produktivitas &amp; Bonus Karyawan (<?php echo count($reports); ?> data)</span>
        <button onclick="window.print()" class="btn btn-gold btn-sm no-print">
            <i class="fa-solid fa-print"></i> Cetak Laporan PDF
        </button>
    </div>

    <!-- Info Box: Rumus & Sumber Data (Dosen Pembimbing) -->
    <div class="alert alert-info no-print" style="background: rgba(46,125,50,0.03); border: 1.5px solid var(--primary-light); color: var(--text-color); padding: 15px; border-radius: 8px; margin: 15px 20px; font-size: 0.85rem; line-height: 1.5; display: flex; align-items: flex-start; gap: 12px;">
        <i class="fa-solid fa-circle-info" style="font-size: 1.2rem; color: var(--primary-light); margin-top: 2px;"></i>
        <div>
            <strong>Informasi Rumus &amp; Sumber Data Laporan:</strong>
            <ul style="margin: 5px 0 0 0; padding-left: 20px;">
                <li><strong>Sumber Data Target</strong> diperoleh dari input penugasan awal oleh Manajer JEM (tersimpan pada tabel <code>assignments</code>).</li>
                <li><strong>Sumber Data Hasil</strong> diperoleh dari laporan harian Karyawan yang dilengkapi bukti foto fisik sawit &amp; koordinat GPS lapangan (tersimpan pada tabel <code>work_reports</code>).</li>
                <li><strong>Indeks Kinerja (%)</strong> dihitung menggunakan rumus: <code>(Hasil / Target) &times; 100%</code>.</li>
                <li><strong>Ketentuan Penalti</strong>: Jika terdeteksi manipulasi data oleh sistem/mandor, hasil dianggap <code>0</code> secara administratif sehingga Indeks Kinerja hari itu menjadi <code>0%</code>.</li>
            </ul>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nama Karyawan</th>
                    <th>Nama Mandor</th>
                    <th>Aktivitas Kerja</th>
                    <th style="text-align: center;">Target</th>
                    <th style="text-align: center;">Hasil</th>
                    <th style="text-align: center;">Indeks Kinerja (%)</th>
                    <th style="text-align: center;">Predikat Produktivitas</th>
                    <th style="text-align: center;">Potongan Penalti (10%)</th>
                    <th style="text-align: right;">Bonus Netto (Diterima)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 30px; color: var(--text-muted);">Tidak ada data laporan produktivitas yang ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reports as $rep): 
                        // Performance index calculation
                        $real_val = (float)$rep['jumlah_realisasi'];
                        if ((float)$rep['potongan_penalti'] > 0) {
                            $real_val = 0;
                        }
                        $percentage = $rep['target_jumlah'] > 0 ? (($real_val / (float)$rep['target_jumlah']) * 100) : 0;
                        
                        // Row Predicate Classifications
                        $row_predikat = "Kurang Produktif";
                        $row_color = "#c62828";
                        $row_bg = "rgba(198,40,40,0.05)";
                        
                        if ($percentage >= 80) {
                            $row_predikat = "Sangat Produktif";
                            $row_color = "var(--primary-dark)";
                            $row_bg = "rgba(46,125,50,0.08)";
                        } elseif ($percentage >= 60) {
                            $row_predikat = "Cukup Produktif";
                            $row_color = "#d48b03";
                            $row_bg = "rgba(212,139,3,0.08)";
                        }
                        
                        if ((float)$rep['potongan_penalti'] > 0) {
                            $row_predikat = "Fraud (Manipulasi)";
                            $row_color = "#c62828";
                            $row_bg = "rgba(198,40,40,0.1)";
                        }
                    ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($rep['nama_karyawan']); ?></strong></td>
                            <td><?php echo htmlspecialchars($rep['nama_mandor']); ?></td>
                            <td>
                                <span class="badge" style="background: rgba(0,0,0,0.05); color: var(--text-color); font-weight: bold; border-radius: 4px; font-size: 0.72rem;">
                                    <?php echo htmlspecialchars($rep['aktivitas']); ?>
                                </span>
                            </td>
                            <td style="text-align: center;"><?php echo (float)$rep['target_jumlah'] . ' ' . htmlspecialchars($rep['unit']); ?></td>
                            <td style="text-align: center;">
                                <?php if ((float)$rep['potongan_penalti'] > 0): ?>
                                    <span style="text-decoration: line-through; color: var(--text-muted);"><?php echo (float)$rep['jumlah_realisasi']; ?></span>
                                    <strong style="color: var(--danger); block; font-size: 0.78rem;">(0)</strong>
                                <?php else: ?>
                                    <?php echo (float)$rep['jumlah_realisasi'] . ' ' . htmlspecialchars($rep['unit']); ?>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; font-weight: bold; color: <?php echo $row_color; ?>;"><?php echo round($percentage, 1); ?>%</td>
                            <td style="text-align: center;">
                                <span class="badge" style="background: <?php echo $row_bg; ?>; color: <?php echo $row_color; ?>; font-weight: bold; border-radius: 4px; font-size: 0.72rem; padding: 3px 8px; border: 1px solid <?php echo $row_color; ?>;">
                                    <?php echo $row_predikat; ?>
                                </span>
                            </td>
                            <td style="text-align: center; color: #c62828; font-weight: bold;">
                                <?php echo (float)$rep['potongan_penalti'] > 0 ? 'Rp ' . number_format($rep['potongan_penalti'], 0, ',', '.') : '-'; ?>
                            </td>
                            <td style="text-align: right; font-weight: bold; color: <?php echo (float)$rep['bonus_diterima'] >= 0 ? 'var(--primary-dark)' : '#c62828'; ?>;">
                                <?php echo (float)$rep['bonus_diterima'] >= 0 ? 'Rp ' . number_format($rep['bonus_diterima'], 0, ',', '.') : '-Rp ' . number_format(abs($rep['bonus_diterima']), 0, ',', '.'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: rgba(0,0,0,0.03); font-weight: bold;">
                    <td colspan="6" style="text-align: right;">RATA-RATA &amp; TOTAL KESELURUHAN:</td>
                    <td style="text-align: center; color: <?php echo $predikat_color; ?>; font-size: 1rem;"><?php echo $avg_achievement_all; ?>%</td>
                    <td style="text-align: center; font-size: 0.78rem; color: <?php echo $predikat_color; ?>;"><?php echo $predikat_text; ?></td>
                    <td style="text-align: center; color: #c62828;">-Rp <?php echo number_format($total_penalty_deducted, 0, ',', '.'); ?></td>
                    <td style="text-align: right; color: var(--primary-dark); font-size: 1rem;">Rp <?php echo number_format($total_net_payout, 0, ',', '.'); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Print-only Signature block (Only visible on Print/PDF) -->
    <div class="print-only" style="margin-top: 40px; display: flex; justify-content: flex-end;">
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
</div>

<style>
    /* Clickable stats card hover style */
    .clickable-stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(46,125,50,0.12) !important;
    }

    /* Printing print-only class visibility */
    @media screen {
        .print-only { display: none !important; }
    }
    @media print {
        @page {
            size: A4 portrait;
            margin: 1.5cm;
        }
        body {
            background: #fff !important;
            color: #000 !important;
            font-family: "Times New Roman", Times, serif !important;
            font-size: 11pt !important;
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
        table.table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 15px !important;
        }
        table.table th, table.table td {
            border: 1px solid #000 !important;
            padding: 8px 6px !important;
            font-size: 10pt !important;
            color: #000 !important;
            background: transparent !important;
        }
        table.table th {
            font-weight: bold !important;
            text-align: center !important;
            background-color: #f2f2f2 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .badge {
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            color: #000 !important;
            font-weight: bold !important;
        }
    }
</style>

<script>
// Toggle visibility of the Formula and Clusters details panel
function toggleFormulaDetails() {
    const panel = document.getElementById('formulaDetailsPanel');
    if (!panel) return;
    
    if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'block';
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        panel.style.display = 'none';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
