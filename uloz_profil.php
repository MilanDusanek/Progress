<?php
session_start();


$host = "localhost";
$dbname = "Progress";
$user = "postgres";
$password = "heslo";

$dsn = "pgsql:host=$host;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header("Location: uzivatel.php?status=error&msg=" . urlencode("Chyba DB: " . $e->getMessage()));
    exit;
}

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}
$uzivatel_id = $_SESSION['uzivatel_id'];


$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        header("Location: uzivatel.php?status=error&msg=" . urlencode("Chyba: Složka pro nahrávání neexistuje a nelze ji vytvořit."));
        exit;
    }
}


$profilovy_obrazek = null;

if (isset($_FILES['profilovka']) && $_FILES['profilovka']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profilovka'];

    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 5 * 1024 * 1024; 

    $file_type = $file['type'];
    $file_velikost = $file['size'];
    $file_temp = $file['tmp_name'];

    if (!in_array($file_type, $allowed_types)) {
        header("Location: uzivatel.php?status=error&msg=" . urlencode("Nepovolený typ souboru. Povoleny jsou pouze JPG a PNG."));
        exit;
    }

    if ($file_velikost > $max_size) {
        header("Location: uzivatel.php?status=error&msg=" . urlencode("Soubor je příliš velký (max 5 MB)."));
        exit;
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $novy_nazev = $uzivatel_id . '_' . time() . '.' . $ext;
    $cilova_cesta = $upload_dir . $novy_nazev;

    if (move_uploaded_file($file_temp, $cilova_cesta)) {
        $profilovy_obrazek = $cilova_cesta;
        
       
    } else {
        header("Location: uzivatel.php?status=error&msg=" . urlencode("Chyba při nahrávání souboru na server."));
        exit;
    }
}



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $prezdivka = filter_input(INPUT_POST, 'prezdivka', FILTER_SANITIZE_STRING);
    $vek = filter_input(INPUT_POST, 'vek', FILTER_VALIDATE_INT);
    $pohlavi = filter_input(INPUT_POST, 'pohlavi', FILTER_SANITIZE_STRING);
    $vyska = filter_input(INPUT_POST, 'vyska', FILTER_VALIDATE_INT);
    $vaha = filter_input(INPUT_POST, 'vaha', FILTER_VALIDATE_FLOAT); // Získáme aktuálně zadanou váhu
    
    if (!$prezdivka || $vek === false || $vyska === false || $vaha === false || !$pohlavi) {
        header("Location: uzivatel.php?status=error&msg=" . urlencode("Neplatné nebo chybějící vstupní údaje."));
        exit;
    }

    try {
        $pdo->beginTransaction(); 
        

        $stmt_old_data = $pdo->prepare("SELECT vaha, profilovy_obrazek FROM profily WHERE uzivatel_id = :uid");
        $stmt_old_data->execute(['uid' => $uzivatel_id]);
        $old_profile_data = $stmt_old_data->fetch(PDO::FETCH_ASSOC);
        $old_weight = $old_profile_data['vaha'] ?? null;
        $old_image_path = $old_profile_data['profilovy_obrazek'] ?? null;
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
            $sql = "UPDATE profily SET 
                        prezdivka = :prezdivka,
                        vek = :vek,
                        pohlavi = :pohlavi,
                        vyska = :vyska,
                        vaha = :vaha";
            
            if ($profilovy_obrazek) {
                $sql .= ", profilovy_obrazek = :profilovy_obrazek";
                $params['profilovy_obrazek'] = $profilovy_obrazek;
            }
            
            $sql .= " WHERE uzivatel_id = :uid";
            
        } else {
            
            $insert_columns = "uzivatel_id, prezdivka, vek, pohlavi, vyska, vaha";
            $insert_values = ":uid, :prezdivka, :vek, :pohlavi, :vyska, :vaha";
            
            if ($profilovy_obrazek) {
                $insert_columns .= ", profilovy_obrazek";
                $insert_values .= ", :profilovy_obrazek";
                $params['profilovy_obrazek'] = $profilovy_obrazek;
            }

            $sql = "INSERT INTO profily ({$insert_columns}) VALUES ({$insert_values})";
        }
        
        $stmt_update = $pdo->prepare($sql);
        $stmt_update->execute($params);

        
        if ($profilovy_obrazek && $old_image_path && file_exists($old_image_path)) {
            @unlink($old_image_path);
        }
        
    
        
        $today = date('Y-m-d');
        
        $stmt_check_tracking = $pdo->prepare("SELECT vaha FROM tracking WHERE uzivatel_id = :uid AND datum = :today");
        $stmt_check_tracking->execute(['uid' => $uzivatel_id, 'today' => $today]);
        $existing_today_weight = $stmt_check_tracking->fetchColumn();

   
        
        if ($existing_today_weight === false || (float)$existing_today_weight !== (float)$vaha) {
            
            if ($existing_today_weight === false) {
                 $sql_tracking = "INSERT INTO tracking (uzivatel_id, datum, vaha) 
                                 VALUES (:uid, :today, :vaha)";
            } else {
                 $sql_tracking = "UPDATE tracking SET vaha = :vaha WHERE uzivatel_id = :uid AND datum = :today";
            }
            
            $stmt_tracking = $pdo->prepare($sql_tracking);
            $stmt_tracking->execute([
                'uid' => $uzivatel_id, 
                'today' => $today, 
                'vaha' => $vaha
            ]);
        }


    
        $pdo->commit();
        
        if ($exists) {
            $zprava = "Profil byl úspěšně aktualizován!";
        } else {
            $zprava = "Účet byl úspěšně dokončen!";
        }
        
        header("Location: uzivatel.php?status=success&msg=" . urlencode($zprava));
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: uzivatel.php?status=error&msg=" . urlencode("Chyba DB: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: uzivatel.php?status=error&msg=" . urlencode("Neplatný přístup k souboru."));
    exit;
}
?>