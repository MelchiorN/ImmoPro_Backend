# Correction - Installation Sanctum

## 🔧 Étapes de Correction

### Étape 1: Vérifier Sanctum
```bash
cd d:\Th Stage\Projet\ImmoPro\IMMOPRO_BACKEND

# Vérifier que Sanctum est installé
composer show | grep sanctum
```

Si ce n'est pas installé:
```bash
composer require laravel/sanctum
```

### Étape 2: Publier la configuration
```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### Étape 3: Exécuter la migration Sanctum
```bash
php artisan migrate
```

Cette commande va créer la table `personal_access_tokens` nécessaire pour les tokens.

### Étape 4: Clear cache et redémarrer
```bash
php artisan config:clear
php artisan cache:clear
php artisan serve --port=8001
```

---

## ✅ Vérification

Après ces étapes, tu dois avoir:
- ✅ Trait `HasApiTokens` dans le modèle User (FAIT)
- ✅ Table `personal_access_tokens` créée
- ✅ Configuration Sanctum publiée

---

## 🧪 Test Final

```bash
POST http://localhost:8001/api/admin/login
{
  "email": "admin@gmail.com",
  "password": "password"
}
```

Doit retourner un token avec succès! ✅
