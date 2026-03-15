<?php
session_start();

require_once 'db.php'; 

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}
$uzivatel_id = $_SESSION['uzivatel_id'];

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function respond($success, $msg = '') {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'error' => $success ? '' : $msg]);
        exit;
    } else {
        $key = $success ? 'success' : 'error';
        header("Location: dashboard.php?toast=profile_saved");
        exit;
    }
}


$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        respond(false, "Chyba: Nelze vytvořit složku pro fotky.");
    }
}

$profilovy_obrazek = null;


if (isset($_FILES['profilovka']) && $_FILES['profilovka']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profilovka'];
    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 5 * 1024 * 1024;

    if (!in_array($file['type'], $allowed_types)) {
        respond(false, "Povoleny jsou jen JPG a PNG.");
    }

    if ($file['size'] > $max_size) {
        respond(false, "Soubor je příliš velký.");
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $novy_nazev = $uzivatel_id . '_' . time() . '.' . $ext;
    $cilova_cesta = $upload_dir . $novy_nazev;

    if (move_uploaded_file($file['tmp_name'], $cilova_cesta)) {
        $profilovy_obrazek = $cilova_cesta;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $prezdivka = htmlspecialchars(trim($_POST['prezdivka']));
    $vek = (int)$_POST['vek'];
    $pohlavi = htmlspecialchars($_POST['pohlavi']);
    $vyska = (int)$_POST['vyska'];
    $vaha = (float)$_POST['vaha']; 
    
    if (empty($prezdivka) || empty($pohlavi)) {
        respond(false, "Vyplňte všechna pole.");
    }
    
    if ($vek < 15 || $vek > 99) respond(false, "Věk musí být mezi 15 a 99 lety.");
    if ($vyska < 100 || $vyska > 220) respond(false, "Výška musí být mezi 100 a 220 cm.");
    if ($vaha < 30 || $vaha > 200) respond(false, "Váha musí být mezi 30 a 200 kg.");

    try {
        $pdo->beginTransaction(); 

        $stmt_old_data = $pdo->prepare("SELECT profilovy_obrazek FROM profily WHERE uzivatel_id = :uid");
        $stmt_old_data->execute(['uid' => $uzivatel_id]);
        $old_profile_data = $stmt_old_data->fetch(PDO::FETCH_ASSOC);
        $exists = (bool)$old_profile_data;

        $params = [
            'uid' => $uzivatel_id,
            'prezdivka' => $prezdivka,
            'vek' => $vek,
            'pohlavi' => $pohlavi,
            'vyska' => $vyska,
            'vaha' => $vaha,
        ];
        
        if ($exists) {
            $sql = "UPDATE profily SET prezdivka = :prezdivka, vek = :vek, pohlavi = :pohlavi, vyska = :vyska, vaha = :vaha";
            if ($profilovy_obrazek) {
                $sql .= ", profilovy_obrazek = :profilovy_obrazek";
                $params['profilovy_obrazek'] = $profilovy_obrazek;
                if ($old_profile_data['profilovy_obrazek'] && file_exists($old_profile_data['profilovy_obrazek'])) {
                    @unlink($old_profile_data['profilovy_obrazek']);
                }
            }
            $sql .= " WHERE uzivatel_id = :uid";
        } else {
            $sql = "INSERT INTO profily (uzivatel_id, prezdivka, vek, pohlavi, vyska, vaha" . ($profilovy_obrazek ? ", profilovy_obrazek" : "") . ") 
                    VALUES (:uid, :prezdivka, :vek, :pohlavi, :vyska, :vaha" . ($profilovy_obrazek ? ", :profilovy_obrazek" : "") . ")";
            if ($profilovy_obrazek) $params['profilovy_obrazek'] = $profilovy_obrazek;
        }
        
        $stmt_update = $pdo->prepare($sql);
        $stmt_update->execute($params);

        
        $today = date('Y-m-d');
        $stmt_check_tracking = $pdo->prepare("SELECT count(*) FROM tracking WHERE uzivatel_id = :uid AND datum = :today");
        $stmt_check_tracking->execute(['uid' => $uzivatel_id, 'today' => $today]);
        
        if ($stmt_check_tracking->fetchColumn() > 0) {
            $sql_tracking = "UPDATE tracking SET vaha = :vaha WHERE uzivatel_id = :uid AND datum = :today";
        } else {
            $sql_tracking = "INSERT INTO tracking (uzivatel_id, datum, vaha) VALUES (:uid, :today, :vaha)";
        }
        
        $stmt_tracking = $pdo->prepare($sql_tracking);
        $stmt_tracking->execute(['uid' => $uzivatel_id, 'today' => $today, 'vaha' => $vaha]);

        $pdo->commit();
        respond(true, "Profil úspěšně uložen!");

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(false, "Chyba DB: " . $e->getMessage());
    }
}
?>