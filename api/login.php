<?php
require 'db.php'; 
session_start();

require __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/PHPMailer-master/src/SMTP.php';
require __DIR__ . '/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


$status = ''; 
$msg = '';
$action = $_GET['action'] ?? 'login';
$email_for_verification = '';

$email = ''; 
$heslo = '';
$heslo_potvrd = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $heslo = $_POST['heslo'] ?? '';
    $heslo_potvrd = $_POST['heslo_potvrd'] ?? '';

    if (isset($_POST['register_action'])) {
        $action = 'register'; 

        if ($heslo !== $heslo_potvrd) {
            $status = 'error';
            $msg = 'Hesla se neshodují! Zadejte prosím stejné heslo dvakrát.';
        } else {
            

            $kod = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            $hashed_heslo = password_hash($heslo, PASSWORD_DEFAULT);

            try {
                $stmt = $pdo->prepare("INSERT INTO uzivatele (email, heslo, overovaci_kod) VALUES (:email, :heslo, :kod)");
                $stmt->execute(['email' => $email, 'heslo' => $hashed_heslo, 'kod' => $kod]);

                $mail = new PHPMailer(true);
                $mail->CharSet = 'UTF-8';
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'noreply.progressx@gmail.com';
                $mail->Password = 'rgns obkz rkfz hjla';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

                $mail->setFrom('noreply.progressx@gmail.com', 'PROGRESS');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Ověření účtu';
                $mail->Body = "Děkujeme za registraci! Tvůj ověřovací kód je: <strong>$kod</strong><br>Zadej ho na stránce pro ověření.";
                $mail->send();
                
                $status = 'success';
                $msg = "Registrace proběhla. Zkontrolujte e-mail pro ověření a zadejte kód níže.";
                $action = 'verify'; 
                $email_for_verification = $email;

            } catch (Exception $e) {
                $status = 'error';
                $msg = "E-mail se nepodařilo odeslat. Zkuste to později. Chyba: " . $mail->ErrorInfo;
            } catch (PDOException $e) {
                if ($e->getCode() == '23505') { 
                    $status = 'error';
                    $msg = "Uživatel s tímto emailem již existuje.";
                } else {
                     $status = 'error';
                     $msg = "Chyba registrace.";
                }
            }
        }
    } elseif (isset($_POST['login_action'])) {
        $action = 'login'; 

        $stmt = $pdo->prepare("SELECT id, heslo, overovaci_kod FROM uzivatele WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $uzivatel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$uzivatel) {
            $status = 'error';
            $msg = "Tento e-mail není registrovaný.";
        } 
        elseif (!password_verify($heslo, $uzivatel['heslo'])) {
            $status = 'error';
            $msg = "Zadali jste nesprávné heslo.";
        } 
        else {
            if (empty($uzivatel['overovaci_kod']))  { 
                $_SESSION['uzivatel_id'] = $uzivatel['id'];
                aktualizujProgres($pdo, $uzivatel['id'], 'login_10');
                header("Location: dashboard.php");
                exit;
            } else {
                $status = 'error';
                $msg = "Účet není aktivován. Zadejte ověřovací kód, který vám přišel e-mailem.";
                $action = 'verify'; 
                $email_for_verification = $email; 
            }
        }
    
    } elseif (isset($_POST['resend_code_action'])) {
        $action = 'verify';
        $email_to_resend = $_POST['email_hidden'];
        
        $novy_kod = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        
        $stmt_update = $pdo->prepare("UPDATE uzivatele SET overovaci_kod = :kod WHERE email = :email");
        $stmt_update->execute(['kod' => $novy_kod, 'email' => $email_to_resend]);

        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'noreply.progressx@gmail.com';
            $mail->Password = 'rgns obkz rkfz hjla';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

            $mail->setFrom('noreply.progressx@gmail.com', 'PROGRESS');
            $mail->addAddress($email_to_resend);
            $mail->isHTML(true);
            $mail->Subject = 'Nový ověřovací kód';
            $mail->Body = "Vyžádali jste si nový kód. Váš NOVÝ ověřovací kód je: <strong>$novy_kod</strong>.";
            $mail->send();
            
            $status = 'success';
            $msg = "Nový ověřovací kód byl zaslán na {$email_to_resend}.";
            $email_for_verification = $email_to_resend;
            
        } catch (Exception $e) {
            $status = 'error';
            $msg = "Nepodařilo se odeslat nový e-mail. Zkuste to za chvíli.";
            $email_for_verification = $email_to_resend;
        }
        
    } elseif (isset($_POST['verify_action'])) { 
        $action = 'verify'; 
        
        $email_to_verify = $_POST['email_hidden']; 
        $zadan_kod = $_POST['verification_code'];
        $email_for_verification = $email_to_verify; 

        $stmt = $pdo->prepare("SELECT id, overovaci_kod FROM uzivatele WHERE email = :email");
        $stmt->execute(['email' => $email_to_verify]);
        $uzivatel = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($uzivatel && $uzivatel['overovaci_kod'] === $zadan_kod && $uzivatel['overovaci_kod'] !== NULL) {
            
            $stmt_activate = $pdo->prepare("
                UPDATE uzivatele 
                SET overovaci_kod = NULL, overeny = TRUE 
                WHERE id = :id
            ");
            $stmt_activate->execute(['id' => $uzivatel['id']]);
            
            $status = 'success';
            $msg = "Účet byl úspěšně ověřený! Nyní se můžete přihlásit.";
            $action = 'login'; 
        } else {
            $status = 'error';
            $msg = "Zadaný kód je neplatný. Zkuste to znovu.";
            $action = 'verify';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Přihlášení / Registrace</title>
    
    <style>

     body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex; 
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .background-container {
            width: 100vw;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #000000; 
            overflow: hidden; 
            z-index: -1;
        }

        .blob {
            position: absolute;
            border-radius: 50%; 
            opacity: 0.8; 
            filter: blur(120px); 
        }

        .blob-1 {
            width: 500px;
            height: 250px;
            background-color: #00ff80; 
            top: 5%;
            left: 15%;
        }

        .blob-2 {
            width: 800px;
            height: 800px;
            background-color: #00ff80; 
            top: 40%;
            right: 5%;
        }

        .auth-form-container {
            max-width: 400px;
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.1); 
            border-radius: 25px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5); 
            backdrop-filter: blur(5px);
            position: relative; 
            z-index: 20; 
            text-align: center;
            color: white;
        }

        .auth-form h2 {
            color: #00ff80;
            margin-bottom: 25px;
        }

        .auth-form input {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #00ff80;
            border-radius: 6px;
            background-color: rgba(0, 0, 0, 0.5); 
            color: white;
            box-sizing: border-box;
            font-size: 16px;
        }
        .auth-form input:focus {
    border-color: white; 
    outline: none;       
}

        .auth-button {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            background-color: #00ff80;
            color: #000000;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .auth-button:hover {
            background-color: #00e673;
        }
        
        .error-message {
            color: #ff4d4d;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .success-message {
            color: #00ff80;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .hidden-form {
            display: none;
        }

        .toggle-link {
            color: #00ff80;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .toggle-link:hover {
            color: #00e673;
        }

        .verification-input-container {
            display: flex;
            justify-content: center;
            gap: 10px; 
            margin: 30px 0;
        }

        .verification-input {
            width: 40px; 
            height: 40px; 
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            border: 2px solid #00ff80;
            border-radius: 6px;
            background-color: rgba(0, 0, 0, 0.5); 
            color: white;
            box-sizing: border-box;
            -moz-appearance: textfield; 
        }

        .verification-input::-webkit-inner-spin-button,
        .verification-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
    
</head>
<body>
    <div class="background-container">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    
    
    <main class="auth-form-container">
        <h2 id="form-title" class="auth-form">
            <?php 
            if ($action == 'register') echo 'REGISTRACE';
            elseif ($action == 'verify') echo 'OVĚŘENÍ ÚČTU';
            else echo 'PŘIHLÁŠENÍ';
            ?>
        </h2>
        
        <?php 
        
        if ($status && $msg) {
            $class = ($status == 'success') ? 'success-message' : 'error-message';
            echo "<p id='auth-message' class='{$class}'>" . htmlspecialchars($msg) . "</p>";
        }
        
        ?>

        <div id="login-form-wrapper" class="<?php echo ($action != 'login') ? 'hidden-form' : ''; ?>">
            <form method="POST" action="" class="auth-form"> 
                <input type="email" autocomplete="off" name="email" placeholder="E-mail" required>
                <input type="password" name="heslo" placeholder="Heslo" required>
                <input type="hidden" name="login_action" value="1"> 
                <button type="submit" class="auth-button">Přihlásit</button>
            </form>
            
            <p style="color: white; margin-top: 20px;">
                Ještě nemáš účet? 
                <span id="show-register" class="toggle-link">ZAREGISTRUJ SE</span>
            </p>
        </div>
        
        <div id="register-form-wrapper" class="<?php echo ($action != 'register') ? 'hidden-form' : ''; ?>">
            <form method="POST" action="" class="auth-form">
                <input type="email" autocomplete="off" name="email" placeholder="E-mail" required value="<?php echo htmlspecialchars($email); ?>"> 
                <input type="password" name="heslo" placeholder="Heslo" required>
                <input type="password" name="heslo_potvrd" placeholder="Potvrzení hesla" required>
                <input type="hidden" name="register_action" value="1">
                <button type="submit" class="auth-button">Zaregistrovat</button>
            </form>

            <p style="color: white; margin-top: 20px;">
                Už máš účet? 
                <span id="show-login" class="toggle-link">PŘIHLÁSIT SE</span>
            </p>
        </div>
        
    <div id="verify-form-wrapper" class="<?php echo ($action != 'verify') ? 'hidden-form' : ''; ?>">
            <form method="POST" action="" class="auth-form">
                <p style="color: white; margin-bottom: 20px;">
                    Zadejte 5místný kód, který Vám přišel na e-mail: **<?php echo htmlspecialchars($email_for_verification); ?>**
                </p>
                
                <div class="verification-input-container">
                    <input type="number" class="verification-input" maxlength="1" id="c1" required>
                    <input type="number" class="verification-input" maxlength="1" id="c2" required>
                    <input type="number" class="verification-input" maxlength="1" id="c3" required>
                    <input type="number" class="verification-input" maxlength="1" id="c4" required>
                    <input type="number" class="verification-input" maxlength="1" id="c5" required>
                    
                    <input type="hidden" name="verification_code" id="final_code">
                    
                    <input type="hidden" name="email_hidden" value="<?php echo htmlspecialchars($email_for_verification); ?>">
                </div>
                
                <button type="submit" class="auth-button" name="verify_action" value="1">
                    Ověřit účet
                </button>
                
                <p style="margin-top: 15px; color: #aaa;">
                    Kód nedorazil? 
                    <span id="resend-timer" style="color: #00ff80;">(Znovu za 60s)</span>
                </p>
                <button type="submit" id="resend-code-button" 
                        class="auth-button" style="background-color: #555; display: none;" 
                        name="resend_code_action" value="1" disabled
                        formnovalidate> 
                    Poslat kód znovu
                </button>
            </form>
        </div>
        
    </main>

    <script>
        const loginForm = document.getElementById('login-form-wrapper');
        const registerForm = document.getElementById('register-form-wrapper');
        const verifyForm = document.getElementById('verify-form-wrapper');
        const title = document.getElementById('form-title');

        function setUrlAction(action) {
            const url = new URL(window.location);
            url.searchParams.set('action', action);
            url.searchParams.delete('status');
            url.searchParams.delete('msg');
            window.history.pushState({}, '', url);
        }
        
        function switchForm(targetAction) {
            const messageElement = document.getElementById('auth-message');
            if (messageElement) {
                messageElement.style.display = 'none'; 
            }
            loginForm.classList.add('hidden-form');
            registerForm.classList.add('hidden-form');
            if (verifyForm) verifyForm.classList.add('hidden-form');

            if (targetAction === 'register') {
                registerForm.classList.remove('hidden-form');
                title.textContent = 'REGISTRACE';
            } else if (targetAction === 'login') {
                loginForm.classList.remove('hidden-form');
                title.textContent = 'PŘIHLÁŠENÍ';
            } else if (targetAction === 'verify' && verifyForm) {
                verifyForm.classList.remove('hidden-form');
                title.textContent = 'OVĚŘENÍ ÚČTU';
            }
            setUrlAction(targetAction);
        }

        if (document.getElementById('show-register')) {
            document.getElementById('show-register').addEventListener('click', () => switchForm('register'));
        }
        if (document.getElementById('show-login')) {
            document.getElementById('show-login').addEventListener('click', () => switchForm('login'));
        }

        if (verifyForm) {
            const inputs = verifyForm.querySelectorAll('.verification-input');
            const finalCodeInput = document.getElementById('final_code');

            inputs.forEach((input, index) => {
                
                input.addEventListener('input', (e) => {
                    if (input.value.length === 1) {
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    } else if (input.value.length > 1) {
                        input.value = input.value.slice(0, 1);
                    }
                    updateFinalCode();
                });
                
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && input.value.length === 0 && index > 0) {
                        inputs[index - 1].focus();
                        inputs[index - 1].value = ''; 
                        updateFinalCode();
                    }
                });
            });

            function updateFinalCode() {
                let code = '';
                inputs.forEach(input => {
                    code += input.value;
                });
                finalCodeInput.value = code;
            }

            if (!verifyForm.classList.contains('hidden-form')) {
                inputs[0].focus();
            }
        }

        const resendButton = document.getElementById('resend-code-button');
        const resendTimerDisplay = document.getElementById('resend-timer');

        if (verifyForm && !verifyForm.classList.contains('hidden-form') && resendButton) {
            let timeLeft = 60;

            function startTimer() {
                resendButton.style.display = 'none';
                resendButton.disabled = true;
                resendTimerDisplay.style.display = 'inline';
                timeLeft = 60; 

                const countdown = setInterval(() => {
                    timeLeft--;
                    resendTimerDisplay.textContent = `(Znovu za ${timeLeft}s)`;

                    if (timeLeft <= 0) {
                        clearInterval(countdown);
                        resendTimerDisplay.style.display = 'none';
                        resendButton.style.display = 'block'; 
                        resendButton.disabled = false;
                        resendButton.style.backgroundColor = '#00ff80';
                    }
                }, 1000);
            }

            startTimer();
        }
    </script>
</body>
</html>