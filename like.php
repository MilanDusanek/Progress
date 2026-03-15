<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['uzivatel_id']) || !isset($_GET['id'])) {
    header("Location: komunita.php");
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];
$prispevek_id = $_GET['id'];

try {
    
    $stmt_author = $pdo->prepare("SELECT uzivatel_id FROM prispevky WHERE id = ?");
    $stmt_author->execute([$prispevek_id]);
    $autor_id = $stmt_author->fetchColumn();

    if ($autor_id) {
        // Kontrola, zda má uživatel dokončený profil (pokud ne, lajk nepovolíme)
        $stmt_check_profile = $pdo->prepare("SELECT 1 FROM profily WHERE uzivatel_id = ? LIMIT 1");
        $stmt_check_profile->execute([$uzivatel_id]);
        if (!$stmt_check_profile->fetch()) {
             if (isset($_GET['ajax'])) {
                echo json_encode(['success' => false, 'message' => 'incomplete_profile']);
                exit;
            }
            header("Location: komunita.php?status=incomplete_profile");
            exit;
        }

        $stmt_check = $pdo->prepare("SELECT 1 FROM lajky WHERE uzivatel_id = ? AND prispevek_id = ?");
        $stmt_check->execute([$uzivatel_id, $prispevek_id]);

        $lajknuto_mnou = false;
        if ($stmt_check->fetch()) {
            $stmt_del = $pdo->prepare("DELETE FROM lajky WHERE uzivatel_id = ? AND prispevek_id = ?");
            $stmt_del->execute([$uzivatel_id, $prispevek_id]);
            $lajknuto_mnou = false;
        } else {
            $stmt_ins = $pdo->prepare("INSERT INTO lajky (uzivatel_id, prispevek_id) VALUES (?, ?)");
            $stmt_ins->execute([$uzivatel_id, $prispevek_id]);
            $lajknuto_mnou = true;

            if ($autor_id != $uzivatel_id) {
                $stmt_notif = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ, souvisejici_id) VALUES (?, ?, 'lajk', ?)");
                $stmt_notif->execute([$autor_id, $uzivatel_id, $prispevek_id]);
            }
        }

        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM lajky WHERE prispevek_id = ?");
        $stmt_count->execute([$prispevek_id]);
        $novy_pocet = $stmt_count->fetchColumn();

        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'novy_pocet' => (int)$novy_pocet,
                'lajknuto_mnou' => $lajknuto_mnou
            ]);
            exit;
        }
    }
} catch (Exception $e) {
    if (isset($_GET['ajax'])) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    header("Location: komunita.php?status=error&msg=" . urlencode($e->getMessage()));
    exit;
}

header("Location: komunita.php");
exit;