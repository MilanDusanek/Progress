<?php
session_start();

include 'pozadi.php';

// Databáze (nechávám tvoje připojení)
$host = "localhost";
$dbname = "Progress";
$user = "postgres";
$password = "heslo";
$dsn = "pgsql:host=$host;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Chyba připojení k databázi: " . $e->getMessage());
}

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}
$uzivatel_id = $_SESSION['uzivatel_id'];

$stmt = $pdo->prepare("SELECT prezdivka, vek, pohlavi, vyska, vaha, cil, profilovy_obrazek FROM profily WHERE uzivatel_id = :uid");
$stmt->execute(['uid' => $uzivatel_id]);
$profil_data = $stmt->fetch(PDO::FETCH_ASSOC);

$prezdivka = $profil_data['prezdivka'] ?? '';
$vek = $profil_data['vek'] ?? '';
$pohlavi = $profil_data['pohlavi'] ?? '';
$vyska = $profil_data['vyska'] ?? '';
$vaha = $profil_data['vaha'] ?? '';
$profilovy_obrazek = $profil_data['profilovy_obrazek'] ?? null; 
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Úprava profilu</title>
    <link rel="stylesheet" href="style.css"> 
    <style>
        /* --- TVOJE PŮVODNÍ STYLY INPUTŮ A FORMULÁŘE --- */
        .profile-form-container {
            max-width: 450px; 
            margin: 150px auto 50px;
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.1); 
            border-radius: 25px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5); 
            backdrop-filter: blur(5px);
            position: relative; 
            z-index: 20; 
        }

        .profile-form-container h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        .profile-form label {
            display: flex;
            color: white;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: bold;
        }

        /* Původní styl inputů a selectu */
        .profile-form input, 
        .profile-form select { 
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid #00ff80; /* Zelený border */
            border-radius: 6px;
            background-color: rgba(0, 0, 0, 0.5); 
            color: white;
            box-sizing: border-box;
        }

        /* Fokus na bílou - jak jsi chtěl */
        .profile-form input:focus, 
        .profile-form select:focus {
            border-color: white;
            outline: none; 
        }

        /* Odstranění šipek (stepperu) u čísel */
        .profile-form input[type="number"]::-webkit-outer-spin-button,
        .profile-form input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .profile-form input[type="number"] {
            -moz-appearance: textfield;
        }

        /* Styl pro options v selectu */
        .profile-form select option {
            background-color: #1a1a1a; 
            color: white;
            padding: 10px;
        }

        .submit-button { 
            width: 100%; padding: 12px; margin-top: 20px; 
            background-color: #00ff80; color: #000000; 
            border: none; border-radius: 6px; cursor: pointer; 
            font-size: 16px; font-weight: bold; transition: background-color 0.3s; 
        }
        .submit-button:hover { background-color: #00e673; }

        /* Fotka a ikony (tvoje původní) */
        .profile-pic-area { text-align: center; margin-bottom: 25px; }
        .profile-image-wrapper { position: relative; display: inline-block; cursor: pointer; }
        .profile-pic { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #00ff80; box-shadow: 0 0 15px rgba(0, 255, 128, 0.5); }
        .edit-icon { position: absolute; bottom: 0; right: 0; background-color: #00ff80; color: #000; border-radius: 50%; padding: 6px; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; pointer-events: none; }
    </style>
</head>
<body>

<main class="profile-form-container">
    <h2>VYTVOŘENÍ / ÚPRAVA PROFILU</h2>

    <div class="profile-pic-area">
        <div id="profileImageWrapper" class="profile-image-wrapper" title="Klikněte pro změnu fotky">
            <?php if ($profilovy_obrazek): ?>
                <img id="profileImage" src="<?php echo htmlspecialchars($profilovy_obrazek); ?>" alt="Profilová fotka" class="profile-pic">
            <?php else: ?>
                <div id="profileImage" class="profile-pic-placeholder" style="width:120px; height:120px; border-radius:50%; background:rgba(0,255,128,0.1); border:1px dashed #00ff80; display:flex; align-items:center; justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="#00ff80" style="width:60px;">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>
            <?php endif; ?>
            <div class="edit-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
            </div>
        </div>
    </div>
    
    <form action="uloz_profil.php" method="POST" class="profile-form" enctype="multipart/form-data">
        <input type="file" id="profilovka" name="profilovka" accept="image/jpeg, image/png" style="display: none;">

        <label for="nickname">Přezdívka *</label>
        <input type="text" id="nickname" name="prezdivka" value="<?= htmlspecialchars($prezdivka) ?>" placeholder="Např. 'Pepik123'" required autocomplete="off">
        
        <label for="age">Věk *</label>
        <input type="number" id="age" name="vek" value="<?= htmlspecialchars($vek) ?>" placeholder="Zadejte váš věk" required>
        
        <label for="gender">Pohlaví *</label>
        <select id="gender" name="pohlavi">
            <option value="" disabled <?= ($pohlavi == '' ? 'selected' : '') ?>>Vyberte...</option>
            <option value="muz" <?= ($pohlavi == 'muz' ? 'selected' : '') ?>>Muž</option>
            <option value="zena" <?= ($pohlavi == 'zena' ? 'selected' : '') ?>>Žena</option>
            required
        </select>
        
        <label for="height">Výška (cm) *</label>
        <input type="number" id="height" name="vyska" value="<?= htmlspecialchars($vyska) ?>" placeholder="Např. 180" required>
        
        <label for="weight">Váha (kg) *</label>
        <input type="number" id="weight" name="vaha" step="0.1" value="<?= htmlspecialchars($vaha) ?>" placeholder="Např. 75.5" required>
        
        <button type="submit" class="submit-button">Uložit nebo aktualizovat profil</button>
    </form>
</main>

<script>


    const fileInput = document.getElementById('profilovka');
    const profileImageWrapper = document.getElementById('profileImageWrapper');
    profileImageWrapper.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = document.getElementById('profileImage');
                if (img.tagName === 'DIV') {
                    const newImg = document.createElement('img');
                    newImg.id = 'profileImage'; newImg.className = 'profile-pic';
                    img.replaceWith(newImg);
                }
                document.getElementById('profileImage').src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
</script>
</body>
</html>