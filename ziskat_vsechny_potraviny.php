<?php
require_once 'db.php';
session_start();

ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    $uzivatel_id = $_SESSION['uzivatel_id'] ?? null;
    $typ = $_GET['typ'] ?? 'snidane';

    if (!$uzivatel_id) {
        echo json_encode([]); 
        exit;
    }

    
    $filterColumn = ($typ === 'snidane') ? 'snidane' : 'hlavnijidlo';

    
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.nazev, 
            p.kategorie,
            p.kcal_100g,
            p.bilkoviny_100g,
            p.sacharidy_100g,
            p.tuky_100g,
            CASE 
                WHEN uo.potravina_id IS NOT NULL THEN 1 
                ELSE 0 
            END as is_favorite
        FROM potraviny p
        LEFT JOIN uzivatel_oblibene uo ON p.id = uo.potravina_id 
            AND uo.uzivatel_id = :uid 
            AND uo.typ_jidla = :typ
        WHERE p.$filterColumn = 1
        ORDER BY is_favorite DESC, p.nazev ASC
    ");

    $stmt->execute([
        'uid' => $uzivatel_id,
        'typ' => $typ
    ]);

    $potraviny = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($potraviny ?: []);

} catch (Exception $e) {
    
    echo json_encode([]);
}