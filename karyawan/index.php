<?php
// karyawan/index.php
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'karyawan') {
    header("Location: ../index.php");
    exit;
}

$karyawan_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// 1. Fetch Today's Assignments for this Karyawan
try {
    $stmt = $pdo->prepare("
        SELECT a.*, m.nama as nama_mandor, r.id as report_id, r.jumlah_realisasi, r.status as report_status, r.foto_bukti, r.potongan_penalti
        FROM assignments a
        JOIN mandor m ON a.id_mandor = m.id_mandor
        LEFT JOIN work_reports r ON a.id = r.id_assignment AND r.id_karyawan = ?
        WHERE a.id_karyawan = ? AND a.tanggal = ?
    ");
    $stmt->execute([$karyawan_id, $karyawan_id, $today]);
    $today_tasks = $stmt->fetchAll();

    // 2. Fetch Performance history (last 7 days reports) for Chart
    $stmt = $pdo->prepare("
        SELECT a.tanggal, a.aktivitas, a.target_jumlah, r.jumlah_realisasi
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        WHERE r.id_karyawan = ? AND r.status = 'approved'
        ORDER BY a.tanggal ASC LIMIT 7
    ");
    $stmt->execute([$karyawan_id]);
    $history = $stmt->fetchAll();
    
    // Prepare chart data
    $chart_labels = [];
    $chart_targets = [];
    $chart_actuals = [];
    foreach ($history as $h) {
        $chart_labels[] = date('d M', strtotime($h['tanggal'])) . " (" . $h['aktivitas'] . ")";
        $chart_targets[] = (float)$h['target_jumlah'];
        $chart_actuals[] = (float)$h['jumlah_realisasi'];
    }

} catch (\PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
?>

<div style="margin-bottom: 30px;">
    <h2>Dashboard Karyawan</h2>
    <p style="color: var(--text-muted);">Sistem Pemantauan Produktivitas PT Agrotamex Sumindo Abadi</p>
</div>

<div class="grid-3-1">
    <!-- Main Left Panel -->
    <div>
        <div class="card glass-panel">
            <h3 class="card-title">Tugas & Target Hari Ini (<?php echo date('d F Y'); ?>)</h3>
            
            <?php if (empty($today_tasks)): ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="fa-solid fa-calendar-check" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 15px;"></i>
                    <p style="color: var(--text-muted);">Tidak ada target kerja yang ditugaskan untuk Anda hari ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Aktivitas</th>
                                <th>Target</th>
                                <th>Mandor Pengawas</th>
                                <th>Realisasi</th>
                                <th>Status Laporan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_tasks as $task): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($task['aktivitas']); ?></strong></td>
                                    <td><?php echo (float)$task['target_jumlah'] . ' ' . htmlspecialchars($task['unit']); ?></td>
                                    <td><?php echo htmlspecialchars($task['nama_mandor']); ?></td>
                                    <td>
                                        <?php 
                                        if ($task['report_id']) {
                                            echo (float)$task['jumlah_realisasi'] . ' ' . htmlspecialchars($task['unit']); 
                                        } else {
                                            echo "-";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($task['report_id']): ?>
                                            <?php if ($task['report_status'] === 'pending_mandor'): ?>
                                                <span class="badge badge-pending">Menunggu Mandor</span>
                                            <?php elseif ($task['report_status'] === 'verified_by_mandor'): ?>
                                                <span class="badge badge-verified">Terverifikasi Mandor</span>
                                            <?php elseif ($task['report_status'] === 'approved'): ?>
                                                <span class="badge badge-approved">Disetujui Manajer</span>
                                            <?php elseif ($task['report_status'] === 'rejected'): ?>
                                                <?php if ($task['potongan_penalti'] > 0): ?>
                                                    <span class="badge badge-logout" style="background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak (Sanksi Manipulasi)</span>
                                                <?php else: ?>
                                                    <span class="badge badge-logout" style="background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak (Kirim Ulang)</span>
                                                <?php endif; ?>
                                            <?php elseif ($task['report_status'] === 'pending_manajer_tolak'): ?>
                                                <span class="badge badge-pending" style="background:#fff3e0; color:#e65100; border:1px solid #ffe0b2;">Tinjauan Sanksi JEM</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="opacity: 0.6; font-style: italic;">Belum Dilaporkan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$task['report_id'] || ($task['report_status'] === 'rejected' && $task['potongan_penalti'] == 0)): ?>
                                            <a href="input_laporan.php?assignment_id=<?php echo $task['id']; ?>" class="btn <?php echo $task['report_status'] === 'rejected' ? 'btn-danger' : 'btn-primary'; ?> btn-sm">
                                                <i class="fa-solid fa-pen-to-square"></i> <?php echo $task['report_status'] === 'rejected' ? 'Ulangi Lapor' : 'Laporkan'; ?>
                                            </a>
                                        <?php elseif ($task['report_status'] === 'rejected' && $task['potongan_penalti'] > 0): ?>
                                            <a href="#" class="btn btn-secondary btn-sm" style="pointer-events: none; opacity: 0.5; background: #ffebee; color: #c62828; border: 1px solid #ffcdd2;">
                                                <i class="fa-solid fa-ban"></i> Sanksi Diterapkan
                                            </a>
                                        <?php elseif ($task['report_status'] === 'pending_manajer_tolak'): ?>
                                            <a href="#" class="btn btn-secondary btn-sm" style="pointer-events: none; opacity: 0.5;">
                                                <i class="fa-solid fa-hourglass-half"></i> Dibekukan
                                            </a>
                                        <?php else: ?>
                                            <a href="#" class="btn btn-secondary btn-sm" style="pointer-events: none; opacity: 0.5;">
                                                <i class="fa-solid fa-check"></i> Selesai
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Productivity Chart -->
        <div class="card glass-panel">
            <h3 class="card-title">Grafik Produktivitas Anda (7 Pekerjaan Terakhir Disetujui)</h3>
            <div style="height: 300px; position: relative;">
                <?php if (empty($history)): ?>
                    <div style="text-align: center; padding: 80px 20px; color: var(--text-muted);">
                        Belum ada riwayat pekerjaan yang disetujui manajer untuk menampilkan grafik.
                    </div>
                <?php else: ?>
                    <canvas id="employeeProdChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar Right Panel -->
    <div>
        <div class="card glass-panel">
            <h3 class="card-title"><i class="fa-solid fa-award" style="color: var(--gold);"></i> Ringkasan Kerja</h3>
            
            <div style="margin-top: 15px; text-align: center;">
                <?php
                // Get summary of work
                $stmt = $pdo->prepare("SELECT COUNT(*) as total_tasks, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_tasks FROM work_reports WHERE id_karyawan = ?");
                $stmt->execute([$karyawan_id]);
                $summary = $stmt->fetch();
                $total_submitted = $summary['total_tasks'] ?? 0;
                $total_approved = $summary['approved_tasks'] ?? 0;
                ?>
                <div class="stat-value" style="font-size: 3rem; color: var(--primary-light);"><?php echo $total_approved; ?></div>
                <div class="stat-label">Pekerjaan Disetujui</div>
                
                <div style="margin-top: 25px; border-top: 1px solid rgba(110, 192, 136, 0.1); padding-top: 15px; text-align: left;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 8px;">
                        <span>Total Laporan Masuk:</span>
                        <strong><?php echo $total_submitted; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 8px;">
                        <span>Peran Anda:</span>
                        <strong style="color: var(--gold);">Karyawan Lapangan</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 8px;">
                        <span>Status Akun:</span>
                        <strong style="color: #a5d6a7;">Aktif</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($history)): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const labels = <?php echo json_encode($chart_labels); ?>;
        const targets = <?php echo json_encode($chart_targets); ?>;
        const actuals = <?php echo json_encode($chart_actuals); ?>;
        initProductivityChart('employeeProdChart', labels, targets, actuals);
    });
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
