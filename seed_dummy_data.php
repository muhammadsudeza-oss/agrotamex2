<?php
/**
 * seed_dummy_data.php
 * ---------------------------------------------------------------
 * Menambahkan data dummy yang jauh lebih banyak & realistis supaya
 * Laporan Monitoring dan Laporan Produktivitas di halaman manajer
 * benar-benar terisi (ranking, tren bulanan, distribusi status,
 * termasuk status 'rejected' & 'pending_manajer_tolak' yang selama
 * ini tidak pernah ada datanya sama sekali).
 *
 * CARA PAKAI:
 *   1. Taruh file ini di root folder project (sejajar dengan index.php)
 *   2. Jalankan lewat browser:  http://localhost/agrotamex1/seed_dummy_data.php?confirm=yes
 *      ATAU lewat CLI:          php seed_dummy_data.php
 *   3. Tambahkan &reset=1 kalau mau HAPUS DULU semua assignments &
 *      work_reports lama sebelum diisi ulang (data karyawan/mandor/
 *      manajer/login TIDAK dihapus).
 *
 * AMAN dijalankan berkali-kali TANPA ?reset=1 (hanya menambah data baru).
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/config/koneksi.php';

$is_cli = (php_sapi_name() === 'cli');
$confirmed = $is_cli || (isset($_GET['confirm']) && $_GET['confirm'] === 'yes');
$do_reset  = $is_cli ? in_array('reset', $argv ?? []) : (isset($_GET['reset']) && $_GET['reset'] === '1');

function out($msg) {
    global $is_cli;
    echo $is_cli ? ($msg . "\n") : ($msg . "<br>\n");
}

if (!$confirmed) {
    out("Untuk menjalankan seed data, buka URL ini dengan tambahan ?confirm=yes");
    out("Contoh: seed_dummy_data.php?confirm=yes  (tambahkan &reset=1 untuk hapus data lama dulu)");
    exit;
}

try {
    $pdo->beginTransaction();

    // -----------------------------------------------------------
    // 0. (Opsional) Reset data transaksi lama - TIDAK menghapus akun login
    // -----------------------------------------------------------
    if ($do_reset) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE work_reports");
        $pdo->exec("TRUNCATE TABLE assignments");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        out("Data assignments & work_reports lama sudah dikosongkan.");
    }

    // -----------------------------------------------------------
    // 1. Pastikan ada cukup variasi karyawan (tambah kalau masih sedikit)
    // -----------------------------------------------------------
    $extra_karyawan = [
        ['karyawan6', 'Joko S.'],
        ['karyawan7', 'Yanto W.'],
        ['karyawan8', 'Dedi K.'],
    ];
    $hash = password_hash('password123', PASSWORD_BCRYPT);
    $stmt_check = $pdo->prepare("SELECT id_karyawan FROM karyawan WHERE username = ?");
    $stmt_ins_k = $pdo->prepare("INSERT INTO karyawan (username, password, nama, status_aktif) VALUES (?, ?, ?, 1)");
    foreach ($extra_karyawan as [$uname, $nama]) {
        $stmt_check->execute([$uname]);
        if (!$stmt_check->fetch()) {
            $stmt_ins_k->execute([$uname, $hash, $nama]);
            out("Menambah karyawan baru: $nama");
        }
    }

    // Ambil ulang seluruh karyawan aktif & mandor
    $karyawan_list = $pdo->query("SELECT id_karyawan, nama FROM karyawan WHERE status_aktif = 1")->fetchAll();
    $mandor_list   = $pdo->query("SELECT id_mandor, nama, spesialisasi FROM mandor")->fetchAll();
    $manajer_id    = (int)$pdo->query("SELECT id_manajer FROM manajer ORDER BY id_manajer ASC LIMIT 1")->fetchColumn();

    if (empty($karyawan_list) || empty($mandor_list) || !$manajer_id) {
        throw new Exception("Data karyawan/mandor/manajer dasar belum ada. Import database.sql dulu.");
    }

    // Petakan mandor berdasarkan spesialisasi supaya aktivitas & mandor selalu konsisten
    $mandor_by_spec = [];
    foreach ($mandor_list as $m) {
        $mandor_by_spec[$m['spesialisasi']][] = $m;
    }

    // Konfigurasi aktivitas: [unit, target_min, target_max, rp_per_unit_lebih]
    $activities = [
        'Pemanenan'     => ['unit' => 'Ton',    'min' => 1.2, 'max' => 1.8, 'rate' => 50000],
        'Pemupukan'     => ['unit' => 'Karung', 'min' => 8,   'max' => 14,  'rate' => 5000],
        'Penyemprotan'  => ['unit' => 'Hektar', 'min' => 1.5, 'max' => 2.5, 'rate' => 100000],
    ];

    $stmt_ins_a = $pdo->prepare("
        INSERT INTO assignments (tanggal, aktivitas, target_jumlah, unit, id_mandor, id_karyawan, id_manajer, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_ins_r = $pdo->prepare("
        INSERT INTO work_reports
            (id_assignment, id_karyawan, jumlah_realisasi, catatan_karyawan, status,
             catatan_mandor, tanggal_verifikasi_mandor, catatan_manajer, tanggal_verifikasi_manajer,
             bonus_diterima, potongan_penalti, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $catatan_karyawan_pool = [
        'Pekerjaan selesai sesuai target harian.',
        'Cuaca mendukung, hasil kerja maksimal.',
        'Sedikit terkendala alat, tapi tetap selesai.',
        'Selesai lebih cepat dari perkiraan.',
        'Kondisi lahan agak sulit hari ini.',
    ];
    $catatan_mandor_ok_pool = [
        'Sudah dicek langsung di lapangan, sesuai laporan.',
        'Kualitas kerja bagus, lanjutkan.',
        'Hasil timbangan/ukuran cocok dengan laporan.',
    ];
    $catatan_mandor_reject_pool = [
        'Hasil di lapangan tidak sesuai dengan jumlah yang dilaporkan.',
        'Ditemukan selisih antara laporan dan kondisi aktual.',
    ];

    $total_assignment = 0;
    $total_report = 0;
    $status_tally = [];

    $days_back = 60;
    $today = new DateTime('today');

    for ($d = $days_back; $d >= 0; $d--) {
        $date = (clone $today)->modify("-$d days");
        // Lewati hari Minggu (libur)
        if ($date->format('N') == 7) continue;

        // 2-4 penugasan per hari, karyawan dipilih acak (tidak semua orang tiap hari)
        $jumlah_tugas_hari_ini = rand(2, 4);
        $karyawan_hari_ini = $karyawan_list;
        shuffle($karyawan_hari_ini);
        $karyawan_hari_ini = array_slice($karyawan_hari_ini, 0, $jumlah_tugas_hari_ini);

        foreach ($karyawan_hari_ini as $karyawan) {
            $aktivitas_name = array_rand($activities);
            $cfg = $activities[$aktivitas_name];

            if (empty($mandor_by_spec[$aktivitas_name])) continue;
            $mandor = $mandor_by_spec[$aktivitas_name][array_rand($mandor_by_spec[$aktivitas_name])];

            $target = round(mt_rand((int)($cfg['min'] * 100), (int)($cfg['max'] * 100)) / 100, 2);

            $created_at = $date->format('Y-m-d') . ' ' . sprintf('%02d:%02d:00', rand(7, 10), rand(0, 59));

            $stmt_ins_a->execute([
                $date->format('Y-m-d'),
                $aktivitas_name,
                $target,
                $cfg['unit'],
                $mandor['id_mandor'],
                $karyawan['id_karyawan'],
                $manajer_id,
                $created_at,
            ]);
            $assignment_id = (int)$pdo->lastInsertId();
            $total_assignment++;

            // --------------------------------------------------
            // Tentukan skenario laporan kerja untuk assignment ini
            // --------------------------------------------------
            $days_old = $d;
            $scenario_roll = mt_rand(1, 100);

            // Sekitar 6% assignment memang belum dilaporkan sama sekali
            // (karyawan lalai) - baris ini sengaja TIDAK diberi work_report.
            if ($scenario_roll <= 6) {
                continue;
            }

            // Realisasi: sebagian besar di sekitar/di atas target, sebagian kecil jauh di bawah target
            if ($scenario_roll <= 66) {
                // Capaian bagus (100%-135% dari target)
                $realisasi = round($target * (mt_rand(100, 135) / 100), 2);
            } elseif ($scenario_roll <= 88) {
                // Capaian standar/pas-pasan (80%-99%)
                $realisasi = round($target * (mt_rand(80, 99) / 100), 2);
            } else {
                // Capaian rendah / mencurigakan (40%-75%) - kandidat ditolak
                $realisasi = round($target * (mt_rand(40, 75) / 100), 2);
            }

            $catatan_karyawan = $catatan_karyawan_pool[array_rand($catatan_karyawan_pool)];

            // Tentukan status berdasarkan umur data: makin baru, makin mungkin masih "menggantung"
            if ($days_old <= 1 && mt_rand(1, 100) <= 55) {
                $status = 'pending_mandor';
            } elseif ($days_old <= 2 && mt_rand(1, 100) <= 35) {
                $status = 'verified_by_mandor';
            } else {
                // Data yang lebih lama sudah final: approved / rejected / pending_manajer_tolak (kasus sanksi)
                if ($scenario_roll > 88) {
                    // Realisasi rendah -> mayoritas ditolak, sebagian masih ditinjau manajer (kasus sanksi)
                    $status = (mt_rand(1, 100) <= 30) ? 'pending_manajer_tolak' : 'rejected';
                } else {
                    $status = 'approved';
                }
            }

            $tgl_verif_mandor = null;
            $tgl_verif_manajer = null;
            $catatan_mandor = null;
            $catatan_manajer = null;
            $bonus = 0.00;
            $potongan = 0.00;

            $base_ts = strtotime($created_at);

            if (in_array($status, ['verified_by_mandor', 'approved', 'rejected', 'pending_manajer_tolak'])) {
                $jam_respon_mandor = mt_rand(2, 30); // sebagian ada yang lambat (>24 jam) buat isi alarm keterlambatan
                $tgl_verif_mandor = date('Y-m-d H:i:s', $base_ts + ($jam_respon_mandor * 3600));
                $catatan_mandor = ($status === 'pending_manajer_tolak' || ($status === 'rejected' && $scenario_roll > 88))
                    ? $catatan_mandor_reject_pool[array_rand($catatan_mandor_reject_pool)]
                    : $catatan_mandor_ok_pool[array_rand($catatan_mandor_ok_pool)];
            }

            if ($status === 'approved') {
                $jam_respon_manajer = mt_rand(1, 20);
                $tgl_verif_manajer = date('Y-m-d H:i:s', strtotime($tgl_verif_mandor) + ($jam_respon_manajer * 3600));
                $catatan_manajer = 'Disetujui. Pertahankan performa kerja!';
                if ($realisasi > $target) {
                    $bonus = round(($realisasi - $target) * $cfg['rate'], 2);
                }
            } elseif ($status === 'rejected') {
                $jam_respon_manajer = mt_rand(1, 20);
                $tgl_verif_manajer = date('Y-m-d H:i:s', strtotime($tgl_verif_mandor) + ($jam_respon_manajer * 3600));
                // 30% dari yang ditolak adalah kasus sanksi terkonfirmasi (potongan 10% dari akumulasi bonus)
                if (mt_rand(1, 100) <= 30) {
                    $potongan = 10.00;
                    $bonus = -1 * round(mt_rand(20000, 150000), 2); // estimasi potongan denda akumulasi
                    $catatan_manajer = 'Kasus manipulasi dikonfirmasi. Denda potongan 10% diterapkan dari akumulasi bonus.';
                } else {
                    $catatan_manajer = 'Ditolak. Data tidak sesuai, silakan lapor ulang.';
                }
            } elseif ($status === 'pending_manajer_tolak') {
                // Masih menunggu keputusan manajer, belum ada catatan/tanggal manajer
                $catatan_manajer = null;
                $tgl_verif_manajer = null;
            }

            $stmt_ins_r->execute([
                $assignment_id,
                $karyawan['id_karyawan'],
                $realisasi,
                $catatan_karyawan,
                $status,
                $catatan_mandor,
                $tgl_verif_mandor,
                $catatan_manajer,
                $tgl_verif_manajer,
                $bonus,
                $potongan,
                $created_at,
            ]);
            $total_report++;
            $status_tally[$status] = ($status_tally[$status] ?? 0) + 1;
        }
    }

    $pdo->commit();

    out("=== SELESAI ===");
    out("Total assignment dibuat : $total_assignment");
    out("Total laporan kerja     : $total_report");
    foreach ($status_tally as $st => $count) {
        out("  - $st: $count");
    }
    out("Assignment tanpa laporan (karyawan lalai): " . ($total_assignment - $total_report));
    out("Silakan buka menu Laporan Monitoring / Laporan Produktivitas di halaman manajer.");

} catch (\Throwable $e) {
    $pdo->rollBack();
    out("GAGAL: " . $e->getMessage());
}
