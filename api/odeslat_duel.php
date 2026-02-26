<?php
session_start();
require 'db.php';

if (!isset($_SESSION['uzivatel_id']) || !isset($_POST['souper_id']) || !isset($_POST['challenge_id'])) {
    header("Location: odznaky.php?error=missing_data");
    exit;
}

$vyzyvatel_id = $_SESSION['uzivatel_id'];
$souper_id = (int)$_POST['souper_id'];
$challenge_id = (int)$_POST['challenge_id'];

if ($vyzyvatel_id === $souper_id) {
    header("Location: odznaky.php?error=self_challenge");
    exit;
}

try {
    // 1. Zápis do tabulky soubojů (status pending)
    $stmt = $pdo->prepare("INSERT INTO public.challenge_souboje (vyzyvatel_id, souper_id, challenge_id, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$vyzyvatel_id, $souper_id, $challenge_id]);

    // 2. Zjistíme název úkolu pro notifikaci
    $stmt_task = $pdo->prepare("SELECT nazev FROM public.challenge WHERE id = ?");
    $stmt_task->execute([$challenge_id]);
    $task_name = $stmt_task->fetchColumn();

    $zprava = "tě vyzval na souboj v úkolu: " . $task_name;

    // 3. Pošleme notifikaci soupeři
    $stmt_notif = $pdo->prepare("INSERT INTO public.oznameni (prijemce_id, odesilatel_id, typ, zprava) VALUES (?, ?, 'vyzva', ?)");
    $stmt_notif->execute([$souper_id, $vyzyvatel_id, $zprava]);

    header("Location: odznaky.php?status=challenge_sent");
    exit;
} catch (PDOException $e) {
    header("Location: odznaky.php?error=db_error");
    exit;
}