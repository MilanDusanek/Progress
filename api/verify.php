<?php
require 'db.php';

$zprava = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $kod = $_POST['kod'];

    $stmt = $pdo->prepare("SELECT * FROM uzivatele WHERE email = :email AND overovaci_kod = :kod AND overeny = false");
    $stmt->execute(['email' => $email, 'kod' => $kod]);
    $uzivatel = $stmt->fetch();

    if ($uzivatel) {
        $update = $pdo->prepare("UPDATE uzivatele SET overeny = true WHERE email = :email");
        $update->execute(['email' => $email]);
        $zprava = "Účet byl úspěšně ověřen!";
    } else {
        $zprava = "Kód je neplatný nebo už byl použit.";
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Ověření účtu</title>
  <style>
    body { font-family: sans-serif; padding: 2em; background: #f4f4f4; }
    form { background: white; padding: 2em; border-radius: 8px; max-width: 400px; margin: auto; }
    input, button { display: block; width: 100%; margin-bottom: 1em; padding: 0.5em; }
  </style>
</head>
<body>
  <h2 style="text-align:center;">Ověření účtu</h2>
  <form method="POST">
    <input type="email" name="email" placeholder="E-mail" required>
    <input type="text" name="kod" placeholder="Ověřovací kód" required maxlength="5">
    <button type="submit">Ověřit</button>
  </form>

  <?php if ($zprava): ?>
    <p style="text-align:center; margin-top:1em;"><?php echo $zprava; ?></p>
  <?php endif; ?>
</body>
</html>
