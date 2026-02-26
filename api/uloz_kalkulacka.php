<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

$uzivatel_id = $_SESSION['uzivatel_id'] ?? null;

if (!$uzivatel_id) {
    echo json_encode(['success' => false, 'message' => 'Uživatel není přihlášen.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    try {
        $cil = $data['goal'];
        if ($cil === 'udrzba')
            $cil = 'udrzovani';

        $sql = "INSERT INTO public.kalkulacka_vysledky 
                (uzivatel_id, denni_prijem, bilkoviny, sacharidy, tuky, cil, bmr, vaha_v_dobe_vypoctu, aktivita_koeficient, tdee) 
                VALUES 
                (:uid, :prijem, :b, :s, :t, :cil, :bmr, :vaha, :aktivita, :tdee)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid' => $uzivatel_id,
            ':prijem' => $data['kcal'],
            ':b' => $data['protein'],
            ':s' => $data['carbs'],
            ':t' => $data['fats'],
            ':cil' => $cil,
            ':bmr' => $data['bmr'],
            ':vaha' => $data['weight'],
            ':aktivita' => $data['activity'],
            ':tdee' => $data['tdee']
        ]);

        echo json_encode(['success' => true, 'message' => 'Výsledky byly uloženy do tvého postupu!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Chyba DB: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Nebyla přijata žádná data.']);
}