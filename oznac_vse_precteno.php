<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['uzivatel_id'])) {
    echo json_encode(['success' => false, 'message' => 'Uživatel není přihlášen']);
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];

try {
    
    $stmt = $pdo->prepare("UPDATE oznameni SET precteno = 1 WHERE prijemce_id = ? AND precteno = 0");
    $stmt->execute([$uzivatel_id]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
