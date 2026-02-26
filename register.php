<?php
require 'db.php'; // připojení k databázi

// Načtení PHPMailer knihovny
require __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/PHPMailer-master/src/SMTP.php';
require __DIR__ . '/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$zprava = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $heslo = password_hash($_POST['heslo'], PASSWORD_DEFAULT);
$kod = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT); // např. "04217"

    $stmt = $pdo->prepare("INSERT INTO uzivatele (email, heslo, overovaci_kod) VALUES (:email, :heslo, :kod)");
    try {
        $stmt->execute(['email' => $email, 'heslo' => $heslo, 'kod' => $kod]);

        // Ověřovací odkaz
       // $odkaz = "http://localhost/workspace/PROGRESS/verify.php?email=" . urlencode($email) . "&kod=" . $kod;

        // Odeslání e-mailu přes PHPMailer
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply.progressx@gmail.com';
        $mail->Password = 'rgns obkz rkfz hjla'; 
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->SMTPOptions = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
];

        $mail->setFrom('noreply.progressx@gmail.com', 'PROGRESS');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Ověření účtu';
        $mail->Body = "Děkujeme za registraci! Tvůj ověřovací kód je: <strong>$kod</strong><br>Zadej ho na stránce pro ověření.";

        $mail->send();
        $zprava = "✅ Registrace proběhla. Zkontroluj e-mail pro ověření.";
    } catch (Exception $e) {
        $zprava = "❌ E-mail se nepodařilo odeslat: {$mail->ErrorInfo}";
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Registrace</title>
  <style>
    body { font-family: sans-serif; padding: 2em; background: #f4f4f4; }
    form { background: white; padding: 2em; border-radius: 8px; max-width: 400px; margin: auto; }
    input, button { display: block; width: 100%; margin-bottom: 1em; padding: 0.5em; }
  </style>
</head>
<body>
  <h2 style="text-align:center;">Registrace uživatele</h2>
  <form method="POST">
    <input type="email" name="email" placeholder="E-mail" required>
    <input type="password" name="heslo" placeholder="Heslo" required>
    <button type="submit">Registrovat</button>
  </form>

  <?php if ($zprava): ?>
    <p style="text-align:center; margin-top:1em;"><?php echo $zprava; ?></p>
  <?php endif; ?>
</body>
</html>
