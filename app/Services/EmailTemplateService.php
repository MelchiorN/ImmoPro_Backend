<?php

namespace App\Services;

/**
 * Génère des templates HTML d'email cohérents avec la charte ImmoPro.
 *
 * Règle UX : aucun emoji — les icônes de ligne utilisent des SVG inline.
 */
class EmailTemplateService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Bibliothèque de SVG inline (16 × 16, couleur héritée via currentColor)
    // ─────────────────────────────────────────────────────────────────────────

    private static array $icons = [
        // Bien / maison
        'home' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10"/></svg>',
        // Localisation / épingle
        'pin'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.686 2 6 4.686 6 8c0 5.25 6 13 6 13s6-7.75 6-13c0-3.314-2.686-6-6-6zm0 8a2 2 0 110-4 2 2 0 010 4z"/></svg>',
        // Calendrier / date
        'cal'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        // Horloge / durée
        'clock'=> '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        // Utilisateur / personne
        'user' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        // Email / enveloppe
        'mail' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
        // Téléphone
        'phone'=> '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>',
        // Argent / paiement
        'money'=> '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
        // Reçu / document
        'doc'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        // Transaction / type
        'type' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>',
        // Statut / horloge sablier
        'status'=> '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        // Bâtiment / type de logement
        'building'=> '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>',
        // Répétition / tentatives
        'repeat'=> '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>',
        // Note / crayon
        'note' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>',
        // Opérateur mobile / carte
        'card' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
        // Générique (fallback)
        'dot'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/></svg>',
    ];

    /**
     * Mappe les anciens emojis vers une clé de SVG.
     * Permet la rétro-compatibilité si des emojis sont encore passés.
     */
    private static array $emojiMap = [
        '🏠' => 'home', '🏡' => 'home',
        '📍' => 'pin',
        '📅' => 'cal', '📆' => 'cal',
        '⏱️' => 'clock', '⏱' => 'clock', '⏰' => 'clock', '⏳' => 'clock',
        '👤' => 'user', '🧑‍💼' => 'user',
        '📧' => 'mail',
        '📞' => 'phone', '📱' => 'phone',
        '💰' => 'money', '💳' => 'card',
        '🧾' => 'doc', '📋' => 'doc', '📄' => 'doc', '📝' => 'note',
        '🔄' => 'type',
        '🏗️' => 'building', '🏢' => 'building',
        '🔁' => 'repeat',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // API publique
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Template email générique avec carte de détails.
     *
     * @param string      $titre   Titre affiché dans le header et le <title>
     * @param string      $intro   Paragraphe d'introduction (HTML autorisé)
     * @param array       $rows    [['icon'=>'home|📅|…','label'=>'…','value'=>'…'], …]
     * @param string|null $noteBox Encadré note optionnel (fond jaune)
     * @param string|null $outro   Paragraphe de clôture
     */
    public static function generic(
        string  $titre,
        string  $intro,
        array   $rows    = [],
        ?string $noteBox = null,
        ?string $outro   = null,
    ): string {
        $rowsHtml = '';
        foreach ($rows as $row) {
            $svgIcon = self::resolveIcon($row['icon'] ?? '');
            $label   = htmlspecialchars($row['label'] ?? '');
            $value   = htmlspecialchars($row['value'] ?? '');
            $rowsHtml .= <<<HTML
            <div class="row">
              <span class="icon" aria-hidden="true">{$svgIcon}</span>
              <div>
                <div class="label">{$label}</div>
                <div class="value">{$value}</div>
              </div>
            </div>
            HTML;
        }

        $cardHtml = $rowsHtml ? "<div class=\"card\">{$rowsHtml}</div>" : '';

        $noteSvg  = self::$icons['note'];
        $noteHtml = $noteBox
            ? '<div class="notes-box"><span class="note-icon" aria-hidden="true">' . $noteSvg . '</span><strong>Note :</strong><br>' . nl2br(htmlspecialchars($noteBox)) . '</div>'
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
            .header-icon{margin:0 auto 12px;width:48px;height:48px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center}
            .header-icon svg{color:#fff;width:28px;height:28px}
            .header h1{color:#fff;font-size:20px;margin:0;font-weight:700}
            .header p{color:rgba(255,255,255,.75);font-size:13px;margin:6px 0 0}
            .body{padding:36px 40px}
            .badge{display:inline-block;background:#e8f3ff;color:#003e7e;font-size:12px;font-weight:700;border-radius:20px;padding:4px 14px;margin-bottom:20px}
            h2{color:#003e7e;font-size:18px;margin:0 0 16px}
            p{color:#555;font-size:14px;line-height:1.7;margin:0 0 16px}
            .card{background:#f0f6ff;border-left:4px solid #003e7e;border-radius:8px;padding:16px 20px;margin:24px 0}
            .row{display:flex;gap:12px;align-items:flex-start;margin-bottom:10px}
            .row:last-child{margin-bottom:0}
            .icon{color:#003e7e;width:20px;flex-shrink:0;margin-top:2px}
            .icon svg{width:16px;height:16px}
            .label{font-size:11px;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
            .value{font-size:14px;color:#1a1a2e;font-weight:700}
            .notes-box{background:#fffbea;border:1px solid #fde68a;border-radius:8px;padding:14px 18px;font-size:13px;color:#78350f;margin-top:16px;display:flex;gap:10px;align-items:flex-start}
            .note-icon{color:#92400e;width:18px;flex-shrink:0;margin-top:1px}
            .note-icon svg{width:16px;height:16px}
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

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers internes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Résout une clé ('home', 'cal', …) ou un emoji ancien en SVG inline.
     * Si la valeur est déjà du SVG, elle est retournée telle quelle.
     * Fallback : point générique.
     */
    private static function resolveIcon(string $raw): string
    {
        $raw = trim($raw);

        // Déjà du SVG
        if (str_starts_with($raw, '<svg')) {
            return $raw;
        }

        // Clé nommée
        if (isset(self::$icons[$raw])) {
            return self::$icons[$raw];
        }

        // Emoji legacy → clé
        if (isset(self::$emojiMap[$raw])) {
            return self::$icons[self::$emojiMap[$raw]];
        }

        return self::$icons['dot'];
    }
}
