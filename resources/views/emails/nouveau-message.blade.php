<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nouveau message — ImmoPro</title>
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 0; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,62,126,0.10); }
    .header { background: linear-gradient(135deg, #003e7e 0%, #1a6bc4 100%); padding: 34px 40px; text-align: center; }
    .header-icon { margin: 0 auto 14px; width: 52px; height: 52px; background: rgba(255,255,255,0.15); border-radius: 14px; display: flex; align-items: center; justify-content: center; }
    .header-icon svg { color: #fff; width: 28px; height: 28px; }
    .header h1 { color: #fff; font-size: 20px; margin: 0; font-weight: 700; }
    .header p { color: rgba(255,255,255,0.75); font-size: 13px; margin: 6px 0 0; }
    .body { padding: 36px 40px; }
    .badge { display: inline-block; background: #e8f3ff; color: #003e7e; font-size: 12px; font-weight: 700; border-radius: 20px; padding: 4px 14px; margin-bottom: 20px; }
    h2 { color: #1a1a2e; font-size: 18px; margin: 0 0 14px; }
    p { color: #555; font-size: 14px; line-height: 1.7; margin: 0 0 16px; }
    .info-card { background: #f0f6ff; border-left: 4px solid #003e7e; border-radius: 10px; padding: 18px 22px; margin: 22px 0; }
    .info-row { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
    .info-row:last-child { margin-bottom: 0; }
    .info-icon { color: #003e7e; width: 20px; flex-shrink: 0; margin-top: 2px; }
    .info-icon svg { width: 16px; height: 16px; }
    .info-label { font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-value { font-size: 14px; color: #1a1a2e; font-weight: 700; margin-top: 2px; }
    .notice { background: #fff8e1; border: 1px solid #ffe082; border-radius: 10px; padding: 14px 18px; font-size: 13px; color: #795548; margin: 22px 0; display: flex; align-items: flex-start; gap: 10px; }
    .notice-icon { color: #92400e; flex-shrink: 0; margin-top: 1px; }
    .notice-icon svg { width: 18px; height: 18px; }
    .cta-btn { display: inline-block; background: #003e7e; color: #fff !important; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-size: 15px; font-weight: 700; margin-top: 8px; }
    .footer { background: #f5f7fa; padding: 20px 40px; text-align: center; font-size: 11px; color: #aaa; border-top: 1px solid #eee; }
    .footer strong { color: #003e7e; }
  </style>
</head>
<body>
  <div class="wrapper">

    <!-- En-tête -->
    <div class="header">
      <div class="header-icon" aria-hidden="true">
        <!-- Icône bulle de message -->
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
      </div>
      <h1>Nouveau message</h1>
      <p>Vous avez reçu un message sur ImmoPro</p>
    </div>

    <!-- Corps -->
    <div class="body">
      <span class="badge">Messagerie ImmoPro</span>
      <h2>Bonjour {{ $destinatairePrenom }},</h2>

      <p>
        <strong>{{ $expediteurNom }}</strong> vous a envoyé un nouveau message
        {{ $bienTitre ? 'concernant le bien <strong>"' . e($bienTitre) . '"</strong>' : 'sur ImmoPro' }}.
      </p>

      <!-- Résumé expéditeur -->
      <div class="info-card">
        <div class="info-row">
          <span class="info-icon" aria-hidden="true">
            <!-- Icône utilisateur -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </span>
          <div>
            <div class="info-label">Expéditeur</div>
            <div class="info-value">{{ $expediteurNom }}</div>
          </div>
        </div>
        @if($bienTitre)
        <div class="info-row">
          <span class="info-icon" aria-hidden="true">
            <!-- Icône maison -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10"/>
            </svg>
          </span>
          <div>
            <div class="info-label">Bien concerné</div>
            <div class="info-value">{{ $bienTitre }}</div>
          </div>
        </div>
        @endif
      </div>

      <!-- Notice confidentialité -->
      <div class="notice">
        <span class="notice-icon" aria-hidden="true">
          <!-- Icône cadenas -->
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
        </span>
        <span>
          Pour des raisons de confidentialité, le contenu du message n'est pas affiché dans cet email.
          Connectez-vous à votre espace pour lire et répondre au message.
        </span>
      </div>

      <p style="text-align:center;margin-top:28px;">
        <a href="{{ $appUrl }}/agent/messages" class="cta-btn">
          Lire le message &rarr;
        </a>
      </p>

      <p style="margin-top:28px;color:#888;font-size:13px;">
        Si vous recevez cet email par erreur, vous pouvez l'ignorer.
      </p>
    </div>

    <!-- Pied de page -->
    <div class="footer">
      <p>&copy; {{ date('Y') }} <strong>ImmoPro</strong> — Plateforme immobilière professionnelle</p>
      <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
    </div>

  </div>
</body>
</html>
