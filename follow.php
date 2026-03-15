<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['uzivatel_id']) || !isset($_GET['id'])) {
    header("Location: komunita.php");
    exit;
}

$moje_id = $_SESSION['uzivatel_id'];
$sledovany_id = $_GET['id'];

if ($moje_id == $sledovany_id) {
    header("Location: komunita.php");
    exit;
}

try {
    
    $stmt_check = $pdo->prepare("SELECT 1 FROM sledujici WHERE sledujici_id = ? AND sledovany_id = ?");
    $stmt_check->execute([$moje_id, $sledovany_id]);

    if ($stmt_check->fetch()) {
        
        $stmt_del = $pdo->prepare("DELETE FROM sledujici WHERE sledujici_id = ? AND sledovany_id = ?");
        $stmt_del->execute([$moje_id, $sledovany_id]);
    } else {
        
        $stmt_ins = $pdo->prepare("INSERT INTO sledujici (sledujici_id, sledovany_id) VALUES (?, ?)");
        $stmt_ins->execute([$moje_id, $sledovany_id]);

        
        $stmt_notif = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ) VALUES (?, ?, 'follow')");
        $stmt_notif->execute([$sledovany_id, $moje_id]);
    }
} catch (Exception $e) {
    header("Location: komunita.php?status=error&msg=" . urlencode($e->getMessage()));
    exit;
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;