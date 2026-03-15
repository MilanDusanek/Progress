<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'db.php';
session_start();

try {
    $uzivatel_id = $_SESSION['uzivatel_id'] ?? null;
    if (!$uzivatel_id)
        throw new Exception('Uživatel není přihlášen');

    $stmt = $pdo->prepare("SELECT bilkoviny, sacharidy, tuky, denni_prijem FROM kalkulacka_vysledky WHERE uzivatel_id = :id ORDER BY id DESC LIMIT 1");
    $stmt->execute(['id' => $uzivatel_id]);
    $cile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cile)
        throw new Exception('Nejdříve si vyplňte kalkulačku!');

    $pouzite_id = [];

    function vygenerujJidloPresne($pdo, $typ_sloupec, $denni_b, $denni_s, $denni_t, $procento, $uzivatel_id, $typ_jidla_nazev, &$pouzite_id)
    {
        $target_b = $denni_b * $procento;
        $target_s = $denni_s * $procento;
        $target_t = $denni_t * $procento;

        $pokusy = 0;
        $max_pokusu = 200;

        
        $sladke_bilkovina_ids = [148, 149, 150]; 
        $sladke_sacharid_ids = [28, 29, 32, 33, 35]; 
        $sladke_tuk_ids = [40, 42, 43, 44, 45, 46, 49]; 

        while ($pokusy < $max_pokusu) {
            $pokusy++;

            $not_in_sql = !empty($pouzite_id) ? "AND p.id NOT IN (" . implode(',', $pouzite_id) . ")" : "";

            if ($pokusy < 100) {
                $orderBy = "ORDER BY (CASE WHEN o.potravina_id IS NOT NULL THEN 1 ELSE 0 END) DESC, RAND() LIMIT 1";
            } else {
                $orderBy = "ORDER BY RAND() LIMIT 1";
            }

            
            $getFood = function ($kategorie, $required_chuť = null) use ($pdo, $typ_sloupec, $uzivatel_id, $typ_jidla_nazev, $not_in_sql, $orderBy, $sladke_bilkovina_ids, $sladke_sacharid_ids, $sladke_tuk_ids) {
                $chuť_filter = "";
                if ($required_chuť === 'sladke') {
                    if ($kategorie === 'sacharid') $chuť_filter = " AND p.id IN (" . implode(',', $sladke_sacharid_ids) . ")";
                    else if ($kategorie === 'tuk') $chuť_filter = " AND p.id IN (" . implode(',', $sladke_tuk_ids) . ")";
                } elseif ($required_chuť === 'slane') {
                    
                    if ($kategorie === 'sacharid') $chuť_filter = " AND p.id NOT IN (" . implode(',', array_merge($sladke_sacharid_ids, [29, 30, 31, 32, 33, 34, 35, 36])) . ")"; 
                    else if ($kategorie === 'tuk') $chuť_filter = " AND p.id NOT IN (" . implode(',', $sladke_tuk_ids) . ")";
                }

                $sql = "SELECT p.*, (o.potravina_id IS NOT NULL) as is_favorite 
                        FROM potraviny p 
                        LEFT JOIN uzivatel_oblibene o ON p.id = o.potravina_id 
                             AND o.uzivatel_id = :uid 
                             AND o.typ_jidla = :typ_nazev
                        WHERE p.$typ_sloupec = 1 
                        AND p.kategorie = :kat 
                        $chuť_filter
                        $not_in_sql 
                        $orderBy";

                $stmt = $pdo->prepare($sql);
                $stmt->execute(['uid' => $uzivatel_id, 'typ_nazev' => $typ_jidla_nazev, 'kat' => $kategorie]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                
                if (!$result && $required_chuť !== null) {
                    $sql_fallback = "SELECT p.*, (o.potravina_id IS NOT NULL) as is_favorite 
                                    FROM potraviny p 
                                    LEFT JOIN uzivatel_oblibene o ON p.id = o.potravina_id 
                                         AND o.uzivatel_id = :uid 
                                         AND o.typ_jidla = :typ_nazev
                                    WHERE p.$typ_sloupec = 1 
                                    AND p.kategorie = :kat 
                                    $not_in_sql 
                                    $orderBy";
                    $stmt_fb = $pdo->prepare($sql_fallback);
                    $stmt_fb->execute(['uid' => $uzivatel_id, 'typ_nazev' => $typ_jidla_nazev, 'kat' => $kategorie]);
                    $result = $stmt_fb->fetch(PDO::FETCH_ASSOC);
                }

                return $result;
            };

            
            $b_res = $getFood('bilkovina');
            if (!$b_res) continue;

            
            $chuť = in_array($b_res['id'], $sladke_bilkovina_ids) ? 'sladke' : 'slane';

            
            $s_res = $getFood('sacharid', $chuť);
            $t_res = $getFood('tuk', $chuť);

            if (!$s_res) continue;

            if (!$t_res) {
                $t_res = ['id' => 0, 'nazev' => 'Tuk (olej/doplněk)', 'bilkoviny_100g' => 0, 'sacharidy_100g' => 0, 'tuky_100g' => 100, 'is_favorite' => 0];
            }

            $a1 = $b_res['bilkoviny_100g'] / 100;
            $b1 = $s_res['bilkoviny_100g'] / 100;
            $c1 = $t_res['bilkoviny_100g'] / 100;
            $a2 = $b_res['sacharidy_100g'] / 100;
            $b2 = $s_res['sacharidy_100g'] / 100;
            $c2 = $t_res['sacharidy_100g'] / 100;
            $a3 = $b_res['tuky_100g'] / 100;
            $b3 = $s_res['tuky_100g'] / 100;
            $c3 = $t_res['tuky_100g'] / 100;

            $D = $a1 * ($b2 * $c3 - $b3 * $c2) - $b1 * ($a2 * $c3 - $a3 * $c2) + $c1 * ($a2 * $b3 - $a3 * $b2);
            if (abs($D) < 0.0001) continue;

            $g_b = ($target_b * ($b2 * $c3 - $b3 * $c2) - $b1 * ($target_s * $c3 - $target_t * $c2) + $c1 * ($target_s * $b3 - $target_t * $b2)) / $D;
            $g_s = ($a1 * ($target_s * $c3 - $target_t * $c2) - $target_b * ($a2 * $c3 - $a3 * $c2) + $c1 * ($a2 * $target_t - $a3 * $target_s)) / $D;
            $g_t = ($a1 * ($b2 * $target_t - $b3 * $target_s) - $b1 * ($a2 * $target_t - $a3 * $target_s) + $target_b * ($a2 * $b3 - $a3 * $b2)) / $D;

            if ($g_b >= 10 && $g_b <= 500 && $g_s >= 5 && $g_s <= 500 && $g_t >= 0 && $g_t <= 200) {

                $pouzite_id[] = $b_res['id'];
                $pouzite_id[] = $s_res['id'];
                if ($t_res['id'] > 0) $pouzite_id[] = $t_res['id'];

                $jidlo = [
                    'kcal' => round(($target_b * 4) + ($target_s * 4) + ($target_t * 9)),
                    'b' => round($target_b),
                    's' => round($target_s),
                    't' => round($target_t),
                    'suroviny_detaily' => [
                        ['id' => $b_res['id'], 'nazev' => $b_res['nazev'], 'gramy' => round($g_b), 'is_favorite' => (bool) $b_res['is_favorite']],
                        ['id' => $s_res['id'], 'nazev' => $s_res['nazev'], 'gramy' => round($g_s), 'is_favorite' => (bool) $s_res['is_favorite']]
                    ]
                ];
                if ($t_res['id'] > 0) {
                    $jidlo['suroviny_detaily'][] = ['id' => $t_res['id'], 'nazev' => $t_res['nazev'], 'gramy' => round($g_t), 'is_favorite' => (bool) $t_res['is_favorite']];
                }
                return $jidlo;
            }
        }
        throw new Exception("Nepodařilo se sestavit jídlo pro $typ_jidla_nazev! Snažil jsem se vytvořit optimální kombinaci jídel z databáze a žádná přesně neseděla do Vašich maker pro tento chod. Doporučuji buď vymazat nějaké oblíbené suroviny, nebo zkusit štěstí znovu.");
    }

    $snidane = vygenerujJidloPresne($pdo, 'snidane', $cile['bilkoviny'], $cile['sacharidy'], $cile['tuky'], 0.25, $uzivatel_id, 'snidane', $pouzite_id);
    $obed = vygenerujJidloPresne($pdo, 'hlavnijidlo', $cile['bilkoviny'], $cile['sacharidy'], $cile['tuky'], 0.40, $uzivatel_id, 'obed', $pouzite_id);
    $vecere = vygenerujJidloPresne($pdo, 'hlavnijidlo', $cile['bilkoviny'], $cile['sacharidy'], $cile['tuky'], 0.35, $uzivatel_id, 'vecere', $pouzite_id);

    
    aktualizujProgres($pdo, $uzivatel_id, 'meal_plan_gen');

    echo json_encode([
        'snidane' => $snidane,
        'obed' => $obed,
        'vecere' => $vecere
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}