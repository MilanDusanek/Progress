<?php
session_start();
require_once 'db.php';
include 'pozadi.php';

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}
$uzivatel_id = $_SESSION['uzivatel_id'];

$trackingData = [];
$json_tracking_data = '[]';

try {
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
    <title>Sledování Progresu | PROGRESS</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <style>
        .tracking-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 25px;
            padding: 25px;
            max-width: 1300px;
            width: 100%;
            margin: 100px auto 40px;
            box-sizing: border-box;
            position: relative;
            z-index: 10;
        }

        .premium-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .premium-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0) 100%);
            pointer-events: none;
        }

        .premium-card:hover {
            border-color: rgba(0, 255, 128, 0.35);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.05);
        }

        .premium-card h2 {
            color: var(--toxic-green);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 900;
            opacity: 0.9;
            text-shadow: 0 0 10px var(--toxic-glow);
        }

        .tracking-form label {
            display: block;
            color: rgba(255, 255, 255, 0.7);
            margin: 15px 0 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .tracking-form input {
            width: 100%;
            padding: 14px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid #00ff80;
            border-radius: 10px;
            color: white;
            font-size: 0.95rem;
            transition: 0.3s;
            box-sizing: border-box;
            outline: none;
        }

        .tracking-form input:focus {
            border-color: white;
        }

        .tracking-form input[type="number"]::-webkit-outer-spin-button,
        .tracking-form input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .tracking-form input[type="number"] {
            -moz-appearance: textfield;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            margin-top: 30px;
            background: #00ff80;
            color: black;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 0 20px rgba(0, 255, 128, 0.4);
            background-color: #00e673;
        }

        .metric-selector {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .selector-btn {
            padding: 10px 18px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: 0.2s;
        }

        .selector-btn:hover, .selector-btn.active {
            background: rgba(0, 255, 128, 0.1);
            border-color: #00ff80;
            color: #00ff80;
        }

        .chart-wrapper {
            flex-grow: 1;
            min-height: 400px;
            position: relative;
        }

        .no-data-msg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            opacity: 0.5;
        }

        @media (max-width: 1000px) {
            .tracking-layout {
                grid-template-columns: 1fr;
                padding-top: 100px;
                margin-top: 20px;
                width: 100%;
                overflow-x: hidden;
                box-sizing: border-box;
            }
            .premium-card {
                padding: 25px 15px;
                box-sizing: border-box;
            }
        }

        /* Flatpickr Customization */
        .flatpickr-calendar { background: #1a1a1a !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; box-shadow: 0 15px 35px rgba(0,0,0,0.5) !important; }
        .flatpickr-day.selected { background: #00ff80 !important; border-color: #00ff80 !important; color: black !important; }
        .flatpickr-day:hover { background: rgba(0, 255, 128, 0.2) !important; }
    </style>
</head>
<body>

    <main class="tracking-layout">
        <section class="premium-card">
            <h2>Nové měření</h2>
            <form action="uloz_tracking.php" method="POST" class="tracking-form">
                <label>DATUM MĚŘENÍ</label>
                <input type="text" id="date_tracking" name="datum" readonly>

                <label>HMOTNOST (KG)</label>
                <input type="number" name="vaha" step="0.1" min="30" max="200" placeholder="75">

                <label>OBVOD PASU (CM)</label>
                <input type="number" name="pas" step="0.1" min="20" max="200" placeholder="82">

                <label>OBVOD BOKŮ (CM)</label>
                <input type="number" name="boky" step="0.1" min="20" max="200" placeholder="98">

                <label>OBVOD BICEPSU (CM)</label>
                <input type="number" name="biceps" step="0.1" min="10" max="100" placeholder="36.5">

                <button type="submit" class="submit-btn">Uložit progres</button>
            </form>
        </section>

        <section class="premium-card">
            <h2>Tvoje výsledky</h2>
            <div class="metric-selector" id="metric-selector">
                <button class="selector-btn active" data-metric="vaha">Váha</button>
                <button class="selector-btn" data-metric="pas">Pas</button>
                <button class="selector-btn" data-metric="boky">Boky</button>
                <button class="selector-btn" data-metric="biceps">Biceps</button>
            </div>
            <div class="chart-wrapper">
                <canvas id="trackingGraph"></canvas>
                <?php if (empty($trackingData)): ?>
                    <div class="no-data-msg">
                        <img src="img/tracking.png" style="width: 50px; height: 50px; filter: grayscale(1); margin-bottom: 15px; opacity: 0.3;">
                        <p>Zatím jsi nezadal žádná měření.<br>Zapiš si svůj první údaj vlevo!</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/cs.js"></script>
    <script>
        const rawData = <?php echo $json_tracking_data; ?>;
        const ctx = document.getElementById('trackingGraph').getContext('2d');
        let chartInstance = null;

        const metricsConfigs = {
            vaha: { label: 'Váha (kg)', color: '#00ff80' },
            pas: { label: 'Obvod pasu (cm)', color: '#ffcc00' },
            boky: { label: 'Obvod boků (cm)', color: '#00d4ff' },
            biceps: { label: 'Obvod bicepsu (cm)', color: '#ff4d4d' }
        };

        function drawChart(metricKey) {
            const config = metricsConfigs[metricKey];
            const filteredData = rawData.filter(d => d[metricKey] !== null);
            
            const labels = filteredData.map(d => {
                const date = new Date(d.datum);
                return date.toLocaleDateString('cs-CZ', { day: 'numeric', month: 'numeric' });
            });
            const values = filteredData.map(d => parseFloat(d[metricKey]));

            if (chartInstance) chartInstance.destroy();

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: config.label,
                        data: values,
                        borderColor: config.color,
                        backgroundColor: config.color + '22',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointBackgroundColor: config.color,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,0.5)' } },
                        y: { 
                            grid: { color: 'rgba(255,255,255,0.05)' }, 
                            ticks: { color: 'rgba(255,255,255,0.5)' },
                            beginAtZero: false 
                        }
                    }
                }
            });
        }

        document.getElementById('metric-selector').addEventListener('click', (e) => {
            if (e.target.classList.contains('selector-btn')) {
                document.querySelectorAll('.selector-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                drawChart(e.target.dataset.metric);
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            flatpickr("#date_tracking", {
                locale: "cs",
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "j. F Y",
                defaultDate: "today",
                maxDate: "today",
                disableMobile: true
            });
            
            if (rawData.length > 0) drawChart('vaha');

            // AJAX save for tracking form
            const trackingForm = document.querySelector('.tracking-form');
            if (trackingForm) {
                trackingForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(trackingForm);

                    fetch('uloz_tracking.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            if (typeof showToast === 'function') showToast('Progres uložen');
                            // Reset form values but keep date
                            ['vaha','pas','boky','biceps'].forEach(n => {
                                const el = trackingForm.querySelector('[name="' + n + '"]');
                                if (el) el.value = '';
                            });
                            // Reload chart data
                            fetch(location.href)
                            .then(r => r.text())
                            .then(html => {
                                const match = html.match(/const rawData = (\[.*?\]);/s);
                                if (match) {
                                    const newData = JSON.parse(match[1]);
                                    rawData.length = 0;
                                    newData.forEach(d => rawData.push(d));
                                    const activeBtn = document.querySelector('.selector-btn.active');
                                    if (activeBtn) drawChart(activeBtn.dataset.metric);
                                }
                            });
                        } else {
                            if (typeof showToast === 'function') showToast('Chyba při ukládání');
                        }
                    })
                    .catch(() => {
                        if (typeof showToast === 'function') showToast('Chyba připojení');
                    });
                });
            }
        });
    </script>
</body>
</html>