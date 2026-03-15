<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['uzivatel_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

try {
    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("DELETE FROM oznameni WHERE id = ? AND prijemce_id = ?");
        $stmt->execute([$id, $uzivatel_id]);
    } 
    elseif ($action === 'read_all') {
        $stmt = $pdo->prepare("UPDATE oznameni SET precteno = 1 WHERE prijemce_id = ?");
        $stmt->execute([$uzivatel_id]);
    } 
    elseif ($action === 'delete_all') {
        $stmt = $pdo->prepare("DELETE FROM oznameni WHERE prijemce_id = ?");
        $stmt->execute([$uzivatel_id]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
