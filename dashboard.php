<?php
session_start();

require_once 'db.php';
include 'pozadi.php'; 

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: index.php");
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];


aktualizujProgres($pdo, $uzivatel_id, 'login_count');

synchronizujVsehenPokrok($pdo, $uzivatel_id);

$stmt_vysledky = $pdo->prepare("SELECT denni_prijem, bilkoviny, sacharidy, tuky FROM kalkulacka_vysledky WHERE uzivatel_id = :id ORDER BY id DESC LIMIT 1");
$stmt_vysledky->execute(['id' => $uzivatel_id]);
$vysledky = $stmt_vysledky->fetch(PDO::FETCH_ASSOC);

$stmt_vaha = $pdo->prepare("SELECT vaha FROM profily WHERE uzivatel_id = :id");
$stmt_vaha->execute(['id' => $uzivatel_id]);
$posledni_vaha = $stmt_vaha->fetchColumn() ?: "--";

$kcal = $vysledky['denni_prijem'] ?? 0;
$b = $vysledky['bilkoviny'] ?? 0;
$s = $vysledky['sacharidy'] ?? 0;
$t = $vysledky['tuky'] ?? 0;

$je_zamceno = ($kcal <= 0);



$stmt_active = $pdo->prepare("
    SELECT COUNT(*) FROM uzivatele_challenge uc
    WHERE uc.uzivatel_id = ? 
    AND uc.aktualni_hodnota < uc.cil_hodnota
    AND uc.challenge_id NOT IN (
        SELECT challenge_id 
        FROM challenge_souboje 
        WHERE (vyzyvatel_id = ? OR souper_id = ?) 
        AND status IN ('active', 'completed')
    )
");
$stmt_active->execute([$uzivatel_id, $uzivatel_id, $uzivatel_id]);
$pocet_aktivnich = $stmt_active->fetchColumn();


$stmt_badges_total = $pdo->prepare("SELECT SUM(dosazeny_level) FROM uzivatele_challenge WHERE uzivatel_id = ?");
$stmt_badges_total->execute([$uzivatel_id]);
$sum_badges = $stmt_badges_total->fetchColumn() ?: 0;


$stmt_nove = $pdo->prepare("SELECT nove_odznaky FROM uzivatele WHERE id = ?");
$stmt_nove->execute([$uzivatel_id]);
$nove_odznaky_count = $stmt_nove->fetchColumn();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress | Dashboard</title>
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            padding: 130px 20px 40px;
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;

        }

        .card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 25px;
            text-align: left;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none;
            color: white;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 180px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform-style: preserve-3d;
            perspective: 1000px;
        }

        .card:hover {
            transform: translateY(-10px) rotateX(4deg) rotateY(-2deg);
            border-color: #00ff80;
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
        }

        .card-icon-bg {
            position: absolute;
            bottom: 25px;
            right: 25px;
            width: 80px;
            height: 80px;
            opacity: 0.1;
            pointer-events: none;
            transition: all 0.4s ease;
            filter: grayscale(1) brightness(1.5);
        }

        .card:hover .card-icon-bg {
            opacity: 0.25;
            transform: scale(1.2) rotate(-10deg);
            filter: grayscale(0) brightness(1.1);
        }
        .card h2 {
            color: #00ff80;
            margin: 0 0 10px 0;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 800;
            opacity: 0.8;
        }

        .card-value {
            font-size: 2.2rem;
            font-weight: 900;
            margin-bottom: 5px;
            line-height: 1.1;
            letter-spacing: -1px;
        }

        .card-unit {
            font-size: 0.9rem;
            opacity: 0.4;
            font-weight: 600;
            margin-left: 5px;
            letter-spacing: 0;
        }

        .macros-info {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 20px;
            margin-top: 10px;
            opacity: 0.8;
        }

        .btn-link {
            display: inline-block;
            margin-top: 15px;
            color: #00ff80;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }

        .card-locked {
            cursor: default;
            overflow: hidden;
        }

        .card-blur-content {
            filter: blur(8px);
            opacity: 0.3;      
            pointer-events: none; 
        }

        .lock-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
            background: rgba(0, 0, 0, 0.2); 
            border-radius: 25px;
        }

        .lock-overlay img {
            width: 50px; 
            height: auto;
            margin-bottom: 15px;
            filter: drop-shadow(0 0 10px rgba(0, 255, 128, 0.3));
        }

        .lock-overlay p {
            color: #00ff80;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 0 0 5px 0;
            font-size: 1rem;
        }

        .lock-subtext {
            color: white;
            opacity: 0.7;
            font-size: 0.75rem;
            margin-bottom: 20px;
            max-width: 200px;
            line-height: 1.4;
        }

        .lock-btn {
            background: #00ff80;
            color: black;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            text-decoration: none;
            transition: 0.3s;
            text-transform: uppercase;
        }

        .lock-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(0, 255, 128, 0.6);
        }

        .notification-dot {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 11px;
            height: 11px;
            background-color: #ff4d4d;
            border-radius: 50%;
            z-index: 5;
            animation: pulse-alert 2s infinite;
        }

        @keyframes pulse-alert {
            0% { box-shadow: 0 0 0 0 rgba(255, 77, 77, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(255, 77, 77, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 77, 77, 0); }
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                padding: 100px 15px 30px; 
                gap: 15px; 
                width: 100%;
                overflow-x: hidden;
            }
            
            .card {
                min-height: 160px; 
                padding: 20px;
            }
            
            .card-value {
                font-size: 1.8rem;
            }
            
            .card h2 {
                font-size: 0.65rem;
            }
            
            .btn-link {
                font-size: 0.75rem;
                margin-top: 10px;
            }
            
            .macros-info {
                font-size: 0.85rem;
                padding-top: 15px;
            }
        }
    </style>
</head>
<body>

    
    <main class="dashboard-grid">
        
        <a href="kalkulacka.php" class="card">
            <img src="img/kalkulacka.png" class="card-icon-bg" alt="">
            <div>
                <h2>Můj denní příjem</h2>
                <div class="card-value"><?= round($kcal) ?> <span class="card-unit">kcal</span></div>
                <div class="macros-info">
                    <span>B: <?= round($b) ?>g</span>
                    <span>S: <?= round($s) ?>g</span>
                    <span>T: <?= round($t) ?>g</span>
                </div>
            </div>
            <span class="btn-link">VYPOČÍTAT</span>
        </a>

        <?php if ($je_zamceno): ?>
            <div class="card">
                <img src="img/jidelnicek.png" class="card-icon-bg" alt="" style="opacity: 0.03;">
                <div class="lock-overlay">
                    <div class="lock-icon">
                        <img src="img/zamek.png" alt="Zámek">
                    </div>
                    <p>Jídelníček</p> 
                    <span style="color: rgba(255,255,255,0.7); font-size: 0.7rem; margin-bottom: 15px; display: block;">
                        Nejdříve si v kalkulačce spočítej svůj denní příjem.
                    </span>
                    <a href="kalkulacka.php" class="lock-btn">PŘEJÍT KE KALKULAČCE</a>
                </div>
            </div>
        <?php else: ?>
            <a href="jidelnicek.php" class="card">
                <img src="img/jidelnicek.png" class="card-icon-bg" alt="">
                <div>
                    <h2>Jídelníček</h2>
                    <div class="card-value" style="font-size: 1.8rem;">Sestavit menu</div>
                    <div class="macros-info" style="font-size: 0.8rem;">Podle tvých makroživin</div>
                </div>
                <span class="btn-link">OTEVŘÍT GENERÁTOR</span>
            </a>
        <?php endif; ?>

        <a href="tracking.php" class="card">
            <img src="img/tracking.png" class="card-icon-bg" alt="">
            <div>
                <h2>Aktuální váha</h2>
                <div class="card-value"><?= $posledni_vaha ?> <span class="card-unit">kg</span></div>
                <div class="macros-info">
                    <span>Poslední záznam</span>
                </div>
            </div>
            <span class="btn-link">ZAPSAT NOVÉ TĚLESNÉ MÍRY</span>
        </a>

        
        <a href="komunita.php" class="card">
            <img src="img/komunita.png" class="card-icon-bg" alt="">
            <div>
                <h2>Komunita</h2>
                <div class="card-value" style="font-size: 1.5rem;">Sleduj pokroky ostatních</div>
            </div>
            <span class="btn-link">PŘIPOJ SE K OSTATNÍM</span>
        </a>
         
        <a href="odznaky.php" class="card">
            <img src="img/odznaky.png" class="card-icon-bg" alt="">
            <?php if (isset($nove_odznaky_count) && $nove_odznaky_count > 0): ?>
                <div class="notification-dot"></div>
            <?php endif; ?>
            <div>
                <h2>Odznaky</h2>
                <div class="card-value"><?= $sum_badges ?> <span class="card-unit">získaných úrovní</span></div>
            </div>
            <span class="btn-link">MÉ DOSAŽENÉ ÚSPĚCHY</span>
        </a>

        <a href="vyzvy.php" class="card">
            <img src="img/vyzvy.png" class="card-icon-bg" alt="">
            <div>
                <h2>Výzvy</h2>
                <div class="card-value"><?= $pocet_aktivnich ?> <span class="card-unit">aktivních výzev</span></div>
            </div>
            <span class="btn-link">VSTOUPIT DO ARÉNY</span>
        </a>
     

    </main>
</body>
</html>