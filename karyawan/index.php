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

<!-- Welcome Banner -->
<div class="card glass-panel" style="background: linear-gradient(135deg, #1b4d3e 0%, #0d2820 100%); border-left: 5px solid var(--primary-light); color: #fff; padding: 25px; margin-bottom: 25px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; overflow: hidden; position: relative; box-shadow: 0 8px 32px rgba(27,77,62,0.15);">
    <div style="position: absolute; right: -20px; top: -30px; opacity: 0.12; font-size: 8rem; color: #fff; transform: rotate(15deg); pointer-events: none;">
        <i class="fa-solid fa-helmet-safety"></i>
    </div>
    
    <div style="position: relative; z-index: 2;">
        <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--primary-light); font-weight: 700; margin-bottom: 5px;">Portal Karyawan Lapangan</div>
        <h2 style="margin: 0; font-size: 1.6rem; color: #fff; font-weight: 700;">Selamat Bekerja, <?php echo htmlspecialchars($_SESSION['nama'] ?? 'Karyawan'); ?>! 👷‍♂️</h2>
        <p style="margin: 10px 0 0 0; font-size: 0.88rem; color: #d0e7da; max-width: 600px; line-height: 1.5;">
            Laporkan hasil kerja harian Anda secara akurat. Ambil bukti foto secara langsung di lokasi dan pastikan GPS Anda terkunci dengan benar untuk menghindari sanksi penalti.
        </p>
    </div>
    <div class="no-print" style="position: relative; z-index: 2; text-align: right; background: rgba(255,255,255,0.08); padding: 12px 18px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(5px);">
        <div style="font-size: 0.72rem; color: #d0e7da; text-transform: uppercase; font-weight: 600;">Hari Operasional</div>
        <div style="font-size: 1.1rem; font-weight: bold; margin-top: 3px; color: #fff;"><i class="fa-regular fa-calendar-check"></i> <?php echo date('d M Y'); ?></div>
    </div>
</div>

<div class="grid-3-1">
    <!-- Main Left Panel -->
    <div>
        <!-- Today's Tasks -->
        <div class="card glass-panel" style="margin-bottom: 25px; padding: 20px;">
            <h3 class="card-title" style="margin-bottom: 15px;"><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Tugas &amp; Target Hari Ini</h3>
            
            <?php if (empty($today_tasks)): ?>
                <div style="text-align: center; padding: 50px 20px; color: var(--text-muted);">
                    <i class="fa-solid fa-calendar-check" style="font-size: 3.5rem; color: var(--primary-light); margin-bottom: 15px; display: block;"></i>
                    <p style="font-size: 0.95rem; margin: 0;">Tidak ada penugasan atau target kerja yang diberikan untuk Anda hari ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Aktivitas</th>
                                <th>Target</th>
                                <th>Mandor Pengawas</th>
                                <th>Hasil Anda</th>
                                <th>Status Laporan</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_tasks as $task): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($task['aktivitas']); ?></strong></td>
                                    <td>
                                        <span class="badge" style="background: rgba(0,0,0,0.05); color: var(--text-color); font-weight: bold; border-radius: 4px;">
                                            <?php echo (float)$task['target_jumlah'] . ' ' . htmlspecialchars($task['unit']); ?>
                                        </span>
                                    </td>
                                    <td><i class="fa-solid fa-user-tie" style="color:#29b6f6;"></i> <?php echo htmlspecialchars($task['nama_mandor']); ?></td>
                                    <td>
                                        <?php if ($task['report_id']): ?>
                                            <strong style="color: var(--primary-dark);"><?php echo (float)$task['jumlah_realisasi'] . ' ' . htmlspecialchars($task['unit']); ?></strong>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;">-</span>
                                        <?php endif; ?>
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
                                                    <span class="badge" style="background:#ffebee; color:#c62828; border:1px solid #ffcdd2; font-weight: bold;">Ditolak (Sanksi Manipulasi)</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background:#ffebee; color:#c62828; border:1px solid #ffcdd2; font-weight: bold;">Ditolak (Kirim Ulang)</span>
                                                <?php endif; ?>
                                            <?php elseif ($task['report_status'] === 'pending_manajer_tolak'): ?>
                                                <span class="badge" style="background:#fff3e0; color:#e65100; border:1px solid #ffe0b2; font-weight: bold;">Tinjauan Sanksi JEM</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge" style="background: rgba(0,0,0,0.04); color: var(--text-muted); font-style: italic;">Belum Dilaporkan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if (!$task['report_id'] || ($task['report_status'] === 'rejected' && $task['potongan_penalti'] == 0)): ?>
                                            <a href="input_laporan.php?assignment_id=<?php echo $task['id']; ?>" class="btn <?php echo $task['report_status'] === 'rejected' ? 'btn-danger' : 'btn-primary'; ?> btn-sm" style="padding: 5px 12px; font-size: 0.78rem; border-radius: 4px;">
                                                <i class="fa-solid fa-pen-to-square"></i> <?php echo $task['report_status'] === 'rejected' ? 'Ulangi Lapor' : 'Laporkan'; ?>
                                            </a>
                                        <?php elseif ($task['report_status'] === 'rejected' && $task['potongan_penalti'] > 0): ?>
                                            <span class="badge" style="background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; padding: 4px 8px; font-size: 0.75rem;">
                                                <i class="fa-solid fa-ban"></i> Terkunci (Sanksi)
                                            </span>
                                        <?php elseif ($task['report_status'] === 'pending_manajer_tolak'): ?>
                                            <span class="badge" style="background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; padding: 4px 8px; font-size: 0.75rem;">
                                                <i class="fa-solid fa-hourglass-half"></i> Dibekukan
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: rgba(46,125,50,0.1); color: var(--primary-dark); padding: 4px 8px; font-size: 0.75rem;">
                                                <i class="fa-solid fa-circle-check"></i> Selesai
                                            </span>
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
        <div class="card glass-panel" style="padding: 20px;">
            <h3 class="card-title" style="margin-bottom: 15px;"><i class="fa-solid fa-chart-line" style="color: var(--primary);"></i> Grafik Produktivitas Anda (7 Pekerjaan Terakhir Disetujui)</h3>
            <div style="height: 300px; position: relative;">
                <?php if (empty($history)): ?>
                    <div style="text-align: center; padding: 85px 20px; color: var(--text-muted);">
                        <i class="fa-solid fa-chart-area" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
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
        <!-- Profile Badge Card -->
        <div class="card glass-panel" style="margin-bottom: 20px; text-align: center; padding: 25px 20px;">
            <div style="width: 70px; height: 70px; background: rgba(27,77,62,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto; border: 2.5px solid var(--primary-light);">
                <i class="fa-solid fa-user-gear" style="font-size: 2.2rem; color: var(--primary-dark);"></i>
            </div>
            
            <?php
            // Get summary of work
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_tasks, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_tasks FROM work_reports WHERE id_karyawan = ?");
            $stmt->execute([$karyawan_id]);
            $summary = $stmt->fetch();
            $total_submitted = $summary['total_tasks'] ?? 0;
            $total_approved = $summary['approved_tasks'] ?? 0;
            ?>
            <h4 style="margin: 0; font-size: 1.15rem; color: var(--text-color); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Karyawan'); ?></h4>
            <div style="font-size: 0.75rem; color: var(--gold); font-weight: bold; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.5px;">Karyawan Lapangan</div>
            
            <div style="margin-top: 20px; padding: 12px; background: rgba(0,0,0,0.02); border-radius: 8px; border: 1px solid rgba(0,0,0,0.03);">
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary-light);"><?php echo $total_approved; ?></div>
                <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;">Pekerjaan Disetujui</div>
            </div>
            
            <div style="margin-top: 20px; border-top: 1px dashed rgba(0,0,0,0.1); padding-top: 15px; text-align: left; font-size: 0.8rem; display: flex; flex-direction: column; gap: 8px;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Total Laporan Masuk:</span>
                    <strong><?php echo $total_submitted; ?> Laporan</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Status Akun:</span>
                    <strong style="color: #2e7d32;"><i class="fa-solid fa-circle-check"></i> Aktif</strong>
                </div>
            </div>
        </div>
        
        <!-- Sanksi Warning Box (Academic Context) -->
        <div class="card glass-panel" style="padding: 15px; background: rgba(198,40,40,0.02); border-left: 4px solid var(--danger); font-size: 0.78rem; line-height: 1.5;">
            <strong style="color: var(--danger); display: block; margin-bottom: 5px;"><i class="fa-solid fa-circle-exclamation"></i> Penting Penting!</strong>
            <p style="margin: 0; color: var(--text-color);">
                Sistem secara cerdas memantau integritas laporan Anda. Tindakan memanipulasi koordinat GPS, foto palsu, atau hasil fiktif yang terdeteksi oleh Mandor/Manajer akan memicu **sanksi administratif (denda potongan bonus sebesar 10%)**.
            </p>
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
