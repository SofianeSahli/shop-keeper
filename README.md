# Shop Keeper

> Une app de gestion pour les petits commerces indépendants (épiceries, drogueries, quincailleries, boulangeries… en Tunisie d'abord) : magasin, produits, stock, ventes, crédit client et tableau de bord, **sans matériel à acheter**.

Le projet est un **monorepo** : une API Laravel, une app par acteur (aujourd'hui l'app du commerçant), et les spécifications qui pilotent le tout.

| Dossier | Rôle | Stack | État |
|---|---|---|---|
| [`shop-keeper-mobile/`](shop-keeper-mobile/README.md) | App du commerçant : produits, tickets, crédit client, paiements, tableau de bord | Expo 58 · React Native 0.88 · TypeScript · SQLite | 🟢 Specs 001 à 005 terminées, en local sur le téléphone |
| [`shop-keeper-backend/`](shop-keeper-backend/README.md) | API REST : comptes, magasins, puis stock, ventes, synchronisation | Laravel 13 · PHP 8.3+ · MySQL 8 · Sanctum | 🟡 Phase 1 en cours : spec 006 (authentification et magasin) |
| [`specs/`](specs/) | Spécifications, plans et tâches (Spec Kit) | Markdown | Une spec par feature |

---

## Vision

Le commerçant gère toute sa boutique avec son téléphone : ventes, stock avec prix d'achat et de vente, crédit client, et plus tard ses employés et ses états de caisse. L'app fonctionne **sans connexion** et **en arabe ou en français**.

L'écosystème grandit ensuite en 5 phases, chacune ouverte par un seuil d'usage :

1. **App marchand** (en cours) : la caisse de gestion.
2. **App client et carte des prix** : le client voit son crédit et trouve un produit près de chez lui.
3. **Grossistes** : commandes, livraisons et stock du dépôt, sur le même socle.
4. **Dashboard sponsor** : prix, ruptures et flux par région.
5. **Grandes enseignes** : la même plateforme sur leur propre serveur.

Le détail est dans la feuille de route « Feuille de route — Écosystème Shop Keeper ».

---

## Architecture

```
┌──────────────────────────────┐          ┌───────────────────────────────┐
│     shop-keeper-mobile       │          │      shop-keeper-backend      │
│  Expo / React Native (TS)    │  REST    │  Laravel 13 (PHP 8.3+)        │
│                              │  /api/v1 │                               │
│  UI ─► Redux Toolkit         │ ───────► │  Modules par domaine          │
│        └► services/repos     │ ◄─────── │  Actions · Policies · Sanctum │
│            └► SQLite local   │  sync    │  └► MySQL                     │
│  journal pending_changes     │          │  autorité finale des données  │
└──────────────────────────────┘          └───────────────────────────────┘
```

- **Offline-first.** Chaque action (vente, réception, crédit, paiement) s'écrit d'abord en SQLite et dans un journal d'opérations, avec un UUID généré par le téléphone.
- **Synchronisation (à venir, spec 010).** Le téléphone envoie ses opérations, le serveur applique chacune une seule fois avec les mêmes règles que l'API, puis le téléphone récupère ce qui a changé.
- **Catalogue commun.** Les produits tunisiens sont référencés une seule fois (`global_products`) ; chaque magasin n'ajoute que son prix et son stock (`shop_products`).
- **Le backend fait autorité** sur la validation, les permissions et les données.

---

## Principes clés

Ces règles viennent de la [constitution du projet](.specify/memory/constitution.md) (version 2.0.0) et s'appliquent à toutes les features.

- **Simple pour tous.** Gros boutons, textes courts, 3 taps maximum pour une action courante, aucun matériel à acheter.
- **Arabe et français dès le départ**, avec RTL et LTR. Aucun texte en dur ; l'API renvoie des codes d'erreur, l'app les traduit.
- **L'argent en entiers**, dans l'unité mineure de la devise (TND : millimes), jamais en `float`.
- **Rien n'est modifié après coup** pour l'argent et le stock : une correction est un nouvel enregistrement.
- **Crédit client et moyen de paiement sont distincts** : une vente est réglée tout de suite ou mise à crédit, et chaque règlement a son moyen (espèces, chèque, carte, virement).
- **Données.** Les données saisies appartiennent à la plateforme (conditions d'utilisation) ; un magasin ne voit que les siennes ; tout partage avec des tiers est anonymisé selon la loi.
- **Une app indépendante par acteur**, qui ne parle aux autres qu'à travers l'API.

---

## Démarrage rapide

### Application mobile

```bash
cd shop-keeper-mobile
npm install
npx expo start          # puis « a » (Android), « i » (iOS) ou « w » (web)
```

➡️ **[Voir le README de l'application mobile](./shop-keeper-mobile/README.md)** pour les scripts, l'architecture et les captures d'écran.

### Backend

```bash
cd shop-keeper-backend
composer install
cp .env.example .env    # puis renseigner la base MySQL et les secrets
php artisan key:generate
php artisan migrate
php artisan serve       # http://localhost:8000
```

➡️ **[Voir le README du backend](./shop-keeper-backend/README.md)** pour les prérequis, la structure et les tests avec Postman.

---

## Développement piloté par les specs

Chaque feature passe par [Spec Kit](https://github.com/github/spec-kit) : `specify` → `clarify` → `plan` → `tasks` → `analyze` → `implement`. Les skills sont dans `.github/skills/`, les règles pour l'agent dans [`CLAUDE.md`](CLAUDE.md) et [`shop-keeper-backend/CLAUDE.md`](shop-keeper-backend/CLAUDE.md).

| Feature | Description | État |
|---|---|---|
| [`001-kridi-credit-ledger`](specs/001-kridi-credit-ledger/spec.md) | Produits, clients, tickets, crédit client, paiements, soldes, hors ligne | Clôturée |
| [`002-ledger-usability-dashboard`](specs/002-ledger-usability-dashboard/spec.md) | Barre d'onglets, tableau de bord du jour, scan de code-barres, création inline | Clôturée |
| [`003-ledger-visual-redesign`](specs/003-ledger-visual-redesign/spec.md) | Refonte visuelle de tous les écrans | Clôturée |
| [`004-async-feedback`](specs/004-async-feedback/spec.md) | Services asynchrones, loader, toasts, squelettes, erreurs de champ | Clôturée |
| [`005-dark-mode`](specs/005-dark-mode/spec.md) | Thème clair et sombre | Clôturée |
| `006` | Authentification et magasin (backend seul, testable avec Postman) | Prochaine |

Chaque dossier `specs/NNN-*/` contient `spec.md` (le quoi), `plan.md` (le comment), `tasks.md` (les étapes), `data-model.md`, `research.md`, `quickstart.md` et `contracts/`.

---

## Structure du dépôt

```
shop-keeper/
├── .github/skills/          # Commandes Spec Kit pour l'agent IA
├── .specify/                # Constitution, templates et scripts Spec Kit
├── specs/                   # Une spec par feature (001, 002, …)
├── shop-keeper-mobile/      # App du commerçant (Expo)
└── shop-keeper-backend/     # API Laravel
```

---

## Feuille de route de la phase 1

- [x] Socle du carnet : specs 001 à 005
- [x] Pilote APK et pivot vers la caisse de gestion
- [ ] 006 — Authentification et magasin (backend)
- [ ] 007 — Stock, prix et catalogue commun
- [ ] 008 — Ventes
- [ ] 009 — Dashboard
- [ ] 010 — App connectée et synchronisation
- [ ] 011 — Employés
- [ ] 012 — États de caisse
- [ ] 013 à 015 — Tout petit commerce et Play Store
