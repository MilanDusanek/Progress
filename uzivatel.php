<?php
session_start();

include 'pozadi.php';
require_once 'db.php'; 

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}
$uzivatel_id = $_SESSION['uzivatel_id'];

try {
    $stmt = $pdo->prepare("SELECT prezdivka, vek, pohlavi, vyska, vaha, profilovy_obrazek FROM profily WHERE uzivatel_id = :uid");
    $stmt->execute(['uid' => $uzivatel_id]);
    $profil_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $prezdivka = $profil_data['prezdivka'] ?? '';
    $vek = $profil_data['vek'] ?? '';
    $pohlavi = $profil_data['pohlavi'] ?? '';
    $vyska = $profil_data['vyska'] ?? '';
    $vaha = $profil_data['vaha'] ?? '';
    $profilovy_obrazek = $profil_data['profilovy_obrazek'] ?? null; 

} catch (PDOException $e) {
    die("Chyba při načítání profilu: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Úprava profilu</title>
    <link rel="stylesheet" href="style.css"> 
    <style>
        .profile-form-container {
            max-width: 850px; 
            margin: 120px auto 50px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 25px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            position: relative; 
            z-index: 20; 
        }

        .profile-form-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #00ff80;
            letter-spacing: 2px;
        }

        .profile-layout-split {
            display: flex;
            gap: 40px;
            align-items: center;
        }

        .profile-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding-right: 40px;
        }

        .profile-right {
            flex: 1.5;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .profile-form label {
            display: block;
            color: white;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 0.85rem;
        }

        .profile-form input, 
        .profile-form select { 
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #00ff80; 
            border-radius: 10px;
            background-color: rgba(0, 0, 0, 0.4); 
            color: white;
            box-sizing: border-box;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .profile-form input:focus, 
        .profile-form select:focus {
            border-color: white;
        }

        .profile-form input[type="number"]::-webkit-outer-spin-button,
        .profile-form input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .profile-form input[type="number"] {
            -moz-appearance: textfield;
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

        .profile-pic-area { text-align: center; margin-bottom: 30px; }
        .profile-image-wrapper { position: relative; display: inline-block; cursor: pointer; transition: transform 0.3s ease; }
        .profile-image-wrapper:hover { transform: scale(1.05); }
        .profile-pic { width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 3px solid #00ff80; box-shadow: 0 0 20px rgba(0, 255, 128, 0.3); }
        .profile-pic-placeholder { width: 140px; height: 140px; border-radius: 50%; background: rgba(0, 255, 128, 0.05); border: 2px dashed #00ff80; display: flex; align-items: center; justify-content: center; }
        .edit-icon { position: absolute; bottom: 5px; right: 5px; background-color: #00ff80; color: #000; border-radius: 50%; padding: 8px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; pointer-events: none; box-sizing: border-box; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }

        .gender-selection {
            display: flex;
            justify-content: center;
            gap: 30px;
            width: 100%;
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
            border: 2px solid rgba(255, 255, 255, 0.2);
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(255, 255, 255, 0.02);
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }

        .gender-circle img {
            width: 35px;
            height: 35px;
            opacity: 0.5;
            transition: all 0.3s ease;
        }

        .gender-item span {
            font-size: 0.75rem;
            font-weight: bold;
            color: rgba(255, 255, 255, 0.5);
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .gender-item.active .gender-circle {
            border-color: #00ff80;
            background: rgba(0, 255, 128, 0.1);
            box-shadow: 0 0 20px rgba(0, 255, 128, 0.2);
        }

        .gender-item.active .gender-circle img {
            opacity: 1;
            transform: scale(1.1);
        }

        .gender-item.active span {
            color: #00ff80;
        }

        .gender-item:hover:not(.active) .gender-circle {
            border-color: rgba(0, 255, 128, 0.5);
        }

        @media (max-width: 768px) {
            .profile-form-container {
                padding: 30px 20px;
                margin-top: 100px;
                margin-left: 15px;
                margin-right: 15px;
                width: calc(100% - 30px);
                overflow-x: hidden;
                box-sizing: border-box;
            }
            .profile-layout-split {
                flex-direction: column;
                gap: 30px;
            }
            .profile-left {
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                padding-right: 0;
                padding-bottom: 30px;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<main class="profile-form-container">
    <h2>MŮJ PROFIL</h2>

    <form action="uloz_profil.php" method="POST" class="profile-form" enctype="multipart/form-data">
        <input type="file" id="profilovka" name="profilovka" accept="image/jpeg, image/png" style="display: none;">

        <div class="profile-layout-split">
            <!-- LEVÁ ČÁST -->
            <div class="profile-left">
                <div class="profile-pic-area">
                    <div id="profileImageWrapper" class="profile-image-wrapper" title="Klikněte pro změnu fotky">
                        <?php if ($profilovy_obrazek): ?>
                            <img id="profileImage" src="<?php echo htmlspecialchars($profilovy_obrazek); ?>" alt="Profilová fotka" class="profile-pic">
                        <?php else: ?>
                            <div id="profileImage" class="profile-pic-placeholder">
                                <svg viewBox="0 0 24 24" fill="#00ff80" style="width:60px;">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                        <div class="edit-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PRAVÁ ČÁST -->
            <div class="profile-right">
                <div>
                    <label for="nickname">PŘEZDÍVKA *</label>
                    <input type="text" id="nickname" name="prezdivka" value="<?= htmlspecialchars($prezdivka) ?>" placeholder="Fitnesak" required autocomplete="off">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label for="age">VĚK *</label>
                        <input type="number" id="age" name="vek" min="15" max="99" value="<?= htmlspecialchars($vek) ?>" placeholder="20" required>
                    </div>
                    <div>
                        <label for="height">VÝŠKA (CM) *</label>
                        <input type="number" id="height" name="vyska" min="100" max="220" value="<?= htmlspecialchars($vyska) ?>" placeholder="180" required>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; align-items: end;">
                    <div>
                        <label for="weight">VÁHA (KG) *</label>
                        <input type="number" id="weight" name="vaha" step="0.1" min="30" max="200" value="<?= htmlspecialchars($vaha) ?>" placeholder="75.5" required>
                    </div>
                    <div>
                        <label style="text-align: center; margin-bottom: 5px; color: rgba(255,255,255,0.7); display: block;">POHLAVÍ *</label>
                        <div class="gender-selection" style="gap: 15px; margin-bottom: 0;">
                            <input type="hidden" name="pohlavi" id="bmrPohlavi" value="<?= htmlspecialchars($pohlavi) ?>" required>
                            
                            <div class="gender-item <?= ($pohlavi === 'muz') ? 'active' : '' ?>" onclick="selectGender('muz')" id="gender-muz">
                                <div class="gender-circle" style="width: 50px; height: 50px;">
                                    <img src="img/muz.png" alt="Muž" style="width: 25px; height: 25px;"> 
                                </div>
                                <span style="font-size: 0.65rem;">MUŽ</span>
                            </div>

                            <div class="gender-item <?= ($pohlavi === 'zena') ? 'active' : '' ?>" onclick="selectGender('zena')" id="gender-zena">
                                <div class="gender-circle" style="width: 50px; height: 50px;">
                                    <img src="img/zena.png" alt="Žena" style="width: 25px; height: 25px;"> 
                                </div>
                                <span style="font-size: 0.65rem;">ŽENA</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-button" style="margin-top: 10px;">ULOŽIT ZMĚNY</button>
            </div>
        </div>
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

    function selectGender(gender) {
    document.getElementById('bmrPohlavi').value = gender;

    document.querySelectorAll('.gender-item').forEach(item => {
        item.classList.remove('active');
    });

    document.getElementById('gender-' + gender).classList.add('active');

    if(typeof resetSaveButton === "function") resetSaveButton();
}

    // AJAX profile save
    document.querySelector('.profile-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('uloz_profil.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') showToast('Profil uložen');
            } else {
                if (typeof showToast === 'function') showToast(data.error || 'Chyba při ukládání');
            }
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('Chyba připojení');
        });
    });
</script>
</body>
</html>