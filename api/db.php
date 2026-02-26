<?php

$host = "localhost";

$dbname = "Progress";

$user = "postgres";

$password = "heslo";



try {

    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    die("Chyba připojení k databázi: " . $e->getMessage());

}



function aktualizujProgres($pdo, $uzivatel_id, $typ_vyzvy, $mnozstvi = 1) {


    $stmt = $pdo->prepare("SELECT id FROM public.challenge WHERE typ = ?");

    $stmt->execute([$typ_vyzvy]);

    $challenge_id = $stmt->fetchColumn();



    if (!$challenge_id) return;




    $stmt_upd = $pdo->prepare("

        UPDATE public.uzivatele_challenge

        SET aktualni_hodnota = aktualni_hodnota + ?

        WHERE uzivatel_id = ? AND challenge_id = ?

        RETURNING aktualni_hodnota

    ");

    $stmt_upd->execute([$mnozstvi, $uzivatel_id, $challenge_id]);

    $nova_hodnota = $stmt_upd->fetchColumn();




    if ($nova_hodnota !== false) {


        $konfigurace = [

            'login_10'       => 2,  

            'first_post'     => 1,    

            'likes_100'      => 100,  

            'weight_loss_10' => 10,

            'login_count'    => 2, 

            'default'        => 100  

        ];

       

        $cil = $konfigurace[$typ_vyzvy] ?? $konfigurace['default'];




        if ($nova_hodnota >= $cil) {

            $stmt_duel = $pdo->prepare("

                SELECT id, vyzyvatel_id, souper_id FROM public.challenge_souboje

                WHERE challenge_id = ? AND status = 'active'

                AND (vyzyvatel_id = ? OR souper_id = ?)

            ");

            $stmt_duel->execute([$challenge_id, $uzivatel_id, $uzivatel_id]);

            $duel = $stmt_duel->fetch(PDO::FETCH_ASSOC);



            if ($duel) {


                $stmt_win = $pdo->prepare("

                    UPDATE public.challenge_souboje

                    SET status = 'completed', vitez_id = ?

                    WHERE id = ?

                ");

                $stmt_win->execute([$uzivatel_id, $duel['id']]);

               


            }

        }

    }

}

?>
