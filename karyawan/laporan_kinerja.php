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
?>

<div style="margin-bottom: 30px;">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    <h2 style="margin-top: 10px;">Laporan & Histori Kinerja Anda</h2>
    <p style="color: var(--text-muted);">Pantau seluruh catatan hasil kerja, persentase pencapaian target, dan status verifikasi mandor/manajer</p>
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
<div class="grid-3" style="margin-bottom: 25px;">
    <!-- Net Wallet Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid var(--primary-light); background: rgba(46,125,50,0.02);">
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
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #2e7d32; background: rgba(46,125,50,0.02);">
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
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #c62828; background: rgba(198,40,40,0.01);">
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
</div>

<!-- Chart Card (Top of reports list) -->
<div class="card glass-panel" style="margin-bottom: 25px;">
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
                        <th>Bukti Foto</th>
                        <th>Status Verifikasi</th>
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
                            <td><strong style="color: var(--primary);"><?php echo (float)$row['jumlah_realisasi'] . ' ' . htmlspecialchars($row['unit']); ?></strong></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-weight:700; color: <?php echo $percentage >= 100 ? 'var(--success)' : '#d48b03'; ?>;"><?php echo round($percentage, 1); ?>%</span>
                                    <div class="progress-container" style="width: 60px; height: 6px; margin: 0; background: rgba(0,0,0,0.05); border-radius: 4px;">
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
                            <td><?php echo htmlspecialchars($row['nama_mandor']); ?></td>
                            <td>
                                <?php if (!empty($row['foto_bukti'])): ?>
                                    <img src="../<?php echo htmlspecialchars($row['foto_bukti']); ?>" class="img-proof" onclick="openModal('../<?php echo htmlspecialchars($row['foto_bukti']); ?>', 'Bukti Foto: <?php echo htmlspecialchars($row['aktivitas']) . ' - ' . date('d-m-Y', strtotime($row['tanggal_tugas'])); ?>')" />
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:0.8rem; font-style:italic;">Tidak ada foto</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $status_badge; ?></td>
                        </tr>
                        <!-- Detail feedback collapsible row if notes exist -->
                        <?php if (!empty($row['catatan_mandor']) || !empty($row['catatan_manajer']) || !empty($row['catatan_karyawan'])): ?>
                            <tr style="background: rgba(46,125,50,0.01);">
                                <td colspan="8" style="padding: 10px 16px; border-top:none; border-bottom: 2px solid var(--card-border);">
                                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                        <?php if (!empty($row['catatan_karyawan'])): ?>
                                            <div style="background: #ffffff; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--card-border); font-size: 0.8rem; flex: 1; min-width: 200px;">
                                                <strong style="color: var(--primary); display:block; margin-bottom:4px;"><i class="fa-solid fa-user-pen"></i> Catatan Anda:</strong> 
                                                <span style="font-style: italic; color: var(--text-color);"><?php echo htmlspecialchars($row['catatan_karyawan']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['catatan_mandor'])): ?>
                                            <div style="background: #ffffff; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--primary-light); font-size: 0.8rem; flex: 1; min-width: 200px;">
                                                <strong style="color: var(--primary-light); display:block; margin-bottom:4px;"><i class="fa-solid fa-signature"></i> Catatan Mandor Pengawas:</strong> 
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
    initProductivityChart('performanceProdChart', labels, null, actuals);
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
