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
        padding-top: 120px;
        width: 100%;
        z-index: 10;
        padding-bottom: 50px;
    }

    .profile-form-container {
        max-width: 700px;
        width: 90%;
        padding: 30px;
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 25px;
        box-shadow: 0 4px 50px rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(5px);
        position: relative;
    }

    .profile-form-container h2 {
        color: white;
        text-align: center;
        margin-bottom: 25px;
        letter-spacing: 1px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px 25px;
    }

    .full-width {
        grid-column: span 2;
    }

    .profile-form label {
        display: block;
        color: white;
        margin: 5px;
        font-weight: bold;
        font-size: 0.9rem;
        display: flex;
    }

    .profile-form input,
    .profile-form select {
        width: 100%;
        padding: 12px;
        border: 1px solid #00ff80;
        border-radius: 6px;
        background-color: rgba(0, 0, 0, 0.5);
        color: white;
        box-sizing: border-box;
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
        font-size: 2.2rem;
        font-weight: 900;
        margin: 5px 0;
    }

    .result-item p1 {
        font-size: 0.8rem;
        opacity: 0.7;
    }

    .submit-button {
        width: 100%;
        padding: 15px;
        margin-top: 10px;
        background-color: #00ff80;
        color: #000000;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
        transition: all 0.3s;
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

    @media (max-width: 600px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .full-width {
            grid-column: span 1;
        }

        .results-container {
            flex-direction: column;
        }
    }

    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield;
    }

    /* Vzhled tlačítek cílů */
    .goal-btn {
        background: transparent !important;
        border: 2px solid #00ff80 !important;
        color: #00ff80 !important;
        transition: all 0.3s ease;
    }

    .goal-btn.active {
        background-color: #00ff80 !important;
        color: #000000 !important;
        box-shadow: 0 0 15px rgba(0, 255, 128, 0.5);
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
</style>

<body>
    <div id="imageOverlay">
        <span class="close-btn" id="closeHelp">&times;</span>
        <img src="img/fat.png" alt="Nápověda k procentu tuku">
    </div>
    <main class="tracking-layout">
        <div class="profile-form-container">
            <h2>VÝPOČET METABOLISMU</h2>

            <form id="bmrCalculatorForm" class="profile-form">
                <div class="form-grid">
                    <div class="input-group">
                        <label for="bmrVaha">Aktuální váha (kg) *</label>
                        <input type="number" id="bmrVaha" step="0.1" required
                            value="<?= htmlspecialchars($userData['vaha'] ?? '') ?>" placeholder="80 kg">
                    </div>

                    <div class="input-group">
                        <label for="bmrVyska">Výška (cm) *</label>
                        <input type="number" id="bmrVyska" min="130" max="210"
                            value="<?= htmlspecialchars($userData['vyska'] ?? '') ?>" placeholder="180 cm" required
                            oninput="if(this.value > 210) this.value = 210;"
                            onblur="if(this.value < 130 && this.value !== '') this.value = 130;">
                    </div>

                    <div class="input-group">
                        <label for="bmrVek">Věk (let) *</label>
                        <input type="number" id="bmrVek" min="15" max="99"
                            value="<?= htmlspecialchars($userData['vek'] ?? '') ?>" placeholder="20 let" required
                            oninput="if(this.value > 99) this.value = 99;"
                            onblur="if(this.value < 15 && this.value !== '') this.value = 15;">
                    </div>

                    <div class="input-group">
                        <label for="bmrPohlavi">Pohlaví *</label>
                        <select id="bmrPohlavi">
                            <option value="muz" <?= ($userData['pohlavi'] ?? '') === 'muz' ? 'selected' : '' ?>>Muž
                            </option>
                            <option value="zena" <?= ($userData['pohlavi'] ?? '') === 'zena' ? 'selected' : '' ?>>Žena
                            </option>
                        </select>
                    </div>

                    <div class="full-width">
                        <label for="bmrTuk">Procento podkožního tuku (%) *</label>
                        <input type="number" id="bmrTuk" min="5" max="45" step="0.1" placeholder="%" required>
                        <span id="openHelp"
                            style="display:block; cursor: pointer; margin-top:5px; margin-left:545px; font-size: 15px; font-weight: normal; color:white;">Nevíš
                            jaké číslo zvolit?</span>
                    </div>

                    <div class="full-width">
                        <label for="bmrAktivita">Úroveň aktivity: *</label>
                        <select id="bmrAktivita" required>
                            <option value="1.2">Sedavá (minimum pohybu)</option>
                            <option value="1.375">Mírná (lehký trénink 1-3x týdně)</option>
                            <option value="1.55">Střední (trénink 3-5x týdně)</option>
                            <option value="1.725">Vysoká (intenzivní trénink každý den)</option>
                            <option value="1.9">Extrémní (profesionální sportovec)</option>
                        </select>
                    </div>

                    <div class="full-width">
                        <button type="submit" id="vypocitatBMR" class="submit-button"> VYPOČÍTEJ </button>
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
                    <button type="button" class="submit-button goal-btn" data-goal="hubnuti">HUBNUTÍ</button>
                    <button type="button" class="submit-button goal-btn" data-goal="udrzba">UDRŽENÍ</button>
                    <button type="button" class="submit-button goal-btn" data-goal="nabirani">NABÍRÁNÍ</button>
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
                    ULOŽIT VÝSLEDKY DO PROFILU
                </button>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const resultsWrapper = document.getElementById('resultsWrapper');
            const macrosWrapper = document.getElementById('macrosWrapper');
            const saveSection = document.getElementById('saveSection');
            const saveBtn = document.getElementById('saveToDb');
            const bmrForm = document.getElementById('bmrCalculatorForm');

            let state = { bmr: 0, tdee: 0, weight: 0, activity: 0, goal: '' };

            function resetSaveButton() {
                saveBtn.style.backgroundColor = 'transparent';
                saveBtn.style.color = '#00ff80';
                saveBtn.textContent = 'ULOŽIT VÝSLEDKY DO PROFILU';
            }

            bmrForm.addEventListener('submit', function (event) {
                event.preventDefault();

                state.weight = parseFloat(document.getElementById('bmrVaha').value);
                const tuk = parseFloat(document.getElementById('bmrTuk').value);
                state.activity = parseFloat(document.getElementById('bmrAktivita').value);

                const LBM = state.weight * (1 - (tuk / 100));
                state.bmr = 370 + (21.6 * LBM);
                state.tdee = state.bmr * state.activity;

                document.getElementById('bmrVysledek').textContent = Math.round(state.bmr) + " Kcal";

                resultsWrapper.style.display = 'flex';
                macrosWrapper.style.display = 'none';
                saveSection.style.display = 'none';

                document.querySelectorAll('.goal-btn').forEach(btn => btn.classList.remove('active'));
                resetSaveButton();
                resultsWrapper.scrollIntoView({ behavior: 'smooth' });
            });

            document.querySelectorAll('.goal-btn').forEach(button => {
                button.addEventListener('click', function () {
                    document.querySelectorAll('.goal-btn').forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    resetSaveButton();

                    state.goal = this.getAttribute('data-goal');
                    let finalKcal = state.tdee;
                    let proteinPerKg = 2.0;

                    if (state.goal === 'hubnuti') {
                        finalKcal = state.tdee * 0.80;

                        if (finalKcal < state.bmr) {
                            finalKcal = state.bmr;
                        }

                        proteinPerKg = 2.2;

                    } else if (state.goal === 'nabirani') {
                        finalKcal = state.tdee * 1.15;
                        proteinPerKg = 2.0;
                    } else {
                        finalKcal = state.tdee;
                        proteinPerKg = 2.0;
                    }

                    const p = state.weight * proteinPerKg;
                    const f = state.weight * 0.9;
                    const carbKcal = finalKcal - (p * 4) - (f * 9);
                    const c = carbKcal / 4;

                    document.getElementById('finalKcal').textContent = Math.round(finalKcal);
                    document.getElementById('finalProteins').textContent = Math.round(p) + "g";
                    document.getElementById('finalCarbs').textContent = Math.round(c) + "g";
                    document.getElementById('finalFats').textContent = Math.round(f) + "g";

                    macrosWrapper.style.display = 'flex';
                    saveSection.style.display = 'block';
                    saveSection.scrollIntoView({ behavior: 'smooth' });
                });
            });

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
                            window.scrollTo({
                                top: 0,
                                behavior: 'smooth'
                            });
                            triggerToast("Tvoje výsledky byly uloženy!", "success");
                        }
                    })
                triggerToast("Chyba při ukládání: " + (data.message || "neznámá chyba"), "error");
            });

            const overlay = document.getElementById('imageOverlay');
            const openHelp = document.getElementById('openHelp');
            const closeHelp = document.getElementById('closeHelp');

            if (openHelp) openHelp.addEventListener('click', () => overlay.style.display = 'flex');
            if (closeHelp) closeHelp.addEventListener('click', () => overlay.style.display = 'none');
        });
    </script>
</body>

</html>