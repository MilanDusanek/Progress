<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['uzivatel_id'])) {
    $prispevek_id = filter_input(INPUT_POST, 'prispevek_id', FILTER_VALIDATE_INT);
    $obsah = trim($_POST['obsah']);
    $odesilatel_id = $_SESSION['uzivatel_id'];

    if ($prispevek_id && !empty($obsah)) {
        $stmt = $pdo->prepare("INSERT INTO komentare (prispevek_id, uzivatel_id, obsah) VALUES (?, ?, ?)");
        $stmt->execute([$prispevek_id, $odesilatel_id, $obsah]);

        $stmt_owner = $pdo->prepare("SELECT uzivatel_id FROM prispevky WHERE id = ?");
        $stmt_owner->execute([$prispevek_id]);
        $autor_prispevku = $stmt_owner->fetchColumn();

        if ($autor_prispevku && $autor_prispevku != $odesilatel_id) {
            $stmt_notif = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ, prispevek_id) VALUES (?, ?, 'komentar', ?)");
            $stmt_notif->execute([$autor_prispevku, $odesilatel_id, $prispevek_id]);
        }

        header("Location: komunita.php?status=comment_ok");
        exit;
    }
}

header("Location: komunita.php");
exit;