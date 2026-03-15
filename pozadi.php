<?php
require_once 'db.php'; 

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: index.php");
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
        LEFT JOIN profily pr ON o.odesilatel_id = pr.uzivatel_id
        WHERE o.prijemce_id = ? 
        ORDER BY o.precteno ASC, o.vytvoren DESC 
        LIMIT 50
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
        :root {
            --toxic-green: #00ff80;
            --toxic-glow: rgba(0, 255, 128, 0.3);
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-blur: 25px;
            --card-radius: 25px;
            --deep-dark: rgba(10, 10, 10, 0.6);
        }

        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            margin: 0; padding: 0;
            background-color: #000000; color: white;
            overflow-x: hidden;
            width: 100vw;
            min-height: 100vh;
        }
        .background-container {
            width: 100vw; height: 100vh; position: fixed;
            top: 0; left: 0; z-index: -1; overflow: hidden;
        }
        

        @keyframes float {
    0% { transform: translate(0, 0); }
    33% { transform: translate(40px, -60px); }
    66% { transform: translate(-30px, 30px); }
    100% { transform: translate(0, 0); }
}

.blob { 
    position: absolute; 
    border-radius: 50%; 
    opacity: 0.7; 
    filter: blur(140px); 
    animation: float 25s ease-in-out infinite;
}

.blob-1 { 
    width: 800px; 
    height: 600px; 
    background-color: var(--toxic-green); 
    top: -10%; 
    left: -5%; 
    animation-duration: 8s;
}

.blob-2 { 
    width: 900px; 
    height: 900px; 
    background-color: var(--toxic-green); 
    bottom: -15%; 
    right: -10%; 
    animation-duration: 12s;
    animation-delay: -6s;
}

        header {
            width: 100%; padding: 20px 50px; display: flex; align-items: center; justify-content: space-between;
            position: fixed; top: 0; z-index: 1000; box-sizing: border-box;
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); 
            background: rgba(255, 255, 255, 0.01); 
        }

        h1 { color: white; margin: 0; font-size: 50px; }
        .header-icons { display: flex; align-items: center; }
        .header-icons a, .nav-item-notif { margin-left: 25px; text-decoration: none; }
        .header-icons img { height: 25px; width: 25px; opacity: 0.9; transition: 0.2s; display: block; }
        .nav-item-notif { position: relative; }

        .notif-dropdown {
            position: absolute; top: 60px; right: -15px; width: 360px;
            background: rgba(15, 15, 15, 0.85); 
            backdrop-filter: blur(var(--glass-blur)) saturate(200%);
            border: 1px solid var(--glass-border); 
            border-radius: var(--card-radius);
            display: none; z-index: 9999; 
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.9), 0 0 30px var(--toxic-glow);
            overflow: hidden; text-align: left; 
            animation: slideIn 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes slideIn { from { opacity: 0; transform: translateY(-15px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .notif-dropdown.show { display: block !important; }

        .notif-items-container { 
            max-height: 280px; 
            overflow-y: auto; 
            scrollbar-width: thin; 
            scrollbar-color: #00ff80 rgba(255,255,255,0.02); 
        }

        .notif-header { 
            display: flex; justify-content: space-between; align-items: center;
            padding: 18px 22px; background: var(--glass-bg); 
            border-bottom: 1px solid var(--glass-border); 
        }
        .notif-header h4 { margin: 0; color: var(--toxic-green); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px; font-weight: 900; opacity: 0.9; text-shadow: 0 0 10px var(--toxic-glow); }
        .delete-all-btn { font-size: 0.65rem; color: #ff4d4d; text-decoration: none; font-weight: 800; opacity: 0.6; transition: 0.3s; cursor: pointer; border: 1px solid rgba(255, 77, 77, 0.2); padding: 5px 12px; border-radius: 12px; background: rgba(255, 77, 77, 0.05); }
        .delete-all-btn:hover { opacity: 1; background: rgba(255, 77, 77, 0.15); transform: translateY(-1px); border-color: rgba(255, 77, 77, 0.4); box-shadow: 0 0 15px rgba(255, 77, 77, 0.2); }

        .notif-items-container::-webkit-scrollbar { width: 4px; }
        .notif-items-container::-webkit-scrollbar-track { background: transparent; }
        .notif-items-container::-webkit-scrollbar-thumb { background: #00ff80; border-radius: 10px; box-shadow: 0 0 10px rgba(0, 255, 128, 0.5); }

        .notif-dropdown-item { 
            padding: 18px 22px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); 
            transition: 0.2s; position: relative;
        }
        .notif-dropdown-item:hover { background: rgba(255, 255, 255, 0.02); }
        .notif-dropdown-item.unread { background: rgba(0, 255, 128, 0.05); border-left: 3px solid var(--toxic-green); }
        .notif-dropdown-item p { margin: 0; font-size: 0.82rem; color: #fff; line-height: 1.4; }
        .notif-dropdown-item strong { color: var(--toxic-green); font-weight: 700; }
        .notif-dropdown-item small { display: block; margin-top: 6px; font-size: 0.65rem; color: rgba(255, 255, 255, 0.3); font-weight: 500; }
        
        /* Styl pro tlačítka výzvy */
        .challenge-btns { display: flex; gap: 8px; margin-top: 10px; }
        .btn-acc { background: #00ff80; border: none; color: black; padding: 5px 10px; border-radius: 5px; font-size: 0.75rem; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-dec { background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: white; padding: 5px 10px; border-radius: 5px; font-size: 0.75rem; cursor: pointer; }
        .btn-acc:hover { background: #00cc66; transform: scale(1.05); }

        .notif-badge-header { 
            position: absolute; 
            top: -5px; 
            right: -5px; 
            background: #ff4d4d; 
            color: white; 
            width: 14px; 
            height: 14px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 0.6rem; 
            font-weight: 800; 
            z-index: 10;
            animation: pulse-alert 2s infinite;
        }

        .toast-banner {
            position: fixed;
            top: -120px;
            left: 50%;
            transform: translateX(-50%);

            background: rgba(8, 8, 8, 0.88);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(0, 255, 128, 0.35);
            border-radius: 50px;

            color: rgba(255, 255, 255, 0.92);
            padding: 13px 26px 13px 18px;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            white-space: nowrap;
            z-index: 10000;

            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(0, 255, 128, 0.1) inset;
            transition: top 0.45s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
            overflow: hidden;
        }

        /* Holografický odlesk */
        .toast-banner::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(0, 255, 128, 0.08) 0%, rgba(255,255,255,0.02) 60%, transparent 100%);
            pointer-events: none;
        }

        /* Zelená tečka vlevo */
        .toast-banner::after {
            content: '';
            flex-shrink: 0;
            width: 7px;
            height: 7px;
            background: #00ff80;
            border-radius: 50%;
            box-shadow: 0 0 8px #00ff80, 0 0 16px rgba(0, 255, 128, 0.5);
            order: -1;
        }

        .toast-banner.show { top: 55px; }

        /* Styl pro červený identifikátor profilu */
.profile-link {
    position: relative; /* Nutné pro správné umístění tečky */
}

.profile-alert-dot {
    position: absolute;
    top: -3px;
    right: -0.1px;
    width: 11px;
    height: 11px;
    background-color: #ff4d4d; /* Svítivě červená */
    border-radius: 50%;
    z-index: 10;
    animation: pulse-alert 2s infinite;
}

@keyframes pulse-alert {
    0% { box-shadow: 0 0 0 0 rgba(255, 77, 77, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(255, 77, 77, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 77, 77, 0); }
}

/* --- MOBILE RESPONSIVE GLOBALS --- */
@media (max-width: 768px) {
    header {
        padding: 15px 20px;
    }
    
    h1 {
        font-size: 24px;
        letter-spacing: -0.5px;
    }
    
    .header-icons a, .nav-item-notif {
        margin-left: 15px;
    }
    
    .header-icons img {
        height: 22px;
        width: 22px;
    }
    
    .notif-dropdown {
        width: 90vw;
        max-width: 350px;
        right: -60px; /* Posun doprostřed, ať nepřeteče doleva */
    }
    
    /* Zmenšení blobů, aby nelagovaly a nezpůsobovaly overflow-x na mobilech */
    .blob-1 {
        width: 500px;
        height: 400px;
        filter: blur(80px);
    }
    
    .blob-2 {
        width: 500px;
        height: 500px;
        filter: blur(80px);
    }
    
    .toast-banner {
        width: 90vw;
        font-size: 0.65rem;
        padding: 12px 15px;
        letter-spacing: 1px;
    }
}
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
            <a href="dashboard.php"><img src="img/domu.png" alt="Dashboard"></a>
            
            <div class="nav-item-notif">
                <div class="notif-icon-wrapper" onclick="toggleNotifDropdown(event)" style="cursor: pointer; position: relative;">
                    <img src="img/oznameni.png" alt="Oznámení">
                    <?php if (isset($pocet_oznameni) && $pocet_oznameni > 0): ?>
                        <span class="notif-badge-header"><?php echo $pocet_oznameni; ?></span>
                    <?php endif; ?>
                </div>

                <div id="notif-dropdown" class="notif-dropdown">
                    <div class="notif-header">
                        <h4>Oznámení</h4>
                        <?php if (!empty($notifikace)): ?>
                            <span class="delete-all-btn" onclick="deleteAllNotifs()">Smazat vše</span>
                        <?php endif; ?>
                    </div>
                    <div class="notif-items-container">
                        <?php if (!empty($notifikace)): ?>
                            <?php foreach ($notifikace as $n): ?>
                                <div id="notif-box-<?php echo $n['id']; ?>" class="notif-dropdown-item <?php echo !$n['precteno'] ? 'unread' : ''; ?>">
                                    <p>
                                        <?php if ($n['typ'] !== 'duel_win' && $n['typ'] !== 'duel_lose'): ?>
                                            <strong><?php echo htmlspecialchars($n['prezdivka'] ?: 'Neznámý uživatel'); ?></strong>
                                        <?php endif; ?>
                                        <?php 
                                            if ($n['typ'] == 'lajk') echo " ti dal lajk.";
                                            elseif ($n['typ'] == 'komentar') echo " napsal komentář.";
                                            elseif ($n['typ'] == 'follow') echo " tě začal sledovat.";
                                            elseif ($n['typ'] == 'vyzva') echo " tě vyzval na souboj!";
                                            elseif ($n['typ'] == 'duel_accept') echo " přijal tvou výzvu!";
                                            elseif ($n['typ'] == 'duel_decline') echo " odmítl tvou výzvu.";
                                            elseif ($n['typ'] == 'duel_win') echo "Vyhrál jsi souboj!";
                                            elseif ($n['typ'] == 'duel_lose') echo "Prohrál jsi souboj.";
                                        ?>
                                    </p>
                                    
                                    <?php if ($n['typ'] == 'vyzva' && !$n['precteno']): ?>
                                        <div class="challenge-btns">
                                            <button class="btn-acc" onclick="handleChallenge(<?php echo $n['id']; ?>, 'accept')">Přijmout</button>
                                            <button class="btn-dec" onclick="handleChallenge(<?php echo $n['id']; ?>, 'decline')">Zrušit</button>
                                        </div>
                                    <?php endif; ?>

                                    <small><?php echo date('j.n. H:i', strtotime($n['vytvoren'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: 40px 20px; text-align: center; opacity: 0.4;">
                                <img src="img/oznameni.png" style="width: 30px; height: 30px; margin: 0 auto 10px; filter: grayscale(1);">
                                <p style="font-size: 0.8rem; color: #fff;">Zatím žádná oznámení</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <a href="uzivatel.php" class="profile-link">
    <img src="img/uzivatel.png" alt="Profil">
    <?php if ($ukazat_tecku): ?>
        <span class="profile-alert-dot" title="Dokonči svůj profil!"></span>
    <?php endif; ?>
</a>
            <a href="logout.php"><img src="img/odhlaseni.png" alt="Odhlásit"></a>
        </div>
    </header>

    <div id="statusToast" class="toast-banner"></div>

    <script>
    function deleteAllNotifs() {
        if (!confirm('Opravdu chcete smazat všechna oznámení?')) return;
        
        fetch('smaz_vsechna_oznameni.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.querySelector('.notif-items-container');
                container.innerHTML = '<div style="padding: 40px 20px; text-align: center; opacity: 0.4;"><img src="img/oznameni.png" style="width: 30px; height: 30px; margin: 0 auto 10px; filter: grayscale(1);"><p style="font-size: 0.8rem; color: #fff;">Zatím žádná oznámení</p></div>';
                
                const badge = document.querySelector('.notif-badge-header');
                if (badge) badge.style.display = 'none';
                
                const delBtn = document.querySelector('.delete-all-btn');
                if (delBtn) delBtn.style.display = 'none';

                showToast("Všechna oznámení byla smazána.");
            }
        });
    }

    function toggleNotifDropdown(event) {
        if (event) event.stopPropagation(); 
        
        const dropdown = document.getElementById('notif-dropdown');
        if (!dropdown) return;
        
        const isShowing = dropdown.classList.toggle('show');
        
        if (isShowing) {
            // Automaticky označíme jako přečtené při otevření
            fetch('oznac_vse_precteno.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const badge = document.querySelector('.notif-badge-header');
                    if (badge) badge.style.display = 'none';
                    
                    document.querySelectorAll('.notif-dropdown-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                }
            });
        }
    }

    // Zavření dropdownu při kliknutí kamkoliv jinam
    window.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notif-dropdown');
        const notifIcon = document.querySelector('.notif-icon-wrapper');
        
        if (dropdown && dropdown.classList.contains('show')) {
            // Pokud klik neproběhl uvnitř dropdownu ani na ikonku zvonečku
            if (!dropdown.contains(e.target) && !notifIcon.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        }
    });

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
            if (action === 'accept') {
                window.location.href = 'vyzvy.php';
            } else {
                const box = document.getElementById('notif-box-' + notifId);
                if (box) box.remove();

                const row = document.getElementById('notif-row-' + notifId);
                if (row) row.remove();
                
                showToast('Výzva odmítnuta');
            }
        } else {
            showToast('Chyba: ' + (data.error || 'Neznámý problém'));
        }
    });
}

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const toastType = urlParams.get('toast');
        
        if (toastType && typeof showToast === 'function') {
            let message = "";
            if (toastType === 'welcome') message = "Vítej v aplikaci Progress!";
            else if (toastType === 'profile_saved') message = "Profil úspěšně uložen!";
            else if (toastType === 'kalkulacka_saved') message = "Výsledky kalkulačky uloženy!";
            
            if (message) {
                setTimeout(() => showToast(message), 300);
            }
        }
    });

    function showToast(message) {
        const toast = document.getElementById('statusToast');
        if (!toast) return;
        toast.innerText = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3500);
    }
    </script>
</body>
</html>