<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

# Shop Keeper backend — rules

Laravel API for the Shop Keeper ecosystem. It serves the merchant app (`shop-keeper-mobile/`) first, and later the customer, wholesaler and sponsor apps. Read `../.specify/memory/constitution.md` before any change; it wins over this file.

## Stack

- Laravel 13, PHP 8.3+, MySQL 8 (every environment, tests included; tests use the `shop_keeper_test` database), Redis (cache, rate limits, queue), Laravel Sanctum for API tokens.
- Everything runs in Docker through `compose.yaml`. Run PHP, Composer and Artisan inside the containers (`docker compose exec app <command>`), not on the host. Any new backend service goes in `compose.yaml`.
- PHP must be a release that fixes CVE-2026-109618 and CVE-2026-109619: at least 8.3.36, 8.4.27 or 8.5.12. Check `php -v` locally and on every server before deploying, and restart queue workers and other long-running PHP processes after a PHP update.
- Keep PHP on a supported branch; never deploy on 8.2 or older.
- Install packages with `composer require`; do not add a package without saying why in the PR.
- Never edit files under `vendor/`, and never touch `../shop-keeper-mobile/` from backend work.

## Structure

- Code is split by business domain under `app/Modules/<Domain>/` (for example `Shops`, `Catalog`, `Inventory`, `Sales`, `Credit`, `Reporting`). Cross-cutting code (auth, current shop, error format, money) lives in `app/Shared/`.
- A module owns its models, actions, controllers, form requests, resources, policies, events, migrations, routes and tests.
- In every module, controllers, form requests and resources live in a `Controllers/` folder, never `Http/`.
- A module never reads or writes another module's tables or models. It calls the other module's actions, or listens to its events. Sharing ids and foreign keys is fine.
- Create a module only when a spec needs it. No empty folders, no speculative layers.

## Code

- Controllers stay thin: validate with a Form Request, authorize with a policy, call one action, return a JSON resource.
- Business rules live in action classes, one public method each (`RecordSale::handle(...)`). Anything that writes several tables runs in a database transaction inside the action.
- Code explains itself: clear names, small functions, named constants instead of magic values. Do not write comments; rename or extract instead.
- Run `docker compose exec app vendor/bin/pint` before committing.

## Data

- Every shop-owned table has a `shop_id`, and every query is scoped to the current shop. A user must never reach another shop's data. Shared reference tables (`global_products`) are the only tables without a `shop_id`.
- Money is an integer in the currency's minor unit (TND: millimes, 3 decimals). Never floats, never decimals in PHP.
- Primary keys of records the mobile app creates offline are UUIDs generated by the client, stored as `BINARY(16)`, never as text.
- Records that carry money or stock (sales, payments, credit entries, stock movements) are never updated or deleted: a correction is a new record.
- Migrations are never edited once merged; add a new migration instead.
- A sale's stock exit is its sale lines: never write a stock movement for a sale. `stock_movements` holds only receptions, adjustments and losses.
- Technical sync tables (received operations, change log) are pruned once every device has synced, after 30 to 90 days. They are never a source of business history.

## Catalog

- Products live in two tables. `global_products` is the shared catalog: a translation key (`products.bread`), a status (`verified` or `pending`), an optional barcode (unique when present), later brand, format and category; no price, no stock, no `shop_id`. Its names in French, Arabic and English, plus other spellings for search, are stored in the backend with the catalog, never in the apps' translation files, so a new catalog product needs no app release. `shop_products` is a shop's own product: `shop_id`, its own name (always), an optional `global_product_id`, sale price and purchase price in millimes; stock comes with the stock-movements spec. The barcode lives only in `global_products`.
- A merchant scans a barcode: if it is in `global_products`, the app only asks for the sale price and creates the `shop_products` row. A shop has at most one `shop_products` row per global product.
- An unknown barcode creates a `global_products` row with status `pending`, usable at once by the shop that created it. Another shop scanning the same barcode is linked to that same row (the barcode stays unique) and types its own product name until the row is `verified`; only `verified` rows fill in the details for other shops.
- The catalog also holds the most common products without a barcode (vegetables, fruit, bread, eggs…), seeded from a catalog file by an operator command. A product without a barcode that is not in the catalog is a `shop_products` row with no `global_product_id`. In-store barcodes (13 digits starting with `2`) never enter the catalog.
- Adding a product by name: suggestions search all three languages and other spellings, ignoring case, accents and Arabic letter variants. A creation whose name is close to an existing product is held until the merchant confirms it is different; the same name twice in one shop is refused even then (spec 007).
- Shops never edit a `global_products` row; their own name or price stays on `shop_products`.
- To decide later: who verifies `pending` global products (owner review queue, agreement between several shops, external source). Until then, do not build verification logic.

## Sync

- The mobile app works offline and syncs through an operation log: it pushes the operations it recorded (`POST /api/v1/sync/push`), then pulls what changed on the server (`GET /api/v1/sync/pull?since=<cursor>`).
- Each operation has a client-generated UUID and a type (`sale.record`, `stock.receive`). Pushing the same operation twice applies it once.
- An operation is applied by the same action as the matching REST endpoint, with the same validation and policies. There is no separate sync logic for business rules.
- Each operation gets its own result: `applied`, `duplicate`, or `rejected` with a stable error code the app shows to the merchant. One rejected operation never blocks the others.
- Pull returns the shop's changes in server order since the cursor, and a new cursor.

## Hosting

- Hosting is not chosen yet. Keep the app deployable anywhere: configuration only through `.env`, no provider-specific code or service.
- Rate limits per origin use `$request->ip()` (`app/Modules/Identity/IdentityServiceProvider.php`). That is correct behind the current nginx, but a load balancer or reverse proxy in front of it needs `trustProxies` configured for that proxy, or every user shares one limit. Do this when hosting is chosen, and check it before the first production deploy.

## API

- REST under `/api/v1`, JSON only, resources for every response, cursor pagination for lists.
- Errors use one format with a stable machine code (`stock.insufficient`, `shop.forbidden`) and no user-facing text: the mobile app translates the code into French, Arabic and English.
- Authentication: Sanctum token per device; login by Google sign-in or verified email and password, rate limited, with optional two-step sign-in: a new 6-digit code emailed at each sign-in, held on the pending sign-in. There is no PIN.
- Authorization: policies on every route that touches shop data.
- Any change to an endpoint's request or response is a contract change: update the spec's contract file and the tests in the same PR.

## Tests

- Every endpoint has feature tests for the success case, validation errors, unauthenticated access, and access to another shop's data.
- Every action with a business rule has tests for that rule.
- `docker compose exec app php artisan test` and `docker compose exec app vendor/bin/pint` must pass before a task is done.
- The owner runs the Postman collection manually. Never run it automatically: no newman, no script, no CI step, and no task or spec that asks for it. Keep the collection in sync with the endpoints.

## Security

- Secrets and environment values stay in `.env`, never in code or git.
- `.env.example` mirrors `.env`: the same keys in the same order, with safe values. Non-secret settings get working defaults for the Docker services (MySQL database `shop_keeper`, `APP_ENV=local`); secrets (`APP_KEY`, passwords, tokens) are left empty. Starting is: copy `.env.example` to `.env`, fill `DB_PASSWORD` and `DB_ROOT_PASSWORD`, run `docker compose up -d --build`; the `setup` service installs packages, creates `APP_KEY` and runs migrations.
- A key added to `.env` is added to `.env.example` in the same commit.
- Store only hashes of passwords, emailed codes (sign-in codes included) and invitation tokens.
- Log no personal data (names, emails, phone numbers, passwords, codes, tokens).

## Workflow

- Work only from an approved Spec Kit spec in `../specs/`, following `../.github/skills/` in order. Do not create, edit or close specs unless the owner asks.
- One feature or fix per branch, and exactly one commit per pull request.
