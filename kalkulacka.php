<?php
session_start();
if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}
$uzivatel_id = $_SESSION['uzivatel_id'];


$host = "localhost";
$dbname = "Progress";
$user = "postgres";
$password = "heslo";
$dsn = "pgsql:host=$host;dbname=$dbname";

$trackingData = [];
$json_tracking_data = '[]';

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT datum, vaha, pas, boky, biceps FROM tracking WHERE uzivatel_id = :uid ORDER BY datum ASC");
    $stmt->execute(['uid' => $uzivatel_id]);
    $trackingData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $json_tracking_data = json_encode($trackingData);

} catch (PDOException $e) {
    // V reálné aplikaci byste zde měli logovat chybu
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sledování Progresu a Kalkulačka</title>
    
    <!-- Zde jsem odstranil odkaz na Chart.js, jak jste požadoval -->

    <style>
    /* PŮVODNÍ STYLY - Nastavení barvy textu pro input[type="date"] a spin-button */
    .profile-form input[type="date"] {
        color: white; 
    }
    
    .profile-form input[type="date"]::-webkit-calendar-picker-indicator {
        filter: invert(1); 
        cursor: pointer;
    }

    .profile-form input[type="number"]::-webkit-inner-spin-button,
    .profile-form input[type="number"]::-webkit-outer-spin-button {
        filter: invert(1); 
        opacity: 0.8; 
        cursor: pointer;
    }
    
    .profile-form input[type="number"]::-webkit-inner-spin-button:hover,
    .profile-form input[type="number"]::-webkit-outer-spin-button:hover {
        background: transparent !important;
        opacity: 1;
    }
    
    /* PŮVODNÍ STYLY - Všeobecné nastavení těla */
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column; 
        min-height: 100vh; 
        justify-content: flex-start; /* Zarovná obsah nahoru */
        align-items: center; /* Centruje obsah na šířku */
        padding-top: 120px; /* Mezera pro fixní header */
    }

    /* PŮVODNÍ STYLY - Pozadí a blob efekty */
    .background-container { 
        width: 100vw; 
        height: 100vh; 
        position: fixed; 
        top: 0; 
        left: 0; 
        background-color: #000000; 
        overflow: hidden; 
        z-index: -1; 
    }
    .blob { position: absolute; border-radius: 50%; opacity: 0.8; filter: blur(120px); }
    .blob-1 { width: 500px; height: 750px; background-color: #00ff80; top: 15%; left: 5%; }
    .blob-2 { width: 600px; height: 600px; background-color: #00ff80; top: 20%; right: 5%; }

    /* KONTEJNER PRO KALKULAČKU - CENTROVANÝ A STYLOVANÝ */
    .profile-form-container {
        display: flex;
        flex-direction: column;
        max-width: 500px; 
        width: 90%; 
        padding: 30px;
        background-color: rgba(255, 255, 255, 0.1); 
        border-radius: 12px;
        box-shadow: 0 4px 50px rgba(0, 0, 0, 0.7); 
        backdrop-filter: blur(5px); 
        position: relative; 
        z-index: 20; 
        margin: 20px auto; /* Centrování kontejneru */
    }
    
    .profile-form h2 { color: #00ff80; text-align: center; margin-bottom: 25px; }
    .profile-form label { display: block; color: white; margin-top: 15px; margin-bottom: 5px; font-weight: bold; }
    .profile-form input, .profile-form select { 
        width: 100%; 
        padding: 10px; 
        margin-bottom: 10px; 
        border: 1px solid #00ff80; 
        border-radius: 6px; 
        background-color: rgba(0, 0, 0, 0.5); 
        color: white; 
        box-sizing: border-box; 
    }

    /* Styly pro Výsledky Kalkulačky */
    .results-container {
        margin-top: 30px; 
        padding-top: 20px;
        border-top: 2px solid #00e673; 
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }
    .result-item {
        text-align: center;
    }
    .result-item h3 {
        font-size: 1.25rem;
        color: white;
        margin-bottom: 5px;
    }
    .result-item p {
        font-size: 2.5rem; /* Větší písmo pro hodnotu */
        font-weight: 900;
        color: #00ff80;
        text-shadow: 0 0 10px rgba(0, 255, 128, 0.5);
    }
    
    .submit-button { 
        width: 100%; 
        padding: 12px; 
        margin-top: 20px; 
        background-color: #00ff80; 
        color: #000000; 
        border: none; 
        border-radius: 6px; 
        cursor: pointer; 
        font-size: 16px; 
        font-weight: bold; 
        transition: background-color 0.3s, transform 0.2s; 
    }
    .submit-button:hover { 
        background-color: #00e673; 
        transform: translateY(-2px);
    }

    /* PŮVODNÍ STYLY - Header */
      header {
        width: 100%;
        padding: 20px 50px;
        display: flex;
        align-items: center;
        position: fixed; 
        top: 0;
        left: 0; 
        z-index: 100; 
        justify-content: space-between;
        background-color: rgba(0, 0, 0, 0.5); 
        backdrop-filter: blur(5px); 
      }
        h1 {
        color: white;
        margin: 0;
        font-size: 50px;
    }
    
    .header-icons a {
        margin-left: 25px; 
        text-decoration: none;
        display: inline-block;
    }

    .header-icons img {
        height: 35px; 
        width: 35px;
        opacity: 0.9;
        transition: opacity 0.2s;
        margin: 5;
    }
    
    .header-icons img:hover {
        opacity: 1;
    }

    @media (max-width: 600px) {
        header {
            padding: 15px 20px;
        }
        h1 {
            font-size: 35px;
        }
        .header-icons a {
            margin-left: 10px;
        }
        .header-icons img {
            height: 30px;
            width: 30px;
        }
    }

    </style>

</head>
<body>
    <!-- PŮVODNÍ POZADÍ -->
    <div class="background-container">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <!-- PŮVODNÍ HEADER - OBNOVENO PŘESNĚ DLE VAŠEHO ZADÁNÍ -->
    <header>
        <h1>PROGRESS</h1>
        <div class="header-icons">
            <a href="tracking.php">
                <img 
                    src="img/statisticsIcon.png" 
                    alt="Tracking"
                >
            </a>
            <a href="uzivatel.php">
                <img 
                    src="img/userIcon.png" 
                    alt="Profil uživatele"
                >
            </a>
            <a href="logout.php">
                <img  src="img/logout.png" 
                    alt="Odhlásit se"
                >
            </a>
            <a href="dashboard.php">
                <img src="img/home.png" alt="Domů">
            </a>
            <a href="dashboard.php">
                <img src="img/home.png" alt="Domů">
            </a>
        </div>
    </header>
    
    <!-- HLAVNÍ OBSAH: Kalkulačka BMR a TDEE -->
    <main class="tracking-layout">
        <div class="profile-form-container">
            <h2>Výpočet bazálního metabolismu</h2>
            <form id="bmrCalculatorForm" class="profile-form">
                <label for="bmrVaha">Váha (kg):</label>
                <input type="number" id="bmrVaha" min="1" step="0.1" required placeholder="Např. 75.5">

                <label for="bmrVyska">Výška (cm):</label>
                <input type="number" id="bmrVyska" min="1" step="1" required placeholder="Např. 180">

                <label for="bmrVek">Věk (roky):</label>
                <input type="number" id="bmrVek" min="1" step="1" required placeholder="Např. 30">
                
                <label for="bmrTuk">Procento Podkožního Tuku (%):</label>
                <input type="number" id="bmrTuk" min="5" max="60" step="0.1" placeholder="Volitelné, např. 15.0">

                <label for="bmrPohlavi">Pohlaví:</label>
                <select id="bmrPohlavi" required>
                    <option value="muz">Muž</option>
                    <option value="zena">Žena</option>
                </select>
                
                <label for="bmrAktivita">Úroveň Aktivity (pro TDEE):</label>
                <select id="bmrAktivita" required>
                    <option value="1.2">Sedavá (Kancelářská práce, minimum pohybu)</option>
                    <option value="1.375">Mírná (Lehké cvičení 1-3x týdně)</option>
                    <option value="1.55">Střední (Střední cvičení 3-5x týdně)</option>
                    <option value="1.725">Vysoká (Těžké cvičení 6-7x týdně)</option>
                    <option value="1.9">Extrémní (Dvojfázový trénink, fyzicky náročná práce)</option>
                </select>
                
                <button type="button" id="vypocitatBMR" class="submit-button">Vypočítat Výdej Kalorií</button>
            </form>
            
            <div class="results-container">
                <div class="result-item">
                    <h3>Bazální Metabolický Výdej (BMR)</h3>
                    <p id="bmrVysledek">-- Kcal</p>
                    <p1>Bazální metabolismus (BMR) je energie, kterou vaše tělo potřebuje k tomu, aby mohlo plnohodnotně fungovat. Je tedy nutné mít vyšší kalorický příjem, než je tato hodnota.</p1>
                </div>
                <div class="result-item">
                    <h3>Celkový Denní Výdej (TDEE)</h3>
                    <p id="tdeeVysledek">-- Kcal</p>
                </div>
            </div>
        </div>
    </main>

    <!-- JS pro BMR a TDEE kalkulačku -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const vypocitatBMRButton = document.getElementById('vypocitatBMR');
            const bmrVysledek = document.getElementById('bmrVysledek');
            const tdeeVysledek = document.getElementById('tdeeVysledek');

            vypocitatBMRButton.addEventListener('click', function() {
                const vaha = parseFloat(document.getElementById('bmrVaha').value);
                const vyska = parseFloat(document.getElementById('bmrVyska').value);
                const vek = parseInt(document.getElementById('bmrVek').value, 10);
                const pohlavi = document.getElementById('bmrPohlavi').value;
                const aktivitaFaktor = parseFloat(document.getElementById('bmrAktivita').value);
                const tukProcento = parseFloat(document.getElementById('bmrTuk').value);
                
                // Základní validace
                if (isNaN(vaha) || isNaN(vyska) || isNaN(vek) || vaha <= 0 || vyska <= 0 || vek <= 0 || isNaN(aktivitaFaktor)) {
                    bmrVysledek.textContent = 'Chyba!';
                    tdeeVysledek.textContent = 'Chyba!';
                    bmrVysledek.style.color = '#ff4d4d'; // error color
                    tdeeVysledek.style.color = '#ff4d4d';
                    return;
                }
                
                let bmr;
                
                // --- 1. Výpočet BMR (Bazální Metabolický Výdej) ---
                if (!isNaN(tukProcento) && tukProcento > 0 && tukProcento < 100) {
                    // Katch-McArdle Formula (pokud je znám % tuku)
                    // BMR = 370 + (21.6 * LBM)
                    // LBM (Lean Body Mass) = váha * (1 - %tuku/100)
                    const LBM = vaha * (1 - (tukProcento / 100));
                    bmr = 370 + (21.6 * LBM);
                } else {
                    // Harris-Benedict Revised Formula (pokud není znám % tuku)
                    if (pohlavi === 'muz') {
                        // Muži: BMR = 66.5 + (13.75 * váha) + (5.003 * výška) - (6.75 * věk)
                        bmr = 66.5 + (13.75 * vaha) + (5.003 * vyska) - (6.75 * vek);
                    } else { // Žena
                        // Ženy: BMR = 655.1 + (9.563 * váha) + (1.850 * výška) - (4.676 * věk)
                        bmr = 655.1 + (9.563 * vaha) + (1.850 * vyska) - (4.676 * vek);
                    }
                }

                // --- 2. Výpočet TDEE (Celkový Denní Energetický Výdej) ---
                const tdee = bmr * aktivitaFaktor;

                // --- Zobrazení výsledků ---
                bmrVysledek.textContent = `${Math.round(bmr)} Kcal`;
                tdeeVysledek.textContent = `${Math.round(tdee)} Kcal`;

                bmrVysledek.style.color = '#00ff80'; 
                tdeeVysledek.style.color = '#00ff80';
            });
        });
    </script>
</body>
</html>