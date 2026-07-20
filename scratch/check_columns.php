<?php
require_once '../config/koneksi.php';

echo "=== ASSIGNMENTS ===\n";
$q = $pdo->query("DESCRIBE assignments");
while ($r = $q->fetch()) {
    echo $r['Field'] . " - " . $r['Type'] . "\n";
}

echo "\n=== WORK_REPORTS ===\n";
$q = $pdo->query("DESCRIBE work_reports");
while ($r = $q->fetch()) {
    echo $r['Field'] . " - " . $r['Type'] . "\n";
}
