<?php

namespace App\Services;

/**
 * Génère des templates HTML d'email cohérents avec la charte ImmoPro.
 */
class EmailTemplateService
{
    /**
     * Template email générique avec carte de détails.
     *
     * @param string $titre        Ex : "Votre rapport a été approuvé"
     * @param string $intro        Paragraphe d'introduction
     * @param array  $rows         Lignes de détails [['icon'=>'📋','label'=>'Bien','value'=>'...']]
     * @param string|null $noteBox Encadré note optionnel (jaune)
     * @param string|null $outro   Paragraphe de clôture
     */
    public static function generic(
        string $titre,
        string $intro,
        array $rows = [],
        ?string $noteBox = null,
        ?string $outro = null,
    ): string {
        $rowsHtml = '';
        foreach ($rows as $row) {
            $icon  = htmlspecialchars($row['icon']  ?? '');
            $label = htmlspecialchars($row['label'] ?? '');
            $value = htmlspecialchars($row['value'] ?? '');
            $rowsHtml .= <<<HTML
            <div class="row">
              <span class="icon">{$icon}</span>
              <div>
                <div class="label">{$label}</div>
                <div class="value">{$value}</div>
              </div>
            </div>
            HTML;
        }

        $cardHtml = $rowsHtml ? "<div class=\"card\">{$rowsHtml}</div>" : '';

        $noteHtml = $noteBox
            ? '<div class="notes-box"><strong>📝 Note :</strong><br>' . nl2br(htmlspecialchars($noteBox)) . '</div>'
            : '';

        $outroHtml = $outro
            ? '<p>' . nl2br(htmlspecialchars($outro)) . '</p>'
            : '';

        $year = date('Y');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width,initial-scale=1.0">
          <title>{$titre} — ImmoPro</title>
          <style>
            body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f7fa;margin:0;padding:0}
            .wrapper{max-width:580px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,62,126,.10)}
            .header{background:linear-gradient(135deg,#003e7e 0%,#1a6bc4 100%);padding:36px 40px;text-align:center}
            .header h1{color:#fff;font-size:22px;margin:0;font-weight:700}
            .header p{color:rgba(255,255,255,.75);font-size:13px;margin:6px 0 0}
            .body{padding:36px 40px}
            .badge{display:inline-block;background:#e8f3ff;color:#003e7e;font-size:12px;font-weight:700;border-radius:20px;padding:4px 14px;margin-bottom:20px}
            h2{color:#003e7e;font-size:18px;margin:0 0 16px}
            p{color:#555;font-size:14px;line-height:1.7;margin:0 0 16px}
            .card{background:#f0f6ff;border-left:4px solid #003e7e;border-radius:8px;padding:16px 20px;margin:24px 0}
            .row{display:flex;gap:12px;align-items:flex-start;margin-bottom:10px}
            .row:last-child{margin-bottom:0}
            .icon{font-size:18px;margin-top:1px}
            .label{font-size:11px;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
            .value{font-size:14px;color:#1a1a2e;font-weight:700}
            .notes-box{background:#fffbea;border:1px solid #fde68a;border-radius:8px;padding:14px 18px;font-size:13px;color:#78350f;margin-top:16px}
            .footer{background:#f5f7fa;padding:20px 40px;text-align:center;font-size:11px;color:#aaa;border-top:1px solid #eee}
            .footer strong{color:#003e7e}
          </style>
        </head>
        <body>
          <div class="wrapper">
            <div class="header">
              <h1>{$titre}</h1>
              <p>ImmoPro — Plateforme immobilière professionnelle</p>
            </div>
            <div class="body">
              <h2>Bonjour,</h2>
              <p>{$intro}</p>
              {$cardHtml}
              {$noteHtml}
              {$outroHtml}
              <p>Merci de votre confiance,<br><strong style="color:#003e7e;">L'équipe ImmoPro</strong></p>
            </div>
            <div class="footer">
              <p>© {$year} <strong>ImmoPro</strong></p>
              <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
          </div>
        </body>
        </html>
        HTML;
    }
}
