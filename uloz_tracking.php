<?php
session_start();

if (!isset($_SESSION['uzivatel_id'])) {
    header("Location: login.php");
    exit;
}
$uzivatel_id = $_SESSION['uzivatel_id'];

$host = "localhost";
$dbname = "Progress";
$user = "postgres";
$password = "heslo";
$dsn = "pgsql:host=$host;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header("Location: tracking.php?status=error&msg=" . urlencode("Chyba připojení k databázi."));
    exit;
}

$datum = strip_tags(trim($_POST['datum']));
$vaha = empty($_POST['vaha']) ? null : (float)$_POST['vaha']; 
$pas = empty($_POST['pas']) ? null : (float)$_POST['pas'];
$boky = empty($_POST['boky']) ? null : (float)$_POST['boky'];
$biceps = empty($_POST['biceps']) ? null : (float)$_POST['biceps'];

try {

    $sql = "INSERT INTO tracking 
                (uzivatel_id, datum, vaha, pas, boky, biceps) 
            VALUES 
                (:uid, :datum, :vaha, :pas, :boky, :biceps)
            ON CONFLICT (uzivatel_id, datum) 
            DO UPDATE SET 
                vaha = COALESCE(EXCLUDED.vaha, tracking.vaha),
                pas = COALESCE(EXCLUDED.pas, tracking.pas),
                boky = COALESCE(EXCLUDED.boky, tracking.boky),
                biceps = COALESCE(EXCLUDED.biceps, tracking.biceps)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'uid' => $uzivatel_id,
        'datum' => $datum,
        'vaha' => $vaha,
        'pas' => $pas,
        'boky' => $boky,
        'biceps' => $biceps
    ]);

    
    $updateProfileSql = "UPDATE profily 
                         SET vaha = (
                             SELECT vaha FROM tracking 
                             WHERE uzivatel_id = :uid AND vaha IS NOT NULL 
                             ORDER BY datum DESC, id DESC LIMIT 1
                         ) 
                         WHERE uzivatel_id = :uid";
    
    $stmtUpdate = $pdo->prepare($updateProfileSql);
    $stmtUpdate->execute(['uid' => $uzivatel_id]);

    header("Location: tracking.php?status=success&msg=" . urlencode("Měření bylo uloženo a profil aktualizován!"));   
    exit;

} catch (PDOException $e) {
    $errorMessage = "Chyba při zápisu do databáze: " . $e->getMessage();
    header("Location: tracking.php?status=error&msg=" . urlencode($errorMessage));
    exit;
}
?>