# Shop Keeper — guide for Claude Code

Monorepo for a Tunisian small-shop ecosystem: a management app for small independent shops, backed by a Laravel API.

- `shop-keeper-mobile/` — merchant app (Expo, React Native, TypeScript). Its rules are in `shop-keeper-mobile/CLAUDE.md`; read it before any mobile change.
- `shop-keeper-backend/` — Laravel 13 REST API. Its rules are in `shop-keeper-backend/CLAUDE.md`; read it before any backend change.
- `specs/` — Spec Kit features. `001` to `007` are closed. Flow in `.github/skills/`: `speckit-specify` → `clarify` → `plan` → `tasks` → `analyze` → `implement`. Helper scripts are PowerShell (`.specify/scripts/powershell/`).
- `.specify/memory/constitution.md` — governing rules (version 3.1.0). Read it before any change.

## Current focus

Next: spec 008 — catalog checking, not specified yet. Open question for the owner before `speckit-specify`: who checks `pending` catalog products. Roadmap step 1.3 is split: `007` catalog and shop products, `008` catalog checking, `009` suppliers and deliveries, `010` stock movements and alerts. Then sales `011`, dashboard `012`, connected app and sync `013`, employees `014`, cash shifts `015`, every small business and Play Store `016` to `018`.

## Decisions that still bind

- No PIN. Optional two-step sign-in emails a new 6-digit code at each sign-in (spec 006, `research.md` R13).
- Every API change ships with its Postman collection and contract file updated.
- All entered data is collected by the backend (constitution VIII). No hard-coded app name, API URL or colors outside config.
- One standalone app per actor (merchant, customer, wholesaler, sponsor, field agent). Never add another actor's features as a "mode" inside an app; apps talk only through the backend API and may share only a common package.

## Git and pull requests

- One feature or fix per branch, and exactly one commit per pull request: squash before pushing (`git reset --soft $(git merge-base main HEAD)`, then one `git commit`), amend after new changes and push with `--force-with-lease`.

## Roadmap

The owner's "Feuille de route — Écosystème Shop Keeper" doc holds the 5 phases and the order of specs.
