<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['uzivatel_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Uživatel není přihlášen']);
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];

try {
    
    $stmt = $pdo->prepare("DELETE FROM oznameni WHERE prijemce_id = ?");
    $stmt->execute([$uzivatel_id]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
