<?php
// manajer/laporan.php
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
$total_net_payout = 0.00;
$total_gross_bonus = 0.00;
$total_penalty_deducted = 0.00;

if (!empty($reports)) {
    foreach ($reports as $rep) {
        $val = (float)$rep['bonus_diterima'];
        $total_net_payout += $val;
        if ($val > 0) {
            $total_gross_bonus += $val;
        } elseif ($val < 0) {
            $total_penalty_deducted += abs($val);
        }
    }
}
?>

<div style="margin-bottom: 30px;" class="no-print">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    <h2 style="margin-top: 10px;">Laporan & Histori Produktivitas</h2>
    <p style="color: var(--text-muted);">Cari, filter, dan cetak histori laporan pencapaian kerja karyawan</p>
</div>

<!-- Print Title Header (Only visible when printing) -->
<div style="display:none; text-align: center; margin-bottom: 30px;" class="print-only">
    <h1 style="color: #000; margin-bottom: 5px;">LAPORAN PRODUKTIVITAS KARYAWAN</h1>
    <h3 style="color: #444; margin-top: 0; font-weight: 500;">PT AGROTAMEX SUMINDO ABADI</h3>
    <p style="font-size: 0.85rem; color: #666; margin-top: 5px;">
        Tanggal Cetak: <?php echo date('d-m-Y H:i'); ?> 
        <?php if (!empty($start_date) || !empty($end_date)): ?>
            | Periode: <?php echo htmlspecialchars($start_date); ?> s/d <?php echo htmlspecialchars($end_date); ?>
        <?php endif; ?>
    </p>
    <hr style="border: 0; border-top: 2px solid #000; margin-top: 15px;">
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger no-print"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filter Panel (Only visible on screen) -->
<div class="card glass-panel no-print" style="margin-bottom: 25px;">
    <h3 class="card-title">Filter Pencarian</h3>
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
                <label for="aktivitas">Aktivitas</label>
                <select name="aktivitas" id="aktivitas" class="form-control">
                    <option value="">-- Semua Aktivitas --</option>
                    <option value="Pemanenan" <?php echo $filter_activity === 'Pemanenan' ? 'selected' : ''; ?>>Pemanenan</option>
                    <option value="Penyemprotan" <?php echo $filter_activity === 'Penyemprotan' ? 'selected' : ''; ?>>Penyemprotan</option>
                    <option value="Pemupukan" <?php echo $filter_activity === 'Pemupukan' ? 'selected' : ''; ?>>Pemupukan</option>
                </select>
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 20px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="id_mandor">Supervisor (Mandor)</label>
                <select name="id_mandor" id="id_mandor" class="form-control">
                    <option value="0">-- Semua Mandor --</option>
                    <?php foreach ($foremen as $m): ?>
                        <option value="<?php echo $m['id_mandor']; ?>" <?php echo $filter_mandor === (int)$m['id_mandor'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['nama']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <a href="laporan.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Terapkan Filter</button>
        </div>
    </form>
</div>

<!-- Ringkasan Bonus Pengeluaran Perusahaan (Filter-Aware) -->
<div class="grid-3 no-print" style="margin-bottom: 25px;">
    <!-- Net Payout Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid var(--primary-light); background: rgba(46,125,50,0.02);">
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
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #2e7d32; background: rgba(46,125,50,0.02);">
        <div style="background: rgba(46,125,50,0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0;">
            <i class="fa-solid fa-gift" style="font-size: 1.4rem; color: #2e7d32;"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Akumulasi Pencapaian Bonus</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: #2e7d32; margin-top: 2px;">
                Rp <?php echo number_format($total_gross_bonus, 0, ',', '.'); ?>
            </div>
        </div>
    </div>

    <!-- Total Sanksi Deductions Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #c62828; background: rgba(198,40,40,0.01);">
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
</div>

<!-- Laporan Table -->
<div class="card glass-panel">
    <div class="card-title">
        <span>Histori Pekerjaan Lapangan (<?php echo count($reports); ?> data ditemukan)</span>
        <button onclick="window.print()" class="btn btn-gold btn-sm no-print">
            <i class="fa-solid fa-print"></i> Cetak Laporan Rapi
        </button>
    </div>

    <?php if (empty($reports)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
            Tidak ada data laporan pekerjaan yang cocok dengan kriteria filter.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Karyawan</th>
                        <th>Mandor</th>
                        <th>Aktivitas</th>
                        <th>Target</th>
                        <th>Realisasi</th>
                        <th>Pencapaian (%)</th>
                        <th>Bonus</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $rep): 
                        $percentage = $rep['target_jumlah'] > 0 ? round(($rep['jumlah_realisasi'] / $rep['target_jumlah']) * 100, 1) : 0;
                        $color = ($percentage >= 100) ? 'var(--primary-light)' : (($percentage >= 80) ? 'var(--gold)' : 'var(--danger)');
                        // override colors for print
                        ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($rep['nama_karyawan']); ?></strong></td>
                            <td><?php echo htmlspecialchars($rep['nama_mandor']); ?></td>
                            <td><?php echo htmlspecialchars($rep['aktivitas']); ?></td>
                            <td><?php echo (float)$rep['target_jumlah'] . ' ' . htmlspecialchars($rep['unit']); ?></td>
                            <td><strong><?php echo (float)$rep['jumlah_realisasi'] . ' ' . htmlspecialchars($rep['unit']); ?></strong></td>
                            <td style="font-weight: 700; color: <?php echo $color; ?>;" class="percent-col">
                                <?php echo $percentage; ?>%
                            </td>
                            <td>
                                <?php if ($rep['bonus_diterima'] > 0): ?>
                                    <strong style="color: var(--success);">Rp <?php echo number_format($rep['bonus_diterima'], 0, ',', '.'); ?></strong>
                                <?php elseif ($rep['bonus_diterima'] < 0): ?>
                                    <strong style="color: #c62828;">-Rp <?php echo number_format(abs($rep['bonus_diterima']), 0, ',', '.'); ?></strong>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size:0.85rem;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($rep['status'] === 'pending_mandor'): ?>
                                    <span class="badge badge-pending">Pending Mandor</span>
                                <?php elseif ($rep['status'] === 'verified_by_mandor'): ?>
                                    <span class="badge badge-verified">Terverifikasi Mandor</span>
                                <?php elseif ($rep['status'] === 'approved'): ?>
                                    <span class="badge badge-approved">Approved</span>
                                <?php elseif ($rep['status'] === 'rejected'): ?>
                                    <?php if ($rep['potongan_penalti'] > 0): ?>
                                        <span class="badge badge-logout" style="background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak (Sanksi)</span>
                                    <?php else: ?>
                                        <span class="badge badge-logout" style="background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak</span>
                                    <?php endif; ?>
                                <?php elseif ($rep['status'] === 'pending_manajer_tolak'): ?>
                                    <span class="badge badge-pending" style="background:#fff3e0; color:#e65100; border:1px solid #ffe0b2;">Tinjauan Sanksi JEM</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Printing print-only class visibility */
    @media screen {
        .print-only { display: none !important; }
    }
    @media print {
        .print-only { display: block !important; }
        .glass-panel { background: #fff !important; color: #000 !important; border: 0 !important; }
        .percent-col { color: #000 !important; }
        th { background: #e0e0e0 !important; color: #000 !important; border: 1px solid #000 !important; }
        td { border: 1px solid #ddd !important; }
    }
</style>

<?php require_once '../includes/footer.php'; ?>
