<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Podmínky & Soukromí | PROGRESS</title>
    <style>
        :root {
            --toxic-green: #00ff80;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            margin: 0; padding: 0;
            background-color: #000000; color: white;
            overflow-x: hidden;
            width: 100vw;
            min-height: 100vh;
        }

        .background-container {
            width: 100vw; height: 100vh; position: fixed;
            top: 0; left: 0; z-index: -1; overflow: hidden;
        }

        @keyframes float {
            0% { transform: translate(0, 0); }
            33% { transform: translate(40px, -60px); }
            66% { transform: translate(-30px, 30px); }
            100% { transform: translate(0, 0); }
        }

        .blob { 
            position: absolute; 
            border-radius: 50%; 
            opacity: 0.6; 
            filter: blur(140px); 
            animation: float 25s ease-in-out infinite;
        }

        .blob-1 { 
            width: 800px; height: 600px; 
            background-color: var(--toxic-green); 
            top: -10%; left: -5%; 
            animation-duration: 8s;
        }

        .blob-2 { 
            width: 900px; height: 900px; 
            background-color: var(--toxic-green); 
            bottom: -15%; right: -10%; 
            animation-duration: 12s; animation-delay: -6s;
        }

        .legal-container {
            max-width: 800px;
            margin: 100px auto 60px;
            padding: 40px;
            background: var(--glass-bg);
            backdrop-filter: blur(25px);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.9);
            position: relative;
        }

        h1, h2 { color: var(--toxic-green); font-weight: 800; letter-spacing: 1px; }
        h1 { font-size: 2rem; margin-bottom: 30px; text-align: center; }
        h2 { font-size: 1.2rem; margin-top: 30px; }
        p { margin-bottom: 15px; font-size: 0.95rem; }
        ul { margin-bottom: 20px; padding-left: 20px; }
        li { margin-bottom: 8px; }

        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--toxic-green);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .back-btn:hover { color: white; transform: translateX(-5px); }

        @media (max-width: 768px) {
            .legal-container {
                margin: 40px 15px 40px 15px;
                padding: 25px 20px;
                width: calc(100% - 30px);
                box-sizing: border-box;
            }
            h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="background-container">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <main class="legal-container">
        <a href="index.php" class="back-btn">← ZPĚT NA PŘIHLÁŠENÍ</a>

        <h1>ZÁSADY OCHRANY SOUKROMÍ</h1>
        
        <p>Vítejte v aplikaci <strong>PROGRESS</strong>. Vaše soukromí a bezpečnost vašich dat jsou pro nás klíčové. Tento dokument shrnuje, jak nakládáme s vašimi údaji.</p>

        <h2>1. Sbírané údaje</h2>
        <p>Pro fungování aplikace PROGRESS zpracováváme následující údaje:</p>
        <ul>
            <li>E-mailová adresa (slouží k přihlášení a ověření účtu).</li>
            <li>Profilové údaje (přezdívka, věk, výška, váha, pohlaví) – nezbytné pro výpočet kalorického příjmu a sledování pokroku.</li>
            <li>Data o aktivitě (záznamy měření, příspěvky v komunitě, progres ve výzvách).</li>
        </ul>

        <h2>2. Účel zpracování</h2>
        <p>Údaje využíváme výhradně pro:</p>
        <ul>
            <li>Personalizaci vašeho fitness plánu.</li>
            <li>Zajištění interakce v rámci komunity.</li>
            <li>Zasílání systémových oznámení (např. ověřovací kódy).</li>
        </ul>

        <h2>3. Zabezpečení dat</h2>
        <p>Veškerá hesla jsou v databázi šifrována pomocí bezpečného algoritmu <code>Bcrypt</code>. Vaše data nepředáváme žádným třetím stranám pro marketingové účely.</p>

        <h2>4. Práva uživatele</h2>
        <p>Máte právo kdykoliv požádat o export svých dat nebo o smazání svého účtu. V případě dotazů nás kontaktujte na naší podpoře.</p>

        <p style="margin-top: 40px; text-align: center; opacity: 0.5; font-size: 0.8rem;">
            Poslední aktualizace: <?php echo date('j. n. Y'); ?>
        </p>
    </main>
</body>
</html>
