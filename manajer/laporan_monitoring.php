<?php
// manajer/laporan_monitoring.php
// LAPORAN MONITORING: fokus pada PROSES / ALUR KERJA (siapa mengerjakan apa,
// sudah diverifikasi mandor / disetujui manajer atau belum). Tidak mencampur
// dengan angka produktivitas (target vs realisasi, bonus) - itu ada di
// laporan_produktivitas.php.
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'manajer') {
    header("Location: ../index.php");
    exit;
}

$error = "";
$nama = $_SESSION['nama'] ?? 'Manajer';

// 1. Fetch Mandor & Karyawan for Filter Dropdowns
try {
    $stmt = $pdo->query("SELECT id_mandor, nama FROM mandor ORDER BY nama ASC");
    $foremen = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT id_karyawan, nama FROM karyawan ORDER BY nama ASC");
    $employees = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// 2. Capture filters
$start_date   = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date     = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$filter_mandor    = isset($_GET['id_mandor']) ? (int)$_GET['id_mandor'] : 0;
$filter_karyawan  = isset($_GET['id_karyawan']) ? (int)$_GET['id_karyawan'] : 0;
$filter_status    = isset($_GET['status']) ? trim($_GET['status']) : '';

$query_str = "
    SELECT r.id, r.status, r.catatan_mandor, r.catatan_manajer,
           r.tanggal_verifikasi_mandor, r.tanggal_verifikasi_manajer,
           a.tanggal, a.aktivitas,
           k.nama as nama_karyawan, m.nama as nama_mandor
    FROM work_reports r
    JOIN assignments a ON r.id_assignment = a.id
    JOIN karyawan k ON r.id_karyawan = k.id_karyawan
    JOIN mandor m ON a.id_mandor = m.id_mandor
    WHERE 1=1
";
$params = [];

if (!empty($start_date)) { $query_str .= " AND a.tanggal >= ?"; $params[] = $start_date; }
if (!empty($end_date))   { $query_str .= " AND a.tanggal <= ?"; $params[] = $end_date; }
if ($filter_mandor > 0)  { $query_str .= " AND a.id_mandor = ?"; $params[] = $filter_mandor; }
if ($filter_karyawan > 0){ $query_str .= " AND a.id_karyawan = ?"; $params[] = $filter_karyawan; }
if (!empty($filter_status)) { $query_str .= " AND r.status = ?"; $params[] = $filter_status; }

$query_str .= " ORDER BY a.tanggal DESC, r.created_at DESC";

$reports = [];
try {
    $stmt = $pdo->prepare($query_str);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error = "Gagal memuat laporan: " . $e->getMessage();
}

// 3. Ringkasan jumlah laporan PER STATUS (ini yang membuat laporan ini
// benar-benar "monitoring": manajer langsung tahu berapa yang masih macet
// di suatu tahap alur kerja).
$status_labels = [
    'pending_mandor'        => 'Menunggu Mandor',
    'verified_by_mandor'    => 'Terverifikasi Mandor',
    'approved'              => 'Disetujui',
    'rejected'              => 'Ditolak',
    'pending_manajer_tolak' => 'Tinjauan Sanksi',
];
$status_counts = array_fill_keys(array_keys($status_labels), 0);
foreach ($reports as $rep) {
    if (isset($status_counts[$rep['status']])) {
        $status_counts[$rep['status']]++;
    }
}
$total_reports = count($reports);
?>

<div style="margin-bottom: 30px;" class="no-print">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    <h2 style="margin-top: 10px;">Laporan Monitoring</h2>
    <p style="color: var(--text-muted);">Pantau alur verifikasi setiap laporan kerja: sudah sampai tahap mana, dan apa yang masih tertahan/pending</p>
</div>

<!-- Kop Surat (hanya tampil saat dicetak) -->
<div style="display:none;" class="print-only">
    <div style="display: flex; align-items: center; gap: 15px; border-bottom: 3px solid #000; padding-bottom: 12px; margin-bottom: 20px;">
        <i class="fa-solid fa-seedling" style="font-size: 2.2rem; color: #1e5235;"></i>
        <div>
            <div style="font-size: 1.15rem; font-weight: 700; color: #000;">PT AGROTAMEX SUMINDO ABADI</div>
            <div style="font-size: 0.8rem; color: #444;">Sistem Pemantauan Produktivitas Karyawan Perkebunan</div>
        </div>
    </div>
    <div style="text-align: center; margin-bottom: 20px;">
        <h2 style="color: #000; margin-bottom: 4px; text-decoration: underline;">LAPORAN MONITORING ALUR KERJA</h2>
        <p style="font-size: 0.85rem; color: #444; margin-top: 5px;">
            No. Laporan: MON/<?php echo date('Ym'); ?>/<?php echo str_pad((string)$total_reports, 4, '0', STR_PAD_LEFT); ?>
            &nbsp;|&nbsp; Dicetak: <?php echo date('d-m-Y H:i'); ?>
            <?php if (!empty($start_date) || !empty($end_date)): ?>
                &nbsp;|&nbsp; Periode: <?php echo !empty($start_date) ? htmlspecialchars($start_date) : 'Awal Data'; ?> s/d <?php echo !empty($end_date) ? htmlspecialchars($end_date) : 'Sekarang'; ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger no-print"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filter Panel -->
<div class="card glass-panel no-print" style="margin-bottom: 25px;">
    <h3 class="card-title">Filter Pencarian</h3>
    <form method="GET" action="laporan_monitoring.php">
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
                <label for="status">Status Alur</label>
                <select name="status" id="status" class="form-control">
                    <option value="">-- Semua Status --</option>
                    <?php foreach ($status_labels as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $filter_status === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
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
            <a href="laporan_monitoring.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Terapkan Filter</button>
        </div>
    </form>
</div>

<!-- Ringkasan Status (inti dari laporan monitoring) -->
<div class="grid-3 no-print" style="margin-bottom: 15px; display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px;">
    <?php
    $status_colors = [
        'pending_mandor'        => '#e0a800',
        'verified_by_mandor'    => '#0d6efd',
        'approved'              => '#2e7d32',
        'rejected'              => '#c62828',
        'pending_manajer_tolak' => '#e65100',
    ];
    foreach ($status_labels as $key => $label):
        $count = $status_counts[$key];
        $color = $status_colors[$key];
    ?>
        <div class="card glass-panel" style="padding: 14px; text-align:center; border-top: 3px solid <?php echo $color; ?>;">
            <div style="font-size: 1.6rem; font-weight: 700; color: <?php echo $color; ?>;"><?php echo $count; ?></div>
            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.3px; margin-top: 4px;"><?php echo $label; ?></div>
        </div>
    <?php endforeach; ?>
</div>
<p class="no-print" style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 25px;">
    Total <?php echo $total_reports; ?> laporan sesuai filter di atas. Klik salah satu status pada filter untuk fokus ke tahap tertentu (misalnya cek semua yang masih "Menunggu Mandor").
</p>

<!-- Grafik Distribusi Status -->
<?php if ($total_reports > 0): ?>
<div class="card glass-panel no-print" style="margin-bottom: 25px;">
    <h3 class="card-title"><i class="fa-solid fa-chart-column" style="color: var(--primary);"></i> Distribusi Status Laporan</h3>
    <div style="height: 220px; position: relative;">
        <canvas id="statusDistChart"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- Tabel Alur Status -->
<div class="card glass-panel">
    <div class="card-title">
        <span>Detail Alur Verifikasi (<?php echo $total_reports; ?> data)</span>
        <button onclick="window.print()" class="btn btn-gold btn-sm no-print">
            <i class="fa-solid fa-print"></i> Cetak
        </button>
    </div>

    <?php if (empty($reports)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
            Tidak ada data yang cocok dengan kriteria filter.
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
                        <th>Status Alur</th>
                        <th>Diverifikasi Mandor</th>
                        <th>Diverifikasi Manajer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $rep): ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($rep['nama_karyawan']); ?></strong></td>
                            <td><?php echo htmlspecialchars($rep['nama_mandor']); ?></td>
                            <td><?php echo htmlspecialchars($rep['aktivitas']); ?></td>
                            <td>
                                <span class="badge" style="background: <?php echo $status_colors[$rep['status']]; ?>1a; color: <?php echo $status_colors[$rep['status']]; ?>; border: 1px solid <?php echo $status_colors[$rep['status']]; ?>44;">
                                    <?php echo $status_labels[$rep['status']]; ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">
                                <?php echo $rep['tanggal_verifikasi_mandor'] ? date('d-m-Y H:i', strtotime($rep['tanggal_verifikasi_mandor'])) : '-'; ?>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">
                                <?php echo $rep['tanggal_verifikasi_manajer'] ? date('d-m-Y H:i', strtotime($rep['tanggal_verifikasi_manajer'])) : '-'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Blok Tanda Tangan (hanya tampil saat dicetak) -->
<div class="print-only" style="display:none; margin-top: 50px; page-break-inside: avoid;">
    <p style="font-size: 0.85rem; color: #000; text-align: right; margin-bottom: 60px;">
        Batam, <?php echo date('d F Y'); ?>
    </p>
    <div style="display: flex; justify-content: space-between; text-align: center; font-size: 0.85rem; color: #000;">
        <div style="width: 40%;">
            <p style="margin-bottom: 60px;">Dibuat oleh,</p>
            <p style="border-top: 1px solid #000; padding-top: 5px; margin: 0;"><strong>Sistem Agrotamex</strong><br>(Otomatis)</p>
        </div>
        <div style="width: 40%;">
            <p style="margin-bottom: 60px;">Disetujui oleh,</p>
            <p style="border-top: 1px solid #000; padding-top: 5px; margin: 0;"><strong><?php echo htmlspecialchars($nama); ?></strong><br>Manajer</p>
        </div>
    </div>
</div>

<style>
    @media screen { .print-only { display: none !important; } }
    @media print {
        .print-only { display: block !important; }
        .glass-panel { background: #fff !important; color: #000 !important; border: 0 !important; }
        th { background: #e0e0e0 !important; color: #000 !important; border: 1px solid #000 !important; }
        td { border: 1px solid #ddd !important; }
    }
</style>

<script>
<?php if ($total_reports > 0): ?>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('statusDistChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_values($status_labels)); ?>,
            datasets: [{
                label: 'Jumlah Laporan',
                data: <?php echo json_encode(array_values($status_counts)); ?>,
                backgroundColor: <?php echo json_encode(array_values($status_colors)); ?>,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
