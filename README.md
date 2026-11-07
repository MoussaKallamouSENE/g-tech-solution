# G-Tech Solution

Plateforme web de vente de matériel informatique avec service de demande de réparation en ligne.

## Fonctionnalités

### Espace client
- Catalogue de produits, fiche produit et liste de favoris
- Panier, paiement et confirmation de commande
- Inscription, connexion, mot de passe oublié et profil
- Suivi des commandes
- Demande de réparation en ligne (maintenance)
- Page de contact

### Livraison
- Gestion des livraisons et des zones de livraison
- Espace livreur

### Espace administrateur
- Tableau de bord
- Gestion des produits, des clients, des fournisseurs et des commandes
- Gestion des livraisons et des demandes de maintenance
- Paramètres de la boutique
- Factures au format PDF

## Technologies

- Backend : PHP, Laravel (API)
- Frontend : Angular, TypeScript, HTML5, CSS3
- Base de données : MySQL
- Déploiement : Docker, Docker Compose, Nginx
- Outils : Git, GitHub, Composer, npm

## Structure du projet

- `backend/` : API Laravel
- `frontend/` : application Angular
- `php/` et `nginx/` : configuration des conteneurs
- `docker-compose.yml` : lancement de l'ensemble

## Installation

```
git clone https://github.com/MoussaKallamouSENE/g-tech-solution.git
cd g-tech-solution
cp .env.example .env
cp backend/.env.example backend/.env
docker compose up -d --build
```

Renseignez les mots de passe et la configuration dans les fichiers `.env`.
Les commandes propres au backend (migrations, tests) sont détaillées dans `backend/README.md`.

## Tests

Backend : `php artisan test`. Frontend : `npm test` dans le dossier `frontend`.

## Auteur

Moussa SENE, développeur et administrateur d'applications.
GitHub : https://github.com/MoussaKallamouSENE
