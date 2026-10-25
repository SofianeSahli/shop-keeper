# Shop Keeper

> Un écosystème pour aider les petits commerces indépendants (épiceries de quartier, en Tunisie d'abord) à suivre leurs produits, leurs clients, le crédit (*kridi*), les fournisseurs et les prix.

Le projet est un **monorepo** qui contient deux applications indépendantes et les spécifications qui les pilotent.

| Dossier | Rôle | Stack | État |
|---|---|---|---|
| [`shop-keeper-mobile/`](shop-keeper-mobile/README.md) | Application du commerçant : catalogue, tickets, kridi, paiements, tableau de bord | React Native · Expo 57 · TypeScript · SQLite | 🟡 En cours (features 001 et 002) |
| [`shop-keeper-backend/`](shop-keeper-backend/README.md) | API REST : comptes, synchronisation, données partagées | Laravel 13 · PHP 8.3 · MySQL · Sanctum | ⚪ Squelette Laravel, pas encore de code métier |
| [`specs/`](specs/) | Spécifications fonctionnelles et plans (Spec Kit) | Markdown | Une spec par feature |

---

## Vision

Aujourd'hui, beaucoup d'épiciers notent les ventes à crédit dans un cahier. Shop Keeper remplace ce cahier par une application simple, utilisable **sans connexion** et **en arabe ou en français**, puis ouvrira peu à peu sur un écosystème plus large :

1. **Le registre du commerçant** (en cours). Produits, clients, tickets, kridi, paiements et totaux du jour, le tout sur le téléphone.
2. **Comptes et synchronisation** (à venir). Sauvegarde sur le serveur, plusieurs appareils pour une même boutique, connexion par téléphone + PIN ou par Google.
3. **Fournisseurs et prix** (à venir). Suivi des achats, des fournisseurs et de l'évolution des prix.
4. **Données agrégées** (plus tard). Des statistiques anonymisées et soumises à un consentement explicite pour aider commerçants et acheteurs. Les données kridi des clients ne sont **jamais** partagées.

---

## Architecture

```
┌──────────────────────────────┐          ┌──────────────────────────────┐
│     shop-keeper-mobile       │          │     shop-keeper-backend      │
│  Expo / React Native (TS)    │  REST    │  Laravel 13 (PHP 8.3)        │
│                              │ ───────► │                              │
│  UI ─► Redux Toolkit         │  (futur) │  Controllers ─► Services     │
│        └► services/repos     │          │  Policies (RBAC) · Sanctum   │
│            └► SQLite local   │          │  └► MySQL                    │
│  journal pending_changes     │          │  autorité finale des données │
└──────────────────────────────┘          └──────────────────────────────┘
```

- **Offline-first.** Toute action courante (ajouter un client, enregistrer une dette ou un paiement, consulter un solde) s'écrit d'abord en SQLite sur l'appareil. Elle est notée dans un journal `pending_changes` avec un UUID généré côté client.
- **Le backend fait autorité.** Une fois la synchronisation en place, le serveur validera chaque opération et appliquera les permissions avant de l'accepter.
- **Pour l'instant, tout reste local.** Les features 001 et 002 sont volontairement mono-boutique, mono-appareil et sans aucun envoi au serveur (exception prévue par la constitution).

---

## Principes clés

Ces règles viennent de la [constitution du projet](.specify/memory/constitution.md) et s'appliquent à toutes les features.

- **Simplicité.** Gros boutons, textes courts, et 3 taps maximum pour une action courante.
- **Arabe et français dès le départ**, avec RTL et LTR. Aucun texte en dur : tout passe par des fichiers JSON de traduction.
- **L'argent en entiers.** Les montants sont stockés en **millimes** (1 TND = 1 000 millimes), jamais en `float`.
- **Vie privée.** Les noms, numéros et dettes des clients restent privés à la boutique.
- **Séparation stricte** entre mobile et backend : chaque application a ses propres dépendances, tests et configuration.
- **Styles séparés de la logique.** NativeWind, et pas de `StyleSheet` ni de style inline dans les fichiers TSX.

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
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve       # http://localhost:8000
```

> Le backend n'est encore que le squelette Laravel par défaut, sans route métier. Le `.env.example` pointe sur SQLite ; la cible est MySQL.

---

## Développement piloté par les specs

Chaque feature passe par [Spec Kit](https://github.com/github/spec-kit) : `specify` → `clarify` → `plan` → `tasks` → `implement`. Les skills correspondants sont dans `.github/skills/`.

| Feature | Description | Avancement |
|---|---|---|
| [`001-kridi-credit-ledger`](specs/001-kridi-credit-ledger/spec.md) | Registre de crédit : produits, clients, tickets, paiements, soldes, hors ligne | Implémentée côté mobile (les cases de `tasks.md` ne sont pas cochées) |
| [`002-ledger-usability-dashboard`](specs/002-ledger-usability-dashboard/spec.md) | Barre d'onglets, tableau de bord du jour, scan de code-barres, création inline dans un ticket | 29 / 33 tâches |

Chaque dossier `specs/NNN-*/` contient `spec.md` (le quoi), `plan.md` (le comment), `tasks.md` (les étapes), `data-model.md`, `research.md`, `quickstart.md` et `contracts/`.

---

## Structure du dépôt

```
shop-keeper/
├── .github/skills/          # Commandes Spec Kit pour l'agent IA
├── .specify/                # Constitution, templates et scripts Spec Kit
├── specs/                   # Une spec par feature (001, 002, …)
├── shop-keeper-mobile/      # Application Expo
└── shop-keeper-backend/     # API Laravel
```

---

## Feuille de route

- [x] Registre kridi local (001)
- [ ] Ergonomie et tableau de bord (002) : 4 tâches restantes
- [ ] Authentification (téléphone + PIN, Google) via Sanctum
- [ ] API REST et synchronisation du journal `pending_changes`
- [ ] Fournisseurs, achats et suivi des prix
- [ ] Statistiques anonymisées avec consentement
