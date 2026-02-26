<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); 
header('Content-Type: application/json');

require_once 'db.php';
session_start();

try {
    $uzivatel_id = $_SESSION['uzivatel_id'] ?? null;
    if (!$uzivatel_id) throw new Exception('Uživatel není přihlášen');

    $stmt = $pdo->prepare("SELECT bilkoviny, sacharidy, tuky FROM kalkulacka_vysledky WHERE uzivatel_id = :id ORDER BY id DESC LIMIT 1");
    $stmt->execute(['id' => $uzivatel_id]);
    $cile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cile) throw new Exception('Nejdříve si vyplňte kalkulačku!');

    $pouzite_id = [];

    function vygenerujJidloPresne($pdo, $typ_sloupec, $denni_b, $denni_s, $denni_t, $procento, $uzivatel_id, $typ_jidla_nazev, &$pouzite_id) {
        $target_b = $denni_b * $procento;
        $target_s = $denni_s * $procento;
        $target_t = $denni_t * $procento;

        $pokusy = 0;
        $max_pokusu = 200; 

        while ($pokusy < $max_pokusu) {
            $pokusy++;

            $not_in_sql = !empty($pouzite_id) ? "AND p.id NOT IN (" . implode(',', $pouzite_id) . ")" : "";
            
          
            if ($pokusy < 100) {
                $orderBy = "ORDER BY (CASE WHEN o.potravina_id IS NOT NULL THEN 1 ELSE 0 END) DESC, RANDOM() LIMIT 1";
            } else {
                $orderBy = "ORDER BY RANDOM() LIMIT 1";
            }

            $getFood = function($kategorie) use ($pdo, $typ_sloupec, $uzivatel_id, $typ_jidla_nazev, $not_in_sql, $orderBy) {
                $sql = "SELECT p.*, (o.potravina_id IS NOT NULL) as is_favorite 
                        FROM potraviny p 
                        LEFT JOIN uzivatel_oblibene o ON p.id = o.potravina_id 
                             AND o.uzivatel_id = :uid 
                             AND o.typ_jidla = :typ_nazev
                        WHERE p.$typ_sloupec = 1 
                        AND p.kategorie = :kat 
                        $not_in_sql 
                        $orderBy";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['uid' => $uzivatel_id, 'typ_nazev' => $typ_jidla_nazev, 'kat' => $kategorie]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            };

            $b_res = $getFood('bilkovina');
            $s_res = $getFood('sacharid');
            $t_res = $getFood('tuk');

            if (!$b_res || !$s_res || !$t_res) continue;

            $a1 = $b_res['bilkoviny_100g'] / 100; $b1 = $s_res['bilkoviny_100g'] / 100; $c1 = $t_res['bilkoviny_100g'] / 100;
            $a2 = $b_res['sacharidy_100g'] / 100; $b2 = $s_res['sacharidy_100g'] / 100; $c2 = $t_res['sacharidy_100g'] / 100;
            $a3 = $b_res['tuky_100g'] / 100;      $b3 = $s_res['tuky_100g'] / 100;      $c3 = $t_res['tuky_100g'] / 100;

            $D = $a1*($b2*$c3 - $b3*$c2) - $b1*($a2*$c3 - $a3*$c2) + $c1*($a2*$b3 - $a3*$b2);
            if (abs($D) < 0.0001) continue; 

            $g_b = ($target_b*($b2*$c3 - $b3*$c2) - $b1*($target_s*$c3 - $target_t*$c2) + $c1*($target_s*$b3 - $target_t*$b2)) / $D;
            $g_s = ($a1*($target_s*$c3 - $target_t*$c2) - $target_b*($a2*$c3 - $a3*$c2) + $c1*($a2*$target_t - $a3*$target_s)) / $D;
            $g_t = ($a1*($b2*$target_t - $b3*$target_s) - $b1*($a2*$target_t - $a3*$target_s) + $target_b*($a2*$b3 - $a3*$b2)) / $D;

            if ($g_b >= 20 && $g_b <= 300 && $g_s >= 20 && $g_s <= 300 && $g_t >= 2 && $g_t <= 100) {
                
                $pouzite_id[] = $b_res['id'];
                $pouzite_id[] = $s_res['id'];
                $pouzite_id[] = $t_res['id'];

                return [
                    'kcal' => round(($target_b * 4) + ($target_s * 4) + ($target_t * 9)),
                    'b' => round($target_b), 's' => round($target_s), 't' => round($target_t),
                    'suroviny_detaily' => [
                        ['id' => $b_res['id'], 'nazev' => $b_res['nazev'], 'gramy' => round($g_b), 'is_favorite' => (bool)$b_res['is_favorite']],
                        ['id' => $s_res['id'], 'nazev' => $s_res['nazev'], 'gramy' => round($g_s), 'is_favorite' => (bool)$s_res['is_favorite']],
                        ['id' => $t_res['id'], 'nazev' => $t_res['nazev'], 'gramy' => round($g_t), 'is_favorite' => (bool)$t_res['is_favorite']]
                    ]
                ];
            }
        }
        throw new Exception("Nepodařilo se sestavit jídlo pro $typ_jidla_nazev z vašich oblíbených surovin. Zkuste vybrat jiné kombinace.");
    }

    $snidane = vygenerujJidloPresne($pdo, 'snidane', $cile['bilkoviny'], $cile['sacharidy'], $cile['tuky'], 0.25, $uzivatel_id, 'snidane', $pouzite_id);
    $obed    = vygenerujJidloPresne($pdo, 'hlavniJidlo', $cile['bilkoviny'], $cile['sacharidy'], $cile['tuky'], 0.40, $uzivatel_id, 'obed', $pouzite_id);
    $vecere  = vygenerujJidloPresne($pdo, 'hlavniJidlo', $cile['bilkoviny'], $cile['sacharidy'], $cile['tuky'], 0.35, $uzivatel_id, 'vecere', $pouzite_id);

    echo json_encode([
        'snidane' => $snidane,
        'obed'    => $obed,
        'vecere'  => $vecere
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}