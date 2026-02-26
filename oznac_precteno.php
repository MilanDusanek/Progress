<?php
session_start();
require 'db.php';

if (isset($_SESSION['uzivatel_id'])) {
    $moje_id = $_SESSION['uzivatel_id'];
    // Označí jako přečtené vše, co NENÍ výzva
    $stmt = $pdo->prepare("UPDATE oznameni SET precteno = true WHERE prijemce_id = ? AND typ != 'vyzva'");
    $stmt->execute([$moje_id]);
}
?>