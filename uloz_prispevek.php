<?php
session_start();
require 'db.php'; // Předpokládám, že zde máš připojení k PDO

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uzivatel_id = $_SESSION['uzivatel_id'];
    $obsah = trim($_POST['obsah']);
    $obrazek_cesta = null;

    // 1. Kontrola, zda není příspěvek prázdný
    if (empty($obsah) && empty($_FILES['obrazek']['name'])) {
        header("Location: komunita.php?status=error&msg=" . urlencode("Příspěvek nemůže být prázdný."));
        exit;
    }

    // 2. Zpracování obrázku (pokud byl nahrán)
    if (isset($_FILES['obrazek']) && $_FILES['obrazek']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/posts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $ext = pathinfo($_FILES['obrazek']['name'], PATHINFO_EXTENSION);
        $novy_nazev = 'post_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $cilova_cesta = $upload_dir . $novy_nazev;

        if (move_uploaded_file($_FILES['obrazek']['tmp_name'], $cilova_cesta)) {
            $obrazek_cesta = $cilova_cesta;
        }
    }

    // 3. Zápis do databáze
    try {
        $sql = "INSERT INTO prispevky (uzivatel_id, obsah, obrazek) VALUES (:uid, :obsah, :img)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'uid' => $uzivatel_id,
            'obsah' => $obsah,
            'img' => $obrazek_cesta
        ]);

        header("Location: komunita.php?status=success&msg=" . urlencode("Příspěvek byl sdílen!"));
        exit;
    } catch (PDOException $e) {
        header("Location: komunita.php?status=error&msg=" . urlencode("Chyba při ukládání: " . $e->getMessage()));
        exit;
    }
}