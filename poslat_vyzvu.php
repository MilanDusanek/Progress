<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['uzivatel_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nepřihlášen']);
    exit;
}

$vyzyvatel_id = $_SESSION['uzivatel_id'];

// Kontrola profilu
$stmt_p = $pdo->prepare("SELECT 1 FROM profily WHERE uzivatel_id = ? LIMIT 1");
$stmt_p->execute([$vyzyvatel_id]);
if (!$stmt_p->fetch()) {
    echo json_encode(['success' => false, 'error' => 'incomplete_profile']);
    exit;
}

$souper_id = $_POST['souper_id'] ?? null;
$challenge_id = $_POST['challenge_id'] ?? 1;
$cil_hodnota = $_POST['cil_hodnota'] ?? 0;

if (!$souper_id) {
    echo json_encode(['success' => false, 'error' => 'Chybí soupeř']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO challenge_souboje (vyzyvatel_id, souper_id, challenge_id, cil_hodnota, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$vyzyvatel_id, $souper_id, $challenge_id, $cil_hodnota]);

    $stmt_notif = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ, souvisejici_id) VALUES (?, ?, 'vyzva', ?)");
    $stmt_notif->execute([$souper_id, $vyzyvatel_id, $pdo->lastInsertId()]);

    echo json_encode(['success' => true]);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
