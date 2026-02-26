<?php 
session_start();

require_once 'db.php';
include 'pozadi.php'; 

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];

// Konfigurace cílů
$konfigurace_vyzev = [
    'login_10' => 2, // Uprav si hodnoty dle potřeby
    'login_count' => 5,    
    'default' => 100       
];

// --- OPRAVA BODU 1 ---
// Načtení tvých výzev, které NEJSOU v aktivním ANI dokončeném duelu
$sql = "SELECT uc.*, c.nazev, c.popis, c.typ 
        FROM public.uzivatele_challenge uc
        JOIN public.challenge c ON uc.challenge_id = c.id
        WHERE uc.uzivatel_id = :uid
        AND uc.challenge_id NOT IN (
            SELECT challenge_id 
            FROM public.challenge_souboje 
            WHERE (vyzyvatel_id = :uid OR souper_id = :uid) 
            AND status IN ('active', 'completed') -- Změna zde: ignorujeme i hotové duely
        )";
$stmt = $pdo->prepare($sql);
$stmt->execute(['uid' => $uzivatel_id]);
$vsechny_vyzvy = $stmt->fetchAll(PDO::FETCH_ASSOC);

$aktivni_questy = [];
$ziskani_andele = [];

foreach ($vsechny_vyzvy as $v) {
    $cil = $konfigurace_vyzev[$v['typ']] ?? $konfigurace_vyzev['default'];
    if ($v['aktualni_hodnota'] >= $cil) {
        $ziskani_andele[] = $v;
    } else {
        $aktivni_questy[] = $v;
    }
}

// --- PŘIDÁNÍ DOKONČENÝCH DUELŮ DO SBÍRKY ODZNAKŮ ---
$sql_hotove_duely = "SELECT uc.*, c.nazev, c.popis, c.typ 
                     FROM public.uzivatele_challenge uc
                     JOIN public.challenge c ON uc.challenge_id = c.id
                     WHERE uc.uzivatel_id = :uid
                     AND uc.challenge_id IN (
                         SELECT challenge_id FROM public.challenge_souboje 
                         WHERE (vyzyvatel_id = :uid OR souper_id = :uid) 
                         AND status = 'completed'
                     )";
$stmt_hd = $pdo->prepare($sql_hotove_duely);
$stmt_hd->execute(['uid' => $uzivatel_id]);
$hotove_duely_data = $stmt_hd->fetchAll(PDO::FETCH_ASSOC);

// Sloučíme sólo odznaky a odznaky z duelů
$ziskani_andele = array_merge($ziskani_andele, $hotove_duely_data);


// 2. Načtení aktivních DUELŮ (Souboj dvou barů) - Beze změny
$sql_aktivni_duely = "
    SELECT 
        ds.id as duel_id,
        c.nazev as challenge_nazev,
        c.typ as challenge_typ,
        p_souper.prezdivka as jmeno_souper,
        COALESCE(uc_ja.aktualni_hodnota, 0) as moje_hodnota,
        COALESCE(uc_souper.aktualni_hodnota, 0) as souper_hodnota
    FROM public.challenge_souboje ds
    JOIN public.challenge c ON ds.challenge_id = c.id
    JOIN public.profily p_souper ON (p_souper.uzivatel_id = CASE WHEN ds.vyzyvatel_id = :uid THEN ds.souper_id ELSE ds.vyzyvatel_id END)
    LEFT JOIN public.uzivatele_challenge uc_ja ON (uc_ja.challenge_id = ds.challenge_id AND uc_ja.uzivatel_id = :uid)
    LEFT JOIN public.uzivatele_challenge uc_souper ON (uc_souper.challenge_id = ds.challenge_id AND uc_souper.uzivatel_id = p_souper.uzivatel_id)
    WHERE (ds.vyzyvatel_id = :uid OR ds.souper_id = :uid) AND ds.status = 'active'";

$stmt_duely = $pdo->prepare($sql_aktivni_duely);
$stmt_duely->execute(['uid' => $uzivatel_id]);
$aktivni_duely = $stmt_duely->fetchAll(PDO::FETCH_ASSOC);

// 3. Načtení ostatních výzev k aktivaci - Beze změny
$sql_dostupne = "SELECT * FROM public.challenge 
                  WHERE id NOT IN (SELECT challenge_id FROM public.uzivatele_challenge WHERE uzivatel_id = :uid)";
$stmt_d = $pdo->prepare($sql_dostupne);
$stmt_d->execute(['uid' => $uzivatel_id]);
$dostupne_vyzvy = $stmt_d->fetchAll(PDO::FETCH_ASSOC);

// 4. Načtení soupeřů pro formulář - Beze změny
$sql_souperi = "SELECT u.id, p.prezdivka FROM public.uzivatele u 
                JOIN public.profily p ON u.id = p.uzivatel_id 
                WHERE u.id != :uid ORDER BY p.prezdivka ASC";
$stmt_s = $pdo->prepare($sql_souperi);
$stmt_s->execute(['uid' => $uzivatel_id]);
$souperi = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duel Aréna & Úspěchy</title>
    <style>
        /* PONECHÁVÁM TVŮJ PŮVODNÍ STYLE */
        body { font-family: 'Segoe UI', sans-serif; color: white; margin: 0; padding: 0; }
        .container { max-width: 900px; margin: 120px auto; padding: 20px; }
        .quest-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 255, 128, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .duel-arena-title { color: #ff0055; letter-spacing: 3px; text-align: center; margin-bottom: 30px; text-transform: uppercase; }
        .duel-box {
            border: 2px solid #ff0055;
            background: rgba(255, 0, 85, 0.08);
            box-shadow: 0 0 20px rgba(255, 0, 85, 0.2);
        }
        .progress-container {
            background: rgba(255, 255, 255, 0.1);
            height: 12px; border-radius: 6px; margin: 10px 0;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            transition: width 1s ease-in-out;
        }
        .btn-main-challenge {
            background: linear-gradient(45deg, #ff0055, #ff5500);
            color: white; border: none; padding: 15px 35px; border-radius: 50px;
            font-weight: bold; cursor: pointer; display: block; margin: 40px auto;
            box-shadow: 0 4px 15px rgba(255, 0, 85, 0.4); transition: 0.3s;
        }
        .btn-main-challenge:hover { transform: scale(1.05); }
        .btn-main-challenge.active { background: #222; color: #ff0055; border: 1px solid #ff0055; }
        .duel-expandable { max-height: 0; overflow: hidden; transition: max-height 0.5s ease; opacity: 0; }
        .duel-expandable.show { max-height: 500px; opacity: 1; margin-bottom: 40px; }
        .duel-select {
            background: #111; color: white; border: 1px solid #ff0055;
            padding: 12px; border-radius: 10px; margin: 5px; flex: 1;
        }
        .badge-grid { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 30px; }
        .badge-card { text-align: center; width: 120px; }
        .badge-circle {
            width: 70px; height: 70px; background: #ffcc00; border-radius: 50%;
            margin: 0 auto; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; border: 3px solid white;
        }
        .available-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .available-card {        
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            padding: 35px;
            text-align: left;
            transition: transform 0.3s ease, border-color 0.3s ease;
            text-decoration: none;
            color: white;
            position: relative;  
        }
          .available-card:hover {
            transform: translateY(-10px);
            border-color: #00ff80;
        }
    </style>
</head>
<body>

<div class="container">

    <?php if (!empty($aktivni_duely)): ?>
        <h2 class="duel-arena-title">PROBÍHAJÍCÍ SOUBOJE </h2>
        <?php foreach ($aktivni_duely as $d): 
            $cil = $konfigurace_vyzev[$d['challenge_typ']] ?? $konfigurace_vyzev['default'];
            $moje_procenta = min(100, ($d['moje_hodnota'] / $cil) * 100);
            $souper_procenta = min(100, ($d['souper_hodnota'] / $cil) * 100);
        ?>
            <div class="quest-card duel-box">
                <h3 style="margin-top: 0; text-align: center; color: #ff0055;">Souboj: <?php echo htmlspecialchars($d['challenge_nazev']); ?></h3>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
                            <span style="color: #00ff80;">VY (<?php echo $d['moje_hodnota']; ?> / <?php echo $cil; ?>)</span>
                            <span><?php echo round($moje_procenta); ?>%</span>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?php echo $moje_procenta; ?>%; background: #00ff80; box-shadow: 0 0 10px #00ff80;"></div>
                        </div>
                    </div>
                    <div style="text-align: center; font-weight: bold; color: #ff0055; font-style: italic;">VS</div>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
                            <span style="color: #ff3377;"><?php echo htmlspecialchars($d['jmeno_souper']); ?> (<?php echo $d['souper_hodnota']; ?> / <?php echo $cil; ?>)</span>
                            <span><?php echo round($souper_procenta); ?>%</span>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?php echo $souper_procenta; ?>%; background: #ff0055; box-shadow: 0 0 10px #ff0055;"></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<h2 style="color: #00ff80; letter-spacing: 2px;">MOJE OSOBNÍ VÝZVY</h2>

<?php if (empty($aktivni_questy)): ?>
    <div class="quest-card" style="text-align: center; border-style: dashed; opacity: 0.6;">
        <p style="margin: 0;">Zatím nemáš aktivované žádné osobní výzvy. Vyber si nějakou níže!</p>
    </div>
<?php else: ?>
    <?php foreach ($aktivni_questy as $av): 
        $cil = $konfigurace_vyzev[$av['typ']] ?? $konfigurace_vyzev['default'];
        $procenta = min(100, ($av['aktualni_hodnota'] / $cil) * 100);
    ?>
        <div class="quest-card">
            <div style="display: flex; justify-content: space-between;">
                <h3 style="margin: 0; font-size: 1.1rem;"><?php echo htmlspecialchars($av['nazev']); ?></h3>
                <span style="color: #00ff80; font-weight: bold;"><?php echo round($procenta); ?>%</span>
            </div>
            <div class="progress-container">
                <div class="progress-bar" style="width: <?php echo $procenta; ?>%; background: linear-gradient(90deg, #00ff80, #00ccff);"></div>
            </div>
            <p style="font-size: 0.85rem; opacity: 0.7; margin: 0;"><?php echo htmlspecialchars($av['popis']); ?></p>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

    <h2 style="margin-top: 40px;">NOVÉ VÝZVY</h2>
    <div class="available-grid">
        <?php foreach ($dostupne_vyzvy as $dv): ?>
            <div class="available-card">
                <h4 style="margin: 0;"><?php echo htmlspecialchars($dv['nazev']); ?></h4>
                <p style="font-size: 0.8rem; opacity: 0.7;"><?php echo htmlspecialchars($dv['popis']); ?></p>
                <form action="prijmout_vyzvu.php" method="POST">
                    <input type="hidden" name="challenge_id" value="<?php echo $dv['id']; ?>">
                    <button type="submit" style="background:none; border: 1px solid #00ff80; color:#00ff80; cursor:pointer; padding:5px 10px; border-radius:5px;">AKTIVOVAT</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

     <button class="btn-main-challenge" id="toggleDuelBtn" onclick="toggleDuelForm()">VYZVAT KAMARÁDA</button>
    <div id="duelFormContainer" class="duel-expandable">
        <div class="quest-card" style="border: 2px dashed #ff0055;">
            <form action="odeslat_duel.php" method="POST" style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
                <select name="souper_id" class="duel-select" required>
                    <option value="">Vyber soupeře</option>
                    <?php foreach ($souperi as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['prezdivka']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="challenge_id" class="duel-select" required>
                    <option value="">Vyber výzvu</option>
                    <?php 
                    $sql_vsechny = "SELECT id, nazev FROM public.challenge ORDER BY nazev ASC";
                    $vsechny_možnosti = $pdo->query($sql_vsechny)->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($vsechny_možnosti as $moznost): ?>
                        <option value="<?php echo $moznost['id']; ?>"><?php echo htmlspecialchars($moznost['nazev']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-main-challenge" style="margin: 5px; padding: 10px 20px;">ODESLAT</button>
            </form>
        </div>
    </div>

    <h2 style="color: #ffcc00; margin-top: 60px;">SBÍRKA ODZNAKŮ</h2>
<div class="badge-grid">
    <?php if (empty($ziskani_andele)): ?>
        <div class="quest-card" style="width: 100%; display: block; text-align: center; border-style: dashed; opacity: 0.6; box-sizing: border-box;">
            <p style="margin: 0;">Zatím nemáš žádné odznaky</p>
        </div>
    <?php else: ?>
        <?php foreach ($ziskani_andele as $o): 
            $stmt_vitez = $pdo->prepare("
                SELECT count(*) FROM public.challenge_souboje 
                WHERE challenge_id = ? AND vitez_id = ? AND status = 'completed'
            ");
            $stmt_vitez->execute([$o['challenge_id'], $uzivatel_id]);
            $je_vitez = $stmt_vitez->fetchColumn() > 0;
        ?>
            <div class="badge-card" style="position: relative; padding-top: 15px;">
                
                <?php if ($je_vitez): ?>
                    <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 2.5rem; z-index: 10; filter: drop-shadow(0 0 5px #ffcc00);">
                        👑
                    </div>
                <?php endif; ?>

                <div class="badge-circle" style="border-color: #ffcc00; box-shadow: 0 0 15px rgba(0, 0, 0, 0.3); background: rgba(0, 0, 0, 0.1);">
                    ⭐
                </div>

                <div class="badge-name" style="font-size: 0.8rem; margin-top: 5px; font-weight: bold; color: #ffcc00;">
                    <?php echo htmlspecialchars($o['nazev']); ?>
                </div>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</div>

<script>
function toggleDuelForm() {
    const container = document.getElementById('duelFormContainer');
    const btn = document.getElementById('toggleDuelBtn');
    container.classList.toggle('show');
    btn.innerText = container.classList.contains('show') ? 'ZRUŠIT' : 'VYZVAT KAMARÁDA';
}
</script>

</body>
</html>