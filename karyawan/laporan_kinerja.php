<?php
// karyawan/laporan_kinerja.php
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'karyawan') {
    header("Location: ../index.php");
    exit;
}

$karyawan_id = $_SESSION['user_id'];
$error = "";
$history_reports = [];

// 1. Capture search/filter parameters
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$filter_activity = isset($_GET['aktivitas']) ? trim($_GET['aktivitas']) : '';

// 2. Build filtered SQL query for history log list
$query_str = "
    SELECT r.id, r.jumlah_realisasi, r.foto_bukti, r.catatan_karyawan, r.status, 
           r.catatan_mandor, r.catatan_manajer, r.tanggal_verifikasi_mandor, r.bonus_diterima, r.potongan_penalti, r.created_at as tanggal_lapor,
           a.tanggal as tanggal_tugas, a.aktivitas, a.target_jumlah, a.unit,
           m.nama as nama_mandor
    FROM work_reports r
    JOIN assignments a ON r.id_assignment = a.id
    JOIN mandor m ON a.id_mandor = m.id_mandor
    WHERE r.id_karyawan = ?
";
$params = [$karyawan_id];

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

$query_str .= " ORDER BY a.tanggal DESC, r.created_at DESC";

try {
    $stmt = $pdo->prepare($query_str);
    $stmt->execute($params);
    $history_reports = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error = "Error loading reports: " . $e->getMessage();
}

// Fetch last 10 reports for productivity trend chart (approved only)
$chart_reports = [];
$chart_labels = [];
$chart_actuals = [];
try {
    $stmt_chart = $pdo->prepare("
        SELECT a.tanggal, a.aktivitas, r.jumlah_realisasi 
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        WHERE r.id_karyawan = ? AND r.status = 'approved'
        ORDER BY a.tanggal ASC LIMIT 10
    ");
    $stmt_chart->execute([$karyawan_id]);
    $chart_reports = $stmt_chart->fetchAll();

    foreach ($chart_reports as $c) {
        $chart_labels[] = date('d M', strtotime($c['tanggal'])) . " (" . $c['aktivitas'] . ")";
        $chart_actuals[] = (float)$c['jumlah_realisasi'];
    }
} catch (\PDOException $e) {
    // Fail silently
}

// Calculate cumulative bonus statistics for this employee
$total_net_bonus = 0.00;
$total_gross_bonus = 0.00;
$total_penalty_deduction = 0.00;

try {
    $stmt_stats = $pdo->prepare("
        SELECT 
            SUM(bonus_diterima) as net_bonus,
            SUM(CASE WHEN bonus_diterima > 0 THEN bonus_diterima ELSE 0 END) as gross_bonus,
            SUM(CASE WHEN bonus_diterima < 0 THEN ABS(bonus_diterima) ELSE 0 END) as penalty_deduction
        FROM work_reports 
        WHERE id_karyawan = ?
    ");
    $stmt_stats->execute([$karyawan_id]);
    $bonus_stats = $stmt_stats->fetch();
    
    $total_net_bonus = (float)($bonus_stats['net_bonus'] ?? 0);
    $total_gross_bonus = (float)($bonus_stats['gross_bonus'] ?? 0);
    $total_penalty_deduction = (float)($bonus_stats['penalty_deduction'] ?? 0);
} catch (\PDOException $e) {
    // Fail silently
}

// Calculate average achievement index for approved reports
$avg_achievement = 0;
$total_approved_count = 0;
try {
    $stmt_avg = $pdo->prepare("
        SELECT r.jumlah_realisasi, a.target_jumlah, r.potongan_penalti 
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        WHERE r.id_karyawan = ? AND (r.status = 'approved' OR r.potongan_penalti > 0) AND a.target_jumlah > 0
    ");
    $stmt_avg->execute([$karyawan_id]);
    $approved_rows = $stmt_avg->fetchAll();
    
    if (!empty($approved_rows)) {
        $total_percentage_sum = 0;
        foreach ($approved_rows as $row) {
            $real_val = (float)$row['jumlah_realisasi'];
            if ((float)$row['potongan_penalti'] > 0) {
                $real_val = 0;
            }
            $pct = ($real_val / (float)$row['target_jumlah']) * 100;
            $total_percentage_sum += min(100.0, $pct);
        }
        $total_approved_count = count($approved_rows);
        $avg_achievement = round($total_percentage_sum / $total_approved_count, 1);
    }
} catch (\PDOException $e) {
    // Fail silently
}

$predikat_text = "Kurang Produktif";
$predikat_color = "#c62828"; // Red
$predikat_bg = "rgba(198,40,40,0.01)";

if ($avg_achievement >= 80) {
    $predikat_text = "Sangat Produktif";
    $predikat_color = "#1b5e20"; // Green
    $predikat_bg = "rgba(46,125,50,0.02)";
} elseif ($avg_achievement >= 60) {
    $predikat_text = "Cukup Produktif";
    $predikat_color = "#d48b03"; // Gold/Orange
    $predikat_bg = "rgba(212,139,3,0.02)";
}
?>

<style>
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
        nav.navbar, footer, .btn, .no-print, .alert, .progress-container, .card-title {
            display: none !important;
        }
        .img-proof {
            display: block !important;
            width: 50px !important;
            height: 38px !important;
            object-fit: cover !important;
            border: 1px solid #000 !important;
            border-radius: 4px !important;
            margin: 0 auto !important;
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
        }
        table th, table td {
            border: 1px solid #000 !important;
            padding: 8px 6px !important;
            font-size: 10pt !important;
            color: #000 !important;
            background: transparent !important;
        }
        table th {
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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;" class="no-print">
    <div>
        <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
        <h2 style="margin-top: 10px;">Laporan & Histori Kinerja Anda</h2>
        <p style="color: var(--text-muted);">Pantau seluruh catatan hasil kerja, persentase pencapaian target, dan status verifikasi mandor/manajer</p>
    </div>
    <div>
        <button onclick="window.print()" class="btn btn-primary" style="padding: 10px 20px; font-weight: 600; border-radius: 8px;">
            <i class="fa-solid fa-print"></i> Cetak Laporan
        </button>
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
            LAPORAN KINERJA & PRODUKTIVITAS KARYAWAN
        </h3>
        <p style="font-size: 0.85rem; color: #444; margin: 0;">
            Karyawan: <strong><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Karyawan'); ?></strong> | Tanggal Cetak: <?php echo date('d-m-Y H:i'); ?> WIB 
            <?php if (!empty($start_date) || !empty($end_date)): ?>
                | Periode: <?php echo date('d-m-Y', strtotime($start_date)); ?> s/d <?php echo date('d-m-Y', strtotime($end_date)); ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filter Panel -->
<div class="card glass-panel no-print" style="margin-bottom: 25px;">
    <h3 class="card-title"><i class="fa-solid fa-magnifying-glass" style="color: var(--primary);"></i> Cari & Filter Histori Kinerja</h3>
    <form method="GET" action="laporan_kinerja.php">
        <div class="grid-3" style="margin-bottom: 15px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="start_date">Mulai Tanggal</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="end_date">Sampai Tanggal</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="aktivitas">Aktivitas Kerja</label>
                <select name="aktivitas" id="aktivitas" class="form-control">
                    <option value="">-- Semua Aktivitas --</option>
                    <option value="Pemanenan" <?php echo $filter_activity === 'Pemanenan' ? 'selected' : ''; ?>>Pemanenan</option>
                    <option value="Penyemprotan" <?php echo $filter_activity === 'Penyemprotan' ? 'selected' : ''; ?>>Penyemprotan</option>
                    <option value="Pemupukan" <?php echo $filter_activity === 'Pemupukan' ? 'selected' : ''; ?>>Pemupukan</option>
                </select>
            </div>
        </div>
        
        <div style="display:flex; gap:10px; justify-content: flex-end;">
            <a href="laporan_kinerja.php" class="btn btn-secondary" style="padding: 8px 16px;"><i class="fa-solid fa-arrow-rotate-left"></i> Reset</a>
            <button type="submit" class="btn btn-primary" style="padding: 8px 24px;"><i class="fa-solid fa-filter"></i> Cari Laporan</button>
        </div>
    </form>
</div>

<!-- Ringkasan Bonus Kinerja -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px;">
    <!-- Net Wallet Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid var(--primary-light); background: rgba(46,125,50,0.02); margin: 0;">
        <div style="background: rgba(46,125,50,0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0;">
            <i class="fa-solid fa-wallet" style="font-size: 1.4rem; color: var(--primary-light);"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Saldo Bonus Bersih</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary); margin-top: 2px;">
                Rp <?php echo number_format($total_net_bonus, 0, ',', '.'); ?>
            </div>
        </div>
    </div>

    <!-- Gross Achieved Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #2e7d32; background: rgba(46,125,50,0.02); margin: 0;">
        <div style="background: rgba(46,125,50,0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0;">
            <i class="fa-solid fa-gift" style="font-size: 1.4rem; color: #2e7d32;"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Akumulasi Bonus</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: #2e7d32; margin-top: 2px;">
                Rp <?php echo number_format($total_gross_bonus, 0, ',', '.'); ?>
            </div>
        </div>
    </div>

    <!-- Total Penalty Deductions Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #c62828; background: rgba(198,40,40,0.01); margin: 0;">
        <div style="background: rgba(198,40,40,0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0;">
            <i class="fa-solid fa-circle-minus" style="font-size: 1.4rem; color: #c62828;"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Sanksi Denda</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: #c62828; margin-top: 2px;">
                Rp <?php echo number_format($total_penalty_deduction, 0, ',', '.'); ?>
            </div>
        </div>
    </div>

    <!-- Performance Index Tier Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid <?php echo $predikat_color; ?>; background: <?php echo $predikat_bg; ?>; margin: 0;">
        <div style="background: rgba(0,0,0,0.05); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0;">
            <i class="fa-solid fa-gauge-high" style="font-size: 1.4rem; color: <?php echo $predikat_color; ?>;"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Indeks Kinerja (Avg)</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: <?php echo $predikat_color; ?>; margin-top: 2px;">
                <?php echo $avg_achievement; ?>%
            </div>
            <div style="font-size: 0.72rem; font-weight: 600; color: var(--text-muted); margin-top: 2px; line-height: 1.3;">
                Predikat: <strong style="color: <?php echo $predikat_color; ?>;"><?php echo $predikat_text; ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Chart Card (Top of reports list) -->
<div class="card glass-panel no-print" style="margin-bottom: 25px;">
    <h3 class="card-title"><i class="fa-solid fa-chart-line" style="color: var(--primary);"></i> Tren Produktivitas Hasil Kerja Anda (10 Laporan Terakhir Disetujui)</h3>
    <div style="height: 250px; position: relative;">
        <?php if (empty($chart_reports)): ?>
            <div style="text-align: center; padding: 75px 20px; color: var(--text-muted);">
                <i class="fa-solid fa-chart-simple" style="font-size: 2.5rem; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                Belum ada data laporan disetujui untuk divisualisasikan.
            </div>
        <?php else: ?>
            <canvas id="performanceProdChart"></canvas>
        <?php endif; ?>
    </div>
</div>

<div class="card glass-panel">
    <h3 class="card-title"><i class="fa-solid fa-list" style="color: var(--primary);"></i> Histori Produktivitas Kerja (<?php echo count($history_reports); ?> Hasil)</h3>
    
    <!-- Info Box: Rumus & Sumber Data (Dosen Pembimbing) -->
    <div class="alert alert-info" style="background: rgba(46,125,50,0.03); border: 1.5px solid var(--primary-light); color: var(--text-color); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem; line-height: 1.5; display: flex; align-items: flex-start; gap: 12px;">
        <i class="fa-solid fa-circle-info" style="font-size: 1.2rem; color: var(--primary-light); margin-top: 2px;"></i>
        <div>
            <strong>Informasi Rumus &amp; Sumber Data Laporan Kinerja:</strong>
            <ul style="margin: 5px 0 0 0; padding-left: 20px;">
                <li><strong>Sumber Data Target</strong> diperoleh dari input penugasan awal oleh Manajer JEM (tersimpan pada tabel <code>assignments</code>).</li>
                <li><strong>Sumber Data Realisasi</strong> diperoleh dari laporan harian Karyawan yang dilengkapi bukti foto fisik sawit &amp; koordinat GPS lapangan (tersimpan pada tabel <code>work_reports</code>).</li>
                <li><strong>Indeks Kinerja (%)</strong> dihitung menggunakan rumus matematis: <code>(Realisasi / Target) &times; 100%</code>.</li>
                <li><strong>Ketentuan Penalti</strong>: Jika terdeteksi manipulasi data oleh sistem/mandor, realisasi dianggap <code>0</code> secara administratif sehingga Indeks Kinerja hari itu menjadi <code>0%</code>.</li>
            </ul>
        </div>
    </div>

    <?php if (empty($history_reports)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
            <i class="fa-solid fa-folder-open" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 15px; display: block;"></i>
            Tidak ditemukan laporan kerja yang cocok dengan filter pencarian Anda.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal Tugas</th>
                        <th>Aktivitas Kerja</th>
                        <th>Target</th>
                        <th>Realisasi Hasil</th>
                        <th>Pencapaian (%)</th>
                        <th>Bonus Pencapaian</th>
                        <th>Mandor Pengawas</th>
                        <th>Catatan</th>
                        <th>Bukti Foto</th>
                        <th>Status Verifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history_reports as $row): 
                        // Override realization display if report has a penalty
                        $realisasi_display = $row['jumlah_realisasi'];
                        if ($row['potongan_penalti'] > 0) {
                            $realisasi_display = 0;
                        }

                        // Calculate achievement percentage
                        $percentage = 0;
                        if ($row['target_jumlah'] > 0) {
                            $percentage = ($realisasi_display / $row['target_jumlah']) * 100;
                        }
                        
                        // Badge formatting for status
                        $status_badge = '';
                        if ($row['status'] === 'pending_mandor') {
                            $status_badge = '<span class="badge badge-pending">Menunggu Mandor</span>';
                        } elseif ($row['status'] === 'verified_by_mandor') {
                            $status_badge = '<span class="badge badge-verified">Terverifikasi Mandor</span>';
                        } elseif ($row['status'] === 'approved') {
                            $status_badge = '<span class="badge badge-approved">Disetujui Manajer</span>';
                        } elseif ($row['status'] === 'rejected') {
                            if ($row['potongan_penalti'] > 0) {
                                $status_badge = '<span class="badge badge-logout" style="background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak (Manipulasi)</span>';
                            } else {
                                $status_badge = '<span class="badge badge-logout" style="background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak</span>';
                            }
                        } elseif ($row['status'] === 'pending_manajer_tolak') {
                            $status_badge = '<span class="badge badge-pending" style="background:#fff3e0; color:#e65100; border:1px solid #ffe0b2;">Tinjauan Sanksi JEM</span>';
                        }
                    ?>
                        <tr style="border-bottom: 1px solid var(--card-border);">
                            <td><?php echo date('d-m-Y', strtotime($row['tanggal_tugas'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['aktivitas']); ?></strong></td>
                            <td><?php echo (float)$row['target_jumlah'] . ' ' . htmlspecialchars($row['unit']); ?></td>
                            <td><strong style="color: var(--primary);"><?php echo (float)$realisasi_display . ' ' . htmlspecialchars($row['unit']); ?></strong></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-weight:700; color: <?php echo $percentage >= 80 ? 'var(--primary-light)' : ($percentage >= 60 ? 'var(--warning)' : 'var(--danger)'); ?>;"><?php echo round($percentage, 1); ?>%</span>
                                    <div class="progress-container" style="width: 60px; height: 6px; margin: 0; background: rgba(0,0,0,0.05); border-radius: 4px;">
                                        <div class="progress-bar" style="width: <?php echo min($percentage, 100); ?>%; border-radius: 4px; height: 100%; background: <?php echo $percentage >= 80 ? 'var(--primary-light)' : ($percentage >= 60 ? 'var(--warning)' : 'var(--danger)'); ?>;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($row['bonus_diterima'] > 0): ?>
                                    <strong style="color: var(--success); font-size: 0.9rem;"><i class="fa-solid fa-gift"></i> Rp <?php echo number_format($row['bonus_diterima'], 0, ',', '.'); ?></strong>
                                <?php elseif ($row['bonus_diterima'] < 0): ?>
                                    <strong style="color: #c62828; font-size: 0.9rem;"><i class="fa-solid fa-circle-minus"></i> -Rp <?php echo number_format(abs($row['bonus_diterima']), 0, ',', '.'); ?></strong>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size:0.8rem;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['nama_mandor']); ?></td>
                            <td>
                                <?php if (!empty($row['catatan_karyawan'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-bottom: 4px; line-height: 1.2;">
                                        <strong>Anda:</strong> <?php echo htmlspecialchars($row['catatan_karyawan']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($row['catatan_mandor'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--primary-light); margin-bottom: 2px; line-height: 1.2;">
                                        <strong>Mandor:</strong> <?php echo htmlspecialchars($row['catatan_mandor']); ?>
                                        <small style="display:block; color:var(--text-muted); font-size:0.65rem;">(<?php echo date('d-m-Y H:i', strtotime($row['tanggal_verifikasi_mandor'])); ?>)</small>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($row['catatan_manajer'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--primary-dark); line-height: 1.2; background: rgba(46,125,50,0.05); padding: 2px 4px; border-radius: 3px; display: inline-block;">
                                        <strong>Manajer:</strong> <?php echo htmlspecialchars($row['catatan_manajer']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($row['catatan_karyawan']) && empty($row['catatan_mandor']) && empty($row['catatan_manajer'])): ?>
                                    <span style="color: var(--text-muted); font-size:0.8rem; font-style:italic;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['foto_bukti'])): ?>
                                    <img src="../<?php echo htmlspecialchars($row['foto_bukti']); ?>" class="img-proof" onclick="openModal('../<?php echo htmlspecialchars($row['foto_bukti']); ?>', 'Bukti Foto: <?php echo htmlspecialchars($row['aktivitas']) . ' - ' . date('d-m-Y', strtotime($row['tanggal_tugas'])); ?>')" />
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:0.8rem; font-style:italic;">Tidak ada foto</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $status_badge; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #fcfcfc; font-weight: bold; border-top: 1.5px solid #000;">
                            <td colspan="4" style="text-align: right; padding: 8px;">Indeks Kinerja (Avg):</td>
                            <td style="color: <?php echo $predikat_color; ?>; padding: 8px; font-weight: bold;">
                                <?php echo $avg_achievement; ?>%
                            </td>
                            <td style="padding: 8px;">
                                <span class="badge" style="background: <?php echo $predikat_bg; ?>; color: <?php echo $predikat_color; ?>; border: 1px solid <?php echo $predikat_color; ?>; font-size: 0.75rem; border-radius: 4px; padding: 4px 8px; font-weight: bold; display: inline-block;">
                                    <?php echo $predikat_text; ?>
                                </span>
                            </td>
                            <td colspan="4" style="padding: 8px; text-align: right;">
                                <strong style="color: var(--success);">Rp <?php echo number_format($total_net_bonus, 0, ',', '.'); ?></strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tanda Tangan Pengesahan (Hanya Muncul Saat Cetak/PDF) -->
<div class="print-only" style="margin-top: 40px; display: flex; justify-content: space-between; page-break-inside: avoid;">
    <div style="text-align: center; width: 220px; font-family: 'Times New Roman', Times, serif;">
        <p style="margin: 0 0 70px 0; font-size: 0.95rem;">
            Mengetahui,<br>
            <strong>Mandor Pengawas</strong>
        </p>
        <div style="border-bottom: 1.5px solid #000; width: 180px; margin: 0 auto; font-weight: bold; font-size: 1rem;">
            &nbsp;
        </div>
    </div>
    
    <div style="text-align: center; width: 220px; font-family: 'Times New Roman', Times, serif;">
        <p style="margin: 0 0 70px 0; font-size: 0.95rem;">
            Jambi, <?php echo date('d-m-Y'); ?><br>
            <strong>Manajer JEM</strong>
        </p>
        <div style="border-bottom: 1.5px solid #000; width: 180px; margin: 0 auto; font-weight: bold; font-size: 1rem;">
            &nbsp;
        </div>
        <p style="margin: 5px 0 0 0; font-size: 0.8rem; color: #444;">PT Agrotamex Sumindo Abadi</p>
    </div>
</div>

<!-- Modal for Image Preview Lightbox -->
<div id="imageModal" class="modal" onclick="closeModal()">
    <span class="modal-close" onclick="closeModal()">&times;</span>
    <img class="modal-content" id="imgModalTarget">
    <div id="imgModalCaption" class="modal-caption"></div>
</div>

<script>
function openModal(src, caption) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('imgModalTarget');
    const captionText = document.getElementById('imgModalCaption');
    
    modal.style.display = "block";
    modalImg.src = src;
    captionText.innerHTML = caption;
}

function closeModal() {
    document.getElementById('imageModal').style.display = "none";
}

// Render chart if data is present
<?php if (!empty($chart_reports)): ?>
document.addEventListener('DOMContentLoaded', () => {
    const labels = <?php echo json_encode($chart_labels); ?>;
    const actuals = <?php echo json_encode($chart_actuals); ?>;
    initProductivityChart('performanceProdChart', labels, null, actuals);
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
