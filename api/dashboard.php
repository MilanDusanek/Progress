<?php
session_start();

require_once 'db.php';
include 'pozadi.php'; 

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}



$uzivatel_id = $_SESSION['uzivatel_id'];

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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            padding: 150px 50px 50px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }

        .card {
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

        .card:hover {
            transform: translateY(-10px);
            border-color: #00ff80;
        }

        .card h2 {
            color: #00ff80;
            margin: 0 0 20px 0;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        .card-value {
            font-size: 3.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .card-unit {
            font-size: 1.2rem;
            opacity: 0.5;
            font-weight: normal;
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
            margin-top: 25px;
            color: #00ff80;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }

        .lock-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.65); 
            backdrop-filter: blur(15px); 
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            z-index: 5;
            padding: 20px;
            box-sizing: border-box;
        }

        .lock-overlay img {
            width: 150px;
            margin-bottom: 15px;
        }

        .lock-overlay p {
            font-size: 0.85rem;
            font-weight: bold;
            color: #00ff80;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .lock-btn {
            background: #00ff80;
            color: black;
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            text-decoration: none;
            transition: 0.3s;
        }

        .lock-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(0, 255, 128, 0.4);
        }


    </style>
</head>
<body>

    
    <main class="dashboard-grid">
        
        <a href="kalkulackav2.php" class="card">
            <h2>Můj denní příjem</h2>
            <div class="card-value"><?= round($kcal) ?> <span class="card-unit">kcal</span></div>
            <div class="macros-info">
                <span>B: <?= round($b) ?>g</span>
                <span>S: <?= round($s) ?>g</span>
                <span>T: <?= round($t) ?>g</span>
            </div>
            <span class="btn-link">VYPOČÍTAT</span>
        </a>

        <?php if ($je_zamceno): ?>
            <div class="card">
                <div class="lock-overlay">
                    <img src="img/zamek.png" alt="Zámek">
                    <p>Nejdříve vypočítejte hodnoty</p>
                </div>
                <h2>Jídelníček</h2>
                <div class="card-value" style="font-size: 2.5rem; margin-top: 15px; margin-bottom: 25px;">Sestavit menu</div>
                <span class="btn-link">OTEVŘÍT GENERÁTOR</span>
            </div>
        <?php else: ?>
            <a href="jidelnicek.php" class="card">
                <h2>Jídelníček</h2>
                <div class="card-value" style="font-size: 2.5rem; margin-top: 15px; margin-bottom: 25px;">Sestavit menu</div>
                <div class="macros-info">
                Podle tvých makroživin
                </div>
                <span class="btn-link">OTEVŘÍT GENERÁTOR</span>
            </a>
        <?php endif; ?>

        <a href="tracking.php" class="card">
            <h2>Aktuální váha</h2>
            <div class="card-value"><?= $posledni_vaha ?> <span class="card-unit">kg</span></div>
            <div class="macros-info">
                <span>Poslední záznam</span>
            </div>
            <span class="btn-link">ZAPSAT NOVÉ TĚLESNÉ MÍRY</span>
        </a>

        
        <a href="komunita.php" class="card">
            <h2>Komunita</h2>
            <div class="card-value" style="font-size: 2.5rem; margin-top: 15px; margin-bottom: 25px;"></div>
            <span class="btn-link">PODÍVEJ SE, CO JE NOVÉHO</span>
        </a>
         
        <a href="odznaky.php" class="card">
            <h2>Odznaky</h2>
            <div class="card-value" style="font-size: 2.5rem; margin-top: 15px; margin-bottom: 25px;"></div>
            <span class="btn-link">PODÍVEJ SE NA SVÉ ODZNAKY</span>
        </a>
     

    </main>
</body>
</html>