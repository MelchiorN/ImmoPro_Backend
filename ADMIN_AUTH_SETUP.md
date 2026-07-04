# Configuration Authentification Admin - ImmoPro

## ✅ Corrections Apportées

### 1. **Modèle User** (`app/Models/User.php`)
- ✅ Ajout de tous les champs `fillable` correspondant à la migration
- Champs: `first_name`, `last_name`, `email`, `telephone`, `country`, `city`, `profile_picture`, `role`, `status`, `password`, `provider`, `provider_id`, `provider_token`

### 2. **Seeder Admin** (`database/seeders/UserSeeder.php`)
- ✅ Import de `Hash` facade pour encoder le mot de passe
- ✅ Création d'un utilisateur admin avec :
  - Email: `admin@gmail.com`
  - Mot de passe: `password` (hasé)
  - Rôle: `admin`
  - Status: `active` (par défaut)

### 3. **Database Seeder** (`database/seeders/DatabaseSeeder.php`)
- ✅ Correction du typo: `this->call()` → `$this->call()`

### 4. **Contrôleur Authentification** (`app/Http/Controllers/Auth/AuthController.php`)
- ✅ Méthode `login()` : Authentifie et retourne un token Sanctum
- ✅ Méthode `logout()` : Révoque le token actuel
- ✅ Méthode `me()` : Retourne les données de l'utilisateur connecté

### 5. **Routes API** (`routes/api.php`)
- ✅ Route POST `/api/admin/login` (publique) - Connexion
- ✅ Route GET `/api/admin/me` (protégée) - Utilisateur courant
- ✅ Route POST `/api/admin/logout` (protégée) - Déconnexion

---

## 🚀 Instructions de Test

### Étape 1: Créer la base de données et exécuter les migrations

```bash
cd d:\Th Stage\Projet\ImmoPro\IMMOPRO_BACKEND

# Créer la base de données (si SQLite)
# Ou configurer votre base de données dans .env

# Exécuter les migrations
php artisan migrate

# Seeder l'admin
php artisan db:seed
```

### Étape 2: Tester l'authentification avec Postman ou cURL

#### Test 1: Login
```bash
POST http://localhost:8000/api/admin/login
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
    "id": 1,
    "first_name": "Super",
    "last_name": "Admin",
    "email": "admin@gmail.com",
    "role": "admin",
    "status": "active"
  },
  "token": "1|AbCdEfGhIjKlMnOpQrStUvWxYz...",
  "message": "Connexion réussie"
}
```

#### Test 2: Get Current User (protégé)
```bash
GET http://localhost:8000/api/admin/me
Authorization: Bearer 1|AbCdEfGhIjKlMnOpQrStUvWxYz...
```

**Réponse attendue:**
```json
{
  "id": 1,
  "first_name": "Super",
  "last_name": "Admin",
  "email": "admin@gmail.com",
  "role": "admin",
  "status": "active",
  "created_at": "2026-07-02T10:30:00.000000Z",
  "updated_at": "2026-07-02T10:30:00.000000Z"
}
```

#### Test 3: Logout (protégé)
```bash
POST http://localhost:8000/api/admin/logout
Authorization: Bearer 1|AbCdEfGhIjKlMnOpQrStUvWxYz...
```

**Réponse attendue:**
```json
{
  "message": "Déconnexion réussie"
}
```

---

## 🔑 Identifiants de Test

| Champ | Valeur |
|-------|--------|
| Email | `admin@gmail.com` |
| Mot de passe | `password` |
| Rôle | `admin` |
| Statut | `active` |

---

## 📋 Checklist de Vérification

- [ ] Les migrations sont exécutées (`php artisan migrate`)
- [ ] Le seeder a créé l'utilisateur admin (`php artisan db:seed`)
- [ ] Sanctum est configuré et fonctionnel
- [ ] Les routes API sont enregistrées
- [ ] Le contrôleur AuthController compile sans erreurs
- [ ] Les tests de login/logout/me réussissent
- [ ] Le token Sanctum est valide et révocable

---

## 🔐 Configuration Sanctum (Vérifié)

Le fichier `config/sanctum.php` est configuré pour :
- Tokens stateless (API)
- Middleware `auth:sanctum` pour les routes protégées
- Expiration automatique des tokens (configurable)

---

## 📝 Notes

- Le mot de passe par défaut est `password` - **À changer en production!**
- Les tokens Sanctum sont stockés dans la table `personal_access_tokens`
- Chaque requête authentifiée doit inclure: `Authorization: Bearer <token>`
- Le password est automatiquement hasé lors du create/update via le model

---

## 🛠️ Troubleshooting

### Erreur: "Identifiants invalides"
- Vérifier que l'email existe et que le mot de passe correspond
- Vérifier que le rôle est bien `admin`

### Erreur: "Compte désactivé ou bloqué"
- Vérifier que le statut de l'utilisateur est `active`

### Erreur: "Unauthenticated" sur route protégée
- Vérifier que le token est passé dans le header `Authorization: Bearer`
- Vérifier que le token n'a pas expiré
