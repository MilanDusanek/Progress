<?php
session_start();
require_once 'db.php';
include 'pozadi.php';

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: index.php");
    exit;
}

$uzivatel_id = $_SESSION['uzivatel_id'];


$stmt = $pdo->prepare("
    SELECT o.*, pr.prezdivka, pr.profilovy_obrazek 
    FROM oznameni o
    LEFT JOIN profily pr ON o.odesilatel_id = pr.uzivatel_id
    WHERE o.prijemce_id = ? 
    ORDER BY o.vytvoren DESC
");
$stmt->execute([$uzivatel_id]);
$vsechna_oznameni = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt_mark = $pdo->prepare("UPDATE oznameni SET precteno = 1 WHERE prijemce_id = ?");
$stmt_mark->execute([$uzivatel_id]);


$count_unread = 0;
foreach($vsechna_oznameni as $n) if(!$n['precteno']) $count_unread++;
?>

<style>
    .notif-container {
        max-width: 800px;
        margin: 120px auto 50px;
        padding: 0 20px;
        box-sizing: border-box;
        position: relative;
        z-index: 10;
    }

    .notif-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .notif-header h2 {
        color: #00ff80;
        margin: 0;
        font-size: 1.8rem;
        letter-spacing: 1px;
    }

    .notif-actions {
        display: flex;
        gap: 15px;
    }

    .notif-action-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        padding: 8px 15px;
        border-radius: 12px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }

    .notif-action-btn:hover {
        background: rgba(0, 255, 128, 0.1);
        border-color: #00ff80;
    }

    .notif-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .notif-page-card {
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 20px;
        padding: 18px 25px;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .notif-page-card:hover {
        background: rgba(255, 255, 255, 0.06);
        transform: translateY(-2px);
        border-color: rgba(255, 255, 255, 0.1);
    }

    .notif-page-card.unread {
        border-left: 4px solid #00ff80;
        background: rgba(0, 255, 128, 0.02);
    }

    .notif-icon {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        background: rgba(0, 0, 0, 0.2);
        flex-shrink: 0;
    }

    .notif-content {
        flex-grow: 1;
    }

    .notif-content p {
        margin: 0;
        font-size: 0.95rem;
        color: #eee;
    }

    .notif-content strong {
        color: #00ff80;
    }

    .notif-time {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.4);
        margin-top: 4px;
        display: block;
    }

    .notif-badge-new {
        background: #00ff80;
        color: black;
        font-size: 0.65rem;
        font-weight: bold;
        padding: 2px 8px;
        border-radius: 10px;
        text-transform: uppercase;
        margin-left: 10px;
    }

    .btn-delete-notif {
        opacity: 0;
        background: transparent;
        border: none;
        color: rgba(255, 255, 255, 0.3);
        cursor: pointer;
        padding: 5px;
        transition: 0.2s;
        font-size: 1.1rem;
    }

    .notif-page-card:hover .btn-delete-notif {
        opacity: 1;
    }

    .btn-delete-notif:hover {
        color: #ff4d4d;
    }

    .empty-notif {
        text-align: center;
        padding: 60px 20px;
        background: rgba(255, 255, 255, 0.02);
        border-radius: 25px;
        border: 1px dashed rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.5);
    }

    @media (max-width: 600px) {
        .notif-header { flex-direction: column; align-items: flex-start; gap: 15px; }
        .notif-actions { width: 100%; justify-content: space-between; }
    }
</style>

<div class="notif-container">
    <div class="notif-header">
        <h2>Oznámení</h2>
        <div class="notif-actions">
            <?php if ($count_unread > 0): ?>
                <button class="notif-action-btn" onclick="bulkAction('read_all')">Označit jako přečtené</button>
            <?php endif; ?>
            <?php if (!empty($vsechna_oznameni)): ?>
                <button class="notif-action-btn" onclick="bulkAction('delete_all')" style="color: #ff4d4d;">Smazat historii</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="notif-list">
        <?php if (empty($vsechna_oznameni)): ?>
            <div class="empty-notif">
                <div style="font-size: 3rem; margin-bottom: 15px;">🔔</div>
                <p>Zatím nemáš žádná oznámení.</p>
            </div>
        <?php else: ?>
            <?php foreach ($vsechna_oznameni as $n): 
                $ikona = '🔔';
                $barva = 'rgba(255,255,255,0.1)';
                if ($n['typ'] == 'lajk') { $ikona = ''; $barva = 'rgba(255, 77, 77, 0.1)'; }
                elseif ($n['typ'] == 'komentar') { $ikona = ''; $barva = 'rgba(0, 150, 255, 0.1)'; }
                elseif ($n['typ'] == 'follow') { $ikona = ''; $barva = 'rgba(0, 255, 128, 0.1)'; }
                elseif ($n['typ'] == 'vyzva' || $n['typ'] == 'duel_accept') { 
                    $ikona = '<img src="img/vyzvy.png" style="width: 24px; height: 24px; object-fit: contain;">'; 
                    $barva = 'rgba(255, 170, 0, 0.1)'; 
                }
                elseif ($n['typ'] == 'duel_decline') { $ikona = '❌'; $barva = 'rgba(255, 77, 77, 0.1)'; }
                elseif ($n['typ'] == 'duel_win') { 
                    $ikona = ''; 
                    $barva = 'rgba(0, 255, 128, 0.1)'; 
                }
                elseif ($n['typ'] == 'duel_lose') { 
                    $ikona = ''; 
                    $barva = 'rgba(255, 77, 77, 0.1)'; 
                }
            ?>
                <div class="notif-page-card <?php echo !$n['precteno'] ? 'unread' : ''; ?>" id="notif-row-<?php echo $n['id']; ?>">
                    <div class="notif-icon" style="background: <?php echo $barva; ?>;">
                        <?php echo $ikona; ?>
                    </div>
                    
                    <div class="notif-content">
                        <p>
                            <?php if ($n['typ'] !== 'duel_win' && $n['typ'] !== 'duel_lose'): ?>
                                <strong><?php echo htmlspecialchars($n['prezdivka'] ?: 'Neznámý uživatel'); ?></strong>
                            <?php endif; ?>
                            <?php 
                                if ($n['typ'] == 'lajk') echo " ti dal lajk na příspěvek.";
                                elseif ($n['typ'] == 'komentar') echo " ti napsal komentář pod příspěvek.";
                                elseif ($n['typ'] == 'follow') echo " tě začal sledovat.";
                                elseif ($n['typ'] == 'vyzva') echo " tě vyzval na souboj!";
                                elseif ($n['typ'] == 'duel_accept') echo " přijal tvou výzvu k souboji! Jdi do toho.";
                                elseif ($n['typ'] == 'duel_decline') echo " tvou výzvu k souboji bohužel odmítl.";
                                elseif ($n['typ'] == 'duel_win') echo "Gratulujeme! Vyhrál jsi souboj proti uživateli " . htmlspecialchars($n['prezdivka']) . "!";
                                elseif ($n['typ'] == 'duel_lose') echo "Souboj proti uživateli " . htmlspecialchars($n['prezdivka']) . " bohužel skončil tvou prohrou. Nevěš hlavu!";
                            ?>
                            <?php if(!$n['precteno']): ?><span class="notif-badge-new">Nové</span><?php endif; ?>
                        </p>
                        
                        <?php if ($n['typ'] == 'vyzva'): ?>
                            <div class="challenge-btns" style="margin-top: 10px; display: flex; gap: 10px;">
                                <button class="btn-acc" onclick="handleChallenge(<?php echo $n['id']; ?>, 'accept')">Přijmout</button>
                                <button class="btn-dec" onclick="handleChallenge(<?php echo $n['id']; ?>, 'decline')">Zrušit</button>
                            </div>
                        <?php endif; ?>

                        <span class="notif-time"><?php echo date('j. n. Y \v H:i', strtotime($n['vytvoren'])); ?></span>
                    </div>

                    <button class="btn-delete-notif" onclick="deleteNotif(<?php echo $n['id']; ?>)" title="Smazat">✕</button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function deleteNotif(id) {
    if(!confirm('Opravdu smazat toto oznámení?')) return;
    
    fetch('hromadne_akce_notif.php?action=delete&id=' + id)
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            const row = document.getElementById('notif-row-' + id);
            row.style.opacity = '0';
            row.style.transform = 'translateX(20px)';
            setTimeout(() => row.remove(), 300);
        }
    });
}

function bulkAction(type) {
    let msg = type === 'read_all' ? 'Označit vše jako přečtené?' : 'Opravdu smazat celou historii?';
    if(!confirm(msg)) return;

    fetch('hromadne_akce_notif.php?action=' + type)
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            location.reload();
        }
    });
}
</script>

</body>
</html>
