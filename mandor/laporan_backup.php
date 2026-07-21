<?php
// mandor/laporan.php
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'mandor') {
    header("Location: ../index.php");
    exit;
}

$mandor_id = $_SESSION['user_id'];
$error = "";
$history_reports = [];
$employees = [];

// Fetch all employees for filter dropdown
try {
    $stmt = $pdo->query("SELECT id_karyawan, nama FROM karyawan ORDER BY nama ASC");
    $employees = $stmt->fetchAll();
} catch (\PDOException $e) {
    // Fail silently
}

// 1. Capture search/filter parameters
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$filter_karyawan = isset($_GET['id_karyawan']) ? (int)$_GET['id_karyawan'] : 0;

// 2. Build filtered SQL query
$query_str = "
    SELECT r.id, r.jumlah_realisasi, r.foto_bukti, r.catatan_karyawan, r.status, 
           r.catatan_mandor, r.catatan_manajer, r.tanggal_verifikasi_mandor, r.bonus_diterima, r.potongan_penalti,
           a.tanggal as tanggal_tugas, a.aktivitas, a.target_jumlah, a.unit,
           k.nama as nama_karyawan
    FROM work_reports r
    JOIN assignments a ON r.id_assignment = a.id
    JOIN karyawan k ON r.id_karyawan = k.id_karyawan
    WHERE a.id_mandor = ?
";
$params = [$mandor_id];

if (!empty($start_date)) {
    $query_str .= " AND a.tanggal >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $query_str .= " AND a.tanggal <= ?";
    $params[] = $end_date;
}
if ($filter_karyawan > 0) {
    $query_str .= " AND r.id_karyawan = ?";
    $params[] = $filter_karyawan;
}

$query_str .= " ORDER BY a.tanggal DESC, r.created_at DESC";

try {
    $stmt = $pdo->prepare($query_str);
    $stmt->execute($params);
    $history_reports = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error = "Error loading reports: " . $e->getMessage();
}

// Fetch last 10 reports for productivity trend chart (chronological order)
$chart_reports = [];
$chart_labels = [];
$chart_actuals = [];
try {
    $stmt_chart = $pdo->prepare("
        SELECT a.tanggal, a.aktivitas, r.jumlah_realisasi, k.nama as nama_karyawan
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        JOIN karyawan k ON r.id_karyawan = k.id_karyawan
        WHERE a.id_mandor = ? AND r.status IN ('verified_by_mandor', 'approved')
        ORDER BY a.tanggal ASC LIMIT 10
    ");
    $stmt_chart->execute([$mandor_id]);
    $chart_reports = $stmt_chart->fetchAll();

    foreach ($chart_reports as $c) {
        $chart_labels[] = date('d M', strtotime($c['tanggal'])) . " (" . $c['aktivitas'] . " - " . $c['nama_karyawan'] . ")";
        $chart_actuals[] = (float)$c['jumlah_realisasi'];
    }
} catch (\PDOException $e) {
    // Fail silently
}
?>

<?php
$avg_achievement = 0;
$total_percentage_sum = 0;
if (!empty($history_reports)) {
    foreach ($history_reports as $row) {
        $real_val = (float)$row['jumlah_realisasi'];
        if ((float)$row['potongan_penalti'] > 0) {
            $real_val = 0;
        }
        $pct = $row['target_jumlah'] > 0 ? (($real_val / (float)$row['target_jumlah']) * 100) : 0;
        $total_percentage_sum += min(100.0, $pct);
    }
    $avg_achievement = round($total_percentage_sum / count($history_reports), 1);
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
        <h2 style="margin-top: 10px;">Laporan Histori Pengawasan Kerja</h2>
        <p style="color: var(--text-muted);">Pantau seluruh histori pencapaian tugas karyawan di bawah pengawasan Anda</p>
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
            LAPORAN PENGAWASAN & PRODUKTIVITAS MANDOR
        </h3>
        <p style="font-size: 0.85rem; color: #444; margin: 0;">
            Mandor Pengawas: <strong><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Mandor'); ?></strong> | Tanggal Cetak: <?php echo date('d-m-Y H:i'); ?> WIB 
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
    <h3 class="card-title"><i class="fa-solid fa-magnifying-glass" style="color: var(--primary);"></i> Cari & Filter Histori Laporan</h3>
    <form method="GET" action="laporan.php">
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
                <label for="id_karyawan">Pelaksana (Karyawan)</label>
                <select name="id_karyawan" id="id_karyawan" class="form-control">
                    <option value="0">-- Semua Karyawan --</option>
                    <?php foreach ($employees as $k): ?>
                        <option value="<?php echo $k['id_karyawan']; ?>" <?php echo $filter_karyawan === (int)$k['id_karyawan'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($k['nama']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div style="display:flex; gap:10px; justify-content: flex-end;">
            <a href="laporan.php" class="btn btn-secondary" style="padding: 8px 16px;"><i class="fa-solid fa-arrow-rotate-left"></i> Reset</a>
            <button type="submit" class="btn btn-primary" style="padding: 8px 24px;"><i class="fa-solid fa-filter"></i> Cari Laporan</button>
        </div>
    </form>
</div>

<!-- Chart Card (Top of reports list) -->
<div class="card glass-panel no-print" style="margin-bottom: 25px;">
    <h3 class="card-title"><i class="fa-solid fa-chart-line" style="color: var(--primary);"></i> Tren Produktivitas Kelompok Mandor Anda (10 Laporan Terverifikasi Terakhir)</h3>
    <div style="height: 250px; position: relative;">
        <?php if (empty($chart_reports)): ?>
            <div style="text-align: center; padding: 75px 20px; color: var(--text-muted);">
                <i class="fa-solid fa-chart-simple" style="font-size: 2.5rem; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                Belum ada data laporan terverifikasi untuk divisualisasikan.
            </div>
        <?php else: ?>
            <canvas id="mandorHistoryChart"></canvas>
        <?php endif; ?>
    </div>
</div>

<div class="card glass-panel">
    <h3 class="card-title"><i class="fa-solid fa-list" style="color: var(--primary);"></i> Histori Pengawasan Kelompok Mandor (<?php echo count($history_reports); ?> Hasil)</h3>
    
    <!-- Info Box: Rumus & Sumber Data (Dosen Pembimbing) -->
    <div class="alert alert-info" style="background: rgba(46,125,50,0.03); border: 1.5px solid var(--primary-light); color: var(--text-color); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem; line-height: 1.5; display: flex; align-items: flex-start; gap: 12px;">
        <i class="fa-solid fa-circle-info" style="font-size: 1.2rem; color: var(--primary-light); margin-top: 2px;"></i>
        <div>
            <strong>Informasi Rumus &amp; Sumber Data Laporan Pengawasan:</strong>
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
            <table class="table" style="vertical-align: middle;">
                <thead>
                    <tr>
                        <th>Tanggal Kerja</th>
                        <th>Pelaksana (Karyawan)</th>
                        <th>Aktivitas Kerja</th>
                        <th>Target</th>
                        <th>Realisasi Hasil</th>
                        <th>Pencapaian (%)</th>
                        <th>Catatan</th>
                        <th>Bukti Foto</th>
                        <th>Status Laporan</th>
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
                            $status_badge = '<span class="badge badge-pending" style="font-size: 0.7rem; border-radius: 4px;">Menunggu Verifikasi</span>';
                        } elseif ($row['status'] === 'verified_by_mandor') {
                            $status_badge = '<span class="badge badge-verified" style="font-size: 0.7rem; border-radius: 4px;">Terverifikasi</span>';
                        } elseif ($row['status'] === 'approved') {
                            $status_badge = '<span class="badge badge-approved" style="font-size: 0.7rem; border-radius: 4px;">Disetujui JEM</span>';
                        } elseif ($row['status'] === 'rejected') {
                            if ($row['potongan_penalti'] > 0) {
                                $status_badge = '<span class="badge badge-logout" style="font-size: 0.7rem; border-radius: 4px; background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak (Sanksi)</span>';
                            } else {
                                $status_badge = '<span class="badge badge-logout" style="font-size: 0.7rem; border-radius: 4px; background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak</span>';
                            }
                        } elseif ($row['status'] === 'pending_manajer_tolak') {
                            $status_badge = '<span class="badge badge-pending" style="font-size: 0.7rem; border-radius: 4px; background:#fff3e0; color:#e65100; border:1px solid #ffe0b2;">Tinjauan Sanksi JEM</span>';
                        }
                    ?>
                        <tr style="border-bottom: 1px solid var(--card-border);">
                            <td><?php echo date('d-m-Y', strtotime($row['tanggal_tugas'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nama_karyawan']); ?></strong></td>
                            <td><span class="badge badge-verified" style="font-size:0.75rem; text-transform: none;"><?php echo htmlspecialchars($row['aktivitas']); ?></span></td>
                            <td><?php echo (float)$row['target_jumlah'] . ' ' . htmlspecialchars($row['unit']); ?></td>
                            <td><strong style="color: var(--primary);"><?php echo (float)$realisasi_display . ' ' . htmlspecialchars($row['unit']); ?></strong></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-weight:700; font-size:0.85rem; color: <?php echo $percentage >= 100 ? 'var(--success)' : '#d48b03'; ?>;"><?php echo round($percentage, 1); ?>%</span>
                                    <div class="progress-container" style="width: 70px; height: 7px; margin: 0; background: rgba(0,0,0,0.05); border-radius: 4px;">
                                        <div class="progress-bar <?php echo $percentage >= 100 ? '' : 'gold'; ?>" style="width: <?php echo min($percentage, 100); ?>%; border-radius: 4px;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($row['catatan_karyawan'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-bottom: 4px; line-height: 1.2;">
                                        <strong>Karyawan:</strong> <?php echo htmlspecialchars($row['catatan_karyawan']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($row['catatan_mandor'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--primary-light); margin-top: 3px; line-height: 1.2;">
                                        <strong>Anda:</strong> <?php echo htmlspecialchars($row['catatan_mandor']); ?>
                                        <small style="display:block; color:var(--text-muted); font-size:0.65rem;">(Diverifikasi: <?php echo date('d-m-Y H:i', strtotime($row['tanggal_verifikasi_mandor'])); ?>)</small>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($row['catatan_manajer'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--primary-dark); margin-top: 3px; line-height: 1.2; background: rgba(46,125,50,0.05); padding: 2px 4px; border-radius: 3px; display: inline-block;">
                                        <strong>Catatan Manajer:</strong> <?php echo htmlspecialchars($row['catatan_manajer']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($row['catatan_karyawan']) && empty($row['catatan_mandor']) && empty($row['catatan_manajer'])): ?>
                                    <span style="color: var(--text-muted); font-size:0.8rem; font-style:italic;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['foto_bukti'])): ?>
                                    <img src="../<?php echo htmlspecialchars($row['foto_bukti']); ?>" class="img-proof" style="width: 44px; height: 44px; border-radius: 6px; border: 1px solid var(--card-border);" onclick="openModal('../<?php echo htmlspecialchars($row['foto_bukti']); ?>', 'Bukti Foto: <?php echo htmlspecialchars($row['nama_karyawan']) . ' - ' . htmlspecialchars($row['aktivitas']); ?>')" />
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
                        <td colspan="5" style="text-align: right; padding: 8px;">Indeks Kinerja Diawasi (Avg):</td>
                        <td style="color: <?php echo $predikat_color; ?>; padding: 8px; font-weight: bold;">
                            <?php echo $avg_achievement; ?>%
                        </td>
                        <td style="padding: 8px;">
                            <span class="badge" style="background: <?php echo $predikat_bg; ?>; color: <?php echo $predikat_color; ?>; border: 1px solid <?php echo $predikat_color; ?>; font-size: 0.75rem; border-radius: 4px; padding: 4px 8px; font-weight: bold; display: inline-block;">
                                <?php echo $predikat_text; ?>
                            </span>
                        </td>
                        <td colspan="2" style="padding: 8px;"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Tanda Tangan Pengesahan (Hanya Muncul Saat Cetak/PDF) -->
<div class="print-only" style="margin-top: 40px; display: flex; justify-content: flex-end; page-break-inside: avoid;">
    <div style="text-align: center; width: 250px; font-family: 'Times New Roman', Times, serif;">
        <p style="margin: 0 0 70px 0; font-size: 0.95rem;">
            Jambi, <?php echo date('d-m-Y'); ?><br>
            <strong>Mandor Pengawas</strong>
        </p>
        <div style="border-bottom: 1.5px solid #000; width: 180px; margin: 0 auto; font-weight: bold; font-size: 1rem;">
            <?php echo htmlspecialchars($_SESSION['nama'] ?? 'Mandor Pengawas'); ?>
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
    initProductivityChart('mandorHistoryChart', labels, null, actuals);
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
