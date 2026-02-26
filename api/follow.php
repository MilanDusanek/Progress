<?php
session_start();
require 'db.php';

if (!isset($_SESSION['uzivatel_id']) || !isset($_GET['id'])) {
    header("Location: komunita.php");
    exit;
}

$ja = $_SESSION['uzivatel_id'];
$on = (int)$_GET['id'];

if ($ja !== $on) {
    // Zjistíme, jestli už ho sleduju
    $check = $pdo->prepare("SELECT id FROM sledujici WHERE sledujici_id = ? AND sledovany_id = ?");
    $check->execute([$ja, $on]);
    
    if ($check->fetch()) {
        // Už sleduju -> zrušit (Unfollow)
        $del = $pdo->prepare("DELETE FROM sledujici WHERE sledujici_id = ? AND sledovany_id = ?");
        $del->execute([$ja, $on]);
    } else {
$add = $pdo->prepare("INSERT INTO sledujici (sledujici_id, sledovany_id) VALUES (?, ?)");
    $add->execute([$ja, $on]);

    // OZNÁMENÍ: X tě začal sledovat
    $stmt_notif = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ) VALUES (?, ?, 'follow')");
    $stmt_notif->execute([$on, $ja]);
    }
}

// Vrátíme se zpět tam, odkud jsme přišli
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;