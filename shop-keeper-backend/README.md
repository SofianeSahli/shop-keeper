# Shop Keeper — Backend

API REST Laravel de l'écosystème Shop Keeper. Elle sert d'abord l'app du commerçant ([`shop-keeper-mobile/`](../shop-keeper-mobile/README.md)), puis les apps client, grossiste et sponsor.

← Retour au [README principal](../README.md)

> **État : phase 1 en cours.** Le projet est encore le squelette Laravel. La première spec, **006 — Authentification et magasin**, ajoute le compte du patron (téléphone + PIN) et la création de son magasin, testables avec Postman.

---

## Stack

| Domaine | Choix |
|---|---|
| Framework | Laravel 13 |
| Langage | PHP 8.3+ (au minimum 8.3.36, 8.4.27 ou 8.5.12, les versions qui corrigent CVE-2026-109618 et CVE-2026-109619) |
| Base de données | MySQL 8 (SQLite seulement pour les tests locaux) |
| Authentification | Laravel Sanctum, un jeton par appareil, connexion par téléphone + PIN |
| Tests | Pest |
| Qualité | Pint |

---

## Démarrer

**Prérequis :** PHP (version corrigée, voir ci-dessus), Composer, MySQL 8.

```bash
php -v                   # vérifier la version de PHP
composer install
cp .env.example .env     # renseigner la base MySQL et les secrets laissés vides
php artisan key:generate
php artisan migrate
php artisan serve        # http://localhost:8000
```

Pour déployer : même chose sur le serveur, avec ses propres valeurs dans `.env`. Toute la configuration passe par `.env` ; aucun hébergeur n'est encore choisi.

### Commandes

| Commande | Effet |
|---|---|
| `php artisan serve` | Lance l'API en local |
| `php artisan test` | Lance les tests |
| `vendor/bin/pint` | Formate le code, à lancer avant chaque commit |
| `php artisan migrate` | Applique les migrations |

---

## Tester avec Postman

L'API est exposée sous `/api/v1`. Chaque spec livre une collection Postman (ou un fichier OpenAPI que Postman importe) à jour avec ses endpoints.

1. Importer la collection de la spec dans Postman.
2. Créer un compte, puis se connecter : la réponse contient un jeton.
3. Ajouter ce jeton dans l'en-tête `Authorization: Bearer <jeton>` des autres requêtes.

Les erreurs ont toujours le même format, avec un code stable (par exemple `shop.forbidden`) et sans texte destiné à l'utilisateur : c'est l'app qui traduit le code.

---

## Architecture

Le code est découpé par domaine métier. Chaque module contient ses modèles, ses actions (les règles métier), ses contrôleurs, ses validations, ses policies, ses migrations, ses routes et ses tests. Un module ne lit ni n'écrit jamais les tables d'un autre : il appelle ses actions ou écoute ses événements.

```
app/
├── Shared/          # authentification, magasin courant, format d'erreur, argent
└── Modules/
    ├── Identity/    # comptes, appareils, connexion téléphone + PIN      (006)
    ├── Shops/       # magasins                                          (006)
    ├── Catalog/     # global_products (catalogue commun), shop_products  (007)
    ├── Inventory/   # fournisseurs, réceptions, mouvements de stock     (007)
    ├── Sales/       # ventes, lignes, paiements                         (008)
    ├── Credit/      # clients, crédit client                            (008)
    ├── Reporting/   # dashboard                                         (009)
    └── Sync/        # envoi et récupération des opérations              (010)
```

Un module n'est créé que lorsqu'une spec en a besoin. Les numéros entre parenthèses suivent l'ordre de la feuille de route.

---

## Règles

Les règles complètes sont dans [`CLAUDE.md`](CLAUDE.md) et dans la [constitution](../.specify/memory/constitution.md). L'essentiel :

- **Isolation.** Chaque table d'un magasin a un `shop_id` ; un utilisateur n'accède jamais aux données d'un autre magasin.
- **Argent** en entiers dans l'unité mineure de la devise (TND : millimes).
- **Aucune modification après coup** pour les ventes, paiements, crédits et mouvements de stock : une correction est un nouvel enregistrement.
- **UUID** générés par le téléphone, stockés en `BINARY(16)`.
- **Sécurité.** Secrets uniquement dans `.env`, PIN hachés, aucune donnée personnelle dans les logs.
- **Tests** pour chaque endpoint : succès, validation, accès sans connexion, accès à un autre magasin.
