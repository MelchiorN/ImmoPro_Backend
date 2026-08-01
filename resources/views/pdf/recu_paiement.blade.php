<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a2e; background: #fff; }

  .page { padding: 40px 50px; }

  /* ── En-tête ── */
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 36px; border-bottom: 3px solid #1a6cff; padding-bottom: 20px; }
  .logo-zone .app-name { font-size: 26px; font-weight: 800; color: #1a6cff; letter-spacing: -0.5px; }
  .logo-zone .app-sub  { font-size: 11px; color: #6b7280; margin-top: 2px; }
  .recu-badge { text-align: right; }
  .recu-badge .badge-label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
  .recu-badge .badge-num   { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-top: 2px; }
  .recu-badge .badge-date  { font-size: 11px; color: #6b7280; margin-top: 3px; }

  /* ── Titre central ── */
  .doc-title { text-align: center; margin-bottom: 28px; }
  .doc-title h1 { font-size: 20px; font-weight: 700; color: #1a1a2e; }
  .doc-title .statut-badge {
    display: inline-block; margin-top: 8px;
    padding: 4px 16px; border-radius: 20px;
    font-size: 12px; font-weight: 700; letter-spacing: 0.5px;
    background: #d1fae5; color: #065f46;
  }

  /* ── Section ── */
  .section { margin-bottom: 22px; }
  .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }

  /* ── Table infos ── */
  table.info { width: 100%; border-collapse: collapse; }
  table.info tr td { padding: 8px 0; vertical-align: top; }
  table.info tr td:first-child { color: #6b7280; width: 45%; font-size: 12px; }
  table.info tr td:last-child  { font-weight: 600; color: #1a1a2e; font-size: 13px; text-align: right; }
  table.info tr.highlight td:last-child { font-size: 18px; font-weight: 800; color: #1a6cff; }
  table.info tr + tr td { border-top: 1px solid #f3f4f6; }

  /* ── Pied de page ── */
  .footer { margin-top: 48px; padding-top: 16px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 10px; color: #9ca3af; line-height: 1.6; }
  .footer strong { color: #6b7280; }
</style>
</head>
<body>
<div class="page">

  <!-- En-tête -->
  <div class="header">
    <div class="logo-zone">
      <div class="app-name">ImmoPro</div>
      <div class="app-sub">Plateforme immobilière</div>
    </div>
    <div class="recu-badge">
      <div class="badge-label">Reçu de paiement</div>
      <div class="badge-num">{{ $recu->numero_recu }}</div>
      <div class="badge-date">Émis le {{ $dateEmission }}</div>
    </div>
  </div>

  <!-- Titre -->
  <div class="doc-title">
    <h1>Reçu de paiement</h1>
    <span class="statut-badge">✓ Paiement confirmé</span>
  </div>

  <!-- Détails transaction -->
  <div class="section">
    <div class="section-title">Détails de la transaction</div>
    <table class="info">
      <tr>
        <td>Type de paiement</td>
        <td>{{ $typeLabel }}</td>
      </tr>
      <tr>
        <td>Objet</td>
        <td>{{ $label }}</td>
      </tr>
      <tr class="highlight">
        <td>Montant payé</td>
        <td>{{ number_format($montant, 0, ',', ' ') }} FCFA</td>
      </tr>
      <tr>
        <td>Opérateur</td>
        <td>{{ $operateur }}</td>
      </tr>
      @if($reference)
      <tr>
        <td>Référence transaction</td>
        <td style="font-family: monospace; font-size: 11px;">{{ $reference }}</td>
      </tr>
      @endif
      <tr>
        <td>Date de paiement</td>
        <td>{{ $datePaiement }}</td>
      </tr>
    </table>
  </div>

  <!-- Bénéficiaire -->
  <div class="section">
    <div class="section-title">Bénéficiaire</div>
    <table class="info">
      <tr>
        <td>Nom complet</td>
        <td>{{ $nomClient }}</td>
      </tr>
      <tr>
        <td>Email</td>
        <td>{{ $emailClient }}</td>
      </tr>
    </table>
  </div>

  <!-- Pied de page -->
  <div class="footer">
    <p><strong>ImmoPro</strong> — Ce reçu est généré automatiquement et fait foi de paiement.</p>
    <p>Conservez ce document pour vos archives. En cas de litige, contactez notre support.</p>
    <p>Document généré le {{ $dateGeneration }}</p>
  </div>

</div>
</body>
</html>
