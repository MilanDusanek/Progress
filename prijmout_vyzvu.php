<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['uzivatel_id'])) {
    $challenge_id = filter_input(INPUT_POST, 'challenge_id', FILTER_VALIDATE_INT);
    $uzivatel_id = $_SESSION['uzivatel_id'];

    if ($challenge_id) {
        try {
            // Vložíme výzvu jako aktivní pro daného uživatele
            $stmt = $pdo->prepare("INSERT INTO public.uzivatele_challenge (uzivatel_id, challenge_id, start_hodnota, aktualni_hodnota) VALUES (?, ?, 0, 0)");
            $stmt->execute([$uzivatel_id, $challenge_id]);

            header("Location: odznaky.php?success=quest_started");
            exit;
        } catch (PDOException $e) {
            header("Location: odznaky.php?error=already_active");
            exit;
        }
    }
}