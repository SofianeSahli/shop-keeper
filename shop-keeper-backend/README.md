# Shop Keeper — Backend

API REST Laravel de l'écosystème Shop Keeper. Elle sert d'abord l'app du commerçant ([`shop-keeper-mobile/`](../shop-keeper-mobile/README.md)), puis les apps client, grossiste et sponsor.

← Retour au [README principal](../README.md)

> **État : phase 1 en cours.** La spec **006 — Authentification et magasin** est livrée : compte du patron (connexion Google ou e-mail, vérification en deux étapes optionnelle par code envoyé par e-mail) et création de son magasin, testables avec Postman. La spec **007 — Catalogue partagé et produits du magasin** est en cours : catalogue commun traduit en français, arabe et anglais, produits du magasin avec leurs prix, ajout par code-barres ou par nom, doublons bloqués.

---

## Stack

| Domaine | Choix |
|---|---|
| Framework | Laravel 13 |
| Langage | PHP 8.3+ (au minimum 8.3.36, 8.4.27 ou 8.5.12, les versions qui corrigent CVE-2026-109618 et CVE-2026-109619) |
| Base de données | MySQL 8, tests compris (base `shop_keeper_test`) |
| Cache, limites, file d'attente | Redis |
| Environnement | Docker Compose : PHP-FPM, Nginx, worker de file, MySQL, Redis, Mailpit, phpMyAdmin |
| Authentification | Laravel Sanctum, un jeton par appareil, connexion Google ou e-mail et mot de passe, vérification en deux étapes optionnelle : un nouveau code à 6 chiffres envoyé par e-mail à chaque connexion |
| Tests | Pest |
| Qualité | Pint |

---

## Démarrer

**Prérequis :** Docker et Docker Compose. PHP, Composer, MySQL et Redis ne sont pas nécessaires sur la machine.

```bash
cp .env.example .env     # renseigner DB_PASSWORD et DB_ROOT_PASSWORD
docker compose up -d --build
```

Le service `setup` installe les paquets, crée `APP_KEY` et applique les migrations à chaque démarrage. L'API répond sur http://localhost:8000 et Mailpit, qui reçoit les e-mails envoyés en local, sur http://localhost:8025. phpMyAdmin, pour consulter la base, répond sur http://localhost:8080 avec `DB_USERNAME` et `DB_PASSWORD` de `.env`.

Toute la configuration passe par `.env` ; aucun hébergeur n'est encore choisi. Ce `compose.yaml` sert au développement.

### Commandes

| Commande | Effet |
|---|---|
| `docker compose up -d` | Lance l'API et ses services |
| `docker compose down` | Arrête tout, les données restent dans les volumes |
| `docker compose logs -f app queue` | Suit les logs |
| `docker compose exec app php artisan test` | Lance les tests |
| `docker compose exec app vendor/bin/pint` | Formate le code, à lancer avant chaque commit |
| `docker compose exec app php artisan migrate` | Applique les migrations |
| `docker compose exec app php artisan catalog:load` | Charge ou met à jour le catalogue commun depuis `app/Modules/Catalog/database/catalog/products.json` ; relancer ne change rien, une entrée fausse arrête tout sans rien modifier |
| `docker compose exec app composer require <paquet>` | Ajoute un paquet |

---

## Tester avec Postman

L'API est exposée sous `/api/v1`. Chaque spec livre une collection Postman à jour avec ses endpoints, dans [`postman/`](postman/). Un test vérifie que chaque route de l'API a sa requête dans la collection.

1. Mettre `AUTH_TEST_MODE=true` dans `.env`, puis lancer `docker compose restart queue`. Ce mode remplace Google par `test:<email>` et chaque code envoyé par `000000` ; il est refusé en production.
2. Charger le catalogue : `docker compose exec app php artisan catalog:load`.
3. Importer `postman/shop-keeper.postman_collection.json` dans Postman et régler `base_url` sur `http://localhost:8000`. C'est une seule collection pour toute l'API ; elle remplace `shop-keeper-006…`.
4. Lancer la collection dans l'ordre : le jeton, les codes-barres et les identifiants de produits passent d'une requête à l'autre tout seuls.

Les erreurs ont toujours le même format, avec un code stable (par exemple `shop.forbidden`) et sans texte destiné à l'utilisateur : c'est l'app qui traduit le code.

---

## Architecture

Le code est découpé par domaine métier. Chaque module contient ses modèles, ses actions (les règles métier), ses contrôleurs, ses validations, ses policies, ses migrations, ses routes et ses tests. Un module ne lit ni n'écrit jamais les tables d'un autre : il appelle ses actions ou écoute ses événements.

```
app/
├── Shared/          # format d'erreur, exigence de JSON
└── Modules/
    ├── Identity/    # comptes, appareils, Google, e-mail, code par e-mail (006)
    ├── Shops/       # magasins                                          (006)
    ├── Catalog/     # global_products (catalogue commun), shop_products  (007, 008)
    ├── Inventory/   # fournisseurs, réceptions, mouvements de stock     (009, 010)
    ├── Sales/       # ventes, lignes, paiements                         (011)
    ├── Credit/      # clients, crédit client                            (011)
    ├── Reporting/   # dashboard                                         (012)
    └── Sync/        # envoi et récupération des opérations              (013)
```

Un module n'est créé que lorsqu'une spec en a besoin. Les numéros entre parenthèses suivent l'ordre de la feuille de route.

---

## Règles

Les règles complètes sont dans [`CLAUDE.md`](CLAUDE.md) et dans la [constitution](../.specify/memory/constitution.md). L'essentiel :

- **Isolation.** Chaque table d'un magasin a un `shop_id` ; un utilisateur n'accède jamais aux données d'un autre magasin.
- **Argent** en entiers dans l'unité mineure de la devise (TND : millimes).
- **Aucune modification après coup** pour les ventes, paiements, crédits et mouvements de stock : une correction est un nouvel enregistrement.
- **UUID** générés par le téléphone, stockés en `BINARY(16)`.
- **Sécurité.** Secrets uniquement dans `.env`, mots de passe et codes hachés, aucune donnée personnelle dans les logs.
- **Tests** pour chaque endpoint : succès, validation, accès sans connexion, accès à un autre magasin.
