<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['uzivatel_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];
$notif_id = $_POST['notif_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$notif_id || !$action) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT souvisejici_id, odesilatel_id FROM oznameni WHERE id = ? AND prijemce_id = ? AND typ = 'vyzva'");
    $stmt->execute([$notif_id, $uzivatel_id]);
    $notif = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notif) {
        echo json_encode(['success' => false, 'error' => 'Notification not found or not a challenge']);
        exit;
    }

    $duel_id = $notif['souvisejici_id'];
    $vyzyvatel_id = $notif['odesilatel_id'];

    if ($action === 'accept') {
        $stmt_duel = $pdo->prepare("UPDATE challenge_souboje SET status = 'active' WHERE id = ? AND souper_id = ?");
        $stmt_duel->execute([$duel_id, $uzivatel_id]);
        
        $stmt_del = $pdo->prepare("DELETE FROM oznameni WHERE id = ?");
        $stmt_del->execute([$notif_id]);

        $stmt_notif = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ, souvisejici_id) VALUES (?, ?, 'duel_accept', ?)");
        $stmt_notif->execute([$vyzyvatel_id, $uzivatel_id, $duel_id]);

        $stmt_duel_info = $pdo->prepare("SELECT challenge_id, cil_hodnota FROM challenge_souboje WHERE id = ?");
        $stmt_duel_info->execute([$duel_id]);
        $d_info = $stmt_duel_info->fetch(PDO::FETCH_ASSOC);

        if ($d_info) {
            $ch_id = $d_info['challenge_id'];
            $cil = $d_info['cil_hodnota'];

            $stmt_ins = $pdo->prepare("INSERT IGNORE INTO uzivatele_challenge (uzivatel_id, challenge_id, cil_hodnota) VALUES (?, ?, ?)");
            $stmt_ins->execute([$vyzyvatel_id, $ch_id, $cil]);
            $stmt_ins->execute([$uzivatel_id, $ch_id, $cil]);

            $stmt_upd_cil = $pdo->prepare("UPDATE uzivatele_challenge SET cil_hodnota = ? WHERE challenge_id = ? AND (uzivatel_id = ? OR uzivatel_id = ?)");
            $stmt_upd_cil->execute([$cil, $ch_id, $vyzyvatel_id, $uzivatel_id]);

            kontrolujDokonceniDuely($pdo, $uzivatel_id, $ch_id);
            kontrolujDokonceniDuely($pdo, $vyzyvatel_id, $ch_id);
        }

    } elseif ($action === 'decline') {
        $stmt_duel = $pdo->prepare("UPDATE challenge_souboje SET status = 'declined' WHERE id = ? AND souper_id = ?");
        $stmt_duel->execute([$duel_id, $uzivatel_id]);

        $stmt_del = $pdo->prepare("DELETE FROM oznameni WHERE id = ?");
        $stmt_del->execute([$notif_id]);

        $stmt_notif = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ, souvisejici_id) VALUES (?, ?, 'duel_decline', ?)");
        $stmt_notif->execute([$vyzyvatel_id, $uzivatel_id, $duel_id]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
