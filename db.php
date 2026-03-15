<?php
$host = getenv('DB_HOST') ?: "db.dw357.endora.cz";
$dbname = getenv('DB_NAME') ?: "fitprogress_eu"; 
$user = getenv('DB_USER') ?: "fitprogress_eu";
$password = getenv('DB_PASS') ?: "WCnaYnXul3"; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Chyba připojení k databázi: " . $e->getMessage());
}


function syncLevelAndNotify($pdo, $uzivatel_id, $challenge_id) {
    $stmt = $pdo->prepare("
        SELECT uc.aktualni_hodnota, uc.cil_hodnota, uc.dosazeny_level, c.typ 
        FROM uzivatele_challenge uc
        JOIN challenge c ON uc.challenge_id = c.id
        WHERE uc.uzivatel_id = ? AND uc.challenge_id = ?
    ");
    $stmt->execute([$uzivatel_id, $challenge_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) return;

    $level_thresholds = [
        'login_count' => [1 => 5, 2 => 15, 3 => 50],
        'first_post' => [1 => 3, 2 => 10, 3 => 30],
        'weight_loss_10' => [1 => 5, 2 => 15, 3 => 40],
        'meal_plan_gen' => [1 => 3, 2 => 10, 3 => 30]
    ];

    $typ = $data['typ'];
    $val = $data['aktualni_hodnota'];
    $cil = $data['cil_hodnota'];
    $current_db_lvl = $data['dosazeny_level'];

    $this_level = 0;
    if (isset($level_thresholds[$typ])) {
        foreach ($level_thresholds[$typ] as $lvl => $thresh) {
            if ($cil == $thresh) {
                $this_level = $lvl;
                break;
            }
        }
    }

    $best_earned_lvl = 0;
    if (isset($level_thresholds[$typ])) {
        foreach ($level_thresholds[$typ] as $lvl => $thresh) {
            if ($val >= $thresh) $best_earned_lvl = $lvl;
        }
    }

    $final_sync_lvl = max($this_level, $best_earned_lvl);

    if ($val >= $cil || $best_earned_lvl > $current_db_lvl) {
        if ($final_sync_lvl > $current_db_lvl) {
            $stmt_lvl = $pdo->prepare("UPDATE uzivatele_challenge SET dosazeny_level = ? WHERE uzivatel_id = ? AND challenge_id = ? AND dosazeny_level < ?");
            $stmt_lvl->execute([$final_sync_lvl, $uzivatel_id, $challenge_id, $final_sync_lvl]);
            
            $stmt_flag = $pdo->prepare("UPDATE uzivatele SET nove_odznaky = 1 WHERE id = ?");
            $stmt_flag->execute([$uzivatel_id]);
        } 
        elseif ($val >= $cil && $current_db_lvl == 0 && $final_sync_lvl == 0) {
            $stmt_lvl = $pdo->prepare("UPDATE uzivatele_challenge SET dosazeny_level = 1 WHERE uzivatel_id = ? AND challenge_id = ? AND dosazeny_level = 0");
            $stmt_lvl->execute([$uzivatel_id, $challenge_id]);
            
            $stmt_flag = $pdo->prepare("UPDATE uzivatele SET nove_odznaky = 1 WHERE id = ?");
            $stmt_flag->execute([$uzivatel_id]);
        }
    }
}

function kontrolujDokonceniDuely($pdo, $uzivatel_id, $challenge_id) {
    $stmt_duel = $pdo->prepare("
        SELECT id, vyzyvatel_id, souper_id, cil_hodnota 
        FROM challenge_souboje 
        WHERE challenge_id = ? AND status = 'active' 
        AND (vyzyvatel_id = ? OR souper_id = ?)
    ");
    $stmt_duel->execute([$challenge_id, $uzivatel_id, $uzivatel_id]);
    $duel = $stmt_duel->fetch(PDO::FETCH_ASSOC);

    if (!$duel) return;

    $duel_id = $duel['id'];
    $cil = $duel['cil_hodnota'];

    $stmt_progres = $pdo->prepare("
        SELECT uzivatel_id, aktualni_hodnota 
        FROM uzivatele_challenge 
        WHERE challenge_id = ? AND (uzivatel_id = ? OR uzivatel_id = ?)
    ");
    $stmt_progres->execute([$challenge_id, $duel['vyzyvatel_id'], $duel['souper_id']]);
    $rows = $stmt_progres->fetchAll(PDO::FETCH_ASSOC);

    $progresy = [];
    foreach ($rows as $row) {
        $progresy[$row['uzivatel_id']] = $row['aktualni_hodnota'];
    }

    $prog_ja = $progresy[$uzivatel_id] ?? 0;
    $souper_id = ($duel['vyzyvatel_id'] == $uzivatel_id) ? $duel['souper_id'] : $duel['vyzyvatel_id'];
    $prog_souper = $progresy[$souper_id] ?? 0;

    if ($prog_ja >= $cil) {
        $stmt_win = $pdo->prepare("UPDATE challenge_souboje SET status = 'completed', vitez_id = ? WHERE id = ?");
        $stmt_win->execute([$uzivatel_id, $duel_id]);
        
        // Notifikace vítězi
        $stmt_n1 = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ, souvisejici_id) VALUES (?, ?, 'duel_win', ?)");
        $stmt_n1->execute([$uzivatel_id, $souper_id, $duel_id]);

        // Notifikace poraženému
        $stmt_n2 = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ, souvisejici_id) VALUES (?, ?, 'duel_lose', ?)");
        $stmt_n2->execute([$souper_id, $uzivatel_id, $duel_id]);

        syncLevelAndNotify($pdo, $uzivatel_id, $challenge_id);
    } elseif ($prog_souper >= $cil) {
        $stmt_win = $pdo->prepare("UPDATE challenge_souboje SET status = 'completed', vitez_id = ? WHERE id = ?");
        $stmt_win->execute([$souper_id, $duel_id]);
        
        // Notifikace vítězi (soupeř)
        $stmt_n1 = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ, souvisejici_id) VALUES (?, ?, 'duel_win', ?)");
        $stmt_n1->execute([$souper_id, $uzivatel_id, $duel_id]);

        // Notifikace poraženému (já)
        $stmt_n2 = $pdo->prepare("INSERT INTO oznameni (prijemce_id, odesilatel_id, typ, souvisejici_id) VALUES (?, ?, 'duel_lose', ?)");
        $stmt_n2->execute([$uzivatel_id, $souper_id, $duel_id]);

        syncLevelAndNotify($pdo, $souper_id, $challenge_id);
    }
}

function synchronizujVsehenPokrok($pdo, $uzivatel_id) {
    $stmt = $pdo->prepare("SELECT challenge_id FROM challenge_souboje WHERE status = 'active' AND (vyzyvatel_id = ? OR souper_id = ?)");
    $stmt->execute([$uzivatel_id, $uzivatel_id]);
    $aktivni = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($aktivni as $d) {
        kontrolujDokonceniDuely($pdo, $uzivatel_id, $d['challenge_id']);
    }
    
    $stmt_ids = $pdo->prepare("SELECT challenge_id FROM uzivatele_challenge WHERE uzivatel_id = ?");
    $stmt_ids->execute([$uzivatel_id]);
    $ids = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($ids as $cid) {
        syncLevelAndNotify($pdo, $uzivatel_id, $cid);
    }
}

function validujVsechnyAktivniDuely($pdo, $uzivatel_id) {
    synchronizujVsehenPokrok($pdo, $uzivatel_id);
}

function aktualizujProgres($pdo, $uzivatel_id, $typ_vyzvy, $mnozstvi = 1) {
    $stmt = $pdo->prepare("SELECT id FROM challenge WHERE typ = ?");
    $stmt->execute([$typ_vyzvy]);
    $challenge_id = $stmt->fetchColumn();

    if (!$challenge_id) return;

    $stmt_old = $pdo->prepare("SELECT aktualni_hodnota FROM uzivatele_challenge WHERE uzivatel_id = ? AND challenge_id = ?");
    $stmt_old->execute([$uzivatel_id, $challenge_id]);
    $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
    
    if (!$old_data) return;

    $omezeni_denne = ['login_count', 'meal_plan_gen', 'weight_loss_10'];
    $dnes = date('Y-m-d');
    $preskocit_update = false;

    if (in_array($typ_vyzvy, $omezeni_denne)) {
        $stmt_check = $pdo->prepare("SELECT posledni_aktualizace FROM uzivatele_challenge WHERE uzivatel_id = ? AND challenge_id = ?");
        $stmt_check->execute([$uzivatel_id, $challenge_id]);
        $posledni = $stmt_check->fetchColumn();
        if ($posledni && substr($posledni, 0, 10) === $dnes) $preskocit_update = true;
    }

    if (!$preskocit_update) {
        $stmt_upd = $pdo->prepare("UPDATE uzivatele_challenge SET aktualni_hodnota = aktualni_hodnota + ?, posledni_aktualizace = NOW() WHERE uzivatel_id = ? AND challenge_id = ?");
        $stmt_upd->execute([$mnozstvi, $uzivatel_id, $challenge_id]);
        syncLevelAndNotify($pdo, $uzivatel_id, $challenge_id);
    }

    kontrolujDokonceniDuely($pdo, $uzivatel_id, $challenge_id);
}
?>