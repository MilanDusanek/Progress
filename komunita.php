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
                JOIN profily pr ON p.uzivatel_id = pr.uzivatel_id";

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
              JOIN profily pr ON k.uzivatel_id = pr.uzivatel_id
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
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .main-content {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 80px 20px 20px 20px;
            box-sizing: border-box;
            position: relative;
            z-index: 10;
        }

        /* BOX PRO TVORBU PŘÍSPĚVKU */
        .post-create-box {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(0, 255, 128, 0.3);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            margin-top: 5%;
        }

        .post-textarea {
            width: 100%;
            min-height: 80px;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid #00ff80;
            border-radius: 8px;
            color: white;
            padding: 12px;
            font-size: 1rem;
            resize: none;
            box-sizing: border-box;
        }

        .post-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        .btn-upload {
            background: transparent;
            border: 1px solid #00ff80;
            color: #00ff80;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-share {
            background: #00ff80;
            color: black;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }

        /* KARTY PŘÍSPĚVKŮ */
        .post-card {
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            color: white;
        }

    .post-header {
    display: flex;
    align-items: center;
    justify-content: space-between; /* Jméno vlevo, zbytek vpravo */
    margin-bottom: 15px;
}

.post-header-right {
    display: flex;
    align-items: center;
    gap: 10px; /* Mezera mezi tlačítkem výzvy a časem/follow buttonem */
}

        .author-meta {
            display: flex;
            align-items: center;
        }

        .author-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid #00ff80;
            object-fit: cover;
            margin-right: 12px;
        }

        .author-info h4 {
            margin: 0;
            color: #00ff80;
        }

        .btn-follow {
            text-decoration: none;
            font-size: 0.75rem;
            padding: 5px 12px;
            border-radius: 6px;
            border: 1px solid #00ff80;
            transition: 0.3s;
            font-weight: bold;
        }

        .post-content {
            margin-bottom: 15px;
            text-align: left;
            margin-left: 2%;
        }

        .post-image {
            width: 100%;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .post-date-container {
            text-align: right;
            margin-bottom: 5px;
        }

        .post-date-container span {
            font-size: 0.75rem;
            color: #aaa;
        }

        .post-footer {
            display: flex;
            gap: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 10px;
        }

        .action-btn {
            background: none;
            border: none;
            color: #aaa;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: 0.3s;
        }

        .action-btn:hover {
            color: #00ff80;
        }

        .action-btn.active {
            color: #00ff80;
            font-weight: bold;
        }

        /* ANIMOVANÉ KOMENTÁŘE */
        .comments-wrapper {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s cubic-bezier(0, 1, 0, 1);
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }

        .comments-wrapper.open {
            max-height: 1000px;
            transition: max-height 1s ease-in-out;
            margin-top: 15px;
            padding: 15px;
        }

        .comment-item {
            margin-bottom: 10px;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 5px;
            text-align: left;
        }

        .comment-form {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }

        .comment-input {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 255, 128, 0.4);
            border-radius: 5px;
            color: white;
            padding: 8px;
        }

        .btn-comment {
            background: #00ff80;
            border: none;
            border-radius: 5px;
            width: 35px;
            cursor: pointer;
            font-weight: bold;
        }

        #post-image-input {
            display: none;
        }

        .feed-filter {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-link {
            color: #aaa;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid transparent;
            transition: 0.3s;
        }

        .filter-link:hover {
            color: #00ff80;
        }

        .filter-link.active {
            color: #00ff80;
            border-color: #00ff80;
            background: rgba(0, 255, 128, 0.1);
        }
    </style>
</head>

<body>

    <?php include 'pozadi.php'; ?>

    <main class="main-content">

        <div class="post-create-box">
            <form action="uloz_prispevek.php" method="POST" enctype="multipart/form-data">
                <textarea name="obsah" class="post-textarea" placeholder="Co si dneska dělal?..."></textarea>
                <div class="post-actions">
                    <label for="post-image-input" class="btn-upload">Nahrát fotku</label>
                    <input type="file" name="obrazek" id="post-image-input" accept="image/*">
                    <span id="file-name" style="font-size: 0.8rem; color: #aaa;"></span>
                    <button type="submit" class="btn-share">Sdílet</button>
                </div>
            </form>
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
                            <img src="<?php echo htmlspecialchars($p['profilovy_obrazek'] ?: 'img/userIcon.png'); ?>"
                                class="author-img">
                            <div class="author-info">
                                <h4><?php echo htmlspecialchars($p['prezdivka']); ?></h4>
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
                            <a href="poslat_vyzvu.php?souper_id=<?php echo $p['uzivatel_id']; ?>"
                                style="text-decoration: none; font-size: 0.75rem; padding: 5px 12px; border-radius: 6px; border: 1px solid #ffaa00; color: #ffaa00; margin-left: 5px; font-weight: bold;">
                                Vyzvat
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
                        <a href="like.php?id=<?php echo $p['id']; ?>"
                            class="action-btn <?php echo $p['lajknuto_mnou'] > 0 ? 'active' : ''; ?>"
                            style="text-decoration: none;">
                            ❤ <?php echo $p['pocet_lajku'] > 0 ? $p['pocet_lajku'] : ''; ?> To se mi líbí
                        </a>

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
                                    <div class="comment-item">
                                        <strong style="color: #00ff80;"><?php echo htmlspecialchars($k['prezdivka']); ?>:</strong>
                                        <?php echo htmlspecialchars($k['obsah']); ?>
                                    </div>
                                <?php endforeach;
                            endif; ?>
                        </div>

                        <form action="uloz_komentar.php" method="POST" class="comment-form">
                            <input type="hidden" name="prispevek_id" value="<?php echo $p['id']; ?>">
                            <input type="text" name="obsah" class="comment-input" placeholder="Napiš komentář..." required
                                autocomplete="off">
                            <button type="submit" class="btn-comment">➡</button>
                        </form>
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
        function triggerToast(message) {
            const toast = document.getElementById('statusToast');
            if (toast) {
                toast.innerText = message;
                toast.classList.add('show');

                setTimeout(() => {
                    toast.classList.remove('show');
                    const url = new URL(window.location);
                    url.searchParams.delete('status');
                    window.history.replaceState({}, document.title, url.pathname + url.search);
                }, 3500);
            }
        }

     window.addEventListener('load', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('status')) {
        const status = urlParams.get('status');
        
        if (status === 'comment_ok') {
            triggerToast('Komentář byl úspěšně přidán! ');
        } else if (status === 'post_ok') {
            triggerToast('Příspěvek byl sdílen! ');
        } else if (status === 'challenge_sent') {
            triggerToast('Výzva byla odeslána! ', 'warning'); 
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

        document.getElementById('post-image-input')?.addEventListener('change', function () {
            document.getElementById('file-name').textContent = this.files[0] ? this.files[0].name : '';
        });
    </script>

</body>

</html>