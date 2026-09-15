#!/bin/bash
set -e

echo " ImmoPro — Démarrage du conteneur..."

# ── 1. Recréer le fichier Firebase Service Account depuis la variable Base64 ──
if [ -n "$FIREBASE_CREDENTIALS_BASE64" ]; then
    echo "🔥 Restauration du Service Account Firebase..."
    mkdir -p /var/www/html/storage/app/firebase
    echo "$FIREBASE_CREDENTIALS_BASE64" | base64 -d > /var/www/html/storage/app/firebase/immopro.json
    chown www-data:www-data /var/www/html/storage/app/firebase/immopro.json
    chmod 600 /var/www/html/storage/app/firebase/immopro.json
    echo "✅ immopro.json créé."
else
    echo "⚠️  FIREBASE_CREDENTIALS_BASE64 non définie — push FCM désactivé."
fi

# ── 2. S'assurer que les dossiers storage existent et ont les bonnes permissions ──
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ── 3. Générer APP_KEY si absent ─────────────────────────────────────────────
if [ -z "$APP_KEY" ]; then
    echo "⚠️  APP_KEY absente — génération..."
    php artisan key:generate --force
fi

# ── 4. Vider et reconstruire le cache de config ──────────────────────────────
php artisan config:clear

# Note: config:cache est incompatible avec L5-Swagger (ReflectionAnalyser non sérialisable)
# On tente le cache mais on ignore l'erreur si elle se produit
php artisan config:cache 2>/dev/null || echo "⚠️  config:cache ignoré (L5-Swagger incompatible) — l'app fonctionne sans cache."
echo "✅ Config prête."

# ── 5. Lancer les migrations ─────────────────────────────────────────────────
# php artisan migrate --force --no-interaction
# echo "✅ Migrations effectuées."

# ── 6. Créer le lien storage ─────────────────────────────────────────────────
php artisan storage:link --force 2>/dev/null || true
echo "✅ Storage link créé."

# ── 7. Démarrer Apache ───────────────────────────────────────────────────────
echo "🌐 Démarrage Apache..."
exec apache2-foreground
