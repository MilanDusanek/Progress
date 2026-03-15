<?php
session_start();
require 'db.php'; 

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uzivatel_id = $_SESSION['uzivatel_id'];

    // Kontrola profilu
    $stmt_p = $pdo->prepare("SELECT 1 FROM profily WHERE uzivatel_id = ? LIMIT 1");
    $stmt_p->execute([$uzivatel_id]);
    if (!$stmt_p->fetch()) {
        header("Location: komunita.php?status=incomplete_profile");
        exit;
    }

    $obsah = trim($_POST['obsah']);
    $obrazek_cesta = null;

    
    if (empty($obsah) && empty($_FILES['obrazek']['name'])) {
        header("Location: komunita.php?status=error&msg=" . urlencode("Příspěvek nemůže být prázdný."));
        exit;
    }

    
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

    
    try {
        $sql = "INSERT INTO prispevky (uzivatel_id, obsah, obrazek) VALUES (:uid, :obsah, :img)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'uid' => $uzivatel_id,
            'obsah' => $obsah,
            'img' => $obrazek_cesta
        ]);

        
        aktualizujProgres($pdo, $uzivatel_id, 'first_post', 1);

        header("Location: komunita.php?status=success&msg=" . urlencode("Příspěvek byl sdílen!"));
        exit;
    } catch (PDOException $e) {
        header("Location: komunita.php?status=error&msg=" . urlencode("Chyba při ukládání: " . $e->getMessage()));
        exit;
    }
}