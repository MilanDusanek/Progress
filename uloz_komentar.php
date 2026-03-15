<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['uzivatel_id']) || !isset($_POST['prispevek_id'])) {
    header("Location: komunita.php");
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];

// Kontrola profilu
$stmt_p = $pdo->prepare("SELECT 1 FROM profily WHERE uzivatel_id = ? LIMIT 1");
$stmt_p->execute([$uzivatel_id]);
if (!$stmt_p->fetch()) {
    header("Location: komunita.php?status=incomplete_profile");
    exit;
}

$prispevek_id = $_POST['prispevek_id'];
$obsah = trim($_POST['obsah']);

if (empty($obsah)) {
    header("Location: komunita.php");
    exit;
}

try {
    
    $stmt = $pdo->prepare("INSERT INTO komentare (uzivatel_id, prispevek_id, obsah) VALUES (?, ?, ?)");
    $stmt->execute([$uzivatel_id, $prispevek_id, $obsah]);

    
    $stmt_author = $pdo->prepare("SELECT uzivatel_id FROM prispevky WHERE id = ?");
    $stmt_author->execute([$prispevek_id]);
    $autor_id = $stmt_author->fetchColumn();

    if ($autor_id && $autor_id != $uzivatel_id) {
        $stmt_notif = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ, souvisejici_id) VALUES (?, ?, 'komentar', ?)");
        $stmt_notif->execute([$autor_id, $uzivatel_id, $prispevek_id]);
    }
} catch (Exception $e) { }

header("Location: komunita.php?status=comment_ok");
exit;