<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['uzivatel_id']) || !isset($_POST['challenge_id'])) {
    header("Location: vyzvy.php");
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];
$challenge_id = $_POST['challenge_id'];
$cil_hodnota = isset($_POST['cil_hodnota']) ? intval($_POST['cil_hodnota']) : 100;


if ($cil_hodnota <= 0) $cil_hodnota = 100;

try {
    
    $stmt_check = $pdo->prepare("SELECT 1 FROM uzivatele_challenge WHERE uzivatel_id = ? AND challenge_id = ?");
    $stmt_check->execute([$uzivatel_id, $challenge_id]);

    if (!$stmt_check->fetch()) {
        
        $stmt = $pdo->prepare("INSERT INTO uzivatele_challenge (uzivatel_id, challenge_id, aktualni_hodnota, cil_hodnota, je_solo) VALUES (?, ?, 0, ?, 1)");
        $stmt->execute([$uzivatel_id, $challenge_id, $cil_hodnota]);
    } else {
        
        
        $stmt = $pdo->prepare("UPDATE uzivatele_challenge SET cil_hodnota = ?, aktualni_hodnota = 0, je_solo = 1 WHERE uzivatel_id = ? AND challenge_id = ?");
        $stmt->execute([$cil_hodnota, $uzivatel_id, $challenge_id]);
    }
    
    
    header("Location: vyzvy.php?success=1");
} catch (Exception $e) {
    header("Location: vyzvy.php?error=" . urlencode($e->getMessage()));
}
exit;
