<?php
// manajer/index.php
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'manajer') {
    header("Location: ../index.php");
    exit;
}

$error = "";

try {
    // 1. Statistics
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM karyawan WHERE status_aktif = 1");
    $total_karyawan = $stmt->fetch()['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM mandor");
    $total_mandor = $stmt->fetch()['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM work_reports WHERE status = 'verified_by_mandor'");
    $pending_approvals = $stmt->fetch()['count'] ?? 0;

    // 2. Fetch pending final approval reports
    $stmt = $pdo->prepare("
        SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan, m.nama as nama_mandor
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        JOIN karyawan k ON r.id_karyawan = k.id_karyawan
        JOIN mandor m ON a.id_mandor = m.id_mandor
        WHERE r.status = 'verified_by_mandor'
        ORDER BY a.tanggal DESC, r.created_at DESC
    ");
    $stmt->execute();
    $pending_reports = $stmt->fetchAll();

    // 3. Fetch data for overall target vs achievement chart (last 7 dates)
    $stmt = $pdo->prepare("
        SELECT a.tanggal, SUM(a.target_jumlah) as total_target, SUM(r.jumlah_realisasi) as total_realisasi
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        WHERE r.status = 'approved'
        GROUP BY a.tanggal
        ORDER BY a.tanggal ASC LIMIT 7
    ");
    $stmt->execute();
    $chart_data = $stmt->fetchAll();

    $chart_labels = [];
    $chart_targets = [];
    $chart_realisasi = [];
    foreach ($chart_data as $data) {
        $chart_labels[] = date('d M', strtotime($data['tanggal']));
        $chart_targets[] = (float)$data['total_target'];
        $chart_realisasi[] = (float)$data['total_realisasi'];
    }

} catch (\PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<div style="margin-bottom: 30px;">
    <h2>Dashboard Manajer</h2>
    <p style="color: var(--text-muted);">PT Agrotamex Sumindo Abadi - Panel Junior Estate Manager (JEM)</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Stats cards -->
<div class="grid-3" style="margin-bottom: 25px;">
    <div class="stat-card glass-panel">
        <div class="stat-icon" style="color: var(--gold); background: rgba(212, 175, 55, 0.1);">
            <i class="fa-solid fa-user-clock"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $pending_approvals; ?></div>
            <div class="stat-label">Persetujuan Pending</div>
        </div>
    </div>

    <div class="stat-card glass-panel">
        <div class="stat-icon" style="color: var(--primary-light); background: rgba(110, 192, 136, 0.1);">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $total_karyawan; ?></div>
            <div class="stat-label">Total Karyawan Lapangan</div>
        </div>
    </div>

    <div class="stat-card glass-panel">
        <div class="stat-icon" style="color: #81d4fa; background: rgba(2, 119, 189, 0.1);">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $total_mandor; ?></div>
            <div class="stat-label">Total Mandor Pengawas</div>
        </div>
    </div>
</div>

<div class="grid-1-3">
    <!-- Quick Actions Panel -->
    <div>
        <div class="card glass-panel">
            <h3 class="card-title">Menu Cepat</h3>
            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px;">
                <a href="targets.php" class="btn btn-primary" style="text-align: left;">
                    <i class="fa-solid fa-plus"></i> Buat Tugas Baru
                </a>
                <a href="karyawan.php" class="btn btn-secondary" style="text-align: left;">
                    <i class="fa-solid fa-user-plus"></i> Kelola Pengguna
                </a>
                <a href="laporan_monitoring.php" class="btn btn-secondary" style="text-align: left;">
                    <i class="fa-solid fa-list-check"></i> Laporan Monitoring
                </a>
                <a href="laporan_produktivitas.php" class="btn btn-secondary" style="text-align: left;">
                    <i class="fa-solid fa-print"></i> Laporan Produktivitas
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content Panel -->
    <div>
        <!-- Chart Section -->
        <div class="card glass-panel" style="margin-bottom: 25px;">
            <h3 class="card-title">Perbandingan Total Target vs Realisasi (Disetujui)</h3>
            <div style="height: 250px; position: relative;">
                <?php if (empty($chart_data)): ?>
                    <div style="text-align: center; padding: 70px 20px; color: var(--text-muted);">
                        Belum ada riwayat produktivitas yang disetujui untuk dianalisis.
                    </div>
                <?php else: ?>
                    <canvas id="managerProdChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Approvals list -->
        <div class="card glass-panel">
            <h3 class="card-title">Laporan Menunggu Persetujuan Final</h3>
            
            <?php if (empty($pending_reports)): ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <i class="fa-solid fa-circle-check" style="font-size: 3rem; color: var(--primary-light); margin-bottom: 15px; display: block;"></i>
                    Semua laporan pekerjaan kelompok mandor telah diproses.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Karyawan</th>
                                <th>Aktivitas</th>
                                <th>Pencapaian</th>
                                <th>Verifikator (Mandor)</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_reports as $rep): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($rep['nama_karyawan']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($rep['aktivitas']); ?></td>
                                    <td>
                                        <strong style="color: var(--gold);"><?php echo (float)$rep['jumlah_realisasi'] . ' ' . htmlspecialchars($rep['unit']); ?></strong> 
                                        <span style="font-size:0.8rem; color:var(--text-muted);">/ <?php echo (float)$rep['target_jumlah'] . ' ' . htmlspecialchars($rep['unit']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($rep['nama_mandor']); ?></td>
                                    <td>
                                        <a href="verifikasi.php?report_id=<?php echo $rep['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fa-solid fa-stamp"></i> Review & Approve
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($chart_data)): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const labels = <?php echo json_encode($chart_labels); ?>;
        const targets = <?php echo json_encode($chart_targets); ?>;
        const realisasi = <?php echo json_encode($chart_realisasi); ?>;
        initProductivityChart('managerProdChart', labels, targets, realisasi);
    });
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
