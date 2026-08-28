<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Visite planifiée — ImmoPro</title>
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 0; }
    .wrapper { max-width: 580px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,62,126,0.10); }
    .header { background: linear-gradient(135deg, #003e7e 0%, #1a6bc4 100%); padding: 36px 40px; text-align: center; }
    .header-icon { margin: 0 auto 14px; width: 52px; height: 52px; background: rgba(255,255,255,0.15); border-radius: 14px; display: flex; align-items: center; justify-content: center; }
    .header-icon svg { color: #fff; width: 28px; height: 28px; }
    .header h1 { color: #fff; font-size: 20px; margin: 0; font-weight: 700; }
    .header p { color: rgba(255,255,255,0.75); font-size: 13px; margin: 6px 0 0; }
    .body { padding: 36px 40px; }
    .badge { display: inline-block; background: #e8f3ff; color: #003e7e; font-size: 12px; font-weight: 700; border-radius: 20px; padding: 4px 14px; margin-bottom: 20px; }
    h2 { color: #003e7e; font-size: 18px; margin: 0 0 16px; }
    p { color: #555; font-size: 14px; line-height: 1.7; margin: 0 0 16px; }
    .card { background: #f0f6ff; border-left: 4px solid #003e7e; border-radius: 8px; padding: 16px 20px; margin: 24px 0; }
    .card .row { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 10px; }
    .card .row:last-child { margin-bottom: 0; }
    .card .icon { color: #003e7e; width: 20px; flex-shrink: 0; margin-top: 2px; }
    .card .icon svg { width: 16px; height: 16px; }
    .card .label { font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .card .value { font-size: 14px; color: #1a1a2e; font-weight: 700; }
    .notes-box { background: #fffbea; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 18px; font-size: 13px; color: #78350f; margin-top: 16px; display: flex; gap: 10px; align-items: flex-start; }
    .notes-icon { color: #92400e; flex-shrink: 0; margin-top: 1px; }
    .notes-icon svg { width: 16px; height: 16px; }
    .footer { background: #f5f7fa; padding: 20px 40px; text-align: center; font-size: 11px; color: #aaa; border-top: 1px solid #eee; }
    .footer strong { color: #003e7e; }
  </style>
</head>
<body>
  <div class="wrapper">
    <!-- Header -->
    <div class="header">
      <div class="header-icon" aria-hidden="true">
        <!-- Icône calendrier -->
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
      </div>
      <h1>Visite planifiée</h1>
      <p>Votre bien va être visité par un agent ImmoPro</p>
    </div>

    <!-- Body -->
    <div class="body">
      <span class="badge">Notification de visite</span>
      <h2>Bonjour,</h2>
      <p>
        Un agent ImmoPro a planifié une visite sur votre bien. Voici les informations de la visite :
      </p>

      <!-- Détails visite -->
      <div class="card">
        <div class="row">
          <span class="icon" aria-hidden="true">
            <!-- Maison -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10"/>
            </svg>
          </span>
          <div>
            <div class="label">Bien concerné</div>
            <div class="value">{{ $bienTitre }}</div>
            @if($bienAdresse)
            <div style="font-size:12px;color:#666;margin-top:2px;">{{ $bienAdresse }}</div>
            @endif
          </div>
        </div>
        <div class="row">
          <span class="icon" aria-hidden="true">
            <!-- Calendrier -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
          </span>
          <div>
            <div class="label">Date et heure de la visite</div>
            <div class="value">{{ $dateVisite }}</div>
          </div>
        </div>
        <div class="row">
          <span class="icon" aria-hidden="true">
            <!-- Utilisateur -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </span>
          <div>
            <div class="label">Agent assigné</div>
            <div class="value">{{ $agentNom }}</div>
          </div>
        </div>
      </div>

      @if($notes)
      <div class="notes-box">
        <span class="notes-icon" aria-hidden="true">
          <!-- Crayon / note -->
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
        </span>
        <div><strong>Note de l'agent :</strong><br>{{ $notes }}</div>
      </div>
      @endif

      <p style="margin-top:24px;">
        Cette visite fait partie du processus de vérification de votre annonce. Votre agent vous contactera
        si des informations complémentaires sont nécessaires.
      </p>
      <p>
        Merci de votre confiance,<br>
        <strong style="color:#003e7e;">L'équipe ImmoPro</strong>
      </p>
    </div>

    <!-- Footer -->
    <div class="footer">
      <p>&copy; {{ date('Y') }} <strong>ImmoPro</strong> — Plateforme immobilière professionnelle</p>
      <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
    </div>
  </div>
</body>
</html>
