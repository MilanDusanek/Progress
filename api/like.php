<?php
session_start();
require 'db.php';

if (!isset($_SESSION['uzivatel_id']) || !isset($_GET['id'])) {
    header("Location: komunita.php");
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];
$prispevek_id = (int)$_GET['id'];

// 1. Zjistíme, jestli uživatel už lajk dal
$check = $pdo->prepare("SELECT id FROM lajky WHERE uzivatel_id = ? AND prispevek_id = ?");
$check->execute([$uzivatel_id, $prispevek_id]);
$existujici_lajk = $check->fetch();

if ($existujici_lajk) {
    // Už lajknuto -> Smazat lajk (Unlike)
    $del = $pdo->prepare("DELETE FROM lajky WHERE uzivatel_id = ? AND prispevek_id = ?");
    $del->execute([$uzivatel_id, $prispevek_id]);
} else {
    // Ještě nelajknuto -> Přidat lajk
    $add = $pdo->prepare("INSERT INTO lajky (uzivatel_id, prispevek_id) VALUES (?, ?)");
    $add->execute([$uzivatel_id, $prispevek_id]);

    // 2. OZNÁMENÍ: Zjistíme majitele příspěvku
    $stmt_owner = $pdo->prepare("SELECT uzivatel_id FROM prispevky WHERE id = ?");
    $stmt_owner->execute([$prispevek_id]);
    $autor_id = $stmt_owner->fetchColumn();

    // Pošleme notifikaci (pokud to není můj vlastní příspěvek)
    if ($autor_id && $autor_id != $uzivatel_id) {
        $notif = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ, prispevek_id) VALUES (?, ?, 'lajk', ?)");
        $notif->execute([$autor_id, $uzivatel_id, $prispevek_id]);
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;