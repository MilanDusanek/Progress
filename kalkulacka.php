<!DOCTYPE html>
<html lang="en">

<?php
session_start();

require_once 'db.php';
include 'pozadi.php';

$uzivatel_id = $_SESSION['uzivatel_id'] ?? null;
$userData = null;

if ($uzivatel_id) {
    $stmt = $pdo->prepare("SELECT vaha, vyska, vek, pohlavi FROM profily WHERE uzivatel_id = :id LIMIT 1");
    $stmt->execute(['id' => $uzivatel_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PROGRESS - BMR Kalkulačka</title>
</head>

<style>
    .tracking-layout {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding-top: 130px;
        width: 100%;
        z-index: 10;
        padding-bottom: 50px;
        box-sizing: border-box;
    }

    .profile-form-container {
        max-width: 600px;
        width: 90%;
        padding: 25px;
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        box-shadow: 0 4px 50px rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(5px);
        position: relative;
    }

    .profile-form-container h2 {
        color: white;
        text-align: center;
        margin-bottom: 20px;
        font-size: 1.2rem;
        letter-spacing: 1px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px 20px;
    }

    .full-width {
        grid-column: span 2;
    }

    .profile-form label {
        display: block;
        color: white;
        margin: 5px;
        font-weight: bold;
        font-size: 0.8rem;
        display: flex;
    }

    .profile-form input,
    .profile-form select {
        width: 100%;
        padding: 10px;
        border: 1px solid #00ff80;
        border-radius: 10px;
        background-color: rgba(0, 0, 0, 0.5);
        color: white;
        box-sizing: border-box;
        transition: all 0.3s ease;
        outline: none;
    }

    .profile-form input:focus,
    .profile-form select:focus {
        border-color: white;
    }

    .results-container {
        display: none;
        margin-top: 30px;
        padding-top: 25px;
        border-top: 2px solid #00e673;
        width: 100%;
        justify-content: space-around;
        gap: 20px;
    }

    .result-item {
        text-align: center;
        color: white;
        flex: 1;
    }

    .result-item h3 {
        font-size: 1rem;
        color: #00ff80;
        margin-bottom: 5px;
    }

    .result-item p {
        font-size: 1.5rem;
        font-weight: 900;
        margin: 5px 0;
    }

    .result-item p1 {
        font-size: 0.7rem;
        opacity: 0.7;
    }

    .submit-button {
        width: 100%;
        padding: 15px;
        margin-top: 20px;
        background-color: #00ff80;
        color: #000000;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: 1px;
        transition: all 0.3s;
        text-transform: uppercase;
    }

    .submit-button:hover { 
        background-color: #00e673; 
        transform: scale(1.02);
        box-shadow: 0 0 20px rgba(0, 255, 128, 0.4);
    }

    #imageOverlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    #imageOverlay img {
        max-width: 90%;
        max-height: 80%;
        border: 2px solid #ffffff;
        border-radius: 10px;
    }

    .close-btn {
        position: absolute;
        top: 30px;
        right: 40px;
        color: #ffffff;
        font-size: 60px;
        cursor: pointer;
    }

    /* --- MOBILE RESPONSIVE --- */
    @media (max-width: 600px) {
        .tracking-layout {
            padding-top: 100px;
            width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
        }
        
        .profile-form-container {
            width: calc(100% - 30px);
            margin: 0 auto;
            padding: 25px 20px;
            box-sizing: border-box;
        }
        
        .profile-form-container h2 {
            font-size: 1rem;
        }

        .form-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .full-width {
            grid-column: span 1;
        }

        .results-container {
            flex-direction: column;
            gap: 15px;
        }
        
        .result-item p {
            font-size: 1.25rem;
        }
        
        .gender-selection {
            gap: 20px;
        }
        
        .custom-select-trigger span {
            font-size: 0.8rem;
        }
        
        .goal-btn {
            font-size: 0.70rem;
            padding: 10px;
        }
    }

    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    input[type="number"] {
        -moz-appearance: textfield;
    }

    .goal-btn {
        background: transparent !important;
        border: 2px solid #00ff80 !important;
        color: #00ff80 !important;
        transition: all 0.3s ease;
        border-radius: 25px;
    }

    .goal-btn.active {
        background-color: #00ff80 !important;
        color: #000000 !important;
    }

    .goal-btn:hover:not(.active) {
        background-color: rgba(0, 255, 128, 0.1) !important;
    }

    #saveSection {
        display: none;
        width: 100%;
        margin-top: 30px;
        text-align: center;
        border-top: 1px dashed rgba(0, 255, 128, 0.3);
        padding-top: 20px;
    }

    .gender-selection {
        display: flex;
        justify-content: center;
        gap: 40px;
        margin-bottom: 10px;
    }

    .gender-item {
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: all 0.3s ease;
    }

    .gender-circle {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        border: 2px solid white;
        display: flex;
        justify-content: center;
        align-items: center;
        background: rgba(255, 255, 255, 0.05);
        transition: all 0.3s ease;
        margin-bottom: 8px;
    }

    .gender-circle img {
        width: 40px;
        height: 40px;
        opacity: 0.8;
        transition: all 0.3s ease;
    }

    .gender-item span {
        font-size: 0.75rem;
        font-weight: bold;
        color: white;
        letter-spacing: 1px;
    }

    .gender-item.active .gender-circle {
        border-color: #00ff80;
        background: rgba(0, 255, 128, 0.1);
        box-shadow: 0 0 15px rgba(0, 255, 128, 0.3);
    }

    .gender-item.active .gender-circle img {
        opacity: 1;
        transform: scale(1.1);
    }

    .gender-item.active span {
        color: #00ff80;
    }

    .gender-item:hover .gender-circle {
        border-color: #00ff80;
    }


    .custom-select-wrapper {
        position: relative;
        width: 100%;
        user-select: none;
    }

    .custom-select-trigger {
        padding: 12px 15px;
        border: 1px solid #00ff80;
        border-radius: 10px;
        background: rgba(0, 0, 0, 0.6);
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        transition: all 0.3s;
    }

    .custom-select-trigger:hover {
        border: 1px solid white;
    }

    .custom-options {
        position: absolute;
        top: calc(100% + 5px);
        left: 0;
        right: 0;
        background: rgba(15, 15, 15, 0.95);
        border: 1px solid #00ff80;
        border-radius: 10px;
        display: none;
        /* Schované */
        z-index: 1000;
        overflow: hidden;
        backdrop-filter: blur(10px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8);
    }

    .custom-options.show {
        display: block;
        animation: slideIn 0.2s ease-out;
    }

    .custom-option {
        padding: 12px 20px;
        color: white;
        cursor: pointer;
        text-align: left;
        transition: all 0.2s;
        border-bottom: 1px solid rgba(0, 255, 128, 0.1);
    }

    .custom-option:last-child {
        border-bottom: none;
    }

    .custom-option:hover {
        background: rgba(0, 255, 128, 0.2);
        color: #00ff80;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-out {
        animation: fadeOut 0.5s ease forwards;
    }

    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: translateY(0);
        }

        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }
</style>

<body>
    <div id="imageOverlay">
        <span class="close-btn" id="closeHelp">&times;</span>
        <img src="img/procentotuku.png" alt="Nápověda k procentu tuku">
    </div>
    <main class="tracking-layout">
        <div class="profile-form-container">
            <h2>VÝPOČET METABOLISMU</h2>

            <form id="bmrCalculatorForm" class="profile-form">
                <div class="form-grid">
                    <div class="input-group full-width">
                        <label style="justify-content: center; margin-bottom: 15px;">POHLAVÍ *</label>
                        <div class="gender-selection">
                            <input type="hidden" id="bmrPohlavi"
                                value="<?= htmlspecialchars($userData['pohlavi'] ?? 'muz') ?>">

                            <div class="gender-item <?= ($userData['pohlavi'] ?? 'muz') === 'muz' ? 'active' : '' ?>"
                                onclick="selectGender('muz')" id="gender-muz">
                                <div class="gender-circle">
                                    <img src="img/muz.png" alt="Muž">
                                </div>
                                <span>MUŽ</span>
                            </div>

                            <div class="gender-item <?= ($userData['pohlavi'] ?? '') === 'zena' ? 'active' : '' ?>"
                                onclick="selectGender('zena')" id="gender-zena">
                                <div class="gender-circle">
                                    <img src="img/zena.png" alt="Žena">
                                </div>
                                <span>ŽENA</span>
                            </div>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="bmrVaha">AKTUÁLNÍ VÁHA (KG) *</label>
                        <input type="number" id="bmrVaha" step="0.1" required min="30" max="200"
                            value="<?= htmlspecialchars($userData['vaha'] ?? '') ?>" placeholder="80"
                            oninput="if(this.value > 200) this.value = 200;"
                            onblur="if(this.value < 30 && this.value !== '') this.value = 30;">
                    </div>

                    <div class="input-group">
                        <label for="bmrVyska">VÝŠKA (CM) *</label>
                        <input type="number" id="bmrVyska" min="130" max="220"
                            value="<?= htmlspecialchars($userData['vyska'] ?? '') ?>" placeholder="180" required
                            oninput="if(this.value > 220) this.value = 220;"
                            onblur="if(this.value < 130 && this.value !== '') this.value = 130;">
                    </div>

                    <div class="input-group">
                        <label for="bmrVek">VĚK (LET) *</label>
                        <input type="number" id="bmrVek" min="15" max="99"
                            value="<?= htmlspecialchars($userData['vek'] ?? '') ?>" placeholder="20" required
                            oninput="if(this.value > 99) this.value = 99;"
                            onblur="if(this.value < 15 && this.value !== '') this.value = 15;">
                    </div>

                    <div class="input-group">
                        <label for="bmrTuk">TUK (%) *</label>
                        <input type="number" id="bmrTuk" min="5" max="45" step="0.1" placeholder="%" required>

                        <div style="display: flex; justify-content: flex-end; margin-top: 5px;">
                            <div style="display: flex; align-items: center; gap: 5px; cursor: pointer;" id="openHelp">
                                <span
                                    style="font-size: 0.7rem; color: white; font-weight: bold; letter-spacing: 0.5px;">JAKÉ
                                    ČÍSLO ZVOLIT?</span>

                            </div>
                        </div>
                    </div>

                    <div class="full-width">
                        <label>ÚROVEŇ AKTIVITY: *</label>
                        <div class="custom-select-wrapper">
                            <input type="hidden" id="bmrAktivita" value="1.2">

                            <div class="custom-select-trigger" onclick="toggleDropdown()">
                                <span id="selected-text">Sedavá (minimum pohybu)</span>
                                <img src="img/sipkadolu.png" style="height: 12px; filter: hue-rotate(90deg);">
                            </div>

                            <div class="custom-options" id="dropdown-options">
                                <div class="custom-option" onclick="setActivity('1.2', 'Sedavá (minimum pohybu)')">
                                    Sedavá (minimum pohybu)</div>
                                <div class="custom-option"
                                    onclick="setActivity('1.375', 'Mírná (lehký trénink 1-3x týdně)')">Mírná (lehký
                                    trénink 1-3x týdně)</div>
                                <div class="custom-option"
                                    onclick="setActivity('1.55', 'Střední (trénink 3-5x týdně)')">Střední (trénink 3-5x
                                    týdně)</div>
                                <div class="custom-option"
                                    onclick="setActivity('1.725', 'Vysoká (intenzivní trénink každý den)')">Vysoká
                                    (intenzivní trénink každý den)</div>
                                <div class="custom-option"
                                    onclick="setActivity('1.9', 'Extrémní (profesionální sportovec)')">Extrémní
                                    (profesionální sportovec)</div>
                            </div>
                        </div>
                    </div>

                    <div class="full-width">
                        <button type="submit" id="vypocitatBMR" class="submit-button"> VYPOČÍTAT! </button>
                    </div>
                </div>
            </form>

            <div id="resultsWrapper" class="results-container" style="display:none; flex-direction:column;">
                <div class="result-item">
                    <h3 id="bmrTitle">Vaše BMR</h3>
                    <p id="bmrVysledek">-- Kcal</p>
                    <p1>Bazální metabolismus (BMR) je energie, kterou vaše tělo potřebuje k plnohodnotnému fungování.
                    </p1>
                </div>

                <h2 style="color:white; text-align:center; margin-top:20px;">JAKÝ JE VÁŠ CÍL?</h2>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="button" class="submit-button goal-btn" data-goal="hubnuti">REDUKOVAT <br>
                        HMOTNOST</button>
                    <button type="button" class="submit-button goal-btn" data-goal="udrzba">UDRŽET <br>
                        HMOTNOST</button>
                    <button type="button" class="submit-button goal-btn" data-goal="nabirani">NABRAT <br>
                        HMOTNOST</button>
                </div>
            </div>

            <div id="macrosWrapper" class="results-container" style="display:none;">
                <div class="result-item">
                    <h3>CELKOVÝ PŘÍJEM</h3>
                    <p id="finalKcal" style="font-size: 1.5rem;">--</p>
                    <p1>kcal/den</p1>
                </div>
                <div class="result-item">
                    <h3>BÍLKOVINY</h3>
                    <p id="finalProteins" style="font-size: 1.5rem;">--</p>
                    <p1>gramů</p1>
                </div>
                <div class="result-item">
                    <h3>SACHARIDY</h3>
                    <p id="finalCarbs" style="font-size: 1.5rem;">--</p>
                    <p1>gramů</p1>
                </div>
                <div class="result-item">
                    <h3>TUKY</h3>
                    <p id="finalFats" style="font-size: 1.5rem;">--</p>
                    <p1>gramů</1>
                </div>
            </div>

            <div id="saveSection">
                <button type="button" id="saveToDb" class="submit-button"
                    style="background: transparent; border: 2px solid #00ff80; color: #00ff80;">
                    ULOŽIT VÝSLEDKY
                </button>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const bmrForm = document.getElementById('bmrCalculatorForm');
            const resultsWrapper = document.getElementById('resultsWrapper');
            const macrosWrapper = document.getElementById('macrosWrapper');
            const saveSection = document.getElementById('saveSection');
            const saveBtn = document.getElementById('saveToDb');

            let state = { bmr: 0, tdee: 0, weight: 0, lbm: 0, activity: 0, goal: '', gender: '' };

            // --- 1. KLIKNUTÍ NA VYPOČÍTAT (ROZBALENÍ BMR) ---
            bmrForm.addEventListener('submit', function (event) {
                event.preventDefault();

                state.weight = parseFloat(document.getElementById('bmrVaha').value);
                const height = parseFloat(document.getElementById('bmrVyska').value);
                const age = parseFloat(document.getElementById('bmrVek').value);
                const tuk = parseFloat(document.getElementById('bmrTuk').value);
                state.activity = parseFloat(document.getElementById('bmrAktivita').value);
                state.gender = document.getElementById('bmrPohlavi').value;

                // Výpočet čisté tělesné hmoty bez tuku (Lean Body Mass - LBM)
                state.lbm = state.weight * (1 - (tuk / 100));

                // 1. Mifflin-St Jeor vzorec
                let bmrMifflin = (10 * state.weight) + (6.25 * height) - (5 * age);
                bmrMifflin += (state.gender === 'muz') ? 5 : -161;

                // 2. Katch-McArdle vzorec
                const bmrKatch = 370 + (21.6 * state.lbm);

                // Průměr obou vzorců pro nejpřesnější možný výsledek (tlumí nepřesnosti v odhadu tuku)
                state.bmr = (bmrMifflin + bmrKatch) / 2;
                state.tdee = state.bmr * state.activity;

                document.getElementById('bmrVysledek').textContent = Math.round(state.bmr) + " Kcal";

                // ZOBRAZENÍ PRVNÍ ČÁSTI
                resultsWrapper.style.display = 'flex';
                resultsWrapper.scrollIntoView({ behavior: 'smooth' });
            });

            // --- 2. VÝBĚR CÍLE (ROZBALENÍ MAKER) ---
            document.querySelectorAll('.goal-btn').forEach(button => {
                button.addEventListener('click', function () {
                    document.querySelectorAll('.goal-btn').forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    state.goal = this.getAttribute('data-goal');
                    let finalKcal = state.tdee;
                    let proteinPerLbm = 2.2;

                    // Relativně bezpečný minimální příjem pro hubnutí
                    const minKcal = state.gender === 'muz' ? 1500 : 1200;

                    if (state.goal === 'hubnuti') {
                        // Hubnutí: deficit 20%, ale nenecháme padnout pod minimum
                        finalKcal = Math.max(state.tdee * 0.80, minKcal);
                        proteinPerLbm = 2.7; // U hubnutí vyšší bílkoviny pro ochranu svalů v deficitu
                    } else if (state.goal === 'nabirani') {
                        // Nabírání: mírný přebytek cca +300kcal (čistší nabírání než paušální procento)
                        finalKcal = state.tdee + 300; 
                        proteinPerLbm = 2.2;
                    } else {
                        // Udržování
                        finalKcal = state.tdee;
                        proteinPerLbm = 2.2;
                    }

                    // Výpočet maker založený na LBM (čisté svalové hmotě bez tuku) 
                    // => mnohem přesnější i pro lidi s vyšším procentem tělesného tuku (nedostanou 300g bílkovin)
                    let p = state.lbm * proteinPerLbm;
                    
                    // Tuky obvykle 25% z cílového příjmu kalorií (pro správnou hormonální funkci)
                    let f = (finalKcal * 0.25) / 9;
                    
                    // Minimální hranice tuků, pokud jdou kalorie moc nízko (bezpečnost metabolismu)
                    const minFat = state.weight * 0.6;
                    if (f < minFat) {
                        f = minFat;
                    }

                    // Sacharidy tvoří zbytek kalorií
                    let c = (finalKcal - (p * 4) - (f * 9)) / 4;

                    // Kontrola pro případ, že by sacharidy vyšly extrémně nízko
                    if (c < 30) {
                        c = 30; // Min na fungování mozku a trochu zeleniny
                        // Přepočítat kalorie nahoru
                        finalKcal = (p * 4) + (f * 9) + (c * 4);
                    }

                    document.getElementById('finalKcal').textContent = Math.round(finalKcal);
                    document.getElementById('finalProteins').textContent = Math.round(p) + "g";
                    document.getElementById('finalCarbs').textContent = Math.round(c) + "g";
                    document.getElementById('finalFats').textContent = Math.round(f) + "g";

                    // ZOBRAZENÍ DRUHÉ ČÁSTI
                    macrosWrapper.style.display = 'flex';
                    saveSection.style.display = 'block';
                    saveSection.scrollIntoView({ behavior: 'smooth' });
                });
            });

            // --- 3. ULOŽIT (ZABALENÍ VŠEHO) ---
            saveBtn.addEventListener('click', function () {
                const payload = {
                    bmr: Math.round(state.bmr),
                    tdee: Math.round(state.tdee),
                    kcal: parseInt(document.getElementById('finalKcal').textContent),
                    protein: parseInt(document.getElementById('finalProteins').textContent),
                    carbs: parseInt(document.getElementById('finalCarbs').textContent),
                    fats: parseInt(document.getElementById('finalFats').textContent),
                    weight: state.weight,
                    goal: state.goal,
                    activity: state.activity
                };

                fetch('uloz_kalkulacka.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Vyrolujeme nahoru
                            window.scrollTo({ top: 0, behavior: 'smooth' });

                            // ZABALENÍ: Schováme vše po dojezdu nahoru
                            setTimeout(() => {
                                resultsWrapper.style.display = 'none';
                                macrosWrapper.style.display = 'none';
                                saveSection.style.display = 'none';

                                // Reset tlačítek cílů
                                document.querySelectorAll('.goal-btn').forEach(btn => btn.classList.remove('active'));

                                if (data.success) {
                                    window.location.href = "dashboard.php?toast=kalkulacka_saved";
                                }
                            }, 600);
                        }
                    });
            });
        });

        function selectGender(gender) {
            // 1. Nastav hodnotu do skrytého inputu
            document.getElementById('bmrPohlavi').value = gender;

            // 2. Odstraň 'active' třídu ze všech
            document.querySelectorAll('.gender-item').forEach(item => {
                item.classList.remove('active');
            });

            // 3. Přidej 'active' vybranému
            document.getElementById('gender-' + gender).classList.add('active');

            // Volitelné: pokud chceš resetovat tlačítko uložení při změně
            if (typeof resetSaveButton === "function") resetSaveButton();
        }

        function toggleDropdown() {
            document.getElementById('dropdown-options').classList.toggle('show');
        }

        function setActivity(val, text) {
            // Nastavíme hodnotu do skrytého inputu pro výpočet
            document.getElementById('bmrAktivita').value = val;
            // Změníme text v triggeru
            document.getElementById('selected-text').innerText = text;
            // Zavřeme menu
            document.getElementById('dropdown-options').classList.remove('show');
        }

        // Zavření dropdownu při kliknutí mimo něj
        window.onclick = function (event) {
            if (!event.target.closest('.custom-select-wrapper')) {
                document.getElementById('dropdown-options').classList.remove('show');
            }
        }

        const openHelpBtn = document.getElementById('openHelp');
        const closeHelpBtn = document.getElementById('closeHelp');
        const overlay = document.getElementById('imageOverlay');

        if (openHelpBtn && overlay && closeHelpBtn) {
            openHelpBtn.addEventListener('click', function () {
                overlay.style.display = 'flex';
            });

            closeHelpBtn.addEventListener('click', function () {
                overlay.style.display = 'none';
            });

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    overlay.style.display = 'none';
                }
            });
        }
    </script>
</body>

</html>