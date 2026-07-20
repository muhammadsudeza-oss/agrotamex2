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

    // Total Harvested Oil Palm (Approved Pemanenan)
    $stmt = $pdo->query("
        SELECT SUM(r.jumlah_realisasi) as total 
        FROM work_reports r 
        JOIN assignments a ON r.id_assignment = a.id 
        WHERE r.status = 'approved' AND a.aktivitas = 'Pemanenan'
    ");
    $total_harvest = $stmt->fetch()['total'] ?? 0;

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

<!-- Welcome Banner -->
<div class="card glass-panel" style="background: linear-gradient(135deg, var(--primary-dark) 0%, #113824 100%); border-left: 5px solid var(--primary-light); color: #fff; padding: 25px; margin-bottom: 25px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; overflow: hidden; position: relative; box-shadow: 0 8px 32px rgba(46,125,50,0.15);">
    <div style="position: absolute; right: -20px; top: -30px; opacity: 0.12; font-size: 8rem; color: #fff; transform: rotate(15deg); pointer-events: none;">
        <i class="fa-solid fa-leaf"></i>
    </div>
    
    <div style="position: relative; z-index: 2;">
        <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--primary-light); font-weight: 700; margin-bottom: 5px;">Panel Evaluasi &amp; Pengawasan</div>
        <h2 style="margin: 0; font-size: 1.6rem; color: #fff; font-weight: 700;">Selamat Datang, <?php echo htmlspecialchars($_SESSION['nama'] ?? 'Manajer JEM'); ?>! 👋</h2>
        <p style="margin: 10px 0 0 0; font-size: 0.88rem; color: #d0e7da; max-width: 600px; line-height: 1.5;">
            Pantau disiplin kehadiran, lokasi GPS kerja, dan hasil produksi perkebunan kelapa sawit secara real-time. Anda memiliki <strong><?php echo $pending_approvals; ?> laporan</strong> yang menunggu persetujuan final.
        </p>
    </div>
    <div class="no-print" style="position: relative; z-index: 2; text-align: right; background: rgba(255,255,255,0.08); padding: 12px 18px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(5px);">
        <div style="font-size: 0.72rem; color: #d0e7da; text-transform: uppercase; font-weight: 600;">Hari &amp; Tanggal</div>
        <div style="font-size: 1.1rem; font-weight: bold; margin-top: 3px; color: #fff;"><i class="fa-regular fa-calendar-days"></i> <?php echo date('d M Y'); ?></div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Stats cards grid (4 columns) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px;">
    <!-- Pending Approvals -->
    <a href="#pending-approvals-section" style="text-decoration: none; color: inherit;">
        <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid var(--gold); margin: 0; transition: transform 0.2s ease; cursor: pointer;">
            <div style="background: rgba(212, 175, 55, 0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0; color: var(--gold); font-size: 1.3rem;">
                <i class="fa-solid fa-stamp"></i>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Pending Approval</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-color); margin-top: 2px;">
                    <?php echo $pending_approvals; ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">laporan</span>
                </div>
            </div>
        </div>
    </a>

    <!-- Total Karyawan -->
    <a href="karyawan.php" style="text-decoration: none; color: inherit;">
        <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid var(--primary-light); margin: 0; transition: transform 0.2s ease; cursor: pointer;">
            <div style="background: rgba(110, 192, 136, 0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0; color: var(--primary-light); font-size: 1.3rem;">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Karyawan Aktif</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-color); margin-top: 2px;">
                    <?php echo $total_karyawan; ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">orang</span>
                </div>
            </div>
        </div>
    </a>

    <!-- Total Mandor -->
    <a href="karyawan.php" style="text-decoration: none; color: inherit;">
        <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #29b6f6; margin: 0; transition: transform 0.2s ease; cursor: pointer;">
            <div style="background: rgba(41, 182, 246, 0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0; color: #29b6f6; font-size: 1.3rem;">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Mandor Pengawas</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-color); margin-top: 2px;">
                    <?php echo $total_mandor; ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">kelompok</span>
                </div>
            </div>
        </div>
    </a>

    <!-- Total Tonnage Harvested -->
    <a href="laporan_produktivitas.php" style="text-decoration: none; color: inherit;">
        <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid var(--primary); margin: 0; transition: transform 0.2s ease; cursor: pointer;">
            <div style="background: rgba(46,125,50,0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0; color: var(--primary); font-size: 1.3rem;">
                <i class="fa-solid fa-cubes"></i>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Hasil Panen</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-color); margin-top: 2px;">
                    <?php echo number_format($total_harvest, 0, ',', '.'); ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">kg</span>
                </div>
            </div>
        </div>
    </a>
</div>

<div class="grid-1-3">
    <!-- Quick Actions Panel -->
    <div>
        <div class="card glass-panel" style="margin-bottom: 25px;">
            <h3 class="card-title" style="margin-bottom: 15px;"><i class="fa-solid fa-bolt" style="color: var(--gold);"></i> Kendali Sistem</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="targets.php" class="btn btn-primary" style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 8px; text-decoration: none;">
                    <i class="fa-solid fa-plus-circle" style="font-size: 1.2rem;"></i>
                    <div style="text-align: left;">
                        <div style="font-weight: bold; font-size: 0.88rem;">Buat Penugasan</div>
                        <div style="font-size: 0.72rem; opacity: 0.85; font-weight: normal;">Kirim target harian ke karyawan</div>
                    </div>
                </a>
                
                <a href="karyawan.php" class="btn btn-secondary" style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 8px; background: rgba(255,255,255,0.4); border: 1px solid rgba(0,0,0,0.06); color: var(--text-color); text-decoration: none; transition: background 0.2s;">
                    <i class="fa-solid fa-users-gear" style="font-size: 1.2rem; color: var(--primary);"></i>
                    <div style="text-align: left;">
                        <div style="font-weight: bold; font-size: 0.88rem; color: var(--text-color);">Kelola Pengguna</div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: normal;">Karyawan, mandor &amp; admin</div>
                    </div>
                </a>
                
                <a href="laporan_monitoring.php" class="btn btn-secondary" style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 8px; background: rgba(255,255,255,0.4); border: 1px solid rgba(0,0,0,0.06); color: var(--text-color); text-decoration: none; transition: background 0.2s;">
                    <i class="fa-solid fa-desktop" style="font-size: 1.2rem; color: var(--primary);"></i>
                    <div style="text-align: left;">
                        <div style="font-weight: bold; font-size: 0.88rem; color: var(--text-color);">Laporan Monitoring</div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: normal;">Pengawasan fisik &amp; GPS Nominatim</div>
                    </div>
                </a>

                <a href="laporan_produktivitas.php" class="btn btn-secondary" style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 8px; background: rgba(255,255,255,0.4); border: 1px solid rgba(0,0,0,0.06); color: var(--text-color); text-decoration: none; transition: background 0.2s;">
                    <i class="fa-solid fa-chart-line" style="font-size: 1.2rem; color: var(--primary);"></i>
                    <div style="text-align: left;">
                        <div style="font-weight: bold; font-size: 0.88rem; color: var(--text-color);">Laporan Produktivitas</div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: normal;">Evaluasi kinerja &amp; bonus insentif</div>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- System Guidelines Brief -->
        <div class="card glass-panel" style="padding: 15px; font-size: 0.8rem; line-height: 1.5; color: var(--text-color); background: rgba(255,255,255,0.2);">
            <strong style="color: var(--primary-dark); display: block; margin-bottom: 5px;"><i class="fa-solid fa-circle-info"></i> Petunjuk Cepat:</strong>
            <ul style="margin: 0; padding-left: 15px;">
                <li>Gunakan <strong>Buat Penugasan</strong> untuk merilis instruksi kerja harian bagi mandor &amp; karyawan.</li>
                <li><strong>Laporan Monitoring</strong> murni menampilkan data bukti foto lapangan dan koordinat GPS Nominatim.</li>
                <li><strong>Laporan Produktivitas</strong> mengalkulasi target vs realisasi, denda, dan bonus bersih.</li>
            </ul>
        </div>
    </div>

    <!-- Main Content Panel -->
    <div>
        <!-- Chart Section -->
        <div class="card glass-panel" style="margin-bottom: 25px; padding: 20px;">
            <h3 class="card-title" style="margin-bottom: 15px;"><i class="fa-solid fa-chart-line" style="color: var(--primary);"></i> Tren Produktivitas Harian (Target vs Realisasi disetujui)</h3>
            <div style="height: 250px; position: relative;">
                <?php if (empty($chart_data)): ?>
                    <div style="text-align: center; padding: 75px 20px; color: var(--text-muted);">
                        <i class="fa-solid fa-chart-area" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
                        Belum ada riwayat produktivitas yang disetujui untuk dianalisis chart.
                    </div>
                <?php else: ?>
                    <canvas id="managerProdChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Approvals list -->
        <div id="pending-approvals-section" class="card glass-panel" style="padding: 20px;">
            <h3 class="card-title" style="margin-bottom: 15px;"><i class="fa-solid fa-stamp" style="color: var(--gold);"></i> Laporan Menunggu Persetujuan Final JEM</h3>
            
            <?php if (empty($pending_reports)): ?>
                <div style="text-align: center; padding: 50px 20px; color: var(--text-muted);">
                    <i class="fa-solid fa-circle-check" style="font-size: 3.5rem; color: var(--primary-light); margin-bottom: 15px; display: block;"></i>
                    <strong>Luar Biasa!</strong> Semua laporan pekerjaan dari mandor telah selesai diproses.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Karyawan</th>
                                <th>Aktivitas</th>
                                <th>Realisasi / Target</th>
                                <th>Verifikator (Mandor)</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_reports as $rep): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($rep['nama_karyawan']); ?></strong></td>
                                    <td>
                                        <span class="badge" style="background: rgba(46,125,50,0.1); color: var(--primary-dark); font-weight: bold;">
                                            <?php echo htmlspecialchars($rep['aktivitas']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--primary-dark);"><?php echo (float)$rep['jumlah_realisasi'] . ' ' . htmlspecialchars($rep['unit']); ?></strong> 
                                        <span style="font-size:0.8rem; color:var(--text-muted);">/ <?php echo (float)$rep['target_jumlah'] . ' ' . htmlspecialchars($rep['unit']); ?></span>
                                    </td>
                                    <td><i class="fa-solid fa-user-tie" style="color: #29b6f6;"></i> <?php echo htmlspecialchars($rep['nama_mandor']); ?></td>
                                    <td style="text-align: center;">
                                        <a href="verifikasi.php?report_id=<?php echo $rep['id']; ?>" class="btn btn-primary btn-sm" style="padding: 5px 12px; font-size: 0.78rem; border-radius: 4px; display: inline-flex; align-items: center; gap: 5px;">
                                            <i class="fa-solid fa-circle-check"></i> Proses
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
