<?php
session_start();

include 'pozadi.php';
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
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Sledování Progresu</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">

    <style>

        /* Stylování inputu, aby ladil k ostatním */
.flatpickr-input {
    width: 100%;
    padding: 12px;
    background: rgba(0, 0, 0, 0.5) !important;
    border: 1px solid #00ff80 !important;
    border-radius: 6px;
    color: white !important;
    box-sizing: border-box;
}

/* Úprava barev kalendáře na tvou zelenou */
.flatpickr-day.selected {
    background: #00ff80 !important;
    border-color: #00ff80 !important;
    color: black !important;
}

.flatpickr-day.today {
    border-color: #00ff80 !important;
    color: #00ff80 !important;
}

.flatpickr-calendar {
    background: #1a1a1a !important;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
}
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
        h2{
            color: white;
        }

        

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


        .profile-form-container {
            display: flex;
            flex-direction: column;
            flex-grow: 1; 
            
            max-width: 100%; 
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.1); 
            border-radius: 25px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5); 
            backdrop-filter: blur(5px); 
            position: relative; 
            z-index: 20; 
            margin: 0; 
        }
        
        .profile-form h2 { color: #00ff80; text-align: center; margin-bottom: 25px; }
        .profile-form label { display: flex; color: white; margin-top: 15px; margin-bottom: 5px; font-weight: bold; }
        .profile-form input, .profile-form select { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #00ff80; border-radius: 6px; background-color: rgba(0, 0, 0, 0.5); color: white; box-sizing: border-box; }
        .submit-button { width: 100%; padding: 12px; margin-top: 20px; background-color: #00ff80; color: #000000; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; transition: background-color 0.3s; }
        .submit-button:hover { background-color: #00e673; }
        
        .tracking-layout {
            display: flex;
            gap: 30px; 
            padding: 30px 20px;
            max-width: 1300px; 
            width: 100%; 
            margin: 0 auto; 
            box-sizing: border-box;
            position: relative;
            z-index: 10;
            margin-top: 80px; 
            margin-bottom: 20px; 
            flex-grow: 0; 
            flex-shrink: 0; 
        }

        .column {
            flex: 1; 
            min-width: 300px; 
            display: flex;
            flex-direction: column;
        }

        .column h2{
            text-align: center;
        }
        
        .chart-container {
            flex-grow: 1; 
            position: relative; 
            margin-top: 20px;
        }
        
        .selector-btn {
            padding: 8px 15px;
            margin: 5px;
            border: 1px solid #00ff80;
            border-radius: 6px;
            background-color: transparent;
            color: #00ff80;
            cursor: pointer;
            transition: all 0.2s;
        }

        .selector-btn:hover,
        .selector-btn.active {
            background-color: #00ff80;
            color: #000000;
            font-weight: bold;
        }
        
        @media (max-width: 900px) {
            .tracking-layout {
                flex-direction: column; 
                padding: 10px;
                margin-top: 100px; 
            }
        }


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
        

    </style>
</head>
<body>
    
    <main class="tracking-layout">
        
        <div class="column">
            <div class="profile-form-container">
                <h2>NOVÉ MĚŘENÍ</h2>

                <form action="uloz_tracking.php" method="POST" class="profile-form">
                    
                   <label for="date_tracking">Datum měření</label>
                    <input type="text" id="date_tracking" name="datum" placeholder="Vyberte datum.." >
                    
                    <label for="vaha">Hmotnost (kg)</label>
                    <input type="number" id="vaha" name="vaha" step="0.1"  placeholder="Např. 75.5" min="35"> 
                    
                    <label for="pas">Obvod pasu (cm)</label>
                    <input type="number" id="pas" name="pas" step="0.1" placeholder="Např. 85.0" min="35">
                    
                    <label for="boky">Obvod boků (cm)</label>
                    <input type="number" id="boky" name="boky" step="0.1"  placeholder="Např. 95.0" min="35">

                    <label for="biceps">Obvod bicepsu (cm)</label>
                    <input type="number" id="biceps" name="biceps" step="0.1"  placeholder="Např. 35.0" min="35">
                    
                    <button type="submit" class="submit-button">Uložit měření</button>
                </form>
            </div>
        </div>
        
        <div class="column">
            <div class="profile-form-container">
                <h2>VÝSLEDKY</h2>
                
                <div id="metric-selector" style="text-align: center; margin-bottom: 20px;">
                    <button class="selector-btn active" data-metric="vaha">Váha (kg)</button>
                    <button class="selector-btn" data-metric="pas">Pas (cm)</button>
                    <button class="selector-btn" data-metric="boky">Boky (cm)</button>
                    <button class="selector-btn" data-metric="biceps">Biceps (cm)</button>
                </div>
                
                <div class="chart-container">
                    <canvas id="trackingGraph"></canvas>
                </div>
            </div>
        </div>
        
    </main>
    
    <script>
    const rawData = <?php echo $json_tracking_data; ?>;
    
    const ctx = document.getElementById('trackingGraph').getContext('2d');
    let chartInstance = null; 

    const metrics = {
        vaha: { label: 'Hmotnost (kg)', unit: 'kg', color: '#00ff80' },
        pas: { label: 'Obvod Pasu (cm)', unit: 'cm', color: '#ffcc00' },
        boky: { label: 'Obvod Boků (cm)', unit: 'cm', color: '#00ccff' },
        biceps: { label: 'Obvod Bicepsu (cm)', unit: 'cm', color: '#ff6666' }
    };

    function drawChart(metricKey) {
        const metric = metrics[metricKey];
        

        const filteredData = rawData.filter(d => d[metricKey] !== null && d[metricKey] !== undefined);
        
        const labels = filteredData.map(d => d.datum);
        const dataValues = filteredData.map(d => parseFloat(d[metricKey])); 

        if (chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels, 
                datasets: [{
                    label: metric.label,
                    data: dataValues, 
                    borderColor: metric.color,
                    backgroundColor: metric.color + '33', 
                    fill: true,
                    tension: 0.3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, 
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Datum', color: 'white' },
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    y: {
                        title: { display: true, text: metric.label, color: 'white' },
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        beginAtZero: false 
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.profile-form');

    form.addEventListener('submit', function(e) {
     
        const inputs = form.querySelectorAll('input[type="number"]');
        let asponJednoVyplneno = false;

        inputs.forEach(input => {
            if (input.value.trim() !== "") {
                asponJednoVyplneno = true;
            }
        });

        if (!asponJednoVyplneno) {
            e.preventDefault(); 
            
            triggerToast("Musíš vyplnit alespoň jeden údaj!", "error");
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
});

    document.getElementById('metric-selector').addEventListener('click', (e) => {
        if (e.target.tagName === 'BUTTON') {
            const metricKey = e.target.getAttribute('data-metric');
            
            document.querySelectorAll('.selector-btn').forEach(btn => btn.classList.remove('active'));
            e.target.classList.add('active');
            
            drawChart(metricKey);
        }
    });

    if (rawData.length > 0) {
        drawChart('vaha');
    } else {
        drawChart('vaha');
    }

    
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/cs.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr("#date_tracking", {
            locale: "cs",
            dateFormat: "Y-m-d",    
            altInput: true,         
            altFormat: "j. F Y",   
            defaultDate: "today",
            maxDate: "today",      
            disableMobile: "true"
        });
    });
</script>
</body>
</html>