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

<div style="margin-bottom: 30px;">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    <h2 style="margin-top: 10px;">Laporan Histori Pengawasan Kerja</h2>
    <p style="color: var(--text-muted);">Pantau seluruh histori pencapaian tugas karyawan di bawah pengawasan Anda</p>
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
<div class="card glass-panel" style="margin-bottom: 25px;">
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
                        <th>Bonus Pencapaian</th>
                        <th>Bukti Foto</th>
                        <th>Status Laporan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history_reports as $row): 
                        // Calculate achievement percentage
                        $percentage = 0;
                        if ($row['target_jumlah'] > 0) {
                            $percentage = ($row['jumlah_realisasi'] / $row['target_jumlah']) * 100;
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
                            <td><strong style="color: var(--primary);"><?php echo (float)$row['jumlah_realisasi'] . ' ' . htmlspecialchars($row['unit']); ?></strong></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-weight:700; font-size:0.85rem; color: <?php echo $percentage >= 100 ? 'var(--success)' : '#d48b03'; ?>;"><?php echo round($percentage, 1); ?>%</span>
                                    <div class="progress-container" style="width: 70px; height: 7px; margin: 0; background: rgba(0,0,0,0.05); border-radius: 4px;">
                                        <div class="progress-bar <?php echo $percentage >= 100 ? '' : 'gold'; ?>" style="width: <?php echo min($percentage, 100); ?>%; border-radius: 4px;"></div>
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
                            <td>
                                <?php if (!empty($row['foto_bukti'])): ?>
                                    <img src="../<?php echo htmlspecialchars($row['foto_bukti']); ?>" class="img-proof" style="width: 44px; height: 44px; border-radius: 6px; border: 1px solid var(--card-border);" onclick="openModal('../<?php echo htmlspecialchars($row['foto_bukti']); ?>', 'Bukti Foto: <?php echo htmlspecialchars($row['nama_karyawan']) . ' - ' . htmlspecialchars($row['aktivitas']); ?>')" />
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:0.8rem; font-style:italic;">Tidak ada foto</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $status_badge; ?></td>
                        </tr>
                        <!-- Collapsible Feedback Row -->
                        <?php if ($row['status'] !== 'pending_mandor' || !empty($row['catatan_karyawan'])): ?>
                            <tr style="background: rgba(46,125,50,0.01);">
                                <td colspan="9" style="padding: 10px 16px; border-top: none; border-bottom: 2px solid var(--card-border);">
                                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                        <?php if (!empty($row['catatan_karyawan'])): ?>
                                            <div style="background: #ffffff; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--card-border); font-size: 0.8rem; flex: 1; min-width: 200px;">
                                                <strong style="color: var(--primary); display:block; margin-bottom:4px;"><i class="fa-solid fa-user-pen"></i> Catatan Karyawan:</strong> 
                                                <span style="font-style: italic; color: var(--text-color);"><?php echo htmlspecialchars($row['catatan_karyawan']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['catatan_mandor'])): ?>
                                            <div style="background: #ffffff; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--primary-light); font-size: 0.8rem; flex: 1; min-width: 200px;">
                                                <strong style="color: var(--primary-light); display:block; margin-bottom:4px;"><i class="fa-solid fa-signature"></i> Catatan Pemeriksaan Anda:</strong> 
                                                <span style="font-style: italic; color: var(--text-color);"><?php echo htmlspecialchars($row['catatan_mandor']); ?></span>
                                                <small style="display:block; color:var(--text-muted); margin-top:5px; font-size:0.7rem;">(Diverifikasi: <?php echo date('d-m-Y H:i', strtotime($row['tanggal_verifikasi_mandor'])); ?>)</small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['catatan_manajer'])): ?>
                                            <div style="background: #e8f5e9; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--primary); font-size: 0.8rem; flex: 1; min-width: 200px;">
                                                <strong style="color: var(--primary-dark); display:block; margin-bottom:4px;"><i class="fa-solid fa-circle-check"></i> Catatan Manajer JEM:</strong> 
                                                <span style="font-style: italic; color: var(--text-color);"><?php echo htmlspecialchars($row['catatan_manajer']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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
