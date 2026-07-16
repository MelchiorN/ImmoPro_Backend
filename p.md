3. Architecture des données : modèle hybride relationnel + JSON
Le système repose sur un principe inspiré du pattern EAV (Entity-Attribute-Value), mais adapté pour rester simple et performant :

Entity → table biens (classique, relationnelle)
Attribute → table attribut_definitions (classique, relationnelle) : définit quels champs existent pour chaque catégorie
Value → colonne caracteristiques en JSON sur biens, plutôt qu'une table Value séparée

Ce compromis conserve la flexibilité du EAV pour la définition des champs (géréedynamiquement par l'administrateur), tout en simplifiant le stockage des valeurs (une seule colonne JSON par bien, pas de jointures multiples), ce qui est adapté à l'échelle du projet.
4. Deux niveaux de champs
Champs socle (colonnes fixes sur biens, non modifiables par l'admin)
Titre, Description, Prix, Adresse, Position GPS, Photos, Pièce d'identité — obligatoires pour toute annonce, garantissent le fonctionnement minimal de la plateforme (recherche, vérification, affichage).
Champs dynamiques (gérés via attribut_definitions, propres à chaque catégorie)
Caractéristiques, Équipements, Documents complémentaires, État du bien — modifiables et extensibles par l'administrateur selon la catégorie.
5. Gestion des catégories par l'administrateur
À la création d'une nouvelle catégorie, l'admin :Saisit le nom de la catégorie
Voit les champs socle automatiquement inclus (verrouillés, non désactivables)
Voit les champs suggérés par défaut (Équipements standards, Documents complémentaires) pré-cochés, qu'il peut décocher
Peut ajouter des champs personnalisés, en précisant pour chacun :

Nom technique et label affiché
Type de donnée (via select) : Texte / Nombre / Booléen / Liste déroulante (enum) / Date
Si "Liste déroulante" : saisie des options possibles
Obligatoire ou non6. Règles d'intégrité

Ajout d'un champ : toujours autorisé, sans impact sur les annonces existantes (valeur absente = null)
Suppression d'un champ déjà utilisé par au moins une annonce : bloquée, pour éviter des données orphelines dans le JSON
Désactivation (actif = false) : alternative proposée pour retirer un champ des futurs formulaires sans casser les annonces existantes, qui continuent à l'afficher en lecture seule7. Schéma de base de données simplifié
categories (id, nom, description)

attribut_definitions (id, categorie_id, nom_champ, label_affiche, 
                       type_champ, options_enum, obligatoire, 
                       modifiable_par_admin, actif, ordre_affichage)

biens (id, categorie_id, proprietaire_id, titre, description, prix,
       adresse, latitude, longitude, etat_bien, caracteristiques[JSON])
8. Génération dynamique du formulaire de publication
Le formulaire vu par le propriétaire est généré à l'exécution à partir des attribut_definitions actives de la catégorie choisie : chaque type de champ (texte, nombre, booléen, enum, date) est mappé vers le composant de saisie correspondant, sans code spécifique à écrire pour chaque nouvelle catégorie créée par l'admin.

Ce résumé couvre l'ensemble de la logique de conception qu'on a construite. Veux-tu maintenant que je rédige ce contenu sous forme de texte structuré et académique pour ton chapitre Analyse et Conception (avec la terminologie et le style que tu utilises habituellement), ou que je prépare d'abord le diagramme de classes correspondant ?


Définition des catégories de biens — ImmoPro
Voici la définition formalisée de chaque catégorie, prête à intégrer dans ton chapitre Analyse et Conception. Chaque définition repose sur la règle unique qu'on a établie : une annonce correspond à une unité complète, louable ou vendable dans son intégralité, à un seul locataire/acheteur (personne ou foyer).
1. Maison / Villa
Bien immobilier individuel, bâti sur une parcelle, loué ou vendu dans son intégralité à un seul locataire ou acheteur. Comprend l'ensemble des pièces de vie (chambres, salons, cuisine, salle de bain) ainsi que, le cas échéant, un espace extérieur privatif (cour, jardin) et un garage. Ne peut pas être subdivisée en plusieurs annonces distinctes : si une parcelle comporte plusieurs logements indépendants, chacun doit être publié séparément sous la catégorie Chambre/Studio ou Appartement selon sa taille.
2. Appartement
Unité de logement complète et autonome au sein d'un immeuble à plusieurs niveaux, louée ou vendue dans son intégralité à un seul locataire ou acheteur. Comprend obligatoirement ses propres chambre(s), salon, cuisine et salle de bain à l'intérieur d'un espace fermé. Seuls les espaces liés à l'immeuble (parking, ascenseur) peuvent être partagés avec les autres résidents ; l'intérieur de l'appartement lui-même reste toujours privatif à l'occupant.
3. Chambre / Studio
Unité individuelle de taille réduite, destinée à être louée à une seule personne ou un seul foyer restreint, qu'elle soit physiquement isolée ou qu'elle fasse partie d'une parcelle comportant plusieurs unités similaires appartenant au même propriétaire. Contrairement à l'Appartement, certains espaces annexes (parking, WC dans certains cas) peuvent être partagés avec les occupants des autres unités de la même parcelle, sans que cela remette en cause l'indépendance de l'unité elle-même (chambre et, selon le type, cuisine/salon restent toujours privatifs à l'occupant).
4. Terrain / Parcelle
Bien immobilier non bâti, vendu ou destiné à la construction, sans structure d'habitation existante. Publié dans son intégralité, sans notion de subdivision en unités louables.
5. Bureau / Local commercial
Espace à usage professionnel (bureau, boutique, local commercial), loué ou vendu dans son intégralité à un seul locataire ou acheteur (personne physique ou entreprise). Suit la même logique d'unité complète que Maison/Villa et Appartement : pas de sous-division en plusieurs annonces.
________________________________________
Tableau de synthèse
Catégorie	Unité de location/vente	Peut être partagée avec d'autres locataires ?
Maison / Villa	Le bien entier	Non — un seul locataire/acheteur pour tout le bien
Appartement	Le bien entier	Non à l'intérieur — seulement parking/ascenseur au niveau de l'immeuble
Chambre / Studio	Une unité individuelle réduite	Parking (et parfois WC) peuvent être partagés avec d'autres unités de la même parcelle
Terrain	Le bien entier	Non applicable
Bureau / Local commercial	Le bien entier	Non
________________________________________

1. Maison / Villa
Informations générales
Titre de l'annonce Description Prix ,Superficie du terrain (m²) ,Superficie habitable (m²) 
Localisation Adresse (obligatoire) Position GPS (optionnelle) 
Caractéristiques
Nombre de chambres ,Nombre de salons Nombre de salles de bain Nombre de toilettes Nombre de cuisines Nombre de balcons Nombre de terrasses Nombre d'étages 
État du bien 
o	Neuf 
o	Bon état 
o	À rénover 
Garage
•	Garage disponible (cocher )Si oui 
•	Capacité du garage (nombre de véhicules) 
Équipements
•	Eau courante 
•	Électricité 
•	Internet/Fibre 
•	Caméras 
•	Meublé
•	Jardin
•	Piscine
Médias
•	Photos 
•	Vidéo
Documents
•	Piece d’identité : type (cni,.autres)
•	Attestation de propriété, quittance d'impôt foncier

Terrain
Informations générales
•	Titre de l'annonce
•	Description
•	Prix
•	Superficie du terrain (m²)
Localisation
•	Adresse (obligatoire)
•	Position GPS (fortement recommandée, voire obligatoire ici car c'est le principal repère pour un terrain)
Caractéristiques
•	Type de terrain : Titré / Non titré / En cours de titrisation
•	Usage prévu : Résidentiel / Commercial / Agricole / Mixte
•	Terrain clôturé (oui/non)
•	Viabilisation : 
o	Accès à l'eau
o	Accès à l'électricité
o	Route bitumée à proximité
Médias
•	Photos
•	Vidéo 
Documents
•	Pièce d'identité (CNI ou autre)
•	Document que le propriétaire peut soumettre attestant son droit sur le terrain (attestation villageoise, certificat de propriété, permis d'habiter, ou tout autre document possédé — sans exiger le titre foncier)
Bureau / Local commercial
Informations générales
•	Titre de l'annonce
•	Description
•	Prix (vente ou loyer mensuel)
•	Superficie habitable ou utilisable (m²)
Localisation
•	Adresse (obligatoire)
•	Position GPS (optionnelle)
Caractéristiques
•	Nombre de pièces/bureaux
•	Nombre de salles de réunion
•	Nombre de toilettes
•	Étage / Rez-de-chaussée
•	Vitrine sur rue (oui/non) — pertinent pour local commercial
État du bien
•	Neuf / Bon état / À rénover Parking
•	Parking disponible (cocher) → si oui, capacité
Équipements
•	Eau courante
•	Électricité
•	Internet/Fibre
•	Climatisation
•	Caméras/Sécurité
Médias
•	Photos
•	Vidéo
Documents
•	Pièce d'identité (CNI ou autre)
•	Justificatif de propriété que le propriétaire peut fournir

6. Chambre / Studio (location simple ou collocation où il partagent le même parking)
Informations générales
•	Titre de l'annonce
•	Description
•	Prix (loyer mensuel)
•	Superficie habitable (m²)
Localisation
•	Adresse (obligatoire)
•	Position GPS (optionnelle)
Caractéristiques
•	Type : Chambre simple / Chambre salon / Studio
•	Salle de bain privée ou partagée
•	Cuisine privée ou partagée
•	Meublé (oui/non)
Équipements
•	Eau courante
•	Électricité
•	Internet/Fibre
•	Caméras/Sécurité
État du bien
•	Neuf / Bon état / À rénover 
Parking
•	Parking disponible (cocher)
→ Si oui : partagé avec d'autres locataires de la parcelle (oui/non) + capacité totale

Médias
•	Photos
•	Vidéo
Documents
•	Pièce d'identité (CNI ou autre)
•	Justificatif de propriété que le propriétaire peut fournir (attestation de propriété, quittance d'impôt foncier)

Appartement — Champs de publication (version mise à jour)
Informations générales
•	Titre de l'annonce
•	Description
•	Prix (annuel ou mensuel)
•	Superficie habitable (m²)
•	Étage de l'appartement / Nombre total d'étages de l'immeuble
Localisation
•	Adresse (obligatoire)
•	Position GPS (optionnelle)
Caractéristiques
•	Nombre de chambres
•	Nombre de salons
•	Nombre de salles de bain
•	Nombre de toilettes
•	Nombre de cuisines
•	Nombre de balcons
•	Ascenseur disponible dans l'immeuble (oui/non)
État du bien
•	Neuf / Bon état / À rénover 
Parking
•	Parking disponible (cocher)
•	 Si oui : 
o	Type : prive (place attitrée) / partage (parking commun de l'immeuble)
o	Capacité (si prive, nombre de véhicules)
Équipements
•	Eau courante
•	Électricité
•	Internet/Fibre
•	Caméras / Sécurité (gardien)
•	Meublé
•	Climatisation
Médias
•	Photos
•	Vidéo
Documents
•	Pièce d'identité (CNI ou autre)
•	Justificatif de propriété que le propriétaire peut fournir (attestation de propriété, quittance d'impôt foncier)
