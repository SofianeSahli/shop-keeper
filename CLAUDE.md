# Shop Keeper — guide for Claude Code

Monorepo for a Tunisian small-shop ecosystem: a management app for small independent shops, backed by a Laravel API.

- `shop-keeper-mobile/` — merchant app (Expo, React Native, TypeScript). Its rules are in `shop-keeper-mobile/CLAUDE.md`; read it before any mobile change.
- `shop-keeper-backend/` — Laravel 13 REST API. Its rules are in `shop-keeper-backend/CLAUDE.md`; read it before any backend change.
- `specs/` — Spec Kit features. `001` to `007` are closed. Flow in `.github/skills/`: `speckit-specify` → `clarify` → `plan` → `tasks` → `analyze` → `implement`. Helper scripts are PowerShell (`.specify/scripts/powershell/`).
- `.specify/memory/constitution.md` — governing rules (version 3.1.0). Read it before any change.

## Current focus

Current: spec 009 — contacts, transactions and payments for purchases (`specs/009-suppliers-deliveries/`, reworked by the owner on 2026-11-21), backend only. Three modules: Contacts (address book, supplier and customer roles), Transactions (one model for purchases now and sales in 011), Payments (money recorded only, per contact and optionally linked to one transaction; a transaction may have many payments). Implemented on the branch (tasks T001 to T042); the first 009 code (module `Purchasing`) is removed. Spec 008 (catalog checking) is on hold: pending products are checked through the catalog file and `catalog:load`. Later specs: `010` stock movements and alerts, `011` sales and customer credit (adds the sale kind), `012` dashboard, `013` connected app and sync, `014` employees, `015` cash shifts, `016` to `018` every small business and Play Store.

## Decisions that still bind

- No PIN. Optional two-step sign-in emails a new 6-digit code at each sign-in (spec 006, `research.md` R13).
- Every API change ships with its Postman collection and contract file updated.
- All entered data is collected by the backend (constitution VIII). No hard-coded app name, API URL or colors outside config.
- One standalone app per actor (merchant, customer, wholesaler, sponsor, field agent). Never add another actor's features as a "mode" inside an app; apps talk only through the backend API and may share only a common package.

## Code style (backend and mobile)

- Never put a call inside an argument. Store the call's result in a named variable first, then pass the variable.
  - No: `return new BarcodeLookupResource($lookUpBarcode->handle($currentShop->shopId, $parsedBarcode));`
  - Yes: `$lookup = $lookUpBarcode->handle($currentShop->shopId, $parsedBarcode);` then `return new BarcodeLookupResource($lookup);`
  - This covers function, method, static and constructor calls (`new`), and `await` calls in TypeScript, also when the call sits in a ternary, a concatenation, a template string or an array inside the argument (`format('Hello '.$user->name())`, `save(['id' => $product->uuid()])`).
  - Chained calls (`$query->where('a', $value)->get()`) and closures passed as callbacks are fine. Inside a closure the rule applies again, so turn an arrow function into a full closure when it needs a variable.
  - Existing backend app code follows this. Tests and Laravel scaffolding (`config/`, `public/`, `bootstrap/`), merged migrations and the mobile app have older code that does not: apply the rule to any code you write or change there.

## Git and pull requests

- One feature or fix per branch, and exactly one commit per pull request: squash before pushing (`git reset --soft $(git merge-base main HEAD)`, then one `git commit`), amend after new changes and push with `--force-with-lease`.

## Roadmap

The owner's "Feuille de route — Écosystème Shop Keeper" doc holds the 5 phases and the order of specs.
