# Contributing to Enverif

Enverif is MIT licensed and developed in the open. Useful contribution tracks are Core, Plugin/Connector, Skill, Translation, UI/UX, Documentation and Testing.

## Development setup

```bash
git clone https://github.com/ShubhamTuts/enverif.git
cd enverif
composer install
cp .env.example .env
php artisan key:generate
```

Use an isolated branch/worktree. Add a failing test before changing runtime behavior. Run standalone security-contract tests, `php scripts/verify.php`, Laravel tests and the relevant database/runtime matrix before opening a pull request.

## Pull requests

Keep changes focused, document user-visible behavior, never commit credentials or private customer data, declare risk levels for external actions, preserve workspace isolation, and add English/French/Dutch keys together when using `ui.*` translations. Third-party plugins must identify their actual developer; first-party Enverif plugins use **Codefreex**.

Read [Core development](../developers/core.md), [Plugin development](../extensions/plugins.md), [Skills](../extensions/skills.md), and [Translations](translations.md).

## Choose a contribution track

| Track | Typical files | What reviewers expect |
|---|---|---|
| Core | `app/Core`, models, migrations, jobs | durable state, workspace isolation, failure recovery, tests |
| Plugin / connector | `plugins/external`, `app/Core/Connectors` | declared capabilities, encrypted secrets, connection test, action tests |
| Skill | `skills/`, `SKILL.md` | provenance, no embedded secrets, minimal capability request |
| Translation | `lang/<locale>/ui.php` | complete key parity and preserved placeholders |
| UI/UX | `resources/views`, `resources/css`, `resources/js` | responsive light/dark UI, keyboard usability, no feature regression |
| Documentation | `docs/` | commands that can be copied, hosting assumptions stated explicitly |
| Testing | `tests/`, `scripts/verify.php` | deterministic regression coverage across shared/performance modes |

## Repository setup

A contributor workstation should have PHP 8.3+, Composer, Node.js 22+, MySQL 8.x or a compatible MariaDB release, and Git. Redis is recommended when touching Performance Mode but is not required to work on the shared-hosting path.

```bash
git clone https://github.com/ShubhamTuts/enverif.git
cd enverif
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Create a MySQL database, update the local `.env`, then initialize the schema:

```bash
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Do not develop against a production Enverif database. Migrations and queue behavior are part of the product and must be safe to test destructively in local/CI environments.

## Branch and pull-request workflow

1. Synchronize your fork with `main`.
2. Create one focused branch such as `feat/hubspot-connector` or `fix/shared-host-cron-lock`.
3. For a behavior change, write the smallest regression test first and verify that it fails for the intended reason.
4. Implement the change without weakening capability policy, workspace scoping, secret storage, approval semantics, or durable recovery.
5. Update relevant operator/developer docs in the same pull request.
6. Run the release checks below.
7. Open a pull request describing the user-visible behavior, security implications, migration impact and verification performed.

## Required local verification

Run the checks that are possible on your machine before opening a pull request:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/standalone/run.php
php scripts/verify.php
php artisan test
npm run check
npm run build
php scripts/build-site.php
php scripts/check-site.php
```

For migrations, queue code, schedules, workflow execution or approvals, also test a clean schema and both supported runtime classes:

```bash
php artisan migrate:fresh --force
QUEUE_CONNECTION=database CACHE_STORE=database php artisan test
```

When Redis is available, repeat the relevant suite with `QUEUE_CONNECTION=redis` and `CACHE_STORE=redis`. GitHub CI performs the supported PHP matrix and a no-Redis shared-hosting job, so a pull request is not ready to merge until CI is green.

## Security-sensitive changes

Treat the following as security-sensitive even when the feature looks small:

- adding or changing connector actions;
- modifying capability/risk classifications;
- changing OAuth scopes, token refresh or SMTP credentials;
- touching workspace query scopes or route ownership checks;
- modifying Web Cron, workflow webhooks or approval bypasses;
- adding filesystem access, shell execution or arbitrary URLs;
- changing encryption/casts for stored credentials.

Explain the trust boundary in the pull request. Never add an “easy” bypass for approval or workspace scoping to make a test pass.

## UI contribution contract

Enverif uses one global interaction shell. New screens should reuse existing spacing, cards, forms, tables, empty states and navigation rather than introducing a disconnected mini-design system. Verify desktop and narrow/mobile widths, light and dark themes, long names, empty states, validation errors and pagination. Chat, workflow and settings screens must remain usable without horizontal page overflow.

When adding visible UI text through the primary application shell, add the corresponding English, French and Dutch `ui.*` keys in the same change. `php scripts/verify.php` checks key parity and missing references.

## Documentation contribution contract

Documentation must state who the procedure is for, prerequisites, exact configuration/commands, how to verify success, common failure modes and security implications. Do not tell shared-hosting users to “run Supervisor” or “use SSH” without providing the supported database-queue/cron path.

The GitHub Pages site is generated from `docs/`; never edit generated `site/docs/*.html` as the source of truth. Run `php scripts/build-site.php` after changing Markdown and commit the regenerated Pages tree when required by the release process.

## Attribution

Enverif is by **Codefreex**. First-party plugins maintained as part of Enverif use `developer: Codefreex`. External plugin authors must use their own real developer identity and license; do not relabel third-party work as Codefreex code.
