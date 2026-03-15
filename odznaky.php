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

$stmt_reset = $pdo->prepare("UPDATE uzivatele SET nove_odznaky = 0 WHERE id = ?");
$stmt_reset->execute([$uzivatel_id]);


$stmt_all = $pdo->query("SELECT * FROM challenge ORDER BY id ASC");
$vsechny_vyzvy = $stmt_all->fetchAll(PDO::FETCH_ASSOC);


$stmt_user = $pdo->prepare("SELECT challenge_id, aktualni_hodnota, cil_hodnota, dosazeny_level FROM uzivatele_challenge WHERE uzivatel_id = ? AND je_solo = 1");
$stmt_user->execute([$uzivatel_id]);
$uzivatelske_data = $stmt_user->fetchAll(PDO::FETCH_UNIQUE);


$stmt_wins = $pdo->prepare("
    SELECT cs.*, c.nazev as cat_name, c.typ as cat_typ, u.email as souper_mail, p.prezdivka as souper_prezdivka
    FROM challenge_souboje cs
    JOIN challenge c ON cs.challenge_id = c.id
    LEFT JOIN profily p ON (p.uzivatel_id = CASE WHEN cs.vyzyvatel_id = ? THEN cs.souper_id ELSE cs.vyzyvatel_id END)
    JOIN uzivatele u ON (u.id = cs.souper_id OR u.id = cs.vyzyvatel_id) AND u.id != ?
    WHERE cs.vitez_id = ? AND cs.status = 'completed'
    ORDER BY cs.id DESC
");
$stmt_wins->execute([$uzivatel_id, $uzivatel_id, $uzivatel_id]);
$skalpy = $stmt_wins->fetchAll(PDO::FETCH_ASSOC);


$level_thresholds = [
    'login_count' => [1 => 5, 2 => 15, 3 => 50],
    'first_post' => [1 => 3, 2 => 10, 3 => 30],
    'weight_loss_10' => [1 => 5, 2 => 15, 3 => 40],
    'meal_plan_gen' => [1 => 3, 2 => 10, 3 => 30]
];

$ziskane_count = 0;
$zobrazene_vyzvy = [];

foreach ($vsechny_vyzvy as $c) {
    
    $data = $uzivatelske_data[$c['id']] ?? null;
    if (!$data) continue;

    $hodnota = $data['aktualni_hodnota'];
    $cil = $data['cil_hodnota'];
    $highest_lvl = $data['dosazeny_level'];
    
    
    $current_progress_lvl = 0;
    $thresholds = $level_thresholds[$c['typ']] ?? null;
    if ($thresholds) {
        foreach ($thresholds as $lvl => $thresh) {
            if ($hodnota >= $thresh) $current_progress_lvl = $lvl;
        }
    } else {
        if ($hodnota >= $cil) $current_progress_lvl = 3;
    }

    $final_lvl = max($highest_lvl, $current_progress_lvl);
    
    $je_ziskano = ($final_lvl > 0);
    if ($je_ziskano) $ziskane_count++;
    
    $tier = 'none';
    if ($final_lvl == 1) $tier = 'bronze';
    elseif ($final_lvl == 2) $tier = 'silver';
    elseif ($final_lvl >= 3) $tier = 'gold';

    $c['je_ziskano'] = $je_ziskano;
    $c['hodnota'] = $hodnota;
    $c['cil'] = $cil;
    $c['tier'] = $tier;
    $zobrazene_vyzvy[] = $c;
}


$ziskane_count += count($skalpy);

$procenta_celkem = count($vsechny_vyzvy) > 0 ? round(($ziskane_count / (count($vsechny_vyzvy) + 4)) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Síň slávy | Progress</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; color: white; background-color: #0a0a0a; overflow-x: hidden; }
        .container { width: 100%; max-width: 1100px; margin: 0 auto; padding: 120px 20px 60px 20px; box-sizing: border-box; }

        .hero-stats {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 25px;
            padding: 40px;
            margin-bottom: 50px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .stat-group { text-align: center; }
        .stat-value { font-size: 3rem; font-weight: 900; color: #00ff80; display: block; text-shadow: 0 0 20px rgba(0, 255, 128, 0.2); }
        .stat-label { font-size: 0.8rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 3px; font-weight: 700; }

        .section-title {
            font-size: 1.8rem;
            font-weight: 900;
            margin: 60px 0 30px 0;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .section-title::after { content: ''; flex-grow: 1; height: 1px; background: linear-gradient(90deg, rgba(255,255,255,0.1), transparent); }

        .subtitle { color: rgba(255,255,255,0.4); font-size: 0.9rem; margin-top: -25px; margin-bottom: 30px; display: block; font-weight: 600; }

        .badge-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 30px; }

        .badge-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 25px;
            padding: 35px 25px;
            text-align: center;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .badge-card:hover {
            transform: translateY(-8px);
            border-color: #00ff80;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .badge-shape {
            width: 90px;
            height: 90px;
            background: #1a1a1a;
            border-radius: 50% !important;
            margin: 0 auto 20px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255,255,255,0.05);
            position: relative;
            z-index: 2;
            overflow: hidden;
            box-sizing: border-box;
        }
        .badge-shape img {
            width: 50%;
            height: 50%;
            object-fit: contain;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.2));
        }
        .gold .badge-shape img, .silver .badge-shape img, .bronze .badge-shape img {
            filter: drop-shadow(0 0 10px rgba(0, 0, 0, 0.3));
        }
        /* TIER COLORS */
        .bronze .badge-shape {
            background: radial-gradient(circle at 30% 30%, #cd7f32, #6a3805);
            border-color: #ff9d5c;
            box-shadow: 0 10px 20px rgba(205, 127, 50, 0.3);
        }
        .silver .badge-shape {
            background: radial-gradient(circle at 30% 30%, #c0c0c0, #4a4a4a);
            border-color: #ffffff;
            box-shadow: 0 10px 25px rgba(192, 192, 192, 0.4);
            color: #000;
        }
        .gold .badge-shape {
            background: radial-gradient(circle at 30% 30%, #ffd700, #b8860b);
            border-color: #fff4b3;
            box-shadow: 0 10px 35px rgba(255, 215, 0, 0.5);
            color: #000;
            animation: gold-shine 3s infinite;
        }

        @keyframes gold-shine {
            0%, 100% { box-shadow: 0 10px 30px rgba(255, 215, 0, 0.4); }
            50% { box-shadow: 0 10px 50px rgba(255, 215, 0, 0.8); transform: scale(1.05); }
        }

        .locked { opacity: 0.25; filter: grayscale(1); cursor: not-allowed; }


        .badge-name { font-weight: 800; font-size: 1.1rem; display: block; margin-bottom: 8px; color: #fff; }
        .badge-desc { font-size: 0.8rem; color: rgba(255,255,255,0.4); line-height: 1.5; display: block; }

        .tier-label {
            display: inline-block;
            margin-top: 20px;
            font-size: 0.7rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            padding: 4px 12px;
            border-radius: 12px;
        }
        .bronze .tier-label { background: rgba(205, 127, 50, 0.15); color: #ff9d5c; }
        .silver .tier-label { background: rgba(192, 192, 192, 0.15); color: #fff; }
        .gold .tier-label { background: rgba(255, 215, 0, 0.15); color: #ffd700; }

        .progress-bar-mini {
            width: 100%;
            height: 6px;
            background: rgba(255,255,255,0.05);
            border-radius: 3px;
            margin-top: 20px;
            overflow: hidden;
        }
        .progress-fill-mini { height: 100%; background: #444; border-radius: 3px; }
        .locked-progress { font-size: 0.7rem; color: #555; margin-top: 8px; display: block; font-weight: 700; }

        /* DUEL TROPHY CARD */
        .trophy-badge {
            background: linear-gradient(145deg, rgba(255, 215, 0, 0.1), rgba(0, 0, 0, 0.3)) !important;
            border: 1px solid rgba(255, 215, 0, 0.2) !important;
        }
        .trophy-description {
            font-size: 0.75rem;
            color: #00ff80;
            font-weight: 700;
            margin-top: 10px;
            display: block;
            text-transform: uppercase;
        }

        @media (max-width: 768px) {
            .hero-stats { flex-direction: column; text-align: center; gap: 40px; padding: 25px; }
            .container { padding-top: 100px; padding-left: 15px; padding-right: 15px; }
            .badge-grid { grid-template-columns: 1fr; gap: 20px; }
            .section-title { font-size: 1.4rem; margin: 40px 0 20px 0; }
        }
    </style>
</head>
<body>

    <div class="container">
        
        <div class="hero-stats">
            <div>
                <h1 style="margin:0; font-size: 2.8rem; font-weight: 900; letter-spacing: -1px;">SÍŇ SLÁVY</h1>
                <p style="margin:5px 0 0 0; color: rgba(255,255,255,0.5); font-size: 1.1rem;">Tvé úspěchy a milníky na cestě za lepším já</p>
            </div>
            <div style="display: flex; gap: 50px;">
                <div class="stat-group">
                    <span class="stat-value"><?php echo $ziskane_count; ?></span>
                    <span class="stat-label">Úspěchů</span>
                </div>
                <div class="stat-group">
                    <span class="stat-value"><?php echo $procenta_celkem; ?>%</span>
                    <span class="stat-label">Sbírky</span>
                </div>
            </div>
        </div>

        <h2 class="section-title">Moje Milníky</h2>
        <span class="subtitle">Osobní výzvy, které jsi si aktivoval sám</span>
        <div class="badge-grid">
            <?php 
            if (empty($zobrazene_vyzvy)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: rgba(255,255,255,0.2); border-radius: 20px;">
                    Zatím nemáš aktivované žádné osobní výzvy. <a href="vyzvy.php" style="color: #00ff80; text-decoration: none;">Aktivovat první!</a>
                </div>
            <?php else:
                foreach ($zobrazene_vyzvy as $c): 
                    $ikonka = 'img/aktivita.png';
                    if (stripos($c['typ'], 'login') !== false) $ikonka = 'img/aktivita.png';
                    if (stripos($c['typ'], 'post') !== false) $ikonka = 'img/komunitaa.png';
                    if (stripos($c['typ'], 'weight') !== false) $ikonka = 'img/zdravi.png';
                    if (stripos($c['typ'], 'meal') !== false) $ikonka = 'img/vyziva.png';
                    $procenta = min(100, ($c['hodnota'] / $c['cil']) * 100);
                ?>
                    <div class="badge-card <?php echo $c['je_ziskano'] ? $c['tier'] : 'locked'; ?>">
                        <div class="badge-shape">
                            <img src="<?php echo $ikonka; ?>" alt="Icon">
                        </div>
                        <span class="badge-name"><?php echo htmlspecialchars($c['nazev']); ?></span>
                        <span class="badge-desc"><?php echo htmlspecialchars($c['popis']); ?></span>
                        
                        <?php if ($c['je_ziskano']): ?>
                            <div class="tier-label"><?php echo $c['tier']; ?></div>
                        <?php else: ?>
                            <div class="progress-bar-mini">
                                <div class="progress-fill-mini" style="width: <?php echo $procenta; ?>%;"></div>
                            </div>
                            <span class="locked-progress"><?php echo $c['hodnota']; ?> / <?php echo $c['cil']; ?></span>
                        <?php endif; ?>
                    </div>
            <?php endforeach; endif; ?>
        </div>

        <h2 class="section-title">Trofeje ze Soubojů</h2>
        <span class="subtitle">Odznaky získané vítězstvím nad ostatními</span>
        <div class="badge-grid">
            <?php 
            if (empty($skalpy)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: rgba(255,255,255,0.2); border-radius: 20px;">
                    Zatím jsi v žádném souboji nezvítězil. Vyzvi někoho!
                </div>
            <?php else: 
                foreach ($skalpy as $s): 
                    $souper_name = $s['souper_prezdivka'] ?: explode('@', $s['souper_mail'])[0];
                    $ikonka = ''; // No default emoji
                    if ($s['cat_typ'] == 'login_count') $ikonka = 'img/aktivita.png';
                    if ($s['cat_typ'] == 'first_post') $ikonka = 'img/komunitaa.png';
                    if ($s['cat_typ'] == 'weight_loss_10') $ikonka = 'img/zdravi.png';
                    if ($s['cat_typ'] == 'meal_plan_gen') $ikonka = 'img/vyziva.png';
            ?>
                <div class="badge-card gold trophy-badge">
                    <div class="badge-shape">
                        <?php if (strpos($ikonka, '.png') !== false): ?>
                            <img src="<?php echo $ikonka; ?>" alt="Icon">
                        <?php else: ?>
                            <?php echo $ikonka; ?>
                        <?php endif; ?>
                    </div>
                    <span class="badge-name"><?php echo htmlspecialchars($s['cat_name']); ?></span>
                    <span class="badge-desc">Porazil jsi uživatele <strong><?php echo htmlspecialchars($souper_name); ?></strong></span>
                    <span class="trophy-description">VÍTĚZSTVÍ</span>
                    <div class="tier-label">TROFEJ</div>
                </div>
            <?php endforeach; endif; ?>
        </div>

    </div>

</body>
</html>