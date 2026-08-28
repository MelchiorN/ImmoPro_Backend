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
        .header-icon {
            margin: 0 auto 14px;
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header-icon svg {
            color: #ffffff;
            width: 28px;
            height: 28px;
        }
        .header h1 {
            color: #ffffff;
            font-size: 22px;
            margin: 0;
            letter-spacing: 0.5px;
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
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .warning-icon {
            color: #92400e;
            flex-shrink: 0;
            margin-top: 1px;
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
            <!-- Icône clé / vérification -->
            <div class="header-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            <h1>ImmoPro — Vérification</h1>
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
                <span class="warning-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </span>
                <span>Ce code est valide pendant <strong>{{ $expiry }} minutes</strong>. Ne partagez ce code avec personne.</span>
            </div>

            <p style="margin-top:24px;">
                Si vous n'avez pas créé de compte sur ImmoPro, ignorez cet email.
            </p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} ImmoPro — Tous droits réservés<br>
            Cet email est automatique, merci de ne pas y répondre.
        </div>
    </div>
</body>
</html>
