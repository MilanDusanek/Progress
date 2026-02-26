<?php
require_once 'db.php'; 

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];
$ukazat_tecku = false;

$stmt_check = $pdo->prepare("SELECT 1 FROM profily WHERE uzivatel_id = :id LIMIT 1");
$stmt_check->execute(['id' => $uzivatel_id]);

if (!$stmt_check->fetch()) {
    $ukazat_tecku = true;
}

if (isset($_SESSION['uzivatel_id'])) {
    $moje_id = $_SESSION['uzivatel_id'];
    
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM oznameni WHERE prijemce_id = ? AND precteno = false");
    $stmt_count->execute([$moje_id]);
    $pocet_oznameni = $stmt_count->fetchColumn();

    $stmt_list = $pdo->prepare("
    SELECT o.*, pr.prezdivka 
    FROM oznameni o
    JOIN profily pr ON o.odesilatel_id = pr.uzivatel_id
    WHERE o.prijemce_id = ? 
    AND (o.precteno = false OR o.typ = 'vyzva') 
    ORDER BY o.precteno ASC, o.vytvoren DESC 
    LIMIT 10
");
    $stmt_list->execute([$moje_id]);
    $notifikace = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* ... tvoje stávající styly ... */
        body {
            font-family: Arial, sans-serif;
            margin: 0; padding: 0;
            background-color: #000000; color: white;
            text-align: center; min-height: 100vh;
        }
        .background-container {
            width: 100vw; height: 100vh; position: fixed;
            top: 0; left: 0; z-index: -1; overflow: hidden;
        }
        .blob { position: absolute; border-radius: 50%; opacity: 0.8; filter: blur(120px); }
        .blob-1 { width: 500px; height: 150px; background-color: #00ff80; top: 10%; left: 15%; }
        .blob-2 { width: 800px; height: 800px; background-color: #00ff80; top: 40%; right: 5%; }

        header {
            width: 100%; padding: 20px 50px; display: flex; align-items: center; justify-content: space-between;
            position: fixed; top: 0; z-index: 1000; box-sizing: border-box;
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); 
        }

        h1 { color: white; margin: 0; font-size: 50px; }
        .header-icons { display: flex; align-items: center; }
        .header-icons a, .nav-item-notif { margin-left: 25px; text-decoration: none; }
        .header-icons img { height: 35px; width: 35px; opacity: 0.9; transition: 0.2s; display: block; }
        .nav-item-notif { position: relative; }

        .notif-dropdown {
            position: absolute; top: 55px; right: -10px; width: 300px;
            background: rgba(15, 15, 15, 0.75); backdrop-filter: blur(25px) saturate(160%);
            border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px;
            display: none; z-index: 9999; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            overflow: hidden; text-align: left; animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .notif-dropdown.show { display: block; }
        .notif-dropdown h4 { margin: 0; padding: 15px 20px; background: rgba(255, 255, 255, 0.05); color: #00ff80; font-size: 0.9rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .notif-dropdown-item { padding: 15px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .notif-dropdown-item p { margin: 0; font-size: 0.85rem; color: #eee; }
        .notif-dropdown-item strong { color: #00ff80; }
        
        /* Styl pro tlačítka výzvy */
        .challenge-btns { display: flex; gap: 8px; margin-top: 10px; }
        .btn-acc { background: #00ff80; border: none; color: black; padding: 5px 10px; border-radius: 5px; font-size: 0.75rem; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-dec { background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: white; padding: 5px 10px; border-radius: 5px; font-size: 0.75rem; cursor: pointer; }
        .btn-acc:hover { background: #00cc66; transform: scale(1.05); }

        .notif-badge-header { position: absolute; top: -5px; right: -5px; background: #ff0000; color: white; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold; }
        .toast-banner { position: fixed; top: -100px; left: 50%; transform: translateX(-50%); background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(15px); border: 1px solid rgba(0, 255, 128, 0.4); color: #00ff80; padding: 12px 40px; border-radius: 25px; font-weight: bold; z-index: 9999; transition: top 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .toast-banner.show { top: 50px; }
    </style>
</head>
<body>
    <div class="background-container">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>
    
    <header>
        <h1>PROGRESS</h1>
        <div class="header-icons">
            <a href="dashboard.php"><img src="img/home-button.png" alt="Dashboard"></a>
            
            <div class="nav-item-notif">
                <div class="notif-icon-wrapper" onclick="toggleNotifDropdown()" style="cursor: pointer; position: relative;">
                    <img src="img/message.png" alt="Oznámení">
                    <?php if (isset($pocet_oznameni) && $pocet_oznameni > 0): ?>
                        <span class="notif-badge-header"><?php echo $pocet_oznameni; ?></span>
                    <?php endif; ?>
                </div>

                <div id="notif-dropdown" class="notif-dropdown">
                    <h4>Oznámení</h4>
                    <div class="notif-items-container">
                        <?php if (!empty($notifikace)): ?>
                            <?php foreach ($notifikace as $n): ?>
                                <div id="notif-box-<?php echo $n['id']; ?>" class="notif-dropdown-item <?php echo !$n['precteno'] ? 'unread' : ''; ?>">
                                    <p>
                                        <strong><?php echo htmlspecialchars($n['prezdivka']); ?></strong>
                                        <?php 
                                            if ($n['typ'] == 'lajk') echo " ti dal lajk.";
                                            elseif ($n['typ'] == 'komentar') echo " napsal komentář.";
                                            elseif ($n['typ'] == 'follow') echo " tě začal sledovat.";
                                            elseif ($n['typ'] == 'vyzva') echo " tě vyzval na souboj! 🔥";
                                        ?>
                                    </p>
                                    
                                    <?php if ($n['typ'] == 'vyzva'): ?>
                                        <div class="challenge-btns">
                                            <button class="btn-acc" onclick="handleChallenge(<?php echo $n['id']; ?>, 'accept')">Přijmout</button>
                                            <button class="btn-dec" onclick="handleChallenge(<?php echo $n['id']; ?>, 'decline')">Zrušit</button>
                                        </div>
                                    <?php endif; ?>

                                    <small><?php echo date('j.n. H:i', strtotime($n['vytvoren'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="padding: 20px; color: #666; font-size: 0.8rem; text-align: center;">Žádná nová oznámení</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <a href="uzivatel.php" class="profile-link <?= $ukazat_tecku ? 'alert' : '' ?>">
                <img src="img/userIcon.png" alt="Profil">
            </a>
            <a href="logout.php"><img src="img/logout.png" alt="Odhlásit"></a>
        </div>
    </header>

    <div id="statusToast" class="toast-banner"></div>

    <script>
    function toggleNotifDropdown() {
    const dropdown = document.getElementById('notif-dropdown');
    const badge = document.querySelector('.notif-badge-header');
    
    dropdown.classList.toggle('show');

    // Pokud otevíráme menu
    if (dropdown.classList.contains('show')) {
        fetch('oznac_precteno.php').then(response => {
            if(response.ok) {
                // Místo natvrdo schování badge (display = none) 
                // zjistíme, jestli jsou v seznamu ještě nějaké výzvy
                const vypadekVyzvy = dropdown.querySelectorAll('.challenge-btns').length;
                
                if (vypadekVyzvy > 0) {
                    // Pokud tam jsou výzvy, badge necháme a jen aktualizujeme číslo
                    if (badge) badge.innerText = vypadekVyzvy;
                } else {
                    // Pokud tam žádná výzva není, badge schováme
                    if (badge) badge.style.display = 'none';
                }
            }
        });
    }
}

   function handleChallenge(notifId, action) {
    const params = new URLSearchParams();
    params.append('notif_id', notifId);
    params.append('action', action);

    fetch('vyridit_vyzvu.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const radek = document.getElementById('notif-box-' + notifId);
            if (radek) {
                radek.remove(); 
            }
            
            triggerToast(action === 'accept' ? 'Výzva přijata!' : 'Výzva odmítnuta.');
        }
    });
}

    function triggerToast(message) {
        const toast = document.getElementById('statusToast');
        if (!toast) return;
        toast.innerText = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3500);
    }
    </script>
</body>
</html>