<?php
session_start();

require_once 'db.php';
include 'pozadi.php';

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: index..php");
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];

$stmt_vysledky = $pdo->prepare("SELECT denni_prijem, bilkoviny, sacharidy, tuky FROM kalkulacka_vysledky WHERE uzivatel_id = :id ORDER BY id DESC LIMIT 1");
$stmt_vysledky->execute(['id' => $uzivatel_id]);
$vysledky = $stmt_vysledky->fetch(PDO::FETCH_ASSOC);

if (!$vysledky) {
    $vysledky = ['denni_prijem' => 0, 'bilkoviny' => 0, 'sacharidy' => 0, 'tuky' => 0];
}

$kcal = $vysledky['denni_prijem'];
$b = $vysledky['bilkoviny'];
$s = $vysledky['sacharidy'];
$t = $vysledky['tuky'];
?>
<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Progress | Jídelníček</title>
    <style>
        .layout-container {
            padding-top: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            position: relative;
            z-index: 10;
        }

        .macros-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 95%;
            max-width: 1000px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 100px;
            padding: 10px 30px;
            margin-bottom: 40px;
            position: relative;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }

        .macros-wrapper::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 15%;
            width: 70%;
            height: 1px;
            background: linear-gradient(90deg, transparent, #00ff80, transparent);
            filter: blur(1px);
        }

        .macro-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            flex: 1;
        }

        .macro-item:not(:last-child)::after {
            content: '';
            position: absolute;
            right: 0;
            top: 20%;
            height: 60%;
            width: 1px;
            background: linear-gradient(to bottom, transparent, rgba(0, 255, 128, 0.3), transparent);
        }

        .macro-item .label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #00ff80;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .macro-item .value-group {
            display: flex;
            align-items: baseline;
            gap: 4px;
        }

        .macro-item .value {
            font-size: 1.5rem;
            font-weight: 900;
            color: white;
            font-family: 'Inter', sans-serif;
        }

        .macro-item .unit {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 400;
        }

        @media (max-width: 768px) {
            .macros-wrapper {
                border-radius: 25px;
                flex-wrap: wrap;
                padding: 20px;
            }

            .macro-item {
                flex: 50%;
                padding: 10px 0;
            }

            .macro-item:nth-child(even)::after {
                display: none;
            }
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            padding: 40px 20px;
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 25px;
            text-align: left;
            transition: transform 0.3s ease, border-color 0.3s ease;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            border-color: #00ff80;
        }

        .card h2 {
            color: #00ff80;
            margin: 0 0 15px 0;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .card-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .dashboard-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            padding: 40px 20px;
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
            transition: all 0.5s ease-in-out;
        }

        @media (max-width: 768px) {
            .layout-container {
                padding-top: 100px;
                width: 100%;
                overflow-x: hidden;
                box-sizing: border-box;
            }
            .dashboard-grid {
                padding: 10px 15px 40px;
                gap: 15px;
            }
            .macros-wrapper {
                margin: 0 15px 20px 15px;
                width: calc(100% - 30px);
                padding: 15px;
                box-sizing: border-box;
                gap: 15px;
                flex-wrap: wrap;
            }
        }

        .card {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            color: white;
            cursor: pointer;
            overflow: hidden;
            min-width: 150px;
            display: flex;
            flex-direction: column;
        }

        .card.expanded {
            flex: 3;
            border-color: #00ff80;
            cursor: default;
        }

        .dashboard-grid.has-expanded .card:not(.expanded) {
            flex: 0.5;
            opacity: 0.5;
            filter: blur(2px);
        }

        .dashboard-grid.has-expanded .card:not(.expanded) p,
        .dashboard-grid.has-expanded .card:not(.expanded) .btn-link,
        .dashboard-grid.has-expanded .card:not(.expanded) .add-btn {
            display: none;
        }

        .food-selector {
            display: none;
            opacity: 0;
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            transition: opacity 0.5s ease;
        }

        .card.expanded .food-selector {
            display: block;
            opacity: 1;
        }

        .btn-link {
            display: inline-block;
            margin-top: 15px;
            color: #00ff80;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.8rem;
            letter-spacing: 1px;
            cursor: pointer;
            text-transform: uppercase;
        }

        .add-btn {
            background: none;
            border: 2px solid #00ff80;
            color: #00ff80;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }

        .add-btn:hover {
            background: #00ff80;
            color: black;
        }

        .food-selector {
            display: none;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            max-height: 300px;
            overflow-y: auto;
        }

        .food-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.03);
            margin-bottom: 10px;
            padding: 12px 15px;
            border-radius: 12px;
            transition: 0.2s;
        }

        .heart-icon {
            cursor: pointer;
            transition: all 0.3s;
            color: rgba(255, 255, 255, 0.2);
            font-size: 1.2rem;
        }

        .heart-icon.active {
            color: #ff4d4d;
            filter: drop-shadow(0 0 5px rgba(255, 77, 77, 0.5));
        }

        .generate-btn {
            margin: 30px 0 80px;
            padding: 15px 50px;
            background: #00ff80;
            color: black;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 1px;
        }

        .generate-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(0, 255, 128, 0.4);
        }

        .food-selector::-webkit-scrollbar {
            width: 5px;
        }

        .food-selector::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
        }

        .food-selector::-webkit-scrollbar-thumb {
            background: #00ff80;
            border-radius: 10px;
        }

        .card.expanded {
            flex: 3;
            border-color: #00ff80;
            cursor: default;
            box-shadow: 0 0 30px rgba(0, 255, 128, 0.2);
            z-index: 20;
        }
    </style>
</head>

<body>

    <main class="layout-container">
        <div class="macros-wrapper">
            <div class="macro-item">
                <span class="label">Energie</span>
                <div class="value-group">
                    <span class="value"><?= round($kcal) ?></span>
                    <span class="unit">kcal</span>
                </div>
            </div>

            <div class="macro-item">
                <span class="label">Bílkoviny</span>
                <div class="value-group">
                    <span class="value"><?= round($b) ?></span>
                    <span class="unit">g</span>
                </div>
            </div>

            <div class="macro-item">
                <span class="label">Sacharidy</span>
                <div class="value-group">
                    <span class="value"><?= round($s) ?></span>
                    <span class="unit">g</span>
                </div>
            </div>

            <div class="macro-item">
                <span class="label">Tuky</span>
                <div class="value-group">
                    <span class="value"><?= round($t) ?></span>
                    <span class="unit">g</span>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <?php
            $kategorie = ['snidane' => 'Snídaně', 'obed' => 'Oběd', 'vecere' => 'Večeře'];
            foreach ($kategorie as $id => $nazev):
                ?>
                <div class="card" id="card-<?= $id ?>">
                    <h2>OBLÍBENÉ SUROVINY</h2>
                    <div class="card-value"><?= $nazev ?></div>
                    <p style="opacity: 0.5; font-size: 0.9rem; margin-bottom: 20px;">Vyberte suroviny, které máte rádi.</p>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="btn-link" onclick="toggleFoodSelector('<?= $id ?>')">Upravit seznam</span>
                        <button class="add-btn" onclick="toggleFoodSelector('<?= $id ?>')">+</button>
                    </div>

                    <div id="selector-<?= $id ?>" class="food-selector">
                        <div id="list-<?= $id ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button id="generateBtn" class="generate-btn">VYGENEROVAT JÍDELNÍČEK</button>

        <div id="mealResult" style="width: 100%; display: none;">
            <div id="breakfastContent" class="dashboard-grid" style="padding-top: 0; padding-bottom: 20px;"></div>
            
            <div style="max-width: 1000px; width: 95%; margin: 0 auto 40px; text-align: center; color: rgba(255,255,255,0.5); font-size: 0.85rem; line-height: 1.5; padding: 15px 20px;">
                <i class="fa-solid fa-circle-info" style="color: rgba(255,255,255,0.5); margin-right: 5px;"></i>
                Generátor skládá jídla striktně matematicky na základě Vašich denních cílů. 
                Množství surovin (v gramech) se může lišit od běžně konzumovaných porcí za účelem absolutní makronutriční přesnosti.
                <strong>Jídelníček berte pouze jako orientační vzor!</strong>
            </div>
        </div>
    </main>

    <script>
        function toggleFoodSelector(typ) {
            const grid = document.querySelector('.dashboard-grid');
            const targetCard = document.getElementById('card-' + typ);
            const list = document.getElementById('list-' + typ);

            if (targetCard.classList.contains('expanded')) {
                targetCard.classList.remove('expanded');
                grid.classList.remove('has-expanded');
                return;
            }

            document.querySelectorAll('.card').forEach(c => c.classList.remove('expanded'));
            targetCard.classList.add('expanded');
            grid.classList.add('has-expanded');

           
            setTimeout(() => {
                const elementRect = targetCard.getBoundingClientRect();
                const absoluteElementTop = elementRect.top + window.pageYOffset;
                const middle = absoluteElementTop - (window.innerHeight / 2) + (targetCard.offsetHeight / 2);

                window.scrollTo({
                    top: middle,
                    behavior: 'smooth'
                });
            }, 300); 

            list.innerHTML = '<p style="text-align:center; opacity:0.3; padding: 20px;">Načítám...</p>';
            fetch(`ziskat_vsechny_potraviny.php?typ=${typ}`)
                .then(res => res.json())
                .then(foods => {
                    list.innerHTML = '';
                    let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">';
                    foods.forEach(food => {
                        const icon = food.is_favorite ? 'fa-solid active' : 'fa-regular';
                        html += `
                <div class="food-item">
                    <span style="font-size:0.85rem;">${food.nazev}</span>
                    <i class="fa-heart ${icon} heart-icon" onclick="toggleFavorite(${food.id}, '${typ}', this)"></i>
                </div>`;
                    });
                    html += '</div>';
                    list.innerHTML = html;
                });
        }

        document.addEventListener('click', function(event) {
            const grid = document.querySelector('.dashboard-grid');
            const expandedCard = document.querySelector('.card.expanded');
            
            if (expandedCard && !expandedCard.contains(event.target)) {
                expandedCard.classList.remove('expanded');
                grid.classList.remove('has-expanded');
            }
        });

        async function toggleFavorite(potravinaId, typJidla, element) {
            try {
                const response = await fetch('oznacit_oblibene.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ potravina_id: potravinaId, typ_jidla: typJidla })
                });

                const data = await response.json();

                if (data.status === 'added') {
                    element.classList.remove('fa-regular');
                    element.classList.add('fa-solid', 'active');
                } else {
                    element.classList.remove('fa-solid', 'active');
                    element.classList.add('fa-regular');
                }
            } catch (error) {
                console.error('Chyba:', error);
            }
        }

        document.getElementById('generateBtn').addEventListener('click', function () {
            const btn = this;
            const resultDiv = document.getElementById('mealResult');
            const content = document.getElementById('breakfastContent');

            btn.innerText = '...';

            fetch('vygenerovat_jidla.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        btn.innerText = 'VYGENEROVAT JÍDELNÍČEK';
                        return;
                    }

                    content.innerHTML = '';

                    const chody = [
                        { id_nazev: 'snidane', nazev: 'Snídaně', info: data.snidane },
                        { id_nazev: 'obed', nazev: 'Oběd', info: data.obed },
                        { id_nazev: 'vecere', nazev: 'Večeře', info: data.vecere }
                    ];

                    chody.forEach(chod => {
                        let surovinyHTML = '';
                        chod.info.suroviny_detaily.forEach(sur => {
                            const iconClass = sur.is_favorite ? 'fa-solid active' : 'fa-regular';

                            surovinyHTML += `
            <div class="food-item" style="background: rgba(255,255,255,0.03); margin-bottom: 8px;">
                <span style="color: white; font-size: 0.9rem;">
                    <strong style="color: #00ff80;">${sur.gramy}g</strong> ${sur.nazev}
                </span>
                <i class="fa-heart ${iconClass} heart-icon" 
                   onclick="toggleFavorite(${sur.id}, '${chod.id_nazev}', this)"></i>
            </div>
        `;
                        });

                        content.innerHTML += `
        <div class="card" style="border-top: 3px solid #00ff80; cursor: default; flex: 1; min-width: 250px;">
            <h2>VÝSLEDEK</h2>
            <div class="card-value">${chod.nazev}</div>
            <div style="margin-bottom: 20px; font-weight: bold; color: rgba(255,255,255,0.7); font-size: 1.1rem;">
                ${chod.info.kcal} <span style="font-size: 0.7rem; opacity: 0.5;">kcal</span>
            </div>
            
            <div class="food-list-container">
                ${surovinyHTML}
            </div>

            <div style="display: flex; gap: 15px; opacity: 0.5; font-size: 0.75rem; margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                <span>B: ${chod.info.b}g</span>
                <span>S: ${chod.info.s}g</span>
                <span>T: ${chod.info.t}g</span>
            </div>
        </div>
    `;
                    });

                    resultDiv.style.display = 'block';
                    resultDiv.scrollIntoView({ behavior: 'smooth' });
                    btn.innerText = 'Zkusit jinou kombinaci!';
                });
        });
    </script>

</body>

</html>