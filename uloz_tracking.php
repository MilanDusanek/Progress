<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['uzivatel_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
$uzivatel_id = $_SESSION['uzivatel_id'];

$datum = strip_tags(trim($_POST['datum'] ?? ''));
$vaha = empty($_POST['vaha']) ? null : (float)$_POST['vaha']; 
$pas = empty($_POST['pas']) ? null : (float)$_POST['pas'];
$boky = empty($_POST['boky']) ? null : (float)$_POST['boky'];
$biceps = empty($_POST['biceps']) ? null : (float)$_POST['biceps'];

if (empty($datum)) {
    echo json_encode(['success' => false, 'error' => 'Chybí datum']);
    exit;
}

if ($vaha !== null && ($vaha < 30 || $vaha > 200)) {
    echo json_encode(['success' => false, 'error' => 'Váha musí být mezi 30 a 200 kg.']);
    exit;
}
if ($pas !== null && ($pas < 20 || $pas > 200)) {
    echo json_encode(['success' => false, 'error' => 'Obvod pasu musí být mezi 20 a 200 cm.']);
    exit;
}
if ($boky !== null && ($boky < 20 || $boky > 200)) {
    echo json_encode(['success' => false, 'error' => 'Obvod boků musí být mezi 20 a 200 cm.']);
    exit;
}
if ($biceps !== null && ($biceps < 10 || $biceps > 100)) {
    echo json_encode(['success' => false, 'error' => 'Obvod bicepsu musí být mezi 10 a 100 cm.']);
    exit;
}

try {
    $sql = "INSERT INTO tracking 
                (uzivatel_id, datum, vaha, pas, boky, biceps) 
            VALUES 
                (:uid, :datum, :vaha, :pas, :boky, :biceps)
            ON DUPLICATE KEY UPDATE 
                vaha = IFNULL(VALUES(vaha), vaha),
                pas = IFNULL(VALUES(pas), pas),
                boky = IFNULL(VALUES(boky), boky),
                biceps = IFNULL(VALUES(biceps), biceps)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'uid' => $uzivatel_id,
        'datum' => $datum,
        'vaha' => $vaha,
        'pas' => $pas,
        'boky' => $boky,
        'biceps' => $biceps
    ]);

    if ($vaha !== null) {
        $updateProfileSql = "UPDATE profily SET vaha = :nova_vaha WHERE uzivatel_id = :uid";
        $stmtUpdate = $pdo->prepare($updateProfileSql);
        $stmtUpdate->execute(['nova_vaha' => $vaha, 'uid' => $uzivatel_id]);
        
        aktualizujProgres($pdo, $uzivatel_id, 'weight_loss_10', 1);
    }

    echo json_encode(['success' => true]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>