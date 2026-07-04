# Debugging - Erreur 500 sur Route GET

## 🔍 Étapes de Test Progressives

### 1️⃣ Tester la santé du serveur
```bash
GET http://localhost:8001/api/health
```
**Réponse attendue:**
```json
{
  "status": "ok",
  "message": "API is running"
}
```

Si cette route retourne 500 → **Le problème est au niveau serveur Laravel**
Si elle fonctionne → **Le problème est avec l'authentification**

---

### 2️⃣ Vérifier les logs en temps réel
Pendant que tu testes, ouvre un autre terminal et vois les logs:
```bash
cd d:\Th Stage\Projet\ImmoPro\IMMOPRO_BACKEND
tail -f storage/logs/laravel.log
```

---

### 3️⃣ Séquence correcte de test

#### **Étape A: Login (publique)**
```bash
POST http://localhost:8001/api/admin/login
Content-Type: application/json

{
  "email": "admin@gmail.com",
  "password": "password"
}
```

**Réponse attendue:**
```json
{
  "user": {
    "id": "uuid-here",
    "first_name": "Super",
    "last_name": "Admin",
    "email": "admin@gmail.com",
    "role": "admin",
    "status": "active"
  },
  "token": "1|AbCdEfGhIj...",
  "message": "Connexion réussie"
}
```

**Copie le token!**

---

#### **Étape B: Me (protégée)**
```bash
GET http://localhost:8001/api/admin/me
Authorization: Bearer 1|AbCdEfGhIj...
```

**Points d'attention:**
- ✅ Le header `Authorization` DOIT commencer par `Bearer ` (avec espace)
- ✅ Le token doit être le token complet du login
- ✅ Ne pas oublier le `Bearer ` devant

**Réponse attendue:**
```json
{
  "id": "uuid-here",
  "first_name": "Super",
  "last_name": "Admin",
  "email": "admin@gmail.com",
  "role": "admin",
  "status": "active",
  "created_at": "2026-07-02T...",
  "updated_at": "2026-07-02T..."
}
```

---

## ⚠️ Erreurs Courantes et Solutions

### **Erreur: "Unauthenticated" (401)**
```json
{
  "message": "Unauthenticated."
}
```
**Solution:**
- Vérifier que le token est envoyé: `Authorization: Bearer <token>`
- Vérifier que le token commence par `1|` (Sanctum)
- Vérifier que le token n'a pas expiré

### **Erreur: Internal Server Error (500)**
```json
{
  "message": "Server Error"
}
```
**Solutions possibles:**
1. Vérifier les logs: `tail -f storage/logs/laravel.log`
2. Vérifier que la migration est bien exécutée: `php artisan migrate:status`
3. Vérifier que le seeder a créé l'admin: `php artisan tinker` puis `App\Models\User::all()`
4. Vérifier que Sanctum est installé: `composer show | grep sanctum`

### **Erreur: "Identifiants invalides" (422)**
```json
{
  "message": "Identifiants invalides.",
  "errors": {
    "email": ["Identifiants invalides."]
  }
}
```
**Solutions:**
- Vérifier l'email: `admin@gmail.com` (exact!)
- Vérifier le mot de passe: `password`
- Vérifier que l'utilisateur existe: `php artisan tinker`
  ```php
  > User::where('email', 'admin@gmail.com')->first()
  ```
- Vérifier que le rôle est bien `admin`

---

## 🔧 Checklist de Configuration

- [ ] `.env` a `APP_DEBUG=true` pour voir les erreurs
- [ ] `.env` a `APP_ENV=local`
- [ ] `php artisan migrate` a été exécuté
- [ ] `php artisan db:seed` a été exécuté
- [ ] `composer require laravel/sanctum` (si pas encore fait)
- [ ] Sanctum est enregistré dans `config/app.php` providers
- [ ] `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`

---

## 🛠️ Commands Utiles de Debugging

```bash
# 1. Voir les routes
php artisan route:list | grep admin

# 2. Vérifier les utilisateurs
php artisan tinker
> User::all()
> User::where('email', 'admin@gmail.com')->first()

# 3. Clear tout cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# 4. Relancer le serveur
php artisan serve --port=8001

# 5. Test direct depuis artisan
php artisan tinker
> $user = User::find('uuid-here');
> $token = $user->createToken('test')->plainTextToken;
> echo $token;
```

---

## 📝 Informations à Partager si le Problème Persiste

Quand tu as l'erreur 500, copie-colle ces infos:
1. Le contenu du `storage/logs/laravel.log` (dernières lignes)
2. La réponse exacte du serveur
3. L'URL exacte que tu utilises
4. La valeur de `APP_DEBUG` dans `.env`
5. La version de PHP: `php -v`

---

## ✅ Résumé de la Procédure

```
1. GET /api/health              → Doit retourner ok
2. POST /api/admin/login        → Retourne token
3. GET /api/admin/me + token    → Retourne utilisateur
```

Si l'étape 1 échoue → Problème serveur
Si l'étape 2 échoue → Problème seeder/DB
Si l'étape 3 échoue → Problème token/Sanctum
