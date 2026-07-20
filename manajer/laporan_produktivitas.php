<?php
// manajer/laporan_produktivitas.php
// LAPORAN PRODUKTIVITAS: fokus pada ANGKA HASIL KERJA yang sudah final
// (status = approved saja - laporan yang masih pending/rejected belum bisa
// dianggap capaian resmi). Data direkap/di-agregasi per karyawan, per
// aktivitas, dan per bulan - bukan ditampilkan mentah per baris seperti
// sebelumnya, supaya kelihatan pola & rankingnya, bukan cuma daftar.
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'manajer') {
    header("Location: ../index.php");
    exit;
}

$error = "";
$nama = $_SESSION['nama'] ?? 'Manajer';

try {
    $stmt = $pdo->query("SELECT id_mandor, nama FROM mandor ORDER BY nama ASC");
    $foremen = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT id_karyawan, nama FROM karyawan ORDER BY nama ASC");
    $employees = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Filters
$start_date  = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date    = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$filter_mandor   = isset($_GET['id_mandor']) ? (int)$_GET['id_mandor'] : 0;
$filter_karyawan = isset($_GET['id_karyawan']) ? (int)$_GET['id_karyawan'] : 0;
$filter_activity = isset($_GET['aktivitas']) ? trim($_GET['aktivitas']) : '';

// Shared WHERE clause: hanya laporan yang sudah disetujui (approved) yang
// dihitung sebagai produktivitas resmi.
$where = " r.status = 'approved' ";
$params = [];
if (!empty($start_date))    { $where .= " AND a.tanggal >= ?"; $params[] = $start_date; }
if (!empty($end_date))      { $where .= " AND a.tanggal <= ?"; $params[] = $end_date; }
if ($filter_mandor > 0)     { $where .= " AND a.id_mandor = ?"; $params[] = $filter_mandor; }
if ($filter_karyawan > 0)   { $where .= " AND a.id_karyawan = ?"; $params[] = $filter_karyawan; }
if (!empty($filter_activity)){ $where .= " AND a.aktivitas = ?"; $params[] = $filter_activity; }

$rekap_karyawan = [];
$rekap_aktivitas = [];
$rekap_bulanan = [];

try {
    // 1. Rekap per karyawan (ranking) - metrik yang dipakai untuk
    //    dibandingkan HANYA yang tidak tergantung satuan: persentase capaian
    //    & bonus (Rp), karena target/realisasi tiap aktivitas satuannya
    //    beda (Ton, Karung, Hektar) sehingga tidak bisa dijumlah langsung.
    $sql = "
        SELECT k.nama,
               COUNT(r.id) AS jumlah_laporan,
               AVG(r.jumlah_realisasi / a.target_jumlah * 100) AS avg_pencapaian,
               SUM(CASE WHEN r.bonus_diterima > 0 THEN r.bonus_diterima ELSE 0 END) AS total_bonus,
               SUM(CASE WHEN r.bonus_diterima < 0 THEN ABS(r.bonus_diterima) ELSE 0 END) AS total_sanksi,
               SUM(r.bonus_diterima) AS net_bonus
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        JOIN karyawan k ON r.id_karyawan = k.id_karyawan
        WHERE $where
        GROUP BY k.id_karyawan, k.nama
        ORDER BY avg_pencapaian DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rekap_karyawan = $stmt->fetchAll();

    // 2. Rekap per aktivitas - di sini target & realisasi BOLEH dijumlah
    //    (bukan hanya persentase) karena dikelompokkan per aktivitas,
    //    sehingga satuannya konsisten dan angka mentahnya jadi bermakna.
    $sql2 = "
        SELECT a.aktivitas, a.unit,
               COUNT(r.id) AS jumlah_laporan,
               SUM(a.target_jumlah) AS total_target,
               SUM(r.jumlah_realisasi) AS total_realisasi,
               AVG(r.jumlah_realisasi / a.target_jumlah * 100) AS avg_pencapaian
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        WHERE $where
        GROUP BY a.aktivitas, a.unit
        ORDER BY total_realisasi DESC
    ";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($params);
    $rekap_aktivitas = $stmt2->fetchAll();

    // 2b. Rincian target vs realisasi per karyawan PER aktivitas (unit tetap
    //     konsisten dalam satu baris, jadi angka mentahnya bisa dijumlah
    //     dan dibandingkan langsung - ini yang biasanya diminta dospem
    //     supaya datanya tidak hanya persentase abstrak).
    $sql2b = "
        SELECT k.nama, a.aktivitas, a.unit,
               COUNT(r.id) AS jumlah_laporan,
               SUM(a.target_jumlah) AS total_target,
               SUM(r.jumlah_realisasi) AS total_realisasi,
               AVG(r.jumlah_realisasi / a.target_jumlah * 100) AS avg_pencapaian
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        JOIN karyawan k ON r.id_karyawan = k.id_karyawan
        WHERE $where
        GROUP BY k.id_karyawan, k.nama, a.aktivitas, a.unit
        ORDER BY k.nama ASC, a.aktivitas ASC
    ";
    $stmt2b = $pdo->prepare($sql2b);
    $stmt2b->execute($params);
    $rekap_detail = $stmt2b->fetchAll();

    // 3. Rekap per bulan - untuk melihat tren naik/turun dari waktu ke waktu.
    $sql3 = "
        SELECT DATE_FORMAT(a.tanggal, '%Y-%m') AS bulan,
               COUNT(r.id) AS jumlah_laporan,
               AVG(r.jumlah_realisasi / a.target_jumlah * 100) AS avg_pencapaian,
               SUM(CASE WHEN r.bonus_diterima > 0 THEN r.bonus_diterima ELSE 0 END) AS total_bonus
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        WHERE $where
        GROUP BY bulan
        ORDER BY bulan ASC
    ";
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute($params);
    $rekap_bulanan = $stmt3->fetchAll();

} catch (\PDOException $e) {
    $error = "Gagal memuat rekap: " . $e->getMessage();
}

$chart_labels = [];
$chart_values = [];
foreach ($rekap_bulanan as $b) {
    $ts = strtotime($b['bulan'] . '-01');
    $chart_labels[] = date('M Y', $ts);
    $chart_values[] = round((float)$b['avg_pencapaian'], 1);
}

$grand_total_bonus = array_sum(array_column($rekap_karyawan, 'total_bonus'));
$grand_total_sanksi = array_sum(array_column($rekap_karyawan, 'total_sanksi'));
$grand_total_laporan = array_sum(array_column($rekap_karyawan, 'jumlah_laporan'));
?>

<div style="margin-bottom: 30px;" class="no-print">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    <h2 style="margin-top: 10px;">Laporan Produktivitas</h2>
    <p style="color: var(--text-muted);">Rekap capaian kerja yang sudah disetujui: ranking karyawan, performa per aktivitas, dan tren bulanan</p>
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
        <h2 style="color: #000; margin-bottom: 4px; text-decoration: underline;">LAPORAN PRODUKTIVITAS KARYAWAN</h2>
        <p style="font-size: 0.85rem; color: #444; margin-top: 5px;">
            No. Laporan: PROD/<?php echo date('Ym'); ?>/<?php echo str_pad((string)$grand_total_laporan, 4, '0', STR_PAD_LEFT); ?>
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
    <h3 class="card-title">Filter Rekap</h3>
    <form method="GET" action="laporan_produktivitas.php">
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
            <a href="laporan_produktivitas.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Terapkan Filter</button>
        </div>
    </form>
</div>

<!-- Ringkasan -->
<div class="grid-3 no-print" style="margin-bottom: 25px;">
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #2e7d32;">
        <div style="background: rgba(46,125,50,0.1); padding: 12px; border-radius: 50%; width: 48px; height: 48px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="fa-solid fa-gift" style="font-size: 1.4rem; color: #2e7d32;"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Bonus Disetujui</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: #2e7d32;">Rp <?php echo number_format($grand_total_bonus, 0, ',', '.'); ?></div>
        </div>
    </div>
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #c62828;">
        <div style="background: rgba(198,40,40,0.1); padding: 12px; border-radius: 50%; width: 48px; height: 48px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="fa-solid fa-circle-minus" style="font-size: 1.4rem; color: #c62828;"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Potongan Sanksi</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: #c62828;">Rp <?php echo number_format($grand_total_sanksi, 0, ',', '.'); ?></div>
        </div>
    </div>
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid var(--primary-light);">
        <div style="background: rgba(46,125,50,0.1); padding: 12px; border-radius: 50%; width: 48px; height: 48px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="fa-solid fa-check-double" style="font-size: 1.4rem; color: var(--primary-light);"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Laporan Disetujui</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary);"><?php echo $grand_total_laporan; ?></div>
        </div>
    </div>
</div>

<!-- Tren Bulanan -->
<?php if (!empty($rekap_bulanan)): ?>
<div class="card glass-panel no-print" style="margin-bottom: 25px;">
    <h3 class="card-title"><i class="fa-solid fa-chart-line" style="color: var(--primary);"></i> Tren Rata-Rata Pencapaian per Bulan</h3>
    <div style="height: 250px; position: relative;">
        <canvas id="trendBulananChart"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- Rekap Per Karyawan (Ranking) -->
<div class="card glass-panel" style="margin-bottom: 25px;">
    <div class="card-title">
        <span><i class="fa-solid fa-ranking-star" style="color: var(--gold);"></i> Ranking Produktivitas per Karyawan</span>
        <button onclick="window.print()" class="btn btn-gold btn-sm no-print">
            <i class="fa-solid fa-print"></i> Cetak Laporan
        </button>
    </div>
    <?php if (empty($rekap_karyawan)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
            Belum ada laporan berstatus "disetujui" pada rentang filter ini.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Karyawan</th>
                        <th>Jumlah Laporan</th>
                        <th>Rata-rata Pencapaian</th>
                        <th>Total Bonus</th>
                        <th>Total Sanksi</th>
                        <th>Bonus Bersih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 1; foreach ($rekap_karyawan as $row):
                        $avg = round((float)$row['avg_pencapaian'], 1);
                        $color = $avg >= 100 ? 'var(--primary-light)' : ($avg >= 80 ? 'var(--gold)' : 'var(--danger)');
                    ?>
                        <tr>
                            <td><strong>#<?php echo $rank++; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                            <td><?php echo (int)$row['jumlah_laporan']; ?></td>
                            <td style="font-weight:700; color: <?php echo $color; ?>;"><?php echo $avg; ?>%</td>
                            <td style="color: var(--success);">Rp <?php echo number_format($row['total_bonus'], 0, ',', '.'); ?></td>
                            <td style="color: #c62828;">Rp <?php echo number_format($row['total_sanksi'], 0, ',', '.'); ?></td>
                            <td><strong>Rp <?php echo number_format($row['net_bonus'], 0, ',', '.'); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Rincian Target vs Realisasi per Karyawan per Aktivitas -->
<div class="card glass-panel" style="margin-bottom: 25px;">
    <h3 class="card-title"><i class="fa-solid fa-scale-balanced" style="color: var(--primary);"></i> Rincian Target vs Realisasi per Karyawan</h3>
    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: -8px; margin-bottom: 15px;">
        Angka target dan realisasi dikelompokkan per jenis aktivitas agar satuannya konsisten (Ton/Karung/Hektar tidak dicampur).
    </p>
    <?php if (empty($rekap_detail)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">Tidak ada data.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th>Aktivitas</th>
                        <th>Jumlah Laporan</th>
                        <th>Total Target</th>
                        <th>Total Realisasi</th>
                        <th>Pencapaian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rekap_detail as $row):
                        $avg = round((float)$row['avg_pencapaian'], 1);
                        $color = $avg >= 100 ? 'var(--primary-light)' : ($avg >= 80 ? 'var(--gold)' : 'var(--danger)');
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['aktivitas']); ?></td>
                            <td><?php echo (int)$row['jumlah_laporan']; ?></td>
                            <td><?php echo number_format($row['total_target'], 2); ?> <?php echo htmlspecialchars($row['unit']); ?></td>
                            <td><?php echo number_format($row['total_realisasi'], 2); ?> <?php echo htmlspecialchars($row['unit']); ?></td>
                            <td style="font-weight:700; color: <?php echo $color; ?>;"><?php echo $avg; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Rekap Per Aktivitas -->
<div class="card glass-panel" style="margin-bottom: 25px;">
    <h3 class="card-title"><i class="fa-solid fa-seedling" style="color: var(--primary);"></i> Rekap per Jenis Aktivitas</h3>
    <?php if (empty($rekap_aktivitas)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">Tidak ada data.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Aktivitas</th>
                        <th>Jumlah Laporan</th>
                        <th>Total Target</th>
                        <th>Total Realisasi</th>
                        <th>Rata-rata Pencapaian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rekap_aktivitas as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['aktivitas']); ?></strong></td>
                            <td><?php echo (int)$row['jumlah_laporan']; ?></td>
                            <td><?php echo number_format($row['total_target'], 2); ?> <?php echo htmlspecialchars($row['unit']); ?></td>
                            <td><?php echo number_format($row['total_realisasi'], 2); ?> <?php echo htmlspecialchars($row['unit']); ?></td>
                            <td style="font-weight:700;"><?php echo round((float)$row['avg_pencapaian'], 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Rekap Per Bulan -->
<div class="card glass-panel">
    <h3 class="card-title"><i class="fa-solid fa-calendar-days" style="color: var(--primary);"></i> Rekap per Bulan</h3>
    <?php if (empty($rekap_bulanan)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">Tidak ada data.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th>Jumlah Laporan</th>
                        <th>Rata-rata Pencapaian</th>
                        <th>Total Bonus</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rekap_bulanan as $row): ?>
                        <tr>
                            <td><strong><?php echo date('F Y', strtotime($row['bulan'] . '-01')); ?></strong></td>
                            <td><?php echo (int)$row['jumlah_laporan']; ?></td>
                            <td style="font-weight:700;"><?php echo round((float)$row['avg_pencapaian'], 1); ?>%</td>
                            <td style="color: var(--success);">Rp <?php echo number_format($row['total_bonus'], 0, ',', '.'); ?></td>
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
<?php if (!empty($rekap_bulanan)): ?>
document.addEventListener('DOMContentLoaded', () => {
    const labels = <?php echo json_encode($chart_labels); ?>;
    const values = <?php echo json_encode($chart_values); ?>;
    initProductivityChart('trendBulananChart', labels, null, values);
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
