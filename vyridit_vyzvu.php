<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['uzivatel_id'])) {
    $notif_id = filter_input(INPUT_POST, 'notif_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? ''; 
    $moje_id = $_SESSION['uzivatel_id']; // Soupeř (ten, co přijímá)

    if ($notif_id) {
        try {
            // 1. Zjistíme detaily o souboji z notifikace
            // Předpokládáme, že v tabulce oznameni máš odesilatel_id
            $stmt_notif = $pdo->prepare("SELECT odesilatel_id FROM public.oznameni WHERE id = ?");
            $stmt_notif->execute([$notif_id]);
            $vyzyvatel_id = $stmt_notif->fetchColumn();

            if ($action === 'accept') {
                // Najdeme souboj, který čeká na přijetí
                $stmt_duel = $pdo->prepare("SELECT id, challenge_id FROM public.challenge_souboje 
                                           WHERE vyzyvatel_id = ? AND souper_id = ? AND status = 'pending'");
                $stmt_duel->execute([$vyzyvatel_id, $moje_id]);
                $duel = $stmt_duel->fetch(PDO::FETCH_ASSOC);

                if ($duel) {
                    $cid = $duel['challenge_id'];

                    // A) AKTIVACE SOUBOJE
                    $upd = $pdo->prepare("UPDATE public.challenge_souboje SET status = 'active' WHERE id = ?");
                    $upd->execute([$duel['id']]);

                    // B) AKTIVACE ÚKOLU PRO OBA (pokud ho ještě nemají)
                    // Použijeme INSERT s ON CONFLICT (pokud máš unikátní klíč na uzivatel_id + challenge_id)
                    // Nebo jednodušší verzi:
                    $hraci = [$vyzyvatel_id, $moje_id];
                    foreach ($hraci as $hid) {
                        $check = $pdo->prepare("SELECT count(*) FROM public.uzivatele_challenge WHERE uzivatel_id = ? AND challenge_id = ?");
                        $check->execute([$hid, $cid]);
                        if ($check->fetchColumn() == 0) {
                            $ins = $pdo->prepare("INSERT INTO public.uzivatele_challenge (uzivatel_id, challenge_id, start_hodnota, aktualni_hodnota) VALUES (?, ?, 0, 0)");
                            $ins->execute([$hid, $cid]);
                        }
                    }
                }
            } else {
                // ODMÍTNUTÍ - smažeme souboj
                $del = $pdo->prepare("DELETE FROM public.challenge_souboje WHERE vyzyvatel_id = ? AND souper_id = ? AND status = 'pending'");
                $del->execute([$vyzyvatel_id, $moje_id]);
            }

            // 2. Notifikaci označíme jako vyřízenou
            $stmt = $pdo->prepare("UPDATE public.oznameni SET precteno = true WHERE id = ?");
            $stmt->execute([$notif_id]);

            echo json_encode(['success' => true, 'action' => $action]);
            exit;

        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}
echo json_encode(['success' => false, 'error' => 'Neplatný požadavek']);