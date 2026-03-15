<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['uzivatel_id'])) {
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];



if (isset($_GET['all']) && $_GET['all'] == '1') {
    try {
        $stmt = $pdo->prepare("UPDATE oznameni SET precteno = 1 WHERE prijemce_id = ? AND typ != 'vyzva'");
        $stmt->execute([$uzivatel_id]);
    } catch (Exception $e) { }
}

