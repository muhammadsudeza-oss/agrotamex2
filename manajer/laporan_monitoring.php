<?php
// manajer/laporan_monitoring.php
// LAPORAN MONITORING: Fokus Murni pada ALUR VERIFIKASI & SLA (Menunggu, Diverifikasi, Disetujui, Ditolak, Backlog SLA).
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
           r.tanggal_verifikasi_mandor, r.tanggal_verifikasi_manajer, r.jumlah_realisasi, r.potongan_penalti, r.bonus_diterima, r.catatan_karyawan, r.foto_bukti,
           a.tanggal, a.aktivitas, a.target_jumlah, a.unit,
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

// 3. Ringkasan jumlah laporan PER STATUS
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

// 4. ANALISIS SLA & BACKLOG (Laporan Tertahan)
$sla_hari_mandor  = 2;
$sla_hari_manajer = 2;
$today = new DateTime();
$tindak_lanjut = [];
$total_tindak_lanjut = 0;

foreach ($reports as $rep) {
    $hari = 0;
    $tahap = '';
    $mulai = null;

    if ($rep['status'] === 'pending_mandor') {
        $mulai = $rep['tanggal'];
        $ambang = $sla_hari_mandor;
        $tahap = 'Menunggu verifikasi Mandor';
    } elseif ($rep['status'] === 'verified_by_mandor' && !empty($rep['tanggal_verifikasi_mandor'])) {
        $mulai = $rep['tanggal_verifikasi_mandor'];
        $ambang = $sla_hari_manajer;
        $tahap = 'Menunggu persetujuan Manajer';
    } elseif ($rep['status'] === 'pending_manajer_tolak') {
        $mulai = $rep['tanggal_verifikasi_mandor'] ?: $rep['tanggal'];
        $ambang = $sla_hari_manajer;
        $tahap = 'Menunggu tinjauan sanksi Manajer';
    }

    if ($mulai !== null) {
        $hari = (int)$today->diff(new DateTime($mulai))->days;
        if ($hari >= $ambang) {
            $rep['hari_tertahan'] = $hari;
            $rep['tahap_tertahan'] = $tahap;
            $mandor_key = $rep['nama_mandor'];
            if (!isset($tindak_lanjut[$mandor_key])) {
                $tindak_lanjut[$mandor_key] = [];
            }
            $tindak_lanjut[$mandor_key][] = $rep;
            $total_tindak_lanjut++;
        }
    }
}
?>

<div style="margin-bottom: 25px;" class="no-print">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    <h2 style="margin-top: 10px;">Laporan Monitoring Alur Verifikasi &amp; SLA</h2>
    <p style="color: var(--text-muted);">Pemantauan alur verifikasi bertingkat, status laporan kerja, dan analisis laporan tertahan (SLA)</p>
</div>

<!-- Kop Surat Resmi (Cetak Only) -->
<div style="display:none;" class="print-only">
    <div style="display: flex; align-items: center; justify-content: center; gap: 20px; border-bottom: 3px double #000; padding-bottom: 12px; margin-bottom: 18px;">
        <img src="../assets/logo.png" alt="Logo PT" style="height: 55px; width: auto;">
        <div style="text-align: center;">
            <h2 style="font-family: 'Times New Roman', Times, serif; font-size: 1.5rem; font-weight: bold; margin: 0; color: #000; letter-spacing: 1px; white-space: nowrap;">PT AGROTAMEX SUMINDO ABADI</h2>
            <p style="font-family: 'Times New Roman', Times, serif; font-size: 0.85rem; margin: 4px 0 0 0; color: #000;">Desa Nyogan, Kecamatan Mestong, Kabupaten Muaro Jambi, Provinsi Jambi</p>
        </div>
    </div>
    <div style="text-align: center; margin-bottom: 20px;">
        <h3 style="font-family: 'Times New Roman', Times, serif; font-size: 1.25rem; font-weight: bold; text-decoration: underline; margin: 0 0 5px 0; color: #000;">
            LAPORAN MONITORING ALUR VERIFIKASI &amp; SLA
        </h3>
        <p style="font-family: 'Times New Roman', Times, serif; font-size: 0.85rem; color: #000; margin: 0;">
            Manajer: <strong><?php echo htmlspecialchars($nama); ?></strong> &nbsp;|&nbsp; Tanggal Cetak: <?php echo date('d-m-Y H:i'); ?> WIB 
            <?php if (!empty($start_date) || !empty($end_date)): ?>
                &nbsp;|&nbsp; Periode: <?php echo !empty($start_date) ? htmlspecialchars($start_date) : 'Awal Data'; ?> s/d <?php echo !empty($end_date) ? htmlspecialchars($end_date) : 'Sekarang'; ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger no-print"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filter Panel Inline -->
<div class="card glass-panel no-print" style="margin-bottom: 20px; padding: 14px 18px;">
    <form method="GET" action="laporan_monitoring.php" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 0;">
        <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-color); display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-filter" style="color: var(--primary-light);"></i> Filter:
        </span>
        
        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 6px 10px; font-size: 0.8rem; height: 36px; width: 140px; margin: 0;" title="Mulai Tanggal">
        <span style="font-size: 0.8rem; color: var(--text-muted);">s/d</span>
        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 6px 10px; font-size: 0.8rem; height: 36px; width: 140px; margin: 0;" title="Sampai Tanggal">
        
        <select name="status" class="form-control" style="padding: 6px 10px; font-size: 0.8rem; height: 36px; width: 155px; margin: 0;">
            <option value="">Semua Status</option>
            <?php foreach ($status_labels as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $filter_status === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        
        <select name="id_mandor" class="form-control" style="padding: 6px 10px; font-size: 0.8rem; height: 36px; width: 150px; margin: 0;">
            <option value="0">Semua Mandor</option>
            <?php foreach ($foremen as $m): ?>
                <option value="<?php echo $m['id_mandor']; ?>" <?php echo $filter_mandor === (int)$m['id_mandor'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($m['nama']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <select name="id_karyawan" class="form-control" style="padding: 6px 10px; font-size: 0.8rem; height: 36px; width: 150px; margin: 0;">
            <option value="0">Semua Karyawan</option>
            <?php foreach ($employees as $k): ?>
                <option value="<?php echo $k['id_karyawan']; ?>" <?php echo $filter_karyawan === (int)$k['id_karyawan'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($k['nama']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <div style="display: flex; gap: 6px; margin-left: auto;">
            <a href="laporan_monitoring.php" class="btn btn-secondary" style="padding: 0 12px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; height: 36px; border-radius: 6px;" title="Reset Filter"><i class="fa-solid fa-rotate-left"></i></a>
            <button type="submit" class="btn btn-primary" style="padding: 0 16px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; gap: 6px; height: 36px; border-radius: 6px;"><i class="fa-solid fa-magnifying-glass"></i> Cari</button>
        </div>
    </form>
</div>

<!-- Navigation Tabs Rapi (Monitoring Alur & SLA) -->
<div class="report-tabs-header no-print" style="display: flex; gap: 6px; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px;">
    <button class="tab-btn active" onclick="switchTab(event, 'tab-detail-verifikasi')" style="padding: 10px 18px; font-weight: 600; font-size: 0.85rem; border: none; background: transparent; cursor: pointer; border-bottom: 3px solid var(--primary-light); color: var(--primary-light); display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-file-invoice"></i> 1. Detail Alur Verifikasi (<?php echo $total_reports; ?> Laporan)
    </button>
    <button class="tab-btn" onclick="switchTab(event, 'tab-laporan-tertahan')" style="padding: 10px 18px; font-weight: 600; font-size: 0.85rem; border: none; background: transparent; cursor: pointer; border-bottom: 3px solid transparent; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-triangle-exclamation"></i> 2. Laporan Tertahan / SLA (<?php echo $total_tindak_lanjut; ?>)
    </button>
</div>

<!-- ================= TAB 1: DETAIL ALUR VERIFIKASI ================= -->
<div id="tab-detail-verifikasi" class="tab-content-panel" style="display: block;">
    <!-- Counters per Status -->
    <div class="grid-3 no-print" style="margin-bottom: 20px; display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px;">
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
            <div class="card glass-panel" style="padding: 12px; text-align:center; border-top: 3px solid <?php echo $color; ?>; margin:0;">
                <div style="font-size: 1.4rem; font-weight: 700; color: <?php echo $color; ?>;"><?php echo $count; ?></div>
                <div style="font-size: 0.68rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-top: 2px;"><?php echo $label; ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Grafik Distribusi Status Alur Verifikasi -->
    <?php if ($total_reports > 0): ?>
    <div class="card glass-panel no-print" style="margin-bottom: 20px; padding: 18px;">
        <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--text-color); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-chart-column" style="color: var(--primary-light);"></i> Grafik Distribusi Status Alur Verifikasi
        </h4>
        <div style="height: 200px; position: relative;">
            <canvas id="monitoringStatusChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <div class="card glass-panel" style="margin-bottom: 25px;">
        <div class="card-title no-print">
            <span>Detail Alur Verifikasi Laporan Kerja (<?php echo $total_reports; ?> Data)</span>
            <button onclick="window.print()" class="btn btn-gold btn-sm no-print"><i class="fa-solid fa-print"></i> Cetak Laporan</button>
        </div>

        <h3 class="print-only" style="font-family: 'Times New Roman', Times, serif; font-size: 1.1rem; font-weight: bold; margin-bottom: 10px; display:none;">1. Rincian Detail Alur Verifikasi Laporan Kerja</h3>

        <?php if (empty($reports)): ?>
            <div style="text-align: center; padding: 30px 20px; color: var(--text-muted);">Tidak ada data yang cocok dengan kriteria filter.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="width: 100%; table-layout: fixed;">
                    <thead>
                        <tr>
                            <th style="width: 10%;">Tanggal</th>
                            <th style="width: 14%;">Karyawan</th>
                            <th style="width: 14%;">Mandor</th>
                            <th style="width: 14%;">Aktivitas</th>
                            <th style="width: 14%;">Status Alur</th>
                            <th style="width: 12%;">Verifikasi Mandor</th>
                            <th style="width: 12%;">Verifikasi Manajer</th>
                            <th class="no-print" style="width: 10%; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $rep): 
                            $detail_json = base64_encode(json_encode([
                                'nama_karyawan'    => $rep['nama_karyawan'],
                                'nama_mandor'      => $rep['nama_mandor'] ?? '-',
                                'tanggal_kerja'    => date('d F Y', strtotime($rep['tanggal'])),
                                'aktivitas'        => $rep['aktivitas'],
                                'target_jumlah'    => (float)$rep['target_jumlah'],
                                'unit'             => $rep['unit'],
                                'jumlah_realisasi' => (float)$rep['jumlah_realisasi'],
                                'catatan_karyawan' => $rep['catatan_karyawan'] ?? '',
                                'foto_bukti'       => !empty($rep['foto_bukti']) ? '../' . $rep['foto_bukti'] : '',
                                'status'           => $rep['status'],
                                'status_label'     => $status_labels[$rep['status']] ?? $rep['status'],
                                'catatan_mandor'   => $rep['catatan_mandor'] ?? '',
                                'waktu_mandor'     => $rep['tanggal_verifikasi_mandor'] ? date('d-m-Y H:i', strtotime($rep['tanggal_verifikasi_mandor'])) : '',
                                'catatan_manajer'  => $rep['catatan_manajer'] ?? '',
                                'waktu_manajer'    => $rep['tanggal_verifikasi_manajer'] ? date('d-m-Y H:i', strtotime($rep['tanggal_verifikasi_manajer'])) : '',
                                'bonus_diterima'   => (float)($rep['bonus_diterima'] ?? 0),
                            ]));
                        ?>
                            <tr>
                                <td style="white-space: nowrap; font-size: 0.82rem; text-align: center;"><?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($rep['nama_karyawan']); ?></strong></td>
                                <td><?php echo htmlspecialchars($rep['nama_mandor']); ?></td>
                                <td><?php echo htmlspecialchars($rep['aktivitas']); ?></td>
                                <td style="text-align: center;">
                                    <span class="badge" style="background: <?php echo $status_colors[$rep['status']]; ?>1a; color: <?php echo $status_colors[$rep['status']]; ?>; border: 1px solid <?php echo $status_colors[$rep['status']]; ?>44;">
                                        <?php echo $status_labels[$rep['status']]; ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.78rem; white-space: nowrap; text-align: center; color: var(--text-muted);"><?php echo $rep['tanggal_verifikasi_mandor'] ? date('d-m-Y H:i', strtotime($rep['tanggal_verifikasi_mandor'])) : '-'; ?></td>
                                <td style="font-size: 0.78rem; white-space: nowrap; text-align: center; color: var(--text-muted);"><?php echo $rep['tanggal_verifikasi_manajer'] ? date('d-m-Y H:i', strtotime($rep['tanggal_verifikasi_manajer'])) : '-'; ?></td>
                                <td class="no-print" style="text-align: center;">
                                    <button type="button" class="btn btn-secondary btn-sm" data-detail="<?php echo $detail_json; ?>" onclick="openMonitoringDetailModal(this)" style="padding: 4px 12px; font-size: 0.78rem; font-weight: 600;">
                                        <i class="fa-solid fa-list-check" style="color: var(--primary-light);"></i> Detail
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ================= TAB 2: LAPORAN TERTAHAN / BACKLOG SLA ================= -->
<div id="tab-laporan-tertahan" class="tab-content-panel" style="display: none;">
    <div class="card glass-panel" style="margin-bottom: 25px; border-left: 5px solid #c62828;">
        <div class="card-title no-print">
            <span style="color: #c62828;"><i class="fa-solid fa-triangle-exclamation"></i> Laporan Tertahan / Butuh Tindak Lanjut</span>
            <span class="badge" style="background:#c628281a; color:#c62828; border:1px solid #c6282844;">
                <?php echo $total_tindak_lanjut; ?> laporan tertahan
            </span>
        </div>

        <h3 class="print-only" style="font-family: 'Times New Roman', Times, serif; font-size: 1.1rem; font-weight: bold; margin-bottom: 10px; display:none;">2. Analisis Laporan Tertahan (Backlog SLA)</h3>

        <p style="color: var(--text-muted); font-size: 0.83rem; margin-top: -8px; margin-bottom: 15px;" class="no-print">
            Laporan yang tertahan &ge; <?php echo $sla_hari_mandor; ?> hari di suatu tahap alur verifikasi. Dikelompokkan per Mandor untuk evaluasi SLA.
        </p>

        <?php if ($total_tindak_lanjut === 0): ?>
            <div style="text-align: center; padding: 30px 20px; color: var(--text-muted);">
                <i class="fa-solid fa-circle-check" style="color:#2e7d32; margin-right:6px; font-size: 1.2rem;"></i>
                Seluruh alur verifikasi berjalan lancar. Tidak ada laporan yang tertahan melewati batas SLA.
            </div>
        <?php else: ?>
            <?php foreach ($tindak_lanjut as $mandor_name => $list): ?>
                <div style="margin-bottom: 18px;">
                    <div style="font-weight: 700; margin-bottom: 8px; font-size: 0.88rem;">
                        <i class="fa-solid fa-user-tie" style="color: var(--text-muted); margin-right: 4px;"></i>
                        Mandor: <?php echo htmlspecialchars($mandor_name); ?> (<?php echo count($list); ?> laporan tertahan)
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Pelaksana (Karyawan)</th>
                                    <th>Aktivitas</th>
                                    <th>Tanggal Kerja</th>
                                    <th>Tahap Tertahan</th>
                                    <th>Lama Tertahan</th>
                                    <th class="no-print" style="text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list as $rep): 
                                    $detail_json = base64_encode(json_encode([
                                        'nama_karyawan'    => $rep['nama_karyawan'],
                                        'tanggal_kerja'    => date('d F Y', strtotime($rep['tanggal'])),
                                        'aktivitas'        => $rep['aktivitas'],
                                        'target_jumlah'    => (float)$rep['target_jumlah'],
                                        'unit'             => $rep['unit'],
                                        'jumlah_realisasi' => (float)$rep['jumlah_realisasi'],
                                        'catatan_karyawan' => $rep['catatan_karyawan'] ?? '',
                                        'foto_bukti'       => !empty($rep['foto_bukti']) ? '../' . $rep['foto_bukti'] : '',
                                        'status'           => $rep['status'],
                                        'status_label'     => $status_labels[$rep['status']] ?? $rep['status'],
                                        'catatan_mandor'   => $rep['catatan_mandor'] ?? '',
                                        'waktu_mandor'     => $rep['tanggal_verifikasi_mandor'] ? date('d-m-Y H:i', strtotime($rep['tanggal_verifikasi_mandor'])) : '',
                                        'catatan_manajer'  => $rep['catatan_manajer'] ?? '',
                                        'waktu_manajer'    => $rep['tanggal_verifikasi_manajer'] ? date('d-m-Y H:i', strtotime($rep['tanggal_verifikasi_manajer'])) : '',
                                        'bonus_diterima'   => (float)($rep['bonus_diterima'] ?? 0),
                                    ]));
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($rep['nama_karyawan']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($rep['aktivitas']); ?></td>
                                        <td><?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></td>
                                        <td><?php echo htmlspecialchars($rep['tahap_tertahan']); ?></td>
                                        <td>
                                            <span class="badge" style="background:#e651001a; color:#e65100; border:1px solid #e6510044; font-weight:700;">
                                                <?php echo $rep['hari_tertahan']; ?> hari
                                            </span>
                                        </td>
                                        <td class="no-print" style="text-align: center;">
                                            <button type="button" class="btn btn-secondary btn-sm" data-detail="<?php echo $detail_json; ?>" onclick="openMonitoringDetailModal(this)" style="padding: 4px 12px; font-size: 0.78rem; font-weight: 600;">
                                                <i class="fa-solid fa-list-check" style="color: var(--primary-light);"></i> Detail
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Blok Tanda Tangan Resmi (Cetak Only) -->
<div class="print-only" style="display:none; margin-top: 40px; page-break-inside: avoid;">
    <div style="display: flex; justify-content: flex-end; font-family: 'Times New Roman', Times, serif; font-size: 0.9rem; color: #000;">
        <div style="width: 250px; text-align: center;">
            <p style="margin-bottom: 5px;">Jambi, <?php echo date('d F Y'); ?></p>
            <p style="margin-bottom: 60px;">Disetujui oleh,</p>
            <p style="border-top: 1px solid #000; padding-top: 5px; margin: 0;"><strong><?php echo htmlspecialchars($nama); ?></strong><br>Estate Manager</p>
        </div>
    </div>
</div>

<style>
    @media screen { 
        .print-only { display: none !important; } 
    }
    @media print {
        @page { size: A4 portrait; margin: 1.5cm 1.5cm 1.5cm 1.5cm; }
        html, body { background: #fff !important; margin: 0 !important; padding: 0 !important; width: 100% !important; font-family: "Times New Roman", Times, serif !important; font-size: 8.5pt !important; color: #000 !important; }
        *, html, body, div, p, span, h1, h2, h3, h4, h5, h6, table, th, td, tr, strong, b, small {
            font-family: "Times New Roman", Times, serif !important;
            color: #000 !important;
            border-color: #000 !important;
            box-shadow: none !important;
            text-shadow: none !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        i, .fa, .fas, .far, .fab, .fa-solid { display: none !important; }
        .no-print, header.top-header, aside.sidebar, footer, .report-tabs-header, .btn, .alert-info, .card-title, .grid-3 { display: none !important; }
        body.has-sidebar .main-wrapper,
        body .main-wrapper,
        .main-wrapper, .main-content, .container, .tab-content-panel, .card, .glass-panel {
            margin-left: 0 !important;
            margin-right: 0 !important;
            margin-top: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            box-shadow: none !important;
            border: none !important;
            background: transparent !important;
        }
        .print-only { display: block !important; }
        .table-responsive { overflow: visible !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
        table, table.table { width: 100% !important; table-layout: fixed !important; border-collapse: collapse !important; margin-left: 0 !important; margin-right: 0 !important; margin-top: 10px !important; margin-bottom: 20px !important; }
        th { background: #f2f2f2 !important; color: #000 !important; border: 1px solid #000 !important; text-align: center !important; font-weight: bold !important; padding: 6px 4px !important; font-size: 8.5pt !important; word-wrap: break-word !important; }
        td { border: 1px solid #000 !important; color: #000 !important; padding: 5px 4px !important; font-size: 8pt !important; vertical-align: middle !important; word-wrap: break-word !important; overflow-wrap: break-word !important; }
        td:first-child, td:nth-child(6), td:nth-child(7) { white-space: nowrap !important; }
        .badge { background: transparent !important; border: none !important; color: #000 !important; font-weight: bold !important; padding: 0 !important; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('monitoringStatusChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Menunggu Mandor', 'Terverifikasi Mandor', 'Disetujui', 'Ditolak', 'Tinjauan Sanksi'],
                datasets: [{
                    label: 'Jumlah Laporan',
                    data: [
                        <?php echo $status_counts['pending_mandor']; ?>,
                        <?php echo $status_counts['verified_by_mandor']; ?>,
                        <?php echo $status_counts['approved']; ?>,
                        <?php echo $status_counts['rejected']; ?>,
                        <?php echo $status_counts['pending_manajer_tolak']; ?>
                    ],
                    backgroundColor: ['#e0a800', '#0d6efd', '#2e7d32', '#c62828', '#e65100'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});

function switchTab(evt, tabId) {
    evt.preventDefault();
    const tabPanels = document.querySelectorAll('.tab-content-panel');
    tabPanels.forEach(panel => panel.style.display = 'none');
    
    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => {
        btn.style.borderBottom = '3px solid transparent';
        btn.style.color = 'var(--text-muted)';
        btn.classList.remove('active');
    });

    const targetTab = document.getElementById(tabId);
    if (targetTab) {
        targetTab.style.display = 'block';
    }
    
    evt.currentTarget.style.borderBottom = '3px solid var(--primary-light)';
    evt.currentTarget.style.color = 'var(--primary-light)';
    evt.currentTarget.classList.add('active');
}

function openMonitoringDetailModal(d) {
    if (!d) return;
    let raw = d.getAttribute ? d.getAttribute('data-detail') : d;
    if (!raw) return;
    
    let dataObj = null;
    try {
        if (typeof raw === 'string') {
            if (raw.startsWith('{') || raw.startsWith('[')) {
                dataObj = JSON.parse(raw);
            } else {
                dataObj = JSON.parse(decodeURIComponent(escape(atob(raw))));
            }
        } else {
            dataObj = raw;
        }
    } catch(e) {
        try {
            dataObj = JSON.parse(atob(raw));
        } catch(e2) {
            console.error("Error parsing detail json:", e2);
            return;
        }
    }
    
    if (!dataObj) return;

    const setT = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };
    const setH = (id, val) => { const el = document.getElementById(id); if (el) el.innerHTML = val; };
    const setS = (id, p, val) => { const el = document.getElementById(id); if (el) el.style[p] = val; };

    setT('mon_karyawan', dataObj.nama_karyawan || '-');
    setT('mon_tanggal', dataObj.tanggal_kerja || '-');
    setT('mon_aktivitas', dataObj.aktivitas || '-');
    setT('mon_target', (dataObj.target_jumlah || '0') + ' ' + (dataObj.unit || ''));
    setT('mon_realisasi', (dataObj.jumlah_realisasi || '0') + ' ' + (dataObj.unit || ''));
    setT('mon_catatan_karyawan', dataObj.catatan_karyawan || 'Tidak ada catatan karyawan.');
    setT('mon_mandor', dataObj.nama_mandor || '-');

    const fotoImg = document.getElementById('mon_foto_bukti');
    if (fotoImg) {
        if (dataObj.foto_bukti) {
            fotoImg.src = dataObj.foto_bukti;
            fotoImg.style.display = 'block';
            fotoImg.onclick = () => window.open(dataObj.foto_bukti, '_blank');
        } else {
            fotoImg.style.display = 'none';
        }
    }

    let statusBg = '#0d6efd1a', statusColor = '#0d6efd';
    if (dataObj.status === 'approved') { statusBg = '#2e7d321a'; statusColor = '#2e7d32'; }
    else if (dataObj.status === 'rejected' || dataObj.status === 'pending_manajer_tolak') { statusBg = '#c628281a'; statusColor = '#c62828'; }
    else if (dataObj.status === 'pending_mandor') { statusBg = '#e0a8001a'; statusColor = '#e0a800'; }

    setH('mon_status_badge', `<span class="badge" style="background: ${statusBg}; color: ${statusColor}; padding: 4px 10px; border-radius: 6px; font-weight: 700;">${dataObj.status_label || dataObj.status}</span>`);

    setT('mon_catatan_mandor', dataObj.catatan_mandor || 'Belum ada catatan mandor.');
    setT('mon_waktu_mandor', dataObj.waktu_mandor || '-');

    setT('mon_catatan_manajer', dataObj.catatan_manajer || 'Belum ada catatan manajer.');
    setT('mon_waktu_manajer', dataObj.waktu_manajer || '-');

    const bonusBox = document.getElementById('mon_bonus_box');
    const bonusNum = Number(dataObj.bonus_diterima || 0);
    const targetNum = Number(dataObj.target_jumlah || 0);
    const realisasiNum = Number(dataObj.jumlah_realisasi || 0);
    const unitStr = dataObj.unit || '';

    if (bonusBox) {
        if (bonusNum > 0) {
            bonusBox.style.background = '#f0fdf4';
            bonusBox.style.borderColor = '#bbf7d0';
            setT('mon_bonus_val', '+Rp ' + bonusNum.toLocaleString('id-ID'));
            setS('mon_bonus_val', 'color', '#2e7d32');
            setT('mon_bonus_badge', 'Bonus Kinerja');
            setS('mon_bonus_badge', 'background', '#dcfce7');
            setS('mon_bonus_badge', 'color', '#15803d');
            const surplus = (realisasiNum - targetNum).toFixed(2);
            setH('mon_bonus_breakdown', `
                <div><strong>Ketentuan:</strong> Realisasi (${realisasiNum} ${unitStr}) melampaui Target Dasar (${targetNum} ${unitStr}).</div>
                <div><strong>Surplus Hasil:</strong> +${surplus} ${unitStr} (Surplus Produktif).</div>
                <div><strong>Skema Insentif:</strong> Nominal bonus dihitung dari kelebihan hasil kerja fisik.</div>
            `);
        } else if (bonusNum < 0 || dataObj.status === 'rejected' || dataObj.status === 'pending_manajer_tolak') {
            bonusBox.style.background = '#fef2f2';
            bonusBox.style.borderColor = '#fecaca';
            setT('mon_bonus_val', '-Rp ' + Math.abs(bonusNum).toLocaleString('id-ID'));
            setS('mon_bonus_val', 'color', '#c62828');
            setT('mon_bonus_badge', 'Sanksi Denda 10%');
            setS('mon_bonus_badge', 'background', '#fee2e2');
            setS('mon_bonus_badge', 'color', '#b91c1c');
            setH('mon_bonus_breakdown', `
                <div><strong>Ketentuan:</strong> Terdeteksi pelanggaran / manipulasi data laporan kerja.</div>
                <div><strong>Dampak Capaian:</strong> Hasil realisasi dianulir menjadi <strong>0 ${unitStr} (0%)</strong>.</div>
                <div><strong>Denda Penalti:</strong> Pemotongan otomatis 10% sebesar <strong>-Rp ${Math.abs(bonusNum).toLocaleString('id-ID')}</strong> dari akumulasi insentif.</div>
            `);
        } else {
            bonusBox.style.background = '#f8fafc';
            bonusBox.style.borderColor = '#e2e8f0';
            setT('mon_bonus_val', 'Rp 0 (Target Terpenuhi)');
            setS('mon_bonus_val', 'color', '#64748b');
            setT('mon_bonus_badge', 'Standar');
            setS('mon_bonus_badge', 'background', '#f1f5f9');
            setS('mon_bonus_badge', 'color', '#475569');
            setH('mon_bonus_breakdown', `
                <div><strong>Ketentuan:</strong> Realisasi (${realisasiNum} ${unitStr}) sesuai dengan Target Dasar (${targetNum} ${unitStr}).</div>
                <div><strong>Keterangan:</strong> Pekerjaan tuntas 100% tanpa kelebihan bonus surplus atau denda sanksi.</div>
            `);
        }
    }

    const modal = document.getElementById('monitoringDetailModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

function closeMonitoringDetailModal() {
    const modal = document.getElementById('monitoringDetailModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openReportDetailModal(d) {
    openMonitoringDetailModal(d);
}
function closeReportDetailModal() {
    closeMonitoringDetailModal();
}
</script>

<!-- Modal Detail Verifikasi & Alur Monitoring -->
<div id="monitoringDetailModal" class="modal no-print" style="display:none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.7); overflow-y: auto; backdrop-filter: blur(4px);">
    <div class="modal-dialog" style="background: #ffffff; margin: 30px auto; max-width: 900px; border-radius: 14px; padding: 0; box-shadow: 0 20px 40px rgba(0,0,0,0.3); overflow: hidden; color: #1e293b;">
        <!-- Header Modal -->
        <div style="background: #f8fafc; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-list-check" style="color: var(--primary-light);"></i> Rincian Detail Alur Verifikasi Laporan Kerja
                </h3>
                <p style="margin: 3px 0 0 0; font-size: 0.8rem; color: #64748b;">
                    Melacak status input laporan karyawan, verifikasi mandor, hingga persetujuan manajer
                </p>
            </div>
            <button onclick="closeMonitoringDetailModal()" style="background: transparent; border: none; font-size: 1.6rem; cursor: pointer; color: #64748b; line-height: 1;">&times;</button>
        </div>

        <!-- Body Modal (3 Step Panels) -->
        <div style="padding: 24px; max-height: 80vh; overflow-y: auto;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Panel Kiri: Input Laporan Karyawan & Foto Bukti -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
                    <h4 style="margin: 0 0 14px 0; font-size: 0.95rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-user-pen" style="color: #0d6efd;"></i> 1. Hasil Input Laporan Karyawan
                    </h4>
                    
                    <div style="margin-bottom: 10px;">
                        <span style="font-size: 0.75rem; color: #64748b; display: block;">Pelaksana / Karyawan:</span>
                        <strong id="mon_karyawan" style="font-size: 0.95rem; color: #0f172a;">-</strong>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                        <div>
                            <span style="font-size: 0.75rem; color: #64748b; display: block;">Tanggal Kerja:</span>
                            <strong id="mon_tanggal" style="font-size: 0.88rem; color: #334155;">-</strong>
                        </div>
                        <div>
                            <span style="font-size: 0.75rem; color: #64748b; display: block;">Aktivitas:</span>
                            <strong id="mon_aktivitas" style="font-size: 0.88rem; color: #334155;">-</strong>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                        <div>
                            <span style="font-size: 0.75rem; color: #64748b; display: block;">Target Dasar:</span>
                            <span id="mon_target" style="font-weight: 700; color: #15803d; font-size: 0.9rem;">-</span>
                        </div>
                        <div>
                            <span style="font-size: 0.75rem; color: #64748b; display: block;">Realisasi Lapangan:</span>
                            <span id="mon_realisasi" style="font-weight: 700; color: #2e7d32; font-size: 0.9rem;">-</span>
                        </div>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <span style="font-size: 0.75rem; color: #64748b; display: block;">Catatan / Keterangan Karyawan:</span>
                        <div id="mon_catatan_karyawan" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 0.82rem; color: #334155; margin-top: 4px; font-style: italic;">
                            -
                        </div>
                    </div>

                    <div>
                        <span style="font-size: 0.75rem; color: #64748b; display: block; margin-bottom: 4px;">Foto Bukti Fisik Lapangan:</span>
                        <div id="mon_foto_wrapper">
                            <img id="mon_foto_bukti" src="" alt="Bukti Fisik" style="width: 100%; height: 170px; object-fit: cover; border-radius: 8px; border: 1px solid #cbd5e1; cursor: pointer;">
                        </div>
                    </div>
                </div>

                <!-- Panel Kanan: Verifikasi Mandor & Persetujuan Manajer -->
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- Section Verifikasi Mandor -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
                        <h4 style="margin: 0 0 10px 0; font-size: 0.95rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-user-check" style="color: #0d6efd;"></i> 2. Verifikasi Mandor Lapangan
                        </h4>
                        <div style="margin-bottom: 6px;">
                            <span style="font-size: 0.75rem; color: #64748b;">Mandor Penanggung Jawab:</span>
                            <strong id="mon_mandor" style="font-size: 0.88rem; display: block; color: #0f172a;">-</strong>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <span style="font-size: 0.75rem; color: #64748b;">Catatan Verifikasi Mandor:</span>
                            <div id="mon_catatan_mandor" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 0.82rem; color: #334155; margin-top: 3px; font-style: italic;">
                                -
                            </div>
                        </div>
                        <div style="font-size: 0.76rem; color: #64748b;">
                            <i class="fa-regular fa-clock"></i> Waktu Verifikasi Mandor: <span id="mon_waktu_mandor" style="font-weight: 600; color: #334155;">-</span>
                        </div>
                    </div>

                    <!-- Section Persetujuan Manajer -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
                        <h4 style="margin: 0 0 10px 0; font-size: 0.95rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-user-shield" style="color: #15803d;"></i> 3. Persetujuan Final Manajer
                        </h4>
                        <div style="margin-bottom: 6px;">
                            <span style="font-size: 0.75rem; color: #64748b;">Status Alur Verifikasi:</span>
                            <div id="mon_status_badge" style="margin-top: 2px;">-</div>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <span style="font-size: 0.75rem; color: #64748b;">Catatan Persetujuan Manajer:</span>
                            <div id="mon_catatan_manajer" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 0.82rem; color: #334155; margin-top: 3px;">
                                -
                            </div>
                        </div>
                        <div style="font-size: 0.76rem; color: #64748b;">
                            <i class="fa-regular fa-clock"></i> Waktu Persetujuan Manajer: <span id="mon_waktu_manajer" style="font-weight: 600; color: #334155;">-</span>
                        </div>
                    </div>

                    <!-- Box Summary Bonus / Denda -->
                    <div id="mon_bonus_box" style="padding: 12px 14px; background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 8px; margin-top: auto;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.73rem; color: #166534; font-weight: 700; text-transform: uppercase;">
                                <i class="fa-solid fa-calculator"></i> Status Bonus / Denda:
                            </span>
                            <span id="mon_bonus_badge" class="badge" style="font-size: 0.7rem;">-</span>
                        </div>
                        <div id="mon_bonus_val" style="font-size: 1.15rem; font-weight: 700; color: #2e7d32; margin-top: 3px;">-</div>
                        <div id="mon_bonus_breakdown" style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed rgba(0,0,0,0.12); font-size: 0.78rem; color: #334155; line-height: 1.4;">
                            -
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Modal -->
        <div style="background: #f8fafc; padding: 14px 24px; border-top: 1px solid #e2e8f0; text-align: right;">
            <button onclick="closeMonitoringDetailModal()" class="btn btn-secondary btn-sm" style="padding: 7px 22px; font-weight: 600; border-radius: 6px;">Tutup</button>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
