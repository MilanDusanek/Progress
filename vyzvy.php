<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once 'db.php';
include 'pozadi.php'; 

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];

validujVsechnyAktivniDuely($pdo, $uzivatel_id);

$konfigurace_vyzev = [
    'login_10' => 2, 
    'login_count' => 5,    
    'default' => 100       
];


$sql = "SELECT uc.*, c.nazev, c.popis, c.typ 
        FROM uzivatele_challenge uc
        JOIN challenge c ON uc.challenge_id = c.id
        WHERE uc.uzivatel_id = :uid
        AND uc.challenge_id NOT IN (
            SELECT challenge_id 
            FROM challenge_souboje 
            WHERE (vyzyvatel_id = :uid OR souper_id = :uid) 
            AND status IN ('active', 'completed')
        )
        GROUP BY uc.challenge_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['uid' => $uzivatel_id]);
$vsechny_vyzvy = $stmt->fetchAll(PDO::FETCH_ASSOC);

$aktivni_questy = [];
$ziskani_andele = [];
$uzivatelsky_pokrok_mapa = [];

foreach ($vsechny_vyzvy as $v) {
    $uzivatelsky_pokrok_mapa[$v['challenge_id']] = $v;
    
    $cil = $v['cil_hodnota'];
    if ($v['aktualni_hodnota'] >= $cil) {
        $ziskani_andele[] = $v;
    } else {
        $aktivni_questy[] = $v;
    }
}

$sql_hotove_duely = "SELECT uc.*, c.nazev, c.popis, c.typ 
                     FROM uzivatele_challenge uc
                     JOIN challenge c ON uc.challenge_id = c.id
                     WHERE uc.uzivatel_id = :uid
                     AND uc.challenge_id IN (
                         SELECT challenge_id FROM challenge_souboje 
                         WHERE (vyzyvatel_id = :uid OR souper_id = :uid) 
                         AND status = 'completed'
                     )";
$stmt_hd = $pdo->prepare($sql_hotove_duely);
$stmt_hd->execute(['uid' => $uzivatel_id]);
$hotove_duely_data = $stmt_hd->fetchAll(PDO::FETCH_ASSOC);




$sql_aktivni_duely = "
    SELECT 
        ds.id as duel_id,
        ds.cil_hodnota as duel_cil,
        c.nazev as challenge_nazev,
        c.typ as challenge_typ,
        c.popis as challenge_popis,
        COALESCE(p_souper.prezdivka, 'Soupeř') as jmeno_souper,
        MAX(COALESCE(uc_ja.aktualni_hodnota, 0)) as moje_hodnota,
        MAX(COALESCE(uc_souper.aktualni_hodnota, 0)) as souper_hodnota
    FROM challenge_souboje ds
    JOIN challenge c ON ds.challenge_id = c.id
    LEFT JOIN profily p_souper ON (p_souper.uzivatel_id = CASE WHEN ds.vyzyvatel_id = :uid THEN ds.souper_id ELSE ds.vyzyvatel_id END)
    LEFT JOIN uzivatele_challenge uc_ja ON (uc_ja.challenge_id = ds.challenge_id AND uc_ja.uzivatel_id = :uid)
    LEFT JOIN uzivatele_challenge uc_souper ON (uc_souper.challenge_id = ds.challenge_id AND uc_souper.uzivatel_id = CASE WHEN ds.vyzyvatel_id = :uid THEN ds.souper_id ELSE ds.vyzyvatel_id END)
    WHERE (ds.vyzyvatel_id = :uid OR ds.souper_id = :uid) AND ds.status = 'active'
    GROUP BY ds.id";

$stmt_duely = $pdo->prepare($sql_aktivni_duely);
$stmt_duely->execute(['uid' => $uzivatel_id]);
$aktivni_duely = $stmt_duely->fetchAll(PDO::FETCH_ASSOC);


$sql_dostupne = "SELECT * FROM challenge 
                 WHERE id NOT IN (
                     SELECT challenge_id FROM uzivatele_challenge WHERE uzivatel_id = :uid
                 )
                 AND id NOT IN (
                     SELECT challenge_id FROM challenge_souboje 
                     WHERE (vyzyvatel_id = :uid OR souper_id = :uid) 
                     AND status IN ('active', 'pending')
                 )
                 ORDER BY nazev ASC";
$stmt_d = $pdo->prepare($sql_dostupne);
$stmt_d->execute(['uid' => $uzivatel_id]);
$dostupne_vyzvy = $stmt_d->fetchAll(PDO::FETCH_ASSOC);


$sql_souperi = "SELECT u.id, p.prezdivka, p.profilovy_obrazek 
                FROM sledujici s
                JOIN uzivatele u ON s.sledovany_id = u.id
                JOIN profily p ON u.id = p.uzivatel_id 
                WHERE s.sledujici_id = :uid 
                ORDER BY p.prezdivka ASC";
$stmt_s = $pdo->prepare($sql_souperi);
$stmt_s->execute(['uid' => $uzivatel_id]);
$souperi = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

$sql_vsechny_pro_duel = "SELECT id, nazev FROM challenge 
                         WHERE id NOT IN (
                             SELECT challenge_id FROM uzivatele_challenge WHERE uzivatel_id = :uid
                         )
                         AND id NOT IN (
                             SELECT challenge_id FROM challenge_souboje 
                             WHERE (vyzyvatel_id = :uid OR souper_id = :uid) 
                             AND status IN ('active', 'pending')
                         )
                         ORDER BY nazev ASC";
$stmt_v = $pdo->prepare($sql_vsechny_pro_duel);
$stmt_v->execute(['uid' => $uzivatel_id]);
$vsechny_možnosti = $stmt_v->fetchAll(PDO::FETCH_ASSOC);


$stmt_ids = $pdo->query("SELECT typ, id FROM challenge");
$type_to_id = $stmt_ids->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<style>
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            color: white;
        }

        .container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            padding: 130px 20px 50px 20px;
            box-sizing: border-box;
            position: relative;
            z-index: 10;
        }

        /* Sekční nadpisy */
        .section-title {
            color: #ffffff;
            letter-spacing: 1px;
            font-size: 1.4rem;
            margin-bottom: 25px;
            margin-top: 45px;
            font-weight: 700;
        }

        .duel-arena-title {
            color: #ffffff;
        }

        .badges-title {
            color: #ffffff;
        }

        /* Glassmorphism karty pro Výzvy - stejny styl jako dashboard */
        .quest-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .quest-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0) 100%);
            pointer-events: none;
        }

        .quest-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(0, 255, 128, 0.35);
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
        }

        /* Speciální Duel Karta */
        .duel-box {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-radius: 20px;
        }

        .duel-box > * {
            position: relative;
            z-index: 1;
        }

        .progress-container {
            background: rgba(255, 255, 255, 0.05);
            height: 12px;
            border-radius: 10px;
            margin: 12px 0 5px 0;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .progress-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        /* Lesk progress baru */
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0) 100%);
            border-radius: 10px;
        }

        /* Hodnota progressu nad barem textově */
        .progress-text-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .btn-main-challenge {
            background: #ffffff;
            color: #000000;
            border: none;
            padding: 12px 35px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            display: block;
            margin: 20px auto 10px auto;
            transition: all 0.2s ease;
            text-transform: uppercase;
        }

        .btn-main-challenge:hover {
            transform: translateY(-2px);
            background: #f0f0f0;
        }

        /* --- ARÉNA KROKY --- */
        .arena-step {
            display: none;
            animation: fadeIn 0.4s ease;
        }
        .arena-step.active { display: block; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        .step-dot {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 800;
            color: rgba(255,255,255,0.3);
            transition: all 0.3s;
        }
        .step-dot.active {
            background: rgba(255, 255, 255, 0.1);
            border-color: #ffffff;
            color: #ffffff;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.1);
        }
        .step-dot.done {
            background: #ffffff;
            color: #000000;
            border-color: #ffffff;
        }

        .btn-activate {
            background: transparent;
            border: 1px solid #00ff80;
            color: #00ff80;
            padding: 8px 18px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            margin-top: 15px;
            display: inline-block;
        }

        .btn-activate:hover {
            background: rgba(0, 255, 128, 0.1);
        }

        .duel-expandable {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out, opacity 0.3s;
            opacity: 0;
        }

        .duel-expandable.show {
            max-height: 800px;
            opacity: 1;
            margin-bottom: 25px;
        }

        /* Nové stylování Select a inputů */
        .duel-select {
            background: rgba(0, 0, 0, 0.3);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 18px;
            border-radius: 20px;
            margin: 5px;
            flex: 1;
            font-size: 0.95rem;
            transition: border-color 0.2s;
            outline: none;
            min-width: 200px;
        }

        .duel-select:focus {
            border-color: #00ff80;
        }
        
        .duel-select option {
            background: #222;
            color: white;
        }

        .available-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .badge-grid {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }

        .badge-card {
            text-align: center;
            width: 100px;
        }

        .badge-circle {
            width: 75px;
            height: 75px;
            background: radial-gradient(circle at 30% 30%, #ffd633, #e6b800);
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            border: 2px solid rgba(255,255,255,0.2);
            box-shadow: 0 6px 15px rgba(230, 184, 0, 0.3), inset 0 -5px 10px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .badge-circle::after {
            content: '';
            position: absolute;
            top: 5px;
            left: 10px;
            width: 20px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.8);
            transform: rotate(-45deg);
        }

        .badge-name {
            font-size: 0.8rem;
            margin-top: 10px;
            font-weight: 600;
            color: #ffcc00;
        }

        .vs-text {
            text-align: center;
            font-weight: 800;
            color: #ffffff;
            font-size: 1.2rem;
            letter-spacing: 2px;
            margin: 8px 0;
        }
        .level-description {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 25px;
            min-height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 1.4;
        }

        .level-dots {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .level-dot {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
            color: rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 800;
            position: relative;
            transition: all 0.4s ease;
        }

        /* Stav: HOTOVO */
        .level-dot.done {
            background: rgba(0, 255, 128, 0.15);
            border-color: #00ff80;
            color: #00ff80;
        }
        .level-dot.done::after {
            content: '✓';
            position: absolute;
            font-size: 0.6rem;
            bottom: -2px;
            right: -2px;
            background: #00ff80;
            color: black;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Stav: AKTUÁLNÍ / K AKTIVACI */
        .level-dot.active {
            background: #00ff80;
            border-color: #00ff80;
            color: #000;
            box-shadow: 0 0 20px rgba(0, 255, 128, 0.4);
            transform: scale(1.1);
        }

        /* Stav: ZAMČENO */
        .level-dot.locked {
            opacity: 0.4;
            filter: grayscale(1);
        }

        .category-icon {
            margin-bottom: 15px;
            display: block;
        }
        .category-icon img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            filter: drop-shadow(0 0 10px rgba(0, 255, 128, 0.3));
        }

        .type-icon-mini img {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }

        /* FRIENDS LIST REDESIGN */
        .friends-search {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px 20px;
            color: white;
            margin-bottom: 20px;
            outline: none;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .friends-search:focus { 
            border-color: white;
        }

        .friends-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            max-height: 250px;
            overflow-y: auto;
            padding: 10px;
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
        }

        .friend-card-mini {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .friend-card-mini:hover { background: rgba(255,255,255,0.08); transform: translateY(-2px); }
        .friend-card-mini.selected { border-color: #ffffff; background: rgba(255, 255, 255, 0.1); box-shadow: 0 0 15px rgba(255, 255, 255, 0.1); }

        .friend-img-mini {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-bottom: 8px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .friend-name-mini { font-size: 0.8rem; font-weight: 600; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .challenge-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 20px;
        }
        .type-card-mini {
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 12px;
            text-align: left;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .type-card-mini:hover { background: rgba(255,255,255,0.05); }
        .type-card-mini.selected { border-color: #00ff80; background: rgba(0, 255, 128, 0.1); }
        .type-icon-mini { font-size: 1.4rem; }
        .type-info-mini h5 { margin: 0; font-size: 0.85rem; color: #ffffff; }
        .type-info-mini p { margin: 0; font-size: 0.7rem; color: rgba(255,255,255,0.5); }

        /* --- MOBILE RESPONSIVE --- */
        @media (max-width: 768px) {
            .container {
                padding: 100px 15px 40px 15px; 
                width: 100%;
                overflow-x: hidden;
                box-sizing: border-box;
            }

            .available-grid, .challenge-type-grid {
                grid-template-columns: 1fr; 
            }
            
            .quest-card {
                padding: 20px;
                box-sizing: border-box;
            }
            
            .section-title {
                font-size: 1.2rem;
                margin-top: 35px;
            }
            
            .friends-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
        }
    </style>
<div class="container">

    <?php if (!empty($aktivni_duely)): ?>
        <h2 class="section-title duel-arena-title">PROBÍHAJÍCÍ SOUBOJE</h2>
        <?php foreach ($aktivni_duely as $d): 
            $cil = ($d['duel_cil'] > 0) ? $d['duel_cil'] : ($konfigurace_vyzev[$d['challenge_typ']] ?? $konfigurace_vyzev['default']);
            $moje_procenta = min(100, ($d['moje_hodnota'] / $cil) * 100);
            $souper_procenta = min(100, ($d['souper_hodnota'] / $cil) * 100);
            
            $ja_vedu = $d['moje_hodnota'] > $d['souper_hodnota'];
            $on_vede = $d['souper_hodnota'] > $d['moje_hodnota'];
        ?>
            <div class="quest-card duel-box" style="<?php echo ($ja_vedu) ? 'border-color: #00ff80; box-shadow: 0 0 15px rgba(0, 255, 128, 0.2);' : ''; ?>">
                <h3 style="margin-top: 0; text-align: center; color: white; font-size: 1.15rem; font-weight: 700; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($d['challenge_nazev']); ?>
                </h3>
                <p style="margin: -15px 0 20px 0; text-align: center; font-size: 0.85rem; color: rgba(255,255,255,0.5); font-weight: 500;">
                    <?php echo htmlspecialchars($d['challenge_popis']); ?>
                </p>
                
                <div style="display: flex; flex-direction: column; gap: 6px;">
                    <div style="position: relative;">
                        <?php if($ja_vedu): ?><span style="position: absolute; right: 0; top: -15px; font-size: 0.7rem; color: #00ff80; font-weight: bold; letter-spacing: 1px;">DRŽÍŠ VEDENÍ</span><?php endif; ?>
                        <div class="progress-text-row">
                            <span style="color: #00ff80;">VY (<?php echo $d['moje_hodnota']; ?> / <?php echo $cil; ?>)</span>
                            <span style="color: #00ff80;"><?php echo round($moje_procenta); ?>%</span>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?php echo $moje_procenta; ?>%; background: linear-gradient(90deg, #00b359, #00ff80);"></div>
                        </div>
                    </div>
                    
                    <div class="vs-text" style="color: #00ff80; text-shadow: 0 0 10px rgba(0, 255, 128, 0.4);">VS</div>
                    
                    <div style="position: relative;">
                        <?php if($on_vede): ?><span style="position: absolute; right: 0; top: -15px; font-size: 0.7rem; color: #00ff80; font-weight: bold; letter-spacing: 1px; text-shadow: 0 0 10px rgba(0, 255, 128, 0.4);">SOUPEŘ VEDE</span><?php endif; ?>
                        <div class="progress-text-row">
                            <span style="color: rgba(255,255,255,0.6);"><?php echo htmlspecialchars($d['jmeno_souper']); ?> (<?php echo $d['souper_hodnota']; ?> / <?php echo $cil; ?>)</span>
                            <span style="color: rgba(255,255,255,0.6);"><?php echo round($souper_procenta); ?>%</span>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?php echo $souper_procenta; ?>%; background: linear-gradient(90deg, #333, #666);"></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<h2 class="section-title">MOJE AKTIVOVANÉ VÝZVY</h2>

<?php if (empty($aktivni_questy)): ?>
    <div class="quest-card" style="text-align: center; border-style: dashed; opacity: 0.6;">
        <p style="margin: 0;">Zatím nemáš aktivované žádné výzvy. Vyber si nějakou níže!</p>
    </div>
<?php else: ?>
    <?php foreach ($aktivni_questy as $av): 
        $cil = $av['cil_hodnota'];
        $procenta = min(100, ($av['aktualni_hodnota'] / $cil) * 100);
    ?>
        <div class="quest-card">
            <h3 style="margin: 0 0 10px 0; font-size: 1.1rem;"><?php echo htmlspecialchars($av['nazev']); ?></h3>
            <p style="font-size: 0.85rem; color: rgba(255,255,255,0.7); margin: 0 0 15px 0;"><?php echo htmlspecialchars($av['popis']); ?></p>
            
            <div class="progress-text-row">
                <span>Hodnota: <?php echo $av['aktualni_hodnota']; ?> / <?php echo $cil; ?></span>
                <span style="color: #00ff80; font-weight: bold;"><?php echo round($procenta); ?>%</span>
            </div>
            <div class="progress-container">
                <div class="progress-bar" style="width: <?php echo $procenta; ?>%; background: linear-gradient(90deg, #00b359, #00ff80); box-shadow: 0 0 10px rgba(0, 255, 128, 0.2);"></div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

    <h2 class="section-title">NOVÉ VÝZVY</h2>
    <div class="available-grid">
        <?php 
        $kategorie = [
            [
                'id' => $type_to_id['login_count'] ?? 0,
                'typ' => 'login_count',
                'nazev' => 'Aktivita',
                'ikona' => 'img/aktivita.png',
                'popis' => 'Ukaž svou vytrvalost pravidelným vracením se na web.',
                'levely' => [
                    1 => ['hodnota' => 5, 'text' => 'Přihlaš se 5x na web'],
                    2 => ['hodnota' => 15, 'text' => 'Přihlaš se 15x na web'],
                    3 => ['hodnota' => 50, 'text' => 'Přihlaš se 50x na web']
                ]
            ],
            [
                'id' => $type_to_id['first_post'] ?? 0,
                'typ' => 'first_post',
                'nazev' => 'Komunita',
                'ikona' => 'img/komunitaa.png',
                'popis' => 'Sdílej své pokroky s ostatními a inspiruj svět.',
                'levely' => [
                    1 => ['hodnota' => 3, 'text' => 'Sdílej 3 příspěvky'],
                    2 => ['hodnota' => 10, 'text' => 'Sdílej 10 příspěvků'],
                    3 => ['hodnota' => 30, 'text' => 'Sdílej 30 příspěvků']
                ]
            ],
            [
                'id' => $type_to_id['weight_loss_10'] ?? 0,
                'typ' => 'weight_loss_10',
                'nazev' => 'Zdraví',
                'ikona' => 'img/zdravi.png',
                'popis' => 'Sleduj svou váhu a buď pánem svého těla.',
                'levely' => [
                    1 => ['hodnota' => 5, 'text' => 'Zapiš váhu 5x'],
                    2 => ['hodnota' => 15, 'text' => 'Zapiš váhu 15x'],
                    3 => ['hodnota' => 40, 'text' => 'Zapiš váhu 40x']

                ]
            ],
            [
                'id' => $type_to_id['meal_plan_gen'] ?? 0,
                'typ' => 'meal_plan_gen',
                'nazev' => 'Výživa',
                'ikona' => 'img/vyziva.png',
                'popis' => 'Sestavuj si pravidelně jídelníček na míru tvým cílům.',
                'levely' => [
                    1 => ['hodnota' => 3, 'text' => 'Sestav jídelníček 3x'],
                    2 => ['hodnota' => 10, 'text' => 'Sestav jídelníček 10x'],
                    3 => ['hodnota' => 30, 'text' => 'Sestav jídelníček 30x']
                ]
            ]
        ];

        foreach ($kategorie as $kat): 
            if (!$kat['id']) continue; 

            
            $pokrok = $uzivatelsky_pokrok_mapa[$kat['id']] ?? null;
            
            $zobrazit_level = 1;
            $je_hotovo_vse = false;
            $je_v_procesu = false;

            if ($pokrok) {
                if ($pokrok['aktualni_hodnota'] < $pokrok['cil_hodnota']) {
                    
                    $je_v_procesu = true;
                } else {
                    
                    $zobrazit_level = $pokrok['dosazeny_level'] + 1;
                    if ($zobrazit_level > 3) $je_hotovo_vse = true;
                }
            }

            if ($je_v_procesu) continue; 
            
            if (!$je_hotovo_vse) {
                $level_data = $kat['levely'][$zobrazit_level];
            ?>
                <div class="quest-card" id="card_<?php echo $kat['typ']; ?>" style="text-align: center; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <span class="category-icon">
                            <img src="<?php echo $kat['ikona']; ?>" alt="<?php echo $kat['nazev']; ?>">
                        </span>
                        <h3 style="margin: 0 0 10px 0; font-size: 1.3rem;"><?php echo $kat['nazev']; ?></h3>
        
                        <div class="level-dots">
                            <?php for ($i = 1; $i <= 3; $i++): 
                                $status_class = 'locked';
                                if ($i < $zobrazit_level) $status_class = 'done';
                                elseif ($i == $zobrazit_level) $status_class = 'active';
                            ?>
                                <div class="level-dot <?php echo $status_class; ?>">
                                    <?php echo $i; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
        
                        <div class="level-description" id="desc_<?php echo $kat['typ']; ?>">
                            <?php echo $level_data['text']; ?>
                        </div>
                    </div>
    
                    <form action="aktivovat.php" method="POST" style="margin: 0;">
                        <input type="hidden" name="challenge_id" value="<?php echo $kat['id']; ?>">
                        <input type="hidden" name="cil_hodnota" id="val_<?php echo $kat['typ']; ?>" value="<?php echo $level_data['hodnota']; ?>">
                        <button type="submit" class="btn-main-challenge" style="width: 100%; margin: 0; box-shadow: 0 0 20px rgba(0, 255, 128, 0.2); background: #00ff80;">
                            AKTIVOVAT
                        </button>
                    </form>
                </div>
            <?php } else { ?>
                <div class="quest-card" style="text-align: center; border-color: #ffd700; background: rgba(255, 215, 0, 0.05);">
                    <span class="category-icon"><img src="img/odznaky.png" style="width: 50px; height: 50px; object-fit: contain;"></span>
                    <h3 style="margin: 0 0 10px 0; font-size: 1.3rem; color: #ffd700;"><?php echo $kat['nazev']; ?></h3>
                    <p style="font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-bottom: 20px;">
                        Všechny úrovně této výzvy byly úspěšně zdolány. Jsi mistr!
                    </p>
                    <button disabled class="btn-main-challenge" style="width: 100%; margin: 0; background: #333; color: #777; box-shadow: none; cursor: default;">
                        MISTROVSTVÍ DOSAŽENO
                    </button>
                </div>
            <?php } ?>
        <?php endforeach; ?>
    </div>

     <?php if ($ukazat_tecku): ?>
        <p style="text-align: center; color: rgba(255,255,255,0.4); font-size: 0.9rem; margin-top: 20px;">
            Pro vyzvání kamaráda k souboji si nejdříve dokonči profil.
        </p>
     <?php else: ?>
        <button class="btn-main-challenge" id="toggleDuelBtn" onclick="toggleDuelForm()" style="background: #00ff80; border: none; font-weight: 800; letter-spacing: 1px;">VYZVAT KAMARÁDA</button>
     <?php endif; ?>
    
    <div id="duelFormContainer" class="duel-expandable">
        <div class="quest-card" style="border: 1px solid rgba(0, 255, 128, 0.2); background: rgba(0, 255, 128, 0.02);">
            <div class="step-indicator">
                <div class="step-dot active" id="dot1">1</div>
                <div class="step-dot" id="dot2">2</div>
                <div class="step-dot" id="dot3">3</div>
            </div>

            <!-- STEP 1: SOUPEŘ -->
            <div class="arena-step active" id="step1">
                <h4 style="margin-top: 0; color: #00ff80; text-align: center; letter-spacing: 2px; font-weight: 800;">KROK 1: VYBER SI SOUPEŘE</h4>
                <input type="text" id="friendSearch" class="friends-search" placeholder="HLEDEJ PODLE JMÉNA..." onkeyup="filterFriends()">
                <div class="friends-grid" id="friendsGrid">
                    <?php if (empty($souperi)): ?>
                        <p style="grid-column: 1/-1; text-align: center; color: #777; font-size: 0.9rem;">Zatím nikoho nesleduješ.</p>
                    <?php else: ?>
                        <?php foreach ($souperi as $s): ?>
                            <div class="friend-card-mini" onclick="selectFriend(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['prezdivka']); ?>', this)" data-name="<?php echo strtolower($s['prezdivka']); ?>">
                                <img src="<?php echo htmlspecialchars($s['profilovy_obrazek'] ?: 'img/userIcon.png'); ?>" class="friend-img-mini">
                                <span class="friend-name-mini"><?php echo htmlspecialchars($s['prezdivka']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STEP 2: AKTIVITA -->
            <div class="arena-step" id="step2">
                <h4 style="margin-top: 0; color: #00ff80; text-align: center; letter-spacing: 2px; font-weight: 800;">KROK 2: V ČEM SE UTKÁTE?</h4>
                <div class="challenge-type-grid">
                    <?php foreach ($kategorie as $kat): ?>
                        <div class="type-card-mini" id="duel_type_<?php echo $kat['typ']; ?>" onclick="selectDuelType(<?php echo $kat['id']; ?>, '<?php echo $kat['typ']; ?>', '<?php echo $kat['nazev']; ?>', this)">
                            <span class="type-icon-mini">
                                <img src="<?php echo $kat['ikona']; ?>" alt="<?php echo $kat['nazev']; ?>">
                            </span>
                            <div class="type-info-mini">
                                <h5><?php echo $kat['nazev']; ?></h5>
                                <p><?php echo $kat['popis']; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-activate" style="margin: 20px auto; display: block;" onclick="goToStep(1)">ZPĚT</button>
            </div>

            <!-- STEP 3: POTVRZENÍ -->
            <div class="arena-step" id="step3">
                <h4 style="margin-top: 0; color: #00ff80; text-align: center; letter-spacing: 2px; font-weight: 800;">KROK 3: PŘIPRAVIT K BOJI!</h4>
                
                <div id="duelSummary" style="background: rgba(0,0,0,0.3); border-radius: 15px; padding: 20px; margin: 20px 0; text-align: center;">
                    <p style="margin: 0; color: rgba(255,255,255,0.6); font-size: 0.9rem;">Vyzýváš uživatele:</p>
                    <p style="font-size: 1.3rem; font-weight: 800; color: #fff; margin: 5px 0 15px 0;" id="summaryFriend">-</p>
                    
                    <p style="margin: 0; color: rgba(255,255,255,0.6); font-size: 0.9rem;">V disciplíně:</p>
                    <p style="font-size: 1.3rem; font-weight: 800; color: #00ff80; margin: 5px 0 15px 0;" id="summaryType">-</p>
                    
                    <div id="duelLevelDesc" style="background: rgba(0,255,128,0.1); border-radius: 10px; padding: 10px; font-weight: 600; color: #00ff80; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; gap: 10px;">
                        Vyberte typ...
                    </div>
                </div>

                <form id="duelFormMain" style="margin-top: 20px; text-align: center;" onsubmit="submitDuel(event)">
                    <input type="hidden" name="souper_id" id="selectedFriendId" required>
                    <input type="hidden" name="challenge_id" id="selectedChallengeId" required>
                    <input type="hidden" name="cil_hodnota" id="selectedDuelCil" required>
                    
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button type="button" class="btn-activate" style="margin: 0;" onclick="goToStep(2)">ZPĚT</button>
                        <button type="submit" class="btn-main-challenge" style="margin: 0; flex-grow: 1;" id="submitDuelBtn">VYZVI!</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


</div>

<script>
function toggleDuelForm() {
    const container = document.getElementById('duelFormContainer');
    const btn = document.getElementById('toggleDuelBtn');
    container.classList.toggle('show');
    btn.innerText = container.classList.contains('show') ? 'ZRUŠIT' : 'VYZVAT KAMARÁDA';
}

function filterFriends() {
    const search = document.getElementById('friendSearch').value.toLowerCase();
    const cards = document.querySelectorAll('.friend-card-mini');
    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        card.style.display = name.includes(search) ? 'block' : 'none';
    });
}

function goToStep(n) {
    document.querySelectorAll('.arena-step').forEach(s => s.classList.remove('active'));
    document.getElementById('step' + n).classList.add('active');
    
    // Update dots
    for (let i = 1; i <= 3; i++) {
        const dot = document.getElementById('dot' + i);
        dot.classList.remove('active', 'done');
        if (i < n) dot.classList.add('done');
        if (i === n) dot.classList.add('active');
    }
}

function selectFriend(id, name, el) {
    document.querySelectorAll('.friend-card-mini').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedFriendId').value = id;
    document.getElementById('summaryFriend').innerText = name;
    
    setTimeout(() => goToStep(2), 300);
}

function selectDuelType(id, typ, name, el) {
    document.querySelectorAll('.type-card-mini').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedChallengeId').value = id;
    document.getElementById('summaryType').innerText = name;
    
    // Načtení dat o cíli (Level 1)
    const kategorieData = <?php echo json_encode($kategorie); ?>;
    const kat = kategorieData.find(k => k.typ === typ);
    if (kat) {
        const level1 = kat.levely[1];
        document.getElementById('duelLevelDesc').innerHTML = `
            <img src="${kat.ikona}" style="width: 24px; height: 24px; object-fit: contain;">
            <div style="text-align: left;">
                <span style="opacity: 0.7; font-size: 0.7rem; display: block;">Cíl bitvy:</span>
                <span>${level1.text}</span>
            </div>
        `;
        document.getElementById('selectedDuelCil').value = level1.hodnota;
    }
    
    setTimeout(() => goToStep(3), 300);
}

// selectLevel logic removed as levels are now sequential and not user-selectable

function submitDuel(e) {
    e.preventDefault();
    const form = document.getElementById('duelFormMain');
    const souperId = document.getElementById('selectedFriendId').value;
    const challengeId = document.getElementById('selectedChallengeId').value;
    const cilHodnota = document.getElementById('selectedDuelCil').value;

    if (!souperId || !challengeId) {
        if (typeof showToast === 'function') showToast('Vyber soupere a disciplinu');
        return;
    }

    const body = new URLSearchParams({ souper_id: souperId, challenge_id: challengeId, cil_hodnota: cilHodnota });

    fetch('poslat_vyzvu.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Skryjeme formular a resetujeme
            const container = document.getElementById('duelFormContainer');
            container.classList.remove('show');
            document.getElementById('toggleDuelBtn').innerText = 'VYZVAT KAMARADA';
            // Reset back to step 1
            goToStep(1);
            document.querySelectorAll('.friend-card-mini').forEach(c => c.classList.remove('selected'));
            document.querySelectorAll('.type-card-mini').forEach(c => c.classList.remove('selected'));
            document.getElementById('selectedFriendId').value = '';
            document.getElementById('selectedChallengeId').value = '';
            document.getElementById('selectedDuelCil').value = '';
            document.getElementById('summaryFriend').innerText = '-';
            document.getElementById('summaryType').innerText = '-';

            if (typeof showToast === 'function') showToast('Výzva odeslána');
            // Vyrolování zpět nahoru
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            if (typeof showToast === 'function') showToast('Chyba při odesílání výzvy');
        }
    })
    .catch(() => {
        if (typeof showToast === 'function') showToast('Chyba připojení');
    });
}
</script>

</body>
</html>