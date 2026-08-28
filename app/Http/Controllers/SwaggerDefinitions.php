<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "ImmoPro API",
    description: "Documentation interactive de l'API ImmoPro — plateforme immobilière.

## Authentification
La majorité des endpoints nécessitent un token Bearer (Laravel Sanctum).
Connectez-vous via **POST /api/login** (Admin/Agent) ou **POST /api/client/login** (Client),
puis cliquez sur le bouton **Authorize** et collez votre token.

## Rôles
- **admin** : accès complet à l'administration
- **agent** : gestion des biens assignés et des visites
- **client** : propriétaires / acheteurs / locataires",
    contact: new OA\Contact(name: "Support ImmoPro", email: "support@immopro.com")
)]
#[OA\Server(
    url: "http://localhost:8000/api",
    description: "Serveur principal de l'API ImmoPro"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Token Sanctum obtenu après connexion. Format: Bearer {token}"
)]
#[OA\Schema(
    schema: "UserResource",
    title: "Utilisateur",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "first_name", type: "string", example: "Jean"),
        new OA\Property(property: "last_name", type: "string", example: "Dupont"),
        new OA\Property(property: "email", type: "string", format: "email", example: "jean.dupont@email.com"),
        new OA\Property(property: "telephone", type: "string", example: "+22501234567", nullable: true),
        new OA\Property(property: "country", type: "string", example: "Côte d'Ivoire", nullable: true),
        new OA\Property(property: "city", type: "string", example: "Abidjan", nullable: true),
        new OA\Property(property: "profile_picture", type: "string", format: "url", example: "https://...", nullable: true),
        new OA\Property(property: "role", type: "string", enum: ["admin", "agent", "client"], example: "client"),
        new OA\Property(property: "status", type: "string", enum: ["active", "suspended", "blocked"], example: "active")
    ]
)]
#[OA\Schema(
    schema: "PaginationMeta",
    title: "Métadonnées de pagination",
    properties: [
        new OA\Property(property: "total", type: "integer", example: 120),
        new OA\Property(property: "per_page", type: "integer", example: 15),
        new OA\Property(property: "current_page", type: "integer", example: 1),
        new OA\Property(property: "last_page", type: "integer", example: 8)
    ]
)]
#[OA\Schema(
    schema: "ErrorValidation",
    title: "Erreur de validation",
    properties: [
        new OA\Property(property: "message", type: "string", example: "The given data was invalid."),
        new OA\Property(property: "errors", type: "object", example: ["email" => ["Le champ email est obligatoire."]])
    ]
)]
class SwaggerDefinitions
{
    // Ce fichier centralise les définitions et schémas Swagger globaux.
}

