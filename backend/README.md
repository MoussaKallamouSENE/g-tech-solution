<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Notifications et livraisons G-Tech

### Configuration et démarrage

Les migrations de suivi des notifications et des livraisons sont additives. Copiez `.env.example` vers `.env` à la racine du dépôt et définissez un mot de passe MySQL local; configurez `backend/.env` avec la même valeur dans `DB_PASSWORD` ainsi que `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_DATABASE=gtech` et `DB_USERNAME=root`. Pour un volume MySQL déjà initialisé, gardez son mot de passe existant : Compose ne modifie pas le mot de passe stocké dans ce volume.

`MYSQL_ROOT_PASSWORD` (racine du dépôt) est désormais l’unique source de vérité : Compose l’injecte comme `DB_PASSWORD` dans les services `php`, `queue-worker` et `delivery-scheduler`. La valeur de `backend/.env` est donc ignorée par Docker, mais elle reste la référence pour une exécution de Laravel hors Docker. Si vous changez le mot de passe d’un volume déjà initialisé, exécutez également `ALTER USER 'root'@'%' IDENTIFIED BY '<nouveau mot de passe>';` dans le conteneur `db`, sinon Laravel recevra « Access denied for user ».

L’URL publique du backend (`APP_URL`, port 80 via Nginx) sert à générer les liens vers les images téléversées. `FRONTEND_URL` pointe vers la boutique Angular (port 3000) et sert aux liens envoyés aux clients, comme la réinitialisation de mot de passe.

L’image PHP désactive la revalidation des horodatages d’opcache (`opcache.validate_timestamps=0`). Sur un bind mount hôte, la revalidation controllingait plus de 8 000 fichiers à chaque requête et chaque appel API prenait plusieurs secondes ; elle est désormais de l’ordre de 300 ms. **Conséquence : après une modification du code PHP, redémarrez le service pour recharger les fichiers** :

```powershell
docker compose restart php
```

Le service `php` précharge opcache au démarrage (`post_start`), la première requête qui suit un redémarrage est donc déjà rapide.

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
# Configurez MYSQL_ROOT_PASSWORD dans .env et la même valeur dans backend/.env.
docker compose up -d --build nginx php db
docker compose exec php php artisan migrate
docker compose exec php php artisan db:seed
docker compose up -d --build queue-worker delivery-scheduler
docker compose logs -f queue-worker
docker compose logs -f delivery-scheduler
```

`php artisan db:seed` est idempotent : il restaure le catalogue, les zones de livraison et le compte administrateur manquant sans jamais réécrire le mot de passe d’un compte existant.

### Compte administrateur

Le compte administrateur n’est plus écrit en clair dans le dépôt : il est décrit par `ADMIN_EMAIL`, `ADMIN_NAME`, `ADMIN_PHONE` et `ADMIN_PASSWORD` dans `backend/.env` (fichier non suivi par Git). Si `ADMIN_PASSWORD` est vide, un mot de passe aléatoire est généré puis affiché une seule fois dans la console au moment du seed. Un compte déjà présent n’est jamais réécrit : changer `ADMIN_PASSWORD` dans `.env` ne modifie donc pas un administrateur existant ; utilisez l’interface **Profil** ou la commande ci-dessous pour le changer.

```powershell
docker compose exec php php artisan tinker --execute 'App\Models\User::where("email", "senemoussa@gmail.com")->update(["password" => Illuminate\Support\Facades\Hash::make("VotreNouveauMotDePasse")]);'
```

Ne lancez pas `migrate:fresh`, `db:wipe` ni de suppression du volume de données. `QUEUE_CONNECTION=database` utilise la table `jobs`; le worker Compose traite l’e-mail et WhatsApp hors de la requête HTTP. Les erreurs permanentes restent visibles dans la commande et une nouvelle tentative peut être demandée par un administrateur depuis la fiche de commande.

Pour le courrier, configurez uniquement dans `backend/.env` : `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` et `MAIL_FROM_NAME`. La valeur `log` de l’exemple sert au développement et ne livre pas de vrai e-mail. Ne placez jamais ces secrets dans Angular ni dans un fichier suivi par Git.

Pour WhatsApp Business Cloud, configurez côté backend `WHATSAPP_ENABLED=true`, `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_API_VERSION`, `WHATSAPP_TEMPLATE_NAME` et `WHATSAPP_TEMPLATE_LANGUAGE`. Le modèle doit être approuvé par Meta et correspondre aux six paramètres texte envoyés dans cet ordre : nom du client, numéro de commande, articles, montant, adresse et statut. Le client doit avoir explicitement coché l’option WhatsApp et fournir un numéro international (`+` suivi du numéro E.164). Sans consentement ou configuration complète, aucun message n’est envoyé. Les jetons restent hors du dépôt.

### Factures PDF et garanties

Les factures A4 utilisent la dépendance DomPDF déjà installée. Les clients authentifiés ne peuvent télécharger que les factures de leurs commandes; l’administration peut consulter et télécharger toute facture. Les numéros sont attribués une seule fois, enregistrés et protégés par une contrainte unique. La migration attribue aussi un numéro aux commandes existantes.

Les conditions commerciales spécifiques sont modifiables dans la fiche produit par l’administration et copiées, avec les caractéristiques du produit, sur chaque ligne de commande lors de l’achat. Les anciennes commandes sans instantané n’héritent pas rétroactivement des conditions actuelles; la facture indique explicitement l’absence de conditions spécifiques enregistrées. Les clauses commerciales générales rappellent le signalement sous 72 heures, la couverture commerciale limitée à la carte mère, l’information préalable des frais de réparation et la préservation des droits légaux.

Les coordonnées imprimées sont configurables dans `backend/.env` par `GTECH_INVOICE_COMPANY_NAME`, `GTECH_INVOICE_ADDRESS`, `GTECH_INVOICE_PHONE`, `GTECH_INVOICE_EMAIL` et `GTECH_INVOICE_WEBSITE`. Aucun téléphone, courriel ou site n’est inventé par défaut. Le système ne stocke pas les paiements encaissés ni les remises; la facture n’affirme donc pas qu’un règlement a eu lieu et ne calcule pas de solde restant.

Après sauvegarde, appliquez les migrations additives avec `docker compose exec php php artisan migrate --force`. Ne lancez pas `migrate:fresh` ni `db:wipe`.

### Affectation, GPS et carte

Un administrateur crée les comptes de livreur dans **Administration → Livraisons GPS**; l’inscription publique ne permet pas de choisir un rôle privilégié. Il associe ensuite les commandes confirmées à un compte livreur et marque la livraison prête. Le livreur se connecte sur `/livreur`; les API ne lui renvoient que ses propres livraisons. Les routes administrateur permettent la gestion globale. Le client connecté peut consulter le suivi via `GET /api/orders/{orderId}/delivery` uniquement si la commande lui appartient; cette réponse ne contient que les informations de sa livraison et les coordonnées ne sont renvoyées que si celle-ci est en cours et que le partage GPS est actif. La fiche de commande actualise le suivi toutes les 30 secondes, affiche l’absence ou l’ancienneté d’une position, et ne fournit pas d’heure d’arrivée estimée.

Le partage GPS ne démarre qu’après une autorisation navigateur explicite et une action du livreur. Le navigateur conserve au plus la position la plus récente en mémoire en cas de perte réseau. Les points sont envoyés toutes les 15 secondes si leur précision est meilleure ou égale à 100 m; Laravel valide les plages latitude/longitude et limite les mises à jour à une par 10 secondes. La précision faible, le refus de permission et les indisponibilités réseau/GPS sont signalés dans l’interface. Les navigateurs peuvent suspendre le suivi écran verrouillé ou en arrière-plan : ce n’est pas un suivi continu garanti.

Seule la dernière position est conservée, pas un historique d’itinéraire. Elle est supprimée par la tâche planifiée 30 jours après sa dernière échéance (`DELIVERY_LOCATION_RETENTION_DAYS`); le seuil d’affichage « ancienne » est réglé par `DELIVERY_LOCATION_STALE_MINUTES`. Le service `delivery-scheduler` exécute cette purge quotidienne. Les états de livraison, eux, conservent leur historique.

La carte utilise Leaflet (BSD 2-Clause) avec attribution OpenStreetMap visible. La configuration actuelle emploie les tuiles publiques OSM, qui sont best-effort sans SLA et soumises à leur [politique d’utilisation](https://operations.osmfoundation.org/policies/tiles/). Pour un usage de production nécessitant une disponibilité garantie, remplacez `DELIVERY_MAP_TILE_URL` dans `../frontend/src/app/config/delivery.ts` par un fournisseur ou un hébergement adapté, après vérification de sa licence, de ses quotas, de l’attribution et des conditions d’utilisation. Aucune position de substitution n’est affichée si la position ou les tuiles sont indisponibles.

Pour compiler le frontend après installation des dépendances :

```powershell
Set-Location frontend
npm run build
npm test -- --watch=false
```

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
