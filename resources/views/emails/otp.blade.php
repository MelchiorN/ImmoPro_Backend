<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code de vérification ImmoPro</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            max-width: 520px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1a6fc4, #0ea9a9);
            padding: 32px 40px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 24px;
            margin: 0;
            letter-spacing: 1px;
        }
        .body {
            padding: 36px 40px;
        }
        .body p {
            color: #444;
            font-size: 15px;
            line-height: 1.6;
        }
        .otp-box {
            background: #f0f6ff;
            border: 2px dashed #1a6fc4;
            border-radius: 10px;
            text-align: center;
            padding: 20px 0;
            margin: 28px 0;
        }
        .otp-code {
            font-size: 42px;
            font-weight: 700;
            letter-spacing: 12px;
            color: #1a6fc4;
        }
        .warning {
            background: #fff8e1;
            border-left: 4px solid #f5a623;
            padding: 12px 16px;
            border-radius: 4px;
            color: #7d5a00;
            font-size: 13px;
        }
        .footer {
            background: #f4f6f9;
            padding: 20px 40px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>🏠 ImmoPro</h1>
        </div>

        <div class="body">
            <p>Bonjour,</p>
            <p>
                Vous avez créé un compte sur <strong>ImmoPro</strong>. Utilisez le code ci-dessous
                pour confirmer votre adresse email et activer votre compte.
            </p>

            <div class="otp-box">
                <div class="otp-code">{{ $otp }}</div>
            </div>

            <div class="warning">
                ⏱ Ce code est valide pendant <strong>{{ $expiry }} minutes</strong>.
                Ne partagez ce code avec personne.
            </div>

            <p style="margin-top:24px;">
                Si vous n'avez pas créé de compte sur ImmoPro, ignorez cet email.
            </p>
        </div>

        <div class="footer">
            © {{ date('Y') }} ImmoPro — Tous droits réservés<br>
            Cet email est automatique, merci de ne pas y répondre.
        </div>
    </div>
</body>
</html>
