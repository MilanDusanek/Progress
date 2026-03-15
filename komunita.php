<?php
session_start();
require 'db.php';

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}

$prihlaseny_id = $_SESSION['uzivatel_id'];
$view = $_GET['view'] ?? 'vse';

try {
    $base_sql = "SELECT p.*, pr.prezdivka, pr.profilovy_obrazek,
                (SELECT COUNT(*) FROM lajky WHERE prispevek_id = p.id) as pocet_lajku,
                (SELECT COUNT(*) FROM lajky WHERE prispevek_id = p.id AND uzivatel_id = :moje_id) as lajknuto_mnou
                FROM prispevky p
                LEFT JOIN profily pr ON p.uzivatel_id = pr.uzivatel_id";

    if ($view === 'following') {
        $sql = $base_sql . " JOIN sledujici s ON p.uzivatel_id = s.sledovany_id
                            WHERE s.sledujici_id = :moje_id
                            ORDER BY p.vytvoren DESC";
    } else {
        $sql = $base_sql . " ORDER BY p.vytvoren DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['moje_id' => $prihlaseny_id]);
    $prispevky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql_k = "SELECT k.*, pr.prezdivka, pr.profilovy_obrazek 
              FROM komentare k
              LEFT JOIN profily pr ON k.uzivatel_id = pr.uzivatel_id
              ORDER BY k.vytvoren ASC";
    $stmt_k = $pdo->query($sql_k);
    $vsechny_komentare = $stmt_k->fetchAll(PDO::FETCH_ASSOC);

    $komentare_podle_prispevku = [];
    foreach ($vsechny_komentare as $koment) {
        $komentare_podle_prispevku[$koment['prispevek_id']][] = $koment;
    }

} catch (PDOException $e) {
    die("Chyba databáze: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Komunita | Progress</title>
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        .main-content {
            width: 100%;
            max-width: 650px; /* Užší a modernější feed jako na sociálních sítích */
            margin: 0 auto;
            padding: 120px 20px 50px 20px;
            box-sizing: border-box;
            position: relative;
            z-index: 10;
        }

        /* ===== TVORBA PŘÍSPĚVKU ===== */
        .post-create-box {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 35px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .post-create-box:focus-within {
            border-color: white;
        }

        .post-textarea {
            width: 100%;
            min-height: 50px;
            background: transparent;
            border: none;
            color: white;
            padding: 5px 0;
            font-size: 1.1rem;
            resize: none;
            box-sizing: border-box;
            font-family: inherit;
        }

        .post-textarea:focus {
            outline: none;
        }

        .post-textarea::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        /* Oddělovač před tlačítky */
        .post-create-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 15px 0;
        }

        .post-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-upload {
            background: rgba(0, 255, 128, 0.1);
            border: none;
            color: #00ff80;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .btn-upload:hover {
            background: rgba(0, 255, 128, 0.2);
        }

        .btn-share {
            background: #00ff80;
            color: black;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-share:hover {
            background: #00e673;
            transform: scale(1.02);
            box-shadow: 0 0 20px rgba(0, 255, 128, 0.4);
        }

        /* ===== KARTY PŘÍSPĚVKŮ ===== */
        .post-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            color: white;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .post-card:hover {
            border-color: rgba(0, 255, 128, 0.35);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.05);
        }

        .post-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .post-header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .author-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .author-img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(0, 255, 128, 0.3);
        }

        .author-info {
            display: flex;
            flex-direction: column;
        }

        .author-info h4 {
            margin: 0;
            color: white;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .post-date {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.4);
            margin-top: 2px;
        }

        .btn-follow {
            text-decoration: none;
            font-size: 0.75rem;
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid #00ff80;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-challenge {
            text-decoration: none; 
            font-size: 0.75rem; 
            padding: 6px 14px; 
            border-radius: 20px; 
            border: 1px solid #ffaa00; 
            color: #ffaa00; 
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-challenge:hover {
            background: rgba(255, 170, 0, 0.1);
        }

        .post-content {
            margin-bottom: 15px;
            font-size: 0.95rem;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.9);
            word-wrap: break-word;
        }

        .post-image {
            width: 100%;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .post-footer {
            display: flex;
            gap: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 12px;
        }

        .action-btn {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
            padding: 5px 10px;
            border-radius: 8px;
        }

        .action-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .action-btn.active {
            color: #00ff80;
        }
        
        .action-btn.active:hover {
            background: rgba(0, 255, 128, 0.1);
        }

        /* ===== KOMENTÁŘE ===== */
        .comments-wrapper {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out, opacity 0.3s;
            opacity: 0;
        }

        .comments-wrapper.open {
            max-height: 2000px;
            opacity: 1;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .comment-item {
            margin-bottom: 12px;
            font-size: 0.85rem;
            background: rgba(0,0,0,0.2);
            padding: 10px 15px;
            border-radius: 12px;
            border-left: 2px solid rgba(0, 255, 128, 0.3);
            color: rgba(255,255,255,0.8);
            line-height: 1.4;
        }

        .comment-item strong {
            color: #00ff80;
            margin-right: 5px;
            font-weight: 600;
        }

        .comment-form {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .comment-input {
            flex: 1;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid #00ff80;
            border-radius: 10px;
            color: white;
            padding: 10px 15px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .comment-input:focus {
            border-color: white;
            background: rgba(0, 0, 0, 0.6);
        }

        .btn-comment {
            background: #00ff80;
            color: black;
            border: none;
            border-radius: 50%;
            width: 38px;
            height: 38px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .btn-comment:hover {
            transform: scale(1.05);
            background: #00e673;
            box-shadow: 0 0 15px rgba(0, 255, 128, 0.4);
        }

        #post-image-input {
            display: none;
        }

        /* ===== FILTRY Nahoře ===== */
        .feed-filter {
            display: flex;
            justify-content: center;
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 30px;
            padding: 5px;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
            backdrop-filter: blur(10px);
        }

        .filter-link {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 8px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .filter-link:hover {
            color: white;
        }

        .filter-link.active {
            color: black;
            background: #00ff80;
        }

        /* --- MOBILE RESPONSIVE --- */
        @media (max-width: 768px) {
            .main-content {
                padding-top: 100px;
                padding-left: 15px;
                padding-right: 15px;
                width: 100%;
                overflow-x: hidden;
                box-sizing: border-box;
            }
            .post-actions {
                flex-direction: column;
                gap: 15px;
            }
            .btn-upload, .btn-share {
                width: 100%;
                justify-content: center;
                box-sizing: border-box;
            }
        }
    </style>
</head>

<body>

    <?php include 'pozadi.php'; ?>

    <main class="main-content">

        <div class="post-create-box">
            <?php if ($ukazat_tecku): ?>
                <div style="text-align: center; padding: 20px; color: rgba(255,255,255,0.6);">
                    <img src="img/zamek.png" style="width: 40px; opacity: 0.5; margin-bottom: 10px;">
                    <p style="font-weight: 800; text-transform: uppercase; letter-spacing: 2px; color: #00ff80;">Profil není dokončen</p>
                    <p style="font-size: 0.85rem;">Pro přidávání příspěvků si nejdříve dokonči svůj profil.</p>
                    <a href="uzivatel.php" class="btn-share" style="display: inline-block; text-decoration: none; margin-top: 10px;">DOPLNIT PROFIL</a>
                </div>
            <?php else: ?>
                <form action="uloz_prispevek.php" method="POST" enctype="multipart/form-data">
                    <textarea name="obsah" class="post-textarea" placeholder="CO JSI DNESKA DĚLAL?..."></textarea>
                    
                    <!-- Obal pro náhled fotky před nahráním -->
                    <div id="image-preview-container" style="display: none; margin-top: 15px; text-align: center;">
                        <img id="image-preview-element" src="" style="max-width: 100%; max-height: 300px; border-radius: 10px; border: 1px solid #00ff80;">
                    </div>

                    <div class="post-actions">
                        <label for="post-image-input" class="btn-upload">NAHRÁT FOTKU</label>
                        <input type="file" name="obrazek" id="post-image-input" accept="image/*">
                        <span id="file-name" style="font-size: 0.8rem; color: #aaa; display:none;"></span>
                        <button type="submit" class="btn-share">SDÍLET</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="feed-filter">
            <a href="komunita.php?view=vse" class="filter-link <?php echo $view !== 'following' ? 'active' : ''; ?>">
                Všechny příspěvky
            </a>
            <a href="komunita.php?view=following"
                class="filter-link <?php echo $view === 'following' ? 'active' : ''; ?>">
                Sledovaní
            </a>
        </div>

        <div id="feed">
            <?php foreach ($prispevky as $p): ?>
                <div class="post-card">
                    <div class="post-header">
                        <div class="author-meta">
                            <img src="<?php echo htmlspecialchars($p['profilovy_obrazek'] ?: 'img/uzivatel.png'); ?>"
                                class="author-img">
                            <div class="author-info">
                                <h4><?php echo htmlspecialchars($p['prezdivka'] ?: 'Neznámý uživatel'); ?></h4>
                            </div>
                        </div>

                        <?php if ($p['uzivatel_id'] !== $prihlaseny_id): ?>
                            <?php
                            $stmt_f = $pdo->prepare("SELECT id FROM sledujici WHERE sledujici_id = ? AND sledovany_id = ?");
                            $stmt_f->execute([$prihlaseny_id, $p['uzivatel_id']]);
                            $sleduju = $stmt_f->fetch();
                            ?>
                            <div class="post-header-right">
                            <a href="follow.php?id=<?php echo $p['uzivatel_id']; ?>" class="btn-follow" style="color: <?php echo $sleduju ? 'black' : '#00ff80'; ?>; 
                                  background: <?php echo $sleduju ? '#00ff80' : 'transparent'; ?>;">
                                <?php echo $sleduju ? 'Sleduji' : '+ Sledovat'; ?>
                            </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="post-content">
                        <?php echo htmlspecialchars($p['obsah']); ?>
                    </div>

                    <?php if ($p['obrazek']): ?>
                        <img src="<?php echo htmlspecialchars($p['obrazek']); ?>" class="post-image">
                    <?php endif; ?>

                    <div class="post-date-container">
                        <span><?php echo date('j. n. Y H:i', strtotime($p['vytvoren'])); ?></span>
                    </div>

                    <div class="post-footer">
                        <button onclick="handleLike(<?php echo $p['id']; ?>, this)" 
                            class="action-btn <?php echo $p['lajknuto_mnou'] > 0 ? 'active' : ''; ?>"
                            style="text-decoration: none; background: transparent; border: none; cursor: pointer;">
                            ❤ <span class="like-count"><?php echo $p['pocet_lajku'] > 0 ? $p['pocet_lajku'] : ''; ?></span> To se mi líbí
                        </button>

                        <button class="action-btn" onclick="toggleComments(<?php echo $p['id']; ?>)">
                            💬 Komentáře
                        </button>
                    </div>
                    <div class="comments-wrapper" id="comments-<?php echo $p['id']; ?>">
                        <div class="comments-list">
                            <?php
                            $pid = $p['id'];
                            if (isset($komentare_podle_prispevku[$pid])):
                                foreach ($komentare_podle_prispevku[$pid] as $k): ?>
                                    <div class="comment-item" style="display: flex; align-items: flex-start; gap: 10px;">
                                        <img src="<?php echo htmlspecialchars($k['profilovy_obrazek'] ?: 'img/uzivatel.png'); ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(0,255,128,0.3);">
                                        <div>
                                            <strong style="color: #00ff80;"><?php echo htmlspecialchars($k['prezdivka'] ?: 'Neznámý'); ?>:</strong>
                                            <?php echo htmlspecialchars($k['obsah']); ?>
                                        </div>
                                    </div>
                                <?php endforeach;
                            endif; ?>
                        </div>

                        <?php if ($ukazat_tecku): ?>
                             <div style="text-align: center; padding: 10px; color: rgba(255,255,255,0.4); font-size: 0.8rem;">
                                Pro psaní komentářů si dokonči profil.
                             </div>
                        <?php else: ?>
                            <form action="uloz_komentar.php" method="POST" class="comment-form">
                                <input type="hidden" name="prispevek_id" value="<?php echo $p['id']; ?>">
                                <input type="text" name="obsah" class="comment-input" placeholder="NAPIŠ KOMENTÁŘ..." required
                                    autocomplete="off">
                                <button type="submit" class="btn-comment">➡</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach;

            if (empty($prispevky)): ?>
                <div class="post-card" style="text-align: center; color: #aaa;">
                    <p>Zatím tu nic není. <?php echo $view === 'following' ? 'Musíš někoho sledovat!' : ''; ?></p>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script>
        function handleLike(postId, btn) {
            fetch(`like.php?id=${postId}&ajax=1`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const countSpan = btn.querySelector('.like-count');
                    countSpan.innerText = data.novy_pocet > 0 ? data.novy_pocet : '';
                    
                    if (data.lajknuto_mnou) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                } else if (data.message === 'incomplete_profile') {
                    showToast('Pro lajkování si dokonči svůj profil.');
                } else {
                    showToast('Chyba při zpracování lajku.');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Chyba spojení se serverem.');
            });
        }

        // Komunita uses showToast from pozadi.php
     window.addEventListener('load', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('status')) {
        const status = urlParams.get('status');
        
        if (status === 'comment_ok') {
            showToast('Komentář přidán');
        } else if (status === 'post_ok') {
            showToast('Příspěvek sdílen');
        } else if (status === 'challenge_sent') {
            showToast('Výzva odeslána');
        } else if (status === 'incomplete_profile') {
            showToast('Nejdříve si dokonči svůj profil.');
        }
        
        // Vyčištění URL, aby toast nevyskakoval při každém refreshi
        const url = new URL(window.location);
        url.searchParams.delete('status');
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }
});

        function toggleComments(postId) {
            const area = document.getElementById('comments-' + postId);
            area.classList.toggle('open');
        }

        const fileInput = document.getElementById('post-image-input');
        const previewContainer = document.getElementById('image-preview-container');
        const previewElement = document.getElementById('image-preview-element');
        const fileNameSpan = document.getElementById('file-name');

        if(fileInput) {
            fileInput.addEventListener('change', function () {
                const file = this.files[0];
                if (file) {
                    fileNameSpan.textContent = file.name;
                    
                    // Nastavení náhledu:
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewElement.src = e.target.result;
                        previewContainer.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    fileNameSpan.textContent = '';
                    previewElement.src = '';
                    previewContainer.style.display = 'none';
                }
            });
        }
    </script>

</body>

</html>