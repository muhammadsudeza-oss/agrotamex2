<?php
// index.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];

if ($role === 'manajer') {
    header("Location: manajer/index.php");
    exit;
} elseif ($role === 'mandor') {
    header("Location: mandor/index.php");
    exit;
} elseif ($role === 'karyawan') {
    header("Location: karyawan/index.php");
    exit;
} else {
    // Unknown role? Logout.
    header("Location: logout.php");
    exit;
}
?>
