<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'db.php';
session_start();

try {
    $uzivatel_id = $_SESSION['uzivatel_id'] ?? null;
    if (!$uzivatel_id) throw new Exception('Uživatel není přihlášen');

    $data = json_decode(file_get_contents('php://input'), true);
    $potravina_id = $data['potravina_id'] ?? null;
    $typ_jidla = $data['typ_jidla'] ?? null;

    if (!$potravina_id || !$typ_jidla) throw new Exception('Chybějící parametry');

    $check = $pdo->prepare("SELECT 1 FROM public.uzivatel_oblibene WHERE uzivatel_id = :uid AND potravina_id = :pid AND typ_jidla = :typ");
    $check->execute(['uid' => $uzivatel_id, 'pid' => $potravina_id, 'typ' => $typ_jidla]);

    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM public.uzivatel_oblibene WHERE uzivatel_id = :uid AND potravina_id = :pid AND typ_jidla = :typ");
        $stmt->execute(['uid' => $uzivatel_id, 'pid' => $potravina_id, 'typ' => $typ_jidla]);
        echo json_encode(['status' => 'removed']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO public.uzivatel_oblibene (uzivatel_id, potravina_id, typ_jidla) VALUES (:uid, :pid, :typ)");
        $stmt->execute(['uid' => $uzivatel_id, 'pid' => $potravina_id, 'typ' => $typ_jidla]);
        echo json_encode(['status' => 'added']);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}