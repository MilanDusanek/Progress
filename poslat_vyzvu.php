<?php
session_start();
require 'db.php';

if (!isset($_SESSION['uzivatel_id']) || !isset($_GET['souper_id'])) {
    header("Location: komunita.php");
    exit;
}

$vyzyvatel_id = $_SESSION['uzivatel_id'];
$souper_id = (int)$_GET['souper_id'];

if ($vyzyvatel_id === $souper_id) {
    header("Location: komunita.php?error=self_challenge");
    exit;
}

$stmt = $pdo->prepare("INSERT INTO challenge_souboje (vyzyvatel_id, souper_id, status) VALUES (?, ?, 'pending')");
$stmt->execute([$vyzyvatel_id, $souper_id]);

// 2. Pošleme notifikaci do tvého systému, co už máš hotový
$stmt_notif = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ) VALUES (?, ?, 'vyzva')");
$stmt_notif->execute([$souper_id, $vyzyvatel_id]);

header("Location: komunita.php?status=challenge_sent");
exit;